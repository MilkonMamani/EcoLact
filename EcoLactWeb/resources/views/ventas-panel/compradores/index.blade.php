@extends('layouts.ventas-panel')

@section('title', 'Compradores')

@section('content')
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Compradores" :value="$kpis['total']" hint="Registrados" />
        <x-ui.stat-card label="Mayoristas" :value="$kpis['mayoristas']" />
        <x-ui.stat-card label="Público" :value="$kpis['publico']" />
    </div>

    <x-ui.card title="Compradores" subtitle="Mayoristas y público que compran productos terminados.">
        <x-slot:actions>
            <x-ui.button data-modal-open="modal-nuevo-comprador" size="sm" as="button" type="button">+ Nuevo comprador</x-ui.button>
        </x-slot:actions>

        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-56">
                <x-forms.input name="buscar" label="Buscar" placeholder="Nombre o documento" value="{{ request('buscar') }}" />
            </div>
            <div class="w-44">
                <x-forms.select name="tipo_cliente" label="Tipo de cliente" :options="['MAYORISTA' => 'Mayorista', 'PUBLICO' => 'Público']" :value="request('tipo_cliente')" placeholder="Todos" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['buscar', 'tipo_cliente']))
                <x-ui.button :href="route('ventas.compradores.index')" variant="ghost" size="sm" as="a">Limpiar filtros</x-ui.button>
            @endif
        </form>

        @if (count($compradores) === 0)
            <x-ui.empty-state title="No hay compradores registrados" description="Registra un comprador mayorista o del público para asociarlo a sus ventas." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Nombre</th>
                        <th class="py-2 pr-4">Documento</th>
                        <th class="py-2 pr-4">Teléfono</th>
                        <th class="py-2 pr-4">Tipo de cliente</th>
                        <th class="py-2 pr-4">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($compradores as $comprador)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $comprador->nombre() }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $comprador->documento() ?? '—' }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $comprador->telefono() ?? '—' }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ ucfirst(strtolower($comprador->tipoCliente())) }}</td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$comprador->estado()" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.modal id="modal-nuevo-comprador" title="Nuevo comprador">
        <form method="POST" action="{{ route('ventas.compradores.store') }}" class="space-y-4">
            @csrf
            <x-forms.input name="nombre" label="Nombre" required />
            <x-forms.input name="documento" label="Documento" />
            <x-forms.input name="telefono" label="Teléfono" />
            <x-forms.select name="tipo_cliente" label="Tipo de cliente" required :options="['MAYORISTA' => 'Mayorista', 'PUBLICO' => 'Público']" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
