<?php

namespace App\Application\Pago;

use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DomainException;

/**
 * Fija la bonificación y el descuento del sueldo de un trabajador mientras
 * su pago siga PENDIENTE, desde el panel derecho de "Pagar a trabajadores".
 * El sueldo base no se toca (viene de Configuración de pagos, snapshot en
 * base_calculo); el total se recalcula aquí y es lo que luego paga
 * RegistrarPagoObligacion, sin admitir pagos parciales.
 */
final class AjustarObligacionPersonal
{
    public function __construct(private readonly ObligacionPagoRepository $obligaciones) {}

    public function execute(int $obligacionId, float $bonificacion, float $descuento): AjusteObligacionPersonalResultado
    {
        $obligacion = $this->obligaciones->buscarPorId($obligacionId)
            ?? throw new DomainException('El pago indicado no existe.');

        if ($obligacion->tipoBeneficiario() !== TipoBeneficiarioPago::PERSONAL) {
            throw new DomainException('Solo se pueden ajustar bonificaciones y descuentos de pagos de personal.');
        }

        if (! $obligacion->estaPendiente()) {
            throw new DomainException('Este pago ya fue registrado. No se puede ajustar un pago ya realizado.');
        }

        if ($bonificacion < 0.0 || $descuento < 0.0) {
            throw new DomainException('La bonificación y el descuento no pueden ser negativos.');
        }

        $acumulado = (float) ($obligacion->acumulado() ?? 0.0);
        $total = round($acumulado + $bonificacion - $descuento, 2);

        if ($total <= 0.0) {
            throw new DomainException('El total a pagar debe ser mayor a cero: revisa el descuento.');
        }

        $this->obligaciones->ajustarPersonal($obligacionId, round($bonificacion, 2), round($descuento, 2), $total);

        return new AjusteObligacionPersonalResultado(
            sueldoBase: $acumulado,
            bonificacion: round($bonificacion, 2),
            descuento: round($descuento, 2),
            total: $total,
        );
    }
}
