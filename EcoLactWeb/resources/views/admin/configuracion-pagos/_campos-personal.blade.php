{{--
    Campos de la configuración de pago del PERSONAL (nueva y edición).
    Variables: $p, $roles (id => nombre; solo en "nueva"), $rolNombre (solo en edición), $monto, $fecha, $observacion,
    $deshabilitado, $etiquetaFecha, $sticky.
--}}
@php
    $control = 'w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm transition-colors placeholder:text-text-muted/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary';
    $soloLectura = 'w-full rounded-lg border border-text-muted/15 bg-background px-3 py-2 text-sm text-label';
    $valorOld = fn (string $campo, $porDefecto) => $sticky ? old($campo, $porDefecto) : $porDefecto;
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        @if (isset($roles))
            <x-forms.select name="rol_id" :id="$p.'-rol'" label="Rol del sistema" required :disabled="$deshabilitado" :options="$roles" placeholder="Selecciona un rol" data-config-rol />
        @else
            <label class="mb-1.5 block text-sm font-medium text-text" for="{{ $p }}-rol">Rol del sistema</label>
            <input id="{{ $p }}-rol" type="text" value="{{ $rolNombre }}" readonly tabindex="-1" class="{{ $soloLectura }}">
        @endif
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-text" for="{{ $p }}-calculo">Tipo de cálculo</label>
        <input id="{{ $p }}-calculo" type="text" value="Sueldo fijo mensual" readonly tabindex="-1" class="{{ $soloLectura }}">
    </div>

    <div>
        <x-forms.label :for="$p.'-monto'" :required="true">Sueldo mensual (S/)</x-forms.label>
        <input id="{{ $p }}-monto" name="monto_base" type="number" inputmode="decimal" step="0.01" min="0.01" placeholder="1000.00" required @disabled($deshabilitado)
            value="{{ $valorOld('monto_base', $monto !== null ? number_format($monto, 2, '.', '') : '') }}"
            class="{{ $control }} {{ $errors->has('monto_base') && $sticky ? 'border-danger' : '' }}">
        @if ($sticky)<x-forms.error name="monto_base" />@endif
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-text" for="{{ $p }}-periodicidad">Periodicidad</label>
        <input id="{{ $p }}-periodicidad" type="text" value="Mensual" readonly tabindex="-1" class="{{ $soloLectura }}">
    </div>

    <div class="sm:col-span-2 sm:max-w-[calc(50%-0.5rem)]">
        <x-forms.label :for="$p.'-fecha'" :required="true">{{ $etiquetaFecha }}</x-forms.label>
        <input id="{{ $p }}-fecha" name="fecha_inicio" type="date" required @disabled($deshabilitado) value="{{ $valorOld('fecha_inicio', $fecha) }}"
            class="{{ $control }} {{ $errors->has('fecha_inicio') && $sticky ? 'border-danger' : '' }}">
        @if ($sticky)<x-forms.error name="fecha_inicio" />@endif
    </div>
</div>

<div class="mt-4">
    <x-forms.textarea name="observacion" :id="$p.'-observacion'" label="Observación (opcional)" :rows="2" maxlength="500" :value="$observacion" :sticky="$sticky" placeholder="Ej.: Sueldo mensual para el rol ACOPIADOR." :disabled="$deshabilitado" data-config-observacion />
</div>

<div class="mt-4 flex items-start gap-2 rounded-xl border border-info/20 bg-info-soft px-4 py-3 text-xs text-text">
    <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-info" />
    <span>Si se crea un nuevo rol remunerable en la tabla roles, aparecerá automáticamente.</span>
</div>
