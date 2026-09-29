<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Acopiador\AcopiadorPanelData;
use App\Application\Acopiador\Contracts\AcopiadorPanelQuery;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\AcopiadorZonaHistorialModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use Carbon\CarbonImmutable;

/**
 * Panel lateral de Acopiadores (admin). Cada pestaña sale de una sola
 * consulta con sus joins (proveedor y zona de la entrega, zona del
 * historial): nada de N+1.
 */
final class EloquentAcopiadorPanelQuery implements AcopiadorPanelQuery
{
    /** Entregas más recientes que muestra la pestaña Entregas. */
    public const LIMITE = 15;

    public function obtener(int $acopiadorId): ?AcopiadorPanelData
    {
        $acopiador = AcopiadorModel::with('zona:id,nombre')->find($acopiadorId);

        if ($acopiador === null) {
            return null;
        }

        $resumenEntregas = EntregaModel::query()->toBase()
            ->where('acopiador_id', $acopiadorId)
            ->selectRaw('COUNT(*) as entregas')
            ->selectRaw('COALESCE(SUM(CASE WHEN estado <> ? THEN litros ELSE 0 END), 0) as litros', [EstadoEntrega::ANULADA->value])
            ->first();

        return new AcopiadorPanelData(
            id: (int) $acopiador->id,
            userId: (int) $acopiador->user_id,
            nombres: $acopiador->nombres,
            apellidos: $acopiador->apellidos,
            dni: $acopiador->dni,
            telefono: $acopiador->telefono,
            estado: $acopiador->estado,
            fechaIngreso: $acopiador->fecha_ingreso?->format('d/m/Y'),
            zonaId: (int) $acopiador->zona_id,
            zona: $acopiador->zona?->nombre ?? 'Zona #'.$acopiador->zona_id,
            qrToken: TrabajadorModel::where('user_id', $acopiador->user_id)->value('qr_token'),
            entregas: $this->entregas($acopiadorId),
            entregasTotal: (int) $resumenEntregas->entregas,
            litrosTotales: round((float) $resumenEntregas->litros, 2),
            historialZonas: $this->historialZonas($acopiadorId),
        );
    }

    /** @return list<array{id: int, fecha: string, proveedor: string, zona: string, litros: float, identificacion: string, estado: string}> */
    private function entregas(int $acopiadorId): array
    {
        return EntregaModel::query()->from('entregas as e')->toBase()
            ->join('proveedores as p', 'p.id', '=', 'e.proveedor_id')
            ->leftJoin('zonas as z', 'z.id', '=', 'e.zona_id')
            ->where('e.acopiador_id', $acopiadorId)
            ->orderByDesc('e.fecha_hora')
            ->orderByDesc('e.id')
            ->limit(self::LIMITE)
            ->get(['e.id', 'e.fecha_hora', 'e.litros', 'e.metodo_identificacion', 'e.estado', 'e.zona_id', 'p.nombres', 'p.apellidos', 'z.nombre as zona_nombre'])
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'fecha' => CarbonImmutable::parse($fila->fecha_hora, config('app.timezone'))->format('d/m/Y'),
                'proveedor' => trim($fila->nombres.' '.$fila->apellidos),
                'zona' => $fila->zona_nombre ?? 'Zona #'.$fila->zona_id,
                'litros' => (float) $fila->litros,
                'identificacion' => $fila->metodo_identificacion,
                'estado' => $fila->estado,
            ])
            ->all();
    }

    /**
     * Todos los tramos, del más reciente al más antiguo: cada reactivación o
     * cambio de zona abre uno nuevo y nunca se reescriben los anteriores.
     *
     * @return list<array{zona: string, desde: string, hasta: ?string, motivo: ?string}>
     */
    private function historialZonas(int $acopiadorId): array
    {
        return AcopiadorZonaHistorialModel::query()->from('acopiador_zona_historial as h')->toBase()
            ->leftJoin('zonas as z', 'z.id', '=', 'h.zona_id')
            ->where('h.acopiador_id', $acopiadorId)
            ->orderByDesc('h.fecha_inicio')
            ->orderByDesc('h.id')
            ->get(['h.zona_id', 'h.fecha_inicio', 'h.fecha_fin', 'h.motivo', 'z.nombre as zona_nombre'])
            ->map(fn (object $fila): array => [
                'zona' => $fila->zona_nombre ?? 'Zona #'.$fila->zona_id,
                'desde' => CarbonImmutable::parse($fila->fecha_inicio)->format('d/m/Y'),
                'hasta' => $fila->fecha_fin !== null ? CarbonImmutable::parse($fila->fecha_fin)->format('d/m/Y') : null,
                'motivo' => $fila->motivo,
            ])
            ->all();
    }
}
