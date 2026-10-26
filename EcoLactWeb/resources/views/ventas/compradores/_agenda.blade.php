{{--
    Compradores = agenda de clientes registrados (ADMINISTRADOR y PERSONAL DE VENTAS). La misma
    agenda alimenta "Comprador registrado" en Nueva venta. No hay Público/Mayorista: el precio
    mayorista lo decide la cantidad de cada venta, no el comprador.
--}}
<x-ui.card title="Compradores" subtitle="Agenda de clientes registrados que compran productos terminados.">
    <x-slot:actions>
        @can('create', \App\Infrastructure\Persistence\Models\CompradorModel::class)
            <x-ui.button data-modal-open="modal-nuevo-comprador" size="sm" as="button" type="button">+ Nuevo comprador</x-ui.button>
        @endcan
    </x-slot:actions>

    @if ($conBuscador ?? false)
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-64">
                <x-forms.input name="buscar" label="Buscar" placeholder="Nombre, documento o teléfono" value="{{ request('buscar') }}" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Buscar</x-ui.button>
            @if (request()->filled('buscar'))
                <x-ui.button :href="route($portal.'.compradores.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
            @endif
        </form>
    @endif

    @if (count($compradores) === 0)
        <x-ui.empty-state title="No hay compradores registrados" description="Registra a los clientes que quieras identificar en sus ventas." />
    @else
        <x-tables.table>
            <thead>
                <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                    <th class="py-2 pr-4">Nombre / Razón social</th>
                    <th class="py-2 pr-4">Documento / RUC</th>
                    <th class="py-2 pr-4">Teléfono</th>
                    <th class="py-2 pr-4">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($compradores as $comprador)
                    <tr class="border-b border-text-muted/5 last:border-0">
                        <td class="py-3 pr-4 text-text">{{ $comprador->nombre() }}</td>
                        <td class="py-3 pr-4 text-text-muted">{{ $comprador->documento() ?? '—' }}</td>
                        <td class="py-3 pr-4 text-text-muted">{{ $comprador->telefono() ?? '—' }}</td>
                        <td class="py-3 pr-4"><x-ui.badge compact :status="$comprador->estado()" /></td>
                    </tr>
                @endforeach
            </tbody>
        </x-tables.table>
    @endif
</x-ui.card>

<x-ui.modal id="modal-nuevo-comprador" title="Nuevo comprador">
    <form method="POST" action="{{ route($portal.'.compradores.store') }}" class="space-y-4">
        @csrf
        <x-forms.input name="nombre" label="Nombre / Razón social" required />
        <x-forms.input name="documento" label="Documento / RUC" />
        <x-forms.input name="telefono" label="Teléfono" />
        <div class="flex justify-end gap-2 pt-2">
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit">Guardar comprador</x-ui.button>
        </div>
    </form>
</x-ui.modal>
