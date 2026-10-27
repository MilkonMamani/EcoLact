@extends('layouts.calidad')

@section('title', 'Entregas')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Litros registrados" :value="number_format($datos->kpis['litrosRegistrados'], 2).' L'" hint="Según filtros aplicados" />
        <x-ui.stat-card label="Entregas" :value="$datos->kpis['entregas']" />
        <x-ui.stat-card label="Muestras pendientes" :value="$kpisMuestreo['pendientes']" accent="warning" hint="Solo las seleccionadas se analizan" />
        <x-ui.stat-card label="Analizadas" :value="$datos->kpis['analizadas']" accent="success" />
    </div>

    <x-ui.card title="Entregas" subtitle="Elige qué entregas serán muestra de calidad. Las no seleccionadas no se analizan.">
        <x-slot:actions>
            @can('create', \App\Infrastructure\Persistence\Models\EntregaModel::class)
                <x-ui.button data-modal-open="modal-nueva-muestra" size="sm" as="button" type="button">Muestreo directo</x-ui.button>
            @endcan
        </x-slot:actions>

        <form method="GET" class="mb-4 space-y-3">
            <x-forms.input name="q" label="Buscar por proveedor, DNI o código de entrega" value="{{ request('q') }}" />

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

                <div class="w-44">
                    <x-forms.select name="estado" label="Estado" :options="['PENDIENTE_ANALISIS' => 'Pendiente de análisis', 'ANALIZADA' => 'Analizada']" :value="request('estado')" placeholder="Todos" />
                </div>

                <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
                @if (request()->anyFilled(['q', 'fecha', 'zona_id', 'estado']))
                    <x-ui.button :href="route('calidad.entregas.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
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
                        <th class="py-2 pr-4">Litros</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pr-4">Resultado</th>
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
                            <td class="py-3 pr-4 font-medium text-text">{{ number_format($fila['litros'], 2) }} L</td>
                            <td class="py-3 pr-4"><x-ui.badge compact :status="$fila['estado']" /></td>
                            <td class="py-3 pr-4">
                                @if ($fila['resultado'])
                                    <x-ui.badge compact :status="$fila['resultado']" />
                                @else
                                    <span class="text-xs text-text-muted">—</span>
                                @endif
                            </td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    @php($muestra = $muestras[$fila['id']] ?? null)
                                    @if (in_array($muestra, ['SELECCIONADA', 'EN_ANALISIS'], true))
                                        <x-ui.action-button type="analyze" label="Analizar muestra" :href="route('calidad.entregas.analisis.index', $fila['id'])" />
                                    @elseif ($muestra === null && $fila['estado'] === 'PENDIENTE_ANALISIS')
                                        <form method="POST" action="{{ route('calidad.muestreo.store') }}">
                                            @csrf
                                            <input type="hidden" name="entrega_id" value="{{ $fila['id'] }}">
                                            <input type="hidden" name="metodo_seleccion" value="MANUAL">
                                            <x-ui.action-button type="create" label="Seleccionar como muestra" submit />
                                        </form>
                                    @else
                                        <x-ui.action-button type="view" label="Ver entrega" :href="route('calidad.entregas.show', $fila['id'])" />
                                    @endif
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.modal id="modal-nueva-muestra" title="Muestreo directo">
        <p class="mb-4 text-sm text-text-muted">Inspección en campo: identifica al proveedor y toma la muestra ANTES del acopio normal. Queda como muestra pendiente y continúas con el análisis.</p>
        <form method="POST" action="{{ route('calidad.entregas.store') }}" class="space-y-4">
            @csrf
            <x-forms.select name="metodo_identificacion" label="Identificar por" required :options="['DNI' => 'DNI', 'QR' => 'Código QR']" />
            <x-forms.input name="identificador" label="DNI o código QR del proveedor" required />
            <x-forms.input name="litros" type="number" step="any" label="Litros de la muestra" required />
            <x-forms.textarea name="observacion" label="Observación (opcional)" :rows="2" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Registrar y analizar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
