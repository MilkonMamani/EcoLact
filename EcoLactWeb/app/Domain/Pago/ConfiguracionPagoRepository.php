<?php

namespace App\Domain\Pago;

use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DateTimeImmutable;

interface ConfiguracionPagoRepository
{
    public function guardar(ConfiguracionPago $configuracion): ConfiguracionPago;

    public function actualizar(ConfiguracionPago $configuracion): ConfiguracionPago;

    public function buscarPorId(int $id): ?ConfiguracionPago;

    /**
     * Configuración ACTIVA vigente en la fecha, para el proveedor (rol nulo)
     * o para un rol del personal (el sueldo base que heredan todos sus
     * trabajadores). Las filas individuales por trabajador (user_id) son
     * historial del modelo anterior y nunca se devuelven.
     */
    public function buscarVigente(TipoBeneficiarioPago $tipo, ?int $rolId, DateTimeImmutable $fecha): ?ConfiguracionPago;

    /**
     * Configuraciones ACTIVAS de la misma clave (tipo + rol) cuya vigencia se
     * cruza con [$desde, $hasta] ($hasta nulo = sin fin), excepto
     * $excluirId. Sirve para validar solapamientos: bloquea las filas leídas
     * (FOR UPDATE) para que dos guardados simultáneos no abran dos vigencias.
     *
     * @return ConfiguracionPago[]
     */
    public function listarSolapadas(TipoBeneficiarioPago $tipo, ?int $rolId, DateTimeImmutable $desde, ?DateTimeImmutable $hasta, ?int $excluirId = null): array;

    /**
     * Todas las versiones de una clave (tipo + rol), la más reciente primero.
     *
     * @return ConfiguracionPago[]
     */
    public function listarHistorial(TipoBeneficiarioPago $tipo, ?int $rolId): array;

    /**
     * Configuraciones activas de un tipo, de todas las versiones.
     *
     * @return ConfiguracionPago[]
     */
    public function listarActivasPorTipo(TipoBeneficiarioPago $tipo): array;

    public function tieneObligaciones(int $configuracionId): bool;
}
