<?php

namespace App\Console\Commands;

use App\Infrastructure\Mantenimiento\ConsolidadorZonas;
use DomainException;
use Illuminate\Console\Command;

/**
 * Une las zonas duplicadas por variantes de nombre ("Collana1" → "Collana 1") en su zona
 * canónica, reapuntando proveedores, rutas, historiales, entregas y toda otra referencia.
 * Sin --apply solo muestra el plan. Las reglas están en ConsolidadorZonas.
 */
class ConsolidarZonasCommand extends Command
{
    protected $signature = 'zonas:consolidar
        {--dry-run : Solo muestra lo que se haría; no modifica nada (por defecto)}
        {--apply : Ejecuta la consolidación}
        {--force : Con --apply, omite la confirmación interactiva}';

    protected $description = 'Consolida variantes de nombre de las zonas oficiales (Collana 1, Collana 2, Faon, Yasin) sin perder datos.';

    public function handle(ConsolidadorZonas $consolidador): int
    {
        $aplicar = (bool) $this->option('apply') && ! $this->option('dry-run');
        $plan = $consolidador->planificar();

        $this->mostrarZonas($consolidador);

        if ($plan === []) {
            $this->info('No hay zonas duplicadas que consolidar.');

            return self::SUCCESS;
        }

        foreach ($plan as $paso) {
            $this->mostrarPaso($paso);
        }

        $conflictos = array_merge([], ...array_column($plan, 'conflictos'));

        if ($conflictos !== []) {
            foreach ($conflictos as $conflicto) {
                $this->error($conflicto);
            }

            $this->warn('Resuelve los conflictos antes de aplicar. No se modificó nada.');

            return self::FAILURE;
        }

        if (! $aplicar) {
            $this->info('Simulación (--dry-run): no se modificó nada. Ejecuta con --apply para consolidar.');

            return self::SUCCESS;
        }

        $this->warn('Realiza un respaldo de la base de datos antes de continuar.');

        if (! $this->option('force') && ! $this->confirm('¿Consolidar las zonas indicadas?', false)) {
            $this->info('Cancelado. No se modificó nada.');

            return self::SUCCESS;
        }

        try {
            $consolidador->aplicar();
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Consolidación aplicada.');
        $this->mostrarZonas($consolidador);

        return self::SUCCESS;
    }

    private function mostrarZonas(ConsolidadorZonas $consolidador): void
    {
        $estado = $consolidador->verificar();

        $this->line('<comment>ZONAS</comment>');
        $this->table(['id', 'nombre', 'estado', 'delimitación'], array_map(
            fn (array $zona): array => [$zona['id'], $zona['nombre'], $zona['estado'], $zona['tiene_poligono'] ? 'sí' : 'pendiente'],
            $estado['zonas'],
        ));

        $huerfanas = array_filter($estado['huerfanas']);
        $huerfanas === []
            ? $this->line('Referencias huérfanas a zonas: ninguna.')
            : $this->error('Referencias huérfanas a zonas: '.json_encode($huerfanas));
    }

    /** @param  array<string, mixed>  $paso */
    private function mostrarPaso(array $paso): void
    {
        $this->newLine();
        $this->line("<comment>Zona origen:</comment>  {$paso['origen']['nombre']} (id {$paso['origen']['id']})");
        $this->line('<comment>Zona destino:</comment> '.($paso['destino'] ? "{$paso['destino']['nombre']} (id {$paso['destino']['id']})" : '—'));

        $this->table(['Referencia (tabla.columna → zonas.id)', 'Filas'], array_map(
            fn (string $referencia, int $filas): array => [$referencia, $filas],
            array_keys($paso['referencias']),
            $paso['referencias'],
        ));

        foreach ($paso['rutas'] as $ruta) {
            $this->line($ruta['accion'] === 'fusionar'
                ? "  Ruta \"{$ruta['nombre']}\" (id {$ruta['id']}) se fusiona con la ruta id {$ruta['destino_id']} de la zona destino."
                : "  Ruta \"{$ruta['nombre']}\" (id {$ruta['id']}) pasa a la zona destino.");
        }

        if ($paso['detalles_fusionados'] > 0) {
            $this->line("  Proveedores de ruta (ruta_detalles) que se agregan al final de la ruta destino: {$paso['detalles_fusionados']}");
        }

        $this->line('  Datos copiados a la destino (solo si le faltan): '.($paso['copiar'] === [] ? 'ninguno' : implode(', ', array_keys($paso['copiar']))));
        $this->line('  Conflictos detectados: '.count($paso['conflictos']));
    }
}
