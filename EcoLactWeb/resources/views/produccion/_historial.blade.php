{{--
    Historial de producción (ADMINISTRADOR y ENCARGADO_PLANTA).
    Variables: $datos (LotesProduccionListadoData), $leche (Insumo), $prefijo ('admin' | 'planta') y los datos del modal (ver _nueva).
    La "leche utilizada" sale del consumo real del insumo LECHE registrado en cada lote; la "leche disponible"
    es el stock real del insumo LECHE (sube con Acopio, baja al iniciar una producción).
--}}
@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $cantidad = fn ($v, $u) => \App\Application\Produccion\LoteProduccionDetalleData::cantidad((float) $v, $u);
    $productosTexto = fn (array $fila) => $fila['productosNombres'] === []
        ? '—'
        : $fila['productosNombres'][0].(count($fila['productosNombres']) > 1 ? ' y '.(count($fila['productosNombres']) - 1).' más' : '');
    $resultadoTexto = fn (array $fila) => $fila['obtenido'] === null
        ? null
        : ($fila['unidadObtenido'] !== null ? $cantidad($fila['obtenido'], $fila['unidadObtenido']) : $fmt($fila['obtenido']).' en total');
@endphp

<x-layout.breadcrumbs :items="[['Producción', null], ['Producción', null]]" />

<div class="grid gap-3 sm:grid-cols-3">
    <x-ui.stat-card label="Lotes en proceso" :value="$datos->kpis['enProceso']" hint="Pendientes de finalizar">
        <x-slot:icon><x-ui.icon name="clock" class="h-5 w-5" /></x-slot:icon>
    </x-ui.stat-card>
    <x-ui.stat-card label="Leche disponible" :value="$fmt($leche->stockActual()).' '.$leche->unidadBase()" hint="Ingresa automáticamente desde Acopio" accent="info">
        <x-slot:icon><x-ui.icon name="droplet" class="h-5 w-5" /></x-slot:icon>
    </x-ui.stat-card>
    <x-ui.stat-card label="Lotes finalizados" :value="$datos->kpis['finalizados']" hint="Según filtros aplicados" accent="success">
        <x-slot:icon><x-ui.icon name="check-circle" class="h-5 w-5" /></x-slot:icon>
    </x-ui.stat-card>
</div>

<x-ui.card title="Producción" subtitle="Gestiona los lotes de producción y registra los productos obtenidos.">
    <x-slot:actions>
        @can('create', \App\Infrastructure\Persistence\Models\LoteProduccionModel::class)
            <x-ui.button type="button" size="sm" data-modal-open="modal-nueva-produccion">
                <x-ui.icon name="plus" class="h-4 w-4" /> Nueva producción
            </x-ui.button>
        @endcan
    </x-slot:actions>

    <form method="GET" class="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <div class="sm:min-w-56 sm:flex-1">
            <x-forms.input name="q" label="Buscar por código de lote" value="{{ request('q') }}" placeholder="Ej. LOT-00005" :sticky="false" />
        </div>

        <div class="sm:w-44">
            <x-forms.select name="periodo" label="Periodo" :options="[
                'hoy' => 'Hoy',
                'esta_semana' => 'Esta semana',
                'este_mes' => 'Este mes',
                'personalizado' => 'Personalizado',
            ]" :value="request('periodo')" placeholder="Todos" :sticky="false" />
        </div>

        @if (request('periodo') === 'personalizado')
            <div class="sm:w-40">
                <x-forms.input name="desde" type="date" label="Desde" value="{{ request('desde') }}" :sticky="false" />
            </div>
            <div class="sm:w-40">
                <x-forms.input name="hasta" type="date" label="Hasta" value="{{ request('hasta') }}" :sticky="false" />
            </div>
        @endif

        <div class="sm:w-40">
            <x-forms.select name="estado" label="Estado" :options="['EN_PROCESO' => 'En proceso', 'FINALIZADO' => 'Finalizado']" :value="request('estado')" placeholder="Todos" :sticky="false" />
        </div>

        <div class="flex gap-2">
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['q', 'periodo', 'estado']))
                <x-ui.button :href="route($prefijo.'.produccion.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
            @endif
        </div>
    </form>

    @if (count($datos->filas) === 0)
        <x-ui.empty-state title="No hay lotes de producción" description="Pulsa «Nueva producción», elige qué producto fabricar y cuántos." />
    @else
        {{-- Escritorio y tablet: tabla compacta. --}}
        <div class="hidden md:block">
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Código</th>
                        <th class="py-2 pr-4">Producto(s)</th>
                        <th class="py-2 pr-4">Inicio</th>
                        <th class="py-2 pr-4">Leche utilizada</th>
                        <th class="py-2 pr-4">Resultado</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($datos->filas as $fila)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 font-mono text-xs text-text">{{ $fila['codigo'] }}</td>
                            <td class="py-3 pr-4 font-medium text-text" title="{{ implode(', ', $fila['productosNombres']) }}">{{ $productosTexto($fila) }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap text-text-muted">{{ $fila['inicio'] }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap text-text">{{ $fmt($fila['litros']) }} L</td>
                            <td class="py-3 pr-4 whitespace-nowrap">
                                @if ($resultadoTexto($fila) !== null)
                                    <span class="text-text">{{ $resultadoTexto($fila) }}</span>
                                @else
                                    <span class="text-text-muted">Pendiente</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4"><x-ui.badge compact :status="$fila['estado']" /></td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver detalle del lote" :subject="$fila['codigo']" :href="route($prefijo.'.produccion.show', $fila['id'])" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        </div>

        {{-- Móvil: una tarjeta por lote, sin tabla horizontal. --}}
        <ul class="space-y-2 md:hidden">
            @foreach ($datos->filas as $fila)
                <li>
                    <a href="{{ route($prefijo.'.produccion.show', $fila['id']) }}" class="flex items-center gap-3 rounded-xl border border-text-muted/10 p-3 transition hover:border-primary/40 hover:bg-primary-soft/30" aria-label="Ver detalle del lote {{ $fila['codigo'] }}">
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-xs text-text-muted">{{ $fila['codigo'] }}</span>
                                <x-ui.badge compact :status="$fila['estado']" />
                            </div>
                            <p class="truncate text-sm font-semibold text-text">{{ $productosTexto($fila) }}</p>
                            <p class="text-xs text-text-muted">
                                {{ $fila['inicio'] }} · {{ $fmt($fila['litros']) }} L ·
                                {{ $resultadoTexto($fila) ?? 'Pendiente' }}
                            </p>
                        </div>
                        <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 text-text-muted" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-ui.card>

@can('create', \App\Infrastructure\Persistence\Models\LoteProduccionModel::class)
    @include('produccion._nueva')
@endcan
