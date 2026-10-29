<?php

namespace App\Infrastructure\Mantenimiento;

use App\Application\Produccion\RegistrarIngresoLecheDesdeEntrega;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Produccion\Insumo;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reinicia SOLO el flujo INSUMOS (menos la leche) -> RECETAS -> PRODUCCIÓN -> PRODUCTOS
 * TERMINADOS, conservando por completo el acopio (proveedores, acopiadores, usuarios, zonas,
 * rutas, recorridos, entregas) y la LECHE acopiada.
 *
 * Es una herramienta de mantenimiento para desarrollo (ver el comando
 * `ecolact:limpiar-datos-produccion`, que impide ejecutarla en producción). Todo ocurre en
 * UNA transacción y respetando el orden que imponen las llaves foráneas reales: no se
 * desactivan las FK. Si algo falla o una verificación no se cumple, se revierte todo.
 *
 * Reglas sobre la leche (insumo con codigo LECHE):
 *  - Se conservan sus movimientos de acopio (INGRESO_ACOPIO y REVERSO de entregas) y sus ajustes.
 *  - Se eliminan sus movimientos de consumo de producción (CONSUMO_PRODUCCION o con origen en
 *    un lote), porque las producciones se borran.
 *  - Su stock se recalcula como la suma con signo de los movimientos que quedan. Nunca se inventa.
 */
final class LimpiadorDatosProduccion
{
    /** Tablas que quedan completamente vacías (sirve además para reiniciar sus contadores). */
    private const TABLAS_VACIADAS = [
        'produccion_detalles', 'lote_produccion_insumos', 'lotes_produccion', 'planificaciones_produccion',
        'receta_detalles', 'recetas', 'producto_movimientos', 'producto_precios', 'productos',
    ];

    /**
     * Foto de lo que se conservará y lo que se eliminará.
     *
     * @return array{conservar: array<string, float|int|string>, eliminar: array<string, int>, bloqueos: list<string>, leche_sin_movimiento: list<int>}
     */
    public function resumen(): array
    {
        $lecheId = $this->lecheId();

        return [
            'conservar' => [
                'Proveedores' => DB::table('proveedores')->count(),
                'Acopiadores' => DB::table('acopiadores')->count(),
                'Usuarios' => DB::table('users')->count(),
                'Zonas' => DB::table('zonas')->count(),
                'Entregas' => DB::table('entregas')->count(),
                'Litros registrados en entregas' => (float) DB::table('entregas')->sum('litros'),
                'Movimientos de acopio de la leche' => $this->movimientosLecheConservados($lecheId)->count(),
                'Stock actual de leche (L)' => (float) DB::table('insumos')->where('id', $lecheId)->value('stock_actual'),
                'Stock de leche tras la limpieza (L)' => $this->stockLecheTrasLimpieza($lecheId),
            ],
            'eliminar' => [
                'Recetas' => DB::table('recetas')->count(),
                'Detalles de receta' => DB::table('receta_detalles')->count(),
                'Lotes de producción' => DB::table('lotes_produccion')->count(),
                'Detalles de producción' => DB::table('produccion_detalles')->count(),
                'Consumos de insumos por lote' => DB::table('lote_produccion_insumos')->count(),
                'Productos terminados' => DB::table('productos')->count(),
                'Movimientos de productos' => DB::table('producto_movimientos')->count(),
                'Precios de productos' => DB::table('producto_precios')->count(),
                'Otros insumos (sin la leche)' => DB::table('insumos')->where('id', '!=', $lecheId)->count(),
                'Movimientos de otros insumos' => DB::table('insumo_movimientos')->where('insumo_id', '!=', $lecheId)->count(),
                'Consumos de leche por producción' => $this->movimientosLecheDeProduccion($lecheId)->count(),
            ],
            'bloqueos' => $this->bloqueos(),
            'leche_sin_movimiento' => $this->entregasValidasSinIngreso(),
        ];
    }

