{{--
    Pantalla "listado + panel" de analistas de calidad, encargados de planta y personal de
    ventas: la misma de Acopiadores (clases fichas-*, admin-fichas.js y admin-estado-switch.js),
    sin filtro de zona porque estos roles no la tienen. Los textos y rutas vienen de
    PantallaFichasPersonal::config().

    @var array $config
    @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $filas
    @var ?\App\Application\Trabajador\FichaPersonalPanelData $panel
--}}
@extends('layouts.admin')

@section('title', $config['titulo'])
@section('body-class', 'admin-fichas-page')
@section('contenedor', 'max-w-[1600px]')

@section('content')
    @php
        $rutas = $config['rutas'];
        $filtrosActivos = request()->anyFilled(['q', 'estado']);
        $seleccionadoId = $panel?->id;
    @endphp

    <div class="fichas" data-fichas-page data-fichas-param="{{ $config['param'] }}" data-estado-clave="ficha" data-modal-desactivar="modal-desactivar-ficha" data-modal-reactivar="modal-reactivar-ficha">
        <div class="fichas-layout">
            <section class="fichas-lista" aria-label="Listado de {{ $config['plural'] }}">
                <header class="fichas-header">
                    <div class="min-w-0">
                        <x-layout.breadcrumbs :items="[['Trabajadores', null], [$config['titulo'], null]]" />
                        <h1 class="fichas-title">{{ $config['titulo'] }}</h1>
                        <p class="fichas-subtitle">{{ $config['subtitulo'] }}</p>
                    </div>
                    @can('create', $config['modelo'])
                        <button type="button" data-modal-open="modal-nueva-ficha" class="fichas-btn is-primary">
                            <x-ui.icon name="plus" class="h-4 w-4" /> {{ $config['nuevo'] }}
                        </button>
                    @endcan
                </header>

                <form method="GET" action="{{ route($rutas.'.index') }}" class="fichas-filtros" data-fichas-filtros>
                    <label class="fichas-search">
                        <span class="sr-only">Buscar por nombre, apellido, DNI o teléfono</span>
                        <x-ui.icon name="magnifying-glass" class="h-5 w-5" />
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre, apellido, DNI o teléfono..." autocomplete="off">
                    </label>

                    <div class="fichas-filtros-fila">
                        <x-forms.select name="estado" label="Estado" :sticky="false" :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" :value="request('estado')" placeholder="Todos" />

                        <div class="fichas-filtros-acciones">
                            <button type="submit" class="fichas-btn is-dark">Filtrar</button>
                            <a href="{{ route($rutas.'.index') }}" class="fichas-btn is-outline">Limpiar</a>
                        </div>
                    </div>
                </form>

                @if ($filas->total() === 0)
                    <div class="fichas-vacio">
                        <x-ui.icon name="users" class="h-6 w-6" />
                        <div>
                            <p class="font-semibold text-text">{{ $filtrosActivos ? 'Ningún '.$config['entidad'].' coincide con los filtros' : 'No hay '.$config['plural'].' registrados' }}</p>
                            <p>{{ $filtrosActivos ? 'Prueba con otra búsqueda o limpia los filtros.' : 'Registra al primer '.$config['entidad'].'.' }}</p>
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
                                    <th scope="col" class="is-num">{{ $config['columna'] }}</th>
                                    <th scope="col" class="is-estado">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="fichas-skeleton" aria-hidden="true">
                                @for ($i = 0; $i < min(8, max(1, $filas->count())); $i++)
                                    <tr>
                                        @foreach (['is-xl', 'is-md', 'is-md', 'is-sm', 'is-switch'] as $ancho)
                                            <td @class(['is-estado' => $ancho === 'is-switch'])><span class="fichas-bone {{ $ancho }}"></span></td>
                                        @endforeach
                                    </tr>
                                @endfor
                            </tbody>
                            <tbody data-fichas-filas>
                                @forelse ($filas as $fila)
                                    <tr data-fichas-fila data-ficha-id="{{ $fila['id'] }}" data-url-panel="{{ route($rutas.'.panel', $fila['id']) }}" tabindex="0" aria-selected="{{ $fila['id'] === $seleccionadoId ? 'true' : 'false' }}" @class(['is-selected' => $fila['id'] === $seleccionadoId])>
                                        <td>
                                            <a href="{{ request()->fullUrlWithQuery([$config['param'] => $fila['id']]) }}" class="fichas-nombre" data-fichas-seleccionar tabindex="-1">{{ $fila['nombre'] }}</a>
                                        </td>
                                        <td class="is-muted">{{ $fila['dni'] }}</td>
                                        <td class="is-muted">{{ $fila['telefono'] ?? '—' }}</td>
                                        <td class="is-num">{{ $fila['actividad'] }}</td>
                                        <td class="is-estado">
                                            <x-ui.estado-switch
                                                :activo="$fila['estado'] === 'ACTIVO'"
                                                :nombre="$fila['nombre']"
                                                :entidad="$config['entidad']"
                                                data-registro-id="{{ $fila['id'] }}"
                                                data-url-desactivar="{{ route($rutas.'.desactivar', $fila['id']) }}"
                                                data-url-reactivar="{{ route($rutas.'.reactivar', $fila['id']) }}"
                                            />
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="fichas-sin-pagina">No hay {{ $config['plural'] }} en esta página. <a href="{{ $filas->url(1) }}">Ir a la primera página</a></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <noscript><style>.fichas-tabla.is-loading [data-fichas-filas] { display: table-row-group; } .fichas-tabla.is-loading .fichas-skeleton { display: none; }</style></noscript>

                    <x-tables.pagination :paginator="$filas" :noun="$config['plural']" class="fichas-pager" />
                @endif
            </section>

            <aside class="fichas-panel" data-fichas-panel aria-live="polite" aria-label="Detalle del {{ $config['entidad'] }}">
                @if ($panel)
                    @include('admin.fichas-personal._panel', ['panel' => $panel, 'controlPeriodo' => $controlPeriodo, 'config' => $config])
                @else
                    <x-fichas.panel-vacio :entidad="$config['entidad']">{{ $config['vacio'] }}</x-fichas.panel-vacio>
                @endif
            </aside>
        </div>

        <template data-fichas-panel-vacio>
            <x-fichas.panel-vacio :entidad="$config['entidad']">{{ $config['vacio'] }}</x-fichas.panel-vacio>
        </template>

        <template data-fichas-panel-skeleton>
            <x-fichas.panel-skeleton />
        </template>
    </div>

    {{--
        Modales del switch de estado: los mismos endpoints de siempre (PATCH <rutas>.desactivar
        y <rutas>.reactivar, que llaman a los Use Cases Desactivar/Reactivar de cada módulo);
        admin-estado-switch.js les pone la acción y el nombre pulsado y los envía por fetch al
        confirmar. Sin zona: reactivar solo pide confirmación.
    --}}
    <x-ui.modal id="modal-desactivar-ficha" :title="'Desactivar '.$config['entidad']">
        <p class="text-sm text-text">¿Deseas desactivar a <strong data-estado-nombre>el {{ $config['entidad'] }}</strong>?</p>
        <p class="mt-2 text-sm text-text-muted">Solo podrá consultar lo que hizo antes: no podrá registrar ni modificar nada. Su historial se conservará.</p>
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

    <x-ui.modal id="modal-reactivar-ficha" :title="'Reactivar '.$config['entidad']">
        <p class="text-sm text-text">¿Deseas reactivar a <strong data-estado-nombre>el {{ $config['entidad'] }}</strong>?</p>
        <p class="mt-2 text-sm text-text-muted">Volverá a operar con normalidad.</p>
        <form method="POST" action="" class="mt-4 space-y-3" data-reactivar-form>
            @csrf
            @method('PATCH')
            <p class="text-sm text-danger" role="alert" data-reactivar-error hidden></p>
            <div class="flex justify-end gap-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" data-reactivar-confirmar>Reactivar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-nueva-ficha" :title="$config['nuevo']" :open="$errors->any() && old('_modal') === 'nueva-ficha'">
        <form method="POST" action="{{ route($rutas.'.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="nueva-ficha">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-forms.input name="dni" label="DNI" required />
                <x-forms.input name="nombres" label="Nombres" required />
                <x-forms.input name="apellidos" label="Apellidos" required />
                <x-forms.input name="telefono" label="Teléfono" />
                <x-forms.input name="username" label="Usuario" required />
                <x-forms.input name="password" type="password" label="Contraseña" required />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
