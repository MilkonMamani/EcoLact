@extends('layouts.admin')

@section('title', 'Pagos a trabajadores')
@section('body-class', 'admin-fichas-page')
@section('contenedor', 'max-w-[1600px]')

{{--
    Pagos a trabajadores: mismo patrón "listado + panel" que Proveedores/Acopiadores
    (admin-fichas.js: skeleton, entrada escalonada, panel por fetch con ?trabajador=ID).
    Todo lo que se pinta sale de TrabajadorPagoFila (snapshot de obligaciones_pago +
    faltas de jornadas_personal); aquí no se calcula dinero. Bonificación, descuento y
    registrar pago siguen en admin-pago-trabajadores.js.
--}}
@php
    $soles = fn ($valor): string => 'S/ '.number_format((float) $valor, 2);
    $hayFiltrosDeBusqueda = request()->anyFilled(['q', 'rol', 'estado']);
    $filtrosPanel = array_filter(request()->only(['q', 'rol', 'periodo', 'estado']), fn ($valor) => $valor !== null && $valor !== '');
    $seleccionadaClave = $seleccion?->clave();
@endphp

@section('content')
    <div class="fichas pagos-trabajadores" data-fichas-page data-fichas-param="trabajador" data-pago-trabajadores data-url-ajuste="{{ route('admin.pagos.trabajadores.ajuste', '__ID__') }}">
        <div class="fichas-layout">
            <section class="fichas-lista" aria-label="Pagos a trabajadores">
                <header class="fichas-header">
                    <div class="flex min-w-0 items-start gap-3">
                        <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="rounded-lg p-2 text-text transition hover:bg-text-muted/10 lg:hidden">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                        </button>
                        <div class="min-w-0">
                            <x-layout.breadcrumbs :items="[['Finanzas', null], ['Pagos', null], ['Trabajadores', null]]" />
                            <h1 class="fichas-title">Pagos a trabajadores</h1>
                            <p class="fichas-subtitle">Gestiona los pagos individuales de los trabajadores correspondientes al mes seleccionado.</p>
                        </div>
                    </div>
                </header>

                {{-- Buscador en su propia fila y filtros compactos debajo, siempre dentro de la columna izquierda. --}}
                <form method="GET" action="{{ route('admin.pagos.trabajadores.index') }}" class="fichas-filtros pagos-filtros" data-fichas-filtros>
                    <label class="fichas-search pagos-buscador">
                        <span class="sr-only">Buscar trabajador por nombre o DNI</span>
                        <x-ui.icon name="magnifying-glass" class="h-5 w-5" />
                        <input id="filtro-q" type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre o DNI..." autocomplete="off">
                    </label>

                    <div class="pagos-filtros-fila">
                        <div class="pagos-filtro is-mes">
                            <label for="filtro-periodo">Mes</label>
                            <input id="filtro-periodo" type="month" name="periodo" value="{{ $periodo }}" max="{{ now()->format('Y-m') }}">
                        </div>
                        <div class="pagos-filtro is-rol">
                            <x-forms.select name="rol" id="filtro-rol" label="Rol" :sticky="false" :options="collect($roles)->pluck('nombre', 'id')->all()" :value="request('rol')" placeholder="Todos" />
                        </div>
                        <div class="pagos-filtro is-estado">
                            <x-forms.select name="estado" id="filtro-estado" label="Estado" :sticky="false" :options="['EN_CURSO' => 'En curso', 'PENDIENTE' => 'Pendiente', 'PAGADO' => 'Pagado', 'SIN_SUELDO' => 'Sin sueldo configurado']" :value="request('estado')" placeholder="Todos" />
                        </div>
                        <div class="fichas-filtros-acciones">
                            <button type="submit" class="fichas-btn is-dark">Filtrar</button>
                            <a href="{{ route('admin.pagos.trabajadores.index') }}" class="fichas-btn is-outline">Limpiar</a>
                        </div>
                    </div>
                </form>

                @if ($filas->total() === 0)
                    <div class="fichas-vacio">
                        <x-ui.icon name="users" class="h-6 w-6" />
                        <div>
                            @if ($hayFiltrosDeBusqueda)
                                <p class="font-semibold text-text">No se encontraron trabajadores</p>
                                <p>Prueba con otros filtros.</p>
                            @else
                                <p class="font-semibold text-text">{{ $diagnostico?->mensaje() ?? 'Aún no hay pagos generados para este periodo.' }}</p>
                                <p>Periodo: {{ $periodoEtiqueta }}.</p>
                            @endif
                        </div>
                    </div>
                @else
                    @if ($diagnostico)
                        <div class="fichas-vacio" data-diagnostico-sueldos>
                            <x-ui.icon name="users" class="h-6 w-6" />
                            <div>
                                <p class="font-semibold text-text">{{ $diagnostico->mensaje() }}</p>
                                <p>Los trabajadores cuyo rol no tiene sueldo se muestran sin montos. <a href="{{ route('admin.configuracion-pagos.index') }}" class="font-semibold text-primary-dark hover:underline">Configurar sueldos →</a></p>
                            </div>
                        </div>
                    @endif
                    <div class="fichas-tabla pagos-tabla is-loading" data-fichas-tabla>
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Trabajador</th>
                                    <th scope="col">Rol</th>
                                    <th scope="col" class="is-num">Asistencias</th>
                                    <th scope="col" class="is-num">Faltas</th>
                                    <th scope="col" class="is-num">Sueldo mensual</th>
                                    <th scope="col" class="is-num">Valor día</th>
                                    <th scope="col" class="is-num">Acumulado</th>
                                    <th scope="col" class="is-num">Total</th>
                                    <th scope="col">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="fichas-skeleton" aria-hidden="true">
                                @for ($i = 0; $i < min(8, max(1, $filas->count())); $i++)
                                    <tr>
                                        <td><span class="flex items-center gap-2.5"><span class="fichas-bone is-avatar-sm"></span><span class="fichas-bone is-lg"></span></span></td>
                                        @foreach (['is-md', 'is-xs', 'is-xs', 'is-sm', 'is-sm', 'is-sm', 'is-sm', 'is-pill'] as $ancho)
                                            <td><span class="fichas-bone {{ $ancho }}"></span></td>
                                        @endforeach
                                    </tr>
                                @endfor
                            </tbody>
                            <tbody data-fichas-filas>
                                @forelse ($filas as $fila)
                                    @php
                                        $urlPanel = $fila->sinSueldo()
                                            ? route('admin.pagos.trabajadores.sin-sueldo', ['trabajador' => $fila->trabajadorId, 'periodo' => $periodo] + $filtrosPanel)
                                            : route('admin.pagos.trabajadores.panel', ['obligacion' => $fila->id] + $filtrosPanel);
                                        $seleccionada = $fila->clave() === $seleccionadaClave;
                                    @endphp
                                    <tr
                                        data-fichas-fila
                                        data-ficha-id="{{ $fila->clave() }}"
                                        data-url-panel="{{ $urlPanel }}"
                                        tabindex="0"
                                        aria-selected="{{ $seleccionada ? 'true' : 'false' }}"
                                        @class(['is-selected' => $seleccionada, 'is-sin-sueldo' => $fila->sinSueldo()])
                                    >
                                        <td>
                                            <span class="pagos-trabajador">
                                                <span class="pagos-avatar" aria-hidden="true">{{ $fila->iniciales }}</span>
                                                <span class="min-w-0">
                                                    <a href="{{ request()->fullUrlWithQuery(['trabajador' => $fila->clave()]) }}" class="fichas-nombre" data-fichas-seleccionar tabindex="-1">{{ $fila->nombre }}</a>
                                                    <span class="pagos-dni">{{ $fila->dni ? 'DNI '.$fila->dni : 'Sin DNI' }}</span>
                                                </span>
                                            </span>
                                        </td>
                                        <td class="is-muted">{{ $fila->rol }}</td>
                                        <td class="is-num">{{ $fila->diasTrabajados }}</td>
                                        <td @class(['is-num', 'pagos-faltas' => $fila->faltas > 0])>{{ $fila->faltas }}</td>
                                        @if ($fila->sinSueldo())
                                            <td class="is-num is-muted">—</td>
                                            <td class="is-num is-muted">—</td>
                                            <td class="is-num is-muted">—</td>
                                            <td class="is-num is-muted" data-celda-total>—</td>
                                        @else
                                            <td class="is-num">{{ $soles($fila->sueldoBase) }}</td>
                                            <td class="is-num is-muted">{{ $soles($fila->valorDia) }}</td>
                                            <td class="is-num">{{ $soles($fila->acumulado) }}</td>
                                            <td class="is-num pagos-total" data-celda-total>{{ $soles($fila->total) }}</td>
                                        @endif
                                        <td><x-pagos.estado-pago :estado="$fila->estado" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="fichas-sin-pagina">No hay trabajadores en esta página. <a href="{{ $filas->url(1) }}">Ir a la primera página</a></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <noscript><style>.fichas-tabla.is-loading [data-fichas-filas] { display: table-row-group; } .fichas-tabla.is-loading .fichas-skeleton { display: none; }</style></noscript>

                    <x-tables.pagination :paginator="$filas" noun="trabajadores" class="fichas-pager" />
                @endif
            </section>

            <aside class="fichas-panel" data-fichas-panel aria-live="polite" aria-label="Resumen del trabajador">
                @include('admin.pagos.trabajadores._panel')
            </aside>
        </div>

        <template data-fichas-panel-vacio>
            @include('admin.pagos.trabajadores._panel', ['seleccion' => null])
        </template>

        <template data-fichas-panel-skeleton>
            <x-fichas.panel-skeleton />
        </template>
    </div>
@endsection
