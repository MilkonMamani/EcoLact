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
     * o para un rol del personal (solo las históricas por rol, sin trabajador).
     */
    public function buscarVigente(TipoBeneficiarioPago $tipo, ?int $rolId, DateTimeImmutable $fecha): ?ConfiguracionPago;

    /**
     * Configuraciones ACTIVAS de la misma clave (tipo + rol) cuya vigencia se
     * cruza con [$desde, $hasta] ($hasta nulo = sin fin), excepto
     * $excluirId. Sirve para validar solapamientos.
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

    /**
     * Sueldo ACTIVO vigente en la fecha para un trabajador.
     */
    public function buscarVigenteDeTrabajador(int $userId, DateTimeImmutable $fecha): ?ConfiguracionPago;

    /**
     * Sueldos ACTIVOS de un trabajador cuya vigencia se cruza con [$desde,
     * $hasta] ($hasta nulo = sin fin), excepto $excluirId.
     *
     * @return ConfiguracionPago[]
     */
    public function listarSolapadasDeTrabajador(int $userId, DateTimeImmutable $desde, ?DateTimeImmutable $hasta, ?int $excluirId = null): array;

    /**
     * Todas las vigencias de sueldo de un trabajador, la más reciente primero.
     *
     * @return ConfiguracionPago[]
     */
    public function listarHistorialDeTrabajador(int $userId): array;

    /**
     * Sueldos ACTIVOS vigentes en la fecha, de cualquier trabajador.
     *
     * @return ConfiguracionPago[]
     */
    public function listarVigentesDeTrabajadores(DateTimeImmutable $fecha): array;
}
