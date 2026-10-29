<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Dashboard\Contracts\DashboardAdministradorQuery;
use App\Application\Dashboard\DashboardAdministradorData;
use App\Application\Dashboard\DashboardPeriodo;
use App\Application\Dashboard\ObtenerResumenFinanciero;
use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\LoteProduccion;
use App\Domain\Shared\Enums\EstadoAnalisis;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Infrastructure\Persistence\Models\ComunicadoModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lecturas del panel administrativo. Solo SELECT: abrir el dashboard nunca
 * genera obligaciones, marca pagos ni modifica inventario.
 */
final class EloquentDashboardAdministradorQuery implements DashboardAdministradorQuery
{
    private const ZONAS_VISIBLES = 5;

    private const PRODUCTOS_VISIBLES = 4;

    private const LOTES_VISIBLES = 4;

    private const COMUNICADOS_VISIBLES = 3;

    public function __construct(
        private readonly ObtenerResumenFinanciero $finanzas,
    ) {}

    public function obtener(DashboardPeriodo $periodo): DashboardAdministradorData
    {
        $produccion = $this->produccion($periodo);
        $existencias = $this->existencias();
        $asistencia = $this->asistencia($periodo);
        $hoy = $periodo->hoy();

        return new DashboardAdministradorData(
            periodo: $periodo,
            acopio: $this->acopio($periodo),
            produccion: $produccion,
            ventas: $this->ventas($periodo),
            finanzas: $this->finanzas->execute($periodo),
            acopioPorIntervalo: $this->acopioPorIntervalo($periodo),
            acopioPorZona: $this->acopioPorZona($periodo),
            porAtender: [
                'analisisPorConfirmar' => $this->analisisPorConfirmar($periodo),
                'insumosBajoMinimo' => $existencias['insumosBajoMinimo'],
                'insumosSinStock' => $existencias['insumosSinStock'],
                'lotesEnProceso' => $produccion['porEstado'][LoteProduccion::ESTADO_EN_PROCESO] ?? 0,
            ],
            existencias: $existencias,
            lotesRecientes: $this->lotesRecientes($periodo),
            asistencia: $asistencia,
            asistenciaHoy: $periodo->porHoras() && $periodo->desde->isSameDay($hoy->desde) ? $asistencia : $this->asistencia($hoy),
            comunicados: $this->comunicados($periodo),
        );
    }

    private function entregasValidas(DashboardPeriodo $periodo): Builder
    {
        return EntregaModel::query()->from('entregas as e')->toBase()
            ->where('e.fecha_hora', '>=', $periodo->limitesSql()[0])
            ->where('e.fecha_hora', '<', $periodo->limitesSql()[1])
            ->where('e.estado', '<>', EstadoEntrega::ANULADA->value);
    }

    /** @return array{litros: float, entregas: int, proveedores: int} */
    private function acopio(DashboardPeriodo $periodo): array
    {
        $fila = $this->entregasValidas($periodo)
            ->selectRaw('COALESCE(SUM(e.litros), 0) as litros, COUNT(*) as entregas, COUNT(DISTINCT e.proveedor_id) as proveedores')
            ->first();

        return [
            'litros' => round((float) $fila->litros, 2),
            'entregas' => (int) $fila->entregas,
            'proveedores' => (int) $fila->proveedores,
        ];
    }

    /**
     * Lotes INICIADOS en el período; el desglose por estado sale del mismo
     * conjunto, así que siempre suma el total.
     *
     * @return array{total: int, porEstado: array<string, int>}
     */
    private function produccion(DashboardPeriodo $periodo): array
    {
        $porEstado = LoteProduccionModel::query()->toBase()
            ->where('fecha_hora_inicio', '>=', $periodo->limitesSql()[0])->where('fecha_hora_inicio', '<', $periodo->limitesSql()[1])
            ->select('estado')->selectRaw('COUNT(*) as cantidad')
            ->groupBy('estado')->orderBy('estado')
            ->pluck('cantidad', 'estado')
            ->map(fn (mixed $cantidad): int => (int) $cantidad)
            ->all();

        return ['total' => array_sum($porEstado), 'porEstado' => $porEstado];
    }

