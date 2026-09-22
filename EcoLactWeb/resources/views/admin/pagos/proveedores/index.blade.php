@extends('layouts.admin')

@section('title', 'Pagar a proveedores')
@section('contenedor', 'max-w-[1560px]')

@php
    $soles = fn ($valor): string => 'S/ '.number_format((float) $valor, 2);
    $ajuste = fn ($valor): string => ((float) $valor < 0 ? '- ' : ((float) $valor > 0 ? '+ ' : '- ')).'S/ '.number_format(abs((float) $valor), 2);
    $kpis = $datos['kpis'];
    $filas = $datos['filas'];
    $semana = $datos['semana'];
    $titulos = [
        'pendientes' => 'Proveedores pendientes de pago',
        'pagados' => 'Proveedores pagados',
        'todos' => $datos['todas'] ? 'Historial de proveedores' : 'Proveedores de la semana',
    ];
    $estados = ['todos' => 'Todos', 'pendientes' => 'Pendientes de pago', 'pagados' => 'Pagados'];
    $campo = 'flex h-11 items-stretch overflow-hidden rounded-lg border border-text-muted/25 bg-surface shadow-sm focus-within:border-primary focus-within:ring-1 focus-within:ring-primary';
    $campoIcono = 'flex w-11 shrink-0 items-center justify-center border-r border-text-muted/15 bg-background/70 text-primary';
    $campoSelect = 'w-full min-w-0 cursor-pointer appearance-none bg-transparent pl-3 pr-8 text-sm text-text focus:outline-none';
@endphp

