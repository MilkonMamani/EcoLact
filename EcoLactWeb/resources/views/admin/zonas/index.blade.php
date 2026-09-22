@extends('layouts.admin')

@section('title', 'Zonas')

@section('content')
    @php
        $estados = ['ACTIVO' => 'Activa', 'INACTIVO' => 'Inactiva'];
        $modalActivo = old('_modal');
        $mapa = config('services.google_maps');
    @endphp

    <div
        data-zonas-page
        data-url-buscar="{{ route('admin.zonas.proveedores.buscar') }}"
        data-url-asignacion="{{ route('admin.zonas.proveedores.asignacion', '__ID__') }}"
        data-url-nueva-ruta="{{ route('admin.zonas.rutas.store', '__ID__') }}"
        data-catalogo="{{ json_encode($catalogo) }}"
        data-mapa-lat="{{ $mapa['default_lat'] }}"
        data-mapa-lng="{{ $mapa['default_lng'] }}"
        data-mapa-zoom="{{ $mapa['default_zoom'] }}"
        data-abrir-modal="{{ $errors->any() ? $modalActivo : '' }}"
        class="space-y-4"
    >
        <x-ui.card>
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
                        class="w-full rounded-lg border border-text-muted/25 bg-surface py-2 pl-9 pr-3 text-sm text-text shadow-sm transition-colors placeholder:text-text-muted/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                </div>
                <div id="buscar-proveedor-resultados" data-buscador-resultados role="listbox" aria-label="Resultados de la búsqueda" class="absolute left-0 right-0 z-20 mt-1 hidden max-h-72 overflow-y-auto rounded-lg border border-text-muted/15 bg-surface shadow-lg"></div>
            </div>
        </x-ui.card>

        <div data-zonas-aviso role="alert" hidden class="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger"></div>

        <div class="grid items-start gap-4 lg:grid-cols-[19rem_minmax(0,1fr)]">
            <x-ui.card title="Zonas" :padded="false" class="lg:col-start-1 lg:row-start-1 lg:sticky lg:top-4">
                <x-slot:actions>
                    @can('create', \App\Infrastructure\Persistence\Models\ZonaModel::class)
                        <x-ui.action-button type="create" label="Nueva zona" data-modal-open="modal-nueva-zona" />
                    @endcan
                </x-slot:actions>

                <nav aria-label="Zonas">
                    <ul data-zonas-lista class="space-y-0.5 p-2">
                        <li>
                            <a
                                href="{{ route('admin.zonas.index', ['zona' => 'sin-asignar']) }}"
                                data-zona-link
                                data-zona-key="sin-asignar"
                                data-panel-url="{{ route('admin.zonas.panel.sin-asignar') }}"
                                data-selected="{{ $seleccion === 'sin-asignar' ? 'true' : 'false' }}"
                                @if ($seleccion === 'sin-asignar') aria-current="page" @endif
                                class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm text-text transition-colors hover:bg-text-muted/5 data-[selected=true]:bg-primary-soft data-[selected=true]:font-medium data-[selected=true]:text-primary-dark"
                            >
                                <span class="truncate italic">Sin asignar</span>
                                <span class="rounded-full bg-warning/10 px-2 py-0.5 text-xs font-medium text-warning">{{ $sinAsignar }}</span>
                            </a>
                        </li>

                        @foreach ($datos->filas as $fila)
                            <li class="flex items-center gap-1.5">
                                <a
                                    href="{{ route('admin.zonas.index', ['zona' => $fila['id']]) }}"
                                    data-zona-link
                                    data-zona-key="{{ $fila['id'] }}"
                                    data-panel-url="{{ route('admin.zonas.panel', $fila['id']) }}"
                                    data-selected="{{ $seleccion === $fila['id'] ? 'true' : 'false' }}"
                                    @if ($seleccion === $fila['id']) aria-current="page" @endif
                                    class="flex min-w-0 flex-1 items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm text-text transition-colors hover:bg-text-muted/5 data-[selected=true]:bg-primary-soft data-[selected=true]:font-medium data-[selected=true]:text-primary-dark"
                                >
                                    <span class="truncate">{{ $fila['nombre'] }}@if ($fila['estado'] !== 'ACTIVO') <span class="text-xs font-normal text-text-muted">(inactiva)</span>@endif</span>
                                    <span class="rounded-full bg-text-muted/10 px-2 py-0.5 text-xs text-text-muted">{{ $fila['proveedores'] }}</span>
                                </a>
                                <x-ui.action-button type="edit" label="Editar zona" :subject="$fila['nombre']" data-modal-open="modal-editar-zona-{{ $fila['id'] }}" />
                                <form method="POST" action="{{ route('admin.zonas.destroy', $fila['id']) }}" data-confirm="¿Eliminar la zona {{ $fila['nombre'] }}?&#10;&#10;Esta acción eliminará permanentemente la zona. Solo es posible si no tiene rutas, proveedores, acopiadores ni historial asociados.">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.action-button type="delete" :submit="true" label="Eliminar zona" :subject="$fila['nombre']" />
                                </form>
                            </li>
                        @endforeach
                    </ul>

                    @if (count($datos->filas) === 0)
                        <p class="px-4 pb-4 text-sm text-text-muted">Aún no hay zonas. Crea la primera con el botón +.</p>
                    @endif
                </nav>
            </x-ui.card>

            <div data-zona-panel class="contents">
                @include('admin.zonas._panel')
            </div>
        </div>
    </div>

    {{-- Modales de zona: fuera del panel para que no se reemplacen al cambiar de zona. --}}
    <x-ui.modal id="modal-nueva-zona" title="Nueva zona">
        <form method="POST" action="{{ route('admin.zonas.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="modal-nueva-zona">
            <x-forms.input name="nombre" id="modal-nueva-zona-nombre" :sticky="$modalActivo === 'modal-nueva-zona'" label="Nombre de la zona" placeholder="Ej. Collana 1" required autofocus />
            <x-forms.select name="estado" id="modal-nueva-zona-estado" :sticky="$modalActivo === 'modal-nueva-zona'" label="Estado" required :options="$estados" value="ACTIVO" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" variant="primary">Crear zona</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    @foreach ($datos->filas as $fila)
        @php($modalEditarZona = 'modal-editar-zona-'.$fila['id'])
        <x-ui.modal :id="$modalEditarZona" title="Editar zona">
            <form method="POST" action="{{ route('admin.zonas.update', $fila['id']) }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <input type="hidden" name="_modal" value="{{ $modalEditarZona }}">
                <x-forms.input name="nombre" :id="$modalEditarZona.'-nombre'" :sticky="$modalActivo === $modalEditarZona" label="Nombre" :value="$fila['nombre']" required />
                <x-forms.textarea name="descripcion" :id="$modalEditarZona.'-descripcion'" :sticky="$modalActivo === $modalEditarZona" label="Descripción" :value="$fila['descripcion']" :rows="3" />
                <x-forms.select name="estado" :id="$modalEditarZona.'-estado'" :sticky="$modalActivo === $modalEditarZona" label="Estado" required :options="$estados" :value="$fila['estado']" />
                @if ($fila['estado'] === 'ACTIVO' && ($fila['proveedores'] > 0 || $fila['acopiadores'] > 0))
                    <p class="rounded-lg bg-warning/10 p-3 text-xs text-warning">Esta zona tiene {{ $fila['proveedores'] }} proveedor(es) y {{ $fila['acopiadores'] }} acopiador(es) asignados. Antes de desactivarla, reasígnalos a otra zona.</p>
                @endif
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                    <x-ui.button type="submit" variant="primary">Guardar cambios</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endforeach

    {{-- Modal de asignación: uno solo para toda la página; se rellena por JS (proveedor, zona, ruta, mapa). --}}
    <x-ui.modal id="modal-asignacion" size="xl" title="Asignar proveedor a zona y ruta">
        <form data-asignacion-form novalidate class="space-y-4">
            <div class="rounded-lg bg-background px-4 py-3">
                <p data-asignacion-nombre class="text-sm font-semibold text-text"></p>
                <p class="text-xs text-text-muted">DNI: <span data-asignacion-dni></span></p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-forms.label for="asignacion-zona" :required="true">Zona</x-forms.label>
                    <select id="asignacion-zona" name="zona_id" data-campo="zona_id" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                        <option value="">Seleccionar zona</option>
                    </select>
                    <p data-error-for="zona_id" class="mt-1.5 text-xs text-danger" hidden></p>
                </div>
                <div>
                    <x-forms.label for="asignacion-ruta" :required="true">Ruta</x-forms.label>
                    <select id="asignacion-ruta" name="ruta_id" data-campo="ruta_id" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                        <option value="">Seleccionar ruta</option>
                    </select>
                    <p data-ruta-vacia class="mt-1.5 text-xs text-text-muted" hidden>Esta zona todavía no tiene rutas activas.</p>
                    <button type="button" data-crear-ruta-principal hidden class="mt-1.5 text-xs font-medium text-primary hover:underline">+ Crear ruta principal para esta zona</button>
                    <p data-crear-ruta-error class="mt-1.5 text-xs text-danger" hidden></p>
                    <p data-error-for="ruta_id" class="mt-1.5 text-xs text-danger" hidden></p>
                </div>
            </div>

            <div>
                <div data-asignacion-mapa class="h-64 w-full rounded-lg bg-text-muted/5 ring-1 ring-text-muted/15 sm:h-80"></div>
                <p data-mapa-ayuda class="mt-1.5 text-xs text-text-muted">Haz clic en el mapa para marcar la ubicación exacta del proveedor. Puedes arrastrar el marcador para afinarla.</p>
                <p data-mapa-aviso class="mt-1.5 text-xs text-warning" hidden></p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-forms.label for="asignacion-latitud">Latitud</x-forms.label>
                    <input id="asignacion-latitud" name="latitud" data-campo="latitud" type="number" step="any" inputmode="decimal" placeholder="-15.840200" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm transition-colors placeholder:text-text-muted/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" />
                    <p data-error-for="latitud" class="mt-1.5 text-xs text-danger" hidden></p>
                </div>
                <div>
                    <x-forms.label for="asignacion-longitud">Longitud</x-forms.label>
                    <input id="asignacion-longitud" name="longitud" data-campo="longitud" type="number" step="any" inputmode="decimal" placeholder="-70.021900" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm transition-colors placeholder:text-text-muted/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" />
                    <p data-error-for="longitud" class="mt-1.5 text-xs text-danger" hidden></p>
                </div>
            </div>

            <div>
                <x-forms.label for="asignacion-referencia">Referencia (opcional)</x-forms.label>
                <input id="asignacion-referencia" name="referencia_ubicacion" data-campo="referencia_ubicacion" type="text" maxlength="255" placeholder="Ej. Frente a la escuela, a 200 metros de la plaza" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm transition-colors placeholder:text-text-muted/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" />
                <p data-error-for="referencia_ubicacion" class="mt-1.5 text-xs text-danger" hidden></p>
            </div>

            <p data-error-general class="rounded-lg bg-danger/10 px-3 py-2 text-sm text-danger" hidden></p>

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" variant="primary" data-asignacion-guardar>Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <button type="button" hidden data-modal-open="modal-asignacion" data-asignacion-disparador aria-hidden="true" tabindex="-1"></button>
@endsection
