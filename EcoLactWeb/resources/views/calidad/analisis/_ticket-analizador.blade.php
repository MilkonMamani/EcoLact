{{--
    Escáner del ticket del analizador (dentro del formulario de Registrar análisis).
    El OCR solo PROPONE valores: rellena los campos de _parametros y el analista
    revisa/corrige antes de guardar. Ver resources/js/pages/ticket-analizador.js.
    Variables: $urlEscanear (ruta POST de escanear-ticket).
--}}
<section class="rounded-xl border border-text-muted/20 p-4" data-ticket-analizador data-url="{{ $urlEscanear }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-text">Ticket del analizador</p>
            <p class="mt-0.5 text-xs text-text-muted">Toma una foto del ticket y los valores se completarán solos. Revísalos antes de guardar.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ui.button type="button" size="sm" data-ticket-camara>
                <x-ui.icon name="camera" /> Tomar foto
            </x-ui.button>
            <x-ui.button type="button" size="sm" variant="secondary" data-ticket-archivo>
                <x-ui.icon name="image" /> Seleccionar imagen
            </x-ui.button>
        </div>
    </div>

    {{-- Sin name: la foto viaja por fetch, no con el formulario. capture="environment" abre la cámara trasera en el celular. --}}
    <input type="file" accept="image/*" capture="environment" class="hidden" data-ticket-input-camara>
    <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" data-ticket-input-archivo>
    <input type="hidden" name="ticket_path" value="{{ old('ticket_path') }}" data-ticket-path>
    <x-forms.error name="ticket_path" />

    <div class="mt-4 flex flex-col gap-4 sm:flex-row" data-ticket-resultado hidden>
        <figure class="sm:w-40">
            <img alt="Foto del ticket" class="h-44 w-full rounded-lg border border-text-muted/20 bg-text-muted/5 object-contain" data-ticket-preview>
            <figcaption class="mt-1 truncate text-xs text-text-muted">Archivo: <span data-ticket-archivo-nombre></span></figcaption>
        </figure>

        <div class="flex-1 space-y-3">
            <p class="flex items-center gap-2 text-sm text-text" role="status" aria-live="polite" data-ticket-mensaje></p>
            <p class="text-xs text-text-muted" data-ticket-no-configurados hidden></p>

            <div class="flex flex-wrap gap-2" data-ticket-botones hidden>
                <x-ui.button type="button" size="sm" variant="secondary" data-ticket-reescanear>
                    <x-ui.icon name="refresh" /> Volver a escanear
                </x-ui.button>
                <x-ui.button type="button" size="sm" variant="ghost" data-ticket-manual hidden>Ingresar manualmente</x-ui.button>
                <x-ui.button type="submit" size="sm" data-ticket-guardar hidden>Guardar análisis</x-ui.button>
            </div>

            <details data-ticket-texto-contenedor hidden>
                <summary class="cursor-pointer text-xs text-text-muted">Texto leído por el OCR</summary>
                <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap rounded-lg bg-text-muted/5 p-2 text-xs text-text-muted" data-ticket-texto></pre>
            </details>
        </div>
    </div>
</section>
