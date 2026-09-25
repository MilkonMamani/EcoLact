@extends('layouts.admin')

@section('title', 'Acopio')

@if (isset($datos))
    @section('body-class', 'admin-acopio-page')
    @section('contenedor', 'max-w-[1600px]')
@endif

@section('content')
    @if (isset($datos))
        @php
            $filas = $datos->filas;
            $filtrosActivos = request()->anyFilled(['q', 'fecha', 'zona_id', 'acopiador_id', 'estado']);
            $etiquetasEstado = ['PENDIENTE_ANALISIS' => ['Pendiente de análisis', 'clock'], 'ANALIZADA' => ['Analizada', 'check'], 'ANULADA' => ['Anulada', 'x-circle']];
        @endphp

        <div class="acopio" data-acopio-page>
            <header class="acopio-header">
                <div class="min-w-0">
                    <nav aria-label="Ruta de navegación" class="acopio-breadcrumb">
                    </nav>
                    <h1 class="acopio-title">Acopio</h1>
                    <p class="acopio-subtitle">Registro y seguimiento de entregas de leche.</p>
                </div>
                @can('create', \App\Infrastructure\Persistence\Models\EntregaModel::class)
                    <button type="button" data-modal-open="modal-nueva-entrega" class="acopio-btn is-primary">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Registrar entrega
                    </button>
                @endcan
            </header>

            <form method="GET" action="{{ route('admin.entregas.index') }}" class="acopio-filtros" data-acopio-filtros>
                <label class="acopio-search">
                    <span class="sr-only">Buscar proveedor, DNI o código de entrega</span>
                    <x-ui.icon name="magnifying-glass" class="h-5 w-5" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar proveedor, DNI o código de entrega…" autocomplete="off">
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
                    <x-forms.select name="acopiador_id" label="Acopiador" :sticky="false" :options="collect($datos->acopiadores)->pluck('nombre', 'id')->all()" :value="request('acopiador_id')" placeholder="Todos" />
                    <x-forms.select name="estado" label="Estado" :sticky="false" :options="['PENDIENTE_ANALISIS' => 'Pendiente de análisis', 'ANALIZADA' => 'Analizada']" :value="request('estado')" placeholder="Todos" />

                    <div class="acopio-filtros-acciones">
                        <button type="submit" class="acopio-btn is-dark">Filtrar</button>
                        <a href="{{ route('admin.entregas.index') }}" class="acopio-btn is-outline">Limpiar</a>
                    </div>
                </div>
            </form>

            @if ($filas->total() === 0)
                <div class="acopio-vacio">
                    <x-ui.icon name="milk-jar" class="h-6 w-6" />
                    <div>
                        <p class="font-semibold text-text">{{ $filtrosActivos ? 'Ninguna entrega coincide con los filtros' : 'No hay entregas registradas' }}</p>
                        <p>{{ $filtrosActivos ? 'Prueba con otra búsqueda o limpia los filtros.' : 'Las entregas registradas por los acopiadores aparecerán aquí.' }}</p>
                    </div>
                </div>
            @else
                <div class="acopio-tabla is-loading" data-acopio-tabla>
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Fecha y hora</th>
                                <th scope="col">Proveedor</th>
                                <th scope="col">Zona</th>
                                <th scope="col">Acopiador</th>
                                <th scope="col" class="is-num">Litros</th>
                                <th scope="col">Identificación</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="is-accion">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="acopio-skeleton" aria-hidden="true">
                            @for ($i = 0; $i < min(8, max(1, $filas->count())); $i++)
                                <tr>
                                    @foreach (['is-lg', 'is-xl', 'is-md', 'is-lg', 'is-sm', 'is-xs', 'is-md', 'is-dot'] as $ancho)
                                        <td><span class="acopio-bone {{ $ancho }}"></span></td>
                                    @endforeach
                                </tr>
                            @endfor
                        </tbody>
                        <tbody data-acopio-filas>
                            @forelse ($filas as $fila)
                                @php [$etiquetaEstado, $iconoEstado] = $etiquetasEstado[$fila['estado']] ?? [str($fila['estado'])->replace('_', ' ')->lower()->ucfirst()->toString(), null]; @endphp
                                <tr data-acopio-fila>
                                    <td class="whitespace-nowrap">{{ $fila['fecha'] }}</td>
                                    <td>
                                        <span class="acopio-proveedor">{{ $fila['proveedorNombre'] }}</span>
                                        <span class="acopio-dni">DNI: {{ $fila['proveedorDni'] }}</span>
                                    </td>
                                    <td class="is-muted">{{ $fila['zonaNombre'] }}</td>
                                    <td class="is-muted">{{ $fila['acopiadorNombre'] }}</td>
                                    <td class="is-num acopio-litros" data-entrega-litros="{{ $fila['id'] }}">{{ number_format($fila['litros'], 2) }} L</td>
                                    <td class="is-muted">{{ $fila['metodoIdentificacion'] }}</td>
                                    <td><x-ui.badge :status="$fila['estado']" :icon="$iconoEstado">{{ $etiquetaEstado }}</x-ui.badge></td>
                                    <td class="is-accion">
                                        <a href="{{ route('admin.entregas.show', $fila['id']) }}" class="acopio-ver" aria-label="Ver entrega" title="Ver entrega" data-entrega-ver data-url-detalle="{{ route('admin.entregas.detalle', $fila['id']) }}">
                                            <x-ui.icon name="eye" class="h-4 w-4" />
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="acopio-sin-pagina">No hay entregas en esta página. <a href="{{ $filas->url(1) }}">Ir a la primera página</a></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <noscript><style>.acopio-tabla.is-loading [data-acopio-filas] { display: table-row-group; } .acopio-tabla.is-loading .acopio-skeleton { display: none; }</style></noscript>

                <x-tables.pagination :paginator="$filas" noun="entregas" class="acopio-pager" />
            @endif

            {{-- Modal "Ver entrega": el contenido (resources/views/admin/entregas/_detalle.blade.php) lo carga admin-acopio.js. --}}
            <div id="modal-ver-entrega" data-modal class="fixed inset-0 z-50 items-center justify-center bg-text/40 p-4" role="dialog" aria-modal="true" aria-label="Ver entrega">
                <div class="acopio-detalle-panel" data-entrega-detalle-contenido></div>
            </div>
        </div>
    @else
        {{-- Rama heredada para los roles ACOPIADOR/PROVEEDOR (index() con recorte propio, sin KPIs/filtros). --}}
        <x-ui.card title="Entregas" subtitle="Historial de acopio de leche.">
            <x-slot:actions>
                @can('create', \App\Infrastructure\Persistence\Models\EntregaModel::class)
                    <x-ui.button data-modal-open="modal-nueva-entrega" size="sm" as="button" type="button">Registrar entrega</x-ui.button>
                @endcan
            </x-slot:actions>

            @if (count($entregas) === 0)
                <x-ui.empty-state title="No hay entregas registradas" description="Las entregas registradas por los acopiadores aparecerán aquí." />
            @else
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">Fecha</th>
                            <th class="py-2 pr-4">Proveedor</th>
                            <th class="py-2 pr-4">Zona</th>
                            <th class="py-2 pr-4">Litros</th>
                            <th class="py-2 pr-4">Método</th>
                            <th class="py-2 pr-4">Estado</th>
                            <th class="py-2 pl-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entregas as $entrega)
                            <tr class="border-b border-text-muted/5 last:border-0">
                                <td class="py-3 pr-4 text-text">{{ $entrega->fechaHora()->format('d/m/Y H:i') }}</td>
                                <td class="py-3 pr-4 text-text-muted">Proveedor #{{ $entrega->proveedorId() }}</td>
                                <td class="py-3 pr-4 text-text-muted">Zona #{{ $entrega->zonaId() }}</td>
                                <td class="py-3 pr-4 font-medium text-text">{{ number_format($entrega->litros(), 2) }} L</td>
                                <td class="py-3 pr-4 text-text-muted">{{ $entrega->metodoIdentificacion() }}</td>
                                <td class="py-3 pr-4"><x-ui.badge :status="$entrega->estado()->value" /></td>
                                <td class="py-3 pl-4">
                                    <x-tables.actions>
                                        <x-ui.action-button type="view" label="Ver entrega" :href="route('admin.entregas.show', $entrega->id())" />
                                    </x-tables.actions>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>
            @endif
        </x-ui.card>
    @endif

    <x-ui.modal id="modal-nueva-entrega" title="Registrar entrega">
        <form method="POST" action="{{ route('admin.entregas.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="metodo_identificacion" value="DNI">
            <div class="relative" data-provider-search>
                <x-forms.input name="identificador" label="DNI del proveedor" type="text" inputmode="numeric" autocomplete="off" maxlength="8" placeholder="Escribe el DNI" required data-provider-search-input data-provider-search-url="{{ route('admin.entregas.buscar-proveedor') }}" />
                <div data-provider-search-results class="absolute inset-x-0 top-full z-20 mt-1 hidden overflow-hidden rounded-lg border border-text-muted/15 bg-surface shadow-lg"></div>
            </div>
            <div data-provider-selected class="hidden rounded-lg border border-primary/20 bg-primary-soft px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-text-muted">Proveedor encontrado</p>
                <p data-provider-name class="mt-1 font-semibold text-text"></p>
                <p data-provider-zone class="text-xs text-text-muted"></p>
            </div>
            <x-forms.input name="litros" type="number" label="Litros a registrar" step="0.01" min="0.01" required />
            <x-forms.input name="fecha_hora" type="datetime-local" label="Fecha y hora (opcional)" />
            <x-forms.textarea name="observacion" label="Observación" :rows="3" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Registrar entrega</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
