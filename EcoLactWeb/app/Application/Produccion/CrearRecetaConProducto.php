<?php

namespace App\Application\Produccion;

use App\Application\Produccion\Contracts\AlmacenImagenProducto;
use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\Producto;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Produccion\Receta;
use App\Domain\Produccion\UnidadMedida;
use DomainException;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * NUEVA RECETA: la receta es el origen del producto. En una sola transacción:
 *
 *   1. valida la información y los ingredientes,
 *   2. crea el producto terminado (nombre generado: "Queso Paria 1 kg"),
 *      sin stock (su saldo es la suma de sus movimientos: todavía no hay),
 *   3. guarda la imagen UNA sola vez y la asocia al producto,
 *   4. crea la receta (nombre generado: "Receta Queso Paria 1 kg") y sus
 *      ingredientes, ligada a ese producto.
 *
 * No toca el inventario de insumos ni el de productos. Si algo falla se
 * deshace todo, incluido el archivo de imagen ya guardado. Un producto es
 * único: si ya existe uno con el mismo nombre y presentación, se rechaza (la
 * fórmula se cambia editando su receta o creando una nueva versión).
 */
final class CrearRecetaConProducto
{
    public function __construct(
        private readonly ProductoRepository $productos,
        private readonly CrearReceta $crearReceta,
        private readonly ConstructorIngredientesReceta $ingredientes,
        private readonly AlmacenImagenProducto $imagenes,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @param  list<array{insumo_id: int|string, cantidad: float|int|string, unidad: string, observacion?: string|null}>  $ingredientes
     */
    public function execute(
        string $tipoProducto,
        string $nombreBase,
        float $presentacionCantidad,
        string $presentacionUnidad,
        ?string $descripcion,
        array $ingredientes,
        int $creadaPorUserId,
        ?ImagenSubida $imagen = null,
    ): Receta {
        $tipoProducto = trim($tipoProducto);
        $nombreBase = trim((string) preg_replace('/\s+/', ' ', $nombreBase));

        if ($tipoProducto === '' || $nombreBase === '') {
            throw new DomainException('Indique el tipo y el nombre del producto.');
        }

        if ($presentacionCantidad <= 0.0) {
            throw new DomainException('La presentación debe ser mayor que cero.');
        }

        if (! UnidadMedida::esValida($presentacionUnidad)) {
            throw new DomainException('Elija una unidad de presentación válida.');
        }

        $nombre = Producto::generarNombre($nombreBase, $presentacionCantidad, $presentacionUnidad);

        if ($this->productos->buscarPorNombre($nombre) !== null) {
            throw new DomainException("Ya existe el producto {$nombre}. Para cambiar su fórmula edite su receta; no se crea un producto duplicado.");
        }

        // Se valida antes de guardar la imagen para no dejar archivos por un error evitable.
        $this->ingredientes->construir($ingredientes);

        $ruta = $imagen !== null ? $this->imagenes->guardar($imagen->rutaTemporal, $imagen->extension, $nombre) : null;

        try {
            return $this->transacciones->run(function () use ($tipoProducto, $nombreBase, $nombre, $presentacionCantidad, $presentacionUnidad, $descripcion, $ingredientes, $creadaPorUserId, $ruta): Receta {
                $producto = $this->productos->guardar(new Producto(
                    id: null,
                    nombre: $nombre,
                    unidadMedida: 'unidad',
                    estado: 'ACTIVO',
                    nombreBase: $nombreBase,
                    tipoProducto: $tipoProducto,
                    presentacionCantidad: $presentacionCantidad,
                    presentacionUnidad: $presentacionUnidad,
                    imagen: $ruta,
                ));

                return $this->crearReceta->execute(
                    productoId: $producto->id() ?? throw new DomainException('No se pudo crear el producto.'),
                    descripcion: $descripcion,
                    ingredientes: $ingredientes,
                    creadaPorUserId: $creadaPorUserId,
                    rendimientoCantidad: 1.0,
                    rendimientoUnidad: 'unidad',
                );
            });
        } catch (QueryException $e) {
            $this->imagenes->eliminar($ruta);

            throw new DomainException("No se pudo guardar la receta: ya existe el producto {$nombre}.", previous: $e);
        } catch (Throwable $e) {
            $this->imagenes->eliminar($ruta);

            throw $e;
        }
    }
}
