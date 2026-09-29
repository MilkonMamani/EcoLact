@props(['controlPeriodo', 'modalJornadas' => null, 'urlJornadas' => null, 'etiquetaJornadas' => 'Ver jornadas'])

{{--
    Pestaña Asistencia de las fichas de personal: calendario compacto de círculos, resumen del
    mes y detalle del día elegido. Solo pinta ControlPeriodoTrabajador (estados de cada día ya
    resueltos por el backend); el detalle lo rellena admin-jornadas.js con los data-* del día.
--}}
@php
    $cp = $controlPeriodo;
    $diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    $offsetInicial = $cp ? (int) \DateTimeImmutable::createFromFormat('Y-m-d', $cp->periodoInicioIso)->format('w') : 0;
    $noCorrespondia = $cp ? collect($cp->dias)->where('estado', 'NO_APLICABLE')->count() : 0;
    $titulos = ['PRESENTE' => 'Asistió', 'FALTA' => 'Falta', 'SIN_REGISTRO' => 'Pendiente (hasta las 07:00)', 'NO_APLICABLE' => 'No correspondía', 'FUTURO' => 'Todavía no llega esta fecha'];
@endphp

<div class="fichas-asistencia" data-asistencia-mensual {{ $attributes }}>
    <h3 class="fichas-asistencia-titulo">Asistencia</h3>

    @if ($cp === null)
        <p class="fichas-panel-sin-datos">Este trabajador no tiene una identidad laboral registrada todavía.</p>
    @else
        <div class="fichas-asistencia-mes">
            <a href="{{ request()->fullUrlWithQuery(['mes' => $cp->mesAnteriorValor]) }}" aria-label="Mes anterior" title="Mes anterior"><x-ui.icon name="chevron-left" class="h-4 w-4" /></a>
            <p>{{ $cp->periodoEtiqueta }}</p>
            @if ($cp->mesSiguienteValor)
                <a href="{{ request()->fullUrlWithQuery(['mes' => $cp->mesSiguienteValor]) }}" aria-label="Mes siguiente" title="Mes siguiente"><x-ui.icon name="chevron-right" class="h-4 w-4" /></a>
            @else
                <span aria-hidden="true"><x-ui.icon name="chevron-right" class="h-4 w-4" /></span>
            @endif
        </div>

        <div class="fichas-calendario" aria-label="Asistencia de {{ $cp->periodoEtiqueta }}">
            @foreach ($diasSemana as $dia)
                <span class="fichas-calendario-semana" aria-hidden="true">{{ $dia }}</span>
            @endforeach

            @for ($i = 0; $i < $offsetInicial; $i++)
                <span aria-hidden="true"></span>
            @endfor

            @foreach ($cp->dias as $dia)
                <button
                    type="button"
                    class="fichas-dia"
                    data-dia-asistencia
                    data-fecha="{{ $dia->fechaIso }}"
                    data-estado="{{ $dia->estado }}"
                    data-hora="{{ $dia->horaMarcacion ?? '' }}"
                    data-origen="{{ $dia->origenEtiqueta ?? '' }}"
                    data-observacion="{{ $dia->observacion ?? '' }}"
                    @if ($dia->esHoy) data-hoy aria-current="date" @endif
                    @if ($dia->estado === 'FUTURO') disabled @endif
                    title="{{ $titulos[$dia->estado] ?? '' }}"
                    aria-label="{{ $dia->diaNumero }}: {{ $titulos[$dia->estado] ?? '' }}"
                >{{ $dia->diaNumero }}</button>
            @endforeach
        </div>

        <div class="fichas-asistencia-bloque">
            <h4>Resumen del mes</h4>
            <dl class="fichas-asistencia-resumen">
                <div class="is-presente"><dt>Asistencias</dt><dd><span aria-hidden="true">✓</span> {{ $cp->asistencias }}</dd></div>
                <div class="is-falta"><dt>Faltas</dt><dd><span aria-hidden="true">✕</span> {{ $cp->faltas }}</dd></div>
                <div class="is-neutro"><dt>No correspondía</dt><dd><span aria-hidden="true">—</span> {{ $noCorrespondia }}</dd></div>
            </dl>
        </div>

       
    @endif
</div>
