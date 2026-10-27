<?php

namespace App\Http\Controllers\Api\Acopiador\Concerns;

use App\Application\Calidad\ControlCalidadEntrega;
use App\Domain\Entrega\Entrega;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\MuestreoCalidadModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use DateTimeImmutable;

/**
 * Forma única de una entrega en la API del acopiador (listado, detalle,
 * dashboard y seguimiento): nombres legibles para trabajar sin conexión
 * (el móvil no tiene que cruzar ids) y el estado de control de calidad
 * según el muestreo (ver ControlCalidadEntrega).
 *
 * `fecha_hora` es la hora REAL del acopio (la del teléfono si se registró
 * sin conexión); `fecha_registro` es cuándo llegó al servidor.
 */
trait SerializaEntregasAcopiador
{
    /**
     * @param  Entrega[]  $entregas
     * @return array<int, array<string, mixed>>
     */
    private function serializarEntregas(array $entregas): array
    {
        if ($entregas === []) {
            return [];
        }

        $ids = array_map(fn (Entrega $e): int => $e->id(), $entregas);
        $proveedorIds = array_unique(array_map(fn (Entrega $e): int => $e->proveedorId(), $entregas));
        $zonaIds = array_unique(array_map(fn (Entrega $e): int => $e->zonaId(), $entregas));

        $proveedores = ProveedorModel::whereIn('id', $proveedorIds)->get(['id', 'nombres', 'apellidos', 'dni'])->keyBy('id');
        $zonas = ZonaModel::whereIn('id', $zonaIds)->pluck('nombre', 'id');
        $muestreos = MuestreoCalidadModel::whereIn('entrega_id', $ids)->pluck('estado', 'entrega_id');
        $resultados = AnalisisCalidadModel::whereIn('entrega_id', $ids)->where('estado', 'CONFIRMADO')->pluck('resultado', 'entrega_id');
        $registradas = EntregaModel::whereIn('id', $ids)->pluck('created_at', 'id');

        return array_map(function (Entrega $e) use ($proveedores, $zonas, $muestreos, $resultados, $registradas): array {
            $proveedor = $proveedores->get($e->proveedorId());
            $muestreo = $muestreos->get($e->id());
            $resultado = $resultados->get($e->id());
            $registrada = $registradas->get($e->id());

            return [
                'id' => $e->id(),
                'proveedor_id' => $e->proveedorId(),
                'proveedor_nombre' => $proveedor === null ? null : trim($proveedor->nombres.' '.$proveedor->apellidos),
                'proveedor_dni' => $proveedor?->dni,
                'acopiador_id' => $e->acopiadorId(),
                'zona_id' => $e->zonaId(),
                'zona_nombre' => $zonas->get($e->zonaId()),
                'registrado_por_user_id' => $e->registradoPorUserId(),
                'fecha_hora' => $e->fechaHora()->format(DATE_ATOM),
                'fecha_registro' => $registrada === null ? null : (new DateTimeImmutable((string) $registrada))->format(DATE_ATOM),
                'litros' => $e->litros(),
                'metodo_identificacion' => $e->metodoIdentificacion(),
                'estado' => $e->estado()->value,
                'observacion' => $e->observacion(),
                'resultado' => $resultado,
                'muestreo_estado' => $muestreo,
                'control_calidad' => ControlCalidadEntrega::de($e->estado()->value, $muestreo, $resultado),
            ];
        }, $entregas);
    }

    /** @return array<string, mixed> */
    private function serializarEntrega(Entrega $entrega): array
    {
        return $this->serializarEntregas([$entrega])[0];
    }
}
