<?php

namespace App\Console\Commands;

use App\Infrastructure\Mantenimiento\ConsolidadorSueldosPorRol;
use DateTimeImmutable;
use DomainException;
use Illuminate\Console\Command;

/**
 * Pasa los sueldos individuales por trabajador a un sueldo base por rol. Sin --apply solo
 * muestra el reporte (propuestas y conflictos). Las reglas están en ConsolidadorSueldosPorRol.
 */
class ConsolidarSueldosPorRolCommand extends Command
{
    protected $signature = 'pagos:consolidar-sueldos-por-rol
        {--dry-run : Solo muestra el reporte; no modifica nada (por defecto)}
        {--apply : Crea los sueldos por rol propuestos (sin conflictos)}
        {--force : Con --apply, omite la confirmación interactiva}';

    protected $description = 'Propone (y con --apply crea) el sueldo base de cada rol a partir de los sueldos individuales, sin elegir cuando hay valores distintos.';

    public function handle(ConsolidadorSueldosPorRol $consolidador): int
    {
        $hoy = DateTimeImmutable::createFromInterface(today());
        $aplicar = (bool) $this->option('apply') && ! $this->option('dry-run');
        $plan = $consolidador->planificar($hoy);

        $this->table(['Rol', 'Resultado', 'Sueldo del rol', 'Desde', 'Sueldos individuales', 'Heredan (sin sueldo individual)'], array_map(
            fn (array $paso): array => [
                $paso['rol'],
                $paso['estado'],
                $paso['sueldo'] !== null ? 'S/ '.number_format($paso['sueldo'], 2) : '—',
                $paso['desde'] ?? '—',
                implode("\n", array_map(fn (array $i): string => "{$i['nombre']}: S/ ".number_format($i['sueldo'], 2)." ({$i['desde']})", $paso['individuales'])) ?: '—',
                implode("\n", $paso['heredan']) ?: '—',
            ],
            $plan,
        ));

        foreach ($plan as $paso) {
            if ($paso['estado'] === 'CONFLICTO') {
                $this->error("{$paso['rol']}: sus trabajadores tienen sueldos distintos. No se elige uno automáticamente: configúralo en Configuración de pagos > Trabajadores.");
            }
        }

        $propuestos = count(array_filter($plan, fn (array $paso): bool => $paso['estado'] === 'PROPUESTO'));

        if ($propuestos === 0) {
            $this->info('No hay sueldos por rol que crear.');

            return self::SUCCESS;
        }

        if (! $aplicar) {
            $this->info("Simulación (--dry-run): no se modificó nada. Ejecuta con --apply para crear {$propuestos} sueldo(s) por rol.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("¿Crear {$propuestos} sueldo(s) por rol?", false)) {
            $this->info('Cancelado. No se modificó nada.');

            return self::SUCCESS;
        }

        try {
            $creados = $consolidador->aplicar($hoy);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sueldos por rol creados: {$creados}. Los sueldos individuales quedan como historial.");

        return self::SUCCESS;
    }
}
