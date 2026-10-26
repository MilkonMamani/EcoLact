{{--
    Panel lateral de un parámetro: QUÉ se mide (información general) y su REGLA actual (rango
    aceptado), cada una con su único botón de edición dentro de su sección (la tabla no edita).
    Solo se muestra la regla vigente hoy, la misma con la que el análisis evalúa; las
    reglas anteriores se conservan como historial y no se listan. Configurar/editar la regla usa los
    endpoints existentes admin.reglas-calidad.store / update (sin duplicar lógica). La prioridad no
    se muestra: al crear toma el valor por defecto y al editar se conserva.
    $panel: array de ParametrosCalidadListadoQuery::panel() (o null si el parámetro ya no existe).
--}}
@php
    $puedeGestionar = auth()->user()->can('create', \App\Infrastructure\Persistence\Models\ParametroCalidadModel::class);
    $opcionesResultado = ['ACEPTADA' => 'Aceptada', 'OBSERVADA' => 'Observada', 'RECHAZADA' => 'Rechazada'];
    $opcionesEstado = ['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo'];
    $fecha = fn (string $fecha): string => \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y');
@endphp

@if ($panel === null)
    @include('admin.parametros-calidad._panel-vacio')
@else
    @php
        $unidad = $panel['unidad'];
        $regla = $panel['reglaActual'];
        $activo = $panel['estado'] === 'ACTIVO';
        $subtituloModal = $panel['nombre'].($unidad ? ' · '.$unidad : '');
    @endphp

    <div class="fichas-panel-contenido" data-fichas-contenido data-ficha-id="{{ $panel['id'] }}">
        <div class="fichas-panel-head">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="fichas-panel-nombre mt-0">{{ $panel['nombre'] }}</h2>
                    <x-ui.badge :status="$panel['estado']">{{ $activo ? 'Activo' : 'Inactivo' }}</x-ui.badge>
                </div>
                <p class="parametros-panel-descripcion">{{ $unidad ?? 'Sin unidad' }}</p>
            </div>
            <button type="button" class="fichas-cerrar" data-fichas-cerrar aria-label="Cerrar detalle">
                <x-ui.icon name="x-mark" class="h-5 w-5" />
            </button>
        </div>

        <div class="fichas-panel-cuerpo">
            <section class="fichas-seccion is-primera">
                <div class="fichas-seccion-head">
                    <h3>Información general</h3>
                </div>
                <p class="parametros-nota">Qué se mide: nombre, unidad, estado y descripción.</p>
                <dl class="fichas-datos">
                    <div>
                        <dt>Código</dt>
                        <dd class="font-mono">{{ $panel['codigo'] }}</dd>
                    </div>
                    <div>
                        <dt>Nombre</dt>
                        <dd>{{ $panel['nombre'] }}</dd>
                    </div>
                    <div>
                        <dt>Unidad</dt>
                        <dd @class(['is-muted' => ! $unidad])>{{ $unidad ?? 'Sin unidad' }}</dd>
                    </div>
                    <div>
                        <dt>Estado</dt>
                        <dd>{{ $activo ? 'Activo' : 'Inactivo' }}</dd>
                    </div>
                    <div class="is-ancho">
                        <dt>Última actualización</dt>
                        <dd class="is-num">{{ $panel['actualizado'] ?? '—' }}</dd>
                    </div>
                    @if ($panel['descripcion'])
                        <div class="is-ancho">
                            <dt>Descripción</dt>
                            <dd>{{ $panel['descripcion'] }}</dd>
                        </div>
                    @endif
                </dl>
                @if ($puedeGestionar)
                    <button type="button" class="fichas-btn is-outline is-sm mt-4" data-modal-open="modal-editar-parametro-{{ $panel['id'] }}">
                        <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar parámetro
                    </button>
                @endif
            </section>

            <section class="fichas-seccion" data-regla-actual>
                <div class="fichas-seccion-head">
                    <h3>Regla de evaluación</h3>
                </div>
                <p class="parametros-nota">Cuál es el rango aceptado para el valor medido.</p>

                @if ($regla)
                    <dl class="fichas-datos">
                        <div>
                            <dt>Rango aceptado</dt>
                            <dd class="is-num font-semibold">{{ $regla['rango'] }}</dd>
                        </div>
                        <div>
                            <dt>Si incumple</dt>
                            <dd><x-ui.badge :status="$regla['resultadoIncumplimiento']">{{ $opcionesResultado[$regla['resultadoIncumplimiento']] ?? $regla['resultadoIncumplimiento'] }}</x-ui.badge></dd>
                        </div>
                        <div>
                            <dt>Vigencia</dt>
                            <dd class="is-num">{{ $fecha($regla['fechaInicio']) }} – {{ $regla['fechaFin'] ? $fecha($regla['fechaFin']) : 'Sin fecha fin' }}</dd>
                        </div>
                        <div>
                            <dt>Estado</dt>
                            <dd>{{ $regla['estado'] === 'ACTIVO' ? 'Activa' : 'Inactiva' }}</dd>
                        </div>
                    </dl>
                    @if ($puedeGestionar)
                        <button type="button" class="fichas-btn is-outline is-sm mt-4" data-modal-open="modal-regla-{{ $panel['id'] }}">
                            <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar regla
                        </button>
                    @endif
                @else
                    <div class="fichas-panel-sin-datos parametros-sin-regla">
                        <p class="font-semibold text-text">Sin configurar</p>
                        <p>
                            {{ $panel['tieneHistorial']
                                ? 'Su regla anterior ya no está vigente. Define el rango que se aplicará desde ahora.'
                                : 'Este parámetro todavía no tiene un rango de evaluación definido.' }}
                        </p>
                        @if ($puedeGestionar)
                            <button type="button" class="fichas-btn is-primary is-sm mt-2" data-modal-open="modal-regla-{{ $panel['id'] }}">
                                <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Configurar regla
                            </button>
                        @endif
                    </div>
                @endif
            </section>
        </div>

        @if ($puedeGestionar)
            @php
                $claveRegla = 'regla-'.$panel['id'];
                $sticky = old('_modal') === $claveRegla;
            @endphp
            <x-ui.modal :id="'modal-'.$claveRegla" size="form" :title="$regla ? 'Editar regla de calidad' : 'Configurar regla de calidad'" :subtitle="$subtituloModal" :open="$sticky && $errors->any()">
                <form method="POST" action="{{ $regla ? route('admin.reglas-calidad.update', $regla['id']) : route('admin.reglas-calidad.store') }}">
                    @csrf
                    @if ($regla)
                        @method('PATCH')
                    @else
                        <input type="hidden" name="parametro_id" value="{{ $panel['id'] }}">
                    @endif
                    <input type="hidden" name="_modal" value="{{ $claveRegla }}">
                    <div class="parametros-campos">
                        <x-forms.input name="valor_minimo" :id="$claveRegla.'-minimo'" type="number" step="any" label="Valor mínimo" :sticky="$sticky" :value="$regla['valorMinimo'] ?? null" />
                        <x-forms.input name="valor_maximo" :id="$claveRegla.'-maximo'" type="number" step="any" label="Valor máximo" :sticky="$sticky" :value="$regla['valorMaximo'] ?? null" />
                        <div class="is-ancho">
                            <x-forms.select name="resultado_incumplimiento" :id="$claveRegla.'-resultado'" label="Resultado si incumple" required :placeholder="null" :sticky="$sticky" :options="$opcionesResultado" :value="$regla['resultadoIncumplimiento'] ?? 'RECHAZADA'" />
                        </div>
                        <x-forms.input name="fecha_inicio" :id="$claveRegla.'-inicio'" type="date" label="Fecha inicio" required :sticky="$sticky" :value="$regla['fechaInicio'] ?? now()->toDateString()" />
                        <x-forms.input name="fecha_fin" :id="$claveRegla.'-fin'" type="date" label="Fecha fin" :sticky="$sticky" :value="$regla['fechaFin'] ?? null" />
                        <x-forms.select name="estado" :id="$claveRegla.'-estado'" label="Estado" required :placeholder="null" :sticky="$sticky" :options="$opcionesEstado" :value="$regla['estado'] ?? 'ACTIVO'" />
                    </div>
                    <div class="parametros-modal-pie">
                        <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                        <x-ui.button type="submit">{{ $regla ? 'Guardar cambios' : 'Guardar regla' }}</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        @endif
    </div>
@endif
