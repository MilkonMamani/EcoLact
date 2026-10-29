<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Compra\ComprobanteCompra;
use App\Domain\Compra\ComprobanteCompraRepository;
use App\Domain\Shared\Enums\TipoDocumentoCompra;
use App\Infrastructure\Persistence\Models\ComprobanteCompraModel;
use DateTimeImmutable;

class EloquentComprobanteCompraRepository implements ComprobanteCompraRepository
{
    public function guardar(ComprobanteCompra $comprobante): ComprobanteCompra
    {
        return $this->toEntity(ComprobanteCompraModel::create([
            'compra_insumo_id' => $comprobante->compraInsumoId(),
            'tipo' => $comprobante->tipo()->value,
            'disco' => $comprobante->disco(),
            'ruta' => $comprobante->ruta(),
            'nombre_original' => $comprobante->nombreOriginal() !== null ? mb_substr($comprobante->nombreOriginal(), 0, 150) : null,
            'mime' => $comprobante->mime(),
            'tamano' => $comprobante->tamano(),
            'procesado_ocr' => $comprobante->procesadoOcr(),
            'subido_por_user_id' => $comprobante->subidoPorUserId(),
        ]));
    }

    public function buscarPorId(int $id): ?ComprobanteCompra
    {
        $model = ComprobanteCompraModel::find($id);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function listarPorCompra(int $compraId): array
    {
        return ComprobanteCompraModel::where('compra_insumo_id', $compraId)
            ->orderBy('id')
            ->get()
            ->map(fn (ComprobanteCompraModel $model): ComprobanteCompra => $this->toEntity($model))
            ->all();
    }

    private function toEntity(ComprobanteCompraModel $model): ComprobanteCompra
    {
        return new ComprobanteCompra(
            id: $model->id,
            compraInsumoId: (int) $model->compra_insumo_id,
            tipo: TipoDocumentoCompra::tryFrom((string) $model->tipo) ?? TipoDocumentoCompra::OTRO,
            disco: $model->disco,
            ruta: $model->ruta,
            subidoPorUserId: (int) $model->subido_por_user_id,
            nombreOriginal: $model->nombre_original,
            mime: $model->mime,
            tamano: $model->tamano,
            procesadoOcr: (bool) $model->procesado_ocr,
            subidoEn: $model->created_at !== null ? DateTimeImmutable::createFromInterface($model->created_at) : null,
        );
    }
}
