<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\Receta;
use App\Domain\Produccion\RecetaRepository;
use App\Domain\Shared\Enums\EstadoReceta;
use DomainException;

/**
 * Activa una versión de receta. Un producto tiene como máximo UNA receta
 * activa: al activar esta, la que estuviera activa pasa a INACTIVA (lo
 * respalda además un índice único en la base de datos).
 */
final class ActivarReceta
{
    public function __construct(
        private readonly RecetaRepository $recetas,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(int $recetaId): Receta
    {
        $receta = $this->recetas->buscarPorId($recetaId)
            ?? throw new DomainException('La receta indicada no existe.');

        if ($receta->estaActiva()) {
            return $receta;
        }

        if ($receta->detalles() === []) {
            throw new DomainException('No se puede activar una receta sin ingredientes.');
        }

        return $this->transacciones->run(function () use ($receta): Receta {
            $activaActual = $this->recetas->buscarActivaPorProducto($receta->productoId());

            if ($activaActual !== null) {
                $this->recetas->cambiarEstado($activaActual->id(), EstadoReceta::INACTIVA);
            }

            $this->recetas->cambiarEstado($receta->id(), EstadoReceta::ACTIVA);

            return $this->recetas->buscarPorId($receta->id()) ?? $receta;
        });
    }
}
