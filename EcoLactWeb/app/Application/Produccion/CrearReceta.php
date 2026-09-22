<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Produccion\Receta;
use App\Domain\Produccion\RecetaRepository;
use App\Domain\Shared\Enums\EstadoReceta;
use DomainException;

/**
 * Crea la PRIMERA receta (versión 1) de un producto que ya existe (por
 * ejemplo, un producto histórico que todavía no tenía fórmula). El nombre de
 * la receta se genera solo a partir del producto. Guardarla no consume ningún
 * insumo: una receta es solo la fórmula.
 *
 * Para crear un producto NUEVO junto con su receta, ver CrearRecetaConProducto.
 * Si el producto ya tiene recetas, se debe crear una nueva versión.
 */
final class CrearReceta
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
        int $productoId,
        ?string $descripcion,
        array $ingredientes,
        int $creadaPorUserId,
        bool $activar = true,
        float $rendimientoCantidad = 1.0,
        ?string $rendimientoUnidad = null,
    ): Receta {
        $producto = $this->productos->buscarPorId($productoId)
            ?? throw new DomainException('El producto indicado no existe.');

        if ($this->recetas->versionMaximaDelProducto($productoId) > 0) {
            throw new DomainException('Este producto ya tiene una receta. Edite su receta o cree una nueva versión.');
        }

        $detalles = $this->ingredientes->construir($ingredientes);

        return $this->transacciones->run(fn (): Receta => $this->recetas->guardar(new Receta(
            id: null,
            productoId: $productoId,
            nombre: Receta::generarNombre($producto->nombre()),
            descripcion: $descripcion,
            rendimientoCantidad: $rendimientoCantidad,
            rendimientoUnidad: $rendimientoUnidad ?? $producto->unidadMedida(),
            version: 1,
            estado: $activar ? EstadoReceta::ACTIVA : EstadoReceta::INACTIVA,
            createdByUserId: $creadaPorUserId,
            detalles: $detalles,
        )));
    }
}
