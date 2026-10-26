<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Pago\ComprobantePagoProveedor;
use App\Application\Pago\Contracts\PagoSemanalProveedoresQuery;
use App\Application\Pago\PagoSemanalFiltro;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class EloquentPagoSemanalProveedoresQuery implements PagoSemanalProveedoresQuery
{
    private const ETIQUETAS_RESULTADO = ['ACEPTADA' => 'Aceptada', 'OBSERVADA' => 'Observada', 'RECHAZADA' => 'Rechazada'];

    /** Estado compacto de la tabla (la etiqueta larga del enum sigue en otras pantallas). */
    private const ETIQUETAS_ESTADO = ['PENDIENTE' => 'Pendiente', 'PAGADO' => 'Pagado', 'NO_PAGABLE' => 'No pagable'];

    public function listado(PagoSemanalFiltro $filtro, DateTimeImmutable $hoy): array
    {
        $semanas = $this->semanasDisponibles($hoy);
        $semana = $this->resolverSemana($filtro, $semanas);
        $semanasPorInicio = collect($semanas)->keyBy('inicio');

        // El universo son TODAS las obligaciones de proveedores (pendientes y pagadas):
        // la semana y el estado solo restringen cuando el filtro lo pide.
        $obligaciones = $this->proveedores()
            ->with(['proveedor.zona', 'detalles', 'pago'])
            ->when(! $filtro->todasLasSemanas, fn (Builder $query) => $this->enSemana($query, $semana['inicio']))
            ->when($filtro->zonaId !== null, fn (Builder $query) => $query->whereHas('proveedor', fn (Builder $proveedor) => $proveedor->where('zona_id', $filtro->zonaId)))
            ->when($filtro->estado === PagoSemanalFiltro::PENDIENTES, fn (Builder $query) => $query->where('estado', EstadoObligacionPago::PENDIENTE->value))
            ->when($filtro->estado === PagoSemanalFiltro::PAGADOS, fn (Builder $query) => $query->where('estado', EstadoObligacionPago::PAGADO->value))
            ->orderByDesc('periodo_inicio')
            ->orderBy('beneficiario_nombre')
            ->get();

        $filas = $obligaciones->map(fn (ObligacionPagoModel $obligacion): array => $this->fila($obligacion, $semanasPorInicio));
        $pendientes = $filas->where('pendiente', true);

        $pendientesOtrasSemanas = $filtro->todasLasSemanas
            ? 0
            : (int) collect($semanas)->where('inicio', '!=', $semana['inicio'])->sum('pendientes');

        return [
            'semanas' => $semanas,
            'semana' => $semana,
            'todas' => $filtro->todasLasSemanas,
            'pendientesOtrasSemanas' => $pendientesOtrasSemanas,
            'zonas' => ZonaModel::query()->orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn (ZonaModel $zona): array => ['id' => (int) $zona->id, 'nombre' => $zona->nombre])->all(),
            // Los KPIs hablan de lo que aún se debe: los pagados visibles no los inflan.
            'kpis' => [
                'pendientes' => $pendientes->count(),
                'litros' => round((float) $pendientes->sum('litros'), 2),
                'totalPendiente' => round((float) $pendientes->sum('total'), 2),
            ],
            'filas' => $filas
                ->sortBy([['semanaInicio', 'desc'], ['pendiente', 'desc'], ['proveedor', 'asc']])
                ->values()
                ->all(),
        ];
    }

    public function comprobantes(array $obligacionIds): array
    {
        if ($obligacionIds === []) {
            return [];
        }

        $obligaciones = $this->proveedores()
            ->with(['proveedor.zona', 'detalles', 'pago.registradoPor'])
            ->whereIn('id', $obligacionIds)
            ->get()
            ->keyBy('id');

        $comprobantes = [];

        foreach ($obligacionIds as $id) {
            $obligacion = $obligaciones->get($id);

            if ($obligacion !== null) {
                $comprobantes[] = $this->comprobante($obligacion);
            }
        }

        return $comprobantes;
    }

    public function historialProveedor(int $proveedorId, PagoSemanalFiltro $filtro, int $pagina, int $porPagina): array
    {
        $semanas = $this->semanasConPagos($proveedorId);
        $semanasPorInicio = collect($semanas)->keyBy('inicio');

        // Siempre parte del proveedor indicado y de TODOS sus pagos (pendientes y pagados);
        // semana y estado solo restringen cuando el filtro lo pide.
        $consulta = $this->proveedores()
            ->where('proveedor_id', $proveedorId)
            ->when($filtro->semana !== null, fn (Builder $query) => $this->enSemana($query, $this->semana($filtro->semana)['inicio']))
            ->when($filtro->estado === PagoSemanalFiltro::PENDIENTES, fn (Builder $query) => $query->where('estado', EstadoObligacionPago::PENDIENTE->value))
            ->when($filtro->estado === PagoSemanalFiltro::PAGADOS, fn (Builder $query) => $query->where('estado', EstadoObligacionPago::PAGADO->value));

        $total = (clone $consulta)->count();
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $paginas);

        $filas = $consulta
            ->with(['detalles', 'pago'])
            ->orderByDesc('periodo_inicio')
            ->orderByDesc('id')
            ->forPage($pagina, $porPagina)
            ->get()
            ->map(fn (ObligacionPagoModel $obligacion): array => $this->filaHistorial($obligacion, $semanasPorInicio))
            ->all();

        return [
            'semanas' => $semanas,
            'filas' => $filas,
            'total' => $total,
            'totalProveedor' => $this->proveedores()->where('proveedor_id', $proveedorId)->count(),
            'pagina' => $pagina,
            'paginas' => $paginas,
            'desde' => $total === 0 ? 0 : ($pagina - 1) * $porPagina + 1,
            'hasta' => $total === 0 ? 0 : ($pagina - 1) * $porPagina + count($filas),
        ];
    }

    public function resumenProveedor(int $proveedorId): array
    {
        $fila = $this->proveedores()
            ->where('proveedor_id', $proveedorId)
            ->selectRaw("COUNT(*) as total, COALESCE(SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END), 0) as pendientes")
            ->first();

        return ['total' => (int) ($fila?->total ?? 0), 'pendientes' => (int) ($fila?->pendientes ?? 0)];
    }

    /**
     * @param  Collection<string, array{inicio: string, etiqueta: string, rango: string, pendientes: int}>  $semanasPorInicio
     * @return array<string, mixed>
     */
    private function filaHistorial(ObligacionPagoModel $obligacion, Collection $semanasPorInicio): array
    {
        $calculo = $this->calculo($obligacion);
        $semana = $this->semana($obligacion->periodo_inicio->format('Y-m-d'));
        $semana = $semanasPorInicio->get($semana['inicio'], $semana);

        return [
            'id' => $obligacion->id,
            'tipo' => 'Proveedor',
            'litros' => $calculo['litros'],
            'precioLitro' => $calculo['precioLitro'],
            'semanaEtiqueta' => $semana['etiqueta'],
            'periodoRango' => $obligacion->periodo_inicio->format('d/m/Y').' - '.$obligacion->periodo_fin->format('d/m/Y'),
            'total' => $calculo['total'],
            'estado' => $obligacion->estado,
            'estadoEtiqueta' => EstadoObligacionPago::from($obligacion->estado)->etiqueta(),
            'pendiente' => $obligacion->estado === EstadoObligacionPago::PENDIENTE->value,
        ];
    }

    private function proveedores(): Builder
    {
        return ObligacionPagoModel::query()->where('tipo_beneficiario', TipoBeneficiarioPago::PROVEEDOR->value);
    }

    /**
     * Semanas con pagos de proveedores, la más reciente primero y sin repetir.
     * Un pago puede tener un periodo parcial (p. ej. 14/09-15/09, cuando la
     * configuración cambió a mitad de semana): se agrupa en la semana de EcoLact
     * (jueves a miércoles) que lo contiene, así no aparece una semana duplicada.
     *
     * @return array<int, array{inicio: string, etiqueta: string, rango: string, pendientes: int}>
     */
    private function semanasDisponibles(DateTimeImmutable $hoy): array
    {
        $semanas = $this->semanasConPagos();

        if ($semanas === []) {
            $actual = PeriodoPago::semanaQueContiene($hoy);
            $semanas = [$this->semana($actual->clave()) + ['pendientes' => 0]];
        }

        return $semanas;
    }

    /**
     * Semanas que tienen al menos un pago (de todos los proveedores o de uno solo),
     * únicas y de la más reciente a la más antigua. Vacío si no hay ninguno.
     *
     * @return array<int, array{inicio: string, etiqueta: string, rango: string, pendientes: int}>
     */
    private function semanasConPagos(?int $proveedorId = null): array
    {
        $porInicio = [];

        $filas = $this->proveedores()
            ->when($proveedorId !== null, fn (Builder $query) => $query->where('proveedor_id', $proveedorId))
            ->selectRaw("DATE(periodo_inicio) as inicio, SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes")
            ->groupByRaw('DATE(periodo_inicio)')
            ->get();

        foreach ($filas as $fila) {
            $semana = $this->semana((string) $fila->inicio);
            $porInicio[$semana['inicio']] ??= $semana + ['pendientes' => 0];
            $porInicio[$semana['inicio']]['pendientes'] += (int) $fila->pendientes;
        }

        krsort($porInicio, SORT_STRING);

        return $this->distinguirEtiquetas(array_values($porInicio));
    }

    /**
     * "Semana 38" se repite entre años distintos: solo en ese caso se añade el año.
     *
     * @param  array<int, array{inicio: string, etiqueta: string, rango: string, pendientes: int}>  $semanas
     * @return array<int, array{inicio: string, etiqueta: string, rango: string, pendientes: int}>
     */
    private function distinguirEtiquetas(array $semanas): array
    {
        $repeticiones = array_count_values(array_column($semanas, 'etiqueta'));

        return array_map(function (array $semana) use ($repeticiones): array {
            if ($repeticiones[$semana['etiqueta']] > 1) {
                $semana['etiqueta'] .= ' · '.substr($semana['inicio'], 0, 4);
            }

            return $semana;
        }, $semanas);
    }

    /** Pagos cuyo periodo comienza dentro de la semana (jueves a miércoles) indicada. */
    private function enSemana(Builder $query, string $inicio): Builder
    {
        $fin = (new DateTimeImmutable($inicio))->modify('+6 days')->format('Y-m-d');

        return $query->whereDate('periodo_inicio', '>=', $inicio)->whereDate('periodo_inicio', '<=', $fin);
    }

    /**
     * La semana pedida si existe; si no, la más reciente con pendientes y, en su
     * defecto, la más reciente. "Todos" no restringe el periodo.
     *
     * @param  array<int, array{inicio: string, etiqueta: string, rango: string, pendientes: int}>  $semanas
     * @return array{inicio: string, etiqueta: string, rango: string}
     */
    private function resolverSemana(PagoSemanalFiltro $filtro, array $semanas): array
    {
        if ($filtro->todasLasSemanas) {
            return ['inicio' => PagoSemanalFiltro::SEMANA_TODAS, 'etiqueta' => 'Todos', 'rango' => 'Todo el historial'];
        }

        $elegida = collect($semanas)->firstWhere('inicio', $filtro->semana)
            ?? collect($semanas)->first(fn (array $semana): bool => $semana['pendientes'] > 0)
            ?? $semanas[0];

        return ['inicio' => $elegida['inicio'], 'etiqueta' => $elegida['etiqueta'], 'rango' => $elegida['rango']];
    }

    /**
     * @return array{inicio: string, etiqueta: string, rango: string}
     */
    private function semana(string $inicio): array
    {
        $periodo = PeriodoPago::semanaQueContiene(new DateTimeImmutable(substr($inicio, 0, 10)));

        return [
            'inicio' => $periodo->clave(),
            'etiqueta' => $periodo->etiqueta(),
            'rango' => $periodo->inicio->format('d/m/Y').' - '.$periodo->fin->format('d/m/Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(ObligacionPagoModel $obligacion, Collection $semanasPorInicio): array
    {
        $calculo = $this->calculo($obligacion);
        $proveedor = $obligacion->proveedor;
        $zona = $proveedor?->zona;
        $semana = $this->semana($obligacion->periodo_inicio->format('Y-m-d'));
        $semana = $semanasPorInicio->get($semana['inicio'], $semana);

        return [
            'id' => $obligacion->id,
            'proveedor' => $obligacion->beneficiario_nombre,
            'pagable' => $obligacion->estado === EstadoObligacionPago::PENDIENTE->value
                && PeriodoPago::semanaQueContiene(DateTimeImmutable::createFromInterface($obligacion->periodo_inicio))->fechaPagoProgramada()->format('Y-m-d')
                    <= CarbonImmutable::now(config('app.zona_horaria_negocio'))->format('Y-m-d'),
            'fechaPagoProgramada' => PeriodoPago::semanaQueContiene(DateTimeImmutable::createFromInterface($obligacion->periodo_inicio))->fechaPagoProgramada()->format('d/m/Y'),
            'dni' => $obligacion->pago?->comprobante_snapshot['dni'] ?? $proveedor?->dni ?? '',
            'zona' => $obligacion->pago?->comprobante_snapshot['zona'] ?? $zona?->nombre ?? 'Sin zona',
            'zonaDetalle' => $zona?->descripcion !== null ? Str::limit(trim($zona->descripcion), 24) : null,
            'zonaId' => $zona?->id,
            'semanaInicio' => $semana['inicio'],
            'semanaEtiqueta' => $semana['etiqueta'],
            'litros' => $calculo['litros'],
            'litrosSemana' => $calculo['litrosSemana'],
            'precioLitro' => $calculo['precioLitro'],
            'ajusteLitro' => $calculo['ajusteLitro'],
            'precioAplicado' => $calculo['precioAplicado'],
            'resultado' => $calculo['resultado'],
            'resultadoEtiqueta' => self::ETIQUETAS_RESULTADO[$calculo['resultado']] ?? 'Sin análisis',
            'penalidad' => $calculo['penalidad'],
            'compras' => $calculo['compras'],
            'subtotal' => $calculo['subtotal'],
            'total' => $calculo['total'],
            'estado' => $obligacion->estado,
            'estadoEtiqueta' => self::ETIQUETAS_ESTADO[$obligacion->estado] ?? EstadoObligacionPago::from($obligacion->estado)->etiqueta(),
            'pendiente' => $obligacion->estado === EstadoObligacionPago::PENDIENTE->value,
        ];
    }

    /**
     * Cifras de la liquidación semanal tal como se calcularon (SincronizarPagosProveedor):
     * litros de la semana y pagables, precio base, resultado de calidad semanal, ajuste
     * por litro, precio aplicado, importe de leche, compras descontadas y total. Un pago
     * PAGADO usa su comprobante guardado: nunca se recalcula con la configuración de hoy.
     *
     * @return array{litros: float, litrosSemana: float, precioLitro: float, ajusteLitro: float, precioAplicado: float, resultado: string|null, importeLeche: float, penalidad: float, subtotal: float, compras: float, total: float}
     */
    private function calculo(ObligacionPagoModel $obligacion): array
    {
        $snapshot = $obligacion->pago?->comprobante_snapshot;

        if (is_array($snapshot)) {
            $guardado = ComprobantePagoProveedor::desdeArray($snapshot, estado: EstadoObligacionPago::PAGADO->value);

            return [
                'litros' => round($guardado->litros, 2),
                'litrosSemana' => round($guardado->litrosDeLaSemana(), 2),
                'precioLitro' => round($guardado->precioLitro, 2),
                'ajusteLitro' => round($guardado->ajustePorLitro(), 2),
                'precioAplicado' => round($guardado->precioAplicadoPorLitro(), 2),
                'resultado' => $guardado->resultadoSemanal(),
                'importeLeche' => round($guardado->importeLeche, 2),
                'penalidad' => round($guardado->penalidad, 2),
                'subtotal' => round($guardado->subtotalLeche, 2),
                'compras' => round($guardado->subtotalDescuentos, 2),
                'total' => round($guardado->totalFinal, 2),
            ];
        }

        $base = $obligacion->base_calculo ?? [];
        $total = round((float) $obligacion->monto_calculado, 2);

        if ($obligacion->detalles->isNotEmpty()) {
            $litros = round((float) $obligacion->detalles->sum('litros_pagables'), 2);
            $litrosSemana = round((float) $obligacion->detalles->sum('litros_entregados'), 2);
            $importeLeche = round((float) $obligacion->detalles->sum(fn ($detalle): float => (float) $detalle->litros_pagables * (float) $detalle->precio_base), 2);
            $subtotal = round((float) $obligacion->detalles->sum('subtotal'), 2);
        } else {
            $litros = round((float) ($base['litros_pagables'] ?? 0), 2);
            $litrosSemana = round((float) ($base['litros_entregados'] ?? $litros), 2);
            $importeLeche = round($litros * (float) ($base['precio_base_litro'] ?? 0), 2);
            $subtotal = round((float) ($base['subtotal'] ?? $total), 2);
        }

        $precioLitro = $litros > 0.0 && $importeLeche > 0.0 ? round($importeLeche / $litros, 2) : round((float) ($base['precio_base_litro'] ?? 0), 2);
        $penalidad = $importeLeche > 0.0 ? round($subtotal - $importeLeche, 2) : 0.0;
        $noPagable = $obligacion->estado === EstadoObligacionPago::NO_PAGABLE->value;

        return [
            'litros' => $litros,
            'litrosSemana' => $litrosSemana,
            'precioLitro' => $precioLitro,
            'ajusteLitro' => round((float) ($base['ajuste_calidad_litro'] ?? ($litros > 0.0 ? $penalidad / $litros : 0.0)), 2),
            'precioAplicado' => $noPagable ? 0.0 : ($litros > 0.0 ? round($subtotal / $litros, 2) : $precioLitro),
            'resultado' => $base['resultado_semanal']
                ?? ResultadoCalidad::peor($obligacion->detalles->map(fn ($detalle): ?ResultadoCalidad => ResultadoCalidad::tryFrom((string) $detalle->resultado_calidad)))?->value,
            'importeLeche' => $importeLeche,
            'penalidad' => $penalidad,
            'subtotal' => $subtotal,
            'compras' => max(0.0, round($subtotal - $total, 2)),
            'total' => $total,
        ];
    }

    private function comprobante(ObligacionPagoModel $obligacion): ComprobantePagoProveedor
    {
        $pago = $obligacion->pago;

        if ($pago?->comprobante_snapshot !== null) {
            return ComprobantePagoProveedor::desdeArray(
                $pago->comprobante_snapshot,
                estado: EstadoObligacionPago::PAGADO->value,
                pagadoEl: ($pago->pagado_at ?? $pago->fecha_pago)->format('d/m/Y H:i'),
                pagadoPor: $pago->registradoPor?->username,
            );
        }

        $calculo = $this->calculo($obligacion);
        $proveedor = $obligacion->proveedor;
        $inicio = CarbonImmutable::instance($obligacion->periodo_inicio);
        $fin = CarbonImmutable::instance($obligacion->periodo_fin);
        $descuentos = $this->descuentos($obligacion, $calculo['compras']);
        $penalidadLitros = $calculo['precioLitro'] > 0.0 ? round($calculo['penalidad'] / $calculo['precioLitro'], 2) : 0.0;

        return new ComprobantePagoProveedor(
            obligacionId: $obligacion->id,
            proveedorId: (int) $obligacion->proveedor_id,
            proveedorNombre: $obligacion->beneficiario_nombre,
            dni: (string) ($proveedor?->dni ?? '—'),
            codigo: 'PRV-'.str_pad((string) $obligacion->proveedor_id, 3, '0', STR_PAD_LEFT),
            zona: $proveedor?->zona?->nombre ?? 'Sin zona',
            semanaEtiqueta: PeriodoPago::semanaQueContiene($inicio->toDateTimeImmutable())->etiqueta(),
            periodoInicio: $inicio->format('Y-m-d'),
            periodoFin: $fin->format('Y-m-d'),
            rango: $inicio->format('d/m/Y').' - '.$fin->format('d/m/Y'),
            litros: $calculo['litros'],
            precioLitro: $calculo['precioLitro'],
            importeLeche: $calculo['importeLeche'],
            penalidad: $calculo['penalidad'],
            penalidadLitros: $penalidadLitros,
            detallePenalidad: $this->detallePenalidad($obligacion, $calculo['penalidad']),
            subtotalLeche: $calculo['subtotal'],
            descuentos: $descuentos,
            subtotalDescuentos: $calculo['compras'],
            totalFinal: $calculo['total'],
            resultadoSemanal: $calculo['resultado'] ?? 'SIN_ANALISIS',
            litrosSemana: $calculo['litrosSemana'],
            ajusteLitro: $calculo['ajusteLitro'],
            precioAplicado: $calculo['precioAplicado'],
            estado: $obligacion->estado,
            pagadoEl: $pago !== null ? ($pago->pagado_at ?? $pago->fecha_pago)->format('d/m/Y H:i') : null,
            anioFirma: (int) now()->format('Y'),
            pagadoPor: $pago?->registradoPor?->username,
        );
    }

    private function detallePenalidad(ObligacionPagoModel $obligacion, float $penalidad): string
    {
        if (abs($penalidad) < 0.005) {
            return 'Sin bonificación ni penalidad';
        }

        $resultados = $obligacion->detalles
            ->pluck('resultado_calidad')
            ->filter(fn (?string $resultado): bool => $resultado !== null && $resultado !== 'ACEPTADA')
            ->unique()
            ->map(fn (string $resultado): string => Str::lower($resultado))
            ->values();

        $causa = $resultados->isNotEmpty() ? ' ('.$resultados->implode(', ').')' : '';

        return ($penalidad < 0 ? 'Penalidad por calidad' : 'Bonificación por calidad').$causa;
    }

    /**
     * Productos comprados por el proveedor que se descuentan de este pago. Si el
     * importe de la lista no cuadra con lo descontado (pagos anteriores al detalle
     * de compras), se añade una fila con la diferencia para que el comprobante sume.
     *
     * @return array<int, array{producto: string, presentacion: string, cantidad: string, precioUnitario: float, importe: float}>
     */
    private function descuentos(ObligacionPagoModel $obligacion, float $totalDescontado): array
    {
        $ventaIds = collect($obligacion->base_calculo['descuentos'] ?? [])->pluck('venta_id')->filter()->all();

        /** @var Collection<int, VentaProductoModel> $ventas */
        $ventas = $ventaIds === []
            ? collect()
            : VentaProductoModel::with('producto')->whereIn('id', $ventaIds)->orderBy('fecha')->orderBy('id')->get();

        $filas = $ventas->map(fn (VentaProductoModel $venta): array => [
            'producto' => $venta->producto?->nombre_base ?? $venta->producto?->nombre ?? 'Producto',
            'presentacion' => $this->presentacion($venta->producto),
            'cantidad' => $this->numeroLimpio((float) $venta->cantidad),
            'precioUnitario' => round((float) $venta->precio_unitario, 2),
            'importe' => round((float) $venta->importe_total, 2),
        ])->values()->all();

        $diferencia = round($totalDescontado - array_sum(array_column($filas, 'importe')), 2);

        if (abs($diferencia) >= 0.005) {
            $filas[] = ['producto' => 'Otros descuentos de planilla', 'presentacion' => '-', 'cantidad' => '-', 'precioUnitario' => 0.0, 'importe' => $diferencia];
        }

        return $filas;
    }

    private function presentacion(?ProductoModel $producto): string
    {
        if ($producto === null) {
            return '-';
        }

        if ($producto->presentacion_cantidad !== null && $producto->presentacion_unidad !== null) {
            return $this->numeroLimpio((float) $producto->presentacion_cantidad).' '.$producto->presentacion_unidad;
        }

        return $producto->unidad_medida ?? '-';
    }

    private function numeroLimpio(float $numero): string
    {
        return rtrim(rtrim(number_format($numero, 3, '.', ''), '0'), '.');
    }
}
