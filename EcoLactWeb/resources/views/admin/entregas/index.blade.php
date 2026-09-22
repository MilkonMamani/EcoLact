@extends('layouts.admin')

@section('title', 'Acopio')

@section('content')
    @if (isset($datos))
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat-card label="Litros registrados" :value="number_format($datos->kpis['litrosRegistrados'], 2).' L'" hint="Según filtros aplicados" />
            <x-ui.stat-card label="Entregas" :value="$datos->kpis['entregas']" />
            <x-ui.stat-card label="Pendientes de análisis" :value="$datos->kpis['pendientesAnalisis']" accent="warning" />
            <x-ui.stat-card label="Analizadas" :value="$datos->kpis['analizadas']" accent="success" />
        </div>

        <x-ui.card title="Acopio" subtitle="Registro y seguimiento de entregas de leche.">
            <x-slot:actions>
                @can('create', \App\Infrastructure\Persistence\Models\EntregaModel::class)
                    <x-ui.button data-modal-open="modal-nueva-entrega" size="sm" as="button" type="button">+ Registrar entrega</x-ui.button>
                @endcan
            </x-slot:actions>

            <form method="GET" class="mb-4 space-y-3">
                <x-forms.input name="q" label="Buscar proveedor, DNI o código de entrega" value="{{ request('q') }}" />

                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-44">
                        <x-forms.select name="fecha" label="Fecha" :options="[
                            'hoy' => 'Hoy',
                            'ayer' => 'Ayer',
                            'esta_semana' => 'Esta semana',
                            'semana_anterior' => 'Semana anterior',
                            'este_mes' => 'Este mes',
                            'personalizado' => 'Personalizado',
                        ]" :value="request('fecha')" placeholder="Todas" />
                    </div>

                    @if (request('fecha') === 'personalizado')
                        <div class="w-40">
                            <x-forms.input name="desde" type="date" label="Desde" value="{{ request('desde') }}" />
                        </div>
                        <div class="w-40">
                            <x-forms.input name="hasta" type="date" label="Hasta" value="{{ request('hasta') }}" />
                        </div>
                    @endif

                    <div class="w-44">
                        <x-forms.select name="zona_id" label="Zona" :options="collect($datos->zonas)->pluck('nombre', 'id')->all()" :value="request('zona_id')" placeholder="Todas" />
                    </div>

                    <div class="w-48">
                        <x-forms.select name="acopiador_id" label="Acopiador" :options="collect($datos->acopiadores)->pluck('nombre', 'id')->all()" :value="request('acopiador_id')" placeholder="Todos" />
                    </div>

                    <div class="w-44">
                        <x-forms.select name="estado" label="Estado" :options="['PENDIENTE_ANALISIS' => 'Pendiente de análisis', 'ANALIZADA' => 'Analizada']" :value="request('estado')" placeholder="Todos" />
                    </div>

                    <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
                    @if (request()->anyFilled(['q', 'fecha', 'zona_id', 'acopiador_id', 'estado']))
                        <x-ui.button :href="route('admin.entregas.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
                    @endif
                </div>
            </form>

            @if (count($datos->filas) === 0)
                <x-ui.empty-state title="No hay entregas registradas" description="Las entregas registradas por los acopiadores aparecerán aquí." />
            @else
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">Fecha y hora</th>
                            <th class="py-2 pr-4">Proveedor</th>
                            <th class="py-2 pr-4">Zona</th>
                            <th class="py-2 pr-4">Acopiador</th>
                            <th class="py-2 pr-4">Litros</th>
                            <th class="py-2 pr-4">Identificación</th>
                            <th class="py-2 pr-4">Estado</th>
                            <th class="py-2 pl-4 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datos->filas as $fila)
                            <tr class="border-b border-text-muted/5 last:border-0">
                                <td class="py-3 pr-4 whitespace-nowrap text-text">{{ $fila['fecha'] }}</td>
                                <td class="py-3 pr-4 text-text">
                                    {{ $fila['proveedorNombre'] }}
                                    <span class="block text-xs text-text-muted">DNI: {{ $fila['proveedorDni'] }}</span>
                                </td>
                                <td class="py-3 pr-4 text-text-muted">{{ $fila['zonaNombre'] }}</td>
                                <td class="py-3 pr-4 text-text-muted">{{ $fila['acopiadorNombre'] }}</td>
                                <td class="py-3 pr-4 font-medium text-text">{{ number_format($fila['litros'], 2) }} L</td>
                                <td class="py-3 pr-4 text-text-muted">{{ $fila['metodoIdentificacion'] }}</td>
                                <td class="py-3 pr-4"><x-ui.badge :status="$fila['estado']" /></td>
                                <td class="py-3 pl-4">
                                    <x-tables.actions>
                                        <x-ui.action-button type="view" label="Ver entrega" :href="route('admin.entregas.show', $fila['id'])" />
                                    </x-tables.actions>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>
            @endif
        </x-ui.card>
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
