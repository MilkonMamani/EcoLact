<?php

namespace App\Application\Dashboard;

use App\Domain\Pago\PeriodoPago;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Único lugar donde el dashboard administrativo traduce su filtro a fechas.
 *
 * - hoy: el día actual completo.
 * - semana: la semana calendario actual, de lunes a domingo (no 7 días móviles).
 * - mes: el mes calendario actual (no 30 días móviles).
 * - personalizado: rango explícito (solo lo usa la API).
 *
 * Todo se resuelve en la zona horaria configurada de la aplicación. Las
 * consultas reciben estos límites; ninguna vuelve a calcular fechas.
 */
final readonly class DashboardPeriodo
{
    public const PERIODOS = ['hoy', 'semana', 'mes'];

    public function __construct(
        public string $nombre,
        public CarbonImmutable $desde,
        public CarbonImmutable $hasta,
        public ?CarbonImmutable $ahora = null,
    ) {}

    public static function resolver(string $periodo, ?string $desde = null, ?string $hasta = null): self
    {
        $hoy = CarbonImmutable::now(config('app.timezone'));

        [$inicio, $fin] = match ($periodo) {
            'hoy' => [$hoy, $hoy],
            'semana' => [$hoy->startOfWeek(CarbonInterface::MONDAY), $hoy->endOfWeek(CarbonInterface::SUNDAY)],
            'mes' => [$hoy->startOfMonth(), $hoy->endOfMonth()],
            'personalizado' => [
                CarbonImmutable::createFromFormat('!Y-m-d', $desde ?? '', $hoy->timezone),
                CarbonImmutable::createFromFormat('!Y-m-d', $hasta ?? '', $hoy->timezone),
            ],
            default => throw new InvalidArgumentException('Período de dashboard no válido.'),
        };

        if ($fin->lessThan($inicio)) {
            throw new InvalidArgumentException('La fecha hasta debe ser igual o posterior a desde.');
        }

        return new self($periodo, $inicio->startOfDay(), $fin->endOfDay(), $hoy);
    }

    /** El día actual (según "ahora" de este período), para bloques que siempre representan hoy. */
    public function hoy(): self
    {
        $ahora = $this->ahora();

        return new self('hoy', $ahora->startOfDay(), $ahora->endOfDay(), $ahora);
    }

    /** @return array{string, string} */
    public function limitesSql(): array
    {
        return [$this->desde->toDateTimeString(), $this->hasta->toDateTimeString()];
    }

    /** @return array{string, string} */
    public function limitesFecha(): array
    {
        return [$this->desde->toDateString(), $this->hasta->toDateString()];
    }

    public function ahora(): CarbonImmutable
    {
        return $this->ahora ?? CarbonImmutable::now(config('app.timezone'));
    }

    /**
     * Último día del período que ya ocurrió (o null si todo el período es
     * futuro): lo que viene después no puede tener movimientos.
     */
    public function ultimoDiaTranscurrido(): ?CarbonImmutable
    {
        $hoy = $this->ahora()->startOfDay();

        if ($hoy->lessThan($this->desde->startOfDay())) {
            return null;
        }

        return $hoy->lessThan($this->hasta->startOfDay()) ? $hoy : $this->hasta->startOfDay();
    }

    public function etiqueta(): string
    {
        return $this->desde->format('d/m/Y').' — '.$this->hasta->format('d/m/Y');
    }

    /** Nombre corto del filtro para acompañar el rango ("Hoy", "Esta semana", "Este mes"). */
    public function nombreVisible(): string
    {
        return match ($this->nombre) {
            'hoy' => 'Hoy',
            'semana' => 'Esta semana',
            'mes' => 'Este mes',
            default => 'Rango personalizado',
        };
    }

    /** Rango legible: "jueves 24 sep. 2026", "lun. 21 sep. – dom. 27 sep. 2026" o "septiembre 2026". */
    public function rangoVisible(): string
    {
        $desde = $this->desde->locale('es');
        $hasta = $this->hasta->locale('es');

        return match (true) {
            $this->desde->isSameDay($this->hasta) => $desde->translatedFormat('l j M Y'),
            $this->nombre === 'mes' => $desde->translatedFormat('F Y'),
            default => $desde->translatedFormat('D j M').' – '.$hasta->translatedFormat('D j M Y'),
        };
    }

    /**
     * Intervalos del gráfico de acopio: horas para un solo día, días para
     * cualquier rango mayor. Cada intervalo indica si ya ocurrió.
     *
     * @return list<array{clave: string, etiqueta: string, titulo: string, futuro: bool}>
     */
    public function intervalosGrafico(): array
    {
        $ahora = $this->ahora();
        $intervalos = [];

        if ($this->porHoras()) {
            for ($hora = 0; $hora < 24; $hora++) {
                $inicio = $this->desde->setTime($hora, 0);
                $intervalos[] = [
                    'clave' => sprintf('%02d', $hora),
                    'etiqueta' => sprintf('%02d h', $hora),
                    'titulo' => $inicio->locale('es')->translatedFormat('D j M').' · '.$inicio->format('H:00').'–'.$inicio->format('H:59'),
                    'futuro' => $inicio->greaterThan($ahora),
                ];
            }

            return $intervalos;
        }

        for ($dia = $this->desde->startOfDay(); $dia->lessThanOrEqualTo($this->hasta); $dia = $dia->addDay()) {
            $intervalos[] = [
                'clave' => $dia->toDateString(),
                'etiqueta' => $this->nombre === 'semana' ? $dia->locale('es')->translatedFormat('D j') : $dia->format('j'),
                'titulo' => $dia->locale('es')->translatedFormat('l j \d\e F Y'),
                'futuro' => $dia->greaterThan($ahora),
            ];
        }

        return $intervalos;
    }

    public function porHoras(): bool
    {
        return $this->desde->isSameDay($this->hasta);
    }

    /**
     * Rango de periodo_inicio de las obligaciones semanales de proveedores
     * cuya fecha de pago programada (el jueves siguiente a su semana de
     * jueves a miércoles, ver PeriodoPago::fechaPagoProgramada) cae dentro
     * del período. Null si en el período no hay ningún día de pago.
     *
     * @return array{string, string}|null
     */
    public function inicioSemanasProveedorPagaderas(): ?array
    {
        $desde = $this->semanaQuePagaEl($this->desde, redondearArriba: true);
        $hasta = $this->semanaQuePagaEl($this->hasta, redondearArriba: false);

        if ($desde->inicio > $hasta->inicio) {
            return null;
        }

        return [$desde->inicio->format('Y-m-d'), $hasta->fin->format('Y-m-d')];
    }

    /** periodo_inicio mínimo cuyo pago aún no vencía al empezar el período. */
    public function inicioSemanaProveedorVigente(): string
    {
        return $this->semanaQuePagaEl($this->desde, redondearArriba: true)->inicio->format('Y-m-d');
    }

    /**
     * Semana (inicio Y-m-d) que admite la pantalla de pagos a proveedores
     * cuando el período contiene exactamente un día de pago; null si
     * contiene varios o ninguno.
     */
    public function semanaProveedorUnica(): ?string
    {
        $rango = $this->inicioSemanasProveedorPagaderas();

        if ($rango === null) {
            return null;
        }

        return (new DateTimeImmutable($rango[0]))->modify('+6 days')->format('Y-m-d') === $rango[1] ? $rango[0] : null;
    }

    /**
     * Mes trabajado (Y-m) cuyo sueldo se paga dentro del período (el pago
     * del personal se programa el día 1 del mes siguiente). Null si el
     * período no contiene ningún día 1 o contiene varios.
     */
    public function mesTrabajadoUnico(): ?string
    {
        $primeros = [];

        for ($mes = $this->desde->startOfMonth(); $mes->lessThanOrEqualTo($this->hasta); $mes = $mes->addMonth()) {
            if ($mes->greaterThanOrEqualTo($this->desde->startOfDay())) {
                $primeros[] = $mes;
            }
        }

        return count($primeros) === 1 ? $primeros[0]->subMonth()->format('Y-m') : null;
    }

    private function semanaQuePagaEl(CarbonImmutable $fecha, bool $redondearArriba): PeriodoPago
    {
        $referencia = new DateTimeImmutable($fecha->subDays(7)->toDateString());
        $semana = PeriodoPago::semanaQueContiene($referencia);

        return $redondearArriba && $semana->inicio < $referencia ? $semana->siguiente() : $semana;
    }
}