    /**
     * Datos ajenos al flujo que impedirían borrar los productos sin perderlos.
     *
     * @return list<string>
     */
    public function bloqueos(): array
    {
        $bloqueos = [];
        $ventas = DB::table('ventas_producto')->count();

        if ($ventas > 0) {
            $enLiquidaciones = DB::table('ajustes_liquidacion')->whereNotNull('venta_producto_id')->count();
            $bloqueos[] = "Existen {$ventas} venta(s) de productos".($enLiquidaciones > 0 ? " y {$enLiquidaciones} ajuste(s) de liquidación ligados a ellas" : '').'. Los productos no se pueden borrar sin perder ventas/liquidaciones reales (datos ajenos a este flujo), así que no se toca nada.';
        }

        return $bloqueos;
    }

    /**
     * Ejecuta la limpieza. Devuelve lo eliminado, el estado final y las advertencias.
     *
     * @return array{eliminados: array<string, int>, despues: array<string, float|int|string>, advertencias: list<string>}
     *
     * @throws DomainException si algo impide la limpieza segura (se revierte todo).
     */
    public function ejecutar(bool $reconstruirLeche = false): array
    {
        if ($this->bloqueos() !== []) {
            throw new DomainException(implode(' ', $this->bloqueos()));
        }

        $imagenes = DB::table('productos')->whereNotNull('imagen')->pluck('imagen')->all();

        $resultado = DB::transaction(function () use ($reconstruirLeche): array {
            $lecheId = $this->lecheId();
            $entregasAntes = ['n' => DB::table('entregas')->count(), 'litros' => (float) DB::table('entregas')->sum('litros')];
            $conservadosAntes = [
                'proveedores' => DB::table('proveedores')->count(),
                'acopiadores' => DB::table('acopiadores')->count(),
                'users' => DB::table('users')->count(),
            ];
            $advertencias = [];
            $eliminados = [];

            // 1. Hijos del producto terminado (las ventas ya se descartaron en bloqueos()).
            $eliminados['Movimientos de productos'] = DB::table('producto_movimientos')->delete();
            $eliminados['Precios de productos'] = DB::table('producto_precios')->delete();

            // 2. Consumos y movimientos ligados a lotes, y los movimientos de los demás insumos.
            $eliminados['Detalles de producción'] = DB::table('produccion_detalles')->delete();
            $eliminados['Consumos de insumos por lote'] = DB::table('lote_produccion_insumos')->delete();
            $eliminados['Consumos de leche por producción'] = $this->movimientosLecheDeProduccion($lecheId)->delete();
            $eliminados['Movimientos de otros insumos'] = DB::table('insumo_movimientos')->where('insumo_id', '!=', $lecheId)->delete();

            // 3. Lotes, recetas y productos (en el orden que piden las FK).
            $eliminados['Lotes de producción'] = DB::table('lotes_produccion')->delete();
            $eliminados['Planificaciones de producción'] = DB::table('planificaciones_produccion')->delete();
            $eliminados['Detalles de receta'] = DB::table('receta_detalles')->delete();
            $eliminados['Recetas'] = DB::table('recetas')->delete();
            $eliminados['Productos terminados'] = DB::table('productos')->delete();

            // 4. Insumos, menos la leche del sistema.
            $eliminados['Otros insumos (sin la leche)'] = DB::table('insumos')->where('id', '!=', $lecheId)->delete();

            // 5. Entregas válidas que nunca ingresaron leche: solo si se pide, y solo con datos reales.
            $sinIngreso = $this->entregasValidasSinIngreso();

            if ($sinIngreso !== [] && $reconstruirLeche) {
                foreach ($sinIngreso as $entregaId) {
                    $entrega = app(EntregaRepository::class)->buscarPorId($entregaId);

                    if ($entrega !== null) {
                        app(RegistrarIngresoLecheDesdeEntrega::class)->execute($entrega);
                    }
                }

                $advertencias[] = 'Se reconstruyó el ingreso de leche de '.count($sinIngreso).' entrega(s) que no lo tenían.';
            } elseif ($sinIngreso !== []) {
                $advertencias[] = 'Hay '.count($sinIngreso).' entrega(s) válida(s) sin movimiento de leche (ids: '.implode(', ', $sinIngreso).'). No se inventó su stock; use --reconstruir-leche para registrarlo.';
            }

            // 6. Stock de leche = suma con signo de los movimientos que se conservan.
            $stock = $this->stockLecheTrasLimpieza($lecheId);

            if ($stock < 0.0) {
                throw new DomainException('El stock de leche resultante sería negativo ('.$stock.' L). Se revirtió todo.');
            }

            DB::table('insumos')->where('id', $lecheId)->update(['stock_actual' => $stock, 'updated_at' => now()]);

            $litrosValidos = $this->litrosDeEntregasValidas();

            if (abs($stock - $litrosValidos) > 0.005) {
                $advertencias[] = "El stock de leche ({$stock} L) no coincide con los litros de entregas válidas ({$litrosValidos} L): hay ajustes manuales o reversos de leche que se conservaron.";
            }

            // 7. Verificaciones: si algo no cuadra, se revierte todo.
            $this->verificar($lecheId, $entregasAntes, $conservadosAntes);

            return [
                'eliminados' => $eliminados,
                'despues' => $this->estadoFinal($lecheId),
                'advertencias' => $advertencias,
            ];
        });

        // Fuera de la transacción: archivos de imagen de los productos borrados y contadores.
        foreach ($imagenes as $ruta) {
            Storage::disk('public')->delete($ruta);
        }

        $this->reiniciarContadores();

        return $resultado;
    }

