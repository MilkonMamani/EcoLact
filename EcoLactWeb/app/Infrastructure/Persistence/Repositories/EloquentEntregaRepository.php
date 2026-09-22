<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Entrega\Entrega;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Infrastructure\Persistence\Models\EntregaModel;
use DateTimeImmutable;

class EloquentEntregaRepository implements EntregaRepository
{
    public function guardar(Entrega $entrega): Entrega
    {
        $model = EntregaModel::create($this->toModelData($entrega));

        return $this->toEntity($model);
    }

    public function actualizar(Entrega $entrega): Entrega
    {
        $model = EntregaModel::findOrFail($entrega->id());
        $model->fill($this->toModelData($entrega));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?Entrega
    {
        $model = EntregaModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorTokenIdempotencia(string $token): ?Entrega
    {
        $model = EntregaModel::where('token_idempotencia', $token)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return Entrega[]
     */
    public function listarPorProveedor(int $proveedorId): array
    {
        return EntregaModel::where('proveedor_id', $proveedorId)
            ->get()
            ->map(fn (EntregaModel $model): Entrega => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Entrega[]
     */
    public function listarPorZona(int $zonaId): array
    {
        return EntregaModel::where('zona_id', $zonaId)
            ->get()
            ->map(fn (EntregaModel $model): Entrega => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Entrega[]
     */
    public function listar(): array
    {
        return EntregaModel::orderByDesc('fecha_hora')
            ->get()
            ->map(fn (EntregaModel $model): Entrega => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Entrega[]
     */
    public function listarPorAcopiador(int $acopiadorId): array
    {
        return EntregaModel::where('acopiador_id', $acopiadorId)
            ->orderByDesc('fecha_hora')
            ->get()
            ->map(fn (EntregaModel $model): Entrega => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Entrega[]
     */
    public function listarNoAnuladasDesde(DateTimeImmutable $desde): array
    {
        return EntregaModel::where('estado', '<>', EstadoEntrega::ANULADA->value)
            ->where('fecha_hora', '>=', $desde->format('Y-m-d H:i:s'))
            ->orderBy('fecha_hora')
            ->get()
            ->map(fn (EntregaModel $model): Entrega => $this->toEntity($model))
            ->all();
    }

    private function toEntity(EntregaModel $model): Entrega
    {
        return new Entrega(
            id: $model->id,
            proveedorId: $model->proveedor_id,
            acopiadorId: $model->acopiador_id,
            zonaId: $model->zona_id,
            registradoPorUserId: $model->registrado_por_user_id,
            fechaHora: DateTimeImmutable::createFromInterface($model->fecha_hora),
            litros: (float) $model->litros,
            metodoIdentificacion: $model->metodo_identificacion,
            estado: EstadoEntrega::from($model->estado),
            observacion: $model->observacion,
            recorridoDetalleId: $model->recorrido_detalle_id,
            tokenIdempotencia: $model->token_idempotencia,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(Entrega $entrega): array
    {
        return [
            'proveedor_id' => $entrega->proveedorId(),
            'acopiador_id' => $entrega->acopiadorId(),
            'zona_id' => $entrega->zonaId(),
            'registrado_por_user_id' => $entrega->registradoPorUserId(),
            'fecha_hora' => $entrega->fechaHora()->format('Y-m-d H:i:s'),
            'litros' => $entrega->litros(),
            'metodo_identificacion' => $entrega->metodoIdentificacion(),
            'estado' => $entrega->estado()->value,
            'observacion' => $entrega->observacion(),
            'recorrido_detalle_id' => $entrega->recorridoDetalleId(),
            'token_idempotencia' => $entrega->tokenIdempotencia(),
        ];
    }
}
