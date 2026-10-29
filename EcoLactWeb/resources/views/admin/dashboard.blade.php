@extends('layouts.admin')

@section('title', 'Resumen general')
@section('body-class', 'admin-dashboard-page')
@section('contenedor', 'max-w-[1600px]')

@php
    $periodo = $dashboard->periodo;
    [$desde, $hasta] = $periodo->limitesFecha();
    $rango = ['desde' => $desde, 'hasta' => $hasta];
    $titulos = match ($periodo->nombre) {
        'hoy' => ['acopio' => 'Leche acopiada hoy', 'produccion' => 'Producción de hoy', 'ventas' => 'Ventas del día', 'vence' => 'vencen hoy'],
        'semana' => ['acopio' => 'Leche acopiada esta semana', 'produccion' => 'Producción de la semana', 'ventas' => 'Ventas de la semana', 'vence' => 'vencen esta semana'],
        'mes' => ['acopio' => 'Leche acopiada este mes', 'produccion' => 'Producción del mes', 'ventas' => 'Ventas del mes', 'vence' => 'vencen este mes'],
        default => ['acopio' => 'Leche acopiada', 'produccion' => 'Producción', 'ventas' => 'Ventas del período', 'vence' => 'vencen en el período'],
    };
    $cantidad = fn (float $valor): string => rtrim(rtrim(number_format($valor, 2), '0'), '.');
    $litros = fn (float $valor): string => $cantidad($valor).' L';
    $soles = fn (float $valor): string => 'S/ '.number_format($valor, 2);
    $plural = fn (int $n, string $uno, string $varios): string => $n.' '.($n === 1 ? $uno : $varios);
    $estadoLote = fn (string $estado): string => match ($estado) { 'EN_PROCESO' => 'En proceso', 'FINALIZADO' => 'Finalizado', default => ucfirst(strtolower(str_replace('_', ' ', $estado))) };

    $ventas = $dashboard->ventas;
    $finanzas = $dashboard->finanzas;
    $proveedores = $finanzas->proveedores;
    $trabajadores = $finanzas->trabajadores;
    $corte = \Carbon\CarbonImmutable::parse($finanzas->corte)->format('d/m/Y');
    $enlaceProveedores = route('admin.pagos.proveedores.index', ['semana' => $proveedores['semana'] ?? 'todos', 'estado' => 'pendientes']);
    $enlaceTrabajadores = $trabajadores['mes'] !== null
        ? route('admin.pagos.trabajadores.index', ['periodo' => $trabajadores['mes'], 'estado' => 'PENDIENTE'])
        : route('admin.pagos.trabajadores.index');
    $pendienteTrabajadores = route('admin.pagos.trabajadores.index', ['estado' => 'PENDIENTE']);
    $pendienteProveedores = route('admin.pagos.proveedores.index', ['semana' => 'todos', 'estado' => 'pendientes']);
    $incidencias = $trabajadores['incidencias'];
    $vencidas = $proveedores['vencidas'] + $trabajadores['vencidas'];
    $nomina = array_filter([
        ...array_map(fn (string $mes): string => 'nómina de '.\Carbon\CarbonImmutable::createFromFormat('!Y-m', $mes)->locale('es')->translatedFormat('F Y').' sin cerrar', $incidencias['mesesSinCerrar']),
        $incidencias['sinObligacion'] > 0 ? $plural($incidencias['sinObligacion'], 'trabajador sin obligación de sueldo', 'trabajadores sin obligación de sueldo') : null,
        $incidencias['sueldoDistinto'] > 0 ? $plural($incidencias['sueldoDistinto'], 'sueldo distinto al vigente', 'sueldos distintos al vigente') : null,
    ]);
    $porAtender = array_values(array_filter([
        ['cantidad' => $dashboard->porAtender['analisisPorConfirmar'], 'texto' => $plural($dashboard->porAtender['analisisPorConfirmar'], 'análisis por confirmar', 'análisis por confirmar'), 'accion' => 'Revisar calidad', 'href' => route('admin.analisis-calidad.index', ['estado' => 'BORRADOR', 'fecha' => 'personalizado'] + $rango), 'tono' => 'info', 'icono' => 'beaker'],
        ['cantidad' => $dashboard->porAtender['insumosSinStock'], 'texto' => $plural($dashboard->porAtender['insumosSinStock'], 'insumo sin stock', 'insumos sin stock'), 'accion' => 'Ver insumos', 'href' => route('admin.insumos.index', ['nivel' => 'sin_stock']), 'tono' => 'danger', 'icono' => 'alert-circle'],
        ['cantidad' => $dashboard->porAtender['insumosBajoMinimo'], 'texto' => $plural($dashboard->porAtender['insumosBajoMinimo'], 'insumo bajo su mínimo', 'insumos bajo su mínimo'), 'accion' => 'Ver insumos', 'href' => route('admin.insumos.index', ['nivel' => 'bajo']), 'tono' => 'warning', 'icono' => 'triangle-alert'],
        ['cantidad' => $dashboard->porAtender['lotesEnProceso'], 'texto' => $plural($dashboard->porAtender['lotesEnProceso'], 'lote en proceso', 'lotes en proceso'), 'accion' => 'Ver producción', 'href' => route('admin.produccion.index', ['estado' => 'EN_PROCESO', 'periodo' => 'personalizado'] + $rango), 'tono' => 'primary', 'icono' => 'clock'],
        ['cantidad' => $vencidas, 'texto' => $plural($vencidas, 'pago vencido', 'pagos vencidos').' · '.$soles($proveedores['vencido'] + $trabajadores['vencido']), 'accion' => 'Ver pagos', 'href' => $trabajadores['vencidas'] === 0 ? $pendienteProveedores : ($proveedores['vencidas'] === 0 ? $pendienteTrabajadores : route('admin.pagos.index')), 'tono' => 'danger', 'icono' => 'alert-circle'],
        ['cantidad' => $proveedores['litros']['enAnalisis'], 'texto' => $litros($proveedores['litros']['enAnalisis']).' con muestra de calidad sin resultado', 'accion' => 'Revisar calidad', 'href' => route('admin.analisis-calidad.index'), 'tono' => 'info', 'icono' => 'beaker'],
        ['cantidad' => $proveedores['litros']['sinValorizar'], 'texto' => $litros($proveedores['litros']['sinValorizar']).' de leche aún sin valorizar', 'accion' => 'Ver pagos', 'href' => $pendienteProveedores, 'tono' => 'warning', 'icono' => 'triangle-alert'],
        ['cantidad' => count($incidencias['rolesSinSueldo']), 'texto' => 'Sin sueldo vigente: '.implode(', ', array_map(fn (string $rol): string => ucfirst(strtolower($rol)), $incidencias['rolesSinSueldo'])), 'accion' => 'Configurar sueldos', 'href' => route('admin.configuracion-pagos.index'), 'tono' => 'warning', 'icono' => 'triangle-alert'],
        ['cantidad' => count($nomina), 'texto' => ucfirst(implode(' · ', $nomina)), 'accion' => 'Ver nómina', 'href' => $pendienteTrabajadores, 'tono' => 'warning', 'icono' => 'clock'],
    ], fn (array $aviso): bool => $aviso['cantidad'] > 0));
    $maxZona = max([0.0, ...array_column($dashboard->acopioPorZona, 'litros')]);
    $existencias = $dashboard->existencias;
    $asistencia = $dashboard->asistenciaHoy;
    $comunicado = $dashboard->comunicados['recientes'][0] ?? null;
    $hayAcopio = $dashboard->acopio['entregas'] > 0;
    $hayFuturo = in_array(true, array_column($dashboard->acopioPorIntervalo, 'futuro'), true);
    $unidadIntervalo = $periodo->porHoras() ? 'horas' : 'días';
