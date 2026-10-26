{{--
    Modales de la pantalla Insumos fuera de la compra:
      - Presentación de compra (nueva/editar): define cómo se compra; NO toca el stock.
      - Ajustar stock: merma, pérdida, corrección o inventario físico; NUNCA una compra. Se abre desde el
        encabezado (eligiendo el insumo), desde la fila o desde el panel (insumo ya elegido).
    resources/js/pages/insumos-compra.js los rellena. La equivalencia y el stock resultante que se ven
    aquí son solo una vista previa: el servidor los recalcula al guardar.
    Variables: $motivosAjuste, $insumosAjuste.
--}}
<x-ui.modal id="modal-presentacion" title="Presentación de compra" subtitle="Cómo se compra este insumo y cuánto contiene cada unidad comprada.">
    <form method="POST" action="#" class="space-y-4" data-presentacion-form>
        @csrf
        <input type="hidden" name="_method" value="POST" data-presentacion-metodo>

        <x-forms.input name="nombre" id="presentacion-nombre" label="Nombre de la presentación" required maxlength="60" placeholder="Ej. Saco, Balde, Bolsa" :sticky="false" />

        <div>
            <x-forms.label for="presentacion-cantidad" :required="true">Cantidad que contiene</x-forms.label>
            <div class="grid grid-cols-2 gap-3">
                <input id="presentacion-cantidad" name="cantidad" type="number" step="any" min="0" required placeholder="Ej. 25" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                <select name="unidad" id="presentacion-unidad" aria-label="Unidad equivalente" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" data-presentacion-unidades></select>
            </div>
        </div>

        <div class="rounded-lg bg-primary-soft/40 px-3 py-2.5 text-sm" aria-live="polite">
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Equivalencia en unidad base</p>
            <p class="mt-0.5 font-semibold text-primary-dark" data-presentacion-equivalencia>—</p>
        </div>

        <div data-presentacion-estado-campo hidden>
            <x-forms.select name="estado" id="presentacion-estado" label="Estado" :options="['ACTIVA' => 'Activa', 'INACTIVA' => 'Inactiva (no se ofrece al comprar)']" :placeholder="false" :sticky="false" />
        </div>

        <p class="text-xs text-text-muted">Registrar una presentación no suma stock: solo define la conversión que se usará al registrar compras.</p>

        <div class="flex justify-end gap-2 border-t border-text-muted/10 pt-4">
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit">Guardar presentación</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<x-ui.modal id="modal-ajuste-insumo" title="Ajustar stock" subtitle="Corrige el stock por merma, pérdida, error o conteo físico. Las compras se registran aparte.">
    <form method="POST" action="#" class="space-y-4" data-ajuste-form data-insumos="{{ json_encode($insumosAjuste) }}">
        @csrf

        <div data-ajuste-campo-insumo>
            <x-forms.select name="ajuste_insumo" id="ajuste-insumo" label="Insumo" :options="collect($insumosAjuste)->pluck('nombre', 'id')->all()" placeholder="Seleccionar insumo" :sticky="false" data-ajuste-insumo-select />
        </div>

        <p class="rounded-lg bg-background px-3 py-2 text-sm text-text-muted" data-ajuste-stock hidden>
            Stock actual: <strong class="text-text" data-ajuste-stock-valor></strong>
        </p>

        <x-forms.select name="motivo" id="ajuste-motivo" label="Motivo" :options="$motivosAjuste" value="MERMA" :placeholder="false" required :sticky="false" />

        <div data-ajuste-direccion hidden>
            <x-forms.label :required="true">La corrección</x-forms.label>
            <div class="grid grid-cols-2 gap-2">
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-text-muted/20 px-3 py-2 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary-soft/40">
                    <input type="radio" name="direccion" value="SUMA" class="text-primary focus:ring-primary"> Suma stock
                </label>
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-text-muted/20 px-3 py-2 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary-soft/40">
                    <input type="radio" name="direccion" value="RESTA" class="text-primary focus:ring-primary" checked> Resta stock
                </label>
            </div>
        </div>

        <div>
            <x-forms.label for="ajuste-cantidad" :required="true"><span data-ajuste-cantidad-rotulo>Cantidad a restar</span></x-forms.label>
            <div class="flex items-center gap-2">
                <input id="ajuste-cantidad" name="cantidad" type="number" step="any" min="0" required class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                <span class="w-12 shrink-0 text-sm text-text-muted" data-ajuste-unidad></span>
            </div>
            <p class="mt-1 text-xs text-text-muted" data-ajuste-ayuda></p>
        </div>

        <p class="rounded-lg bg-primary-soft/40 px-3 py-2 text-sm" data-ajuste-resultado hidden aria-live="polite">
            Stock resultante: <strong data-ajuste-resultado-valor></strong>
        </p>
        <p class="text-xs text-info" data-ajuste-solo-salida hidden>La leche solo admite restas: su stock entra únicamente por Acopio.</p>

        <x-forms.textarea name="observacion" id="ajuste-observacion" label="Observación (opcional)" :rows="2" placeholder="Ej. Bolsa rota en almacén" :sticky="false" />

        <div class="flex justify-end gap-2 border-t border-text-muted/10 pt-4">
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit" data-ajuste-enviar>Registrar ajuste</x-ui.button>
        </div>
    </form>
</x-ui.modal>
