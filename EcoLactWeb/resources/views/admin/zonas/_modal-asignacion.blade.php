{{--
    Modal "Asignar proveedor a zona y ruta": uno solo para toda la pantalla; admin-zonas.js lo
    rellena y lo envía a admin.zonas.proveedores.asignacion. Primero la UBICACIÓN: el mapa pinta
    siempre las zonas activas con la misma delimitación y color del mapa general (data-catalogo);
    un click marca al proveedor, rellena latitud/longitud y detecta la zona. Después la
    ASIGNACIÓN: la zona detectada queda elegida y se escoge (o crea) su ruta. El servidor vuelve
    a verificar la zona del punto antes de guardar.
--}}
@php
    $control = 'w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text transition-colors placeholder:text-text-muted/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary';
    $seccion = 'text-xs font-semibold uppercase tracking-wide text-text-muted';
@endphp

<x-ui.modal id="modal-asignacion" size="xl" title="Asignar proveedor a zona y ruta">
    <form data-asignacion-form novalidate class="space-y-5">
        <div class="rounded-lg border border-text-muted/15 px-4 py-3">
            <p data-asignacion-nombre class="text-sm font-semibold text-text"></p>
            <p class="text-xs text-text-muted">DNI: <span data-asignacion-dni></span></p>
            <p data-asignacion-actual class="mt-1 text-xs text-text-muted" hidden></p>
        </div>

        <section class="space-y-3" aria-labelledby="asignacion-ubicacion-titulo">
            <h3 id="asignacion-ubicacion-titulo" class="{{ $seccion }}">Ubicación</h3>
            <div>
                <div data-asignacion-mapa class="h-72 w-full rounded-lg bg-text-muted/5 ring-1 ring-text-muted/15 sm:h-96" role="application" aria-label="Mapa para ubicar al proveedor"></div>
                <p data-mapa-ayuda class="mt-1.5 text-xs text-text-muted">Marca en el mapa la ubicación del proveedor.</p>
                <p data-mapa-aviso class="mt-1.5 text-xs text-warning" role="status" hidden></p>
                <p data-error-for="latitud" class="mt-1.5 text-xs text-danger" hidden></p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <p class="mb-1.5 text-sm font-medium text-text">Zona detectada</p>
                    <p data-zona-detectada class="flex min-h-[38px] items-center gap-2 rounded-lg bg-text-muted/5 px-3 py-2 text-sm text-text-muted">Sin ubicación</p>
                </div>
                <div>
                    <x-forms.label for="asignacion-latitud" :required="true">Latitud</x-forms.label>
                    <input id="asignacion-latitud" name="latitud" data-campo="latitud" type="number" step="any" inputmode="decimal" placeholder="-15.6123456" class="{{ $control }}" />
                </div>
                <div>
                    <x-forms.label for="asignacion-longitud" :required="true">Longitud</x-forms.label>
                    <input id="asignacion-longitud" name="longitud" data-campo="longitud" type="number" step="any" inputmode="decimal" placeholder="-69.9789123" class="{{ $control }}" />
                    <p data-error-for="longitud" class="mt-1.5 text-xs text-danger" hidden></p>
                </div>
            </div>
        </section>

        <section class="space-y-3" aria-labelledby="asignacion-asignacion-titulo">
            <h3 id="asignacion-asignacion-titulo" class="{{ $seccion }}">Asignación</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-forms.label for="asignacion-zona" :required="true">Zona</x-forms.label>
                    <select id="asignacion-zona" name="zona_id" data-campo="zona_id" class="{{ $control }}">
                        <option value="">Seleccionar zona</option>
                    </select>
                    <p data-error-for="zona_id" class="mt-1.5 text-xs text-danger" hidden></p>
                </div>
                <div>
                    <x-forms.label for="asignacion-ruta" :required="true">Ruta</x-forms.label>
                    <select id="asignacion-ruta" name="ruta_id" data-campo="ruta_id" class="{{ $control }}">
                        <option value="">Seleccionar ruta</option>
                    </select>
                    <p data-ruta-vacia class="mt-1.5 text-xs text-text-muted" hidden>Esta zona todavía no tiene rutas activas.</p>
                    <button type="button" data-crear-ruta-principal hidden class="mt-1.5 text-xs font-medium text-primary hover:underline">+ Crear ruta principal para esta zona</button>
                    <p data-crear-ruta-error class="mt-1.5 text-xs text-danger" hidden></p>
                    <p data-error-for="ruta_id" class="mt-1.5 text-xs text-danger" hidden></p>
                </div>
            </div>

            <div>
                <x-forms.label for="asignacion-referencia">Referencia (opcional)</x-forms.label>
                <input id="asignacion-referencia" name="referencia_ubicacion" data-campo="referencia_ubicacion" type="text" maxlength="255" placeholder="Ej. Frente a la escuela, a 200 metros de la plaza" class="{{ $control }}" />
                <p data-error-for="referencia_ubicacion" class="mt-1.5 text-xs text-danger" hidden></p>
            </div>
        </section>

        <p data-error-general class="rounded-lg bg-danger/10 px-3 py-2 text-sm text-danger" hidden></p>

        <div class="flex justify-end gap-2 border-t border-text-muted/10 pt-4">
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit" variant="primary" data-asignacion-guardar>Guardar</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<button type="button" hidden data-modal-open="modal-asignacion" data-asignacion-disparador aria-hidden="true" tabindex="-1"></button>
