{{--
    Zonas: la misma pantalla "listado + panel" de Proveedores y Acopiadores (clases fichas-*,
    admin-fichas.js para selección/pestañas/skeleton y admin-estado-switch.js para el switch).
    admin-zonas.js solo añade lo propio de Zonas: el mapa general (primero, encima de la tabla), el mapa
    del Resumen, el editor de delimitación y el modal de asignación.

    @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $filas
    @var \App\Application\Zona\ZonasMapaData $mapaZonas
    @var ?\App\Application\Zona\ZonaPanelData $panel
    @var ?list<\App\Application\Zona\ProveedorAsignadoFila> $proveedoresSinZona
--}}
@extends('layouts.admin')

@section('title', 'Zonas')
@section('body-class', 'admin-fichas-page')
@section('contenedor', 'max-w-[1600px]')

@section('content')
    @php
        $modalActivo = old('_modal');
        $mapa = config('services.google_maps');
        $filtrosActivos = request()->anyFilled(['q', 'estado']);
        $seleccionadoId = $panel?->id;
    @endphp

    <div
        class="fichas"
        data-fichas-page
        data-fichas-param="zona"
        data-estado-clave="zona"
        data-modal-desactivar="modal-desactivar-zona"
        data-modal-reactivar="modal-reactivar-zona"
        data-zonas-page
        data-url-buscar="{{ route('admin.zonas.proveedores.buscar') }}"
        data-url-asignacion="{{ route('admin.zonas.proveedores.asignacion', '__ID__') }}"
        data-url-nueva-ruta="{{ route('admin.zonas.rutas.store', '__ID__') }}"
        data-catalogo="{{ json_encode($catalogo) }}"
        data-mapa-lat="{{ $mapa['default_lat'] }}"
        data-mapa-lng="{{ $mapa['default_lng'] }}"
        data-mapa-zoom="{{ $mapa['default_zoom'] }}"
        data-zonas-lat="{{ $mapa['zonas_lat'] }}"
        data-zonas-lng="{{ $mapa['zonas_lng'] }}"
        data-zonas-zoom="{{ $mapa['zonas_zoom'] }}"
        data-abrir-modal="{{ $errors->any() ? $modalActivo : '' }}"
    >
        <div class="fichas-layout">
            <section class="fichas-lista" aria-label="Listado de zonas">
                <header class="fichas-header">
                    <div class="min-w-0">
                        <x-layout.breadcrumbs :items="[['Operación', null], ['Zonas', null]]" />
                        <h1 class="fichas-title">Zonas</h1>
                        <p class="fichas-subtitle">Administra las zonas de acopio y consulta sus proveedores y acopiadores.</p>
                    </div>
                    @can('create', \App\Infrastructure\Persistence\Models\ZonaModel::class)
                        <button type="button" data-modal-open="modal-nueva-zona" class="fichas-btn is-primary">
                            <x-ui.icon name="plus" class="h-4 w-4" /> Nueva zona
                        </button>
                    @endcan
                </header>

                @if ($sinAsignar > 0)
                    <div class="zonas-sin-zona" role="status">
                        <x-ui.icon name="map-pin" class="h-4 w-4" />
                        <p><strong>Proveedores sin zona</strong> {{ $sinAsignar }} {{ $sinAsignar === 1 ? 'proveedor pendiente' : 'proveedores pendientes' }} de asignación.</p>
                        <a href="{{ route('admin.zonas.index', array_filter(['zona' => 'sin-asignar', 'q' => request('q'), 'estado' => request('estado')])) }}" class="fichas-btn is-outline is-sm">Ver proveedores</a>
                    </div>
                @endif

                <form method="GET" action="{{ route('admin.zonas.index') }}" class="fichas-filtros zonas-filtros" data-fichas-filtros>
                    <label class="fichas-search">
                        <span class="sr-only">Buscar zona por nombre o descripción</span>
                        <x-ui.icon name="magnifying-glass" class="h-5 w-5" />
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar zona por nombre o descripción..." autocomplete="off">
                    </label>

                    <div class="fichas-filtros-fila">
                        <x-forms.select name="estado" label="Estado" :sticky="false" :options="['ACTIVO' => 'Activa', 'INACTIVO' => 'Inactiva']" :value="request('estado')" placeholder="Todas" />

                        <div class="fichas-filtros-acciones">
                            <button type="submit" class="fichas-btn is-dark">Filtrar</button>
                            <a href="{{ route('admin.zonas.index') }}" class="fichas-btn is-outline">Limpiar</a>
                        </div>
                    </div>
                </form>

                @if ($mapaZonas->zonas !== [])
                    @include('admin.zonas._mapa-general')
                @endif

                <div class="zonas-seccion-head is-tabla">
                    <h2 class="zonas-seccion-titulo">Resumen de zonas</h2>
                </div>

                @if ($filas->total() === 0)
                    <div class="fichas-vacio">
                        <x-ui.icon name="map" class="h-6 w-6" />
                        <div>
                            <p class="font-semibold text-text">{{ $filtrosActivos ? 'Ninguna zona coincide con los filtros' : 'Aún no hay zonas' }}</p>
                            <p>{{ $filtrosActivos ? 'Prueba con otra búsqueda o limpia los filtros.' : 'Crea la primera zona para empezar a asignar proveedores.' }}</p>
                        </div>
                    </div>
                @else
                    <div class="fichas-tabla is-loading" data-fichas-tabla>
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Nombre</th>
                                    <th scope="col">Descripción</th>
                                    <th scope="col" class="is-num">Proveedores</th>
                                    <th scope="col" class="is-num">Acopiadores</th>
                                    <th scope="col" class="is-num">Litros (semana)</th>
                                    <th scope="col">Última entrega</th>
                                    <th scope="col" class="is-estado">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="fichas-skeleton" aria-hidden="true">
                                @for ($i = 0; $i < min(8, max(1, $filas->count())); $i++)
                                    <tr>
                                        @foreach (['is-md', 'is-xl', 'is-sm', 'is-sm', 'is-sm', 'is-md', 'is-switch'] as $ancho)
                                            <td @class(['is-estado' => $ancho === 'is-switch'])><span class="fichas-bone {{ $ancho }}"></span></td>
                                        @endforeach
                                    </tr>
                                @endfor
                            </tbody>
                            <tbody data-fichas-filas>
                                @forelse ($filas as $fila)
                                    <tr data-fichas-fila data-ficha-id="{{ $fila['id'] }}" data-url-panel="{{ route('admin.zonas.panel', $fila['id']) }}" tabindex="0" aria-selected="{{ $fila['id'] === $seleccionadoId ? 'true' : 'false' }}" @class(['is-selected' => $fila['id'] === $seleccionadoId])>
                                        <td>
                                            <a href="{{ request()->fullUrlWithQuery(['zona' => $fila['id']]) }}" class="fichas-nombre" data-fichas-seleccionar tabindex="-1">{{ $fila['nombre'] }}</a>
                                        </td>
                                        <td class="is-muted zonas-descripcion" title="{{ $fila['descripcion'] }}">{{ $fila['descripcion'] ?? '—' }}</td>
                                        <td class="is-num">{{ $fila['proveedores'] }}</td>
                                        <td class="is-num">{{ $fila['acopiadores'] }}</td>
                                        <td class="is-num">{{ number_format($fila['litrosSemana'], 0) }} L</td>
                                        <td @class(['is-muted' => $fila['ultimaEntrega'] === null])>{{ $fila['ultimaEntrega'] ?? 'Sin entregas' }}</td>
                                        <td class="is-estado">
                                            <x-ui.estado-switch
                                                :activo="$fila['estado'] === 'ACTIVO'"
                                                :nombre="$fila['nombre']"
                                                entidad="zona"
                                                data-registro-id="{{ $fila['id'] }}"
                                                data-url-desactivar="{{ route('admin.zonas.update', $fila['id']) }}"
                                                data-url-reactivar="{{ route('admin.zonas.update', $fila['id']) }}"
                                            />
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="fichas-sin-pagina">No hay zonas en esta página. <a href="{{ $filas->url(1) }}">Ir a la primera página</a></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <noscript><style>.fichas-tabla.is-loading [data-fichas-filas] { display: table-row-group; } .fichas-tabla.is-loading .fichas-skeleton { display: none; }</style></noscript>

                    <x-tables.pagination :paginator="$filas" noun="zonas" class="fichas-pager" />
                @endif
            </section>

            <aside class="fichas-panel" data-fichas-panel aria-live="polite" aria-label="Detalle de la zona">
                @include('admin.zonas._panel')
            </aside>
        </div>

        <template data-fichas-panel-vacio>
            <x-fichas.panel-vacio entidad="zona">Haz click en una fila del listado para ver su resumen, proveedores y acopiadores.</x-fichas.panel-vacio>
        </template>

        <template data-fichas-panel-skeleton>
            <x-fichas.panel-skeleton />
        </template>
    </div>

    {{--
        Modales del switch de estado. Ambos envían el MISMO PATCH admin.zonas.update
        (ActualizarZona): admin-estado-switch.js les pone la acción y el nombre de la zona.
        Si la zona todavía tiene acopiadores o proveedores activos, el servidor responde 422
        y el mensaje queda en el modal sin mover el switch.
    --}}
    <x-ui.modal id="modal-desactivar-zona" title="Desactivar zona">
        <p class="text-sm text-text">¿Deseas desactivar <strong data-estado-nombre>la zona</strong>?</p>
        <p class="mt-2 text-sm text-text-muted">Solo es posible si ya no tiene acopiadores ni proveedores activos asignados. Su historial se conservará.</p>
        <form method="POST" action="" class="mt-4 space-y-3" data-desactivar-form>
            @csrf
            @method('PATCH')
            <input type="hidden" name="estado" value="INACTIVO">
            <p class="text-sm text-danger" role="alert" data-desactivar-error hidden></p>
            <div class="flex justify-end gap-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" variant="danger" data-desactivar-confirmar>Sí, desactivar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-reactivar-zona" title="Activar zona">
        <p class="text-sm text-text">¿Deseas activar <strong data-estado-nombre>la zona</strong>?</p>
        <p class="mt-2 text-sm text-text-muted">Volverá a estar disponible para asignar proveedores, rutas y acopiadores.</p>
        <form method="POST" action="" class="mt-4 space-y-3" data-reactivar-form>
            @csrf
            @method('PATCH')
            <input type="hidden" name="estado" value="ACTIVO">
            <p class="text-sm text-danger" role="alert" data-reactivar-error hidden></p>
            <div class="flex justify-end gap-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" data-reactivar-confirmar>Activar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-nueva-zona" title="Nueva zona">
        <form method="POST" action="{{ route('admin.zonas.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="modal-nueva-zona">
            <x-forms.input name="nombre" id="modal-nueva-zona-nombre" :sticky="$modalActivo === 'modal-nueva-zona'" label="Nombre" placeholder="Ej. Collana 1" required autofocus />
            <x-forms.textarea name="descripcion" id="modal-nueva-zona-descripcion" :sticky="$modalActivo === 'modal-nueva-zona'" label="Descripción" :rows="3" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" variant="primary">Crear zona</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Buscador para "+ Asignar proveedor": cualquier proveedor activo por nombre o DNI; elegirlo abre el modal de asignación. --}}
    <x-ui.modal id="modal-buscar-proveedor" title="Asignar proveedor">
        <div data-buscador class="relative">
            <label for="buscar-proveedor" class="mb-1.5 block text-sm font-medium text-text">Buscar proveedor por nombre o DNI</label>
            <div class="relative">
                <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                <input
                    id="buscar-proveedor"
                    type="search"
                    autocomplete="off"
                    placeholder="Ej. Ana Quispe o 12345678"
                    aria-controls="buscar-proveedor-resultados"
                    class="w-full rounded-lg border border-text-muted/25 bg-surface py-2 pl-9 pr-3 text-sm text-text transition-colors placeholder:text-text-muted/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                />
            </div>
            <div id="buscar-proveedor-resultados" data-buscador-resultados role="listbox" aria-label="Resultados de la búsqueda" class="mt-2 hidden max-h-72 overflow-y-auto rounded-lg border border-text-muted/15 bg-surface"></div>
        </div>
    </x-ui.modal>

    {{-- Mapa completo de la zona seleccionada (lo pinta admin-zonas.js con los mismos marcadores del Resumen). --}}
    <x-ui.modal id="modal-mapa-zona" size="xl" title="Ubicación de proveedores">
        <div data-zona-mapa-completo class="h-[28rem] w-full rounded-lg bg-text-muted/5 ring-1 ring-text-muted/15"></div>
    </x-ui.modal>

    @include('admin.zonas._modal-asignacion')
@endsection
