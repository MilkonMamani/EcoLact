@extends('layouts.proveedor')

@section('title', 'Mi calidad')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Análisis" :value="$kpis['total']" />
        <x-ui.stat-card label="Aceptados" :value="$kpis['aceptados']" accent="success" />
        <x-ui.stat-card label="Observados" :value="$kpis['observados']" accent="warning" />
        <x-ui.stat-card label="Rechazados" :value="$kpis['rechazados']" accent="danger" />
    </div>

    <x-ui.card title="Mi calidad" subtitle="Consulta los resultados de calidad de tus entregas de leche.">
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-44">
                <x-forms.select name="periodo" label="Periodo" :options="['ultimos_30_dias' => 'Últimos 30 días', 'este_mes' => 'Este mes']" :value="request('periodo')" placeholder="Todo el historial" />
            </div>
            <div class="w-44">
                <x-forms.select name="resultado" label="Resultado" :options="['ACEPTADA' => 'Aceptada', 'OBSERVADA' => 'Observada', 'RECHAZADA' => 'Rechazada']" :value="request('resultado')" placeholder="Todos" />
            </div>
            <div class="w-44">
                <x-forms.select name="estado" label="Estado" :options="['BORRADOR' => 'Borrador', 'CONFIRMADO' => 'Confirmado']" :value="request('estado')" placeholder="Todos" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['periodo', 'resultado', 'estado']))
                <x-ui.button :href="route('proveedor.analisis.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
            @endif
        </form>

        @if (count($analisis) === 0)
            <x-ui.empty-state title="No tienes análisis registrados" description="Cuando un analista registre un análisis de tus entregas, aparecerá aquí." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Entrega</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pr-4">Resultado</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($analisis as $item)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $item->fechaAnalisis()->format('d/m/Y H:i') }}</td>
                            <td class="py-3 pr-4 text-text-muted">
                                <a class="text-primary hover:underline" href="{{ route('proveedor.entregas.show', $item->entregaId()) }}">Entrega #{{ $item->entregaId() }}</a>
                            </td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$item->estado()->value" /></td>
                            <td class="py-3 pr-4">
                                @if ($item->resultado())
                                    <x-ui.badge :status="$item->resultado()->value" />
                                @else
                                    <span class="text-text-muted">Pendiente</span>
                                @endif
                            </td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver análisis de calidad" :href="route('proveedor.analisis.show', $item->id())" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
