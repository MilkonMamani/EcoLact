{{-- Contenido del modal "Ver análisis" de Calidad (fragmento sin layout, se carga por fetch). --}}
@php
    $proveedor = $entrega->proveedor;
    $estado = $analisis?->estado ?? 'PENDIENTE';
    $confirmado = $estado === 'CONFIRMADO';
    $resultado = $confirmado ? $analisis->resultado : null;
    $resultados = ['ACEPTADA' => 'Aceptada', 'OBSERVADA' => 'Observada', 'RECHAZADA' => 'Rechazada'];
    $estados = [
        'PENDIENTE' => ['Sin análisis', 'clock', 'is-pendiente'],
        'BORRADOR' => ['Borrador (sin confirmar)', 'pen-line', 'is-borrador'],
        'CONFIRMADO' => ['Confirmado', 'check-double', 'is-confirmado'],
    ];
    [$etiquetaEstado, $iconoEstado, $claseEstado] = $estados[$estado] ?? [$estado, 'info', 'is-borrador'];
    $confirmador = $analisis?->confirmadoPor;
    $analistaNombre = match (true) {
        ! $confirmado || $confirmador === null => null,
        $confirmador->analistaCalidad !== null => trim($confirmador->analistaCalidad->nombres.' '.$confirmador->analistaCalidad->apellidos),
        default => $confirmador->username,
    };
@endphp

<div class="acopio-detalle calidad-detalle" data-analisis-detalle>
    <header class="acopio-detalle-header">
        <h2 class="acopio-detalle-titulo">
            {{ $analisis ? 'Análisis #A-'.str_pad((string) $analisis->id, 6, '0', STR_PAD_LEFT) : 'Entrega #'.$entrega->id }}
        </h2>
        <div class="acopio-detalle-acciones">
            <span class="calidad-estado-etiqueta {{ $claseEstado }}">
                <x-ui.icon :name="$iconoEstado" class="h-4 w-4" /> {{ $etiquetaEstado }}
            </span>
            <button type="button" data-modal-close aria-label="Cerrar" class="acopio-detalle-cerrar">
                <x-ui.icon name="x-mark" class="h-5 w-5" />
            </button>
        </div>
    </header>

    <section class="acopio-detalle-seccion">
        <h3 class="acopio-detalle-subtitulo">Entrega</h3>
        <dl class="acopio-detalle-grid">
            <div>
                <dt>Proveedor</dt>
                <dd>{{ $proveedor ? trim($proveedor->nombres.' '.$proveedor->apellidos) : '—' }}</dd>
            </div>
            <div>
                <dt>DNI</dt>
                <dd>{{ $proveedor?->dni ?? '—' }}</dd>
            </div>
            <div>
                <dt>Entrega</dt>
                <dd>#{{ $entrega->id }} · {{ $entrega->fecha_hora?->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt>Zona</dt>
                <dd>{{ $entrega->zona?->nombre ?? '—' }}</dd>
            </div>
            <div>
                <dt>Litros</dt>
                <dd class="acopio-detalle-litros">{{ number_format((float) $entrega->litros, 2) }} L</dd>
            </div>
            @if ($analisis)
                <div>
                    <dt>Analista</dt>
                    <dd>{{ $analistaNombre ?? 'Pendiente de confirmación' }}</dd>
                </div>
                <div>
                    <dt>Fecha de análisis</dt>
                    <dd>{{ $analisis->fecha_analisis?->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
            @endif
        </dl>
    </section>

    @if (! $analisis)
        <section class="acopio-detalle-seccion">
            <div class="calidad-detalle-pendiente">
                <x-ui.icon name="clock" class="h-5 w-5" />
                <div>
                    <p class="font-semibold">Sin análisis de calidad</p>
                    <p>Esta entrega todavía no tiene análisis de calidad. El análisis es opcional: la entrega igual se acopia y se paga.</p>
                </div>
            </div>
            @can('create', \App\Infrastructure\Persistence\Models\AnalisisCalidadModel::class)
                <a href="{{ route('admin.entregas.analisis-calidad.index', $entrega->id) }}" class="acopio-btn is-dark calidad-detalle-accion">
                    <x-ui.icon name="beaker" class="h-4 w-4" /> Registrar análisis
                </a>
            @endcan
        </section>
    @else
        <section class="acopio-detalle-seccion">
            <h3 class="acopio-detalle-subtitulo">Resultados de calidad</h3>
            @if ($analisis->detalles->isEmpty())
                <p class="acopio-detalle-vacio">Sin parámetros registrados.</p>
            @else
                <table class="calidad-parametros">
                    <thead>
                        <tr>
                            <th scope="col">Parámetro</th>
                            <th scope="col" class="is-num">Valor</th>
                            <th scope="col" class="is-cumple">Cumple</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($analisis->detalles->sortBy(fn ($detalle) => $detalle->parametro?->nombre) as $detalle)
                            <tr>
                                <td>{{ $detalle->parametro?->nombre ?? 'Parámetro #'.$detalle->parametro_id }}</td>
                                <td class="is-num">{{ rtrim(rtrim(number_format((float) $detalle->valor, 4, '.', ''), '0'), '.') }} <span class="is-unidad">{{ $detalle->parametro?->unidad }}</span></td>
                                <td class="is-cumple">
                                    @if ($detalle->cumple === null)
                                        <span class="is-unidad">—</span>
                                    @elseif ($detalle->cumple)
                                        <span class="calidad-resultado is-aceptada" title="Cumple"><x-ui.icon name="check" class="h-4 w-4" /><span class="sr-only">Sí</span></span>
                                    @else
                                        <span class="calidad-resultado is-rechazada" title="No cumple"><x-ui.icon name="x-mark" class="h-4 w-4" /><span class="sr-only">No</span></span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="acopio-detalle-seccion">
            <h3 class="acopio-detalle-subtitulo">Resultado final</h3>
            <dl class="acopio-detalle-grid">
                <div>
                    <dt>Resultado</dt>
                    <dd>
                        @if ($resultado)
                            <span class="calidad-resultado is-{{ strtolower($resultado) }} is-mayuscula">{{ $resultados[$resultado] ?? $resultado }}</span>
                        @else
                            <span class="is-unidad">Sin resultado hasta confirmar</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>{{ $confirmado ? 'Confirmado' : 'Borrador (sin confirmar)' }}</dd>
                </div>
                <div class="calidad-detalle-completo">
                    <dt>Observación</dt>
                    <dd>{{ $analisis->observacion ?: 'Sin observaciones registradas.' }}</dd>
                </div>
            </dl>

            <div class="calidad-detalle-pie">
                @if (! $confirmado && auth()->user()->can('update', $analisis))
                    <a href="{{ route('admin.analisis-calidad.show', $analisis->id) }}" class="acopio-btn is-dark is-sm">Revisar y confirmar</a>
                @else
                    <a href="{{ route('admin.analisis-calidad.show', $analisis->id) }}" class="calidad-detalle-enlace">Ver evidencia y seguimiento →</a>
                @endif
            </div>
        </section>
    @endif
</div>
