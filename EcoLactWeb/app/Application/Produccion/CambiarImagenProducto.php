<?php

namespace App\Application\Produccion;

use App\Application\Produccion\Contracts\AlmacenImagenProducto;
use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\Producto;
use App\Domain\Produccion\ProductoRepository;
use DomainException;
use Throwable;

/**
 * Acción EXPLÍCITA de reemplazar la imagen de un producto (la única forma de
 * cambiarla). Guarda la nueva, actualiza la referencia del producto y solo
 * entonces elimina la anterior, y únicamente si ningún otro producto la sigue
 * usando. Si algo falla, la imagen anterior queda intacta y se descarta la
 * nueva.
 */
final class CambiarImagenProducto
{
    public function __construct(
        private readonly ProductoRepository $productos,
        private readonly AlmacenImagenProducto $imagenes,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(int $productoId, ImagenSubida $imagen): Producto
    {
        $producto = $this->productos->buscarPorId($productoId)
            ?? throw new DomainException('El producto indicado no existe.');

        $anterior = $producto->imagen();
        $nueva = $this->imagenes->guardar($imagen->rutaTemporal, $imagen->extension, $producto->nombre());

        try {
            $actualizado = $this->transacciones->run(
                fn (): Producto => $this->productos->actualizar($producto->conImagen($nueva)),
            );
        } catch (Throwable $e) {
            $this->imagenes->eliminar($nueva);

            throw $e;
        }

        if ($anterior !== null && $anterior !== $nueva && $this->productos->contarConImagen($anterior) === 0) {
            $this->imagenes->eliminar($anterior);
        }

        return $actualizado;
    }
}
