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
 * Los días son los del negocio (config app.zona_horaria_negocio, America/Lima):
 * desde/hasta están en esa zona. Las columnas DATETIME se guardan en la zona
 * de la app (UTC), así que limitesSql() convierte el rango a esa zona y lo
 * entrega semiabierto [desde, finExclusivo): el inicio incluido y el límite
 * final excluido, para que dos períodos contiguos nunca compartan un registro.
 * Las columnas DATE (jornadas, periodos de pago) usan limitesFecha().
 *
 * Las consultas reciben estos límites; ninguna vuelve a calcular fechas.
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
        $hoy = CarbonImmutable::now(self::zonaNegocio());

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

    public static function zonaNegocio(): string
    {
        return config('app.zona_horaria_negocio', 'America/Lima');
    }

    /** Un valor DATETIME de la BD (zona de la app) expresado en la hora del negocio. */
    public static function momentoLocal(string $fechaHora): CarbonImmutable
    {
        return CarbonImmutable::parse($fechaHora, config('app.timezone'))->setTimezone(self::zonaNegocio());
    }

    /** El día actual (según "ahora" de este período), para bloques que siempre representan hoy. */
    public function hoy(): self
    {
        $ahora = $this->ahora();

        return new self('hoy', $ahora->startOfDay(), $ahora->endOfDay(), $ahora);
    }

    /** Primer instante que ya NO pertenece al período (00:00 del día siguiente a hasta). */
    public function finExclusivo(): CarbonImmutable
    {
        return $this->hasta->startOfDay()->addDay();
    }

    /**
     * Límites semiabiertos [desde, finExclusivo) para columnas DATETIME, en la
     * zona en que se guardan (la de la app).
     *
     * @return array{string, string}
     */
    public function limitesSql(): array
    {
        $zonaApp = config('app.timezone');

        return [
            $this->desde->setTimezone($zonaApp)->toDateTimeString(),
            $this->finExclusivo()->setTimezone($zonaApp)->toDateTimeString(),
        ];
    }

    /** @return array{string, string} días del negocio, ambos inclusive (columnas DATE) */
    public function limitesFecha(): array
    {
        return [$this->desde->toDateString(), $this->hasta->toDateString()];
    }

    public function ahora(): CarbonImmutable
    {
        return $this->ahora ?? CarbonImmutable::now(self::zonaNegocio());
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

    /**
     * Fecha de corte de los saldos: el último día transcurrido del período
     * (hoy, si el período sigue en curso). Un período enteramente futuro se
     * corta hoy: todavía no puede haber saldos posteriores.
     */
    public function fechaCorte(): CarbonImmutable
    {
        return $this->ultimoDiaTranscurrido() ?? $this->ahora()->startOfDay();
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
     * Semana de proveedores (inicio Y-m-d) cuya fecha de pago programada
     * (PeriodoPago::fechaPagoProgramada, la misma regla con la que se guarda
     * obligaciones_pago.fecha_pago_programada) cae dentro del período, cuando
     * hay exactamente una; null si hay varias o ninguna. Solo sirve para el
     * filtro del enlace a Pagar a proveedores: los importes salen de la
     * columna fecha_pago_programada, nunca de esta traducción.
     */
    public function semanaProveedorUnica(): ?string
    {
        [$desde, $hasta] = $this->limitesFecha();
        $semanas = [];

        // Se parte de la semana anterior para no depender de si el pago cae al
        // cierre de la semana o al día siguiente.
        $semana = PeriodoPago::semanaQueContiene((new DateTimeImmutable($desde))->modify('-7 days'));

        for (; $semana->fechaPagoProgramada()->format('Y-m-d') <= $hasta; $semana = $semana->siguiente()) {
            if ($semana->fechaPagoProgramada()->format('Y-m-d') >= $desde) {
                $semanas[] = $semana->clave();
            }
        }

        return count($semanas) === 1 ? $semanas[0] : null;
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
}
