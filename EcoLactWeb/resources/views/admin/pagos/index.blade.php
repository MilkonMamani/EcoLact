@extends('layouts.admin')

@section('title', 'Pagos')

@php
    $soles = fn ($valor): string => 'S/ '.number_format((float) $valor, 2);
    $kpis = $datos['kpis'];
    $filas = $datos['filas'];
    $hayFiltros = request()->anyFilled(['q', 'tipo', 'periodo', 'estado']);
@endphp

@section('content')
    <div data-pagos-page data-pagos-listado class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="rounded-lg p-2 text-text transition hover:bg-text-muted/10 lg:hidden">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                </button>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-text">Pagos</h1>
                    <p class="mt-1.5 max-w-3xl text-sm text-label">Aquí se muestran los pagos pendientes y pagados generados desde Configuración de pagos.</p>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat-card label="Pendientes de pago" :value="$kpis['pendientes']" accent="warning">
                <x-slot:icon><x-ui.icon name="clock" class="h-6 w-6" /></x-slot:icon>
            </x-ui.stat-card>
            <x-ui.stat-card label="Pagados este mes" :value="$kpis['pagadosMes']" accent="success">
                <x-slot:icon><x-ui.icon name="check-circle" class="h-6 w-6" /></x-slot:icon>
            </x-ui.stat-card>
            <x-ui.stat-card label="Total pendiente" :value="$soles($kpis['totalPendiente'])" accent="info">
                <x-slot:icon><x-ui.icon name="coins" class="h-6 w-6" /></x-slot:icon>
            </x-ui.stat-card>
            <x-ui.stat-card label="Total pagado" :value="$soles($kpis['totalPagado'])" hint="Pagos de este mes" accent="success">
                <x-slot:icon><x-ui.icon name="coins" class="h-6 w-6" /></x-slot:icon>
            </x-ui.stat-card>
        </div>

        <section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-info/20 bg-info-soft px-5 py-4" aria-label="Cómo se generan los pagos">
            <p class="flex max-w-3xl items-start gap-2 text-sm text-text">
                <x-ui.icon name="info" class="mt-0.5 h-5 w-5 shrink-0 text-info" />
                <span>Los pagos pendientes se generan automáticamente desde <a href="{{ route('admin.configuracion-pagos.index') }}" class="font-medium text-primary underline-offset-2 hover:underline">Configuración de pagos</a>. Se actualizan solos al registrar entregas y confirmar análisis. Aquí solo revisas, filtras y registras el pago.</span>
            </p>
            @can('generate', \App\Infrastructure\Persistence\Models\ObligacionPagoModel::class)
                <form method="POST" action="{{ route('admin.pagos.generar') }}">
                    @csrf
                    <button type="submit" title="Respaldo administrativo: normalmente los pagos pendientes se actualizan solos al registrar entregas y confirmar análisis." class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-info hover:bg-info/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-info">
                        <x-ui.icon name="clock" class="h-4 w-4" /> Actualizar pendientes
                    </button>
                </form>
            @endcan
        </section>

        <x-ui.card>
            <form method="GET" action="{{ route('admin.pagos.index') }}" class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1.2fr_1fr_1fr_auto] lg:items-end">
                <x-forms.input name="q" label="Buscar beneficiario" placeholder="Nombre del proveedor o del personal" :value="request('q')" :sticky="false" />
                <x-forms.select name="tipo" label="Tipo" :options="$tipos" :value="request('tipo')" placeholder="Todos" :sticky="false" />
                <x-forms.input name="periodo" type="month" label="Periodo" :value="request('periodo')" :sticky="false" />
                <x-forms.select name="estado" label="Estado" :options="['PENDIENTE' => 'Pendiente', 'PAGADO' => 'Pagado']" :value="request('estado')" placeholder="Todos" :sticky="false" />
                <div class="flex items-center gap-2">
                    <x-ui.button type="submit">Filtrar</x-ui.button>
                    @if ($hayFiltros)
                        <x-ui.button :href="route('admin.pagos.index')" variant="ghost">Limpiar</x-ui.button>
                    @endif
                </div>
            </form>

            @if ($filas->count() === 0)
                <x-ui.empty-state
                    :title="$hayFiltros ? 'No se encontraron pagos' : 'Aún no hay pagos generados'"
                    :description="$hayFiltros ? 'Prueba con otros filtros.' : 'Configura los pagos de proveedores y personal; los pendientes aparecerán aquí cuando corresponda el periodo.'" />
            @else
                <x-tables.table class="ecolact-table-cards">
                    <thead>
                        <tr class="border-b border-text-muted/10 text-[11px] font-medium uppercase tracking-wider text-label">
                            <th class="py-2 pr-4">Beneficiario</th>
                            <th class="py-2 pr-4">Tipo</th>
                            <th class="py-2 pr-4">Base de cálculo</th>
                            <th class="py-2 pr-4">Periodo</th>
                            <th class="py-2 pr-4 text-right">Monto</th>
                            <th class="py-2 pr-4">Estado</th>
                            <th class="py-2 pl-4 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filas as $fila)
                            @php($pendiente = $fila['estado'] === 'PENDIENTE')
                            <tr class="border-b border-text-muted/5 {{ $pendiente ? '' : 'last:border-0' }}">
                                <td data-label="Beneficiario" class="py-4 pr-4 font-medium text-text">{{ $fila['beneficiario'] }}</td>
                                <td data-label="Tipo" class="py-4 pr-4 text-text">{{ $fila['tipo'] }}</td>
                                <td data-label="Base de cálculo" class="py-4 pr-4 text-text">{{ $fila['base'] }}</td>
                                <td data-label="Periodo" class="py-4 pr-4 text-text">
                                    {{ $fila['periodo'] }}
                                    <span class="block text-xs text-text-muted">{{ $fila['rango'] }}</span>
                                </td>
                                <td data-label="Monto" class="py-4 pr-4 text-right font-semibold text-text whitespace-nowrap">{{ $soles($fila['monto']) }}</td>
                                <td data-label="Estado" class="py-4 pr-4"><x-ui.badge compact :status="$fila['estado']" :icon="$pendiente ? 'clock' : 'check-circle'">{{ $fila['estadoEtiqueta'] }}</x-ui.badge></td>
                                <td data-label="Acción" class="py-4 pl-4">
                                    <x-tables.actions>
                                        @if ($pendiente)
                                            @can('pay', \App\Infrastructure\Persistence\Models\ObligacionPagoModel::class)
                                                <x-ui.action-button type="register-payment" :text="true" label="Registrar pago" :subject="'de '.$fila['beneficiario'].', '.$fila['periodo']" data-modal-open="modal-registrar-pago" data-registrar-pago
                                                    data-id="{{ $fila['id'] }}"
                                                    data-beneficiario="{{ $fila['beneficiario'] }}"
                                                    data-tipo="{{ $fila['tipo'] }}"
                                                    data-regla="{{ $fila['regla'] }}"
                                                    data-periodo="{{ $fila['periodo'] }}"
                                                    data-rango="{{ $fila['rango'] }}"
                                                    data-periodo-inicio="{{ $fila['periodoInicio'] }}"
                                                    data-monto="{{ number_format($fila['monto'], 2, '.', '') }}"
                                                    data-detalle="{{ json_encode($fila['detalle'], JSON_UNESCAPED_UNICODE) }}"
                                                    data-action="{{ route('admin.pagos.pagar', $fila['id']) }}" />
                                            @endcan
                                        @else
                                            <x-ui.action-button type="detail" :text="true" label="Ver detalle" :subject="'de '.$fila['beneficiario']" data-toggle-row="detalle-pago-{{ $fila['id'] }}" aria-expanded="false" aria-controls="detalle-pago-{{ $fila['id'] }}" />
                                        @endif
                                    </x-tables.actions>
                                </td>
                            </tr>
                            @unless ($pendiente)
                                <tr id="detalle-pago-{{ $fila['id'] }}" hidden class="border-b border-text-muted/5 bg-background/60 last:border-0">
                                    <td colspan="7" data-full class="px-4 py-4">
                                        @include('admin.pagos._detalle', ['fila' => $fila])
                                    </td>
                                </tr>
                            @endunless
                        @endforeach
                    </tbody>
                </x-tables.table>

                <x-ui.pagination :paginator="$filas" />
            @endif
        </x-ui.card>
    </div>

    @can('pay', \App\Infrastructure\Persistence\Models\ObligacionPagoModel::class)
        @include('admin.pagos._modal-registrar', ['metodos' => $metodos, 'metodosElectronicos' => $metodosElectronicos])
    @endcan
@endsection
