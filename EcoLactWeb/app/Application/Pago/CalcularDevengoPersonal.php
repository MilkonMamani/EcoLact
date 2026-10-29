<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\DevengoPersonalQuery;
use App\Domain\Pago\ConfiguracionPago;
use App\Domain\Pago\ConfiguracionPagoRepository;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Domain\Trabajador\Trabajador;
use App\Domain\Trabajador\TrabajadorRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Remuneración DEVENGADA día a día por el personal (todos los roles con
 * roles.es_remunerable; PROVEEDOR no, cobra por leche). Solo lee: nunca abre,
 * cierra ni modifica obligaciones.
 *
 * Reglas (las mismas de RecalcularProgresoObligacionPersonal y CerrarNominaMensual):
 * - valor del día = sueldo mensual del ROL vigente ESE día / 30 (redondeado a
 *   céntimos), nunca el sueldo actual aplicado a días pasados;
 * - rol con control de asistencia: devenga solo el día con jornada PRESENTE
 *   (FALTA o sin marcar = 0); ADMINISTRADOR (sin control): cada día vigente;
 * - solo días dentro de los periodos de actividad del trabajador y nunca
 *   después de la fecha de corte (lo futuro aún no se trabajó);
 * - el acumulado del mes nunca supera el sueldo mensual (mes de 31 días);
 * - mes ya cerrado (obligación PENDIENTE o PAGADO): manda el acumulado
 *   histórico de la obligación; la diferencia con la suma diaria (regla de
 *   mes completo, meses de 28/31 días, vigencias) se asigna al último día del
 *   mes como ajuste de cierre, así la suma de los días del mes es exactamente
 *   lo que se reconoció y nada se cuenta dos veces.
 *
 * Bonificaciones y descuentos de la obligación son ajustes de pago: afectan
 * el saldo pendiente, no el devengo. Los importes se calculan en céntimos.
 */
final class CalcularDevengoPersonal
{
    public function __construct(
        private readonly DevengoPersonalQuery $hechos,
        private readonly TrabajadorRepository $trabajadores,
        private readonly ConfiguracionPagoRepository $configuraciones,
    ) {}

