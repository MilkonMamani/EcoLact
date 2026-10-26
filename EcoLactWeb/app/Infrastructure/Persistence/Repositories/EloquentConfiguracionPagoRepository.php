<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Pago\ConfiguracionPago;
use App\Domain\Pago\ConfiguracionPagoRepository;
use App\Domain\Shared\Enums\PeriodicidadPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Domain\Shared\Enums\TipoCalculoPago;
use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

class EloquentConfiguracionPagoRepository implements ConfiguracionPagoRepository
{
    public function guardar(ConfiguracionPago $configuracion): ConfiguracionPago
    {
        return $this->toEntity(ConfiguracionPagoModel::create($this->toModelData($configuracion)));
    }

    public function actualizar(ConfiguracionPago $configuracion): ConfiguracionPago
    {
        $model = ConfiguracionPagoModel::findOrFail($configuracion->id());
        $model->fill($this->toModelData($configuracion));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?ConfiguracionPago
    {
        $model = ConfiguracionPagoModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarVigente(TipoBeneficiarioPago $tipo, ?int $rolId, DateTimeImmutable $fecha): ?ConfiguracionPago
    {
        return $this->vigenteEn($this->clave($tipo, $rolId), $fecha);
    }

    public function listarSolapadas(TipoBeneficiarioPago $tipo, ?int $rolId, DateTimeImmutable $desde, ?DateTimeImmutable $hasta, ?int $excluirId = null): array
    {
        return $this->solapadas($this->clave($tipo, $rolId), $desde, $hasta, $excluirId);
    }

    public function listarHistorial(TipoBeneficiarioPago $tipo, ?int $rolId): array
    {
        return $this->historial($this->clave($tipo, $rolId));
    }

    public function listarActivasPorTipo(TipoBeneficiarioPago $tipo): array
    {
        return ConfiguracionPagoModel::where('tipo_beneficiario', $tipo->value)
            ->where('estado', 'ACTIVO')
            ->orderBy('fecha_inicio')
            ->get()
            ->map(fn (ConfiguracionPagoModel $model): ConfiguracionPago => $this->toEntity($model))
            ->all();
    }

    public function tieneObligaciones(int $configuracionId): bool
    {
        return ConfiguracionPagoModel::whereKey($configuracionId)->whereHas('obligaciones')->exists();
    }

    public function buscarVigenteDeTrabajador(int $userId, DateTimeImmutable $fecha): ?ConfiguracionPago
    {
        return $this->vigenteEn($this->deTrabajador($userId), $fecha);
    }

    public function listarSolapadasDeTrabajador(int $userId, DateTimeImmutable $desde, ?DateTimeImmutable $hasta, ?int $excluirId = null): array
    {
        return $this->solapadas($this->deTrabajador($userId), $desde, $hasta, $excluirId);
    }

    public function listarHistorialDeTrabajador(int $userId): array
    {
        return $this->historial($this->deTrabajador($userId));
    }

    public function listarVigentesDeTrabajadores(DateTimeImmutable $fecha): array
    {
        return $this->vigentes(
            ConfiguracionPagoModel::query()
                ->where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
                ->whereNotNull('user_id'),
            $fecha,
        )
            ->get()
            ->map(fn (ConfiguracionPagoModel $model): ConfiguracionPago => $this->toEntity($model))
            ->all();
    }

    private function vigenteEn(Builder $clave, DateTimeImmutable $fecha): ?ConfiguracionPago
    {
        $model = $this->vigentes($clave, $fecha)->orderByDesc('fecha_inicio')->first();

        return $model ? $this->toEntity($model) : null;
    }

    private function vigentes(Builder $clave, DateTimeImmutable $fecha): Builder
    {
        $dia = $fecha->format('Y-m-d');

        return $clave
            ->where('estado', 'ACTIVO')
            ->whereDate('fecha_inicio', '<=', $dia)
            ->where(fn (Builder $query) => $query->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $dia));
    }

    /**
     * @return ConfiguracionPago[]
     */
    private function solapadas(Builder $clave, DateTimeImmutable $desde, ?DateTimeImmutable $hasta, ?int $excluirId): array
    {
        return $clave
            ->where('estado', 'ACTIVO')
            ->when($excluirId !== null, fn (Builder $query) => $query->where('id', '<>', $excluirId))
            ->when($hasta !== null, fn (Builder $query) => $query->whereDate('fecha_inicio', '<=', $hasta->format('Y-m-d')))
            ->where(fn (Builder $query) => $query->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $desde->format('Y-m-d')))
            ->orderBy('fecha_inicio')
            ->get()
            ->map(fn (ConfiguracionPagoModel $model): ConfiguracionPago => $this->toEntity($model))
            ->all();
    }

    /**
     * @return ConfiguracionPago[]
     */
    private function historial(Builder $clave): array
    {
        return $clave
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ConfiguracionPagoModel $model): ConfiguracionPago => $this->toEntity($model))
            ->all();
    }

    /** Proveedor, o configuración histórica por rol: nunca el sueldo de un trabajador. */
    private function clave(TipoBeneficiarioPago $tipo, ?int $rolId): Builder
    {
        return ConfiguracionPagoModel::query()
            ->where('tipo_beneficiario', $tipo->value)
            ->whereNull('user_id')
            ->when(
                $rolId === null,
                fn (Builder $query) => $query->whereNull('rol_id'),
                fn (Builder $query) => $query->where('rol_id', $rolId),
            );
    }

    private function deTrabajador(int $userId): Builder
    {
        return ConfiguracionPagoModel::query()
            ->where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
            ->where('user_id', $userId);
    }

    private function toEntity(ConfiguracionPagoModel $model): ConfiguracionPago
    {
        return new ConfiguracionPago(
            id: $model->id,
            tipoBeneficiario: TipoBeneficiarioPago::from($model->tipo_beneficiario),
            rolId: $model->rol_id,
            tipoCalculo: TipoCalculoPago::from($model->tipo_calculo),
            montoBase: $model->monto_base !== null ? (float) $model->monto_base : null,
            periodicidad: PeriodicidadPago::from($model->periodicidad),
            aplicaCalidad: (bool) $model->aplica_calidad,
            fechaInicio: DateTimeImmutable::createFromInterface($model->fecha_inicio),
            fechaFin: $model->fecha_fin ? DateTimeImmutable::createFromInterface($model->fecha_fin) : null,
            estado: $model->estado,
            observacion: $model->observacion,
            creadoPorUserId: $model->creado_por_user_id,
            userId: $model->user_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(ConfiguracionPago $configuracion): array
    {
        return [
            'tipo_beneficiario' => $configuracion->tipoBeneficiario()->value,
            'rol_id' => $configuracion->rolId(),
            'user_id' => $configuracion->userId(),
            'tipo_calculo' => $configuracion->tipoCalculo()->value,
            'monto_base' => $configuracion->montoBase(),
            'periodicidad' => $configuracion->periodicidad()->value,
            'aplica_calidad' => $configuracion->aplicaCalidad(),
            'fecha_inicio' => $configuracion->fechaInicio()->format('Y-m-d'),
            'fecha_fin' => $configuracion->fechaFin()?->format('Y-m-d'),
            'estado' => $configuracion->estado(),
            'observacion' => $configuracion->observacion(),
            'creado_por_user_id' => $configuracion->creadoPorUserId(),
        ];
    }
}
