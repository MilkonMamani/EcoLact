<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\Receta;
use App\Domain\Produccion\RecetaRepository;
use App\Domain\Shared\Enums\EstadoReceta;
use DomainException;

/**
 * Desactiva una receta. El producto queda sin receta activa y no se podrá
 * producir hasta activar una versión. No afecta a las producciones ya hechas.
 */
final class DesactivarReceta
{
    public function __construct(
        private readonly RecetaRepository $recetas,
    ) {}

    public function execute(int $recetaId): Receta
    {
        $receta = $this->recetas->buscarPorId($recetaId)
            ?? throw new DomainException('La receta indicada no existe.');

        if (! $receta->estaActiva()) {
            return $receta;
        }

        $this->recetas->cambiarEstado($recetaId, EstadoReceta::INACTIVA);

        return $this->recetas->buscarPorId($recetaId) ?? $receta;
    }
}
