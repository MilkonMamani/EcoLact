<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Pago\ObligacionPago;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\PeriodicidadPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

class EloquentObligacionPagoRepository implements ObligacionPagoRepository
{
    public function guardarSiNoExiste(ObligacionPago $obligacion): ?ObligacionPago
    {
        if ($this->existePorClave($obligacion->clave())) {
            return null;
        }

        try {
            $model = $this->crear($obligacion);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        return $this->toEntity($model);
    }

    private function crear(ObligacionPago $obligacion): ObligacionPagoModel
    {
        return ObligacionPagoModel::create([
            'tipo_beneficiario' => $obligacion->tipoBeneficiario()->value,
            'proveedor_id' => $obligacion->proveedorId(),
            'user_id' => $obligacion->userId(),
            'rol_id' => $obligacion->rolId(),
            'beneficiario_nombre' => $obligacion->beneficiarioNombre(),
            'configuracion_pago_id' => $obligacion->configuracionPagoId(),
            'liquidacion_id' => $obligacion->liquidacionId(),
            'periodo_inicio' => $obligacion->periodoInicio()->format('Y-m-d'),
            'periodo_fin' => $obligacion->periodoFin()->format('Y-m-d'),
            'periodicidad' => $obligacion->periodicidad()->value,
            'concepto' => $obligacion->concepto(),
            'base_calculo' => $obligacion->baseCalculo(),
            'monto_calculado' => $obligacion->montoCalculado(),
            'dias_trabajados' => $obligacion->diasTrabajados(),
            'valor_dia' => $obligacion->valorDia(),
            'acumulado' => $obligacion->acumulado(),
            'estado' => $obligacion->estado()->value,
            'clave_unicidad' => $obligacion->clave(),
            'creado_por_user_id' => $obligacion->creadoPorUserId(),
        ]);
    }

    public function buscarPorId(int $id): ?ObligacionPago
    {
        $model = ObligacionPagoModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorIdBloqueando(int $id): ?ObligacionPago
    {
        $model = ObligacionPagoModel::whereKey($id)->lockForUpdate()->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function existePorClave(string $clave): bool
    {
        return ObligacionPagoModel::where('clave_unicidad', $clave)->exists();
    }

    public function marcarComoPagada(int $id): void
    {
        ObligacionPagoModel::whereKey($id)->update(['estado' => EstadoObligacionPago::PAGADO->value, 'clave_abierta' => null]);
    }

    public function ajustarPersonal(int $id, float $bonificacion, float $descuento, float $montoCalculado): void
    {
        ObligacionPagoModel::whereKey($id)->update([
            'bonificacion' => $bonificacion,
            'descuento' => $descuento,
            'monto_calculado' => $montoCalculado,
        ]);
    }

    public function eliminarPersonalPendienteDeUsuarios(int $rolId, array $userIds): int
    {
        if ($userIds === []) {
            return 0;
        }

        return ObligacionPagoModel::query()
            ->where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
            ->where('estado', EstadoObligacionPago::PENDIENTE->value)
            ->where('rol_id', $rolId)
            ->whereIn('user_id', $userIds)
            ->delete();
    }

    public function buscarPersonalAbiertaPorUsuario(int $userId): ?ObligacionPago
    {
        $model = $this->personalAbierta($userId)->orderByDesc('periodo_inicio')->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPersonalAbiertaPorUsuarioBloqueando(int $userId): ?ObligacionPago
    {
        $model = $this->personalAbierta($userId)->orderByDesc('periodo_inicio')->lockForUpdate()->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPersonalMasRecientePorUsuario(int $userId): ?ObligacionPago
    {
        $model = ObligacionPagoModel::where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
            ->where('user_id', $userId)
            ->orderByDesc('periodo_inicio')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function actualizarProgresoPersonal(int $id, int $diasTrabajados, float $valorDia, float $acumulado, EstadoObligacionPago $estado): void
    {
        $model = ObligacionPagoModel::whereKey($id)->lockForUpdate()->first();

        if ($model === null || $model->estado === EstadoObligacionPago::PAGADO->value) {
            return;
        }

        $montoCalculado = round($acumulado + (float) $model->bonificacion - (float) $model->descuento, 2);

        $model->update([
            'dias_trabajados' => $diasTrabajados,
            'valor_dia' => $valorDia,
            'acumulado' => $acumulado,
            'monto_calculado' => max($montoCalculado, 0.0),
            'estado' => $estado->value,
        ]);
    }

    private function personalAbierta(int $userId): Builder
    {
        return ObligacionPagoModel::where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
            ->where('user_id', $userId)
            ->whereIn('estado', [EstadoObligacionPago::EN_CURSO->value, EstadoObligacionPago::PENDIENTE->value]);
    }

    private function toEntity(ObligacionPagoModel $model): ObligacionPago
    {
        return new ObligacionPago(
            id: $model->id,
            tipoBeneficiario: TipoBeneficiarioPago::from($model->tipo_beneficiario),
            proveedorId: $model->proveedor_id,
            userId: $model->user_id,
            rolId: $model->rol_id,
            beneficiarioNombre: $model->beneficiario_nombre,
            configuracionPagoId: $model->configuracion_pago_id,
            liquidacionId: $model->liquidacion_id,
            periodoInicio: DateTimeImmutable::createFromInterface($model->periodo_inicio),
            periodoFin: DateTimeImmutable::createFromInterface($model->periodo_fin),
            periodicidad: PeriodicidadPago::from($model->periodicidad),
            concepto: $model->concepto,
            baseCalculo: $model->base_calculo ?? [],
            montoCalculado: (float) $model->monto_calculado,
            estado: EstadoObligacionPago::from($model->estado),
            creadoPorUserId: $model->creado_por_user_id,
            diasTrabajados: (int) $model->dias_trabajados,
            valorDia: $model->valor_dia !== null ? (float) $model->valor_dia : null,
            acumulado: $model->acumulado !== null ? (float) $model->acumulado : null,
        );
    }
}
