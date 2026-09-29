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
     * Trabajadores de varios usuarios en una sola consulta (con sus periodos),
     * indexados por user_id. Para listados: evita una consulta por fila.
     *
     * @param  list<int>  $userIds
     * @return array<int, Trabajador>
     */
    public function buscarPorUserIds(array $userIds): array;

    /**
     * Todos los trabajadores actualmente ACTIVOS (sin filtrar por vigencia
     * de fecha). Usado por SincronizarTrabajadores para detectar a quien ya
     * no es pagable y debe pasar a INACTIVO.
     *
     * @return Trabajador[]
     */
    public function listarActivos(): array;

    /**
     * Trabajadores con algún periodo de actividad que se cruza con [inicio, fin]
     * (estén hoy activos o no), con sus periodos cargados. Usado por la falta
     * automática, que evalúa día por día con Trabajador::estaVigenteEn().
     *
     * @return Trabajador[]
     */
    public function listarConActividadEntre(DateTimeImmutable $inicio, DateTimeImmutable $fin): array;

    /**
     * Abre un periodo de actividad desde $inicio. Si ya hay uno abierto no hace
     * nada; si el último terminó el día anterior (o después), lo reabre en vez
     * de crear un tramo pegado.
     */
    public function abrirPeriodo(int $trabajadorId, DateTimeImmutable $inicio): void;

    /**
     * Cierra el periodo abierto con fecha fin $fin (inclusive). Si $fin es
     * anterior a su inicio (se desactivó antes de empezar a contar), el
     * periodo se elimina: nunca estuvo activo.
     */
    public function cerrarPeriodo(int $trabajadorId, DateTimeImmutable $fin): void;

    /**
     * Si el rol del trabajador marca asistencia (roles.controla_asistencia).
     * El ADMINISTRADOR no: cobra sueldo fijo y no tiene jornadas ni faltas.
     */
    public function controlaAsistencia(int $trabajadorId): bool;
}
