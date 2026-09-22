<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Dashboard\Contracts\DashboardAdministradorQuery;
use App\Application\Dashboard\DashboardAdministradorData;
use App\Application\Dashboard\DashboardPeriodo;
use App\Domain\Shared\Enums\EstadoAnalisis;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\ComunicadoModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ValorizacionEntregaModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentDashboardAdministradorQuery implements DashboardAdministradorQuery
{
    public function obtener(DashboardPeriodo $periodo): DashboardAdministradorData
    {
        $acopio = $this->entregasValidas($periodo)
            ->selectRaw('COALESCE(SUM(e.litros), 0) as litros, COUNT(DISTINCT e.proveedor_id) as proveedores')
            ->first();
        $activos = ProveedorModel::where('estado', EstadoUsuario::ACTIVO->value)->count();

        $calidad = $this->analisisConfirmados($periodo)
            ->select('a.resultado')
            ->selectRaw('COUNT(*) as cantidad')
            ->selectRaw('SUM(CASE WHEN e.estado <> ? THEN e.litros ELSE 0 END) as litros', [EstadoEntrega::ANULADA->value])
            ->groupBy('a.resultado')->get()->keyBy('resultado');
        $confirmados = (int) $calidad->sum('cantidad');
        $distribucion = [];

        foreach (ResultadoCalidad::cases() as $resultado) {
            $fila = $calidad->get($resultado->value);
            $cantidad = (int) ($fila->cantidad ?? 0);
            $distribucion[] = [
                'resultado' => $resultado->value,
                'cantidad' => $cantidad,
                'porcentaje' => $this->porcentaje($cantidad, $confirmados),
                'litros' => round((float) ($fila->litros ?? 0), 2),
            ];
        }

        $produccion = $this->lotes($periodo)->select('estado')->selectRaw('COUNT(*) as cantidad')
            ->groupBy('estado')->pluck('cantidad', 'estado')->map(fn ($cantidad): int => (int) $cantidad)->all();

        return new DashboardAdministradorData(
            periodo: $periodo,
            kpis: [
                'totalAcopiado' => round((float) $acopio->litros, 2),
                'proveedoresAtendidos' => $activos > 0 ? (int) $acopio->proveedores : 0,
                'proveedoresActivos' => $activos,
                'porcentajeAtendidos' => $this->porcentaje((int) $acopio->proveedores, $activos),
                'calidadAceptada' => $this->porcentaje((int) ($calidad->get(ResultadoCalidad::ACEPTADA->value)?->cantidad ?? 0), $confirmados),
                'analisisConfirmados' => $confirmados,
                'entregasObservadas' => (int) ($calidad->get(ResultadoCalidad::OBSERVADA->value)?->cantidad ?? 0),
                'totalValorizado' => round((float) ValorizacionEntregaModel::whereBetween('fecha_calculo', $periodo->limitesSql())->sum('importe_total'), 2),
                'produccion' => array_sum($produccion),
                'produccionPorEstado' => $produccion,
            ],
            litrosPorDia: $this->litrosPorDia($periodo),
            distribucionCalidad: $distribucion,
            rendimientoZonas: $this->rendimientoZonas($periodo),
            entregasRecientes: $this->entregasRecientes($periodo),
            actividadReciente: $this->actividadReciente($periodo),
            produccionReciente: $this->produccionReciente($periodo),
            comunicaciones: $this->comunicaciones($periodo),
        );
    }

    private function entregasValidas(DashboardPeriodo $periodo): Builder
    {
        return EntregaModel::query()->from('entregas as e')->toBase()
            ->whereBetween('e.fecha_hora', $periodo->limitesSql())
            ->where('e.estado', '<>', EstadoEntrega::ANULADA->value);
    }

    private function analisisConfirmados(DashboardPeriodo $periodo): Builder
    {
        return AnalisisCalidadModel::query()->from('analisis_calidad as a')->toBase()
            ->join('entregas as e', 'e.id', '=', 'a.entrega_id')
            ->where('a.estado', EstadoAnalisis::CONFIRMADO->value)
            ->whereBetween('a.fecha_analisis', $periodo->limitesSql());
    }

    private function lotes(DashboardPeriodo $periodo): Builder
    {
        return LoteProduccionModel::query()->toBase()->whereBetween('fecha_hora_inicio', $periodo->limitesSql());
    }

    /** @return list<array{fecha: string, etiqueta: string, litros: float, entregas: int}> */
    private function litrosPorDia(DashboardPeriodo $periodo): array
    {
        return $this->entregasValidas($periodo)
            ->selectRaw('DATE(e.fecha_hora) as fecha, SUM(e.litros) as litros, COUNT(*) as entregas')
            ->groupByRaw('DATE(e.fecha_hora)')->orderBy('fecha')->get()
            ->map(fn (object $dia): array => [
                'fecha' => $dia->fecha,
                'etiqueta' => CarbonImmutable::parse($dia->fecha)->locale('es')->translatedFormat('D j M'),
                'litros' => round((float) $dia->litros, 2),
                'entregas' => (int) $dia->entregas,
            ])->all();
    }

    /** @return list<array{id: int, nombre: string, proveedores: int, litros: float, confirmados: int, calidad: float}> */
    private function rendimientoZonas(DashboardPeriodo $periodo): array
    {
        $entregas = $this->entregasValidas($periodo)->select('e.zona_id')
            ->selectRaw('COUNT(DISTINCT e.proveedor_id) as proveedores, SUM(e.litros) as litros')->groupBy('e.zona_id');
        $calidad = $this->analisisConfirmados($periodo)->select('e.zona_id')
            ->selectRaw('COUNT(*) as confirmados, SUM(CASE WHEN a.resultado = ? THEN 1 ELSE 0 END) as aceptados', [ResultadoCalidad::ACEPTADA->value])
            ->groupBy('e.zona_id');

        return ZonaModel::query()->from('zonas as z')->toBase()
            ->leftJoinSub($entregas, 'acopio', 'acopio.zona_id', '=', 'z.id')
            ->leftJoinSub($calidad, 'calidad', 'calidad.zona_id', '=', 'z.id')
            ->select('z.id', 'z.nombre', 'acopio.proveedores', 'acopio.litros', 'calidad.confirmados', 'calidad.aceptados')
            ->orderByDesc('acopio.litros')->orderBy('z.nombre')->orderBy('z.id')->get()
            ->map(fn (object $zona): array => [
                'id' => (int) $zona->id,
                'nombre' => $zona->nombre,
                'proveedores' => (int) ($zona->proveedores ?? 0),
                'litros' => round((float) ($zona->litros ?? 0), 2),
                'confirmados' => (int) ($zona->confirmados ?? 0),
                'calidad' => $this->porcentaje((int) ($zona->aceptados ?? 0), (int) ($zona->confirmados ?? 0)),
            ])->all();
    }

    /** @return list<array{id: int, fecha: string, proveedorNombre: string, zonaNombre: string, litros: float, calidad: ?string, estado: string}> */
    private function entregasRecientes(DashboardPeriodo $periodo): array
    {
        return EntregaModel::query()->from('entregas as e')->toBase()
            ->join('proveedores as p', 'p.id', '=', 'e.proveedor_id')
            ->join('zonas as z', 'z.id', '=', 'e.zona_id')
            ->leftJoin('analisis_calidad as a', function ($join): void {
                $join->on('a.entrega_id', '=', 'e.id')->where('a.estado', EstadoAnalisis::CONFIRMADO->value);
            })
            ->whereBetween('e.fecha_hora', $periodo->limitesSql())
            ->select('e.id', 'e.fecha_hora', 'p.nombres', 'p.apellidos', 'z.nombre as zona', 'e.litros', 'a.resultado', 'e.estado')
            ->orderByDesc('e.fecha_hora')->orderByDesc('e.id')->limit(6)->get()
            ->map(fn (object $entrega): array => [
                'id' => (int) $entrega->id,
                'fecha' => $this->fecha($entrega->fecha_hora),
                'proveedorNombre' => trim($entrega->nombres.' '.$entrega->apellidos),
                'zonaNombre' => $entrega->zona,
                'litros' => (float) $entrega->litros,
                'calidad' => $entrega->resultado,
                'estado' => $entrega->estado,
            ])->all();
    }

    /**
     * Auditorias no tiene escritores en Application/Infrastructure. Este feed
     * solo lee operaciones existentes; fecha_analisis no es una fecha de
     * confirmación (el esquema no guarda ese instante por separado).
     *
     * @return list<array{tipo: string, id: int, titulo: string, detalle: string, fecha: string}>
     */
    private function actividadReciente(DashboardPeriodo $periodo): array
    {
        $fuentes = [
            ['entregas', 'entrega', 'Entrega registrada', 'fecha_hora', 'registrado_por_user_id'],
            ['analisis_calidad', 'analisis', 'Análisis confirmado', 'fecha_analisis', 'confirmado_por_user_id'],
            ['liquidaciones', 'liquidacion', 'Liquidación generada', 'fecha_calculo', 'generado_por_user_id'],
            ['pagos_proveedor', 'pago', 'Pago registrado', 'fecha_pago', 'registrado_por_user_id'],
            ['comunicados', 'comunicado', 'Comunicado creado', 'created_at', 'creado_por_user_id'],
        ];
        $union = null;

        foreach ($fuentes as [$tabla, $tipo, $titulo, $fecha, $actor]) {
            $consulta = DB::table($tabla.' as evento')->leftJoin('users as u', 'u.id', '=', 'evento.'.$actor)
                ->whereBetween('evento.'.$fecha, $periodo->limitesSql())
                ->select('evento.id', 'evento.'.$fecha.' as fecha', 'u.username as detalle')
                ->selectRaw('? as tipo, ? as titulo', [$tipo, $titulo]);

            if ($tipo === 'analisis') {
                $consulta->where('evento.estado', EstadoAnalisis::CONFIRMADO->value);
            }

            $limitada = DB::query()->fromSub($consulta->orderByDesc('fecha')->orderByDesc('evento.id')->limit(5), 'eventos_'.$tipo);
            $union = $union === null ? $limitada : $union->unionAll($limitada);
        }

        return DB::query()->fromSub($union, 'actividad')->orderByDesc('fecha')->orderBy('tipo')->orderByDesc('id')->limit(5)->get()
            ->map(fn (object $evento): array => [
                'tipo' => $evento->tipo,
                'id' => (int) $evento->id,
                'titulo' => $evento->titulo,
                'detalle' => ($evento->tipo === 'analisis' ? 'Fecha del análisis · ' : '').($evento->detalle ?? 'Usuario no disponible'),
                'fecha' => $this->fecha($evento->fecha),
            ])->all();
    }

    /** @return array{id: int, codigo: string, estado: string, fecha: string, litros: float, productos: list<array{nombre: string, unidad: string, cantidad: float}>}|null */
    private function produccionReciente(DashboardPeriodo $periodo): ?array
    {
        $lote = $this->lotes($periodo)->select('id', 'codigo', 'estado', 'fecha_hora_inicio')
            ->orderByDesc('fecha_hora_inicio')->orderByDesc('id')->first();

        if ($lote === null) {
            return null;
        }

        $productos = DB::table('produccion_detalles as d')->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->where('d.lote_produccion_id', $lote->id)->select('p.nombre', 'p.unidad_medida', 'd.cantidad_planificada', 'd.cantidad_obtenida')
            ->orderBy('p.nombre')->get()->map(fn (object $producto): array => [
                'nombre' => $producto->nombre,
                'unidad' => $producto->unidad_medida,
                'cantidad' => (float) ($producto->cantidad_obtenida ?? $producto->cantidad_planificada),
            ])->all();

        return [
            'id' => (int) $lote->id,
            'codigo' => $lote->codigo,
            'estado' => $lote->estado,
            'fecha' => $this->fecha($lote->fecha_hora_inicio),
            'litros' => round((float) DB::table('lote_produccion_insumos as c')
                ->join('insumos as i', 'i.id', '=', 'c.insumo_id')
                ->where('c.lote_produccion_id', $lote->id)
                ->where('i.codigo', 'LECHE')
                ->sum('c.cantidad_usada'), 2),
            'productos' => $productos,
        ];
    }

    /** @return array{publicados: int, destinatarios: int, leidos: int, tasaLectura: ?float, recientes: list<array{id: int, titulo: string, fecha: string, alcance: string}>} */
    private function comunicaciones(DashboardPeriodo $periodo): array
    {
        $publicados = ComunicadoModel::query()->from('comunicados as c')->toBase()
            ->where('c.estado', 'PUBLICADO')->where('c.fecha_publicacion', '<=', now())
            ->whereBetween('c.fecha_publicacion', $periodo->limitesSql());
        $total = (clone $publicados)->count();
        $lectura = (clone $publicados)->join('comunicado_destinatarios as d', 'd.comunicado_id', '=', 'c.id')
            ->selectRaw('COUNT(*) as destinatarios, COALESCE(SUM(CASE WHEN d.leido = 1 THEN 1 ELSE 0 END), 0) as leidos')->first();
        $recientes = (clone $publicados)->leftJoin('zonas as z', 'z.id', '=', 'c.zona_id')
            ->select('c.id', 'c.titulo', 'c.fecha_publicacion', 'c.alcance', 'z.nombre as zona')
            ->orderByDesc('c.fecha_publicacion')->orderByDesc('c.id')->limit(3)->get()
            ->map(fn (object $comunicado): array => [
                'id' => (int) $comunicado->id,
                'titulo' => $comunicado->titulo,
                'fecha' => $this->fecha($comunicado->fecha_publicacion),
                'alcance' => $comunicado->zona ?? $comunicado->alcance,
            ])->all();

        return [
            'publicados' => $total,
            'destinatarios' => (int) $lectura->destinatarios,
            'leidos' => (int) $lectura->leidos,
            'tasaLectura' => $lectura->destinatarios > 0 ? $this->porcentaje((int) $lectura->leidos, (int) $lectura->destinatarios) : null,
            'recientes' => $recientes,
        ];
    }

    private function porcentaje(int $cantidad, int $total): float
    {
        return $total > 0 ? round($cantidad * 100 / $total, 1) : 0.0;
    }

    private function fecha(string $fecha): string
    {
        return CarbonImmutable::parse($fecha, config('app.timezone'))->format('d/m/Y H:i');
    }
}
