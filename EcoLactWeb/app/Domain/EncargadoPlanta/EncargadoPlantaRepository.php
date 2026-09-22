<?php

namespace App\Domain\EncargadoPlanta;

interface EncargadoPlantaRepository
{
    public function guardar(EncargadoPlanta $encargado): EncargadoPlanta;

    public function actualizar(EncargadoPlanta $encargado): EncargadoPlanta;

    public function buscarPorId(int $id): ?EncargadoPlanta;

    public function buscarPorDni(string $dni): ?EncargadoPlanta;

    public function buscarPorUserId(int $userId): ?EncargadoPlanta;

    /**
     * @return EncargadoPlanta[]
     */
    public function listar(): array;
}
