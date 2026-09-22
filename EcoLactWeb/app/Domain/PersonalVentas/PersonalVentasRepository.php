<?php

namespace App\Domain\PersonalVentas;

interface PersonalVentasRepository
{
    public function guardar(PersonalVentas $personal): PersonalVentas;

    public function actualizar(PersonalVentas $personal): PersonalVentas;

    public function buscarPorId(int $id): ?PersonalVentas;

    public function buscarPorDni(string $dni): ?PersonalVentas;

    public function buscarPorUserId(int $userId): ?PersonalVentas;

    /**
     * @return PersonalVentas[]
     */
    public function listar(): array;
}
