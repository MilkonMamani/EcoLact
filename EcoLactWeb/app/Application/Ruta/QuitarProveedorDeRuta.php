<?php

namespace App\Application\Ruta;

use App\Application\Proveedor\ActualizarProveedor;
use App\Application\Shared\TransactionManager;
use App\Domain\Ruta\ProveedorRutaHistorialRepository;
use App\Domain\Ruta\RutaDetalleRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Retira a un proveedor de su ruta vigente (sin asignarlo a otra): cierra el
 * historial de ruta y limpia la caché de zona del proveedor (queda sin
 * zona/ruta hasta que se le asigne una nueva desde el módulo Zonas). Los
 * órdenes de visita de los proveedores que permanecen en la ruta se
 * compactan a 1..N sin huecos. El proveedor NO se elimina: solo su
 * asignación (y con ella su punto de recolección).
 */
final class QuitarProveedorDeRuta
{
    public function __construct(
        private readonly RutaDetalleRepository $rutaDetalles,
        private readonly ProveedorRutaHistorialRepository $rutaHistorial,
        private readonly ActualizarProveedor $actualizarProveedor,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(int $proveedorId, ?string $motivo = null): void
    {
        $asignacionVigente = $this->rutaDetalles->buscarPorProveedor($proveedorId);

        if ($asignacionVigente === null) {
            throw new DomainException('El proveedor no tiene una asignación vigente en ninguna ruta.');
        }

        $rutaId = $asignacionVigente->rutaId();

        $this->transacciones->run(function () use ($proveedorId, $motivo, $rutaId): void {
            $this->rutaDetalles->eliminarPorProveedor($proveedorId);
            $this->rutaDetalles->compactarOrden($rutaId);
            $this->rutaHistorial->cerrarVigente($proveedorId, new DateTimeImmutable);

            $this->actualizarProveedor->execute(
                proveedorId: $proveedorId,
                limpiarZona: true,
                motivoCambioZona: $motivo,
            );
        });
    }
}
