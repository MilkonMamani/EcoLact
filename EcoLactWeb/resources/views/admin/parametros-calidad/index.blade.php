{{--
    PARÁMETROS DE CALIDAD: qué se mide (parámetro) y cuál es el rango aceptado (su regla actual),
    todo desde esta pantalla. Tabla + panel lateral (mismo patrón que Acopiadores/Proveedores): al
    elegir una fila el panel muestra el parámetro y su regla vigente. La tabla solo SELECCIONA (sin
    lápiz ni menú de acciones); editar se hace únicamente desde el panel, cada botón en su sección:
    Información general → Editar parámetro, Regla de evaluación → Editar/Configurar regla.
    ?parametro=ID mantiene la selección. El código es el identificador interno
    y estable: no se muestra en la tabla ni se edita.
--}}
@extends('layouts.admin')

@section('title', 'Parámetros de calidad')
@section('body-class', 'admin-fichas-page')
@section('contenedor', 'max-w-[1600px]')

@php
    $seleccionadoId = $panel['id'] ?? null;
    $puedeGestionar = auth()->user()->can('create', \App\Infrastructure\Persistence\Models\ParametroCalidadModel::class);
    $opcionesEstado = ['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo'];
@endphp

@section('content')
    <div class="fichas parametros-calidad" @if (count($parametros) > 0) data-fichas-page data-fichas-param="parametro" @endif data-parametros-calidad-page>
        <div @class(['fichas-layout' => count($parametros) > 0])>
            <section aria-label="Listado de parámetros de calidad">
                <header class="fichas-header">
                    <div class="min-w-0">
                        <x-layout.breadcrumbs :items="[['Configuración', null], ['Parámetros de calidad', null]]" />
                        <h1 class="fichas-title">Parámetros de calidad</h1>
                        <p class="fichas-subtitle">Define qué características se evalúan en cada análisis de leche.</p>
                    </div>
                    @if ($puedeGestionar)
                        <button type="button" class="fichas-btn is-primary" data-modal-open="modal-nuevo-parametro">
                            <x-ui.icon name="plus" class="h-4 w-4" /> Nuevo parámetro
                        </button>
                    @endif
                </header>

                @if (count($parametros) === 0)
                    <x-ui.empty-state class="mt-6 bg-surface" title="No hay parámetros de calidad." description="Crea un parámetro para definir qué características se evalúan en el análisis de leche.">
                        @if ($puedeGestionar)
                            <x-slot:action>
                                <button type="button" class="fichas-btn is-primary" data-modal-open="modal-nuevo-parametro">
                                    <x-ui.icon name="plus" class="h-4 w-4" /> Nuevo parámetro
                                </button>
                            </x-slot:action>
                        @endif
                    </x-ui.empty-state>
                @else
                    <div class="fichas-tabla is-loading" data-fichas-tabla>
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Parámetro</th>
                                    <th scope="col" class="hidden sm:table-cell">Unidad</th>
                                    <th scope="col" class="is-centro">Estado</th>
                                    <th scope="col" class="hidden sm:table-cell">Regla actual</th>
                                    <th scope="col" class="hidden lg:table-cell">Última actualización</th>
                                    <th scope="col" class="is-accion sm:hidden"><span class="sr-only">Ver detalle</span></th>
                                </tr>
                            </thead>
                            <tbody class="fichas-skeleton" aria-hidden="true">
                                @for ($i = 0; $i < min(8, count($parametros)); $i++)
                                    <tr>
                                        <td><span class="fichas-bone is-lg"></span><span class="fichas-bone is-xl mt-2"></span></td>
                                        <td class="hidden sm:table-cell"><span class="fichas-bone is-xs"></span></td>
                                        <td class="is-centro"><span class="fichas-bone is-xs mx-auto"></span></td>
                                        <td class="hidden sm:table-cell"><span class="fichas-bone is-md"></span></td>
                                        <td class="hidden lg:table-cell"><span class="fichas-bone is-md"></span></td>
                                        <td class="sm:hidden"></td>
                                    </tr>
                                @endfor
                            </tbody>
                            <tbody data-fichas-filas>
                                @foreach ($parametros as $parametro)
                                    @php
                                        $activo = $parametro['estado'] === 'ACTIVO';
                                        $rango = $parametro['reglaActual']['rango'] ?? null;
                                    @endphp
                                    <tr data-fichas-fila data-ficha-id="{{ $parametro['id'] }}" data-url-panel="{{ route('admin.parametros-calidad.panel', $parametro['id']) }}" tabindex="0" title="Ver detalle" aria-selected="{{ $parametro['id'] === $seleccionadoId ? 'true' : 'false' }}" @class(['is-selected' => $parametro['id'] === $seleccionadoId])>
                                        <td class="is-parametro">
                                            <a href="{{ route('admin.parametros-calidad.index', ['parametro' => $parametro['id']]) }}" class="fichas-nombre" data-fichas-seleccionar tabindex="-1">{{ $parametro['nombre'] }}</a>
                                            @if ($parametro['descripcion'])
                                                <p class="parametros-descripcion">{{ $parametro['descripcion'] }}</p>
                                            @endif
                                            <p class="parametros-meta sm:hidden">{{ $parametro['unidad'] ?? 'Sin unidad' }} · {{ $rango ?? 'Sin configurar' }}</p>
                                        </td>
                                        <td class="is-muted hidden sm:table-cell">{{ $parametro['unidad'] ?? '—' }}</td>
                                        <td class="is-centro">
                                            <x-ui.badge compact :status="$parametro['estado']">{{ $activo ? 'Activo' : 'Inactivo' }}</x-ui.badge>
                                        </td>
                                        <td @class(['hidden sm:table-cell', 'parametros-rango' => $rango, 'is-muted' => ! $rango]) data-regla-actual>{{ $rango ?? 'Sin configurar' }}</td>
                                        <td class="is-muted hidden lg:table-cell">{{ $parametro['actualizado'] ?? '—' }}</td>
                                        <td class="is-accion sm:hidden" aria-hidden="true">
                                            <x-ui.icon name="chevron-right" class="parametros-chevron h-4 w-4" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <noscript><style>.fichas-tabla.is-loading [data-fichas-filas] { display: table-row-group; } .fichas-tabla.is-loading .fichas-skeleton { display: none; }</style></noscript>
                @endif
            </section>

            @if (count($parametros) > 0)
                <aside class="fichas-panel" data-fichas-panel aria-live="polite" aria-label="Detalle del parámetro de calidad">
                    @if ($panel)
                        @include('admin.parametros-calidad._panel', ['panel' => $panel])
                    @else
                        @include('admin.parametros-calidad._panel-vacio')
                    @endif
                </aside>
            @endif
        </div>

        <template data-fichas-panel-vacio>
            @include('admin.parametros-calidad._panel-vacio')
        </template>

        <template data-fichas-panel-skeleton>
            <div class="fichas-panel-cargando" aria-busy="true" aria-label="Cargando">
                <div class="fichas-panel-head">
                    <div class="grid flex-1 gap-2.5">
                        <span class="fichas-bone is-xl"></span>
                        <span class="fichas-bone is-lg"></span>
                    </div>
                </div>
                <div class="fichas-panel-cuerpo grid gap-4">
                    @foreach (['is-md', 'is-xl', 'is-lg', 'is-xl', 'is-md', 'is-xl', 'is-lg'] as $ancho)
                        <span class="fichas-bone {{ $ancho }}"></span>
                    @endforeach
                </div>
            </div>
        </template>
    </div>

    @if ($puedeGestionar)
        @php
            $stickyNuevo = old('_modal') === 'nuevo-parametro';
        @endphp
        <x-ui.modal id="modal-nuevo-parametro" size="form" title="Nuevo parámetro de calidad" subtitle="Define el parámetro y la unidad de medida." :open="$stickyNuevo && $errors->any()">
            <form method="POST" action="{{ route('admin.parametros-calidad.store') }}" data-parametro-form="nuevo">
                @csrf
                <input type="hidden" name="_modal" value="nuevo-parametro">
                <div class="parametros-campos">
                    <x-forms.input name="nombre" id="nuevo-parametro-nombre" label="Nombre" required maxlength="100" :sticky="$stickyNuevo" placeholder="Ej. Densidad" autocomplete="off" data-codigo-origen />
                    <div>
                        <x-forms.input name="codigo" id="nuevo-parametro-codigo" label="Código" required maxlength="40" pattern="[A-Z0-9_]+" :sticky="$stickyNuevo" placeholder="DENSIDAD" autocomplete="off" class="font-mono uppercase" data-codigo-destino title="Solo letras mayúsculas, números y guion bajo" />
                        <p class="parametros-ayuda">Se genera a partir del nombre. Solo mayúsculas, números y _. No se podrá cambiar después.</p>
                    </div>
                    <x-forms.input name="unidad" id="nuevo-parametro-unidad" label="Unidad" required maxlength="30" :sticky="$stickyNuevo" placeholder="Ej. g/ml, %, °C" list="parametros-unidades" autocomplete="off" />
                    <x-forms.select name="estado" id="nuevo-parametro-estado" label="Estado" required :sticky="$stickyNuevo" :placeholder="null" :options="$opcionesEstado" value="ACTIVO" />
                    <div class="is-ancho">
                        <x-forms.textarea name="descripcion" id="nuevo-parametro-descripcion" label="Descripción" rows="2" maxlength="200" :sticky="$stickyNuevo" placeholder="Ej. Mide la densidad de la leche." />
                    </div>
                </div>
                <div class="parametros-modal-pie">
                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                    <x-ui.button type="submit">Guardar parámetro</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        @foreach ($parametros as $parametro)
            @php
                $claveModal = 'editar-parametro-'.$parametro['id'];
                $stickyEditar = old('_modal') === $claveModal;
            @endphp
            <x-ui.modal :id="'modal-'.$claveModal" size="form" title="Editar parámetro de calidad" :subtitle="$parametro['nombre'].' · Código: '.$parametro['codigo']" :open="$stickyEditar && ($errors->any() || session('error'))">
                <form method="POST" action="{{ route('admin.parametros-calidad.update', $parametro['id']) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="_modal" value="{{ $claveModal }}">
                    @if ($stickyEditar && session('error'))
                        <p class="parametros-error" role="alert">{{ session('error') }}</p>
                    @endif
                    <div class="parametros-campos">
                        <x-forms.input name="nombre" :id="$claveModal.'-nombre'" label="Nombre" required maxlength="100" :sticky="$stickyEditar" :value="$parametro['nombre']" />
                        <div>
                            <x-forms.input name="codigo_bloqueado" :id="$claveModal.'-codigo'" label="Código" :sticky="false" :value="$parametro['codigo']" readonly disabled class="font-mono" />
                            <p class="parametros-ayuda">El código es el identificador interno del parámetro y no se modifica.</p>
                        </div>
                        <x-forms.input name="unidad" :id="$claveModal.'-unidad'" label="Unidad" required maxlength="30" :sticky="$stickyEditar" :value="$parametro['unidad']" list="parametros-unidades" autocomplete="off" />
                        <x-forms.select name="estado" :id="$claveModal.'-estado'" label="Estado" required :sticky="$stickyEditar" :placeholder="null" :options="$opcionesEstado" :value="$parametro['estado']" />
                        <div class="is-ancho">
                            <x-forms.textarea name="descripcion" :id="$claveModal.'-descripcion'" label="Descripción" rows="2" maxlength="200" :sticky="$stickyEditar" :value="$parametro['descripcion']" />
                        </div>
                    </div>
                    <div class="parametros-modal-pie">
                        <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                        <x-ui.button type="submit">Guardar cambios</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        @endforeach

        {{-- Sugerencias de unidad: la unidad sigue siendo texto libre (no hay catálogo en el backend). --}}
        <datalist id="parametros-unidades">
            @foreach (collect($parametros)->pluck('unidad')->filter()->merge(['g/ml', '%', '°C', '°D', 'pH'])->unique()->values() as $unidad)
                <option value="{{ $unidad }}"></option>
            @endforeach
        </datalist>
    @endif
@endsection
