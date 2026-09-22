<?php

namespace App\Application\Zona;

use App\Domain\Zona\Zona;
use App\Domain\Zona\ZonaRepository;
use DomainException;

final class RegistrarZona
{
    public function __construct(
        private readonly ZonaRepository $zonas,
    ) {}

    public function execute(string $nombre, ?string $descripcion, string $estado): Zona
    {
        if ($this->zonas->buscarPorNombre($nombre) !== null) {
            throw new DomainException("Ya existe una zona con el nombre {$nombre}.");
        }

        return $this->zonas->guardar(new Zona(
            id: null,
            nombre: $nombre,
            descripcion: $descripcion,
            estado: $estado,
        ));
    }
}