    /**
     * Importe vendido (no cobrado): contado + descuento en planilla, sin
     * ventas anuladas.
     *
     * @return array{importe: float, ventas: int, contado: float, descuentoPlanilla: float}
     */
    private function ventas(DashboardPeriodo $periodo): array
    {
        $fila = VentaProductoModel::query()->toBase()
            ->where('fecha', '>=', $periodo->limitesSql()[0])->where('fecha', '<', $periodo->limitesSql()[1])
            ->where('estado', '<>', 'ANULADA')
            ->selectRaw('COALESCE(SUM(importe_total), 0) as importe, COUNT(*) as ventas')
            ->selectRaw("COALESCE(SUM(CASE WHEN forma_pago = 'CONTADO' THEN importe_total ELSE 0 END), 0) as contado")
            ->selectRaw("COALESCE(SUM(CASE WHEN forma_pago = 'DESCUENTO_PLANILLA' THEN importe_total ELSE 0 END), 0) as planilla")
            ->first();

        return [
            'importe' => round((float) $fila->importe, 2),
            'ventas' => (int) $fila->ventas,
            'contado' => round((float) $fila->contado, 2),
            'descuentoPlanilla' => round((float) $fila->planilla, 2),
        ];
    }

    /**
     * Un intervalo por hora (un día) o por día (semana/mes). Los intervalos
     * futuros llevan null (no "cero"): todavía no pueden tener entregas.
     *
     * @return list<array{clave: string, etiqueta: string, titulo: string, futuro: bool, litros: float|null, entregas: int|null}>
     */
    private function acopioPorIntervalo(DashboardPeriodo $periodo): array
    {
        // Se agrupa en PHP por hora/día del negocio: DATE() en SQL agruparía por el día UTC.
        $formato = $periodo->porHoras() ? 'H' : 'Y-m-d';
        $totales = [];

        foreach ($this->entregasValidas($periodo)->select('e.fecha_hora', 'e.litros')->get() as $entrega) {
            $clave = DashboardPeriodo::momentoLocal($entrega->fecha_hora)->format($formato);
            $totales[$clave]['litros'] = ($totales[$clave]['litros'] ?? 0.0) + (float) $entrega->litros;
            $totales[$clave]['entregas'] = ($totales[$clave]['entregas'] ?? 0) + 1;
        }

        return array_map(function (array $intervalo) use ($totales): array {
            $total = $totales[$intervalo['clave']] ?? null;
            $vacio = $intervalo['futuro'] && $total === null;

            return $intervalo + [
                'litros' => $vacio ? null : round((float) ($total['litros'] ?? 0), 2),
                'entregas' => $vacio ? null : (int) ($total['entregas'] ?? 0),
            ];
        }, $periodo->intervalosGrafico());
    }

    /**
     * Litros por zona histórica de la entrega, de mayor a menor. Si hay
     * demasiadas zonas, las menores se agrupan en "Otras" con su suma real.
     *
     * @return list<array{nombre: string, litros: float, otras: bool}>
     */
    private function acopioPorZona(DashboardPeriodo $periodo): array
    {
        $zonas = $this->entregasValidas($periodo)
            ->join('zonas as z', 'z.id', '=', 'e.zona_id')
            ->select('z.id', 'z.nombre')->selectRaw('SUM(e.litros) as litros')
            ->groupBy('z.id', 'z.nombre')
            ->orderByDesc('litros')->orderBy('z.nombre')
            ->get()
            ->map(fn (object $zona): array => ['nombre' => $zona->nombre, 'litros' => round((float) $zona->litros, 2), 'otras' => false]);

        if ($zonas->count() <= self::ZONAS_VISIBLES + 1) {
            return $zonas->all();
        }

        return [
            ...$zonas->take(self::ZONAS_VISIBLES)->all(),
            ['nombre' => 'Otras '.($zonas->count() - self::ZONAS_VISIBLES).' zonas', 'litros' => round($zonas->skip(self::ZONAS_VISIBLES)->sum('litros'), 2), 'otras' => true],
        ];
    }

    /** Entregas válidas del período cuyo análisis sigue en borrador. */
    private function analisisPorConfirmar(DashboardPeriodo $periodo): int
    {
        return $this->entregasValidas($periodo)
            ->join('analisis_calidad as a', 'a.entrega_id', '=', 'e.id')
            ->where('a.estado', EstadoAnalisis::BORRADOR->value)
            ->count();
    }