    /**
     * @return array<string, float|int|string>
     */
    public function estadoFinal(?int $lecheId = null): array
    {
        $lecheId ??= $this->lecheId();
        $leche = DB::table('insumos')->where('id', $lecheId)->first();

        return [
            'Proveedores' => DB::table('proveedores')->count(),
            'Acopiadores' => DB::table('acopiadores')->count(),
            'Entregas' => DB::table('entregas')->count(),
            'Litros registrados en entregas' => (float) DB::table('entregas')->sum('litros'),
            'Insumo Leche' => $leche !== null ? "sí ({$leche->tipo}, {$leche->unidad_base}, código {$leche->codigo})" : 'NO',
            'Stock de leche (L)' => (float) ($leche->stock_actual ?? 0),
            'Recetas' => DB::table('recetas')->count(),
            'Detalles de receta' => DB::table('receta_detalles')->count(),
            'Lotes de producción' => DB::table('lotes_produccion')->count(),
            'Detalles de producción' => DB::table('produccion_detalles')->count(),
            'Productos terminados' => DB::table('productos')->count(),
            'Movimientos de productos' => DB::table('producto_movimientos')->count(),
            'Otros insumos' => DB::table('insumos')->where('id', '!=', $lecheId)->count(),
        ];
    }

    private function lecheId(): int
    {
        $id = DB::table('insumos')->where('codigo', Insumo::CODIGO_LECHE)->value('id');

        if ($id === null) {
            throw new DomainException('No existe el insumo del sistema LECHE (codigo = LECHE). No se toca nada.');
        }

        return (int) $id;
    }

    /**
     * Movimientos de leche que provienen de la producción (se eliminan con los lotes).
     */
    private function movimientosLecheDeProduccion(int $lecheId): Builder
    {
        return DB::table('insumo_movimientos')
            ->where('insumo_id', $lecheId)
            ->where(function ($consulta): void {
                $consulta->where('motivo', MotivoMovimientoInsumo::CONSUMO_PRODUCCION->value)
                    ->orWhereNotNull('lote_produccion_id')
                    ->orWhere('origen_tipo', 'LOTE_PRODUCCION');
            });
    }

