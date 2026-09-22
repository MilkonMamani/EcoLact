@php
    $soles = fn ($valor): string => 'S/ '.number_format((float) $valor, 2);
@endphp

<div data-panel-trabajador-contenido>
    @if ($seleccion === null)
        <section class="rounded-xl border border-text-muted/10 bg-surface p-4 shadow-sm">
            <div class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-text-muted/25 bg-background/60 px-6 py-16 text-center">
                <x-ui.icon name="users" class="h-10 w-10 text-text-muted/50" />
                <p class="text-sm font-medium text-text">Ningún trabajador seleccionado</p>
                <p class="max-w-[16rem] text-xs text-text-muted">Selecciona un trabajador de la lista para registrar su pago.</p>
            </div>
        </section>
    @else
        <section data-panel-obligacion="{{ $seleccion->id }}" class="space-y-4 rounded-xl border border-text-muted/10 bg-surface p-5 shadow-sm">
            <div>
                <h2 class="text-base font-semibold text-text">Registrar pago del trabajador</h2>
            </div>

            <div class="flex items-center gap-3 border-b border-text-muted/10 pb-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-soft text-base font-semibold text-primary-dark">{{ $seleccion->iniciales }}</span>
                <div class="min-w-0">
                    <p class="truncate font-semibold text-text">{{ $seleccion->nombre }}</p>
                    <p class="truncate text-sm text-text-muted">{{ $seleccion->rol }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <p class="text-xs uppercase tracking-wide text-text-muted">DNI</p>
                    <p class="mt-0.5 text-text">{{ $seleccion->dni ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-text-muted">Periodo</p>
                    <p class="mt-0.5 text-text">{{ $seleccion->periodoEtiqueta }}</p>
                    <p class="text-xs text-text-muted">{{ $seleccion->periodoRango }}</p>
                </div>
            </div>

            <x-pagos.control-periodo :control-periodo="$controlPeriodo" :trabajador-id="$seleccion->userId" :trabajador-nombre="$seleccion->nombre" class="rounded-lg bg-background/60 p-4" />

            <div class="rounded-lg bg-background/60 p-4">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-text-muted">Resumen de pago</p>

                <dl class="space-y-2.5 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-text-muted">Acumulado</dt>
                        <dd class="font-medium text-text">{{ $soles($seleccion->sueldoBase) }}</dd>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-text-muted">Bonificaciones (+)</dt>
                        <dd>
                            @if ($seleccion->estaPendiente())
                                <span class="inline-flex items-center gap-1 text-success">
                                    <span>+</span>
                                    <input type="number" step="0.01" min="0" inputmode="decimal" value="{{ number_format($seleccion->bonificacion, 2, '.', '') }}" data-campo-bonificacion class="w-24 rounded-md border border-text-muted/25 bg-surface px-2 py-1 text-right text-sm text-text focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                                </span>
                            @else
                                <span class="font-medium text-success">+ {{ $soles($seleccion->bonificacion) }}</span>
                            @endif
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-text-muted">Descuentos (-)</dt>
                        <dd>
                            @if ($seleccion->estaPendiente())
                                <span class="inline-flex items-center gap-1 text-danger">
                                    <span>−</span>
                                    <input type="number" step="0.01" min="0" inputmode="decimal" value="{{ number_format($seleccion->descuento, 2, '.', '') }}" data-campo-descuento class="w-24 rounded-md border border-text-muted/25 bg-surface px-2 py-1 text-right text-sm text-text focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                                </span>
                            @else
                                <span class="font-medium text-danger">− {{ $soles($seleccion->descuento) }}</span>
                            @endif
                        </dd>
                    </div>

                    <p data-ajuste-error hidden class="text-xs text-danger"></p>

                    <div class="flex items-center justify-between gap-3 border-t border-text-muted/15 pt-2.5">
                        <dt class="font-semibold text-text">Total final</dt>
                        <dd data-campo-total class="text-xl font-bold text-primary-dark">{{ $soles($seleccion->total) }}</dd>
                    </div>
                </dl>
            </div>

            @if ($seleccion->estaPendiente())
                <form method="POST" action="{{ route('admin.pagos.trabajadores.pagar', $seleccion->id) }}" enctype="multipart/form-data" class="space-y-3" data-form-confirmar-pago data-metodos-electronicos="{{ json_encode($metodosElectronicos) }}">
                    @csrf
                    <input type="hidden" name="q" value="{{ request('q') }}">
                    <input type="hidden" name="rol" value="{{ request('rol') }}">
                    <input type="hidden" name="periodo" value="{{ request('periodo') }}">
                    <input type="hidden" name="estado" value="{{ request('estado') }}">

                    <x-forms.select name="metodo_pago" label="Método de pago" required :options="$metodos" placeholder="Selecciona un método" data-campo-metodo />

                    <div>
                        <x-forms.label for="trabajador-operacion"><span data-campo-operacion-etiqueta>N° de operación / referencia</span><span class="text-danger" aria-hidden="true" data-campo-operacion-req hidden> *</span></x-forms.label>
                        <input id="trabajador-operacion" name="numero_operacion" type="text" maxlength="100" autocomplete="off" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" data-campo-operacion>
                        <p class="mt-1 text-xs text-text-muted" data-campo-operacion-ayuda>Opcional para efectivo; obligatorio para métodos electrónicos.</p>
                    </div>

                    <div>
                        <x-forms.label for="trabajador-fecha" :required="true">Fecha de pago</x-forms.label>
                        <input id="trabajador-fecha" name="fecha_pago" type="date" required max="{{ now()->toDateString() }}" min="{{ $seleccion->periodoInicioIso }}" value="{{ now()->toDateString() }}" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                    </div>

                    <x-forms.textarea name="observacion" id="trabajador-observacion" label="Observación (opcional)" placeholder="Ej. Pago en efectivo, observaciones..." :rows="2" maxlength="500" />

                    <div>
                        <x-forms.label for="trabajador-comprobante">Comprobante (opcional)</x-forms.label>
                        <input id="trabajador-comprobante" name="comprobante" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-1.5 text-sm text-text shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary-soft file:px-3 file:py-1 file:text-sm file:font-medium file:text-primary-dark">
                    </div>

                    <div class="flex flex-col gap-2 pt-1">
                        <x-ui.button :href="route('admin.pagos.trabajadores.comprobante', $seleccion->id)" target="_blank" variant="secondary">
                            <x-ui.icon name="printer" class="h-4 w-4" /> Imprimir comprobante
                        </x-ui.button>
                        <x-ui.button type="submit" variant="primary">Confirmar pago</x-ui.button>
                    </div>
                </form>
            @elseif ($seleccion->estaEnCurso())
                <div class="space-y-2">
                    <x-ui.button :href="route('admin.pagos.trabajadores.comprobante', $seleccion->id)" target="_blank" variant="secondary" class="w-full">
                        <x-ui.icon name="printer" class="h-4 w-4" /> Imprimir comprobante
                    </x-ui.button>
                    <x-ui.button type="button" disabled class="w-full cursor-not-allowed opacity-50">Pago aún no disponible</x-ui.button>
                </div>
            @else
                <div class="space-y-3">
                    <div class="rounded-lg bg-success/10 px-4 py-3 text-sm text-success">
                        <p class="font-medium">Pago registrado</p>
                        <p class="mt-0.5 text-text-muted">
                            {{ $seleccion->pagadoEl }} · {{ $seleccion->metodoPago }}
                            @if ($seleccion->registradoPor) · por {{ $seleccion->registradoPor }} @endif
                        </p>
                        @if ($seleccion->observacion)
                            <p class="mt-1 text-text-muted">"{{ $seleccion->observacion }}"</p>
                        @endif
                    </div>

                    <x-ui.button :href="route('admin.pagos.trabajadores.comprobante', $seleccion->id)" target="_blank" variant="secondary" class="w-full">
                        <x-ui.icon name="printer" class="h-4 w-4" /> Imprimir comprobante
                    </x-ui.button>
                </div>
            @endif
        </section>
    @endif
</div>
