{{--
    Modales "Nuevo insumo" y "Editar insumo", compartidos por el inventario de planta y la pantalla
    de Insumos del administrador. "Nuevo insumo" crea SOLO la ficha: el stock nace en 0. Los rellena/abre resources/js/pages/insumos.js ([data-insumos-page]).
    La unidad base se elige con "¿Cómo se mide este insumo?" (_selector-unidad). Al editar, solo se puede
    corregir mientras el insumo no tenga nada guardado en ella (`unidad_editable` en sus datos); si no,
    se muestra bloqueada con el motivo.
    Variables: $tipos, $familiasUnidad, $storeRoute.
--}}
<x-ui.modal id="modal-nuevo-insumo" title="Nuevo insumo" subtitle="Crea la ficha del insumo. Todavía no registra ninguna compra." size="lg">
    <form method="POST" action="{{ route($storeRoute) }}" class="space-y-6" data-insumo-nuevo>
        @csrf

        <section class="space-y-4">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-text-muted">Información general</h3>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-forms.input name="nombre" label="Nombre del insumo" required placeholder="Ej. Sal" maxlength="120" data-insumo-nombre />
                <x-forms.select name="tipo" label="Tipo" :options="$tipos" value="INSUMO" :placeholder="false" required />
            </div>

            <x-forms.textarea name="descripcion" label="Descripción (opcional)" :rows="2" placeholder="Ej. Sal industrial para el salado de quesos" />
        </section>

        <section class="space-y-4">
            @include('insumos._selector-unidad', ['idPrefijo' => 'nuevo', 'valor' => old('unidad_base')])

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-forms.label for="stock_minimo" :required="true">Stock mínimo</x-forms.label>
                    <div class="flex items-center gap-2">
                        <input id="stock_minimo" name="stock_minimo" type="number" step="any" min="0" required value="{{ old('stock_minimo', 0) }}" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary {{ $errors->has('stock_minimo') ? 'border-danger' : '' }}">
                        <span class="w-10 shrink-0 text-sm text-text-muted" data-sufijo-unidad></span>
                    </div>
                    <p class="mt-1 text-xs text-text-muted">Avisaremos “Stock bajo” al llegar a esta cantidad.</p>
                    <x-forms.error name="stock_minimo" />
                </div>
                <x-forms.select name="estado" label="Estado" :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" value="ACTIVO" :placeholder="false" required />
            </div>
        </section>

        <div class="flex items-start gap-2 rounded-lg bg-primary-soft/50 px-3 py-2.5 text-xs text-primary-dark ring-1 ring-inset ring-primary/15">
            <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
            <p>
                <strong>El stock empieza en 0.</strong> Crear el insumo no significa que haya entrado mercancía: el stock sube cuando registras una compra.
            </p>
        </div>

        <div class="flex justify-end gap-2 border-t border-text-muted/10 pt-4">
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit">Crear insumo</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<x-ui.modal id="modal-editar-insumo" title="Editar insumo" size="lg">
    <form method="POST" action="#" class="space-y-5">
        @csrf
        @method('PATCH')
        <div class="grid gap-4 sm:grid-cols-2">
            <x-forms.input name="nombre" label="Nombre" required :sticky="false" data-insumo-nombre />
            <x-forms.select name="tipo" label="Tipo" :options="$tipos" :placeholder="false" :sticky="false" />
        </div>
        <p class="-mt-2 text-xs text-info" data-aviso-sistema hidden>Este es un insumo del sistema: no se puede cambiar su tipo, su unidad ni desactivarlo.</p>
        <x-forms.textarea name="descripcion" label="Descripción (opcional)" :rows="2" :sticky="false" />

        <div data-unidad-editable hidden>
            @include('insumos._selector-unidad', ['idPrefijo' => 'editar', 'valor' => null])
            <p class="mt-2 text-xs text-text-muted">Aún puedes corregirla porque este insumo no tiene stock, compras, presentaciones ni recetas.</p>
        </div>
        <div class="rounded-lg bg-background px-3 py-2.5 text-sm" data-unidad-bloqueada>
            <p class="flex items-center gap-1.5 text-text"><x-ui.icon name="lock" class="h-4 w-4 text-text-muted" /> Se controla en <strong data-unidad-base></strong></p>
            <p class="mt-0.5 text-xs text-text-muted" data-unidad-motivo>La unidad no se puede cambiar: reinterpretaría las cantidades ya guardadas.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-forms.label for="editar-stock-minimo" :required="true">Stock mínimo</x-forms.label>
                <div class="flex items-center gap-2">
                    <input id="editar-stock-minimo" name="stock_minimo" type="number" step="any" min="0" required class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                    <span class="w-10 shrink-0 text-sm text-text-muted" data-sufijo-unidad></span>
                </div>
            </div>
            <x-forms.select name="estado" label="Estado" :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" :placeholder="false" :sticky="false" />
        </div>
        <div class="flex justify-end gap-2 border-t border-text-muted/10 pt-4">
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit">Guardar cambios</x-ui.button>
        </div>
    </form>
</x-ui.modal>