@section('content')
    <div data-pago-semanal data-preview-url="{{ route('admin.pagos.proveedores.preview') }}" @if ($verId) data-ver-id="{{ $verId }}" @endif class="space-y-5">
        <x-layout.breadcrumbs :items="[['Pagos', null], ['Pago a proveedores', null]]" />

        <div class="flex items-center gap-4">
            <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="rounded-lg p-2 text-text transition hover:bg-text-muted/10 lg:hidden">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
            </button>
            <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-primary-soft text-primary-dark">
                <x-ui.icon name="calendar-coin" class="h-11 w-11" />
            </span>
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-[#12324a]">Pago semanal a proveedores</h1>
                <p class="mt-1.5 max-w-3xl text-sm text-label">Selecciona los proveedores, revisa los montos y genera la documentación para el pago en efectivo.</p>
            </div>
        </div>

        <div class="grid gap-x-5 gap-y-4 xl:grid-cols-[minmax(0,1fr)_24rem] 2xl:grid-cols-[minmax(0,1fr)_27rem]">
            {{-- KPIs --}}
            <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4 xl:col-start-1">
                <div class="flex items-center gap-3 rounded-xl border border-text-muted/10 bg-surface p-4 shadow-sm">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-dark"><x-ui.icon name="users" class="h-6 w-6" /></span>
                    <div class="min-w-0">
                        <p class="whitespace-nowrap text-[13px] font-semibold text-[#12324a]">Pendientes de pago</p>
                        <p class="mt-0.5 flex items-baseline gap-1.5 whitespace-nowrap"><span class="text-3xl font-bold text-[#12324a]">{{ $kpis['pendientes'] }}</span><span class="text-xs text-text-muted">proveedores</span></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-text-muted/10 bg-surface p-4 shadow-sm">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-dark"><x-ui.icon name="droplet" class="h-6 w-6" /></span>
                    <div class="min-w-0">
                        <p class="whitespace-nowrap text-[13px] font-semibold text-[#12324a]">Litros pagables</p>
                        <p class="mt-0.5 whitespace-nowrap text-3xl font-bold text-[#12324a]">{{ number_format($kpis['litros'], 0) }} L</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-text-muted/10 bg-surface p-4 shadow-sm">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-dark"><x-ui.icon name="banknote" class="h-6 w-6" /></span>
                    <div class="min-w-0">
                        <p class="whitespace-nowrap text-[13px] font-semibold text-[#12324a]">Total pendiente</p>
                        <p class="mt-0.5 whitespace-nowrap text-3xl font-bold text-[#12324a]">{{ $soles($kpis['totalPendiente']) }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-text-muted/10 bg-surface p-4 shadow-sm">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-soft">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-white"><x-ui.icon name="check" class="h-5 w-5" /></span>
                    </span>
                    <div class="min-w-0">
                        <p class="whitespace-nowrap text-[13px] font-semibold text-[#12324a]">Seleccionados</p>
                        <p class="mt-0.5 flex items-baseline gap-1.5 whitespace-nowrap"><span data-kpi-seleccionados class="text-3xl font-bold text-[#12324a]">0</span><span class="text-xs text-text-muted">proveedores</span></p>
                    </div>
                </div>
            </div>

            <div class="min-w-0 space-y-4 xl:col-start-1 xl:row-start-2">
            {{-- Filtros --}}
            <form method="GET" action="{{ route('admin.pagos.proveedores.index') }}" data-filtros class="rounded-xl border border-text-muted/10 bg-surface p-5 shadow-sm">
                <input type="hidden" name="buscar" value="{{ $buscar }}" data-buscar-espejo>
                <div class="grid items-start gap-4 sm:grid-cols-3 2xl:grid-cols-[1fr_1fr_1fr_auto]">
                    <div>
                        <label for="filtro-semana" class="mb-1.5 block text-[13px] font-semibold text-[#12324a]">Semana</label>
                        <div class="{{ $campo }} relative">
                            <span class="{{ $campoIcono }}"><x-ui.icon name="calendar" class="h-[18px] w-[18px]" /></span>
                            <select id="filtro-semana" name="semana" class="{{ $campoSelect }}">
                                <option value="{{ \App\Application\Pago\PagoSemanalFiltro::SEMANA_TODAS }}" @selected($datos['todas'])>Todos</option>
                                @foreach ($datos['semanas'] as $opcion)
                                    <option value="{{ $opcion['inicio'] }}" @selected(! $datos['todas'] && $opcion['inicio'] === $semana['inicio'])>{{ $opcion['etiqueta'] }}</option>
                                @endforeach
                            </select>
                            <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                        </div>
                        <p class="ml-1 mt-1 text-xs text-text-muted">{{ $semana['rango'] }}</p>
                        @if (! $datos['todas'] && $datos['pendientesOtrasSemanas'] > 0)
                            <p class="ml-1 mt-0.5 text-xs font-medium text-warning">{{ $datos['pendientesOtrasSemanas'] }} pendiente(s) en otras semanas</p>
                        @endif
                    </div>
                    <div>
                        <label for="filtro-zona" class="mb-1.5 block text-[13px] font-semibold text-[#12324a]">Zona</label>
                        <div class="{{ $campo }} relative">
                            <span class="{{ $campoIcono }}"><x-ui.icon name="map-pin" class="h-[18px] w-[18px]" /></span>
                            <select id="filtro-zona" name="zona" class="{{ $campoSelect }}">
                                <option value="">Todas las zonas</option>
                                @foreach ($datos['zonas'] as $zona)
                                    <option value="{{ $zona['id'] }}" @selected($filtro->zonaId === $zona['id'])>{{ $zona['nombre'] }}</option>
                                @endforeach
                            </select>
                            <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                        </div>
                    </div>
                    <div>
                        <label for="filtro-estado" class="mb-1.5 block text-[13px] font-semibold text-[#12324a]">Estado</label>
                        <div class="{{ $campo }} relative">
                            <span class="{{ $campoIcono }}"><x-ui.icon name="clock" class="h-[18px] w-[18px]" /></span>
                            <select id="filtro-estado" name="estado" class="{{ $campoSelect }}">
                                @foreach ($estados as $valor => $etiqueta)
                                    <option value="{{ $valor }}" @selected($filtro->estado === $valor)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                            <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                        </div>
                    </div>
                    <div class="sm:col-span-3 2xl:col-span-1 2xl:pt-[1.72rem]">
                        <button type="button" data-seleccionar-pendientes class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-dark focus-visible:outline-primary">
                            <x-ui.icon name="map-pin" class="h-[18px] w-[18px]" /> Seleccionar pendientes de la zona
                        </button>
                    </div>
                </div>

                <p class="mt-4 flex items-center gap-3 rounded-lg bg-info-soft px-4 py-2.5 text-sm text-[#12324a]">
                    <x-ui.icon name="info" class="h-5 w-5 shrink-0 text-info" />
                    Se imprimirá 1 hoja por cada proveedor seleccionado.
                </p>
            </form>

            {{-- Tabla --}}
            <section class="rounded-xl border border-text-muted/10 bg-surface shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-dark"><x-ui.icon name="users" class="h-6 w-6" /></span>
                        <div>
                            <h2 class="text-lg font-semibold leading-tight text-[#12324a]">{{ $titulos[$filtro->estado] }}</h2>
                            <p class="text-sm text-text-muted">{{ $datos['todas'] ? 'Consulta todo el historial y selecciona los pagos pendientes.' : 'Selecciona los proveedores que recibirán su pago esta semana.' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="relative block">
                            <span class="sr-only">Buscar proveedor o zona</span>
                            <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-text-muted" />
                            <input type="search" data-buscar value="{{ $buscar }}" placeholder="Buscar proveedor, zona..." autocomplete="off" class="h-10 w-64 rounded-lg border border-text-muted/25 bg-surface pl-10 pr-3 text-sm text-text placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                        </label>
                        <div class="relative">
                            <button type="button" data-dropdown-toggle="menu-opciones-pago" aria-label="Más opciones" class="flex h-10 w-10 items-center justify-center rounded-lg border border-text-muted/25 bg-surface text-text transition hover:bg-text-muted/10">
                                <x-ui.icon name="ellipsis" class="h-5 w-5" />
                            </button>
                            <div id="menu-opciones-pago" data-dropdown-menu class="absolute right-0 z-20 mt-1 w-56 rounded-lg border border-text-muted/15 bg-surface p-1 shadow-lg">
                                <button type="button" data-seleccionar-pendientes class="block w-full rounded-md px-3 py-2 text-left text-sm text-text hover:bg-text-muted/10">Seleccionar todos los pendientes</button>
                                <button type="button" data-limpiar class="block w-full rounded-md px-3 py-2 text-left text-sm text-text hover:bg-text-muted/10">Limpiar selección</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ecolact-table-scroll mt-4">
                    <table class="w-full min-w-[860px] text-[13px]">
                        <thead>
                            <tr class="border-y border-text-muted/10 bg-background/40 text-left text-xs font-medium text-text-muted">
                                <th class="w-12 py-3 pl-5 pr-2">
                                    <input type="checkbox" data-marcar-todos aria-label="Seleccionar todos los pendientes" class="h-[18px] w-[18px] cursor-pointer rounded border-text-muted/40 accent-primary">
                                </th>
                                <th class="px-2 py-3">Proveedor</th>
                                <th class="px-2 py-3">Zona</th>
                                <th class="px-2 py-3">Litros<br>pagables</th>
                                <th class="px-2 py-3">Precio por<br>litro</th>
                                <th class="px-2 py-3">Penalidad /<br>ajuste</th>
                                <th class="px-2 py-3">Compras<br>descontadas</th>
                                <th class="px-2 py-3">Subtotal</th>
                                <th class="px-2 py-3">Total final</th>
                                <th class="py-3 pl-2 pr-5">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filas as $fila)
                                <tr data-fila data-id="{{ $fila['id'] }}" data-pendiente="{{ $fila['pendiente'] ? 1 : 0 }}" data-litros="{{ $fila['litros'] }}" data-total="{{ number_format($fila['total'], 2, '.', '') }}" data-buscar-texto="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($fila['proveedor'].' '.$fila['zona'].' '.($fila['zonaDetalle'] ?? '').' '.$fila['semanaEtiqueta'].' '.$fila['estadoEtiqueta'])) }}" class="cursor-pointer border-b border-text-muted/10 transition-colors hover:bg-background/50">
                                    <td class="py-3 pl-5 pr-2">
                                        <input type="checkbox" data-marcar value="{{ $fila['id'] }}" @disabled(! $fila['pendiente']) aria-label="Seleccionar a {{ $fila['proveedor'] }}" class="h-[18px] w-[18px] rounded border-text-muted/40 accent-primary {{ $fila['pendiente'] ? 'cursor-pointer' : 'cursor-not-allowed opacity-40' }}">
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-3 font-medium text-[#12324a]">
                                        {{ $fila['proveedor'] }}
                                        @if ($datos['todas'])
                                            <span class="block text-xs font-normal text-text-muted">{{ $fila['semanaEtiqueta'] }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-3 text-text">
                                        {{ $fila['zona'] }}
                                        @if ($fila['zonaDetalle'])
                                            <span class="block text-xs text-text-muted">{{ $fila['zonaDetalle'] }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-3 text-text">{{ number_format($fila['litros'], 0) }} L</td>
                                    <td class="whitespace-nowrap px-2 py-3 text-text">{{ $soles($fila['precioLitro']) }}</td>
                                    <td class="whitespace-nowrap px-2 py-3 text-text">{{ $ajuste($fila['penalidad']) }}</td>
                                    <td class="whitespace-nowrap px-2 py-3 text-text">- {{ $soles($fila['compras']) }}</td>
                                    <td class="whitespace-nowrap px-2 py-3 text-text">{{ $soles($fila['subtotal']) }}</td>
                                    <td class="whitespace-nowrap px-2 py-3 font-semibold text-[#12324a]">{{ $soles($fila['total']) }}</td>
                                    <td class="py-3 pl-2 pr-5"><x-ui.badge :status="$fila['estado']" :icon="$fila['pendiente'] ? 'clock' : 'check-circle'" class="rounded-full">{{ $fila['estadoEtiqueta'] }}</x-ui.badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div data-sin-resultados @if (count($filas) > 0) hidden @endif class="px-5 py-10 text-center">
                        <p class="text-sm font-medium text-text">{{ count($filas) === 0 ? ($datos['todas'] ? 'No hay proveedores para estos filtros' : 'No hay proveedores para esta semana y estos filtros') : 'No se encontraron proveedores con esa búsqueda' }}</p>
                        <p class="mt-1 text-sm text-text-muted">{{ count($filas) === 0 ? 'Prueba con otra semana, zona o estado. Los pagos se generan desde Configuración de pagos.' : 'Prueba con otro nombre o zona.' }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <p data-resumen-tabla class="text-sm text-text-muted"></p>
                    <div data-paginacion class="flex items-center gap-1.5"></div>
                </div>
            </section>

            </div>

            {{-- Hoja individual --}}
            <aside class="xl:sticky xl:top-4 xl:col-start-2 xl:row-start-2 xl:self-start">
                <section class="rounded-xl border border-text-muted/10 bg-surface p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-dark"><x-ui.icon name="file-text" class="h-6 w-6" /></span>
                        <div>
                            <h2 class="text-lg font-semibold leading-tight text-[#12324a]">Hoja individual del proveedor</h2>
                            <p class="text-xs text-text-muted">Vista previa del comprobante para el proveedor seleccionado.</p>
                        </div>
                    </div>

                    <div class="mt-3" data-mini>
                        <div data-mini-vacio class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-text-muted/25 bg-background/60 px-6 py-16 text-center">
                            <x-ui.icon name="file-text" class="h-10 w-10 text-text-muted/50" />
                            <p class="text-sm font-medium text-text">Aún no hay un proveedor seleccionado</p>
                            <p class="max-w-[16rem] text-xs text-text-muted">Haz clic en un proveedor de la lista y aquí verás su comprobante de pago, pendiente o pagado.</p>
                        </div>
                        <div data-mini-cargando hidden class="rounded-lg border border-dashed border-text-muted/25 bg-background/60 px-6 py-16 text-center text-sm text-text-muted">Cargando comprobante…</div>
                        <div data-mini-hoja hidden class="pp-miniatura mx-auto"></div>
                    </div>
                </section>
            </aside>
        </div>

        {{-- Acciones --}}
        <div class="sticky bottom-0 z-30 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t border-text-muted/10 bg-background/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <button type="button" data-limpiar class="inline-flex h-12 items-center gap-2.5 rounded-lg border border-text-muted/25 bg-surface px-6 text-sm font-medium text-[#12324a] shadow-sm transition hover:bg-text-muted/10">
                <x-ui.icon name="x-mark" class="h-5 w-5" /> Limpiar selección
            </button>
            @can('pay', \App\Infrastructure\Persistence\Models\ObligacionPagoModel::class)
                <button type="button" data-abrir-vista-previa disabled class="inline-flex h-12 items-center gap-3 rounded-lg bg-primary px-7 text-[15px] font-medium text-white shadow-sm transition-colors hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-50">
                    <x-ui.icon name="printer" class="h-5 w-5" /> Imprimir y marcar pagados
                </button>
            @endcan
        </div>
    </div>

    @include('admin.pagos.proveedores._modal-vista-previa', ['filtro' => $filtro, 'semana' => $semana])
@endsection
