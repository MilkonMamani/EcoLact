<?php

namespace App\Application\Pago;

use DateTimeImmutable;

/**
 * Genera, de forma IDEMPOTENTE, los pagos pendientes que corresponden hoy:
 * el sueldo mensual del personal y los pagos pendientes de los proveedores
 * (que normalmente ya se sincronizan solos al registrar una entrega o confirmar
 * un análisis; esto es el respaldo administrativo). Se puede ejecutar cuantas veces se quiera (scheduler,
 * comando manual, botón "Actualizar pendientes"): nunca duplica un
 * beneficiario en un mismo periodo.
 *
 * Los pagos NO se crean a mano: solo nacen de una configuración de pago, un
 * periodo y la actividad (personal activo / entregas de leche).
 */
final class GenerarPagosPendientes
{
    public function __construct(
        private readonly GenerarObligacionesPersonal $personal,
        private readonly SincronizarPagosProveedor $proveedores,
    ) {}

    public function execute(?DateTimeImmutable $hoy = null, ?int $actorUserId = null): ResultadoGeneracionPagos
    {
        $hoy ??= DateTimeImmutable::createFromInterface(today());

        return $this->personal->execute($hoy, $actorUserId)
            ->fusionar($this->proveedores->execute(null, $actorUserId));
    }
}
