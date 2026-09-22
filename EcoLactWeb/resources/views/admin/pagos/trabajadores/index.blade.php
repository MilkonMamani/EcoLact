@extends('layouts.admin')

@section('title', 'Pagar a trabajadores')

@php
    $soles = fn ($valor): string => 'S/ '.number_format((float) $valor, 2);
    $hayFiltrosDeBusqueda = request()->anyFilled(['q', 'rol', 'estado']);
    $hayFiltros = $hayFiltrosDeBusqueda || request('periodo') !== null;
@endphp

@section('content')
    <div data-pago-trabajadores data-url-panel="{{ route('admin.pagos.trabajadores.panel', '__ID__') }}" data-url-ajuste="{{ route('admin.pagos.trabajadores.ajuste', '__ID__') }}" class="space-y-5">
        <x-layout.breadcrumbs :items="[['Pagos', null], ['Pagar a trabajadores', null]]" />

        <div class="flex items-start gap-4">
            <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="rounded-lg p-2 text-text transition hover:bg-text-muted/10 lg:hidden">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
            </button>
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary-soft text-primary-dark">
                <x-ui.icon name="users" class="h-7 w-7" />
            </span>
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-text">Pago a trabajadores</h1>
                <p class="mt-1.5 max-w-2xl text-sm text-label">Gestiona aquí los pagos individuales de los trabajadores de tu planta, correspondientes al mes seleccionado.</p>
            </div>
        </div>

        <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_23rem]">
            <div class="min-w-0 space-y-4">
                <x-ui.card>
                    <form method="GET" action="{{ route('admin.pagos.trabajadores.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1.4fr_auto] lg:items-end">
                        <x-forms.select name="rol" label="Rol" :options="collect($roles)->pluck('nombre', 'id')->all()" :value="request('rol')" placeholder="Todos" :sticky="false" />
                        <x-forms.input name="periodo" type="month" label="Mes" :value="$periodo" :sticky="false" />
                        <x-forms.select name="estado" label="Estado" :options="['EN_CURSO' => 'En curso', 'PENDIENTE' => 'Pendiente de pago', 'PAGADO' => 'Pagado']" :value="request('estado')" placeholder="Todos" :sticky="false" />
                        <x-forms.input name="q" label="Buscar trabajador" placeholder="Nombre del trabajador" :value="request('q')" :sticky="false" />
                        <div class="flex items-center gap-2">
                            <x-ui.button type="submit">Filtrar</x-ui.button>
                            @if ($hayFiltros)
                                <x-ui.button :href="route('admin.pagos.trabajadores.index')" variant="ghost">Limpiar</x-ui.button>
                            @endif
                        </div>
                    </form>
                </x-ui.card>

                <x-ui.card :padded="false">
                    @if ($filas->count() === 0)
                        <div class="p-5">
                            @if ($hayFiltrosDeBusqueda)
                                <x-ui.empty-state title="No se encontraron trabajadores" description="Prueba con otros filtros." />
                            @else
                                <x-ui.empty-state :title="$diagnostico?->mensaje() ?? 'Aún no hay pagos generados para este periodo.'" :description="'Periodo: '.$periodoEtiqueta.'.'" />
                            @endif
                        </div>
                    @else
                        <div class="ecolact-table-scroll">
                            <table class="w-full text-left text-sm" data-tabla-trabajadores>
                                <thead>
                                    <tr class="border-b border-text-muted/10 text-[11px] font-medium uppercase tracking-wider text-label">
                                        <th class="py-3 pl-5 pr-3">Trabajador</th>
                                        <th class="px-3 py-3">DNI</th>
                                        <th class="px-3 py-3">Días</th>
                                        <th class="px-3 py-3 text-right">Sueldo</th>
                                        <th class="px-3 py-3 text-right">Acumulado</th>
                                        <th class="px-3 py-3">Periodo</th>
                                        <th class="px-3 py-3 text-right">Total</th>
                                        <th class="py-3 pl-3 pr-5">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($filas as $fila)
                                        @php
                                            $seleccionada = $seleccion?->id === $fila->id;
                                        @endphp
                                        <tr
                                            data-fila-trabajador
                                            data-id="{{ $fila->id }}"
                                            data-selected="{{ $seleccionada ? 'true' : 'false' }}"
                                            class="cursor-pointer border-b border-text-muted/5 transition-colors last:border-0 hover:bg-background/50 data-[selected=true]:bg-primary-soft/40"
                                        >
                                            <td class="py-3 pl-5 pr-3">
                                                <div class="flex items-center gap-2.5">
                                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-soft text-sm font-semibold text-primary-dark">{{ $fila->iniciales }}</span>
                                                    <div class="min-w-0">
                                                        <p class="truncate font-medium text-text">{{ $fila->nombre }}</p>
                                                        <p class="truncate text-xs text-text-muted">{{ $fila->rol }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-3 text-text-muted">{{ $fila->dni ?? '—' }}</td>
                                            <td class="whitespace-nowrap px-3 py-3 text-text">{{ $fila->diasTrabajados }} / 30</td>
                                            <td class="whitespace-nowrap px-3 py-3 text-right text-text">{{ $soles($fila->sueldoBase) }}</td>
                                            <td class="whitespace-nowrap px-3 py-3 text-right text-text">{{ $soles($fila->acumulado) }}</td>
                                            <td class="whitespace-nowrap px-3 py-3 text-text">{{ $fila->periodoRango }}</td>
                                            <td data-celda-total class="whitespace-nowrap px-3 py-3 text-right font-semibold text-text">{{ $soles($fila->total) }}</td>
                                            <td class="whitespace-nowrap py-3 pl-3 pr-5"><x-ui.badge :status="$fila->estado" :icon="$fila->estado === 'PAGADO' ? 'check-circle' : 'clock'">{{ $fila->estadoEtiqueta }}</x-ui.badge></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="px-5 pb-5">
                            <x-ui.pagination :paginator="$filas" />
                        </div>
                    @endif
                </x-ui.card>
            </div>

            <aside class="xl:sticky xl:top-4 xl:self-start" data-panel-trabajador>
                @include('admin.pagos.trabajadores._panel')
            </aside>
        </div>
    </div>
@endsection
