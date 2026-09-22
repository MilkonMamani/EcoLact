<?php

namespace App\Application\Ruta;

use App\Application\Shared\CampoActualizable;
use App\Domain\Ruta\Ruta;
use App\Domain\Ruta\RutaRepository;
use DomainException;

/**
 * @param  CampoActualizable<string|null>|null  $descripcion
 */
final class ActualizarRuta
{
    public function __construct(
        private readonly RutaRepository $rutas,
    ) {}

    public function execute(
        int $rutaId,
        ?string $nombre = null,
        ?CampoActualizable $descripcion = null,
        ?string $estado = null,
    ): Ruta {
        $ruta = $this->rutas->buscarPorId($rutaId);

        if ($ruta === null) {
            throw new DomainException('La ruta indicada no existe.');
        }

        if ($nombre !== null && $nombre !== $ruta->nombre()) {
            $rutaConEseNombre = $this->rutas->buscarPorZonaYNombre($ruta->zonaId(), $nombre);

            if ($rutaConEseNombre !== null && $rutaConEseNombre->id() !== $ruta->id()) {
                throw new DomainException("Ya existe otra ruta llamada \"{$nombre}\" en esta zona.");
            }
        }

        return $this->rutas->actualizar(new Ruta(
            id: $ruta->id(),
            zonaId: $ruta->zonaId(),
            nombre: $nombre ?? $ruta->nombre(),
            descripcion: CampoActualizable::resolver($descripcion, $ruta->descripcion()),
            estado: $estado ?? $ruta->estado(),
        ));
    }
}
