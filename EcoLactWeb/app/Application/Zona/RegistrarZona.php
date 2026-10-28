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
        $equivalente = $this->zonas->buscarEquivalente($nombre);

        if ($equivalente !== null) {
            throw new DomainException("Ya existe una zona equivalente con el nombre {$equivalente->nombre()}.");
        }

        return $this->zonas->guardar(new Zona(
            id: null,
            nombre: $nombre,
            descripcion: $descripcion,
            estado: $estado,
        ));
    }
}
