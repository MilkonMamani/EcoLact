<?php

namespace App\Domain\AnalistaCalidad;

interface AnalistaCalidadRepository
{
    public function guardar(AnalistaCalidad $analista): AnalistaCalidad;

    public function actualizar(AnalistaCalidad $analista): AnalistaCalidad;

    public function buscarPorId(int $id): ?AnalistaCalidad;

    public function buscarPorDni(string $dni): ?AnalistaCalidad;

    public function buscarPorUserId(int $userId): ?AnalistaCalidad;

    /**
     * @return AnalistaCalidad[]
     */
    public function listar(): array;
}
