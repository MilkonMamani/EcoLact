<?php

namespace App\Application\Pago;

use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\OrigenJornada;
use App\Domain\Trabajador\TrabajadorRepository;
use DateTimeImmutable;

/**
 * Corre todos los días a las 12:05 (routes/console.php): para cada
 * trabajador ACTIVO y vigente hoy (ya ingresó, no ha salido) que no tenga
 * jornada de hoy, crea una FALTA/AUTOMATICO. Nunca toca fechas anteriores al
 * ingreso del trabajador (TrabajadorRepository::listarActivosVigentesEn ya
 * los excluye) ni pisa una jornada ya registrada (existeEnFecha).
 */
final class MarcarFaltasAutomaticas
{
    public function __construct(
        private readonly TrabajadorRepository $trabajadores,
        private readonly JornadaPersonalRepository $jornadas,
        private readonly UsuarioRepository $usuarios,
        private readonly ObligacionPagoRepository $obligaciones,
        private readonly RecalcularProgresoObligacionPersonal $recalcular,
    ) {}

    public function execute(?DateTimeImmutable $hoy = null): ResultadoMarcarFaltas
    {
        $hoy = ($hoy ?? DateTimeImmutable::createFromInterface(now()))->setTime(0, 0);
        $resultado = new ResultadoMarcarFaltas;

        foreach ($this->trabajadores->listarActivosVigentesEn($hoy) as $trabajador) {
            if ($this->jornadas->existeEnFecha($trabajador->id(), $hoy)) {
                continue;
            }

            $this->jornadas->registrar(new JornadaPersonal(
                id: null,
                trabajadorId: $trabajador->id(),
                rolId: $this->usuarios->buscarPorId($trabajador->userId())->roleId(),
                fecha: $hoy,
                estado: EstadoJornada::FALTA,
                origen: OrigenJornada::AUTOMATICO,
                horaMarcacion: null,
                observacion: null,
                registradoPorUserId: null,
            ));
            $resultado->creadas++;

            $abierta = $this->obligaciones->buscarPersonalAbiertaPorUsuario($trabajador->userId());

            if ($abierta !== null && $abierta->estaEnCurso()) {
                $this->recalcular->execute($abierta);
            }
        }

        return $resultado;
    }
}
