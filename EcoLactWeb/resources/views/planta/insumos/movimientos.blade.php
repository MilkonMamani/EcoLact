@extends('layouts.planta')

@section('title', 'Movimientos de insumos')

@section('content')
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Movimientos" :value="$kpis['total']" />
        <x-ui.stat-card label="Entradas" :value="$kpis['entradas']" accent="success" />
        <x-ui.stat-card label="Salidas" :value="$kpis['salidas']" accent="danger" />
    </div>

    <x-ui.card title="Movimientos de insumos" subtitle="Consulta las entradas, salidas y consumos registrados en el inventario.">
        <x-slot:actions>
            <x-ui.button :href="route('planta.insumos.index')" variant="ghost" size="sm" as="a">← Inventario</x-ui.button>
        </x-slot:actions>

        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-56">
                <x-forms.select name="insumo_id" label="Insumo" :options="$insumosDisponibles" :value="request('insumo_id')" placeholder="Todos los insumos" />
            </div>
            <div class="w-40">
                <x-forms.select name="tipo" label="Tipo" :options="['ENTRADA' => 'Entrada', 'SALIDA' => 'Salida']" :value="request('tipo')" placeholder="Todos" />
            </div>
            <div class="w-40">
                <x-forms.input name="desde" type="date" label="Desde" value="{{ request('desde') }}" />
            </div>
            <div class="w-40">
                <x-forms.input name="hasta" type="date" label="Hasta" value="{{ request('hasta') }}" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['insumo_id', 'tipo', 'desde', 'hasta']))
                <x-ui.button :href="route('planta.insumos.movimientos')" variant="ghost" size="sm" as="a">Limpiar filtros</x-ui.button>
            @endif
        </form>

        @if (count($movimientos) === 0)
            <x-ui.empty-state title="No hay movimientos registrados" description="Los movimientos de entrada y salida aparecerán aquí cuando se registren." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Insumo</th>
                        <th class="py-2 pr-4">Tipo</th>
                        <th class="py-2 pr-4">Motivo</th>
                        <th class="py-2 pr-4">Cantidad</th>
                        <th class="py-2 pl-4">Lote</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($movimientos as $movimiento)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-2 pr-4 text-text">{{ $movimiento->fecha()->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pr-4 text-text">
                                <a class="hover:text-primary hover:underline" href="{{ route('planta.insumos.show', $movimiento->insumoId()) }}">
                                    {{ $insumosPorId[$movimiento->insumoId()] ?? ('Insumo #'.$movimiento->insumoId()) }}
                                </a>
                            </td>
                            <td class="py-2 pr-4">
                                <x-ui.badge :variant="$movimiento->tipo() === 'ENTRADA' ? 'success' : 'danger'">{{ $movimiento->tipo() === 'ENTRADA' ? 'Entrada' : 'Salida' }}</x-ui.badge>
                            </td>
                            <td class="py-2 pr-4 text-text-muted">
                                {{ $movimiento->motivoEtiqueta() }}
                            </td>
                            <td class="py-2 pr-4 text-text">{{ $movimiento->tipo() === 'ENTRADA' ? '+' : '-' }}{{ rtrim(rtrim(number_format($movimiento->cantidad(), 4), '0'), '.') }}</td>
                            <td class="py-2 pl-4 text-text-muted">{{ $movimiento->loteProduccionId() ? 'LOTE-'.$movimiento->loteProduccionId() : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
