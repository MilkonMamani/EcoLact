<?php

namespace App\Application\Ruta;

use App\Application\Proveedor\ActualizarProveedor;
use App\Application\Shared\TransactionManager;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Ruta\ProveedorRutaHistorial;
use App\Domain\Ruta\ProveedorRutaHistorialRepository;
use App\Domain\Ruta\RutaDetalle;
use App\Domain\Ruta\RutaDetalleRepository;
use App\Domain\Ruta\RutaRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Agrega un proveedor existente a una ruta (o lo traslada, si ya tenía una
 * asignación vigente en otra ruta), estableciendo su orden de visita.
 *
 * Regla inicial del proyecto: un proveedor tiene como máximo una asignación
 * vigente entre TODAS las rutas (columna única ruta_detalles.proveedor_id).
 * Por eso "agregar a una ruta" y "trasladar a otra ruta" son la misma
 * operación: si el proveedor ya estaba en otra ruta, se cierra ese
 * historial y se abre uno nuevo en la ruta destino, conservando la
 * trazabilidad completa en proveedor_ruta_historial.
 *
 * El proveedor se agrega con su punto de recolección (latitud, longitud y
 * referencia, todos opcionales). Al salir de una ruta o entrar con un orden
 * explícito, los órdenes vigentes de las rutas afectadas se compactan a
 * 1..N sin huecos.
 *
 * Reutiliza ActualizarProveedor (Use Case ya existente) para mantener
 * sincronizada la caché proveedores.zona_id y su propio historial de zona
 * (proveedor_zona_historial), en vez de duplicar esa lógica aquí.
 */
final class AsignarProveedorARuta
{
    public function __construct(
        private readonly RutaRepository $rutas,
        private readonly RutaDetalleRepository $rutaDetalles,
        private readonly ProveedorRepository $proveedores,
        private readonly ProveedorRutaHistorialRepository $rutaHistorial,
        private readonly ActualizarProveedor $actualizarProveedor,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $rutaId,
        int $proveedorId,
        ?int $ordenVisita = null,
        ?string $motivo = null,
        ?float $latitud = null,
        ?float $longitud = null,
        ?string $referenciaUbicacion = null,
    ): RutaDetalle {
        $ruta = $this->rutas->buscarPorId($rutaId);

        if ($ruta === null) {
            throw new DomainException('La ruta indicada no existe.');
        }

        if (! $ruta->estaActiva()) {
            throw new DomainException('No se puede asignar proveedores a una ruta inactiva.');
        }

        $proveedor = $this->proveedores->buscarPorId($proveedorId);

        if ($proveedor === null) {
            throw new DomainException('El proveedor indicado no existe.');
        }

        $asignacionVigente = $this->rutaDetalles->buscarPorProveedor($proveedorId);

        if ($asignacionVigente !== null && $asignacionVigente->rutaId() === $rutaId) {
            throw new DomainException('El proveedor ya está asignado a esta ruta.');
        }

        $ordenFinal = $ordenVisita ?? $this->rutaDetalles->siguienteOrden($rutaId);

        if ($ordenVisita !== null) {
            $ordenesUsados = array_map(
                fn (RutaDetalle $d): int => $d->ordenVisita(),
                $this->rutaDetalles->listarPorRuta($rutaId),
            );

            if (in_array($ordenVisita, $ordenesUsados, true)) {
                throw new DomainException("Ya existe un proveedor en el orden {$ordenVisita} de esta ruta.");
            }
        }

        return $this->transacciones->run(function () use (
            $ruta, $proveedorId, $ordenFinal, $motivo, $asignacionVigente, $latitud, $longitud, $referenciaUbicacion
        ): RutaDetalle {
            $fechaCambio = new DateTimeImmutable;

            if ($asignacionVigente !== null) {
                $this->rutaDetalles->eliminarPorProveedor($proveedorId);
                $this->rutaDetalles->compactarOrden($asignacionVigente->rutaId());
                $this->rutaHistorial->cerrarVigente($proveedorId, $fechaCambio);
            }

            $detalle = $this->rutaDetalles->guardar(new RutaDetalle(
                id: null,
                rutaId: $ruta->id(),
                proveedorId: $proveedorId,
                ordenVisita: $ordenFinal,
                latitud: $latitud,
                longitud: $longitud,
                referenciaUbicacion: $referenciaUbicacion,
            ));

            // Un orden explícito mayor al siguiente disponible dejaría un
            // hueco (1, 2, 5): se renumera para que la secuencia sea 1..N.
            $this->rutaDetalles->compactarOrden($ruta->id());

            $this->rutaHistorial->guardar(new ProveedorRutaHistorial(
                id: null,
                proveedorId: $proveedorId,
                rutaId: $ruta->id(),
                fechaInicio: $fechaCambio,
                fechaFin: null,
                motivo: $motivo,
            ));

            $this->actualizarProveedor->execute(
                proveedorId: $proveedorId,
                nuevaZonaId: $ruta->zonaId(),
                motivoCambioZona: $motivo ?? "Asignado a la ruta \"{$ruta->nombre()}\".",
            );

            return $this->rutaDetalles->buscarPorId($detalle->id()) ?? $detalle;
        });
    }
}
