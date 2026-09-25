<?php

namespace App\Domain\Trabajador;

use DateTimeImmutable;

interface TrabajadorRepository
{
    /** Crea o actualiza (upsert por user_id) el trabajador. */
    public function guardar(Trabajador $trabajador): Trabajador;

    public function buscarPorId(int $id): ?Trabajador;

    public function buscarPorUserId(int $userId): ?Trabajador;

    public function buscarPorQrToken(string $qrToken): ?Trabajador;

    /**
     * Todos los trabajadores actualmente ACTIVOS (sin filtrar por vigencia
     * de fecha). Usado por SincronizarTrabajadores para detectar a quien ya
     * no es pagable y debe pasar a INACTIVO.
     *
     * @return Trabajador[]
     */
    public function listarActivos(): array;

    /**
     * Trabajadores ACTIVOS y vigentes en la fecha indicada (ya ingresaron y,
     * si tienen fecha de salida, todavía no salieron). Usado por la falta
     * automática y el cierre mensual.
     *
     * @return Trabajador[]
     */
    public function listarActivosVigentesEn(DateTimeImmutable $fecha): array;
}
