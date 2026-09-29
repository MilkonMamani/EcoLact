<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Trabajador\Contracts\FichasPersonalQuery;
use App\Application\Trabajador\FichaPersonalPanelData;
use App\Application\Trabajador\FichasPersonalFiltro;
use App\Application\Trabajador\TipoFichaPersonal;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Listado y panel lateral de analistas de calidad, encargados de planta y
 * personal de ventas (admin), con el mismo patrón que Acopiadores. La
 * actividad de cada ficha se liga por el usuario que la hizo (ver
 * TipoFichaPersonal); el conteo del listado es una subconsulta correlacionada
 * y cada pestaña del panel sale de una sola consulta: nada de N+1.
 */
final class EloquentFichasPersonalQuery implements FichasPersonalQuery
{
    /** Registros más recientes que muestra la pestaña de actividad. */
    public const LIMITE = 15;

    public function paginar(TipoFichaPersonal $tipo, FichasPersonalFiltro $filtro, int $porPagina, string $nombrePagina = 'pagina'): LengthAwarePaginator
    {
        return $this->base($tipo, $filtro)
            ->select('f.id', 'f.nombres', 'f.apellidos', 'f.dni', 'f.telefono', 'f.estado')
            ->selectSub($this->actividadDe($tipo)->selectRaw('COUNT(*)')->whereColumn('x.'.$this->columnaAutor($tipo), 'f.user_id'), 'actividad_count')
            ->orderBy('f.nombres')
            ->orderBy('f.apellidos')
            ->orderBy('f.id')
            ->paginate($porPagina, pageName: $nombrePagina)
            ->withQueryString()
            ->through(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'nombre' => trim($fila->nombres.' '.$fila->apellidos),
                'dni' => $fila->dni,
                'telefono' => $fila->telefono,
                'actividad' => (int) $fila->actividad_count,
                'estado' => $fila->estado,
            ]);
    }

    public function panel(TipoFichaPersonal $tipo, int $fichaId, ?string $tipoCliente = null): ?FichaPersonalPanelData
    {
        $ficha = DB::table($tipo->tabla())->where('id', $fichaId)->first();

        if ($ficha === null) {
            return null;
        }

        $userId = (int) $ficha->user_id;
        $tipoCliente = $tipo === TipoFichaPersonal::PERSONAL_VENTAS && in_array($tipoCliente, ['PROVEEDOR', 'PUBLICO', 'MAYORISTA'], true) ? $tipoCliente : null;

        [$metricas, $actividad, $total] = match ($tipo) {
            TipoFichaPersonal::ANALISTA_CALIDAD => $this->analisis($userId),
            TipoFichaPersonal::ENCARGADO_PLANTA => $this->produccion($userId),
            TipoFichaPersonal::PERSONAL_VENTAS => $this->ventas($userId, $tipoCliente),
        };

        return new FichaPersonalPanelData(
            tipo: $tipo,
            id: (int) $ficha->id,
            userId: $userId,
            nombres: $ficha->nombres,
            apellidos: $ficha->apellidos,
            dni: $ficha->dni,
            telefono: $ficha->telefono,
            estado: $ficha->estado,
            fechaIngreso: $ficha->fecha_ingreso !== null ? CarbonImmutable::parse($ficha->fecha_ingreso)->format('d/m/Y') : null,
            qrToken: TrabajadorModel::where('user_id', $userId)->value('qr_token'),
            metricas: $metricas,
            actividad: $actividad,
            actividadTotal: $total,
            tipoCliente: $tipoCliente,
        );
    }

    /**
     * Un análisis es del analista que lo confirmó: es el único autor que guarda
     * analisis_calidad (un BORRADOR todavía no tiene responsable).
     *
     * @return array{0: list<array{etiqueta: string, valor: string}>, 1: list<array<string, mixed>>, 2: int}
     */
    private function analisis(int $userId): array
    {
        $resumen = DB::table('analisis_calidad')
            ->where('confirmado_por_user_id', $userId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN resultado = 'ACEPTADA' THEN 1 ELSE 0 END) as aceptados")
            ->selectRaw("SUM(CASE WHEN resultado = 'OBSERVADA' THEN 1 ELSE 0 END) as observados")
            ->selectRaw("SUM(CASE WHEN resultado = 'RECHAZADA' THEN 1 ELSE 0 END) as rechazados")
            ->first();

        $filas = DB::table('analisis_calidad as a')
            ->join('entregas as e', 'e.id', '=', 'a.entrega_id')
            ->join('proveedores as p', 'p.id', '=', 'e.proveedor_id')
            ->where('a.confirmado_por_user_id', $userId)
            ->orderByDesc('a.fecha_analisis')
            ->orderByDesc('a.id')
            ->limit(self::LIMITE)
            ->get(['a.id', 'a.fecha_analisis', 'a.entrega_id', 'a.resultado', 'a.estado', 'p.nombres', 'p.apellidos'])
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'fecha' => CarbonImmutable::parse($fila->fecha_analisis, config('app.timezone'))->format('d/m/Y H:i'),
                'entregaId' => (int) $fila->entrega_id,
                'proveedor' => trim($fila->nombres.' '.$fila->apellidos),
                'resultado' => $fila->resultado,
                'estado' => $fila->estado,
            ])
            ->all();

        return [
            [
                ['etiqueta' => 'Análisis realizados', 'valor' => (string) (int) $resumen->total],
                ['etiqueta' => 'Aceptados', 'valor' => (string) (int) $resumen->aceptados],
                ['etiqueta' => 'Observados', 'valor' => (string) (int) $resumen->observados],
                ['etiqueta' => 'Rechazados', 'valor' => (string) (int) $resumen->rechazados],
            ],
            $filas,
            (int) $resumen->total,
        ];
    }

    /**
     * Lotes que registró el encargado. La leche utilizada es el consumo real del
     * insumo LECHE del lote (igual que el módulo de Producción), nunca un valor escrito a mano.
     *
     * @return array{0: list<array{etiqueta: string, valor: string}>, 1: list<array<string, mixed>>, 2: int}
     */
    private function produccion(int $userId): array
    {
        $resumen = DB::table('lotes_produccion')
            ->where('registrado_por_user_id', $userId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN estado = 'EN_PROCESO' THEN 1 ELSE 0 END) as en_proceso")
            ->selectRaw("SUM(CASE WHEN estado = 'FINALIZADO' THEN 1 ELSE 0 END) as finalizados")
            ->first();

        $litrosTotales = (float) $this->consumoDeLeche()
            ->join('lotes_produccion as l', 'l.id', '=', 'c.lote_produccion_id')
            ->where('l.registrado_por_user_id', $userId)
            ->sum('c.cantidad_usada');

        $lotes = DB::table('lotes_produccion as l')
            ->where('l.registrado_por_user_id', $userId)
            ->select('l.id', 'l.codigo', 'l.fecha_hora_inicio', 'l.estado')
            ->selectSub($this->consumoDeLeche()->selectRaw('COALESCE(SUM(c.cantidad_usada), 0)')->whereColumn('c.lote_produccion_id', 'l.id'), 'litros_leche')
            ->orderByDesc('l.fecha_hora_inicio')
            ->orderByDesc('l.id')
            ->limit(self::LIMITE)
            ->get();

        $productos = DB::table('produccion_detalles as d')
            ->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->whereIn('d.lote_produccion_id', $lotes->pluck('id'))
            ->orderBy('d.id')
            ->get(['d.lote_produccion_id', 'p.nombre'])
            ->groupBy('lote_produccion_id');

        $filas = $lotes->map(function (object $lote) use ($productos): array {
            $nombres = $productos->get($lote->id, collect())->pluck('nombre')->unique()->values();

            return [
                'id' => (int) $lote->id,
                'fecha' => CarbonImmutable::parse($lote->fecha_hora_inicio, config('app.timezone'))->format('d/m/Y'),
                'codigo' => $lote->codigo,
                'productos' => match (true) {
                    $nombres->isEmpty() => '—',
                    $nombres->count() === 1 => $nombres->first(),
                    default => $nombres->first().' +'.($nombres->count() - 1),
                },
                'litros' => round((float) $lote->litros_leche, 2),
                'estado' => $lote->estado,
            ];
        })->all();

        return [
            [
                ['etiqueta' => 'Producciones registradas', 'valor' => (string) (int) $resumen->total],
                ['etiqueta' => 'En proceso', 'valor' => (string) (int) $resumen->en_proceso],
                ['etiqueta' => 'Finalizadas', 'valor' => (string) (int) $resumen->finalizados],
                ['etiqueta' => 'Leche procesada', 'valor' => number_format($litrosTotales, 2).' L'],
            ],
            $filas,
            (int) $resumen->total,
        ];
    }

    /**
     * Ventas que registró el vendedor. Los tipos de cliente son los del dominio
     * (PROVEEDOR, PUBLICO, MAYORISTA); el filtro solo afecta a la lista, no a las métricas.
     *
     * @return array{0: list<array{etiqueta: string, valor: string}>, 1: list<array<string, mixed>>, 2: int}
     */
    private function ventas(int $userId, ?string $tipoCliente): array
    {
        $resumen = DB::table('ventas_producto')
            ->where('registrado_por_user_id', $userId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(importe_total), 0) as importe')
            ->selectRaw("SUM(CASE WHEN tipo_cliente = 'PROVEEDOR' THEN 1 ELSE 0 END) as proveedores")
            ->selectRaw("SUM(CASE WHEN tipo_cliente = 'PUBLICO' THEN 1 ELSE 0 END) as publico")
            ->selectRaw("SUM(CASE WHEN tipo_cliente = 'MAYORISTA' THEN 1 ELSE 0 END) as mayoristas")
            ->first();

        $base = DB::table('ventas_producto as v')
            ->where('v.registrado_por_user_id', $userId)
            ->when($tipoCliente !== null, fn (Builder $query) => $query->where('v.tipo_cliente', $tipoCliente));

        $filas = (clone $base)
            ->leftJoin('proveedores as pr', 'pr.id', '=', 'v.proveedor_id')
            ->leftJoin('compradores as c', 'c.id', '=', 'v.comprador_id')
            ->orderByDesc('v.fecha')
            ->orderByDesc('v.id')
            ->limit(self::LIMITE)
            ->get(['v.id', 'v.fecha', 'v.tipo_cliente', 'v.importe_total', 'v.forma_pago', 'v.estado', 'pr.nombres', 'pr.apellidos', 'c.nombre as comprador'])
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'fecha' => CarbonImmutable::parse($fila->fecha, config('app.timezone'))->format('d/m/Y'),
                'cliente' => $fila->tipo_cliente === 'PROVEEDOR'
                    ? trim($fila->nombres.' '.$fila->apellidos)
                    : ($fila->comprador ?? 'Público general'),
                'tipoCliente' => $fila->tipo_cliente,
                'total' => (float) $fila->importe_total,
                'formaPago' => $fila->forma_pago,
                'estado' => $fila->estado,
            ])
            ->all();

        return [
            [
                ['etiqueta' => 'Ventas registradas', 'valor' => (string) (int) $resumen->total],
                ['etiqueta' => 'Total vendido', 'valor' => 'S/ '.number_format((float) $resumen->importe, 2)],
                ['etiqueta' => 'A proveedores', 'valor' => (string) (int) $resumen->proveedores],
                ['etiqueta' => 'Público general', 'valor' => (string) (int) $resumen->publico],
                ['etiqueta' => 'Mayoristas', 'valor' => (string) (int) $resumen->mayoristas],
            ],
            $filas,
            $tipoCliente === null ? (int) $resumen->total : (clone $base)->count(),
        ];
    }

    /** Tabla de actividad de cada tipo, con alias "x" para correlacionarla con la ficha. */
    private function actividadDe(TipoFichaPersonal $tipo): Builder
    {
        return DB::table(match ($tipo) {
            TipoFichaPersonal::ANALISTA_CALIDAD => 'analisis_calidad as x',
            TipoFichaPersonal::ENCARGADO_PLANTA => 'lotes_produccion as x',
            TipoFichaPersonal::PERSONAL_VENTAS => 'ventas_producto as x',
        });
    }

    private function columnaAutor(TipoFichaPersonal $tipo): string
    {
        return $tipo === TipoFichaPersonal::ANALISTA_CALIDAD ? 'confirmado_por_user_id' : 'registrado_por_user_id';
    }

    private function consumoDeLeche(): Builder
    {
        return DB::table('lote_produccion_insumos as c')
            ->join('insumos as i', 'i.id', '=', 'c.insumo_id')
            ->where('i.codigo', 'LECHE');
    }

    private function base(TipoFichaPersonal $tipo, FichasPersonalFiltro $filtro): Builder
    {
        return DB::table($tipo->tabla().' as f')
            ->when($filtro->busqueda !== null, function (Builder $query) use ($filtro): void {
                $busqueda = '%'.$filtro->busqueda.'%';

                $query->where(function (Builder $query) use ($busqueda): void {
                    $query->where('f.nombres', 'like', $busqueda)
                        ->orWhere('f.apellidos', 'like', $busqueda)
                        ->orWhere('f.dni', 'like', $busqueda)
                        ->orWhere('f.telefono', 'like', $busqueda);
                });
            })
            ->when($filtro->estado !== null, fn (Builder $query) => $query->where('f.estado', $filtro->estado));
    }
}
