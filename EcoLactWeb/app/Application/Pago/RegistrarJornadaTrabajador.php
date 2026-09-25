<?php

namespace App\Application\Pago;

use App\Application\Shared\TransactionManager;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\OrigenJornada;
use App\Domain\Trabajador\TrabajadorRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Marca PRESENTE a mano (p.ej. para completar un día que no se marcó por
 * QR). Las jornadas son hechos independientes del ciclo de nómina: no exige
 * que exista una obligación abierta (si la hay para el mes de la fecha, se
 * recalcula; si no, GenerarObligacionesPersonal la sincronizará después).
 */
final class RegistrarJornadaTrabajador
{
    public function __construct(
        private readonly JornadaPersonalRepository $jornadas,
        private readonly TrabajadorRepository $trabajadores,
        private readonly UsuarioRepository $usuarios,
        private readonly ObligacionPagoRepository $obligaciones,
        private readonly RecalcularProgresoObligacionPersonal $recalcular,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $trabajadorId,
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

        $trabajador = $this->trabajadores->buscarPorId($trabajadorId)
            ?? throw new DomainException('El trabajador indicado no existe.');

        if ($fecha->setTime(0, 0) < $trabajador->fechaIngreso()->setTime(0, 0)) {
            throw new DomainException('La fecha es anterior al ingreso del trabajador.');
        }

        return $this->transacciones->run(function () use ($trabajador, $fecha, $observacion, $registradoPorUserId): JornadaPersonal {
            if ($this->jornadas->existeEnFecha($trabajador->id(), $fecha)) {
                throw new DomainException('Ya se registró una jornada para esta fecha.');
            }

            $jornada = $this->jornadas->registrar(new JornadaPersonal(
                id: null,
                trabajadorId: $trabajador->id(),
                rolId: $this->usuarios->buscarPorId($trabajador->userId())->roleId(),
                fecha: $fecha,
                estado: EstadoJornada::PRESENTE,
                origen: OrigenJornada::MANUAL,
                horaMarcacion: null,
                observacion: $observacion,
                registradoPorUserId: $registradoPorUserId,
            ));

            $abierta = $this->obligaciones->buscarPersonalAbiertaPorUsuarioBloqueando($trabajador->userId());

            if ($abierta !== null && $abierta->estaEnCurso() && $fecha >= $abierta->periodoInicio() && $fecha <= $abierta->periodoFin()) {
                $this->recalcular->execute($abierta);
            }

            return $jornada;
        });
    }
}
