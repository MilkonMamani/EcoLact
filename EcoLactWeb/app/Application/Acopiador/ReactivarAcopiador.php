<?php

namespace App\Application\Acopiador;

use App\Application\Shared\TransactionManager;
use App\Domain\Acopiador\Acopiador;
use App\Domain\Acopiador\AcopiadorRepository;
use App\Domain\Acopiador\AcopiadorZonaHistorial;
use App\Domain\Acopiador\AcopiadorZonaHistorialRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Zona\ZonaRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Reactiva (ACTIVO) a un acopiador que está INACTIVO, asignándolo a la zona
 * indicada. SIEMPRE crea un nuevo período en el historial de zona (nunca
 * reabre ni modifica uno cerrado anteriormente), aunque la zona de destino
 * sea la misma en la que estaba antes de la baja: cada periodo de actividad
 * es un tramo histórico propio.
 */
final class ReactivarAcopiador
{
    public function __construct(
        private readonly AcopiadorRepository $acopiadores,
        private readonly ZonaRepository $zonas,
        private readonly AcopiadorZonaHistorialRepository $zonaHistorial,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(int $acopiadorId, int $zonaId): Acopiador
    {
        $acopiador = $this->acopiadores->buscarPorId($acopiadorId);

        if ($acopiador === null) {
            throw new DomainException('El acopiador indicado no existe.');
        }

        if ($acopiador->estado() !== EstadoUsuario::INACTIVO) {
            throw new DomainException('El acopiador ya se encuentra activo.');
        }

        $zona = $this->zonas->buscarPorId($zonaId);

        if ($zona === null || ! $zona->estaActiva()) {
            throw new DomainException('La zona indicada no existe o no está activa.');
        }

        return $this->transacciones->run(function () use ($acopiador, $zonaId): Acopiador {
            $fechaReactivacion = new DateTimeImmutable;

            // Defensivo: garantiza que no quede ningún historial vigente
            // huérfano antes de abrir el nuevo periodo (no reabre nada).
            $this->zonaHistorial->cerrarVigente($acopiador->id(), $fechaReactivacion);

            $this->zonaHistorial->guardar(new AcopiadorZonaHistorial(
                id: null,
                acopiadorId: $acopiador->id(),
                zonaId: $zonaId,
                fechaInicio: $fechaReactivacion,
                fechaFin: null,
                motivo: 'Reactivación del acopiador.',
            ));

            return $this->acopiadores->actualizar(new Acopiador(
                id: $acopiador->id(),
                userId: $acopiador->userId(),
                zonaId: $zonaId,
                dni: $acopiador->dni(),
                nombres: $acopiador->nombres(),
                apellidos: $acopiador->apellidos(),
                telefono: $acopiador->telefono(),
                estado: EstadoUsuario::ACTIVO,
                fechaIngreso: $acopiador->fechaIngreso(),
            ));
        });
    }
}