    /**
     * @param  DateTimeImmutable  $desde  primer día del período (día del negocio)
     * @param  DateTimeImmutable  $hasta  último día del período, inclusive
     * @param  DateTimeImmutable  $corte  último día ya transcurrido que puede devengar
     */
    public function execute(DateTimeImmutable $desde, DateTimeImmutable $hasta, DateTimeImmutable $corte): DevengoPersonal
    {
        $desde = self::dia($desde);
        $hasta = self::dia($hasta);
        $corte = self::dia($corte);
        $finDevengo = min($hasta, $corte);
        // Desde el mes anterior: su sueldo se programa el día 1 siguiente, que puede caer en el período.
        $inicioMeses = min($desde, $corte)->modify('first day of this month')->modify('-1 month');
        $mesCorte = $corte->format('Y-m');

        $roles = $this->hechos->roles();
        $trabajadores = $this->trabajadores->listarConActividadEntre($inicioMeses, $corte);
        $rolActual = $this->hechos->rolActualPorUsuario(array_map(fn (Trabajador $t): int => $t->userId(), $trabajadores));
        $jornadas = $this->hechos->jornadasEntre($inicioMeses, $corte);
        $obligaciones = $this->hechos->obligacionesMensuales($inicioMeses, $corte);
        $sueldos = $this->sueldosPorRol($roles);

        $total = 0;
        $dias = 0;
        $enCurso = 0;
        $programadoEnCurso = 0;
        $porRol = [];
        $porTrabajador = [];
        $sinSueldo = [];
        $sinObligacion = 0;
        $sinCerrar = [];
        $sueldoDistinto = 0;
        $avisos = [];

        foreach ($trabajadores as $trabajador) {
            $userId = $trabajador->userId();
            $fila = ['trabajadorId' => (int) $trabajador->id(), 'nombre' => $trabajador->nombreCompleto(), 'rol' => '', 'dias' => 0, 'importe' => 0];

            for ($mes = $inicioMeses; $mes <= $corte; $mes = $mes->modify('first day of next month')) {
                $finMes = $mes->modify('last day of this month');
                $claveMes = $mes->format('Y-m');
                $obligacion = $obligaciones[$userId][$claveMes] ?? null;
                $cerrada = $obligacion !== null && in_array($obligacion['estado'], [EstadoObligacionPago::PENDIENTE->value, EstadoObligacionPago::PAGADO->value], true);

                /** @var array<string, array{monto: int, rolId: int, trabajado: bool}> $devengoDelMes */
                $devengoDelMes = [];
                $acumulado = 0;

                for ($dia = $mes; $dia <= min($finMes, $corte); $dia = $dia->modify('+1 day')) {
                    if (! $trabajador->estaVigenteEn($dia)) {
                        continue;
                    }

                    $clave = $dia->format('Y-m-d');
                    $jornada = $jornadas[(int) $trabajador->id()][$clave] ?? null;
                    $rolId = $jornada['rolId'] ?? $rolActual[$userId] ?? null;
                    $rol = $rolId !== null ? ($roles[$rolId] ?? null) : null;

                    if ($rol === null || ! $rol['remunerable']) {
                        continue;
                    }

                    if ($rol['controlaAsistencia'] && ($jornada['estado'] ?? null) !== EstadoJornada::PRESENTE->value) {
                        continue;
                    }

                    $sueldo = $this->sueldoVigente($sueldos[$rolId] ?? [], $dia);

                    if ($sueldo === null) {
                        $sinSueldo[$rol['nombre']][] = $dia->format('d/m/Y');

                        continue;
                    }

                    $monto = max(0, min((int) round($sueldo / JornadaPersonal::DIAS_BASE_PERIODO), $sueldo - $acumulado));
                    $acumulado += $monto;
                    $devengoDelMes[$clave] = ['monto' => $monto, 'rolId' => $rolId, 'trabajado' => true];
                }

                if ($cerrada) {
                    $ultimo = $finMes->format('Y-m-d');
                    $residuo = self::centimos($obligacion['acumulado']) - $acumulado;

                    if ($residuo !== 0) {
                        $devengoDelMes[$ultimo] = [
                            'monto' => ($devengoDelMes[$ultimo]['monto'] ?? 0) + $residuo,
                            'rolId' => $devengoDelMes[$ultimo]['rolId'] ?? $rolActual[$userId] ?? 0,
                            'trabajado' => $devengoDelMes[$ultimo]['trabajado'] ?? false,
                        ];
                    }
                }

                $alCorte = 0;

                foreach ($devengoDelMes as $clave => $dia) {
                    if ($clave > $corte->format('Y-m-d')) {
                        continue;
                    }

                    $alCorte += $dia['monto'];

                    if ($clave < $desde->format('Y-m-d') || $clave > $finDevengo->format('Y-m-d')) {
                        continue;
                    }

                    $rolNombre = $roles[$dia['rolId']]['nombre'] ?? 'SIN_ROL';
                    $porRol[$dia['rolId']] ??= ['rolId' => $dia['rolId'], 'rol' => $rolNombre, 'trabajadores' => [], 'dias' => 0, 'importe' => 0];
                    $porRol[$dia['rolId']]['trabajadores'][$userId] = true;
                    $porRol[$dia['rolId']]['importe'] += $dia['monto'];
                    $fila['rol'] = $rolNombre;
                    $fila['importe'] += $dia['monto'];
                    $total += $dia['monto'];

                    // El ajuste de cierre suma importe, pero no es un día trabajado.
                    if ($dia['trabajado']) {
                        $porRol[$dia['rolId']]['dias']++;
                        $fila['dias']++;
                        $dias++;
                    }
                }

                $abiertoAlCorte = ! ($cerrada && $finMes < $corte);

                if ($claveMes === $mesCorte && $abiertoAlCorte) {
                    $enCurso += $alCorte;

                    if ($obligacion === null && $alCorte > 0) {
                        $sinObligacion++;
                    }
                }

                if ($claveMes < $mesCorte && $obligacion !== null && $obligacion['estado'] === EstadoObligacionPago::EN_CURSO->value) {
                    $sinCerrar[$claveMes] = true;
                }

                // Mes aún sin cerrar cuyo pago (día 1 siguiente, o el de su obligación) cae en el período.
                $fechaPago = $obligacion['fechaPago'] ?? $finMes->modify('+1 day')->format('Y-m-d');

                if (! $cerrada && $fechaPago >= $desde->format('Y-m-d') && $fechaPago <= $hasta->format('Y-m-d')) {
                    $programadoEnCurso += $alCorte;
                }

                if ($claveMes === $mesCorte && $obligacion !== null && $obligacion['estado'] === EstadoObligacionPago::EN_CURSO->value && $obligacion['sueldoBase'] !== null) {
                    $rolId = $rolActual[$userId] ?? null;
                    $vigente = $rolId !== null ? $this->sueldoVigente($sueldos[$rolId] ?? [], $corte) : null;

                    if ($vigente !== null && $vigente !== self::centimos($obligacion['sueldoBase'])) {
                        $sueldoDistinto++;
                        $avisos[] = sprintf(
                            '%s: su sueldo de %s se calcula con %s, pero el sueldo vigente del rol %s es %s.',
                            $trabajador->nombreCompleto(),
                            $claveMes,
                            self::soles(self::centimos($obligacion['sueldoBase'])),
                            $roles[$rolId]['nombre'],
                            self::soles($vigente),
                        );
                    }
                }
            }

            if ($fila['importe'] !== 0) {
                $porTrabajador[] = array_replace($fila, ['importe' => self::aSoles($fila['importe'])]);
            }
        }

        foreach ($sinSueldo as $rol => $fechas) {
            $avisos[] = sprintf('Rol %s sin sueldo vigente: %d día(s) trabajados sin valorizar (%s).', $rol, count($fechas), implode(', ', array_slice(array_unique($fechas), 0, 3)));
        }

        if ($sinObligacion > 0) {
            $avisos[] = sprintf('%d trabajador(es) con días devengados en %s todavía sin obligación de sueldo abierta (la abre la generación diaria de pagos).', $sinObligacion, $mesCorte);
        }

        if ($sinCerrar !== []) {
            $avisos[] = 'Nómina sin cerrar de '.implode(', ', array_keys($sinCerrar)).': su sueldo no figura como pendiente hasta el cierre mensual.';
        }

        return new DevengoPersonal(
            total: self::aSoles($total),
            dias: $dias,
            porRol: array_values(array_map(fn (array $rol): array => [
                'rolId' => (int) $rol['rolId'],
                'rol' => $rol['rol'],
                'trabajadores' => count($rol['trabajadores']),
                'dias' => $rol['dias'],
                'importe' => self::aSoles($rol['importe']),
            ], $porRol)),
            porTrabajador: $porTrabajador,
            enCursoAlCorte: self::aSoles($enCurso),
            programadoEnCurso: self::aSoles($programadoEnCurso),
            avisos: $avisos,
            incidencias: [
                'diasSinSueldo' => array_sum(array_map('count', $sinSueldo)),
                'rolesSinSueldo' => array_keys($sinSueldo),
                'sinObligacion' => $sinObligacion,
                'mesesSinCerrar' => array_keys($sinCerrar),
                'sueldoDistinto' => $sueldoDistinto,
            ],
        );
    }