@endphp

@section('content')
<div class="admin-dashboard" data-admin-dashboard
     data-acopio="{{ \Illuminate\Support\Js::encode($dashboard->acopioPorIntervalo) }}"
     data-por-horas="{{ $periodo->porHoras() ? 'true' : 'false' }}">

    <header class="dash-header">
        <div class="flex min-w-0 items-start gap-2">
            <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="-ml-2 rounded-lg p-2 text-text transition hover:bg-text-muted/10 lg:hidden">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
            </button>
            <div class="min-w-0">
                <x-layout.breadcrumbs :items="[['Administración', null], ['Resumen', null]]" />
                <h1 class="dash-title">Analitics Dashboard</h1>
                <p class="dash-subtitle">Lo esencial de tu operación, en un solo lugar</p>
            </div>
        </div>
        <div class="dash-toolbar">
            <p class="dash-range"><x-ui.icon name="calendar" class="h-4 w-4" /><span class="sr-only">{{ $periodo->nombreVisible() }}:</span> {{ $periodo->rangoVisible() }}</p>
            <nav class="dash-segments" aria-label="Período del dashboard">
                @foreach (['hoy' => 'Hoy', 'semana' => 'Semana', 'mes' => 'Mes'] as $valor => $etiqueta)
                    <a href="{{ route('admin.dashboard', ['periodo' => $valor]) }}"
                       @if ($periodo->nombre === $valor) aria-current="page" @endif
                       @if ($valor === 'semana') title="Semana actual, de lunes a domingo" @endif>{{ $etiqueta }}</a>
                @endforeach
            </nav>
            <a href="{{ route('notificaciones.index') }}" aria-label="Notificaciones" class="dash-icon-button">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
            </a>
        </div>
    </header>

    {{-- Indicadores del período --}}
    <section class="dash-kpis" aria-label="Indicadores del período">
        <article class="dash-card dash-kpi">
            <div class="dash-kpi-head"><h2>{{ $titulos['acopio'] }}</h2><span class="dash-icon" data-tone="primary"><x-ui.icon name="milk-jar" class="h-5 w-5" /></span></div>
            <p class="dash-kpi-value">{{ $cantidad($dashboard->acopio['litros']) }} <small>L</small></p>
            <p class="dash-kpi-note">{{ $plural($dashboard->acopio['proveedores'], 'proveedor con entregas', 'proveedores con entregas') }}</p>
        </article>
        <article class="dash-card dash-kpi">
            <div class="dash-kpi-head"><h2>{{ $titulos['produccion'] }}</h2><span class="dash-icon" data-tone="violet"><x-ui.icon name="beaker" class="h-5 w-5" /></span></div>
            <p class="dash-kpi-value">{{ $dashboard->produccion['total'] }} <small>{{ $dashboard->produccion['total'] === 1 ? 'lote' : 'lotes' }}</small></p>
            <p class="dash-kpi-note">
                @forelse ($dashboard->produccion['porEstado'] as $estado => $total)
                    {{ $total }} {{ match ($estado) { 'EN_PROCESO' => 'en proceso', 'FINALIZADO' => $total === 1 ? 'finalizado' : 'finalizados', default => strtolower(str_replace('_', ' ', $estado)) } }}@if (! $loop->last) · @endif
                @empty
                    Sin lotes iniciados
                @endforelse
            </p>
        </article>
        <article class="dash-card dash-kpi">
            <div class="dash-kpi-head"><h2>{{ $titulos['ventas'] }}</h2><span class="dash-icon" data-tone="info"><x-ui.icon name="shopping-cart" class="h-5 w-5" /></span></div>
            <p class="dash-kpi-value">{{ $soles($ventas['importe']) }}</p>
            <p class="dash-kpi-note">
                @if ($ventas['ventas'] === 0)
                    Sin ventas registradas
                @else
                    {{ $plural($ventas['ventas'], 'venta', 'ventas') }} · {{ $soles($ventas['contado']) }} contado · {{ $soles($ventas['descuentoPlanilla']) }} en planilla
                @endif
            </p>
        </article>
        <article class="dash-card dash-kpi is-highlight">
            <div class="dash-kpi-head"><h2>Pagos pendientes</h2><span class="dash-icon" data-tone="primary-solid"><x-ui.icon name="calendar-coin" class="h-5 w-5" /></span></div>
            <p class="dash-kpi-value">{{ $soles($finanzas->totales['pendienteReconocido']) }}</p>
            <p class="dash-kpi-note">Saldo pendiente acumulado al {{ $corte }}</p>
            <p class="dash-kpi-note">{{ $soles($finanzas->totales['venceEnPeriodo']) }} {{ $titulos['vence'] }}</p>
        </article>
    </section>

    <div class="dash-grid">
        {{-- Pieza principal: acopio en el tiempo --}}
        <section class="dash-card dash-chart-card" aria-labelledby="dash-acopio-titulo">
            <div class="dash-card-head">
                <div>
                    <h2 id="dash-acopio-titulo">{{ $periodo->porHoras() ? 'Acopio por hora' : 'Acopio diario' }}</h2>
                    <p>Litros recibidos · {{ $periodo->rangoVisible() }}</p>
                </div>
                <div class="dash-chart-total">
                    <span>Total del período</span>
                    <strong>{{ $litros($dashboard->acopio['litros']) }}</strong>
                    <span>{{ $plural($dashboard->acopio['entregas'], 'entrega válida', 'entregas válidas') }}</span>
                </div>
            </div>

            @if (! $hayAcopio)
                <p class="dash-empty dash-chart-empty"><x-ui.icon name="info" class="h-4 w-4 shrink-0" /><span><strong>Sin entregas en este período.</strong> Cada {{ $periodo->porHoras() ? 'hora transcurrida' : 'día transcurrido' }} figura con 0 L.</span></p>
            @endif
            <div class="dash-chart" data-chart-container hidden>
                <canvas data-acopio-chart role="img" aria-label="Litros acopiados por {{ $periodo->porHoras() ? 'hora' : 'día' }}; los valores están en la tabla de datos"></canvas>
            </div>
            <p class="dash-chart-error" data-chart-error hidden>No se pudo dibujar el gráfico. Los datos siguen disponibles en la tabla.</p>
            <div class="dash-card-foot">
                @if ($hayAcopio)
                    <details class="dash-chart-data" data-chart-table open>
                        <summary>Consultar datos del gráfico</summary>
                        <div class="overflow-x-auto">
                            <table class="dash-table">
                                <caption class="sr-only">Litros acopiados por {{ $periodo->porHoras() ? 'hora' : 'día' }}</caption>
                                <thead><tr><th scope="col">{{ $periodo->porHoras() ? 'Hora' : 'Fecha' }}</th><th scope="col">Litros</th><th scope="col">Entregas</th></tr></thead>
                                <tbody>
                                    @foreach ($dashboard->acopioPorIntervalo as $intervalo)
                                        @continue($intervalo['futuro'] || ! $intervalo['entregas'])
                                        <tr><td>{{ $intervalo['titulo'] }}</td><td>{{ number_format($intervalo['litros'], 2) }}</td><td>{{ $intervalo['entregas'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
                @if ($hayFuturo)
                    <p class="dash-legend"><span aria-hidden="true"></span>{{ ucfirst($unidadIntervalo) }} que aún no transcurren</p>
                @endif
                <a href="{{ route('admin.entregas.index') }}" class="dash-link">Ver acopio</a>
            </div>
        </section>

        <div class="dash-stack">
            <section class="dash-card" aria-labelledby="dash-atender-titulo">
                <div class="dash-card-head"><div><h2 id="dash-atender-titulo">Por atender</h2></div></div>
                @if ($porAtender === [])
                    <p class="dash-empty"><x-ui.icon name="check-circle" class="h-5 w-5 text-success" /> Nada pendiente de atender.</p>
                @else
                    <ul class="dash-rows">
                        @foreach ($porAtender as $aviso)
                            <li>
                                <span class="dash-icon is-small" data-tone="{{ $aviso['tono'] }}"><x-ui.icon :name="$aviso['icono']" class="h-4 w-4" /></span>
                                <span class="dash-row-text">{{ $aviso['texto'] }}</span>
                                <a href="{{ $aviso['href'] }}" class="dash-link">{{ $aviso['accion'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="dash-card" aria-labelledby="dash-pagos-titulo">
                <div class="dash-card-head"><div><h2 id="dash-pagos-titulo">Pagos pendientes</h2><p>Obligaciones y remuneraciones por pagar al {{ $corte }}</p></div></div>
                <ul class="dash-rows">
                    <li>
                        <div class="dash-row-text"><p>Proveedores · {{ strtolower(\App\Domain\Shared\Enums\PeriodicidadPago::SEMANAL->etiqueta()) }}</p><p class="dash-muted">{{ $plural($proveedores['pendienteObligaciones'], 'obligación por pagar', 'obligaciones por pagar') }}</p></div>
                        <strong class="dash-amount">{{ $soles($proveedores['pendiente']) }}</strong>
                    </li>
                    <li>
                        <div class="dash-row-text">
                            <p>Trabajadores · {{ strtolower(\App\Domain\Shared\Enums\PeriodicidadPago::MENSUAL->etiqueta()) }}</p>
                            <p class="dash-muted">{{ $plural($trabajadores['pendienteObligaciones'], 'obligación por pagar', 'obligaciones por pagar') }}</p>
                            @if ($trabajadores['pendienteEnCurso'] > 0)
                                <p class="dash-muted" title="Remuneración del mes en curso; se reconoce al cierre mensual">{{ $soles($trabajadores['pendienteEnCurso']) }} acumulados pendientes de liquidar</p>
                            @endif
                        </div>
                        <strong class="dash-amount">{{ $soles($trabajadores['pendienteCerrado']) }}</strong>
                    </li>
                </ul>
                <div class="dash-card-foot">
                    <a href="{{ $enlaceProveedores }}" class="dash-link">Ver pagos a proveedores</a>
                    <a href="{{ $enlaceTrabajadores }}" class="dash-link">Ver pagos a trabajadores</a>
                </div>
            </section>
        </div>

        <section class="dash-card" aria-labelledby="dash-zonas-titulo">
            <div class="dash-card-head"><div><h2 id="dash-zonas-titulo">Acopio por zona</h2><p>Litros del período</p></div></div>
            @if ($dashboard->acopioPorZona === [])
                <p class="dash-empty">Sin acopio en este período.</p>
            @else
                <ul class="dash-bars">
                    @foreach ($dashboard->acopioPorZona as $zona)
                        <li>
                            <span @class(['dash-bar-name', 'dash-muted' => $zona['otras']]) title="{{ $zona['nombre'] }}">{{ $zona['nombre'] }}</span>
                            <progress @class(['dash-bar', 'is-other' => $zona['otras']]) max="{{ $maxZona }}" value="{{ $zona['litros'] }}" aria-hidden="true"></progress>
                            <strong>{{ $litros($zona['litros']) }}</strong>
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="dash-card-foot">
                <a href="{{ route('admin.zonas.index') }}" class="dash-link">Ver zonas</a>
            </div>
        </section>

        <section class="dash-card" aria-labelledby="dash-produccion-titulo">
            <div class="dash-card-head"><div><h2 id="dash-produccion-titulo">Producción reciente</h2><p>Lotes iniciados en el período</p></div></div>
            @if ($dashboard->lotesRecientes === [])
                <p class="dash-empty">No se iniciaron lotes en este período.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="dash-table is-compact">
                        <thead><tr><th scope="col">Lote</th><th scope="col">Producto</th><th scope="col" class="text-right">Estado</th></tr></thead>
                        <tbody>
                            @foreach ($dashboard->lotesRecientes as $lote)
                                <tr>
                                    <td><a href="{{ route('admin.produccion.show', $lote['id']) }}" class="dash-link is-plain">{{ $lote['codigo'] }}</a></td>
                                    <td class="dash-cell-truncate" title="{{ implode(', ', $lote['productos']) }}">{{ $lote['productos'] !== [] ? implode(', ', $lote['productos']) : 'Sin productos' }}</td>
                                    <td class="text-right"><x-ui.badge compact :status="$lote['estado']">{{ $estadoLote($lote['estado']) }}</x-ui.badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="dash-card-foot">
                <a href="{{ route('admin.produccion.index', ['periodo' => 'personalizado'] + $rango) }}" class="dash-link">Ver producción</a>
            </div>
        </section>

        <section class="dash-card" aria-labelledby="dash-inventario-titulo">
            <div class="dash-card-head"><div><h2 id="dash-inventario-titulo">Inventario actual</h2><p>Saldo a este momento, no depende del período</p></div></div>
            <div class="dash-stock-hero">
                <span class="dash-icon is-small" data-tone="primary"><x-ui.icon name="droplet" class="h-4 w-4" /></span>
                <span>Leche disponible</span>
                <strong>{{ $existencias['leche'] !== null ? $cantidad($existencias['leche']['cantidad']).' '.$existencias['leche']['unidad'] : 'Sin registro' }}</strong>
            </div>
            @if ($existencias['productos'] === [])
                <p class="dash-muted mt-3">No hay productos terminados con stock.</p>
            @else
                <ul class="dash-stock-list">
                    @foreach ($existencias['productos'] as $producto)
                        <li><span>{{ $producto['nombre'] }}</span><strong>{{ $cantidad($producto['cantidad']) }} {{ $producto['unidad'] }}</strong></li>
                    @endforeach
                </ul>
                @if ($existencias['productosConStock'] > count($existencias['productos']))
                    <p class="dash-muted mt-1">y {{ $plural($existencias['productosConStock'] - count($existencias['productos']), 'producto más', 'productos más') }} con stock</p>
                @endif
            @endif
            @if ($existencias['insumosBajoMinimo'] + $existencias['insumosSinStock'] > 0)
                <p class="dash-note" data-tone="warning">
                    <x-ui.icon name="triangle-alert" class="h-4 w-4 shrink-0" />
                    <span>{{ collect([$existencias['insumosBajoMinimo'] > 0 ? $plural($existencias['insumosBajoMinimo'], 'insumo bajo su mínimo', 'insumos bajo su mínimo') : null, $existencias['insumosSinStock'] > 0 ? $plural($existencias['insumosSinStock'], 'sin stock', 'sin stock') : null])->filter()->implode(' · ') }}</span>
                </p>
            @endif
            <div class="dash-card-foot">
                <a href="{{ route('admin.insumos.index') }}" class="dash-link">Ver insumos</a>
                <a href="{{ route('admin.productos.index', ['estado' => 'DISPONIBLE']) }}" class="dash-link">Ver productos</a>
            </div>
        </section>

        {{-- Franja inferior: estado de hoy y comunicación --}}
        <div class="dash-card dash-strip">
            <section class="dash-strip-item" aria-labelledby="dash-asistencia-titulo">
                <span class="dash-icon" data-tone="primary"><x-ui.icon name="user-check" class="h-5 w-5" /></span>
                <div class="min-w-0 flex-1">
                    <h2 id="dash-asistencia-titulo">Asistencia de hoy</h2>
                    @if ($asistencia['personal'] === 0)
                        <p class="dash-muted">No hay personal registrado para este día.</p>
                    @else
                        <p class="dash-strip-value">{{ $asistencia['presentes'] }} <small>de {{ $asistencia['personal'] }} presentes</small></p>
                        <p class="dash-muted">{{ $plural($asistencia['faltas'], 'falta', 'faltas') }} · {{ $asistencia['sinMarcar'] }} sin marcar</p>
                    @endif
                </div>
                <a href="{{ route('admin.asistencia.index', ['fecha' => $periodo->hoy()->desde->toDateString()]) }}" class="dash-link">Ver asistencia</a>
            </section>
            <section class="dash-strip-item" aria-labelledby="dash-comunicados-titulo">
                <span class="dash-icon" data-tone="primary"><x-ui.icon name="file-text" class="h-5 w-5" /></span>
                <div class="min-w-0 flex-1">
                    <h2 id="dash-comunicados-titulo">Comunicados</h2>
                    @if ($comunicado === null)
                        <p class="dash-muted">No se publicaron comunicados en este período.</p>
                    @else
                        <a href="{{ route('admin.comunicados.show', $comunicado['id']) }}" class="dash-strip-title">{{ $comunicado['titulo'] }}</a>
                        <p class="dash-muted">{{ $comunicado['alcance'] }} · {{ $comunicado['fecha'] }} · {{ $plural($dashboard->comunicados['publicados'], 'publicado', 'publicados') }} en el período</p>
                    @endif
                </div>
                <a href="{{ route('admin.comunicados.index', ['estado' => 'PUBLICADO', 'periodo' => 'personalizado'] + $rango) }}" class="dash-link">Ver comunicados</a>
            </section>
        </div>
    </div>
</div>
@endsection
