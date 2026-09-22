<?php

namespace App\Application\Acopiador;

use App\Application\Shared\TransactionManager;
use App\Domain\Acopiador\Acopiador;
use App\Domain\Acopiador\AcopiadorRepository;
use App\Domain\Acopiador\AcopiadorZonaHistorial;
use App\Domain\Acopiador\AcopiadorZonaHistorialRepository;
use App\Domain\Zona\ZonaRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Actualiza los datos normales de un acopiador (nombres, apellidos,
 * teléfono y zona). Si cambia de zona, cierra el historial vigente y abre
 * uno nuevo, conservando la trazabilidad completa.
 *
 * NO admite cambiar dni, username ni estado: el DNI es la identidad
 * operativa del acopiador, el username pertenece a la gestión de
 * credenciales, y el ciclo ACTIVO/INACTIVO se administra exclusivamente a
 * través de DesactivarAcopiador/ReactivarAcopiador (que además cierran o
 * crean el historial de zona correspondiente), para no mezclar la edición
 * normal de datos con el cambio de situación laboral.
 */
final class ActualizarAcopiador
{
    public function __construct(
        private readonly AcopiadorRepository $acopiadores,
        private readonly ZonaRepository $zonas,
        private readonly AcopiadorZonaHistorialRepository $zonaHistorial,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $acopiadorId,
        ?string $nombres = null,
        ?string $apellidos = null,
        ?string $telefono = null,
        ?int $nuevaZonaId = null,
        ?string $motivoCambioZona = null,
    ): Acopiador {
        $acopiador = $this->acopiadores->buscarPorId($acopiadorId);

        if ($acopiador === null) {
            throw new DomainException('El acopiador indicado no existe.');
        }

        $zonaFinal = $acopiador->zonaId();
        $cambiaZona = $nuevaZonaId !== null && $nuevaZonaId !== $acopiador->zonaId();

        if ($cambiaZona) {
            $zona = $this->zonas->buscarPorId($nuevaZonaId);

            if ($zona === null || ! $zona->estaActiva()) {
                throw new DomainException('La zona indicada no existe o no está activa.');
            }

            $zonaFinal = $nuevaZonaId;
        }

        return $this->transacciones->run(function () use (
            $acopiador, $nombres, $apellidos, $telefono, $zonaFinal, $cambiaZona, $motivoCambioZona
        ): Acopiador {
            if ($cambiaZona) {
                $fechaCambio = new DateTimeImmutable;

                $this->zonaHistorial->cerrarVigente($acopiador->id(), $fechaCambio);

                $this->zonaHistorial->guardar(new AcopiadorZonaHistorial(
                    id: null,
                    acopiadorId: $acopiador->id(),
                    zonaId: $zonaFinal,
                    fechaInicio: $fechaCambio,
                    fechaFin: null,
                    motivo: $motivoCambioZona,
                ));
            }

            return $this->acopiadores->actualizar(new Acopiador(
                id: $acopiador->id(),
                userId: $acopiador->userId(),
                zonaId: $zonaFinal,
                dni: $acopiador->dni(),
                nombres: $nombres ?? $acopiador->nombres(),
                apellidos: $apellidos ?? $acopiador->apellidos(),
                telefono: $telefono ?? $acopiador->telefono(),
                estado: $acopiador->estado(),
                fechaIngreso: $acopiador->fechaIngreso(),
            ));
        });
    }
}
