<?php

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Role;

interface RoleRepository
{
    public function buscarPorId(int $id): ?Role;

    public function buscarPorNombre(string $nombre): ?Role;

    /**
     * Roles del personal que cobran sueldo (roles.es_remunerable), ordenados por nombre.
     *
     * @return Role[]
     */
    public function listarRemunerables(): array;
}
