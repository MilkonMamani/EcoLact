<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\Receta;
use App\Domain\Produccion\RecetaRepository;
use DomainException;

/**
 * "Editar receta" desde la pantalla. Decide solo qué corresponde:
 *
 *  - receta que NUNCA se usó: se edita en el mismo lugar;
 *  - receta ya usada por una producción: NO se toca su historia; se crea la
 *    siguiente versión (v2, v3...), que queda activa si la original lo estaba.
 *
 * En ambos casos el producto es el mismo y conserva su imagen. Solo si se envía
 * una imagen nueva (acción explícita "Cambiar imagen del producto") se
 * reemplaza la del producto. Nada de esto mueve inventario.
 */
final class GuardarCambiosReceta
{
    public function __construct(
        private readonly RecetaRepository $recetas,
        private readonly ActualizarReceta $actualizarReceta,
        private readonly CrearNuevaVersionReceta $crearNuevaVersion,
        private readonly CambiarImagenProducto $cambiarImagen,
    ) {}

    /**
     * @param  list<array{insumo_id: int|string, cantidad: float|int|string, unidad: string, observacion?: string|null}>  $ingredientes
     */
    public function execute(
        int $recetaId,
        ?string $descripcion,
        array $ingredientes,
        int $usuarioId,
        ?ImagenSubida $nuevaImagen = null,
    ): Receta {
        $receta = $this->recetas->buscarPorId($recetaId)
            ?? throw new DomainException('La receta indicada no existe.');

        $resultado = $this->recetas->estaEnUso($recetaId)
            ? $this->crearNuevaVersion->execute($recetaId, $descripcion, $ingredientes, $usuarioId, activar: $receta->estaActiva())
            : $this->actualizarReceta->execute($recetaId, $descripcion, $ingredientes);

        if ($nuevaImagen !== null) {
            $this->cambiarImagen->execute($receta->productoId(), $nuevaImagen);
        }

        return $resultado;
    }
}
