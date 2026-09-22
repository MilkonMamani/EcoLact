<?php

namespace App\Application\Pago;

use App\Application\Shared\TransactionManager;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Pago\ObligacionPagoRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Registra un día trabajado y actualiza en la misma transacción el progreso
 * de la obligación de pago abierta del trabajador (si su fecha cae dentro de
 * esa ventana de 30 días). Bloquea la fila de la obligación para que dos
 * registros simultáneos no pisen el conteo.
 */
final class RegistrarJornadaTrabajador
{
    public function __construct(
        private readonly JornadaPersonalRepository $jornadas,
        private readonly ObligacionPagoRepository $obligaciones,
        private readonly RecalcularProgresoObligacionPersonal $recalcular,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $userId,
        int $rolId,
        DateTimeImmutable $fecha,
        ?string $observacion,
        int $registradoPorUserId,
        ?DateTimeImmutable $hoy = null,
    ): JornadaPersonal {
        $hoy = ($hoy ?? DateTimeImmutable::createFromInterface(now()))->setTime(0, 0);
        $fecha = $fecha->setTime(0, 0);
        $observacion = $observacion !== null && trim($observacion) !== '' ? trim($observacion) : null;

        if ($fecha > $hoy) {
            throw new DomainException('No se puede registrar un día trabajado en el futuro.');
        }

        return $this->transacciones->run(function () use ($userId, $rolId, $fecha, $observacion, $registradoPorUserId): JornadaPersonal {
            if ($this->jornadas->existeEnFecha($userId, $fecha)) {
                throw new DomainException('Ya se registró un día trabajado para esta fecha.');
            }

            $obligacion = $this->obligaciones->buscarPersonalAbiertaPorUsuarioBloqueando($userId);

            if ($obligacion === null || $fecha < $obligacion->periodoInicio() || $fecha > $obligacion->periodoFin()) {
                throw new DomainException('No hay un periodo abierto que incluya esta fecha. Actualiza la pantalla e inténtalo de nuevo.');
            }

            $jornada = $this->jornadas->registrar(new JornadaPersonal(
                id: null,
                userId: $userId,
                rolId: $rolId,
                fecha: $fecha,
                observacion: $observacion,
                registradoPorUserId: $registradoPorUserId,
            ));

            $this->recalcular->execute($obligacion);

            return $jornada;
        });
    }
}
