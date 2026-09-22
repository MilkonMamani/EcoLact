<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Pago\ReglaPagoCalidad;
use App\Domain\Pago\ReglaPagoCalidadRepository;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Infrastructure\Persistence\Models\ReglaPagoCalidadModel;
use DateTimeImmutable;

class EloquentReglaPagoCalidadRepository implements ReglaPagoCalidadRepository
{
    public function guardar(ReglaPagoCalidad $regla): ReglaPagoCalidad
    {
        $model = ReglaPagoCalidadModel::create($this->toModelData($regla));

        return $this->toEntity($model);
    }

    public function actualizar(ReglaPagoCalidad $regla): ReglaPagoCalidad
    {
        $model = ReglaPagoCalidadModel::findOrFail($regla->id());
        $model->fill($this->toModelData($regla));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?ReglaPagoCalidad
    {
        $model = ReglaPagoCalidadModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarVigentePorResultado(ResultadoCalidad $resultado, DateTimeImmutable $fecha): ?ReglaPagoCalidad
    {
        $fechaSql = $fecha->format('Y-m-d');

        $model = ReglaPagoCalidadModel::where('resultado_calidad', $resultado->value)
            ->where('estado', 'ACTIVO')
            ->whereDate('fecha_inicio', '<=', $fechaSql)
            ->where(function ($query) use ($fechaSql) {
                $query->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $fechaSql);
            })
            ->orderByDesc('fecha_inicio')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return ReglaPagoCalidad[]
     */
    public function listar(): array
    {
        return ReglaPagoCalidadModel::orderByDesc('fecha_inicio')
            ->get()
            ->map(fn (ReglaPagoCalidadModel $model): ReglaPagoCalidad => $this->toEntity($model))
            ->all();
    }

    private function toEntity(ReglaPagoCalidadModel $model): ReglaPagoCalidad
    {
        return new ReglaPagoCalidad(
            id: $model->id,
            resultadoCalidad: ResultadoCalidad::from($model->resultado_calidad),
            tipoAjuste: $model->tipo_ajuste,
            valorAjuste: (float) $model->valor_ajuste,
            fechaInicio: DateTimeImmutable::createFromInterface($model->fecha_inicio),
            fechaFin: $model->fecha_fin ? DateTimeImmutable::createFromInterface($model->fecha_fin) : null,
            estado: $model->estado,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(ReglaPagoCalidad $regla): array
    {
        return [
            'resultado_calidad' => $regla->resultadoCalidad()->value,
            'tipo_ajuste' => $regla->tipoAjuste(),
            'valor_ajuste' => $regla->valorAjuste(),
            'fecha_inicio' => $regla->fechaInicio()->format('Y-m-d'),
            'fecha_fin' => $regla->fechaFin()?->format('Y-m-d'),
            'estado' => $regla->estado(),
        ];
    }
}
