<?php

namespace App\Application\Proveedor;

use App\Application\Shared\TransactionManager;
use App\Domain\Proveedor\Proveedor;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Proveedor\ProveedorZonaHistorial;
use App\Domain\Proveedor\ProveedorZonaHistorialRepository;
use App\Domain\Zona\ZonaRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Actualiza los datos de un proveedor (dni, nombres, apellidos, teléfono
 * y, excepcionalmente, zona). La ubicación de entrega ya no es un dato del
 * proveedor: vive en su asignación a una ruta (ver RutaDetalle). Si se indica una nueva
 * zona distinta a la actual (o se pide limpiarla explícitamente con
 * $limpiarZona), cierra el registro de historial vigente y, si corresponde,
 * abre uno nuevo, conservando la trazabilidad de todos los cambios de zona
 * del proveedor.
 *
 * Desde que la zona se deriva de la asignación a una ruta (módulo Zonas),
 * este Use Case ya no recibe la zona desde el formulario de edición del
 * proveedor: $nuevaZonaId/$limpiarZona/$motivoCambioZona los invoca
 * App\Application\Ruta\AsignarProveedorARuta y
 * App\Application\Ruta\QuitarProveedorDeRuta para mantener sincronizada
 * esta caché (ver nota en la migración que hizo nullable proveedores.zona_id).
 *
 * NO admite cambiar el username: la cuenta de acceso se administra por una
 * funcionalidad específica de usuarios/credenciales, fuera de este flujo.
 */
final class ActualizarProveedor
{
    public function __construct(
        private readonly ProveedorRepository $proveedores,
        private readonly ZonaRepository $zonas,
        private readonly ProveedorZonaHistorialRepository $zonaHistorial,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $proveedorId,
        ?string $dni = null,
        ?string $nombres = null,
        ?string $apellidos = null,
        ?string $telefono = null,
        ?int $nuevaZonaId = null,
        ?string $motivoCambioZona = null,
        bool $limpiarZona = false,
    ): Proveedor {
        $proveedor = $this->proveedores->buscarPorId($proveedorId);

        if ($proveedor === null) {
            throw new DomainException('El proveedor indicado no existe.');
        }

        $dniFinal = $proveedor->dni();

        if ($dni !== null && $dni !== $proveedor->dni()) {
            $proveedorConEseDni = $this->proveedores->buscarPorDni($dni);

            if ($proveedorConEseDni !== null && $proveedorConEseDni->id() !== $proveedor->id()) {
                throw new DomainException("Ya existe otro proveedor registrado con el DNI {$dni}.");
            }

            $dniFinal = $dni;
        }

        $zonaFinal = $proveedor->zonaId();
        $cambiaZona = ($nuevaZonaId !== null && $nuevaZonaId !== $proveedor->zonaId())
            || ($limpiarZona && $proveedor->zonaId() !== null);

        if ($nuevaZonaId !== null && $nuevaZonaId !== $proveedor->zonaId()) {
            $zona = $this->zonas->buscarPorId($nuevaZonaId);

            if ($zona === null || ! $zona->estaActiva()) {
                throw new DomainException('La zona indicada no existe o no está activa.');
            }

            $zonaFinal = $nuevaZonaId;
        } elseif ($limpiarZona) {
            $zonaFinal = null;
        }

        return $this->transacciones->run(function () use (
            $proveedor, $dniFinal, $nombres, $apellidos, $telefono, $zonaFinal, $cambiaZona, $motivoCambioZona,
        ): Proveedor {
            if ($cambiaZona) {
                $fechaCambio = new DateTimeImmutable;

                $this->zonaHistorial->cerrarVigente($proveedor->id(), $fechaCambio);

                if ($zonaFinal !== null) {
                    $this->zonaHistorial->guardar(new ProveedorZonaHistorial(
                        id: null,
                        proveedorId: $proveedor->id(),
                        zonaId: $zonaFinal,
                        fechaInicio: $fechaCambio,
                        fechaFin: null,
                        motivo: $motivoCambioZona,
                    ));
                }
            }

            return $this->proveedores->actualizar(new Proveedor(
                id: $proveedor->id(),
                userId: $proveedor->userId(),
                zonaId: $zonaFinal,
                dni: $dniFinal,
                nombres: $nombres ?? $proveedor->nombres(),
                apellidos: $apellidos ?? $proveedor->apellidos(),
                telefono: $telefono ?? $proveedor->telefono(),
                qrToken: $proveedor->qrToken(),
                estado: $proveedor->estado(),
                fechaRegistro: $proveedor->fechaRegistro(),
                advertenciasCount: $proveedor->advertenciasCount(),
            ));
        });
    }
}