    /**
     * Saldos ACTUALES (no hay historial de saldos): leche disponible,
     * productos terminados con stock y alertas de insumos con las mismas
     * reglas que la pantalla de Insumos.
     *
     * @return array{leche: array{cantidad: float, unidad: string}|null, productos: list<array{id: int, nombre: string, cantidad: float, unidad: string}>, productosConStock: int, insumosBajoMinimo: int, insumosSinStock: int}
     */
    private function existencias(): array
    {
        $leche = InsumoModel::query()->toBase()->where('codigo', Insumo::CODIGO_LECHE)->first(['stock_actual', 'unidad_base']);
        $insumos = InsumoModel::query()->toBase()
            ->selectRaw('COALESCE(SUM(CASE WHEN stock_minimo > 0 AND stock_actual > 0 AND stock_actual <= stock_minimo THEN 1 ELSE 0 END), 0) as bajo')
            ->selectRaw('COALESCE(SUM(CASE WHEN stock_actual <= 0 THEN 1 ELSE 0 END), 0) as sin_stock')
            ->first();

        $saldo = '('.EloquentProductosListadoQuery::SALDO_SQL.')';
        $conStock = ProductoModel::query()->from('productos as p')->toBase()
            ->where('p.estado', 'ACTIVO')
            ->whereRaw($saldo.' > 0');

        return [
            'leche' => $leche !== null ? ['cantidad' => round((float) $leche->stock_actual, 2), 'unidad' => $leche->unidad_base] : null,
            'productos' => (clone $conStock)
                ->select('p.id', 'p.nombre', 'p.unidad_medida')->selectRaw($saldo.' as saldo')
                ->orderBy('p.nombre')->orderBy('p.id')->limit(self::PRODUCTOS_VISIBLES)->get()
                ->map(fn (object $producto): array => [
                    'id' => (int) $producto->id,
                    'nombre' => $producto->nombre,
                    'cantidad' => round((float) $producto->saldo, 2),
                    'unidad' => $producto->unidad_medida,
                ])->all(),
            'productosConStock' => (clone $conStock)->count(),
            'insumosBajoMinimo' => (int) $insumos->bajo,
            'insumosSinStock' => (int) $insumos->sin_stock,
        ];
    }

    /**
     * Últimos lotes iniciados en el período con TODOS sus productos (un lote
     * puede producir varios), leídos en una sola consulta adicional.
     *
     * @return list<array{id: int, codigo: string, estado: string, fecha: string, productos: list<string>}>
     */
    private function lotesRecientes(DashboardPeriodo $periodo): array
    {
        $lotes = LoteProduccionModel::query()->toBase()
            ->where('fecha_hora_inicio', '>=', $periodo->limitesSql()[0])->where('fecha_hora_inicio', '<', $periodo->limitesSql()[1])
            ->select('id', 'codigo', 'estado', 'fecha_hora_inicio')
            ->orderByDesc('fecha_hora_inicio')->orderByDesc('id')
            ->limit(self::LOTES_VISIBLES)->get();

        $productos = $lotes->isEmpty() ? collect() : DB::table('produccion_detalles as d')
            ->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->whereIn('d.lote_produccion_id', $lotes->pluck('id'))
            ->select('d.lote_produccion_id', 'p.nombre')
            ->orderBy('p.nombre')->get()
            ->groupBy('lote_produccion_id');

        return $lotes->map(fn (object $lote): array => [
            'id' => (int) $lote->id,
            'codigo' => $lote->codigo,
            'estado' => $lote->estado,
            'fecha' => $this->fecha($lote->fecha_hora_inicio),
            'productos' => $productos->get($lote->id, collect())->pluck('nombre')->unique()->values()->all(),
        ])->all();
    }

