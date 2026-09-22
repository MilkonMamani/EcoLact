<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Produccion\Receta;
use App\Domain\Produccion\RecetaRepository;
use App\Domain\Shared\Enums\EstadoReceta;
use DomainException;

/**
 * Crea la siguiente versión de la receta de un producto (v2, v3...). Las
 * versiones anteriores se conservan intactas para que las producciones
 * históricas sigan sabiendo qué fórmula usaron. El producto NO se duplica: la
 * nueva versión apunta al mismo producto y por lo tanto usa la misma imagen.
 * Por defecto queda ACTIVA y la anterior pasa a INACTIVA.
 */
final class CrearNuevaVersionReceta
{
    public function __construct(
        private readonly RecetaRepository $recetas,
        private readonly ProductoRepository $productos,
        private readonly ConstructorIngredientesReceta $ingredientes,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @param  list<array{insumo_id: int|string, cantidad: float|int|string, unidad: string, observacion?: string|null}>  $ingredientes
     */
    public function execute(
        int $recetaBaseId,
        ?string $descripcion,
        array $ingredientes,
        int $creadaPorUserId,
        bool $activar = true,
    ): Receta {
        $base = $this->recetas->buscarPorId($recetaBaseId)
            ?? throw new DomainException('La receta indicada no existe.');

        $producto = $this->productos->buscarPorId($base->productoId())
            ?? throw new DomainException('El producto de la receta no existe.');

        $detalles = $this->ingredientes->construir($ingredientes);

        return $this->transacciones->run(function () use ($base, $producto, $descripcion, $detalles, $creadaPorUserId, $activar): Receta {
            if ($activar) {
                $activaActual = $this->recetas->buscarActivaPorProducto($base->productoId());

                if ($activaActual !== null) {
                    $this->recetas->cambiarEstado($activaActual->id(), EstadoReceta::INACTIVA);
                }
            }

            return $this->recetas->guardar(new Receta(
                id: null,
                productoId: $base->productoId(),
                nombre: Receta::generarNombre($producto->nombre()),
                descripcion: $descripcion,
                rendimientoCantidad: $base->rendimientoCantidad(),
                rendimientoUnidad: $base->rendimientoUnidad(),
                version: $this->recetas->versionMaximaDelProducto($base->productoId()) + 1,
                estado: $activar ? EstadoReceta::ACTIVA : EstadoReceta::INACTIVA,
                createdByUserId: $creadaPorUserId,
                detalles: $detalles,
            ));
        });
    }
}
