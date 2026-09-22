{{--
    Historial de producción (ADMINISTRADOR y ENCARGADO_PLANTA).
    Variables: $datos (LotesProduccionListadoData), $leche (Insumo), $prefijo ('admin' | 'planta') y los datos del modal (ver _nueva).
    La "leche utilizada" sale del consumo real del insumo LECHE registrado en cada lote.
--}}
@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp

<x-layout.breadcrumbs :items="[['Producción', null], ['Producción', null]]" />

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <x-ui.stat-card label="Lotes en proceso" :value="$datos->kpis['enProceso']" accent="warning" />
    <x-ui.stat-card label="Lotes finalizados" :value="$datos->kpis['finalizados']" accent="success" hint="Según filtros aplicados" />
    <x-ui.stat-card label="Leche utilizada" :value="$fmt($datos->kpis['lecheUtilizada']).' L'" hint="Según filtros aplicados" />
    <x-ui.stat-card label="Productos obtenidos" :value="$fmt($datos->kpis['productosObtenidos'])" hint="Cantidad real registrada al finalizar" />
</div>

<x-ui.card title="Producción" subtitle="Gestiona los lotes de producción y consulta su estado.">
    <x-slot:actions>
        @can('create', \App\Infrastructure\Persistence\Models\LoteProduccionModel::class)
            <x-ui.button type="button" size="sm" data-modal-open="modal-nueva-produccion">
                <x-ui.icon name="plus" class="h-4 w-4" /> Nueva producción
            </x-ui.button>
        @endcan
    </x-slot:actions>

    <p class="mb-4 inline-flex items-center gap-2 text-sm text-primary-dark">
        <x-ui.icon name="droplet" class="h-5 w-5" />
        <span>Leche disponible:</span>
        <strong class="font-semibold">{{ $fmt($leche->stockActual()) }} {{ $leche->unidadBase() }}</strong>
    </p>

    <form method="GET" class="mb-4 space-y-3">
        <x-forms.input name="q" label="Buscar por código de lote" value="{{ request('q') }}" :sticky="false" />

        <div class="flex flex-wrap items-end gap-3">
            <div class="w-44">
                <x-forms.select name="periodo" label="Periodo" :options="[
                    'hoy' => 'Hoy',
                    'esta_semana' => 'Esta semana',
                    'este_mes' => 'Este mes',
                    'personalizado' => 'Personalizado',
                ]" :value="request('periodo')" placeholder="Todos" :sticky="false" />
            </div>

            @if (request('periodo') === 'personalizado')
                <div class="w-40">
                    <x-forms.input name="desde" type="date" label="Desde" value="{{ request('desde') }}" :sticky="false" />
                </div>
                <div class="w-40">
                    <x-forms.input name="hasta" type="date" label="Hasta" value="{{ request('hasta') }}" :sticky="false" />
                </div>
            @endif

            <div class="w-44">
                <x-forms.select name="estado" label="Estado" :options="['EN_PROCESO' => 'En proceso', 'FINALIZADO' => 'Finalizado']" :value="request('estado')" placeholder="Todos" :sticky="false" />
            </div>

            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['q', 'periodo', 'estado']))
                <x-ui.button :href="route($prefijo.'.produccion.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
            @endif
        </div>
    </form>

    @if (count($datos->filas) === 0)
        <x-ui.empty-state title="No hay lotes de producción" description="Pulsa «Nueva producción», elige qué producto fabricar y cuántos." />
    @else
        <x-tables.table>
            <thead>
                <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                    <th class="py-2 pr-4">Código</th>
                    <th class="py-2 pr-4">Fecha inicio</th>
                    <th class="py-2 pr-4">Fecha fin</th>
                    <th class="py-2 pr-4">Leche utilizada</th>
                    <th class="py-2 pr-4">Productos obtenidos</th>
                    <th class="py-2 pr-4">Estado</th>
                    <th class="py-2 pl-4 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($datos->filas as $fila)
                    <tr class="border-b border-text-muted/5 last:border-0">
                        <td class="py-3 pr-4 font-mono text-xs text-text">{{ $fila['codigo'] }}</td>
                        <td class="py-3 pr-4 whitespace-nowrap text-text-muted">{{ $fila['inicio'] }}</td>
                        <td class="py-3 pr-4 whitespace-nowrap text-text-muted">{{ $fila['fin'] ?? 'En proceso' }}</td>
                        <td class="py-3 pr-4 text-text">{{ $fmt($fila['litros']) }} L</td>
                        <td class="py-3 pr-4 text-text">
                            @if ($fila['obtenido'] !== null)
                                {{ $fmt($fila['obtenido']) }} <span class="text-xs text-text-muted">({{ $fila['productos'] }} {{ $fila['productos'] === 1 ? 'producto' : 'productos' }})</span>
                            @else
                                <span class="text-text-muted">Pendiente ({{ $fila['productos'] }} {{ $fila['productos'] === 1 ? 'producto' : 'productos' }})</span>
                            @endif
                        </td>
                        <td class="py-3 pr-4"><x-ui.badge :status="$fila['estado']" /></td>
                        <td class="py-3 pl-4">
                            <x-tables.actions>
                                <x-ui.action-button type="view" label="Ver detalle del lote" :subject="$fila['codigo']" :href="route($prefijo.'.produccion.show', $fila['id'])" />
                            </x-tables.actions>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-tables.table>
    @endif
</x-ui.card>

@can('create', \App\Infrastructure\Persistence\Models\LoteProduccionModel::class)
    @include('produccion._nueva')
@endcan