    private function movimientosLecheConservados(int $lecheId): Builder
    {
        return DB::table('insumo_movimientos')
            ->where('insumo_id', $lecheId)
            ->whereNotIn('id', $this->movimientosLecheDeProduccion($lecheId)->select('id'));
    }

    private function stockLecheTrasLimpieza(int $lecheId): float
    {
        $filas = $this->movimientosLecheConservados($lecheId)->get(['tipo', 'cantidad']);

        return round((float) $filas->sum(
            fn (object $m): float => $m->tipo === MotivoMovimientoInsumo::ENTRADA ? (float) $m->cantidad : -(float) $m->cantidad,
        ), 4);
    }

    /**
     * Litros de las entregas que cuentan como leche acopiada (mismas reglas que
     * entregasValidasSinIngreso): no anuladas y sin análisis RECHAZADA.
     */
    private function litrosDeEntregasValidas(): float
    {
        return round((float) $this->consultaEntregasValidas()->sum('entregas.litros'), 4);
    }

    private function consultaEntregasValidas(): Builder
    {
        return DB::table('entregas')
            ->leftJoin('analisis_calidad', 'analisis_calidad.entrega_id', '=', 'entregas.id')
            ->where('entregas.estado', '!=', 'ANULADA')
            ->where(fn ($q) => $q->whereNull('analisis_calidad.resultado')->orWhere('analisis_calidad.resultado', '!=', 'RECHAZADA'));
    }

    /**
     * Entregas válidas (no anuladas, sin análisis RECHAZADA: esa leche nunca se acopió) cuyo
     * ingreso de leche no está registrado como movimiento.
     *
     * @return list<int>
     */
    private function entregasValidasSinIngreso(): array
    {
        return $this->consultaEntregasValidas()
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')->from('insumo_movimientos as m')
                    ->whereColumn('m.origen_id', 'entregas.id')
                    ->where('m.origen_tipo', 'ENTREGA')
                    ->where('m.motivo', MotivoMovimientoInsumo::INGRESO_ACOPIO->value);
            })
            ->pluck('entregas.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array{n: int, litros: float}  $entregasAntes
     * @param  array<string, int>  $conservadosAntes
     */
    private function verificar(int $lecheId, array $entregasAntes, array $conservadosAntes): void
    {
        $fallos = [];

        foreach (['recetas', 'receta_detalles', 'lotes_produccion', 'planificaciones_produccion', 'produccion_detalles', 'lote_produccion_insumos', 'productos', 'producto_movimientos'] as $tabla) {
            if (DB::table($tabla)->count() !== 0) {
                $fallos[] = "{$tabla} no quedó vacía";
            }
        }

        if (DB::table('insumos')->where('id', '!=', $lecheId)->count() !== 0 || DB::table('insumo_movimientos')->where('insumo_id', '!=', $lecheId)->count() !== 0) {
            $fallos[] = 'quedaron insumos o movimientos ajenos a la leche';
        }

        if (DB::table('entregas')->count() !== $entregasAntes['n'] || round((float) DB::table('entregas')->sum('litros'), 2) !== round($entregasAntes['litros'], 2)) {
            $fallos[] = 'las entregas cambiaron';
        }

        foreach ($conservadosAntes as $tabla => $cantidad) {
            if (DB::table($tabla)->count() !== $cantidad) {
                $fallos[] = "{$tabla} cambió";
            }
        }

        if ($fallos !== []) {
            throw new DomainException('Verificación fallida: '.implode('; ', $fallos).'. Se revirtió todo.');
        }
    }

    private function reiniciarContadores(): void
    {
        $driver = DB::connection()->getDriverName();

        foreach (self::TABLAS_VACIADAS as $tabla) {
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `{$tabla}` AUTO_INCREMENT = 1");
            } elseif ($driver === 'sqlite') {
                DB::table('sqlite_sequence')->where('name', $tabla)->delete();
            }
        }
    }
}
