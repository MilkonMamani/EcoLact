{{--
    Panel derecho de analistas de calidad, encargados de planta y personal de ventas: el mismo
    de Acopiadores (cabecera, pestañas, QR + información personal, Asistencia y Pagos con los
    componentes compartidos). Solo la pestaña operacional cambia según el rol; sin zona.

    @var \App\Application\Trabajador\FichaPersonalPanelData $panel
    @var array $config
--}}
@php
    $tipo = $panel->tipo->value;
    $pestanas = ['resumen' => 'Resumen', 'actividad' => $config['actividad'], 'asistencia' => 'Asistencia', 'pagos' => 'Pagos'];
    $modalEditar = 'modal-editar-ficha-'.$panel->id;
    $modalJornadas = 'modal-jornadas-ficha-'.$panel->id;
    $limite = \App\Infrastructure\Persistence\Queries\EloquentFichasPersonalQuery::LIMITE;
    $columnasResumen = count($panel->metricas) === 4 ? 'is-2' : null;
@endphp

<div class="fichas-panel-contenido" data-fichas-contenido data-ficha-id="{{ $panel->id }}" data-estado="{{ $panel->estado }}">
    <x-fichas.panel-cabecera :ficha="$panel" />

    <x-fichas.panel-tabs :pestanas="$pestanas" :etiqueta="'Información del '.$config['entidad']" />

    <div class="fichas-panel-cuerpo">
        {{-- ------------------------------------------------------------ Resumen --}}
        <section id="ficha-panel-resumen" role="tabpanel" aria-labelledby="ficha-tab-resumen" data-fichas-tab-panel="resumen">
            <x-fichas.datos-personales :ficha="$panel" :modal-editar="$modalEditar" />

            <dl @class(['fichas-resumen', $columnasResumen])>
                @foreach ($panel->metricas as $metrica)
                    <div><dt>{{ $metrica['etiqueta'] }}</dt><dd class="is-num">{{ $metrica['valor'] }}</dd></div>
                @endforeach
            </dl>
        </section>

        {{-- ------------------------------------------------------------ Actividad del rol --}}
        <section id="ficha-panel-actividad" role="tabpanel" aria-labelledby="ficha-tab-actividad" data-fichas-tab-panel="actividad" hidden>
            @include('admin.fichas-personal._actividad-'.$tipo, ['panel' => $panel, 'config' => $config])

            @if ($panel->actividadTotal > $limite)
                <p class="fichas-panel-pie">Últimos {{ $limite }} de {{ $panel->actividadTotal }} registros.</p>
            @endif
        </section>

        {{-- ------------------------------------------------------------ Asistencia --}}
        <section id="ficha-panel-asistencia" role="tabpanel" aria-labelledby="ficha-tab-asistencia" data-fichas-tab-panel="asistencia" hidden>
            <x-fichas.asistencia :control-periodo="$controlPeriodo" :modal-jornadas="$modalJornadas" :url-jornadas="route('admin.trabajadores.jornadas.index', $panel->userId)" :etiqueta-jornadas="'Ver jornadas de este '.$config['entidad']" />
        </section>

        {{-- ------------------------------------------------------------ Pagos --}}
        <section id="ficha-panel-pagos" role="tabpanel" aria-labelledby="ficha-tab-pagos" data-fichas-tab-panel="pagos" hidden>
            <x-pagos.pago-mensual :control-periodo="$controlPeriodo" :dni="$panel->dni" />
        </section>
    </div>
</div>

{{-- Jornadas del periodo (lo rellena admin-jornadas.js al abrirlo desde la pestaña Asistencia). --}}
<x-ui.modal :id="$modalJornadas" title="Jornadas del periodo">
    @if ($controlPeriodo)
        <p class="mb-3 text-sm text-text-muted">{{ $controlPeriodo->periodoRango }}</p>
    @endif
    <div data-jornadas-contenido>
        <p class="text-sm text-text-muted">Cargando…</p>
    </div>
</x-ui.modal>

<x-fichas.modal-editar-personal :ficha="$panel" :id="$modalEditar" :action="route($config['rutas'].'.update', $panel->id)" :entidad="$config['entidad']" />
