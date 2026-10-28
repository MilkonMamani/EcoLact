<?php

namespace App\Application\Zona;

use App\Application\Shared\CampoActualizable;
use App\Domain\Acopiador\AcopiadorRepository;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Zona\Zona;
use App\Domain\Zona\ZonaRepository;
use DomainException;

/**
 * Actualiza nombre/descripcion/estado de una zona existente.
 *
 * Si se intenta desactivar (estado = INACTIVO) una zona que todavía tiene
 * acopiadores o proveedores ACTIVOS asignados, se rechaza: dejarla inactiva
 * en ese caso sería incoherente (personal operando en una zona que ya no
 * existe operativamente). Deben reasignarse/desactivarse esas personas
 * primero.
 *
 * descripcion es una columna nullable: "no enviarla" (conservar la actual)
 * y "enviarla explícitamente como null" (limpiarla) son cosas distintas,
 * por lo que se recibe envuelta en CampoActualizable.
 *
 * @param  CampoActualizable<string|null>|null  $descripcion
 */
final class ActualizarZona
{
    public function __construct(
        private readonly ZonaRepository $zonas,
        private readonly AcopiadorRepository $acopiadores,
        private readonly ProveedorRepository $proveedores,
    ) {}

    public function execute(
        int $zonaId,
        ?string $nombre = null,
        ?CampoActualizable $descripcion = null,
        ?string $estado = null,
    ): Zona {
        $zona = $this->zonas->buscarPorId($zonaId);

        if ($zona === null) {
            throw new DomainException('La zona indicada no existe.');
        }

        if ($nombre !== null && $nombre !== $zona->nombre()) {
            $equivalente = $this->zonas->buscarEquivalente($nombre, exceptoId: $zona->id());

            if ($equivalente !== null) {
                throw new DomainException("Ya existe una zona equivalente con el nombre {$equivalente->nombre()}.");
            }
        }

        $nuevoEstado = $estado ?? $zona->estado();

        if ($nuevoEstado === 'INACTIVO' && $zona->estaActiva()) {
            $tieneAcopiadoresActivos = array_filter(
                $this->acopiadores->listarPorZona($zonaId),
                fn ($acopiador): bool => $acopiador->estado() === EstadoUsuario::ACTIVO,
            ) !== [];

            $tieneProveedoresActivos = ! $tieneAcopiadoresActivos && array_filter(
                $this->proveedores->listarPorZona($zonaId),
                fn ($proveedor): bool => $proveedor->estado() === EstadoUsuario::ACTIVO,
            ) !== [];

            if ($tieneAcopiadoresActivos || $tieneProveedoresActivos) {
                throw new DomainException(
                    'No se puede desactivar la zona: todavía tiene acopiadores o proveedores activos asignados.'
                );
            }
        }

        return $this->zonas->actualizar(new Zona(
            id: $zona->id(),
            nombre: $nombre ?? $zona->nombre(),
            descripcion: CampoActualizable::resolver($descripcion, $zona->descripcion()),
            estado: $nuevoEstado,
        ));
    }
}
