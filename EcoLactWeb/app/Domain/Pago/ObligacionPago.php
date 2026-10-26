<?php

namespace App\Domain\Pago;

use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\PeriodicidadPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Cuánto se debe pagar a un beneficiario por un periodo. Es un snapshot: el
 * monto y la base de cálculo se guardan al generarla y no cambian aunque la
 * configuración o el precio de leche cambien después.
 */
final class ObligacionPago
{
    /**
     * @param  array<string, mixed>  $baseCalculo
     */
    public function __construct(
        private readonly ?int $id,
        private readonly TipoBeneficiarioPago $tipoBeneficiario,
        private readonly ?int $proveedorId,
        private readonly ?int $userId,
        private readonly ?int $rolId,
        private readonly string $beneficiarioNombre,
        private readonly ?int $configuracionPagoId,
        private readonly ?int $liquidacionId,
        private readonly DateTimeImmutable $periodoInicio,
        private readonly DateTimeImmutable $periodoFin,
        private readonly PeriodicidadPago $periodicidad,
        private readonly string $concepto,
        private readonly array $baseCalculo,
        private readonly float $montoCalculado,
        private readonly EstadoObligacionPago $estado,
        private readonly ?int $creadoPorUserId = null,
        private readonly int $diasTrabajados = 0,
        private readonly ?float $valorDia = null,
        private readonly ?float $acumulado = null,
        private readonly ?DateTimeImmutable $fechaPagoProgramada = null,
        private readonly float $bonificacion = 0.0,
        private readonly float $descuento = 0.0,
    ) {
        if ($montoCalculado < 0.0) {
            throw new InvalidArgumentException('El monto calculado no puede ser negativo.');
        }

        if ($montoCalculado === 0.0 && ! in_array($estado, [EstadoObligacionPago::EN_CURSO, EstadoObligacionPago::NO_PAGABLE], true)) {
            throw new InvalidArgumentException('El monto de un pago pendiente debe ser mayor a cero.');
        }

        if (($tipoBeneficiario === TipoBeneficiarioPago::PROVEEDOR) !== ($proveedorId !== null)
            || ($tipoBeneficiario === TipoBeneficiarioPago::PERSONAL) !== ($userId !== null)) {
            throw new InvalidArgumentException('El beneficiario no corresponde al tipo de pago.');
        }
    }

    /** Clave que impide generar dos veces el mismo beneficiario en el mismo periodo. */
    public static function clavePara(TipoBeneficiarioPago $tipo, int $beneficiarioId, DateTimeImmutable $periodoInicio): string
    {
        return $tipo->value.':'.$beneficiarioId.':'.$periodoInicio->format('Y-m-d');
    }

    public function clave(): string
    {
        return self::clavePara($this->tipoBeneficiario, $this->proveedorId ?? $this->userId, $this->periodoInicio);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tipoBeneficiario(): TipoBeneficiarioPago
    {
        return $this->tipoBeneficiario;
    }

    public function proveedorId(): ?int
    {
        return $this->proveedorId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function rolId(): ?int
    {
        return $this->rolId;
    }

    public function beneficiarioNombre(): string
    {
        return $this->beneficiarioNombre;
    }

    public function configuracionPagoId(): ?int
    {
        return $this->configuracionPagoId;
    }

    public function liquidacionId(): ?int
    {
        return $this->liquidacionId;
    }

    public function periodoInicio(): DateTimeImmutable
    {
        return $this->periodoInicio;
    }

    public function periodoFin(): DateTimeImmutable
    {
        return $this->periodoFin;
    }

    public function periodicidad(): PeriodicidadPago
    {
        return $this->periodicidad;
    }

    public function concepto(): string
    {
        return $this->concepto;
    }

    /**
     * @return array<string, mixed>
     */
    public function baseCalculo(): array
    {
        return $this->baseCalculo;
    }

    public function montoCalculado(): float
    {
        return $this->montoCalculado;
    }

    public function estado(): EstadoObligacionPago
    {
        return $this->estado;
    }

    public function creadoPorUserId(): ?int
    {
        return $this->creadoPorUserId;
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoObligacionPago::PENDIENTE;
    }

    public function estaEnCurso(): bool
    {
        return $this->estado === EstadoObligacionPago::EN_CURSO;
    }

    public function estaPagada(): bool
    {
        return $this->estado === EstadoObligacionPago::PAGADO;
    }

    public function diasTrabajados(): int
    {
        return $this->diasTrabajados;
    }

    public function valorDia(): ?float
    {
        return $this->valorDia;
    }

    public function acumulado(): ?float
    {
        return $this->acumulado;
    }

    public function fechaPagoProgramada(): ?DateTimeImmutable
    {
        return $this->fechaPagoProgramada;
    }

    public function bonificacion(): float
    {
        return $this->bonificacion;
    }

    public function descuento(): float
    {
        return $this->descuento;
    }
}
