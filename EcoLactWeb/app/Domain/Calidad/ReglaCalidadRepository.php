<?php

namespace App\Domain\Calidad;

use DateTimeImmutable;

interface ReglaCalidadRepository
{
    public function guardar(ReglaCalidad $regla): ReglaCalidad;

    public function actualizar(ReglaCalidad $regla): ReglaCalidad;

    public function buscarPorId(int $id): ?ReglaCalidad;

    public function buscarVigentePorParametro(int $parametroId, DateTimeImmutable $fecha): ?ReglaCalidad;

    /**
     * @return ReglaCalidad[]
     */
    public function listar(): array;

    /**
     * @return ReglaCalidad[]
     */
    public function listarPorParametro(int $parametroId): array;
}
