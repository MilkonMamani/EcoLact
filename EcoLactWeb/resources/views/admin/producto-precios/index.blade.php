@extends('layouts.admin')

@section('title', 'Precios de ' . $producto->nombre)

@section('content')
    <x-ui.button :href="route('admin.productos.index')" variant="ghost" size="sm" as="a">&larr; Volver a productos</x-ui.button>

    <x-ui.card :title="'Precios — ' . $producto->nombre" subtitle="Precio vigente por tipo de cliente (proveedor, mayorista, público). Registrar un precio nuevo para un tipo cierra automáticamente el anterior.">
        @if (count($precios) === 0)
            <x-ui.empty-state title="Todavía no hay precios configurados" description="Mientras no exista un precio vigente para un tipo de cliente, no se podrá vender este producto a ese tipo de cliente." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Tipo de cliente</th>
                        <th class="py-2 pr-4">Precio</th>
                        <th class="py-2 pr-4">Vigente desde</th>
                        <th class="py-2 pr-4">Vigente hasta</th>
                        <th class="py-2 pl-4">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($precios as $precio)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ ['PROVEEDOR' => 'Proveedor', 'MAYORISTA' => 'Mayorista', 'PUBLICO' => 'Público'][$precio->tipoCliente()] ?? $precio->tipoCliente() }}</td>
                            <td class="py-3 pr-4 font-medium text-text">S/ {{ number_format($precio->precio(), 2) }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $precio->fechaInicio()->format('d/m/Y') }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $precio->fechaFin()?->format('d/m/Y') ?? '—' }}</td>
                            <td class="py-3 pl-4"><x-ui.badge compact :status="$precio->estado()" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.card title="Registrar nuevo precio" class="mt-4">
        <form method="POST" action="{{ route('admin.producto-precios.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="producto_id" value="{{ $producto->id }}">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-forms.select name="tipo_cliente" label="Tipo de cliente" required :options="['PROVEEDOR' => 'Proveedor', 'MAYORISTA' => 'Mayorista', 'PUBLICO' => 'Público']" />
                <x-forms.input name="precio" type="number" step="any" label="Precio (S/)" required />
                <x-forms.input name="fecha_inicio" type="date" label="Vigente desde (opcional, hoy si se deja vacío)" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="submit">Guardar precio</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
