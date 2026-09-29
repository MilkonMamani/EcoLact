<?php

namespace App\Application\Pago;

use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Pago\ObligacionPago;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\OrigenJornada;
use App\Domain\Trabajador\Trabajador;
use App\Domain\Trabajador\TrabajadorRepository;
use DateTimeImmutable;

/**
 * Corre el día 1 de cada mes a las 00:05 (routes/console.php): cierra el mes
 * anterior de cada obligación de personal EN_CURSO cuyo periodo ya terminó
 * (EN_CURSO->PENDIENTE, con el acumulado final y fecha_pago_programada =
 * hoy) y abre el mes actual (GenerarObligacionesPersonal). Antes de cerrar,
 * repara defensivamente cualquier día aplicable sin jornada (debería ser
 * imposible si MarcarFaltasAutomaticas corrió todos los días, pero un fallo
 * técnico puntual no debe traducirse en un sueldo mal calculado en
 * silencio). Idempotente: correrlo dos veces el mismo día no repite el
 * cierre (solo quedan EN_CURSO las del mes actual, con periodo_fin >= hoy).
 */
final class CerrarNominaMensual
{
    public function __construct(
        private readonly ObligacionPagoRepository $obligaciones,
        private readonly TrabajadorRepository $trabajadores,
        private readonly JornadaPersonalRepository $jornadas,
        private readonly UsuarioRepository $usuarios,
        private readonly GenerarObligacionesPersonal $generarObligaciones,
        private readonly ContarJornadasVigentes $contarJornadas,
    ) {}

    public function execute(?DateTimeImmutable $hoy = null, ?int $actorUserId = null): ResultadoCierreNomina
    {
        $hoy = ($hoy ?? DateTimeImmutable::createFromInterface(now()))->setTime(0, 0);
        $resultado = new ResultadoCierreNomina;

        foreach ($this->obligaciones->listarPersonalEnCursoVencidas($hoy) as $obligacion) {
            $this->cerrarObligacion($obligacion, $hoy, $resultado);
        }

        $this->generarObligaciones->execute($hoy, $actorUserId);

        return $resultado;
    }

    private function cerrarObligacion(ObligacionPago $obligacion, DateTimeImmutable $hoy, ResultadoCierreNomina $resultado): void
    {
        $trabajador = $this->trabajadores->buscarPorUserId($obligacion->userId());

        if ($trabajador === null) {
            return;
        }

        $resultado->faltasReparadas += $this->repararFaltasFaltantes($trabajador, $obligacion);

        $sueldoBase = (float) ($obligacion->baseCalculo()['sueldo_base'] ?? 0.0);
        $valorDia = round($sueldoBase / JornadaPersonal::DIAS_BASE_PERIODO, 2);

        ['presentes' => $presentes, 'faltas' => $faltas] = $this->contarJornadas->execute($trabajador, $obligacion->periodoInicio(), $obligacion->periodoFin());

        $acumulado = $trabajador->estuvoVigenteTodoElRango($obligacion->periodoInicio(), $obligacion->periodoFin())
            ? max(0.0, round($sueldoBase - $faltas * $valorDia, 2))
            : min($sueldoBase, round($presentes * $valorDia, 2));

        $this->obligaciones->cerrarMesPersonal($obligacion->id(), $presentes, $valorDia, $acumulado, $hoy);
        $resultado->cerradas++;
    }

    /** Días del mes en que estuvo vigente (dentro de sus periodos de actividad) y quedaron sin jornada. */
    private function repararFaltasFaltantes(Trabajador $trabajador, ObligacionPago $obligacion): int
    {
        $registradas = array_flip($this->jornadas->fechasRegistradasEntre($trabajador->id(), $obligacion->periodoInicio(), $obligacion->periodoFin()));
        $rolId = null;
        $reparadas = 0;

        for ($fecha = $obligacion->periodoInicio(); $fecha <= $obligacion->periodoFin(); $fecha = $fecha->modify('+1 day')) {
            if (isset($registradas[$fecha->format('Y-m-d')]) || ! $trabajador->estaVigenteEn($fecha)) {
                continue;
            }

            $rolId ??= $this->usuarios->buscarPorId($trabajador->userId())->roleId();

            $this->jornadas->registrar(new JornadaPersonal(
                id: null,
                trabajadorId: $trabajador->id(),
                rolId: $rolId,
                fecha: $fecha,
                estado: EstadoJornada::FALTA,
                origen: OrigenJornada::AUTOMATICO,
                horaMarcacion: null,
                observacion: 'Reparación defensiva en el cierre mensual: no se generó falta automática en su momento.',
                registradoPorUserId: null,
            ));
            $reparadas++;
        }

        return $reparadas;
    }
}