    /**
     * Vigencias ACTIVAS del sueldo de cada rol remunerable (nunca las filas
     * individuales de trabajadores, que son solo historial).
     *
     * @param  array<int, array{nombre: string, remunerable: bool, controlaAsistencia: bool}>  $roles
     * @return array<int, ConfiguracionPago[]>
     */
    private function sueldosPorRol(array $roles): array
    {
        $sueldos = [];

        foreach ($roles as $rolId => $rol) {
            if ($rol['remunerable']) {
                $sueldos[$rolId] = $this->configuraciones->listarHistorial(TipoBeneficiarioPago::PERSONAL, $rolId);
            }
        }

        return $sueldos;
    }

    /**
     * Sueldo mensual (en céntimos) vigente ese día; la vigencia más reciente gana.
     *
     * @param  ConfiguracionPago[]  $configuraciones  ordenadas de la más reciente a la más antigua
     */
    private function sueldoVigente(array $configuraciones, DateTimeImmutable $dia): ?int
    {
        foreach ($configuraciones as $configuracion) {
            if ($configuracion->estaVigente($dia) && $configuracion->montoBase() !== null) {
                return self::centimos($configuracion->montoBase());
            }
        }

        return null;
    }

    private static function dia(DateTimeImmutable $fecha): DateTimeImmutable
    {
        return new DateTimeImmutable($fecha->format('Y-m-d'), new DateTimeZone('UTC'));
    }

    private static function centimos(float $soles): int
    {
        return (int) round($soles * 100);
    }

    private static function aSoles(int $centimos): float
    {
        return round($centimos / 100, 2);
    }

    private static function soles(int $centimos): string
    {
        return 'S/ '.number_format($centimos / 100, 2);
    }
}
