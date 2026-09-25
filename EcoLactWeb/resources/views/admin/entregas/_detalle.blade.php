{{-- Contenido del modal "Ver entrega" de Acopio (fragmento sin layout, se carga por fetch). --}}
@php
    $estado = $entrega->estado()->value;
    $etiquetasEstado = ['PENDIENTE_ANALISIS' => ['Pendiente de análisis', 'clock'], 'ANALIZADA' => ['Analizada', 'check'], 'ANULADA' => ['Anulada', 'x-circle']];
    [$etiquetaEstado, $iconoEstado] = $etiquetasEstado[$estado] ?? [str($estado)->replace('_', ' ')->lower()->ucfirst()->toString(), null];
    $analisis = $registro->analisisCalidad;
    $puedeEditarLitros = $estado === 'PENDIENTE_ANALISIS'
        && $entrega->permiteEditarLitros(\Carbon\CarbonImmutable::now(config('app.timezone')))
        && auth()->user()->can('update', $registro);
@endphp

<div class="acopio-detalle" data-entrega-detalle data-entrega-id="{{ $entrega->id() }}">
    <header class="acopio-detalle-header">
        <h2 class="acopio-detalle-titulo">Ver entrega #{{ $entrega->id() }}</h2>
        <div class="acopio-detalle-acciones">
            <x-ui.badge :status="$estado" :icon="$iconoEstado">{{ $etiquetaEstado }}</x-ui.badge>
            @if ($puedeEditarLitros)
                <button type="button" class="acopio-detalle-editar" data-litros-editar>
                    <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar
                </button>
            @endif
            <button type="button" data-modal-close aria-label="Cerrar" class="acopio-detalle-cerrar">
                <x-ui.icon name="x-mark" class="h-5 w-5" />
            </button>
        </div>
    </header>

    <p class="acopio-detalle-aviso" data-litros-aviso role="status" hidden></p>

    <section class="acopio-detalle-seccion">
        <h3 class="acopio-detalle-subtitulo">Información de la entrega</h3>
        <dl class="acopio-detalle-grid">
            <div>
                <dt>Proveedor</dt>
                <dd>{{ $registro->proveedor ? trim($registro->proveedor->nombres.' '.$registro->proveedor->apellidos) : 'Proveedor #'.$entrega->proveedorId() }}</dd>
            </div>
            <div>
                <dt>DNI</dt>
                <dd>{{ $registro->proveedor?->dni ?? '—' }}</dd>
            </div>
            <div>
                <dt>Zona</dt>
                <dd>{{ $registro->zona?->nombre ?? 'Zona #'.$entrega->zonaId() }}</dd>
            </div>
            <div>
                <dt>Acopiador</dt>
                <dd>{{ $registro->acopiador ? trim($registro->acopiador->nombres.' '.$registro->acopiador->apellidos) : '—' }}</dd>
            </div>
            <div>
                <dt>Fecha y hora</dt>
                <dd>{{ $entrega->fechaHora()->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt>Litros entregados</dt>
                <dd>
                    <span class="acopio-detalle-litros" data-litros-lectura>{{ number_format($entrega->litros(), 2) }} L</span>
                    @if ($puedeEditarLitros)
                        <form method="POST" action="{{ route('admin.entregas.litros.update', $entrega->id()) }}" class="acopio-litros-form" data-litros-form novalidate hidden>
                            <div class="acopio-litros-campo">
                                <input type="number" name="litros" step="0.01" min="0.01" inputmode="decimal" value="{{ number_format($entrega->litros(), 2, '.', '') }}" aria-label="Litros entregados" required data-litros-input>
                                <span>L</span>
                            </div>
                            <p class="acopio-litros-error" data-litros-error hidden></p>
                            <div class="acopio-litros-botones">
                                <button type="button" class="acopio-btn is-outline is-sm" data-litros-cancelar>Cancelar</button>
                                <button type="submit" class="acopio-btn is-dark is-sm" data-litros-guardar>Guardar cambios</button>
                            </div>
                        </form>
                    @endif
                </dd>
            </div>
            <div>
                <dt>Método de identificación</dt>
                <dd>{{ $entrega->metodoIdentificacion() }}</dd>
            </div>
        </dl>
    </section>

    <section class="acopio-detalle-seccion">
        <h3 class="acopio-detalle-subtitulo">Resumen del registro</h3>
        <dl class="acopio-detalle-grid">
            <div>
                <dt>Registrado por</dt>
                <dd>{{ $registro->registradoPor?->username ?? '—' }}</dd>
            </div>
            <div>
                <dt>Fecha de registro</dt>
                <dd>{{ $registro->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            </div>
        </dl>
    </section>

    <section class="acopio-detalle-seccion">
        <h3 class="acopio-detalle-subtitulo">Estado del flujo</h3>
        <ol class="acopio-stepper">
            <li class="is-hecho">
                <span class="acopio-stepper-punto"><x-ui.icon name="check" class="h-3.5 w-3.5" /></span>
                <span>Entrega registrada</span>
            </li>
            @if ($estado === 'ANULADA')
                <li class="is-anulado">
                    <span class="acopio-stepper-punto"><x-ui.icon name="x-mark" class="h-3.5 w-3.5" /></span>
                    <span>Entrega anulada</span>
                </li>
            @else
                <li class="{{ $estado === 'ANALIZADA' ? 'is-hecho' : 'is-actual' }}">
                    <span class="acopio-stepper-punto">
                        @if ($estado === 'ANALIZADA')
                            <x-ui.icon name="check" class="h-3.5 w-3.5" />
                        @else
                            <x-ui.icon name="clock" class="h-3.5 w-3.5" />
                        @endif
                    </span>
                    <span>Análisis de calidad</span>
                </li>
            @endif
        </ol>
    </section>

    <section class="acopio-detalle-seccion">
        <h3 class="acopio-detalle-subtitulo">Análisis de calidad</h3>
        @if ($analisis?->estado === 'CONFIRMADO' && $analisis->resultado)
            <div class="acopio-detalle-analisis">
                <x-ui.badge :status="$analisis->resultado" />
                <span>{{ $analisis->fecha_analisis?->format('d/m/Y H:i') }}</span>
                <a href="{{ route('admin.entregas.analisis-calidad.index', $entrega->id()) }}">Ver análisis completo →</a>
            </div>
        @else
            <p class="acopio-detalle-vacio">Esta entrega todavía no tiene un análisis de calidad asociado.</p>
        @endif
    </section>
</div>
