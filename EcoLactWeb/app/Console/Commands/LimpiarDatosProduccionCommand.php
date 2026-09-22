<?php

namespace App\Console\Commands;

use App\Infrastructure\Mantenimiento\LimpiadorDatosProduccion;
use DomainException;
use Illuminate\Console\Command;

/**
 * Reinicia el flujo INSUMOS (menos Leche) -> RECETAS -> PRODUCCIÓN -> PRODUCTOS TERMINADOS
 * conservando el acopio (proveedores, acopiadores, entregas) y la leche acopiada.
 * Solo para desarrollo: aborta en producción.
 */
class LimpiarDatosProduccionCommand extends Command
{
    protected $signature = 'ecolact:limpiar-datos-produccion
        {--dry-run : Solo muestra el resumen; no borra nada}
        {--reconstruir-leche : Registra el ingreso de leche de entregas válidas que no lo tengan}
        {--force : Omite la confirmación interactiva}';

    protected $description = 'DESTRUCTIVO (solo desarrollo): elimina recetas, producciones, productos terminados e insumos excepto Leche.';

    public function handle(LimpiadorDatosProduccion $limpiador): int
    {
        if (app()->environment('production')) {
            $this->error('Comando bloqueado: no puede ejecutarse en producción.');

            return self::FAILURE;
        }

        try {
            $resumen = $limpiador->resumen();
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->mostrarTabla('DATOS QUE SE CONSERVARÁN', $resumen['conservar']);
        $this->mostrarTabla('DATOS QUE SE ELIMINARÁN', $resumen['eliminar']);

        if ($resumen['leche_sin_movimiento'] !== []) {
            $this->warn('Entregas válidas sin ingreso de leche (ids: '.implode(', ', $resumen['leche_sin_movimiento']).').'
                .($this->option('reconstruir-leche') ? ' Se registrará su ingreso.' : ' No se inventará su stock; use --reconstruir-leche para registrarlo.'));
        }

        if ($resumen['bloqueos'] !== []) {
            foreach ($resumen['bloqueos'] as $bloqueo) {
                $this->error($bloqueo);
            }

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Simulación: no se modificó nada.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            'ATENCIÓN: se eliminarán recetas, producciones, productos terminados e insumos excepto Leche. '
            .'Proveedores, acopiadores y entregas serán conservados. ¿Deseas continuar?',
            false,
        )) {
            $this->info('Cancelado. No se modificó nada.');

            return self::SUCCESS;
        }

        try {
            $resultado = $limpiador->ejecutar(reconstruirLeche: (bool) $this->option('reconstruir-leche'));
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->mostrarTabla('ELIMINADO', $resultado['eliminados']);
        $this->mostrarTabla('ESTADO FINAL', $resultado['despues']);

        foreach ($resultado['advertencias'] as $advertencia) {
            $this->warn($advertencia);
        }

        $this->info('Limpieza completada.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, float|int|string>  $filas
     */
    private function mostrarTabla(string $titulo, array $filas): void
    {
        $this->newLine();
        $this->line("<options=bold>{$titulo}</>");
        $this->table(['Concepto', 'Valor'], array_map(fn ($k, $v): array => [$k, $v], array_keys($filas), $filas));
    }
}
