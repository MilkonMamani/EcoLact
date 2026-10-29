<?php

namespace App\Application\Pago\Contracts;

use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DateTimeImmutable;

/**
 * Saldos y vencimientos de obligaciones_pago, descontando lo registrado en
 * `pagos` hasta la fecha de corte. Saldo de una obligación = monto_calculado
 * (ya incluye bonificaciones, descuentos y compras en planilla) − pagos
 * aplicados. NO_PAGABLE nunca cuenta; una obligación de personal EN_CURSO
 * tampoco (su mes aún no cierra: su devengo lo aporta CalcularDevengoPersonal).
 */
interface SaldosObligacionesQuery
{
    /**
     * Saldo reconocido y no pagado a la fecha de corte (proveedor: semana ya
     * iniciada; personal: mes ya cerrado). "Vencido" = su fecha programada ya pasó.
     *
     * @return array{pendiente: float, obligaciones: int, vencido: float, vencidas: int}
     */
    public function saldoAl(TipoBeneficiarioPago $tipo, DateTimeImmutable $corte): array;

    /**
     * Obligaciones cuya fecha_pago_programada cae entre ambas fechas (inclusive).
     *
     * @return array{importe: float, pagado: float, porPagar: float, obligaciones: int}
     */
    public function programadoEntre(TipoBeneficiarioPago $tipo, DateTimeImmutable $desde, DateTimeImmutable $hasta, DateTimeImmutable $corte): array;
}
