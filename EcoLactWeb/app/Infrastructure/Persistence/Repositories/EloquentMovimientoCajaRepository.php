<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Caja\MovimientoCaja;
use App\Domain\Caja\MovimientoCajaRepository;
use App\Domain\Shared\Enums\CategoriaMovimientoCaja;
use App\Infrastructure\Persistence\Models\MovimientoCajaModel;
use DateTimeImmutable;

class EloquentMovimientoCajaRepository implements MovimientoCajaRepository
{
    /**
     * La llave única (referencia_tipo, referencia_id) es la garantía final: un
     * reintento que llegue hasta aquí recibe el movimiento ya existente.
     */
    public function guardarSiNoExiste(MovimientoCaja $movimiento): MovimientoCaja
    {
        $model = MovimientoCajaModel::firstOrCreate(
            [
                'referencia_tipo' => $movimiento->referenciaTipo(),
                'referencia_id' => $movimiento->referenciaId(),
            ],
            [
                'tipo' => $movimiento->tipo()->value,
                'categoria' => $movimiento->categoria()->value,
                'monto' => $movimiento->monto(),
                'concepto' => $movimiento->concepto(),
                'descripcion' => $movimiento->descripcion(),
                'referencia' => $movimiento->referencia(),
                'detalle' => $movimiento->detalle() !== [] ? $movimiento->detalle() : null,
                'registrado_por_user_id' => $movimiento->registradoPorUserId(),
                'fecha' => $movimiento->fecha()->format('Y-m-d H:i:s'),
            ],
        );

        return $this->toEntity($model);
    }

    public function buscarPorReferencia(string $referenciaTipo, int $referenciaId): ?MovimientoCaja
    {
        $model = MovimientoCajaModel::where('referencia_tipo', $referenciaTipo)
            ->where('referencia_id', $referenciaId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    private function toEntity(MovimientoCajaModel $model): MovimientoCaja
    {
        return new MovimientoCaja(
            id: $model->id,
            categoria: CategoriaMovimientoCaja::from($model->categoria),
            monto: (float) $model->monto,
            concepto: $model->concepto,
            descripcion: $model->descripcion,
            referencia: $model->referencia,
            detalle: $model->detalle ?? [],
            referenciaTipo: $model->referencia_tipo,
            referenciaId: (int) $model->referencia_id,
            registradoPorUserId: (int) $model->registrado_por_user_id,
            fecha: DateTimeImmutable::createFromInterface($model->fecha),
        );
    }
}
