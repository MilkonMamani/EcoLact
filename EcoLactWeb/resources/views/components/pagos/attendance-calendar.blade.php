@props(['controlPeriodo', 'trabajadorUserId', 'trabajadorNombre', 'esAdmin' => false])

{{--
    Calendario mensual de asistencia de un trabajador. Los estados (PRESENTE,
    FALTA, NO_APLICABLE, FUTURO, SIN_REGISTRO) ya vienen calculados por el
    backend (ControlPeriodoTrabajador::$dias) — este componente solo los
    pinta, nunca decide por su cuenta si un día es falta o no.
--}}
@php
    $cp = $controlPeriodo;
    $diasSemana = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
    $primerDia = \DateTimeImmutable::createFromFormat('Y-m-d', $cp->periodoInicioIso);
    $offsetInicial = ((int) $primerDia->format('N')) - 1; // 0 = lunes
    $colores = [
        'PRESENTE' => 'bg-success text-white',
        'FALTA' => 'bg-danger text-white',
        'SIN_REGISTRO' => 'bg-text-muted/15 text-text-muted ring-1 ring-warning/40',
        'NO_APLICABLE' => 'bg-transparent text-text-muted/40',
        'FUTURO' => 'bg-transparent text-text-muted/60',
    ];
@endphp

<x-ui.profile-section title="Asistencia" icon="calendar" data-attendance-calendar {{ $attributes }}>
    <div class="flex items-center justify-between">
        <a
            href="{{ $cp->mesAnteriorValor ? request()->fullUrlWithQuery(['mes' => $cp->mesAnteriorValor]) : '#' }}"
            aria-label="Mes anterior"
            class="rounded-lg p-1.5 text-text-muted hover:bg-text-muted/10 hover:text-text"
        ><x-ui.icon name="chevron-left" class="h-4 w-4" /></a>

        <p class="text-sm font-semibold text-text">{{ $cp->periodoEtiqueta }}</p>

        @if ($cp->mesSiguienteValor)
            <a href="{{ request()->fullUrlWithQuery(['mes' => $cp->mesSiguienteValor]) }}" aria-label="Mes siguiente" class="rounded-lg p-1.5 text-text-muted hover:bg-text-muted/10 hover:text-text">
                <x-ui.icon name="chevron-right" class="h-4 w-4" />
            </a>
        @else
            <span class="p-1.5 text-text-muted/30" aria-hidden="true"><x-ui.icon name="chevron-right" class="h-4 w-4" /></span>
        @endif
    </div>

    <div class="mt-4 grid grid-cols-7 gap-1 text-center text-[11px] font-medium uppercase text-text-muted">
        @foreach ($diasSemana as $letra)
            <span>{{ $letra }}</span>
        @endforeach
    </div>

    <div class="mt-1 grid grid-cols-7 gap-1">
        @for ($i = 0; $i < $offsetInicial; $i++)
            <span></span>
        @endfor

        @foreach ($cp->dias as $dia)
            <button
                type="button"
                data-dia-calendario
                data-fecha="{{ $dia->fechaIso }}"
                data-dia-numero="{{ $dia->diaNumero }}"
                data-estado="{{ $dia->estado }}"
                data-hora="{{ $dia->horaMarcacion ?? '' }}"
                data-origen="{{ $dia->origenEtiqueta ?? '' }}"
                data-observacion="{{ $dia->observacion ?? '' }}"
                data-jornada-id="{{ $dia->jornadaId ?? '' }}"
                @if (! in_array($dia->estado, ['NO_APLICABLE', 'FUTURO'], true))
                    title="{{ $dia->estado === 'PRESENTE' ? 'Asistió' : ($dia->estado === 'FALTA' ? 'Falta' : ($dia->esHoy ? 'Pendiente de registro' : 'Sin registrar')) }}"
                @elseif ($dia->estado === 'NO_APLICABLE')
                    disabled
                    title="No correspondía"
                @else
                    disabled
                    title="Todavía no llega esta fecha"
                @endif
                class="flex aspect-square items-center justify-center rounded-full text-xs font-semibold transition disabled:cursor-default {{ $colores[$dia->estado] ?? 'bg-transparent text-text-muted' }} {{ ! in_array($dia->estado, ['NO_APLICABLE', 'FUTURO'], true) ? 'cursor-pointer hover:ring-2 hover:ring-primary/40' : '' }} {{ $dia->esHoy ? 'ring-2 ring-primary' : '' }}"
            >{{ $dia->diaNumero }}</button>
        @endforeach
    </div>

    <p class="mt-4 flex flex-wrap items-center gap-4 text-xs text-text-muted">
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-success"></span> Asistió</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-danger"></span> Falta</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full border border-text-muted/40"></span> No correspondía</span>
    </p>

    <div data-detalle-dia hidden class="mt-4 rounded-lg border border-text-muted/10 bg-background/60 p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p data-detalle-dia-fecha class="text-sm font-semibold text-text"></p>
                <p class="mt-1 text-xs text-text-muted">Estado: <span data-detalle-dia-estado class="font-medium text-text"></span></p>
                <p class="text-xs text-text-muted">Hora: <span data-detalle-dia-hora class="font-medium text-text"></span></p>
                <p class="text-xs text-text-muted">Origen: <span data-detalle-dia-origen class="font-medium text-text"></span></p>
                <p class="text-xs text-text-muted">Observación: <span data-detalle-dia-observacion class="font-medium text-text"></span></p>
            </div>

            @if ($esAdmin)
                <x-ui.button type="button" variant="secondary" size="sm" data-detalle-dia-corregir hidden>Corregir</x-ui.button>
            @endif
        </div>
    </div>

    @if ($esAdmin)
        <x-ui.modal id="modal-corregir-perfil-{{ $trabajadorUserId }}" title="Corregir asistencia">
            <form method="POST" data-form-corregir-perfil data-user-id="{{ $trabajadorUserId }}" data-url-base="{{ url('admin/trabajadores') }}" action="" class="space-y-4">
                @csrf
                <x-ui.data-field label="Trabajador" muted>{{ $trabajadorNombre }}</x-ui.data-field>
                <x-ui.data-field label="Fecha" muted><span data-corregir-perfil-fecha></span></x-ui.data-field>
                <x-forms.select name="estado" label="Nuevo estado" required :options="['PRESENTE' => 'Asistió', 'FALTA' => 'Falta']" placeholder="" :sticky="false" data-corregir-perfil-estado />
                <x-forms.textarea name="motivo" label="Motivo" placeholder="Explica el motivo de la corrección" :rows="2" maxlength="255" required :sticky="false" />
                <p data-corregir-perfil-error hidden class="text-sm text-danger"></p>
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                    <x-ui.button type="submit">Guardar corrección</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</x-ui.profile-section>
