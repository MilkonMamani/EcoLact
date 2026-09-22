{{--
    Campos de la configuración de pago del PROVEEDOR (nueva y edición).
    Variables: $p (prefijo de ids), $precio, $aplica, $fecha, $observacion, $ajustes (resultado => ReglaPagoCalidad|null),
    $deshabilitado, $etiquetaFecha, $sticky.
--}}
@php
    $control = 'w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm transition-colors placeholder:text-text-muted/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary';
    $soloLectura = 'w-full rounded-lg border border-text-muted/15 bg-background px-3 py-2 text-sm text-label';
    $valorOld = fn (string $campo, $porDefecto) => $sticky ? old($campo, $porDefecto) : $porDefecto;
    $aplicaActual = (string) $valorOld('aplica_calidad', $aplica ? '1' : '0') === '1';
    $resultados = ['ACEPTADA' => 'Aceptada', 'OBSERVADA' => 'Observada'];
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-text" for="{{ $p }}-tipo">Tipo</label>
        <input id="{{ $p }}-tipo" type="text" value="Proveedor" readonly tabindex="-1" class="{{ $soloLectura }}">
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-text" for="{{ $p }}-calculo">Cálculo</label>
        <input id="{{ $p }}-calculo" type="text" value="Por litros entregados" readonly tabindex="-1" class="{{ $soloLectura }}">
    </div>

    <div>
        <x-forms.label :for="$p.'-precio'" :required="true">Precio base por litro (S/)</x-forms.label>
        <input id="{{ $p }}-precio" name="precio_base_litro" type="number" inputmode="decimal" step="0.0001" min="0.0001" placeholder="0.00" required @disabled($deshabilitado)
            value="{{ $valorOld('precio_base_litro', $precio !== null ? preg_replace('/(?<=\.\d\d)0{1,2}$/', '', number_format($precio, 4, '.', '')) : '') }}"
            class="{{ $control }} {{ $errors->has('precio_base_litro') && $sticky ? 'border-danger' : '' }}">
        @if ($sticky)<x-forms.error name="precio_base_litro" />@endif
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-text" for="{{ $p }}-periodicidad">Periodicidad <span class="text-danger" aria-hidden="true">*</span></label>
        <select id="{{ $p }}-periodicidad" name="periodicidad" required @disabled($deshabilitado) class="{{ $control }}">
            <option value="SEMANAL" selected>Semanal</option>
        </select>
    </div>

    <div>
        <x-forms.label :for="$p.'-fecha'" :required="true">{{ $etiquetaFecha }}</x-forms.label>
        <input id="{{ $p }}-fecha" name="fecha_inicio" type="date" required @disabled($deshabilitado) value="{{ $valorOld('fecha_inicio', $fecha) }}"
            class="{{ $control }} {{ $errors->has('fecha_inicio') && $sticky ? 'border-danger' : '' }}">
        @if ($sticky)<x-forms.error name="fecha_inicio" />@endif
    </div>
    <div>
        <span class="mb-1.5 block text-sm font-medium text-text" id="{{ $p }}-calidad-etiqueta">Aplicar ajustes de calidad</span>
        <label class="inline-flex cursor-pointer items-center gap-3 py-1.5">
            <input type="hidden" name="aplica_calidad" value="0" @disabled($deshabilitado)>
            <input type="checkbox" name="aplica_calidad" value="1" role="switch" aria-labelledby="{{ $p }}-calidad-etiqueta" data-toggle-calidad class="peer sr-only" @checked($aplicaActual) @disabled($deshabilitado)>
            <span class="relative h-6 w-11 shrink-0 rounded-full bg-text-muted/30 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5 peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary" aria-hidden="true"></span>
            <span class="text-sm font-medium text-text" data-toggle-texto>{{ $aplicaActual ? 'Sí' : 'No' }}</span>
        </label>
    </div>
</div>

{{-- AJUSTES POR CALIDAD: solo si se aplican; los valores salen de reglas_pago_calidad. --}}
<div data-ajustes-bloque class="mt-5 rounded-xl border border-text-muted/15 bg-background/50 p-4" @if (! $aplicaActual) hidden @endif>
    <div class="flex items-center gap-2">
        <x-ui.icon name="beaker" class="h-5 w-5 text-primary" />
        <h3 class="text-sm font-semibold text-text">Ajustes por calidad</h3>
    </div>
    <p class="mt-1 text-xs text-label">Se suman al precio base por litro cuando una entrega tiene análisis. Si no tiene análisis, se paga el precio base.</p>

    <div class="mt-3 hidden grid-cols-[1fr_1.2fr_1fr] gap-3 text-[11px] font-medium uppercase tracking-wider text-label sm:grid">
        <span>Resultado</span><span>Tipo de ajuste</span><span>Valor</span>
    </div>

    <div class="mt-2 divide-y divide-text-muted/10">
        @foreach ($resultados as $clave => $etiqueta)
            @php
                $regla = $ajustes[$clave] ?? null;
                $tipoActual = $valorOld("ajustes.$clave.tipo_ajuste", $regla?->tipoAjuste() ?? 'MONTO');
                $valorActual = $valorOld("ajustes.$clave.valor_ajuste", number_format($regla?->valorAjuste() ?? 0, 2, '.', ''));
            @endphp
            <div class="grid items-center gap-2 py-2.5 sm:grid-cols-[1fr_1.2fr_1fr] sm:gap-3" data-ajuste-fila>
                <div><x-ui.badge :status="$clave">{{ $etiqueta }}</x-ui.badge></div>
                <select name="ajustes[{{ $clave }}][tipo_ajuste]" aria-label="Tipo de ajuste para {{ $etiqueta }}" @disabled($deshabilitado || ! $aplicaActual) class="{{ $control }}">
                    <option value="MONTO" @selected($tipoActual === 'MONTO')>Monto fijo (S/)</option>
                    <option value="PORCENTAJE" @selected($tipoActual === 'PORCENTAJE')>Porcentaje (%)</option>
                </select>
                <input name="ajustes[{{ $clave }}][valor_ajuste]" type="number" step="0.0001" inputmode="decimal" value="{{ $valorActual }}" aria-label="Valor del ajuste para {{ $etiqueta }}" @disabled($deshabilitado || ! $aplicaActual) class="{{ $control }}">
                <p class="text-xs text-label sm:col-span-3" data-precio-final-fila>Se suma al precio base: precio final <span class="font-semibold text-text" data-precio-final>—</span> por litro.</p>
            </div>
        @endforeach
        <div class="grid items-center gap-2 py-2.5 sm:grid-cols-[1fr_1.2fr_1fr] sm:gap-3">
            <div><x-ui.badge status="RECHAZADA">Rechazada</x-ui.badge></div>
            <p class="text-sm text-text">No pagable</p>
            <p class="text-xs text-label">La leche rechazada no ingresa a la planta: S/ 0.00 por sus litros.</p>
        </div>
    </div>
</div>

<div class="mt-4">
    <x-forms.textarea name="observacion" :id="$p.'-observacion'" label="Observación (opcional)" :rows="2" maxlength="500" :value="$observacion" :sticky="$sticky" placeholder="Ej.: Precio vigente desde octubre." :disabled="$deshabilitado" />
</div>

<div class="mt-4 flex items-start gap-2 rounded-xl border border-info/20 bg-info-soft px-4 py-3 text-xs text-text">
    <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-info" />
    <span>El precio base de la leche ahora se configura aquí; no existe un módulo separado de Precio de leche.</span>
</div>
