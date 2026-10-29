<?php

namespace App\Application\Pago;

use App\Application\Trabajador\SincronizarTrabajadores;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\OrigenJornada;
use App\Domain\Trabajador\Trabajador;
use App\Domain\Trabajador\TrabajadorRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Falta automática del cierre de las 23:59 (VentanaAsistencia). Para cada día
 * YA CERRADO del mes (desde el día 1 hasta el último día cerrado) y cada
 * trabajador cuyo rol marca asistencia, crea FALTA/AUTOMATICO (hora null) en
 * los días en que estaba vigente (dentro de alguno de sus periodos de
 * actividad) y no tiene jornada. Nunca toca días antes de su ingreso, días en
 * que estuvo inactivo ni el día de hoy antes de las 23:59 ("Pendiente").
 *
 * Idempotente: una jornada por trabajador y fecha (índice único
 * trabajador_id+fecha + existencia previa), así que correrla a las 23:59,
 * 07:05 o al abrir una pantalla nunca duplica. Al recorrer todo el mes también
 * repara días que quedaron sin cerrar si el scheduler no corrió.
 */
final class MarcarFaltasAutomaticas
{
    public function __construct(
        private readonly TrabajadorRepository $trabajadores,
        private readonly JornadaPersonalRepository $jornadas,
        private readonly UsuarioRepository $usuarios,
        private readonly ObligacionPagoRepository $obligaciones,
        private readonly RecalcularProgresoObligacionPersonal $recalcular,
        private readonly SincronizarTrabajadores $sincronizarTrabajadores,
        private readonly VentanaAsistencia $ventana,
    ) {}

    /** @param  DateTimeImmutable|null  $hasta  Último día a cerrar; por defecto el último día ya cerrado (hoy desde las 23:59). */
    public function execute(?DateTimeImmutable $hasta = null): ResultadoMarcarFaltas
    {
        $this->sincronizarTrabajadores->execute();

        [$desde, $hasta] = $this->rango($hasta);
        $resultado = new ResultadoMarcarFaltas;

        foreach ($this->trabajadores->listarConActividadEntre($desde, $hasta) as $trabajador) {
            $resultado->creadas += $this->cerrarDiasDe($trabajador, $desde, $hasta);
        }

        return $resultado;
    }

    /** Mismo cierre para un solo trabajador (p. ej. antes de pintar su calendario). */
    public function paraTrabajador(Trabajador $trabajador): int
    {
        [$desde, $hasta] = $this->rango(null);

        return $this->cerrarDiasDe($trabajador, $desde, $hasta);
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} */
    private function rango(?DateTimeImmutable $hasta): array
    {
        $hasta = ($hasta ?? $this->ventana->ultimoDiaCerrado())->setTime(0, 0);
        $ultimoCerrado = $this->ventana->ultimoDiaCerrado();

        // Nunca cierra un día que todavía está abierto (hoy antes de las 23:59) ni uno futuro.
        if ($hasta > $ultimoCerrado) {
            $hasta = $ultimoCerrado;
        }

        return [$hasta->modify('first day of this month'), $hasta];
    }

    private function cerrarDiasDe(Trabajador $trabajador, DateTimeImmutable $desde, DateTimeImmutable $hasta): int
    {
        // El ADMINISTRADOR (rol sin control de asistencia) nunca recibe faltas.
        if ($desde > $hasta || ! $this->trabajadores->controlaAsistencia($trabajador->id())) {
            return 0;
        }

        $registradas = array_flip($this->jornadas->fechasRegistradasEntre($trabajador->id(), $desde, $hasta));
        $rolId = null;
        $creadas = 0;

        for ($fecha = $desde; $fecha <= $hasta; $fecha = $fecha->modify('+1 day')) {
            if (isset($registradas[$fecha->format('Y-m-d')]) || ! $trabajador->estaVigenteEn($fecha)) {
                continue;
            }

            $rolId ??= $this->usuarios->buscarPorId($trabajador->userId())->roleId();

            try {
                $this->jornadas->registrar(new JornadaPersonal(
                    id: null,
                    trabajadorId: $trabajador->id(),
                    rolId: $rolId,
                    fecha: $fecha,
                    estado: EstadoJornada::FALTA,
                    origen: OrigenJornada::AUTOMATICO,
                    horaMarcacion: null,
                    observacion: null,
                    registradoPorUserId: null,
                ));
                $creadas++;
            } catch (UniqueConstraintViolationException|DomainException) {
                // Otra corrida simultánea ya la creó: una sola jornada por trabajador y fecha.
            }
        }

        if ($creadas > 0) {
            $abierta = $this->obligaciones->buscarPersonalAbiertaPorUsuario($trabajador->userId());

            if ($abierta !== null && $abierta->estaEnCurso()) {
                $this->recalcular->execute($abierta);
            }
        }

        return $creadas;
    }
}
