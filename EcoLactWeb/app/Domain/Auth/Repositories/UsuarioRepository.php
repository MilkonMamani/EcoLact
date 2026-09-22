<?php

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Usuario;

interface UsuarioRepository
{
    public function guardar(Usuario $usuario): Usuario;

    public function actualizar(Usuario $usuario): Usuario;

    public function buscarPorId(int $id): ?Usuario;

    public function buscarPorUsername(string $username): ?Usuario;

    /**
     * @return Usuario[]
     */
    public function listarActivosPorRole(string $nombreRol): array;
}
