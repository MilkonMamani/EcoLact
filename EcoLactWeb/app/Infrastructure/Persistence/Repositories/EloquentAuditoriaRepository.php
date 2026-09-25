<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Auditoria\AuditoriaRepository;
use App\Infrastructure\Persistence\Models\AuditoriaModel;

final class EloquentAuditoriaRepository implements AuditoriaRepository
{
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
    ): void {
        AuditoriaModel::create([
            'user_id' => $userId,
            'accion' => $accion,
            'tabla_afectada' => $tablaAfectada,
            'registro_id' => $registroId,
            'datos_anteriores' => $datosAnteriores,
            'datos_nuevos' => $datosNuevos,
            'motivo' => $motivo,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
