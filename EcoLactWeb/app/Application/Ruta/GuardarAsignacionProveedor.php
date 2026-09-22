<?php

namespace App\Application\Ruta;

use App\Domain\Ruta\RutaDetalle;
use App\Domain\Ruta\RutaDetalleRepository;
use App\Domain\Ruta\RutaRepository;
use DomainException;

/**
 * Punto de entrada del modal "Asignar proveedor a zona y ruta" (módulo
 * Zonas). Cubre los dos casos con una sola operación:
 *
 * - El proveedor NO estaba en la ruta indicada (sin asignación, o en otra
 *   ruta/zona): lo agrega o lo traslada con su punto de recolección,
 *   delegando en AsignarProveedorARuta (historial, caché de zona y
 *   compactación de órdenes incluidos).
 * - El proveedor YA estaba en esa misma ruta ("Editar asignación" sin
 *   cambiar de ruta): solo actualiza su ubicación/referencia, sin tocar su
 *   orden de visita ni el historial.
 *
 * Así la restricción "un proveedor no se duplica en la misma ruta" sigue
 * viva en AsignarProveedorARuta (que sigue rechazando el duplicado) y el
 * flujo de edición no necesita una segunda vía de escritura.
 */
final class GuardarAsignacionProveedor
{
    public function __construct(
        private readonly RutaRepository $rutas,
        private readonly RutaDetalleRepository $rutaDetalles,
        private readonly AsignarProveedorARuta $asignarProveedorARuta,
    ) {}

    public function execute(
        int $proveedorId,
        int $rutaId,
        ?float $latitud = null,
        ?float $longitud = null,
        ?string $referenciaUbicacion = null,
        ?string $motivo = null,
    ): RutaDetalle {
        $vigente = $this->rutaDetalles->buscarPorProveedor($proveedorId);

        if ($vigente === null || $vigente->rutaId() !== $rutaId) {
            return $this->asignarProveedorARuta->execute(
                rutaId: $rutaId,
                proveedorId: $proveedorId,
                motivo: $motivo,
                latitud: $latitud,
                longitud: $longitud,
                referenciaUbicacion: $referenciaUbicacion,
            );
        }

        if ($this->rutas->buscarPorId($rutaId) === null) {
            throw new DomainException('La ruta indicada no existe.');
        }

        return $this->rutaDetalles->actualizarUbicacion(
            $vigente->conUbicacion($latitud, $longitud, $referenciaUbicacion),
        );
    }
}
