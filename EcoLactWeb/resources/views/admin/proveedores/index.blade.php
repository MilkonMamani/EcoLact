@extends('layouts.admin')

@section('title', 'Proveedores')
@section('body-class', 'admin-fichas-page')
@section('contenedor', 'max-w-[1600px]')

@section('content')
    @php
        $filas = $datos->filas;
        $filtrosActivos = request()->anyFilled(['q', 'estado', 'zona_id', 'seguimiento']);
        $seleccionadoId = $panel?->id;
    @endphp

    <div class="fichas" data-fichas-page data-fichas-param="proveedor" data-estado-clave="proveedor" data-modal-desactivar="modal-desactivar-proveedor" data-modal-reactivar="modal-reactivar-proveedor">
        <div class="fichas-layout">
            <section class="fichas-lista" aria-label="Listado de proveedores">
                <header class="fichas-header">
                    <div class="min-w-0">
                        <x-layout.breadcrumbs :items="[['Operación', null], ['Proveedores', null]]" />
                        <h1 class="fichas-title">Proveedores</h1>
                        <p class="fichas-subtitle">Productores que entregan leche al sistema.</p>
                    </div>
                    @can('create', \App\Infrastructure\Persistence\Models\ProveedorModel::class)
                        <button type="button" data-modal-open="modal-nuevo-proveedor" class="fichas-btn is-primary">
                            <x-ui.icon name="plus" class="h-4 w-4" /> Nuevo proveedor
                        </button>
                    @endcan
                </header>

                <form method="GET" action="{{ route('admin.proveedores.index') }}" class="fichas-filtros" data-fichas-filtros>
                    <label class="fichas-search">
                        <span class="sr-only">Buscar por nombre, DNI, código o teléfono</span>
                        <x-ui.icon name="magnifying-glass" class="h-5 w-5" />
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre, DNI, código o teléfono..." autocomplete="off">
                    </label>

                    <div class="fichas-filtros-fila">
                        <x-forms.select name="estado" label="Estado" :sticky="false" :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" :value="request('estado')" placeholder="Todos" />
                        <x-forms.select name="zona_id" label="Zona" :sticky="false" :options="collect($datos->zonas)->pluck('nombre', 'id')->all()" :value="request('zona_id')" placeholder="Todas" />
                        <x-forms.select name="seguimiento" label="Seguimiento" :sticky="false" :options="['con_seguimiento' => 'Requiere seguimiento', 'sin_seguimiento' => 'Sin seguimiento']" :value="request('seguimiento')" placeholder="Todos" />

                        <div class="fichas-filtros-acciones">
                            <button type="submit" class="fichas-btn is-dark">Filtrar</button>
                            <a href="{{ route('admin.proveedores.index') }}" class="fichas-btn is-outline">Limpiar</a>
                        </div>
                    </div>
                </form>

                @if ($filas->total() === 0)
                    <div class="fichas-vacio">
                        <x-ui.icon name="users" class="h-6 w-6" />
                        <div>
                            <p class="font-semibold text-text">{{ $filtrosActivos ? 'Ningún proveedor coincide con los filtros' : 'No hay proveedores registrados' }}</p>
                            <p>{{ $filtrosActivos ? 'Prueba con otra búsqueda o limpia los filtros.' : 'Registra al primer proveedor para comenzar a acopiar su producción.' }}</p>
                        </div>
                    </div>
                @else
                    <div class="fichas-tabla is-loading" data-fichas-tabla>
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Nombre</th>
                                    <th scope="col">DNI</th>
                                    <th scope="col">Teléfono</th>
                                    <th scope="col">Zona</th>
                                    <th scope="col">Última entrega</th>
                                    <th scope="col" class="is-num">Total (L)</th>
                                    <th scope="col" class="is-estado">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="fichas-skeleton" aria-hidden="true">
                                @for ($i = 0; $i < min(8, max(1, $filas->count())); $i++)
                                    <tr>
                                        @foreach (['is-xl', 'is-md', 'is-md', 'is-md', 'is-md', 'is-sm', 'is-switch'] as $ancho)
                                            <td @class(['is-estado' => $ancho === 'is-switch'])><span class="fichas-bone {{ $ancho }}"></span></td>
                                        @endforeach
                                    </tr>
                                @endfor
                            </tbody>
                            <tbody data-fichas-filas>
                                @forelse ($filas as $fila)
                                    <tr data-fichas-fila data-ficha-id="{{ $fila['id'] }}" data-url-panel="{{ route('admin.proveedores.panel', $fila['id']) }}" tabindex="0" aria-selected="{{ $fila['id'] === $seleccionadoId ? 'true' : 'false' }}" @class(['is-selected' => $fila['id'] === $seleccionadoId])>
                                        <td>
                                            <a href="{{ request()->fullUrlWithQuery(['proveedor' => $fila['id']]) }}" class="fichas-nombre" data-fichas-seleccionar tabindex="-1">{{ $fila['nombre'] }}</a>
                                        </td>
                                        <td class="is-muted">{{ $fila['dni'] }}</td>
                                        <td class="is-muted">{{ $fila['telefono'] ?? '—' }}</td>
                                        <td class="is-muted" data-fila-zona>{{ $fila['zonaNombre'] ?? 'Sin asignar' }}</td>
                                        <td class="is-muted">{{ $fila['ultimaEntrega'] ?? 'Sin entregas' }}</td>
                                        <td class="is-num fichas-litros">{{ number_format($fila['totalLitros'], 2) }}</td>
                                        <td class="is-estado">
                                            <x-ui.estado-switch
                                                :activo="$fila['estado'] === 'ACTIVO'"
                                                :nombre="$fila['nombre']"
                                                entidad="proveedor"
                                                data-registro-id="{{ $fila['id'] }}"
                                                data-url-desactivar="{{ route('admin.proveedores.desactivar', $fila['id']) }}"
                                                data-url-reactivar="{{ route('admin.proveedores.reactivar', $fila['id']) }}"
                                            />
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="fichas-sin-pagina">No hay proveedores en esta página. <a href="{{ $filas->url(1) }}">Ir a la primera página</a></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <noscript><style>.fichas-tabla.is-loading [data-fichas-filas] { display: table-row-group; } .fichas-tabla.is-loading .fichas-skeleton { display: none; }</style></noscript>

                    <x-tables.pagination :paginator="$filas" noun="proveedores" class="fichas-pager" />
                @endif
            </section>

            <aside class="fichas-panel" data-fichas-panel aria-live="polite" aria-label="Detalle del proveedor">
                @if ($panel)
                    @include('admin.proveedores._panel', ['panel' => $panel])
                @else
                    @include('admin.proveedores._panel-vacio')
                @endif
            </aside>
        </div>

        <template data-fichas-panel-vacio>
            @include('admin.proveedores._panel-vacio')
        </template>

        <template data-fichas-panel-skeleton>
            <x-fichas.panel-skeleton />
        </template>
    </div>

    <x-ui.modal id="modal-nuevo-proveedor" title="Nuevo proveedor" :open="$errors->any() && old('_modal') === 'nuevo-proveedor'">
        <form method="POST" action="{{ route('admin.proveedores.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="nuevo-proveedor">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-forms.input name="dni" label="DNI" required />
                <x-forms.input name="nombres" label="Nombres" required />
                <x-forms.input name="apellidos" label="Apellidos" required />
                <x-forms.input name="telefono" label="Teléfono" />
                <x-forms.input name="username" label="Usuario" required />
                <x-forms.input name="password" type="password" label="Contraseña" required />
            </div>

            <p class="text-xs text-text-muted">El proveedor se crea sin zona ni ruta: asígnalo a una ruta desde el módulo <a href="{{ route('admin.zonas.index') }}" class="underline">Zonas</a> cuando corresponda.</p>

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar proveedor</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    @include('admin.proveedores._pagos-modales', ['titulo' => $panel ? 'Pagos de '.$panel->nombreCompleto() : 'Pagos del proveedor'])

    {{--
        Modales del switch de estado (mismo flujo que Acopiadores, admin-estado-switch.js).
        Endpoints: PATCH admin.proveedores.desactivar → DesactivarProveedor y
        PATCH admin.proveedores.reactivar → ReactivarProveedor. La zona de un proveedor se deriva
        de su ruta de acopio, así que al reincorporarlo se elige la zona y una ruta de esa zona.
    --}}
    <x-ui.modal id="modal-desactivar-proveedor" title="Desactivar proveedor">
        <p class="text-sm text-text">¿Deseas desactivar a <strong data-estado-nombre>el proveedor</strong>?</p>
        <p class="mt-2 text-sm text-text-muted">No podrá registrar nuevas entregas. Se conservan sus entregas, compras, controles de calidad, pagos y su historial.</p>
        <form method="POST" action="" class="mt-4 space-y-3">
            @csrf
            @method('PATCH')
            <p class="text-sm text-danger" role="alert" hidden></p>
            <div class="flex justify-end gap-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" variant="danger">Sí, desactivar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-reactivar-proveedor" title="Reactivar proveedor">
        <p class="text-sm text-text">Selecciona la zona a la que se reincorpora <strong data-estado-nombre>el proveedor</strong>.</p>
        <form method="POST" action="" class="mt-4 space-y-3">
            @csrf
            @method('PATCH')
            <x-forms.select name="zona_id" id="reactivar-proveedor-zona" label="Zona" required :sticky="false"
                :options="collect($datos->zonasReincorporacion)->pluck('nombre', 'id')->all()"
                data-mensaje-requerido="Selecciona la zona a la que se reincorpora." />
            <x-forms.select name="ruta_id" id="reactivar-proveedor-ruta" label="Ruta de acopio" required :sticky="false" :options="[]"
                data-depende-de="zona_id" data-mensaje-requerido="Selecciona la ruta de acopio de esa zona.">
                @foreach ($datos->zonasReincorporacion as $zona)
                    @foreach ($zona['rutas'] as $ruta)
                        <option value="{{ $ruta['id'] }}" data-padre="{{ $zona['id'] }}">{{ $ruta['nombre'] }}</option>
                    @endforeach
                @endforeach
            </x-forms.select>
            <p class="text-xs text-warning" data-sin-opciones="ruta_id" hidden>Esta zona no tiene rutas activas. Crea una desde el módulo <a href="{{ route('admin.zonas.index') }}" class="underline">Zonas</a>.</p>
            <p class="text-sm text-danger" role="alert" hidden></p>
            <div class="flex justify-end gap-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Reactivar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
