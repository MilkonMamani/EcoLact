<?php

namespace App\Application\Dashboard;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final readonly class DashboardPeriodo
{
    public function __construct(
        public string $nombre,
        public CarbonImmutable $desde,
        public CarbonImmutable $hasta,
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

        return new self($periodo, $inicio->startOfDay(), $fin->endOfDay());
    }

    /** @return array{string, string} */
    public function limitesSql(): array
    {
        return [$this->desde->toDateTimeString(), $this->hasta->toDateTimeString()];
    }

    public function etiqueta(): string
    {
        return $this->desde->format('d/m/Y').' — '.$this->hasta->format('d/m/Y');
    }
}
