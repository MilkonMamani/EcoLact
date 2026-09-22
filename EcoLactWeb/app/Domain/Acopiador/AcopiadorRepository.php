<?php

namespace App\Domain\Acopiador;

interface AcopiadorRepository
{
    public function guardar(Acopiador $acopiador): Acopiador;

    public function actualizar(Acopiador $acopiador): Acopiador;

    public function buscarPorId(int $id): ?Acopiador;

    public function buscarPorDni(string $dni): ?Acopiador;

    public function buscarPorUserId(int $userId): ?Acopiador;

    /**
     * @return Acopiador[]
     */
    public function listarPorZona(int $zonaId): array;

    /**
     * @return Acopiador[]
     */
    public function listar(): array;
}
