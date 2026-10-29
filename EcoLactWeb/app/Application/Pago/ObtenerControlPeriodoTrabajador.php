<?php

namespace App\Application\Pago;

use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Pago\ObligacionPago;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Trabajador\Trabajador;
use App\Domain\Trabajador\TrabajadorRepository;
use DateTimeImmutable;

/**
 * La única implementación de "Control del periodo": reusada por los 5
 * perfiles y el panel de Pagar a trabajadores. Devuelve el resumen del mes
 * (asistencias, faltas, sueldo, valor por día, acumulado ya calculado por el
 * backend) y el calendario día a día del mes resuelto. Nunca recalcula el
 * dinero: los montos vienen tal cual del snapshot de la obligación.
 *
 * Antes de leer cierra los días ya cerrados (23:59) de ESTE trabajador
 * (MarcarFaltasAutomaticas::paraTrabajador, idempotente), así un día pasado
 * nunca se ve "Pendiente" aunque el scheduler no haya corrido: Pendiente
 * (SIN_REGISTRO) solo existe para hoy antes de las 23:59.
 */
final class ObtenerControlPeriodoTrabajador
{
    public function __construct(
        private readonly ObligacionPagoRepository $obligaciones,
        private readonly TrabajadorRepository $trabajadores,
        private readonly JornadaPersonalRepository $jornadas,
        private readonly ContarJornadasVigentes $contarJornadas,
        private readonly MarcarFaltasAutomaticas $marcarFaltas,
        private readonly VentanaAsistencia $ventana,
    ) {}

    /** @param  string|null  $mes  Formato "Y-m"; por defecto el mes del periodo abierto (o el más reciente). */
    public function execute(int $userId, ?string $mes = null): ?ControlPeriodoTrabajador
    {
        $trabajador = $this->trabajadores->buscarPorUserId($userId);

        if ($trabajador === null) {
            return null;
        }

        $controlaAsistencia = $this->trabajadores->controlaAsistencia($trabajador->id());

        if ($controlaAsistencia) {
            $this->marcarFaltas->paraTrabajador($trabajador);
        }

        $hoy = $this->ventana->hoy();
        [$periodoInicio, $periodoFin, $obligacion] = $this->resolverPeriodo($userId, $mes, $hoy);

        $dias = $this->construirCalendario($trabajador, $periodoInicio, $periodoFin, $hoy);

        $conteo = $this->contarJornadas->execute($trabajador, $periodoInicio, $periodoFin);
        $asistencias = $conteo['presentes'];
        $faltas = $controlaAsistencia ? $conteo['faltas'] : 0;
        $esMesCompleto = $trabajador->estuvoVigenteTodoElRango($periodoInicio, $periodoFin);

        $sueldoMensual = $obligacion !== null ? (float) ($obligacion->baseCalculo()['sueldo_base'] ?? 0.0) : 0.0;
        $valorDia = $obligacion?->valorDia() ?? ($sueldoMensual > 0 ? round($sueldoMensual / JornadaPersonal::DIAS_BASE_PERIODO, 2) : 0.0);
        $acumulado = $obligacion?->acumulado() ?? 0.0;
        $descuentoPorFaltas = $esMesCompleto && $controlaAsistencia ? max(0.0, round($sueldoMensual - $acumulado, 2)) : 0.0;

        $mesActual = $periodoInicio->format('Y-m');
        $inicioMesActual = PeriodoPago::mesQueContiene($hoy)->inicio->format('Y-m');

        return new ControlPeriodoTrabajador(
            userId: $userId,
            obligacionId: $obligacion?->id(),
            estado: $obligacion?->estado()->value ?? 'SIN_OBLIGACION',
            estadoEtiqueta: $obligacion?->estado()->etiqueta() ?? 'Sin registro de nómina',
            asistencias: $asistencias,
            faltas: $faltas,
            sueldoMensual: $sueldoMensual,
            valorDia: $valorDia,
            descuentoPorFaltas: $descuentoPorFaltas,
            acumulado: $acumulado,
            bonificacion: $obligacion?->bonificacion() ?? 0.0,
            descuento: $obligacion?->descuento() ?? 0.0,
            total: $obligacion?->montoCalculado() ?? 0.0,
            esMesCompleto: $esMesCompleto,
            fechaIngresoEtiqueta: $esMesCompleto ? null : $trabajador->fechaIngreso()->format('d/m/Y'),
            periodoEtiqueta: PeriodoPago::mesQueContiene($periodoInicio)->etiqueta(),
            periodoRango: $periodoInicio->format('d/m/Y').' – '.$periodoFin->format('d/m/Y'),
            periodoInicioIso: $periodoInicio->format('Y-m-d'),
            periodoFinIso: $periodoFin->format('Y-m-d'),
            mesValor: $mesActual,
            mesAnteriorValor: $periodoInicio->modify('-1 month')->format('Y-m'),
            mesSiguienteValor: $mesActual < $inicioMesActual ? $periodoInicio->modify('+1 month')->format('Y-m') : null,
            dias: $dias,
            qrToken: $trabajador->qrToken(),
            controlaAsistencia: $controlaAsistencia,
        );
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: ?ObligacionPago} */
    private function resolverPeriodo(int $userId, ?string $mes, DateTimeImmutable $hoy): array
    {
        if ($mes !== null) {
            $periodo = PeriodoPago::mesQueContiene(DateTimeImmutable::createFromFormat('Y-m-d', $mes.'-01')->setTime(0, 0));

            return [$periodo->inicio, $periodo->fin, $this->obligaciones->buscarPersonalPorUsuarioYPeriodo($userId, $periodo->inicio)];
        }

        $obligacion = $this->obligaciones->buscarPersonalAbiertaPorUsuario($userId)
            ?? $this->obligaciones->buscarPersonalMasRecientePorUsuario($userId);

        if ($obligacion !== null) {
            return [$obligacion->periodoInicio(), $obligacion->periodoFin(), $obligacion];
        }

        $periodo = PeriodoPago::mesQueContiene($hoy);

        return [$periodo->inicio, $periodo->fin, null];
    }

    /**
     * @return DiaCalendarioTrabajador[]
     */
    private function construirCalendario(Trabajador $trabajador, DateTimeImmutable $inicio, DateTimeImmutable $fin, DateTimeImmutable $hoy): array
    {
        $jornadasPorFecha = [];

        foreach ($this->jornadas->listarEnPeriodo($trabajador->id(), $inicio, $fin) as $jornada) {
            $jornadasPorFecha[$jornada->fecha()->format('Y-m-d')] = $jornada;
        }

        $dias = [];
        $fecha = $inicio;

        while ($fecha <= $fin) {
            $fechaIso = $fecha->format('Y-m-d');
            $jornada = $jornadasPorFecha[$fechaIso] ?? null;
            $esHoy = $fechaIso === $hoy->format('Y-m-d');

            $estado = match (true) {
                $fecha > $hoy => 'FUTURO',
                ! $trabajador->estaVigenteEn($fecha) => 'NO_APLICABLE',
                $jornada !== null => $jornada->estado()->value,
                default => 'SIN_REGISTRO',
            };

            $dias[] = new DiaCalendarioTrabajador(
                fechaIso: $fechaIso,
                diaNumero: (int) $fecha->format('j'),
                estado: $estado,
                horaMarcacion: $jornada?->horaMarcacion()?->format('H:i'),
                origenEtiqueta: $jornada?->origen()->etiqueta(),
                observacion: $jornada?->observacion(),
                jornadaId: $jornada?->id(),
                esHoy: $esHoy,
            );

            $fecha = $fecha->modify('+1 day');
        }

        return $dias;
    }
}
