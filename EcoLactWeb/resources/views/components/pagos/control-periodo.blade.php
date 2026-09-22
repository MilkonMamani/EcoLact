@props(['controlPeriodo', 'trabajadorId', 'trabajadorNombre'])

@php
    $soles = fn ($valor): string => 'S/ '.number_format((float) $valor, 2);
    $modalJornadas = 'modal-jornadas-'.$trabajadorId;
    $modalRegistrar = 'modal-registrar-jornada-'.$trabajadorId;
@endphp

<x-ui.profile-section title="Control del periodo" icon="calendar" data-control-periodo {{ $attributes }}>
    @if ($controlPeriodo === null)
        <p class="text-sm text-text-muted">Aún no hay un periodo de pago generado para este trabajador. Se abrirá automáticamente cuando haya una configuración de sueldo vigente para su rol.</p>
    @else
        <p class="text-sm text-text-muted">Seguimiento de días trabajados y monto acumulado.</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-ui.data-field label="Periodo actual">{{ $controlPeriodo->periodoRango }}</x-ui.data-field>
            <x-ui.data-field label="Estado">
                <x-ui.badge :status="$controlPeriodo->estado">{{ $controlPeriodo->estadoEtiqueta }}</x-ui.badge>
            </x-ui.data-field>
        </div>

        <div class="mt-4">
            <x-ui.progress-bar :value="$controlPeriodo->diasTrabajados" :max="$controlPeriodo->diasBase" label="Días trabajados" />
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-ui.data-field label="Sueldo mensual">{{ $soles($controlPeriodo->sueldoMensual) }}</x-ui.data-field>
            <x-ui.data-field label="Valor por día" muted>{{ $soles($controlPeriodo->valorDia) }}<span class="ml-1 text-xs text-text-muted">(sueldo mensual ÷ 30)</span></x-ui.data-field>
        </div>

        <div class="mt-4 rounded-lg bg-background/60 p-3">
            <x-ui.data-field label="Acumulado">
                {{ $soles($controlPeriodo->acumulado) }}
                <p class="mt-0.5 text-xs font-normal text-text-muted">{{ $controlPeriodo->diasTrabajados }} días × {{ $soles($controlPeriodo->valorDia) }}</p>
            </x-ui.data-field>
        </div>

        @if ($controlPeriodo->diasFaltantes > 0)
            <p class="mt-3 text-sm text-text-muted">Faltan <strong class="text-text">{{ $controlPeriodo->diasFaltantes }} días</strong> para completar el periodo.</p>
        @elseif ($controlPeriodo->estado === 'PENDIENTE')
            <p class="mt-3 text-sm text-text-muted">Periodo completado: listo para pagar.</p>
        @endif

        <div class="mt-4 flex flex-wrap gap-2">
            <x-ui.button type="button" variant="secondary" size="sm" data-modal-open="{{ $modalJornadas }}" data-jornadas-trigger data-url-jornadas="{{ route('admin.trabajadores.jornadas.index', $trabajadorId) }}">Ver jornadas</x-ui.button>
            @if ($controlPeriodo->estaEnCurso())
                <x-ui.button type="button" variant="primary" size="sm" data-modal-open="{{ $modalRegistrar }}"><x-ui.icon name="plus" class="h-4 w-4" /> Registrar jornada</x-ui.button>
            @endif
        </div>
    @endif
</x-ui.profile-section>

@if ($controlPeriodo !== null)
    <x-ui.modal id="{{ $modalJornadas }}" title="Jornadas del periodo">
        <p class="mb-3 text-sm text-text-muted">{{ $controlPeriodo->periodoRango }}</p>
        <div data-jornadas-contenido>
            <p class="text-sm text-text-muted">Cargando…</p>
        </div>
    </x-ui.modal>

    @if ($controlPeriodo->estaEnCurso())
        <x-ui.modal id="{{ $modalRegistrar }}" title="Registrar jornada" :open="$errors->has('jornada')">
            <form method="POST" action="{{ route('admin.trabajadores.jornadas.store', $trabajadorId) }}" class="space-y-4">
                @csrf
                <x-ui.data-field label="Trabajador" muted>{{ $trabajadorNombre }}</x-ui.data-field>
                <x-forms.input name="fecha" type="date" label="Fecha" :value="now()->toDateString()" max="{{ now()->toDateString() }}" min="{{ $controlPeriodo->periodoInicioIso }}" required />
                <x-forms.textarea name="observacion" label="Observación (opcional)" :rows="2" maxlength="500" />
                @error('jornada')
                    <p class="text-sm text-danger">{{ $message }}</p>
                @enderror
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                    <x-ui.button type="submit">Registrar</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
@endif
