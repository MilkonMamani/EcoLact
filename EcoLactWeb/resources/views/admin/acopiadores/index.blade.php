@extends('layouts.admin')

@section('title', 'Acopiadores')
@section('body-class', 'admin-fichas-page')
@section('contenedor', 'max-w-[1600px]')

@section('content')
    @php
        $filas = $datos->filas;
        $filtrosActivos = request()->anyFilled(['q', 'estado', 'zona_id']);
        $seleccionadoId = $panel?->id;
    @endphp

    <div class="fichas" data-fichas-page data-fichas-param="acopiador" data-acopiadores-page data-estado-clave="acopiador" data-modal-desactivar="modal-desactivar-acopiador" data-modal-reactivar="modal-reactivar-acopiador">
        <div class="fichas-layout">
            <section class="fichas-lista" aria-label="Listado de acopiadores">
                <header class="fichas-header">
                    <div class="min-w-0">
                        <x-layout.breadcrumbs :items="[['Trabajadores', null], ['Acopiadores', null]]" />
                        <h1 class="fichas-title">Acopiadores</h1>
                        <p class="fichas-subtitle">Personal encargado del registro y recolección de leche.</p>
                    </div>
                    @can('create', \App\Infrastructure\Persistence\Models\AcopiadorModel::class)
                        <button type="button" data-modal-open="modal-nuevo-acopiador" class="fichas-btn is-primary">
                            <x-ui.icon name="plus" class="h-4 w-4" /> Nuevo acopiador
                        </button>
                    @endcan
                </header>

                <form method="GET" action="{{ route('admin.acopiadores.index') }}" class="fichas-filtros" data-fichas-filtros>
                    <label class="fichas-search">
                        <span class="sr-only">Buscar por nombre, apellido, DNI o teléfono</span>
                        <x-ui.icon name="magnifying-glass" class="h-5 w-5" />
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre, apellido, DNI o teléfono..." autocomplete="off">
                    </label>

                    <div class="fichas-filtros-fila">
                        <x-forms.select name="estado" label="Estado" :sticky="false" :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" :value="request('estado')" placeholder="Todos" />
                        <x-forms.select name="zona_id" label="Zona" :sticky="false" :options="collect($datos->zonas)->pluck('nombre', 'id')->all()" :value="request('zona_id')" placeholder="Todas" />

                        <div class="fichas-filtros-acciones">
                            <button type="submit" class="fichas-btn is-dark">Filtrar</button>
                            <a href="{{ route('admin.acopiadores.index') }}" class="fichas-btn is-outline">Limpiar</a>
                        </div>
                    </div>
                </form>

                @if ($filas->total() === 0)
                    <div class="fichas-vacio">
                        <x-ui.icon name="users" class="h-6 w-6" />
                        <div>
                            <p class="font-semibold text-text">{{ $filtrosActivos ? 'Ningún acopiador coincide con los filtros' : 'No hay acopiadores registrados' }}</p>
                            <p>{{ $filtrosActivos ? 'Prueba con otra búsqueda o limpia los filtros.' : 'Registra al primer acopiador para asignarlo a una zona.' }}</p>
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
                                    <th scope="col" class="is-num">Entregas</th>
                                    <th scope="col" class="is-estado">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="fichas-skeleton" aria-hidden="true">
                                @for ($i = 0; $i < min(8, max(1, $filas->count())); $i++)
                                    <tr>
                                        @foreach (['is-xl', 'is-md', 'is-md', 'is-md', 'is-sm', 'is-switch'] as $ancho)
                                            <td @class(['is-estado' => $ancho === 'is-switch'])><span class="fichas-bone {{ $ancho }}"></span></td>
                                        @endforeach
                                    </tr>
                                @endfor
                            </tbody>
                            <tbody data-fichas-filas>
                                @forelse ($filas as $fila)
                                    <tr data-fichas-fila data-ficha-id="{{ $fila['id'] }}" data-url-panel="{{ route('admin.acopiadores.panel', $fila['id']) }}" tabindex="0" aria-selected="{{ $fila['id'] === $seleccionadoId ? 'true' : 'false' }}" @class(['is-selected' => $fila['id'] === $seleccionadoId])>
                                        <td>
                                            <a href="{{ request()->fullUrlWithQuery(['acopiador' => $fila['id']]) }}" class="fichas-nombre" data-fichas-seleccionar tabindex="-1">{{ $fila['nombre'] }}</a>
                                        </td>
                                        <td class="is-muted">{{ $fila['dni'] }}</td>
                                        <td class="is-muted">{{ $fila['telefono'] ?? '—' }}</td>
                                        <td class="is-muted" data-fila-zona>{{ $fila['zonaNombre'] }}</td>
                                        <td class="is-num">{{ $fila['entregas'] }}</td>
                                        <td class="is-estado">
                                            <x-ui.estado-switch
                                                :activo="$fila['estado'] === 'ACTIVO'"
                                                :nombre="$fila['nombre']"
                                                entidad="acopiador"
                                                data-registro-id="{{ $fila['id'] }}"
                                                data-url-desactivar="{{ route('admin.acopiadores.desactivar', $fila['id']) }}"
                                                data-url-reactivar="{{ route('admin.acopiadores.reactivar', $fila['id']) }}"
                                            />
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="fichas-sin-pagina">No hay acopiadores en esta página. <a href="{{ $filas->url(1) }}">Ir a la primera página</a></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <noscript><style>.fichas-tabla.is-loading [data-fichas-filas] { display: table-row-group; } .fichas-tabla.is-loading .fichas-skeleton { display: none; }</style></noscript>

                    <x-tables.pagination :paginator="$filas" noun="acopiadores" class="fichas-pager" />
                @endif
            </section>

            <aside class="fichas-panel" data-fichas-panel aria-live="polite" aria-label="Detalle del acopiador">
                @if ($panel)
                    @include('admin.acopiadores._panel', ['panel' => $panel, 'controlPeriodo' => $controlPeriodo, 'zonasActivas' => $datos->zonasActivas])
                @else
                    @include('admin.acopiadores._panel-vacio')
                @endif
            </aside>
        </div>

        <template data-fichas-panel-vacio>
            @include('admin.acopiadores._panel-vacio')
        </template>

        <template data-fichas-panel-skeleton>
            <x-fichas.panel-skeleton />
        </template>
    </div>

    {{--
        Modales del switch de estado. Son los mismos endpoints que ya existían
        (PATCH admin.acopiadores.desactivar → DesactivarAcopiador y
        PATCH admin.acopiadores.reactivar → ReactivarAcopiador): admin-estado-switch.js les pone la
        acción y el nombre del acopiador pulsado, y los envía por fetch al confirmar.
    --}}
    <x-ui.modal id="modal-desactivar-acopiador" title="Desactivar acopiador">
        <p class="text-sm text-text">¿Deseas desactivar a <strong data-estado-nombre>el acopiador</strong>?</p>
        <p class="mt-2 text-sm text-text-muted">El acopiador dejará de estar disponible para registrar nuevas entregas. Su historial se conservará.</p>
        <form method="POST" action="" class="mt-4 space-y-3" data-desactivar-form>
            @csrf
            @method('PATCH')
            <p class="text-sm text-danger" role="alert" data-desactivar-error hidden></p>
            <div class="flex justify-end gap-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" variant="danger" data-desactivar-confirmar>Sí, desactivar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-reactivar-acopiador" title="Reactivar acopiador">
        <p class="text-sm text-text">Selecciona la zona a la que se reincorpora <strong data-estado-nombre>el acopiador</strong>.</p>
        <form method="POST" action="" class="mt-4 space-y-3" data-reactivar-form>
            @csrf
            @method('PATCH')
            <x-forms.select name="zona_id" id="reactivar-zona-id" label="Zona" required :sticky="false" :options="collect($datos->zonasActivas)->pluck('nombre', 'id')->all()" data-mensaje-requerido="Selecciona la zona a la que se reincorpora." />
            <p class="text-sm text-danger" role="alert" data-reactivar-error hidden></p>
            <div class="flex justify-end gap-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" data-reactivar-confirmar>Reactivar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-nuevo-acopiador" title="Nuevo acopiador" :open="$errors->any() && old('_modal') === 'nuevo-acopiador'">
        <form method="POST" action="{{ route('admin.acopiadores.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="nuevo-acopiador">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-forms.input name="dni" label="DNI" required />
                <x-forms.select name="zona_id" id="nuevo-acopiador-zona" label="Zona" required :options="collect($datos->zonasActivas)->pluck('nombre', 'id')->all()" />
                <x-forms.input name="nombres" label="Nombres" required />
                <x-forms.input name="apellidos" label="Apellidos" required />
                <x-forms.input name="telefono" label="Teléfono" />
                <x-forms.input name="username" label="Usuario" required />
                <x-forms.input name="password" type="password" label="Contraseña" required />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar acopiador</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
