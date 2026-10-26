{{--
    Zonas: cabecera → aviso "Proveedores sin zona" → buscador de proveedores → mapa general (el
    centro del módulo) y, a la derecha, el panel de la zona (clases fichas-*, admin-fichas.js para
    el panel, sus pestañas y el skeleton). admin-zonas.js añade lo propio de Zonas: el mapa general
    con la selección de proveedores y "Mover ubicación", el editor de delimitación y el modal de
    asignación. La zona se elige tocando su polígono, su etiqueta o la leyenda del mapa.

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
        $seleccionadoId = $panel?->id;
    @endphp

    <div
        class="fichas"
        data-fichas-page
        data-fichas-param="zona"
        data-fichas-url-panel="{{ route('admin.zonas.panel', '__ID__') }}"
        data-zonas-page
        data-url-buscar="{{ route('admin.zonas.proveedores.buscar') }}"
        data-url-asignacion="{{ route('admin.zonas.proveedores.asignacion', '__ID__') }}"
        data-url-mover="{{ route('admin.zonas.proveedores.ubicacion', '__ID__') }}"
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
            <section class="fichas-lista" aria-label="Mapa de zonas y proveedores">
                <header class="fichas-header">
                    <div class="min-w-0">
                        <x-layout.breadcrumbs :items="[['Operación', null], ['Zonas', null]]" />
                        <h1 class="fichas-title">Zonas</h1>
                        <p class="fichas-subtitle">Administra las zonas de acopio, ubica a sus proveedores en el mapa y consulta sus acopiadores.</p>
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
                        <a href="{{ route('admin.zonas.index', ['zona' => 'sin-asignar']) }}" class="fichas-btn is-outline is-sm">Ver proveedores</a>
                    </div>
                @endif

                {{-- Buscador principal: proveedores reales por nombre o DNI; elegir uno enfoca su pin en el mapa. --}}
                <div class="zonas-buscador" data-zonas-buscador>
                    <label class="fichas-search">
                        <span class="sr-only">Buscar proveedor por nombre o DNI</span>
                        <x-ui.icon name="magnifying-glass" class="h-5 w-5" />
                        <input type="search" placeholder="Buscar proveedor por nombre o DNI..." autocomplete="off" role="combobox" aria-expanded="false" aria-controls="zonas-buscador-resultados" aria-autocomplete="list" data-zonas-buscador-input>
                        <button type="button" class="zonas-buscador-limpiar" aria-label="Limpiar selección" title="Limpiar selección" data-zonas-buscador-limpiar hidden>
                            <x-ui.icon name="x-mark" class="h-4 w-4" />
                        </button>
                    </label>
                    <div id="zonas-buscador-resultados" class="zonas-buscador-resultados" role="listbox" aria-label="Proveedores encontrados" data-zonas-buscador-resultados hidden></div>
                </div>

                @if ($mapaZonas->zonas !== [])
                    @include('admin.zonas._mapa-general')
                @else
                    <div class="fichas-vacio">
                        <x-ui.icon name="map" class="h-6 w-6" />
                        <div>
                            <p class="font-semibold text-text">Aún no hay zonas</p>
                            <p>Crea la primera zona para empezar a asignar proveedores.</p>
                        </div>
                    </div>
                @endif
            </section>

            <aside class="fichas-panel" data-fichas-panel aria-live="polite" aria-label="Detalle de la zona">
                @include('admin.zonas._panel')
            </aside>
        </div>

        <template data-fichas-panel-vacio>
            <x-fichas.panel-vacio entidad="zona">Elige una zona en el mapa para ver su resumen, proveedores y acopiadores.</x-fichas.panel-vacio>
        </template>

        <template data-fichas-panel-skeleton>
            <x-fichas.panel-skeleton />
        </template>
    </div>

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

    @include('admin.zonas._modales-mover')

    @include('admin.zonas._modal-asignacion')
@endsection
