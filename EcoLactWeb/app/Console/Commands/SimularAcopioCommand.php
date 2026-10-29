<?php

namespace App\Console\Commands;

use App\Infrastructure\Mantenimiento\SimuladorAcopio;
use DomainException;
use Illuminate\Console\Command;

/**
 * Carga la simulación de acopio del 28/10/2026 (solo entornos local/testing).
 * Sin --apply solo verifica y muestra el plan. Las reglas están en SimuladorAcopio.
 */
class SimularAcopioCommand extends Command
{
    protected $signature = 'ecolact:simular-acopio
        {--dry-run : Solo verifica y muestra el plan; no modifica nada (por defecto)}
        {--apply : Registra las entregas y las muestras}
        {--analista= : Usuario del analista de Calidad que selecciona las muestras (si hay más de uno)}
        {--force : Con --apply, omite la confirmación interactiva}';

    protected $description = 'Simula el acopio por QR del 28/10/2026 (12 500 L, 4 zonas, 4 muestras pendientes de análisis). Solo desarrollo.';

    public function handle(SimuladorAcopio $simulador): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Esta simulación solo puede ejecutarse en los entornos local o testing.');

            return self::FAILURE;
        }

        $aplicar = (bool) $this->option('apply') && ! $this->option('dry-run');
        $plan = $simulador->planificar($this->option('analista') ?: null);

        $this->mostrarPlan($plan);

        foreach ($plan['avisos'] as $aviso) {
            $this->warn($aviso);
        }

        if ($plan['conflictos'] !== []) {
            foreach ($plan['conflictos'] as $conflicto) {
                $this->error($conflicto);
            }

            $this->warn('Resuelve los conflictos antes de aplicar. No se modificó nada.');

            return self::FAILURE;
        }

        $previo = $simulador->resumen();

        if ($previo['entregas'] > 0) {
            $this->info(sprintf('El lote %s ya tiene %d entregas registradas; volver a aplicarlo no duplica nada.', SimuladorAcopio::LOTE, $previo['entregas']));
        }

        if (! $aplicar) {
            $this->info('Simulación (--dry-run): no se modificó nada. Ejecuta con --apply para registrar el lote.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Registrar las entregas y muestras de la simulación?', false)) {
            $this->info('Cancelado. No se modificó nada.');

            return self::SUCCESS;
        }

        try {
            $simulador->aplicar($plan);
        } catch (DomainException $e) {
            $this->error($e->getMessage().' No se registró nada (transacción revertida).');

            return self::FAILURE;
        }

        $this->mostrarResumen($simulador->resumen());

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $plan */
    private function mostrarPlan(array $plan): void
    {
        $filas = [];

        foreach ($plan['zonas'] as $zona) {
            foreach ($zona['proveedores'] as $proveedor) {
                foreach ($proveedor['entregas'] as $i => $entrega) {
                    $filas[] = [
                        $zona['zona'], $proveedor['dni'], $proveedor['nombre'], $entrega['hora'],
                        number_format($entrega['litros'], 2), $i === 0 && $proveedor['muestra'] !== null ? 'Muestra pendiente' : '—',
                    ];
                }
            }
        }

        $this->table(['Zona', 'DNI', 'Proveedor', 'Hora', 'Litros', 'Calidad'], $filas);
    }

    /** @param  array<string, mixed>  $resumen */
    private function mostrarResumen(array $resumen): void
    {
        $this->info('Lote '.SimuladorAcopio::LOTE.' registrado.');
        $this->table(
            ['Zona', 'Acopiador', 'Proveedores', 'Entregas', 'Litros', 'Pendientes de análisis'],
            array_map(fn (array $z): array => [$z['zona'], $z['acopiador'], $z['proveedores'], $z['entregas'], number_format($z['litros'], 2), $z['pendientes']], $resumen['zonas']),
        );
        $this->line(sprintf('Total del lote: %s L en %d entregas, %d pendientes de análisis.', number_format($resumen['total'], 2), $resumen['entregas'], $resumen['pendientes']));
        $this->line(sprintf('Entregas previas del %s (fuera del lote): %d (%s L). Total del día: %s L.',
            SimuladorAcopio::FECHA, $resumen['previas_dia']['entregas'], number_format($resumen['previas_dia']['litros'], 2), number_format($resumen['total_dia'], 2)));
    }
}
