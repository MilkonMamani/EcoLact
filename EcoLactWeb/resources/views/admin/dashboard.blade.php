@extends('layouts.admin')

@section('title', 'Dashboard')
@section('body-class', 'admin-dashboard-page')

@section('content')
<div class="admin-dashboard space-y-6" data-admin-dashboard
     data-litros-por-dia="{{ \Illuminate\Support\Js::encode($dashboard->litrosPorDia) }}"
     data-distribucion-calidad="{{ \Illuminate\Support\Js::encode($dashboard->distribucionCalidad) }}">
    <div class="dashboard-heading">
        <div>
            <div class="flex items-center gap-3">
                <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="rounded-lg p-2 text-text transition hover:bg-text-muted/10">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                </button>
                <h1 class="text-2xl font-semibold uppercase tracking-[0.08em]" style="color: #e7a33c;">PANEL ADMINISTRATIVO</h1>
            </div>
            
        </div>
        <div class="dashboard-period">
            <div class="flex items-center justify-end gap-2">
                <a href="{{ route('notificaciones.index') }}" aria-label="Notificaciones" class="rounded-lg p-2 text-text transition hover:bg-text-muted/10">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                </a>
                <nav class="dashboard-segments" aria-label="Período del dashboard">
                @foreach (['hoy' => 'Hoy', 'semana' => 'Semana', 'mes' => 'Mes'] as $valor => $etiqueta)
                    <a href="{{ route('admin.dashboard', ['periodo' => $valor]) }}"
                       @if ($dashboard->periodo->nombre === $valor) aria-current="page" @endif>{{ $etiqueta }}</a>
                @endforeach
                <a href="#dashboard-personalizado" data-custom-period
                   @if ($dashboard->periodo->nombre === 'personalizado') aria-current="page" @endif>Personalizado</a>
                </nav>

        </div>
    </div>

    

    <div class="dashboard-kpis">
        <x-ui.stat-card label="Total acopiado" :value="number_format($dashboard->kpis['totalAcopiado'], 2).' L'" hint="Entregas válidas del período" class="dashboard-kpi" />
        <x-ui.stat-card label="Proveedores atendidos" :value="$dashboard->kpis['proveedoresAtendidos'].' / '.$dashboard->kpis['proveedoresActivos']" :hint="number_format($dashboard->kpis['porcentajeAtendidos'], 1).'% respecto a los activos actuales'" class="dashboard-kpi" />
        <x-ui.stat-card label="Calidad aceptada" :value="number_format($dashboard->kpis['calidadAceptada'], 1).'%'" :hint="$dashboard->kpis['analisisConfirmados'].' análisis confirmados'" class="dashboard-kpi" />
        <x-ui.stat-card label="Entregas observadas" :value="$dashboard->kpis['entregasObservadas']" hint="Análisis confirmados con observación" class="dashboard-kpi dashboard-kpi-warning" />
        <x-ui.stat-card label="Total valorizado" :value="'S/ '.number_format($dashboard->kpis['totalValorizado'], 2)" hint="Suma de valorizaciones individuales" class="dashboard-kpi dashboard-kpi-accent" />
        <x-ui.stat-card label="Producción" :value="$dashboard->kpis['produccion'].($dashboard->kpis['produccion'] === 1 ? ' lote' : ' lotes')" :hint="($dashboard->kpis['produccionPorEstado']['EN_PROCESO'] ?? 0).' en proceso · '.($dashboard->kpis['produccionPorEstado']['FINALIZADO'] ?? 0).' finalizados'" class="dashboard-kpi" />
    </div>

    <div class="dashboard-charts">
        <x-ui.card title="Litros de leche por día" subtitle="Cantidad total de leche acopiada en todas las zonas" class="dashboard-volume min-w-0">
            <x-slot:actions>
                <div class="dashboard-segments" role="group" aria-label="Medida del gráfico">
                    <button type="button" data-chart-metric="litros" aria-pressed="true">Litros</button>
                    <button type="button" data-chart-metric="entregas" aria-pressed="false">N° de entregas</button>
                </div>
            </x-slot:actions>
            @if ($dashboard->litrosPorDia === [])
                <x-ui.empty-state title="No hay entregas en este período" description="El gráfico aparecerá cuando existan entregas válidas." />
            @else
                <div class="dashboard-chart" data-chart-container hidden>
                    <canvas data-volume-chart role="ii" aria-label="Acopio diario del período; los valores también están disponibles en la tabla inferior"></canvas>
                </div>
                <details class="dashboard-chart-data" data-chart-table open>
                    <summary>Consultar datos del gráfico</summary>
                    <div class="overflow-x-auto">
                        <table class="dashboard-table">
                            <caption class="sr-only">Acopio diario, únicamente fechas con entregas válidas</caption>
                            <thead><tr><th scope="col">Fecha</th><th scope="col">Litros</th><th scope="col">Entregas</th></tr></thead>
                            <tbody>
                                @foreach ($dashboard->litrosPorDia as $dia)
                                    <tr><td>{{ $dia['fecha'] }}</td><td>{{ number_format($dia['litros'], 2) }}</td><td>{{ $dia['entregas'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
                <p class="mt-3 text-xs text-text-muted">Se muestran las fechas con entregas válidas.</p>
            @endif
        </x-ui.card>

        <x-ui.card title="Distribución por calidad" subtitle="Análisis confirmados del período" class="min-w-0">
            @if ($dashboard->kpis['analisisConfirmados'] === 0)
                <x-ui.empty-state title="Sin datos de calidad" description="Los borradores no se incluyen en las estadísticas." />
            @else
                <div class="dashboard-donut" data-chart-container hidden>
                    <canvas data-quality-chart role="img" aria-label="Distribución de análisis aceptados, observados y rechazados; valores en la lista inferior"></canvas>
                </div>
                <ul class="space-y-4">
                    @foreach ($dashboard->distribucionCalidad as $calidad)
                        <li class="dashboard-quality-row">
                            <x-ui.badge :status="$calidad['resultado']" />
                            <div class="text-right">
                                <span class="text-sm font-semibold">{{ $calidad['cantidad'] }} · {{ number_format($calidad['porcentaje'], 1) }}%</span>
                                <span class="block text-xs text-text-muted">{{ number_format($calidad['litros'], 2) }} L válidos</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Rendimiento por zonas" subtitle="Acopio y calidad del período" class="min-w-0">
            @if ($dashboard->rendimientoZonas === [])
                <x-ui.empty-state title="No hay zonas registradas" />
            @else
                <div class="overflow-x-auto">
                    <table class="dashboard-table">
                        <caption class="sr-only">Proveedores atendidos, litros y porcentaje de calidad aceptada por zona</caption>
                        <thead><tr><th scope="col">Zona</th><th scope="col">Proveedores</th><th scope="col">Litros</th><th scope="col">Calidad</th></tr></thead>
                        <tbody>
                            @foreach ($dashboard->rendimientoZonas as $zona)
                                <tr>
                                    <th scope="row">{{ $zona['nombre'] }}</th>
                                    <td>{{ $zona['proveedores'] }}</td>
                                    <td>{{ number_format($zona['litros'], 2) }}</td>
                                    <td>{{ $zona['confirmados'] > 0 ? number_format($zona['calidad'], 1).'%' : 'Sin datos' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>

    <div class="dashboard-secondary">
        <x-ui.card title="Historial reciente de proveedores" subtitle="Últimas 6 entregas del período, incluidas las anuladas" class="min-w-0">
            <x-slot:actions><a href="{{ route('admin.entregas.index') }}" class="dashboard-link">Ver entregas →</a></x-slot:actions>
            @if ($dashboard->entregasRecientes === [])
                <x-ui.empty-state title="No hay entregas en este período" />
            @else
                <div class="overflow-x-auto">
                    <table class="dashboard-table dashboard-deliveries">
                        <thead><tr><th scope="col">Fecha</th><th scope="col">Proveedor</th><th scope="col">Zona</th><th scope="col">Litros</th><th scope="col">Calidad</th><th scope="col">Estado</th><th scope="col">Acción</th></tr></thead>
                        <tbody>
                            @foreach ($dashboard->entregasRecientes as $entrega)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $entrega['fecha'] }}</td>
                                    <th scope="row">{{ $entrega['proveedorNombre'] }}</th>
                                    <td>{{ $entrega['zonaNombre'] }}</td>
                                    <td class="whitespace-nowrap">{{ number_format($entrega['litros'], 2) }} L</td>
                                    <td>
                                        @if ($entrega['calidad'])
                                            <x-ui.badge :status="$entrega['calidad']" />
                                        @else
                                            <span class="text-xs text-text-muted">Sin confirmar</span>
                                        @endif
                                    </td>
                                    <td><x-ui.badge :status="$entrega['estado']" /></td>
                                    <td><a href="{{ route('admin.entregas.show', $entrega['id']) }}" class="dashboard-link" aria-label="Ver entrega de {{ $entrega['proveedorNombre'] }} del {{ $entrega['fecha'] }}">Ver →</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Producción reciente" subtitle="Último lote iniciado en el período" class="min-w-0">
            @if ($dashboard->produccionReciente === null)
                <x-ui.empty-state title="No existen lotes de producción" description="No hay lotes iniciados en este período." />
            @else
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <a class="dashboard-link text-lg" href="{{ route('admin.produccion.show', $dashboard->produccionReciente['id']) }}">{{ $dashboard->produccionReciente['codigo'] }}</a>
                    <x-ui.badge :status="$dashboard->produccionReciente['estado']" />
                </div>
                <p class="mt-2 text-xs text-text-muted">{{ $dashboard->produccionReciente['fecha'] }}</p>
                <div class="dashboard-production-litres">
                    <span class="text-3xl font-semibold">{{ number_format($dashboard->produccionReciente['litros'], 2) }} L</span>
                    <span class="text-sm text-primary-dark">Leche utilizada</span>
                </div>
                <h3 class="mb-3 text-sm font-semibold">Productos obtenidos</h3>
                <ul class="space-y-3">
                    @forelse ($dashboard->produccionReciente['productos'] as $producto)
                        <li class="flex flex-wrap justify-between gap-2 text-sm"><span>{{ $producto['nombre'] }}</span><strong>{{ number_format($producto['cantidad'], 2) }} {{ $producto['unidad'] }}</strong></li>
                    @empty
                        <li class="text-sm text-text-muted">Aún no hay resultados de producción registrados.</li>
                    @endforelse
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Actividad reciente" subtitle="Últimos movimientos registrados en el período" class="min-w-0">
            @if ($dashboard->actividadReciente === [])
                <x-ui.empty-state title="No hay actividad reciente" />
            @else
                <ol class="dashboard-activity">
                    @foreach ($dashboard->actividadReciente as $evento)
                        <li>
                            <span class="dashboard-activity-dot" aria-hidden="true"></span>
                            <div><p class="text-sm font-semibold">{{ $evento['titulo'] }}</p><p class="text-xs text-text-muted">{{ $evento['detalle'] }}</p></div>
                            <span class="text-xs text-text-muted">{{ $evento['fecha'] }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-ui.card>

        <x-ui.card title="Comunicaciones" subtitle="Publicados en el período" class="min-w-0">
            <x-slot:actions><a href="{{ route('admin.comunicados.index') }}" class="dashboard-link">Ver todos →</a></x-slot:actions>
            @if ($dashboard->comunicaciones['publicados'] === 0)
                <x-ui.empty-state title="No hay comunicados publicados" />
            @else
                <div class="dashboard-communications-stats">
                    <div><strong>{{ $dashboard->comunicaciones['publicados'] }}</strong><span>Publicados</span></div>
                    <div><strong>{{ $dashboard->comunicaciones['destinatarios'] }}</strong><span>Destinatarios</span></div>
                    <div><strong>{{ $dashboard->comunicaciones['tasaLectura'] !== null ? number_format($dashboard->comunicaciones['tasaLectura'], 1).'%' : '—' }}</strong><span>Lectura actual</span></div>
                </div>
                <p class="mt-2 text-xs text-text-muted">{{ $dashboard->comunicaciones['destinatarios'] > 0 ? $dashboard->comunicaciones['leidos'].' lecturas registradas' : 'Sin destinatarios registrados para calcular la lectura.' }}</p>
                <ul class="mt-4 space-y-4">
                    @foreach ($dashboard->comunicaciones['recientes'] as $comunicado)
                        <li>
                            <a href="{{ route('admin.comunicados.show', $comunicado['id']) }}" class="dashboard-link">{{ $comunicado['titulo'] }}</a>
                            <p class="mt-1 text-xs text-text-muted">{{ $comunicado['alcance'] }} · {{ $comunicado['fecha'] }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</div>
@endsection
