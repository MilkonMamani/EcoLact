<?php

namespace App\Console\Commands;

use App\Application\Pago\SincronizarPagosProveedor;
use App\Domain\Pago\PeriodoPago;
use App\Infrastructure\Persistence\Models\AuditoriaModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditarPagosSemanales extends Command
{
    protected $signature = 'pagos:auditar-semanas {--consolidar-pendientes : Recalcula los pendientes con trazabilidad} {--preservar-historicos : Identifica duplicados PAGADOS como excepciones históricas sin alterar importes ni comprobantes}';

    protected $description = 'Audita duplicados por proveedor y semana; por defecto no modifica datos';

    public function handle(SincronizarPagosProveedor $sincronizar): int
    {
        if ($this->option('consolidar-pendientes')) {
            $sincronizar->execute();
        }
        $filas = ObligacionPagoModel::where('tipo_beneficiario', 'PROVEEDOR')
            ->withCount('detalles')->with('pago')->orderBy('id')->get();
        $grupos = $filas->groupBy(fn (ObligacionPagoModel $o): string => $o->proveedor_id.':'.PeriodoPago::semanaQueContiene(DateTimeImmutable::createFromInterface($o->periodo_inicio))->clave())
            ->filter(fn ($grupo): bool => $grupo->count() > 1);
        $this->info($filas->count().' obligaciones; '.$grupos->count().' grupos duplicados.');
        foreach ($grupos as $clave => $grupo) {
            $this->line($clave);
            $this->table(['ID', 'Proveedor', 'Inicio', 'Fin', 'Estado', 'Monto', 'Entregas', 'Pago'], $grupo->map(fn (ObligacionPagoModel $o): array => [
                $o->id, $o->beneficiario_nombre, $o->periodo_inicio->format('Y-m-d'), $o->periodo_fin->format('Y-m-d'),
                $o->estado, $o->monto_calculado, $o->detalles_count, $o->pago?->id ?? '—',
            ])->all());
        }
        if ($this->option('preservar-historicos')) {
            if (! Schema::hasColumn('obligaciones_pago', 'excepcion_semanal_historica')) {
                $this->error('Instale primero la migración add_provider_payment_week.');

                return self::FAILURE;
            }
            foreach ($grupos as $grupo) {
                if ($grupo->contains(fn (ObligacionPagoModel $o): bool => $o->estado !== 'PAGADO' || $o->pago === null)) {
                    $this->error('El grupo contiene obligaciones sin pago confirmado; requiere consolidación o revisión.');

                    return self::FAILURE;
                }
            }
            DB::transaction(function () use ($grupos): void {
                foreach ($grupos as $grupo) {
                    ProveedorModel::whereKey($grupo->first()->proveedor_id)->lockForUpdate()->firstOrFail();
                    foreach ($grupo->skip(1) as $fila) {
                        $actual = ObligacionPagoModel::whereKey($fila->id)->lockForUpdate()->firstOrFail();
                        if ($actual->excepcion_semanal_historica) {
                            continue;
                        }
                        AuditoriaModel::create([
                            'accion' => 'EXCEPCION_SEMANAL', 'tabla_afectada' => 'obligaciones_pago', 'registro_id' => $actual->id,
                            'datos_anteriores' => $actual->toArray(), 'datos_nuevos' => ['excepcion_semanal_historica' => true],
                            'motivo' => 'Conservación explícita de pagos históricos duplicados, sin modificar movimientos ni comprobantes.',
                        ]);
                        DB::table('obligaciones_pago')->where('id', $actual->id)->update(['excepcion_semanal_historica' => true]);
                    }
                }
            });
        }

        return self::SUCCESS;
    }
}
