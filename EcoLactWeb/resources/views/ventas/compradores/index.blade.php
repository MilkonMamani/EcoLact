@extends('layouts.admin')

@section('title', 'Compradores')

@section('content')
    <x-ui.card title="Compradores" subtitle="Mayoristas y público que compran productos terminados.">
        <x-slot:actions>
            @can('create', \App\Infrastructure\Persistence\Models\CompradorModel::class)
                <x-ui.button data-modal-open="modal-nuevo-comprador" size="sm" as="button" type="button">+ Nuevo comprador</x-ui.button>
            @endcan
        </x-slot:actions>

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
                            <td class="py-3 pr-4"><x-ui.badge compact :status="$comprador->estado()" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.modal id="modal-nuevo-comprador" title="Nuevo comprador">
        <form method="POST" action="{{ route('admin.compradores.store') }}" class="space-y-4">
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
