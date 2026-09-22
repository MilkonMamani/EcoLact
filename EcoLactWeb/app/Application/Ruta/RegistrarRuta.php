<?php

namespace App\Application\Ruta;

use App\Domain\Ruta\Ruta;
use App\Domain\Ruta\RutaRepository;
use App\Domain\Zona\ZonaRepository;
use DomainException;

final class RegistrarRuta
{
    public function __construct(
        private readonly RutaRepository $rutas,
        private readonly ZonaRepository $zonas,
    ) {}

    public function execute(
        int $zonaId,
        string $nombre,
        ?string $descripcion = null,
        string $estado = 'ACTIVO',
    ): Ruta {
        $zona = $this->zonas->buscarPorId($zonaId);

        if ($zona === null) {
            throw new DomainException('La zona indicada no existe.');
        }

        if ($this->rutas->buscarPorZonaYNombre($zonaId, $nombre) !== null) {
            throw new DomainException("Ya existe una ruta llamada \"{$nombre}\" en esta zona.");
        }

        return $this->rutas->guardar(new Ruta(
            id: null,
            zonaId: $zonaId,
            nombre: $nombre,
            descripcion: $descripcion,
            estado: $estado,
        ));
    }
}
