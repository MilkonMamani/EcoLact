@props(['controlPeriodo', 'trabajadorId', 'trabajadorNombre', 'conQr' => true])

{{--
    "Control del periodo" de un trabajador: compone el QR de asistencia, el
    calendario mensual y el resumen del mes, la misma implementación
    reutilizada por los 5 perfiles de personal y el panel de "Pagar a
    trabajadores". $trabajadorId es el id de `users` (no de `trabajadores`):
    las rutas de jornadas ya identifican al trabajador por User.
--}}
@php
    $modalJornadas = 'modal-jornadas-'.$trabajadorId;
    $modalRegistrar = 'modal-registrar-jornada-'.$trabajadorId;
    $esAdmin = auth()->user()?->esAdministrador() ?? false;
@endphp

@if ($controlPeriodo === null)
    <x-ui.profile-section title="Control del periodo" icon="calendar" data-control-periodo {{ $attributes }}>
        <p class="text-sm text-text-muted">Este usuario no tiene una identidad laboral registrada todavía.</p>
    </x-ui.profile-section>
@elseif (! $controlPeriodo->controlaAsistencia)
    {{-- Rol sin control de asistencia (ADMINISTRADOR): sin QR, calendario ni registro manual. --}}
    <div data-control-periodo {{ $attributes }} class="space-y-5">
        <x-ui.profile-section title="Asistencia" icon="calendar">
            <p class="text-sm text-text-muted">No marca asistencia: cobra sueldo fijo mensual, sin faltas ni descuentos por asistencia.</p>
        </x-ui.profile-section>

        <x-pagos.attendance-summary :control-periodo="$controlPeriodo" />
    </div>
@else
    <div data-control-periodo {{ $attributes }} class="space-y-5">
        @if ($conQr)
            <x-pagos.worker-qr :qr-token="$controlPeriodo->qrToken" :nombre="$trabajadorNombre" />
        @endif

        <x-pagos.attendance-calendar :control-periodo="$controlPeriodo" :trabajador-user-id="$trabajadorId" :trabajador-nombre="$trabajadorNombre" :es-admin="$esAdmin" />

        <x-pagos.attendance-summary :control-periodo="$controlPeriodo" />

        @if ($esAdmin)
            <x-ui.profile-section title="Registro manual" icon="clipboard-list">
                <p class="text-sm text-text-muted">Para corregir un día ya marcado, hazlo desde el calendario de arriba.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <x-ui.button type="button" variant="secondary" size="sm" data-modal-open="{{ $modalJornadas }}" data-jornadas-trigger data-url-jornadas="{{ route('admin.trabajadores.jornadas.index', $trabajadorId) }}">Ver lista de jornadas</x-ui.button>
                    @if ($controlPeriodo->estaEnCurso())
                        <x-ui.button type="button" variant="primary" size="sm" data-modal-open="{{ $modalRegistrar }}"><x-ui.icon name="plus" class="h-4 w-4" /> Registrar asistencia manual</x-ui.button>
                    @endif
                </div>
            </x-ui.profile-section>
        @endif
    </div>

    <x-ui.modal id="{{ $modalJornadas }}" title="Jornadas del periodo">
        <p class="mb-3 text-sm text-text-muted">{{ $controlPeriodo->periodoRango }}</p>
        <div data-jornadas-contenido>
            <p class="text-sm text-text-muted">Cargando…</p>
        </div>
    </x-ui.modal>

    @if ($esAdmin && $controlPeriodo->estaEnCurso())
        <x-ui.modal id="{{ $modalRegistrar }}" title="Registrar asistencia manual" :open="$errors->has('jornada')">
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
