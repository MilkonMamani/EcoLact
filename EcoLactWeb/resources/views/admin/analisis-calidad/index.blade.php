@extends('layouts.admin')

@section('title', 'Análisis de calidad')
@section('body-class', 'admin-acopio-page')
@section('contenedor', 'max-w-[1600px]')

@section('content')
    @php
        $filas = $datos->filas;
        $filtrosActivos = request()->anyFilled(['q', 'fecha', 'zona_id', 'resultado', 'analista_id', 'estado']);
        $resultados = ['ACEPTADA' => 'Aceptada', 'OBSERVADA' => 'Observada', 'RECHAZADA' => 'Rechazada'];
        $estados = [
            'PENDIENTE' => ['Pendiente de análisis', 'clock', 'is-pendiente'],
            'BORRADOR' => ['Análisis en borrador (sin confirmar)', 'pen-line', 'is-borrador'],
            'CONFIRMADO' => ['Análisis confirmado', 'check-double', 'is-confirmado'],
        ];

        // Encabezado ordenable (backend): mismo filtro, otra columna/dirección, desde la página 1.
        $urlOrden = function (string $columna) use ($filtro): string {
            $direccion = $filtro->orden === $columna
                ? ($filtro->direccion === 'asc' ? 'desc' : 'asc')
                : ($columna === 'fecha' ? 'desc' : 'asc');

            return request()->fullUrlWithQuery(['orden' => $columna, 'dir' => $direccion, 'pagina' => null]);
        };
    @endphp

    <div class="acopio calidad" data-acopio-page>
        <header class="acopio-header">
            <div class="min-w-0">
                <x-layout.breadcrumbs :items="[['Calidad', null], ['Análisis', null]]" />
                <h1 class="acopio-title">Análisis de calidad</h1>
                <p class="acopio-subtitle">Consulta y gestiona los análisis realizados a las entregas de leche.</p>
            </div>
        </header>

        <form method="GET" action="{{ route('admin.analisis-calidad.index') }}" class="acopio-filtros" data-acopio-filtros>
            <label class="acopio-search">
                <span class="sr-only">Buscar por proveedor, DNI o código de entrega</span>
                <x-ui.icon name="magnifying-glass" class="h-5 w-5" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por proveedor, DNI o código de entrega..." autocomplete="off">
            </label>

            <div class="acopio-filtros-fila">
                <x-forms.select name="fecha" label="Fecha" :sticky="false" :options="[
                    'hoy' => 'Hoy',
                    'ayer' => 'Ayer',
                    'esta_semana' => 'Esta semana',
                    'semana_anterior' => 'Semana anterior',
                    'este_mes' => 'Este mes',
                    'personalizado' => 'Personalizado',
                ]" :value="request('fecha')" placeholder="Todas" />

                @if (request('fecha') === 'personalizado')
                    <x-forms.input name="desde" type="date" label="Desde" value="{{ request('desde') }}" />
                    <x-forms.input name="hasta" type="date" label="Hasta" value="{{ request('hasta') }}" />
                @endif

                <x-forms.select name="zona_id" label="Zona" :sticky="false" :options="collect($datos->zonas)->pluck('nombre', 'id')->all()" :value="request('zona_id')" placeholder="Todas" />
                <x-forms.select name="resultado" label="Resultado" :sticky="false" :options="$resultados" :value="request('resultado')" placeholder="Todos" />
                <x-forms.select name="analista_id" label="Analista" :sticky="false" :options="collect($datos->analistas)->pluck('nombre', 'id')->all()" :value="request('analista_id')" placeholder="Todos" />
                <x-forms.select name="estado" label="Estado" :sticky="false" :options="['PENDIENTE' => 'Pendiente', 'BORRADOR' => 'Borrador', 'CONFIRMADO' => 'Confirmado']" :value="request('estado')" placeholder="Todos" />

                @if (request()->filled('orden'))
                    <input type="hidden" name="orden" value="{{ $filtro->orden }}">
                    <input type="hidden" name="dir" value="{{ $filtro->direccion }}">
                @endif
                <input type="hidden" name="por_pagina" value="{{ $porPagina }}">

                <div class="acopio-filtros-acciones">
                    <button type="submit" class="acopio-btn is-dark">Filtrar</button>
                    <a href="{{ route('admin.analisis-calidad.index') }}" class="acopio-btn is-outline">Limpiar</a>
                </div>
            </div>
        </form>

        @if ($filas->total() === 0)
            <div class="acopio-vacio">
                <x-ui.icon name="beaker" class="h-6 w-6" />
                <div>
                    <p class="font-semibold text-text">{{ $filtrosActivos ? 'Ningún análisis coincide con los filtros' : 'No hay entregas para analizar' }}</p>
                    <p>{{ $filtrosActivos ? 'Prueba con otra búsqueda o limpia los filtros.' : 'Las entregas registradas en Acopio aparecen aquí; el análisis se registra cuando el analista muestrea al proveedor.' }}</p>
                </div>
            </div>
        @else
            <div class="acopio-tabla is-loading" data-acopio-tabla>
                <table>
                    <thead>
                        <tr>
                            @foreach (['fecha' => 'Fecha', 'proveedor' => 'Proveedor'] as $columna => $etiqueta)
                                <th scope="col" @if ($filtro->orden === $columna) aria-sort="{{ $filtro->direccion === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                    <a href="{{ $urlOrden($columna) }}" class="calidad-orden @if ($filtro->orden === $columna) is-activo @endif">
                                        {{ $etiqueta }}
                                        <x-ui.icon :name="$filtro->orden === $columna && $filtro->direccion === 'asc' ? 'chevron-up' : 'chevron-down'" class="h-3 w-3" />
                                    </a>
                                </th>
                            @endforeach
                            <th scope="col" class="is-secundaria">Entrega</th>
                            <th scope="col" class="is-secundaria">Zona</th>
                            <th scope="col" class="is-num is-secundaria">Litros</th>
                            <th scope="col" @if ($filtro->orden === 'resultado') aria-sort="{{ $filtro->direccion === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                <a href="{{ $urlOrden('resultado') }}" class="calidad-orden @if ($filtro->orden === 'resultado') is-activo @endif">
                                    Resultado
                                    <x-ui.icon :name="$filtro->orden === 'resultado' && $filtro->direccion === 'asc' ? 'chevron-up' : 'chevron-down'" class="h-3 w-3" />
                                </a>
                            </th>
                            <th scope="col" class="is-estado">Estado</th>
                            <th scope="col" class="is-secundaria">Analista</th>
                            <th scope="col" class="is-accion">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="acopio-skeleton" aria-hidden="true">
                        @for ($i = 0; $i < min(8, max(1, $filas->count())); $i++)
                            <tr>
                                @foreach (['is-lg', 'is-xl', 'is-xs', 'is-md', 'is-sm', 'is-md', 'is-xs', 'is-lg', 'is-dot'] as $indice => $ancho)
                                    <td @class(['is-secundaria' => in_array($indice, [2, 3, 4, 7], true)])><span class="acopio-bone {{ $ancho }}"></span></td>
                                @endforeach
                            </tr>
                        @endfor
                    </tbody>
                    <tbody data-acopio-filas>
                        @forelse ($filas as $fila)
                            @php [$etiquetaEstado, $iconoEstado, $claseEstado] = $estados[$fila['estado']] ?? [$fila['estado'], 'info', 'is-borrador']; @endphp
                            <tr data-acopio-fila>
                                <td class="calidad-fecha">
                                    <span>{{ \Illuminate\Support\Str::before($fila['fecha'], ' ') }}</span>
                                    <span class="acopio-dni">{{ \Illuminate\Support\Str::after($fila['fecha'], ' ') }}</span>
                                </td>
                                <td>
                                    <span class="acopio-proveedor">{{ $fila['proveedorNombre'] }}</span>
                                    <span class="acopio-dni">DNI: {{ $fila['proveedorDni'] }}</span>
                                </td>
                                <td class="is-muted is-secundaria">#{{ $fila['entregaId'] }}</td>
                                <td class="is-muted is-secundaria">{{ $fila['zonaNombre'] }}</td>
                                <td class="is-num acopio-litros is-secundaria">{{ number_format($fila['litros'], 2) }} L</td>
                                <td>
                                    @if ($fila['resultado'])
                                        <span class="calidad-resultado is-{{ strtolower($fila['resultado']) }}">{{ $resultados[$fila['resultado']] ?? $fila['resultado'] }}</span>
                                    @else
                                        <span class="is-muted">—</span>
                                    @endif
                                </td>
                                <td class="is-estado">
                                    <span class="calidad-estado {{ $claseEstado }}" title="{{ $etiquetaEstado }}">
                                        <x-ui.icon :name="$iconoEstado" class="h-4 w-4" />
                                        <span class="sr-only">{{ $etiquetaEstado }}</span>
                                    </span>
                                </td>
                                <td class="is-muted is-secundaria">{{ $fila['analistaNombre'] ?? '—' }}</td>
                                <td class="is-accion">
                                    <a href="{{ $fila['analisisId'] ? route('admin.analisis-calidad.show', $fila['analisisId']) : route('admin.entregas.analisis-calidad.index', $fila['entregaId']) }}" class="acopio-ver" aria-label="Ver análisis de la entrega #{{ $fila['entregaId'] }}" title="Ver análisis" data-entrega-ver data-modal-detalle="modal-ver-analisis" data-url-detalle="{{ route('admin.entregas.analisis-calidad.detalle', $fila['entregaId']) }}">
                                        <x-ui.icon name="eye" class="h-4 w-4" />
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="acopio-sin-pagina">No hay análisis en esta página. <a href="{{ $filas->url(1) }}">Ir a la primera página</a></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <noscript><style>.acopio-tabla.is-loading [data-acopio-filas] { display: table-row-group; } .acopio-tabla.is-loading .acopio-skeleton { display: none; }</style></noscript>

            <div class="calidad-pie">
                <x-tables.pagination :paginator="$filas" noun="análisis" class="acopio-pager" />

                <form method="GET" action="{{ route('admin.analisis-calidad.index') }}" class="calidad-por-pagina" data-acopio-por-pagina>
                    @foreach (request()->except(['por_pagina', 'pagina']) as $clave => $valor)
                        @if (is_string($valor))
                            <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
                        @endif
                    @endforeach
                    <label for="calidad-por-pagina">Mostrar</label>
                    <select id="calidad-por-pagina" name="por_pagina">
                        @foreach ($opcionesPorPagina as $opcion)
                            <option value="{{ $opcion }}" @selected($opcion === $porPagina)>{{ $opcion }}</option>
                        @endforeach
                    </select>
                    <span>por página</span>
                    <noscript><button type="submit" class="acopio-btn is-outline is-sm">Aplicar</button></noscript>
                </form>
            </div>
        @endif

        {{-- Modal "Ver análisis": el contenido (resources/views/admin/analisis-calidad/_detalle.blade.php) lo carga admin-acopio.js. --}}
        <div id="modal-ver-analisis" data-modal class="fixed inset-0 z-50 items-center justify-center bg-text/40 p-4" role="dialog" aria-modal="true" aria-label="Ver análisis">
            <div class="acopio-detalle-panel" data-entrega-detalle-contenido></div>
        </div>
    </div>
@endsection
