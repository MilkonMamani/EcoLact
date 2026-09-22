@extends('layouts.ventas-panel')

@section('title', 'Registrar venta')

@section('content')
    <x-ui.button :href="route('ventas.ventas.index')" variant="ghost" size="sm" as="a">&larr; Volver a ventas</x-ui.button>

    <x-ui.card title="Registrar venta" subtitle="Registra una nueva venta de productos terminados.">
        <form method="POST" action="{{ route('ventas.ventas.store') }}" class="space-y-6">
            @csrf

            <div class="space-y-4">
                <p class="text-sm font-semibold text-text">1. 👥 Comprador</p>

                <x-forms.select name="tipo_cliente" label="Tipo de cliente" required :options="['PROVEEDOR' => 'Proveedor', 'MAYORISTA' => 'Mayorista', 'PUBLICO' => 'Público']" data-venta-tipo-cliente />

                <div data-venta-campo="PROVEEDOR" hidden>
                    <x-forms.select name="proveedor_id" label="Proveedor" :options="collect($proveedores)->mapWithKeys(fn ($p) => [$p->id() => $p->nombreCompleto().' — DNI '.$p->dni()])->all()" placeholder="Selecciona un proveedor" />
                </div>

                <div data-venta-campo="MAYORISTA PUBLICO" hidden>
                    <x-forms.select name="comprador_id" label="Comprador (opcional)" :options="collect($compradores)->mapWithKeys(fn ($c) => [$c->id() => $c->nombre().' ('.ucfirst(strtolower($c->tipoCliente())).')'])->all()" placeholder="Sin comprador específico" />
                    <x-ui.button type="button" data-modal-open="modal-nuevo-comprador" variant="ghost" size="sm" class="mt-1">+ Nuevo comprador</x-ui.button>
                </div>
            </div>

            <div class="space-y-4 border-t border-text-muted/10 pt-4">
                <p class="text-sm font-semibold text-text">2. 📦 Producto y cantidad</p>

                <x-forms.select name="producto_id" label="Producto" required id="select-producto" :options="collect($productos)->mapWithKeys(fn ($p) => [$p->id() => $p->nombre().' — '.number_format($stockPorProducto[$p->id()] ?? 0, 2).' '.$p->unidadMedida().' disponibles'])->all()" />
                <p class="text-xs text-text-muted">Stock disponible: <span id="stock-disponible">—</span></p>

                <x-forms.input name="cantidad" type="number" step="any" label="Cantidad" required />
            </div>

            <div class="space-y-4 border-t border-text-muted/10 pt-4">
                <p class="text-sm font-semibold text-text">3. 💳 Forma de pago</p>
                <x-forms.select name="forma_pago" label="Forma de pago" required :options="['CONTADO' => 'Contado', 'DESCUENTO_PLANILLA' => 'Descuento en planilla']" />
                <p class="text-xs text-text-muted">"Descuento en planilla" solo aplica cuando el tipo de cliente es Proveedor.</p>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button :href="route('ventas.ventas.index')" variant="ghost" as="a">Cancelar</x-ui.button>
                <x-ui.button type="submit">Confirmar venta</x-ui.button>
            </div>
        </form>
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

    <script>
        (function () {
            const selectTipoCliente = document.querySelector('[data-venta-tipo-cliente]');
            const campos = document.querySelectorAll('[data-venta-campo]');

            function actualizarCliente() {
                const tipo = selectTipoCliente.value;
                campos.forEach((campo) => {
                    const tiposVisibles = campo.getAttribute('data-venta-campo').split(' ');
                    campo.hidden = ! tiposVisibles.includes(tipo);
                });
            }

            selectTipoCliente?.addEventListener('change', actualizarCliente);
            actualizarCliente();

            const stockPorProducto = @json($stockPorProducto);
            const selectProducto = document.getElementById('select-producto');
            const stockSpan = document.getElementById('stock-disponible');

            function actualizarStock() {
                const stock = stockPorProducto[selectProducto.value] ?? null;
                stockSpan.textContent = stock !== null ? Number(stock).toFixed(2) : '—';
            }

            selectProducto?.addEventListener('change', actualizarStock);
            actualizarStock();
        })();
    </script>
@endsection
