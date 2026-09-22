<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Ruta\RutaDetalle;
use App\Domain\Ruta\RutaDetalleRepository;
use App\Infrastructure\Persistence\Models\RutaDetalleModel;
use Illuminate\Support\Facades\DB;

class EloquentRutaDetalleRepository implements RutaDetalleRepository
{
    public function guardar(RutaDetalle $detalle): RutaDetalle
    {
        $model = RutaDetalleModel::create($this->toModelData($detalle));

        return $this->toEntity($model);
    }

    public function actualizarUbicacion(RutaDetalle $detalle): RutaDetalle
    {
        $model = RutaDetalleModel::findOrFail($detalle->id());
        $model->fill([
            'latitud' => $detalle->latitud(),
            'longitud' => $detalle->longitud(),
            'referencia_ubicacion' => $detalle->referenciaUbicacion(),
        ]);
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?RutaDetalle
    {
        $model = RutaDetalleModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorProveedor(int $proveedorId): ?RutaDetalle
    {
        $model = RutaDetalleModel::where('proveedor_id', $proveedorId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function eliminarPorProveedor(int $proveedorId): void
    {
        RutaDetalleModel::where('proveedor_id', $proveedorId)->delete();
    }

    /**
     * @return RutaDetalle[]
     */
    public function listarPorRuta(int $rutaId): array
    {
        return RutaDetalleModel::where('ruta_id', $rutaId)
            ->orderBy('orden_visita')
            ->get()
            ->map(fn (RutaDetalleModel $model): RutaDetalle => $this->toEntity($model))
            ->all();
    }

    public function siguienteOrden(int $rutaId): int
    {
        $maximo = RutaDetalleModel::where('ruta_id', $rutaId)->max('orden_visita');

        return $maximo !== null ? ((int) $maximo) + 1 : 1;
    }

    public function compactarOrden(int $rutaId): void
    {
        DB::transaction(function () use ($rutaId): void {
            $ids = RutaDetalleModel::where('ruta_id', $rutaId)
                ->orderBy('orden_visita')
                ->pluck('id')
                ->all();

            // Mismo patrón de dos fases que reordenar(): primero se apartan
            // a valores negativos únicos y luego se asigna 1..N, para no
            // chocar contra uq_ruta_detalles_ruta_id_orden_visita.
            foreach ($ids as $id) {
                RutaDetalleModel::where('id', $id)->update(['orden_visita' => DB::raw("-{$id}")]);
            }

            foreach ($ids as $posicion => $id) {
                RutaDetalleModel::where('id', $id)->update(['orden_visita' => $posicion + 1]);
            }
        });
    }

    /**
     * @param  array<int, int>  $ordenPorDetalleId
     */
    public function reordenar(int $rutaId, array $ordenPorDetalleId): void
    {
        DB::transaction(function () use ($rutaId, $ordenPorDetalleId): void {
            // Fase 1: desplaza las filas afectadas a valores negativos únicos
            // (basados en su propio id), fuera del rango positivo, para no
            // chocar contra uq_ruta_detalles_ruta_id_orden_visita mientras
            // se aplica el orden final.
            foreach (array_keys($ordenPorDetalleId) as $detalleId) {
                RutaDetalleModel::where('id', $detalleId)
                    ->where('ruta_id', $rutaId)
                    ->update(['orden_visita' => DB::raw("-{$detalleId}")]);
            }

            // Fase 2: aplica el orden final definitivo.
            foreach ($ordenPorDetalleId as $detalleId => $orden) {
                RutaDetalleModel::where('id', $detalleId)
                    ->where('ruta_id', $rutaId)
                    ->update(['orden_visita' => $orden]);
            }
        });
    }

    private function toEntity(RutaDetalleModel $model): RutaDetalle
    {
        return new RutaDetalle(
            id: $model->id,
            rutaId: $model->ruta_id,
            proveedorId: $model->proveedor_id,
            ordenVisita: $model->orden_visita,
            latitud: $model->latitud !== null ? (float) $model->latitud : null,
            longitud: $model->longitud !== null ? (float) $model->longitud : null,
            referenciaUbicacion: $model->referencia_ubicacion,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(RutaDetalle $detalle): array
    {
        return [
            'ruta_id' => $detalle->rutaId(),
            'proveedor_id' => $detalle->proveedorId(),
            'orden_visita' => $detalle->ordenVisita(),
            'latitud' => $detalle->latitud(),
            'longitud' => $detalle->longitud(),
            'referencia_ubicacion' => $detalle->referenciaUbicacion(),
        ];
    }
}
