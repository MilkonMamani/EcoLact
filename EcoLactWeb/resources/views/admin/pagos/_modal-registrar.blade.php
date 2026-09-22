@php
    $reabrir = old('_obligacion') !== null && $errors->any();
    $campoSoloLectura = 'w-full rounded-lg border border-text-muted/15 bg-background px-3 py-2 text-sm text-text-muted shadow-sm focus:outline-none';
@endphp

{{-- Un único modal: se abre SOLO desde una fila PENDIENTE y JS carga sus datos (nunca se elige beneficiario). --}}
<x-ui.modal id="modal-registrar-pago" title="Registrar pago" size="xl" :open="$reabrir">
    <div class="-mt-2 flex flex-wrap items-center justify-between gap-3 border-b border-text-muted/10 pb-4">
        <div>
            <p class="text-lg font-semibold text-text" data-pago-titulo></p>
            <p class="text-sm text-text-muted" data-pago-subtitulo></p>
        </div>
        <x-ui.badge status="PENDIENTE">Pendiente de pago</x-ui.badge>
    </div>

    <form method="POST" action="" enctype="multipart/form-data" class="mt-5 space-y-5" data-pago-form data-metodos-electronicos='@json($metodosElectronicos)'>
        @csrf
        <input type="hidden" name="_obligacion" value="{{ old('_obligacion') }}" data-pago-obligacion>

        @if ($reabrir && $errors->has('pago'))
            <x-ui.alert variant="error">{{ $errors->first('pago') }}</x-ui.alert>
        @endif

        <section aria-labelledby="pago-datos-calculo">
            <h3 id="pago-datos-calculo" class="mb-3 text-sm font-semibold text-text">Datos del pago</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="mb-1.5 block text-sm font-medium text-text" for="pago-beneficiario">Beneficiario</label><input id="pago-beneficiario" type="text" readonly tabindex="-1" class="{{ $campoSoloLectura }}" data-pago-campo="beneficiario"></div>
                <div><label class="mb-1.5 block text-sm font-medium text-text" for="pago-tipo">Tipo</label><input id="pago-tipo" type="text" readonly tabindex="-1" class="{{ $campoSoloLectura }}" data-pago-campo="tipo"></div>
                <div><label class="mb-1.5 block text-sm font-medium text-text" for="pago-regla">Regla de pago</label><input id="pago-regla" type="text" readonly tabindex="-1" class="{{ $campoSoloLectura }}" data-pago-campo="regla"></div>
                <div><label class="mb-1.5 block text-sm font-medium text-text" for="pago-periodo">Periodo</label><input id="pago-periodo" type="text" readonly tabindex="-1" class="{{ $campoSoloLectura }}" data-pago-campo="periodo"></div>
                <div><label class="mb-1.5 block text-sm font-medium text-text" for="pago-monto-calculado">Monto calculado</label><input id="pago-monto-calculado" type="text" readonly tabindex="-1" class="{{ $campoSoloLectura }} font-semibold" data-pago-campo="montoTexto"></div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-text" for="pago-monto">Monto a pagar</label>
                    <input id="pago-monto" name="monto" type="text" readonly aria-describedby="pago-monto-ayuda" class="{{ $campoSoloLectura }} font-semibold" data-pago-campo="monto" value="{{ old('monto') }}">
                    <p id="pago-monto-ayuda" class="mt-1 text-xs text-text-muted">Se paga el monto calculado completo; no hay pagos parciales.</p>
                </div>
            </div>
            <div class="mt-4 hidden rounded-lg border border-text-muted/10 bg-background/60 px-4 py-3" data-pago-detalle-caja>
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Detalle del cálculo</p>
                <dl class="mt-2 space-y-1 text-sm" data-pago-detalle></dl>
            </div>
        </section>

        <section aria-labelledby="pago-datos-registro">
            <h3 id="pago-datos-registro" class="mb-3 text-sm font-semibold text-text">Confirmación</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-forms.select name="metodo_pago" id="pago-metodo" label="Método de pago" required :options="$metodos" placeholder="Selecciona un método" data-pago-metodo />
                <div>
                    <x-forms.label for="pago-operacion"><span data-pago-operacion-etiqueta>N° de operación / referencia</span><span class="text-danger" aria-hidden="true" data-pago-operacion-req hidden> *</span></x-forms.label>
                    <input id="pago-operacion" name="numero_operacion" type="text" maxlength="100" value="{{ old('numero_operacion') }}" autocomplete="off"
                        class="w-full rounded-lg border {{ $errors->has('numero_operacion') ? 'border-danger' : 'border-text-muted/25' }} bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" data-pago-operacion>
                    <p class="mt-1 text-xs text-text-muted" data-pago-operacion-ayuda>Opcional para efectivo; obligatorio para métodos electrónicos.</p>
                    <x-forms.error name="numero_operacion" />
                </div>
                <div>
                    <x-forms.label for="pago-fecha" :required="true">Fecha de pago</x-forms.label>
                    <input id="pago-fecha" name="fecha_pago" type="date" required max="{{ now()->toDateString() }}" value="{{ old('fecha_pago', now()->toDateString()) }}"
                        class="w-full rounded-lg border {{ $errors->has('fecha_pago') ? 'border-danger' : 'border-text-muted/25' }} bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" data-pago-fecha>
                    <x-forms.error name="fecha_pago" />
                </div>
                <div>
                    <x-forms.label for="pago-comprobante">Comprobante (PDF o imagen)</x-forms.label>
                    <input id="pago-comprobante" name="comprobante" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" data-pago-comprobante
                        class="w-full rounded-lg border {{ $errors->has('comprobante') ? 'border-danger' : 'border-text-muted/25' }} bg-surface px-3 py-1.5 text-sm text-text shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary-soft file:px-3 file:py-1 file:text-sm file:font-medium file:text-primary-dark">
                    <p class="mt-1 text-xs text-text-muted">PDF, JPG, PNG o WEBP. Máximo 5 MB.</p>
                    <p class="mt-1 hidden text-xs text-danger" role="alert" data-pago-comprobante-error></p>
                    <x-forms.error name="comprobante" />
                </div>
                <div class="sm:col-span-2">
                    <x-forms.textarea name="observacion" id="pago-observacion" label="Observación (opcional)" :rows="2" maxlength="500" />
                </div>
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-2 border-t border-text-muted/10 pt-4">
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit" data-pago-confirmar>Confirmar pago</x-ui.button>
        </div>
    </form>
</x-ui.modal>
