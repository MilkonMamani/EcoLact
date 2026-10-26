{{--
    "+ Nuevo comprador" desde Nueva venta: se guarda por fetch en compradores.store (la misma
    agenda del módulo Compradores) y queda seleccionado sin perder producto ni cantidad.
    Sin Público/Mayorista: eso lo decide la cantidad de cada venta.
--}}
<x-ui.modal id="vpos-modal-comprador" title="Nuevo comprador">
    <form method="POST" action="{{ route($portal.'.compradores.store') }}" class="space-y-4" data-vpos-comprador-form novalidate>
        @csrf
        <x-forms.input name="nombre" id="vpos-comprador-nombre" :sticky="false" label="Nombre / Razón social" required autofocus maxlength="150" autocomplete="off" />
        <x-forms.input name="documento" id="vpos-comprador-documento" :sticky="false" label="Documento / RUC" maxlength="20" inputmode="numeric" autocomplete="off" />
        <x-forms.input name="telefono" id="vpos-comprador-telefono" :sticky="false" label="Teléfono" maxlength="20" inputmode="tel" autocomplete="off" />
        <p class="vpos-alerta is-error" data-vpos-comprador-error hidden></p>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="fichas-btn is-outline" data-modal-close>Cancelar</button>
            <button type="submit" class="fichas-btn is-primary" data-vpos-comprador-guardar>Guardar comprador</button>
        </div>
    </form>
</x-ui.modal>