    /**
     * Un día: trabajadores distintos con marcación frente al personal
     * aplicable ese día (mismas reglas que Control de asistencia). Un rango:
     * jornadas registradas en los días ya transcurridos del período.
     *
     * @return array{modo: string, personal: int, presentes: int, faltas: int, sinMarcar: int, trabajadores: int, dias: int}
     */
    private function asistencia(DashboardPeriodo $periodo): array
    {
        $ultimoDia = $periodo->ultimoDiaTranscurrido();
        $vacio = ['personal' => 0, 'presentes' => 0, 'faltas' => 0, 'sinMarcar' => 0, 'trabajadores' => 0, 'dias' => 0];

        if ($periodo->porHoras()) {
            $fecha = $periodo->desde->toDateString();
            $personal = TrabajadorModel::query()->toBase()
                ->where('estado', 'ACTIVO')
                ->whereDate('fecha_ingreso', '<=', $fecha)
                ->where(fn (Builder $query) => $query->whereNull('fecha_salida')->orWhereDate('fecha_salida', '>=', $fecha));
            $marcas = JornadaPersonalModel::query()->toBase()
                ->whereIn('trabajador_id', (clone $personal)->select('id'))
                ->whereDate('fecha', $fecha)
                ->selectRaw('COUNT(DISTINCT CASE WHEN estado = ? THEN trabajador_id END) as presentes', [EstadoJornada::PRESENTE->value])
                ->selectRaw('COUNT(DISTINCT CASE WHEN estado = ? THEN trabajador_id END) as faltas', [EstadoJornada::FALTA->value])
                ->first();
            $total = $ultimoDia === null ? 0 : $personal->count();
            $presentes = (int) $marcas->presentes;
            $faltas = (int) $marcas->faltas;

            return ['modo' => 'dia'] + [
                'personal' => $total,
                'presentes' => $presentes,
                'faltas' => $faltas,
                'sinMarcar' => max(0, $total - $presentes - $faltas),
                'trabajadores' => $presentes,
                'dias' => $ultimoDia === null ? 0 : 1,
            ];
        }

        if ($ultimoDia === null) {
            return ['modo' => 'rango'] + $vacio;
        }

        $fila = JornadaPersonalModel::query()->toBase()
            ->whereDate('fecha', '>=', $periodo->desde->toDateString())
            ->whereDate('fecha', '<=', $ultimoDia->toDateString())
            ->selectRaw('COALESCE(SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END), 0) as presentes', [EstadoJornada::PRESENTE->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END), 0) as faltas', [EstadoJornada::FALTA->value])
            ->selectRaw('COUNT(DISTINCT trabajador_id) as trabajadores')
            ->first();

        return array_merge(['modo' => 'rango'], $vacio, [
            'presentes' => (int) $fila->presentes,
            'faltas' => (int) $fila->faltas,
            'trabajadores' => (int) $fila->trabajadores,
            'dias' => (int) $periodo->desde->startOfDay()->diffInDays($ultimoDia) + 1,
        ]);
    }

    /**
     * Publicados dentro del período y ya visibles (los programados a futuro
     * y los borradores no cuentan).
     *
     * @return array{publicados: int, recientes: list<array{id: int, titulo: string, fecha: string, alcance: string}>}
     */
    private function comunicados(DashboardPeriodo $periodo): array
    {
        $publicados = ComunicadoModel::query()->from('comunicados as c')->toBase()
            ->where('c.estado', 'PUBLICADO')
            ->where('c.fecha_publicacion', '<=', $periodo->ahora()->setTimezone(config('app.timezone'))->toDateTimeString())
            ->where('c.fecha_publicacion', '>=', $periodo->limitesSql()[0])->where('c.fecha_publicacion', '<', $periodo->limitesSql()[1]);

        return [
            'publicados' => (clone $publicados)->count(),
            'recientes' => $publicados->leftJoin('zonas as z', 'z.id', '=', 'c.zona_id')
                ->select('c.id', 'c.titulo', 'c.fecha_publicacion', 'c.alcance', 'z.nombre as zona')
                ->orderByDesc('c.fecha_publicacion')->orderByDesc('c.id')
                ->limit(self::COMUNICADOS_VISIBLES)->get()
                ->map(fn (object $comunicado): array => [
                    'id' => (int) $comunicado->id,
                    'titulo' => $comunicado->titulo,
                    'fecha' => $this->fecha($comunicado->fecha_publicacion),
                    'alcance' => $comunicado->zona ?? ucfirst(strtolower($comunicado->alcance)),
                ])->all(),
        ];
    }

    private function fecha(string $fecha): string
    {
        return DashboardPeriodo::momentoLocal($fecha)->format('d/m/Y H:i');
    }
}
