<?php

namespace App\Application\Pago;

use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Trabajador\Trabajador;
use DateTimeImmutable;

/**
 * Presentes y faltas de un trabajador en un rango, contando SOLO los días en
 * que estaba vigente (dentro de sus periodos de actividad). Una jornada que
 * cayó en un periodo inactivo (p. ej. faltas que el cierre anterior generó
 * antes de existir el historial de actividad) no cuenta como falta ni como
 * día trabajado. Fuente única para el calendario, el progreso y el cierre.
 */
final class ContarJornadasVigentes
{
    public function __construct(private readonly JornadaPersonalRepository $jornadas) {}

    /** @return array{presentes: int, faltas: int} */
    public function execute(Trabajador $trabajador, DateTimeImmutable $inicio, DateTimeImmutable $fin): array
    {
        $conteo = ['presentes' => 0, 'faltas' => 0];

        foreach ($this->jornadas->listarEnPeriodo($trabajador->id(), $inicio, $fin) as $jornada) {
            if (! $trabajador->estaVigenteEn($jornada->fecha())) {
                continue;
            }

            match ($jornada->estado()) {
                EstadoJornada::PRESENTE => $conteo['presentes']++,
                EstadoJornada::FALTA => $conteo['faltas']++,
            };
        }

        return $conteo;
    }
}
