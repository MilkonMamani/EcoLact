<?php

namespace App\Domain\Pago;

use DateTimeImmutable;

interface ObligacionPagoRepository
{
    /**
     * Guarda la obligación salvo que ya exista otra con la misma clave de
     * unicidad (beneficiario + periodo); en ese caso devuelve null. Es lo que
     * hace idempotente la generación de pagos pendientes.
     */
    public function guardarSiNoExiste(ObligacionPago $obligacion): ?ObligacionPago;

    public function buscarPorId(int $id): ?ObligacionPago;

    /**
     * Igual que buscarPorId, pero bloqueando la fila hasta el fin de la
     * transacción para que dos pagos simultáneos no la paguen dos veces.
     */
    public function buscarPorIdBloqueando(int $id): ?ObligacionPago;

    public function existePorClave(string $clave): bool;

    public function marcarComoPagada(int $id): void;

    /**
     * Fija la bonificación y el descuento de una obligación de personal
     * PENDIENTE y recalcula su monto_calculado (sueldo base + bonificación -
     * descuento). Quien llama (AjustarObligacionPersonal) ya validó que la
     * obligación es de tipo PERSONAL y está pendiente.
     */
    public function ajustarPersonal(int $id, float $bonificacion, float $descuento, float $montoCalculado): void;

    /**
     * Elimina las obligaciones de personal PENDIENTES (nunca pagadas) del rol
     * y los usuarios indicados. Solo se usa para limpiar huérfanos sin ficha
     * real; nunca alcanza una obligación PAGADA, sea cual sea el usuario.
     *
     * @param  list<int>  $userIds
     */
    public function eliminarPersonalPendienteDeUsuarios(int $rolId, array $userIds): int;

    /** La ventana de personal actualmente abierta (EN_CURSO o PENDIENTE) del usuario, si existe. */
    public function buscarPersonalAbiertaPorUsuario(int $userId): ?ObligacionPago;

    /** Igual, pero bloqueando la fila para registrar una jornada de forma segura. */
    public function buscarPersonalAbiertaPorUsuarioBloqueando(int $userId): ?ObligacionPago;

    /** La obligación de personal más reciente (por periodo_inicio) del usuario, sea cual sea su estado. */
    public function buscarPersonalMasRecientePorUsuario(int $userId): ?ObligacionPago;

    /** La obligación de personal del usuario cuyo periodo empieza exactamente en $periodoInicio, si existe (navegación de meses en el perfil). */
    public function buscarPersonalPorUsuarioYPeriodo(int $userId, DateTimeImmutable $periodoInicio): ?ObligacionPago;

    /**
     * Recalcula el progreso (días trabajados/valor por día/acumulado) de una
     * obligación de personal EN_CURSO y recalcula monto_calculado (acumulado
     * + bonificación - descuento). Nunca cambia el estado (el cierre
     * EN_CURSO->PENDIENTE es exclusivo de cerrarMesPersonal) ni toca una
     * obligación que ya no esté EN_CURSO: protege el historial.
     */
    public function actualizarProgresoPersonal(
        int $id,
        int $diasTrabajados,
        float $valorDia,
        float $acumulado,
    ): void;

    /**
     * Obligaciones de personal EN_CURSO cuyo periodo ya terminó (periodo_fin
     * < $hoy): las que el cierre mensual debe cerrar.
     *
     * @return ObligacionPago[]
     */
    public function listarPersonalEnCursoVencidas(DateTimeImmutable $hoy): array;

    /**
     * Cierra el mes de una obligación de personal: fija el acumulado final,
     * la transiciona EN_CURSO->PENDIENTE y fija su fecha de pago programada.
     * No hace nada si ya no está EN_CURSO.
     */
    public function cerrarMesPersonal(
        int $id,
        int $diasTrabajados,
        float $valorDia,
        float $acumulado,
        DateTimeImmutable $fechaPagoProgramada,
    ): void;
}
