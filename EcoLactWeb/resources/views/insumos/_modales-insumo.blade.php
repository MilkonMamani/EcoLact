{{--
    Modales "Nuevo insumo" y "Editar insumo", compartidos por el inventario de planta y la pantalla
    de Insumos del administrador. "Nuevo insumo" crea SOLO la ficha: el stock nace en 0. Los rellena/abre resources/js/pages/insumos.js ([data-insumos-page]).
    Variables: $tipos, $unidades, $storeRoute.
--}}
<x-ui.modal id="modal-nuevo-insumo" title="Nuevo insumo" subtitle="Crea la ficha del insumo. Todavía no registra ninguna compra." size="lg">
    <form method="POST" action="{{ route($storeRoute) }}" class="space-y-5" data-insumo-nuevo>
        @csrf

        <section class="space-y-4">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-text-muted">Ficha del insumo</h3>

            <x-forms.input name="nombre" label="Nombre del insumo" required placeholder="Ej. Cuajo" maxlength="120" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-forms.select name="tipo" label="Tipo" :options="$tipos" value="INSUMO" :placeholder="false" required />
                <x-forms.select name="unidad_base" label="Unidad base" :options="$unidades" :placeholder="false" value="ml" required data-unidad-base-select />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-forms.label for="stock_minimo" :required="true">Stock mínimo</x-forms.label>
                    <div class="flex items-center gap-2">
                        <input id="stock_minimo" name="stock_minimo" type="number" step="any" min="0" required value="{{ old('stock_minimo', 0) }}" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary {{ $errors->has('stock_minimo') ? 'border-danger' : '' }}">
                        <span class="w-8 shrink-0 text-sm text-text-muted" data-sufijo-unidad>ml</span>
                    </div>
                    <x-forms.error name="stock_minimo" />
                </div>
                <x-forms.select name="estado" label="Estado" :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" value="ACTIVO" :placeholder="false" required />
            </div>

            <x-forms.textarea name="descripcion" label="Descripción (opcional)" :rows="2" placeholder="Coagulante utilizado para quesos..." />
        </section>

        <div class="flex items-start gap-2 rounded-lg bg-primary-soft/50 px-3 py-2.5 text-xs text-primary-dark ring-1 ring-inset ring-primary/15">
            <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
            <p>
                <strong>El stock empieza en 0.</strong> Crear el insumo no significa que haya entrado mercancía: el stock sube cuando se registra una compra.
                Todo se controla en la unidad base y se mostrará “Stock bajo” cuando el stock llegue al mínimo.
            </p>
        </div>

        <div class="flex justify-end gap-2 border-t border-text-muted/10 pt-4">
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit">Crear insumo</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<x-ui.modal id="modal-editar-insumo" title="Editar insumo">
    <form method="POST" action="#" class="space-y-4">
        @csrf
        @method('PATCH')
        <x-forms.input name="nombre" label="Nombre" required :sticky="false" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-forms.select name="tipo" label="Tipo" :options="$tipos" :placeholder="false" :sticky="false" />
            <x-forms.select name="estado" label="Estado" :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" :placeholder="false" :sticky="false" />
        </div>
        <p class="-mt-2 text-xs text-info" data-aviso-sistema hidden>Este es un insumo del sistema: no se puede cambiar su tipo ni desactivarlo.</p>
        <p class="text-xs text-text-muted">Unidad base: <strong data-unidad-base></strong> (no se puede cambiar: cambiarla reinterpretaría todo el historial).</p>
        <x-forms.textarea name="descripcion" label="Descripción (opcional)" :rows="2" :sticky="false" />
        <x-forms.input name="stock_minimo" type="number" step="any" min="0" label="Stock mínimo" required :sticky="false" />
        <div class="flex justify-end gap-2 pt-2">
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit">Guardar cambios</x-ui.button>
        </div>
    </form>
</x-ui.modal>
