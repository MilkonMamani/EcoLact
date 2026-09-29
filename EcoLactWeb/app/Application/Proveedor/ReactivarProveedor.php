<?php

namespace App\Application\Proveedor;

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Shared\TransactionManager;
use App\Domain\Proveedor\Proveedor;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Ruta\RutaDetalleRepository;
use App\Domain\Ruta\RutaRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Zona\ZonaRepository;
use DomainException;

/**
 * Reactiva (ACTIVO) a un proveedor que está INACTIVO, reincorporándolo a una
 * zona, y reinicia su contador de advertencias de calidad: la reactivación
 * es una decisión explícita del ADMINISTRADOR de "empezar de nuevo" con ese
 * proveedor.
 *
 * La zona de un proveedor no es un campo suelto: se deriva de su asignación
 * a una ruta (proveedores.zona_id es la caché que mantiene
 * AsignarProveedorARuta). Por eso se reincorpora indicando la RUTA de la
 * zona elegida y se delega en AsignarProveedorARuta, que ya cierra/abre el
 * historial de ruta y el de zona y sincroniza la caché. Si el proveedor ya
 * seguía asignado a esa misma ruta, no se toca su asignación (conserva su
 * orden y su punto de recolección). Todo ocurre en una sola transacción:
 * nunca queda activo sin zona ni con la zona cambiada pero inactivo.
 */
final class ReactivarProveedor
{
    public function __construct(
        private readonly ProveedorRepository $proveedores,
        private readonly RutaRepository $rutas,
        private readonly ZonaRepository $zonas,
        private readonly RutaDetalleRepository $rutaDetalles,
        private readonly AsignarProveedorARuta $asignarProveedorARuta,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(int $proveedorId, int $rutaId): Proveedor
    {
        $proveedor = $this->proveedores->buscarPorId($proveedorId);

        if ($proveedor === null) {
            throw new DomainException('El proveedor indicado no existe.');
        }

        if ($proveedor->estado() !== EstadoUsuario::INACTIVO) {
            throw new DomainException('El proveedor ya se encuentra activo.');
        }

        $ruta = $this->rutas->buscarPorId($rutaId);

        if ($ruta === null || ! $ruta->estaActiva()) {
            throw new DomainException('La ruta indicada no existe o no está activa.');
        }

        $zona = $this->zonas->buscarPorId($ruta->zonaId());

        if ($zona === null || ! $zona->estaActiva()) {
            throw new DomainException('La zona indicada no existe o no está activa.');
        }

        return $this->transacciones->run(function () use ($proveedorId, $rutaId): Proveedor {
            if ($this->rutaDetalles->buscarPorProveedor($proveedorId)?->rutaId() !== $rutaId) {
                $this->asignarProveedorARuta->execute(
                    rutaId: $rutaId,
                    proveedorId: $proveedorId,
                    motivo: 'Reactivación del proveedor.',
                );
            }

            // Se relee: la asignación acaba de sincronizar su zona.
            $proveedor = $this->proveedores->buscarPorId($proveedorId);

            return $this->proveedores->actualizar(new Proveedor(
                id: $proveedor->id(),
                userId: $proveedor->userId(),
                zonaId: $proveedor->zonaId(),
                dni: $proveedor->dni(),
                nombres: $proveedor->nombres(),
                apellidos: $proveedor->apellidos(),
                telefono: $proveedor->telefono(),
                qrToken: $proveedor->qrToken(),
                estado: EstadoUsuario::ACTIVO,
                fechaRegistro: $proveedor->fechaRegistro(),
                advertenciasCount: 0,
            ));
        });
    }
}
