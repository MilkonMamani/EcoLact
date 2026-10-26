{{--
    Confirmaciones de "Mover ubicación" en el mapa general (admin-zonas.js las llena y las abre al
    pulsar Guardar). Cerrar cualquiera con Cancelar, la X o Escape vuelve al modo mover sin guardar.
    El servidor vuelve a calcular la zona con las delimitaciones guardadas antes de escribir nada.
--}}
<x-ui.modal id="modal-mover-misma-zona" title="Actualizar ubicación">
    <div data-mover-modal="misma-zona">
        <p class="text-sm font-semibold text-text" data-mover-nombre></p>
        <p class="mt-2 text-sm text-text-muted">La ubicación permanece dentro de: <strong class="text-text" data-mover-zona-actual></strong></p>
        <p class="mt-2 text-xs text-text-muted">Solo se actualizarán la latitud y la longitud; la zona y la ruta no cambian.</p>
        <p class="mt-3 text-sm text-danger" role="alert" data-mover-error hidden></p>
        <div class="mt-5 flex justify-end gap-2">
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="button" data-mover-confirmar>Guardar ubicación</x-ui.button>
        </div>
    </div>
</x-ui.modal>

<x-ui.modal id="modal-mover-cambio-zona" title="Cambiar proveedor de zona">
    <div data-mover-modal="cambio-zona">
        <p class="text-sm font-semibold text-text" data-mover-nombre></p>
        <p class="zonas-cambio">
            <span class="zonas-cambio-zona" data-mover-zona-actual></span>
            <x-ui.icon name="chevron-right" class="h-4 w-4 text-text-muted" />
            <span class="zonas-cambio-zona" data-mover-zona-nueva></span>
        </p>
        <p class="text-sm text-text-muted">La ubicación y la zona asignada serán actualizadas. Al confirmar se actualizarán también los datos asociados a la zona.</p>

        <div class="mt-3" data-mover-rutas>
            <p class="text-sm text-text" data-mover-ruta-unica hidden></p>
            <div data-mover-ruta-selector hidden>
                <label for="mover-ruta" class="mb-1.5 block text-sm font-medium text-text">Ruta en la nueva zona</label>
                <select id="mover-ruta" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" data-mover-ruta></select>
            </div>
            <div class="zonas-sin-ruta" data-mover-sin-ruta hidden>
                <p>Esta zona todavía no tiene rutas activas. Crea su ruta principal para poder asignarle el proveedor.</p>
                <button type="button" class="fichas-btn is-outline is-sm" data-mover-crear-ruta>
                    <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Crear "Ruta principal"
                </button>
            </div>
        </div>

        <p class="mt-3 text-xs text-text-muted">Las entregas, pagos y liquidaciones ya registrados conservan la zona que tenían: el cambio rige desde ahora.</p>
        <p class="mt-3 text-sm text-danger" role="alert" data-mover-error hidden></p>
        <div class="mt-5 flex justify-end gap-2">
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="button" data-mover-confirmar>Confirmar cambio</x-ui.button>
        </div>
    </div>
</x-ui.modal>

<x-ui.modal id="modal-mover-fuera" title="Ubicación fuera de las zonas de acopio">
    <div data-mover-modal="fuera">
        <p class="text-sm text-text">La ubicación seleccionada no pertenece a ninguna delimitación operativa.</p>
        <p class="mt-2 text-sm text-text-muted" data-mover-fuera-detalle></p>
        <div class="mt-5 flex justify-end gap-2">
            <x-ui.button type="button" variant="ghost" data-mover-cancelar-todo>Cancelar</x-ui.button>
            <x-ui.button type="button" data-modal-close>Seguir moviendo</x-ui.button>
        </div>
    </div>
</x-ui.modal>
