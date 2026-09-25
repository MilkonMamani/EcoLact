<?php

namespace App\Domain\Auditoria;

interface AuditoriaRepository
{
    /**
     * Registra una acción crítica en el historial de auditorías (tabla
     * `auditorias`, que nunca se elimina). Usado por CorregirJornada
     * (cambio manual FALTA<->PRESENTE) y por la corrección de litros de una
     * entrega. La IP y el user agent son opcionales: solo los conoce la capa HTTP.
     *
     * @param  array<string, mixed>|null  $datosAnteriores
     * @param  array<string, mixed>|null  $datosNuevos
     */
    public function registrar(
        ?int $userId,
        string $accion,
        string $tablaAfectada,
        ?int $registroId,
        ?array $datosAnteriores,
        ?array $datosNuevos,
        ?string $motivo,
        ?string $ip = null,
        ?string $userAgent = null,
    ): void;
}
