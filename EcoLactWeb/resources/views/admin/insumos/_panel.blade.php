{{--
    Panel lateral de un insumo: información general y stock disponible (con su mínimo y unidad base),
    presentaciones comerciales (cómo se compra; no tocan el stock), últimos movimientos (cada ingreso por
    compra enlaza con SU compra), consumos recientes de producción y acciones administrativas.
    La LECHE se gestiona sola (Acopio la sube, Producción la baja): no muestra compra ni presentaciones.
    Los botones abren modales (_modales-panel, _modales-insumo); "Comprar este insumo" no registra nada
    aquí: lleva a Compras y gastos con el insumo elegido.
    $panel: array de ObtenerPanelInsumo.
--}}
@use('App\Domain\Produccion\UnidadMedida')
@php
    $insumo = $panel['insumo'];
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $unidad = $insumo->unidadBase();
    $simbolo = UnidadMedida::simbolo($unidad);
    $nivel = $insumo->nivelStock();
    $puedeGestionar = auth()->user()->can('create', \App\Infrastructure\Persistence\Models\InsumoModel::class);
    $puedeComprar = auth()->user()->can('create', \App\Infrastructure\Persistence\Models\CompraInsumoModel::class);
    $consumos = array_values(array_filter($panel['movimientos'], fn ($m) => $m['clase'] === 'consumo'));
    $activas = array_filter($panel['presentaciones'], fn ($p) => $p->estaActiva());
    $comprable = ! $insumo->esLeche() && count($activas) > 0 && $insumo->estado() === 'ACTIVO';
    $clasesMovimiento = ['compra' => 'Compra', 'ajuste' => 'Ajuste', 'consumo' => 'Producción', 'acopio' => 'Acopio', 'otro' => 'Otro'];
    $unidadesCompatibles = UnidadMedida::opcionesCompatiblesCon($unidad);
    $bloqueos = $panel['unidad_bloqueos'];
    $datosEdicion = [
        'id' => $insumo->id(),
        'nombre' => $insumo->nombre(),
        'descripcion' => $insumo->descripcion(),
        'stock_minimo' => $insumo->stockMinimo(),
        'tipo' => $insumo->tipo()->value,
        'estado' => $insumo->estado(),
        'del_sistema' => $insumo->esDelSistema(),
        'unidad_base' => $unidad,
        'unidad_simbolo' => $simbolo,
        'unidad_etiqueta' => UnidadMedida::etiqueta($unidad),
        'unidad_editable' => $bloqueos === [],
        'unidad_motivo' => $bloqueos === [] ? null : 'No se puede cambiar porque '.implode(', ', $bloqueos).'.',
    ];
    $datosPresentacion = fn (?\App\Domain\Produccion\PresentacionInsumo $p) => [
        'titulo' => $p ? 'Editar presentación de compra' : 'Agregar presentación de compra',
        'subtitulo' => $insumo->nombre().' se controla en '.mb_strtolower(UnidadMedida::etiqueta($unidad)).'.',
        'insumo' => $insumo->nombre(),
        'action' => $p ? route('admin.insumos.presentaciones.update', [$insumo->id(), $p->id()]) : route('admin.insumos.presentaciones.store', $insumo->id()),
        'metodo' => $p ? 'PATCH' : 'POST',
        'nombre' => $p?->nombre() ?? '',
        'cantidad' => $p?->cantidad() ?? '',
        'unidad' => $p?->unidad() ?? $unidad,
        'estado' => $p?->estado() ?? 'ACTIVA',
        'unidad_base' => $simbolo,
        'unidades' => $unidadesCompatibles,
        // Solo para la vista previa de la equivalencia; el servidor la recalcula al guardar.
        'factores' => array_map(fn ($codigo) => UnidadMedida::convertir(1.0, $codigo, $unidad), array_combine(array_keys($unidadesCompatibles), array_keys($unidadesCompatibles))),
        'editar' => $p !== null,
    ];
@endphp

<div class="fichas-panel-contenido" data-fichas-contenido data-ficha-id="{{ $insumo->id() }}">
    <div class="fichas-panel-head">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="fichas-panel-nombre mt-0">{{ $insumo->nombre() }}</h2>
                @if ($insumo->esDelSistema())
                    <span class="insumos-sistema">Del sistema</span>
                @endif
                @if ($insumo->estado() !== 'ACTIVO')
                    <x-ui.badge compact status="INACTIVO">Inactivo</x-ui.badge>
                @endif
            </div>
            <p class="mt-1 text-sm text-text-muted">{{ $insumo->tipo()->etiqueta() }} · {{ UnidadMedida::descripcionControl($unidad) }}</p>
        </div>
        <button type="button" class="fichas-cerrar" data-fichas-cerrar aria-label="Cerrar detalle">
            <x-ui.icon name="x-mark" class="h-5 w-5" />
        </button>
    </div>

    <div class="fichas-panel-cuerpo">
        {{-- 1. Stock disponible --}}
        <section class="fichas-seccion is-primera">
            <div @class(['insumos-stock', 'is-'.strtolower($nivel)])>
                <div class="min-w-0">
                    <p class="insumos-stock-rotulo">Stock disponible</p>
                    <p class="insumos-stock-valor">{{ $fmt($insumo->stockActual()) }} <span>{{ $simbolo }}</span></p>
                    <p class="insumos-stock-minimo">Stock mínimo: <strong>{{ $fmt($insumo->stockMinimo()) }} {{ $simbolo }}</strong></p>
                </div>
                @include('admin.insumos._nivel', ['nivel' => $nivel])
            </div>
            <dl class="fichas-datos mt-3">
                <div>
                    <dt>Categoría</dt>
                    <dd>{{ $insumo->tipo()->etiqueta() }}</dd>
                </div>
                <div>
                    <dt>Unidad base</dt>
                    <dd>{{ UnidadMedida::etiqueta($unidad) }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>{{ $insumo->estado() === 'ACTIVO' ? 'Activo' : 'Inactivo' }}</dd>
                </div>
                @if ($insumo->descripcion())
                    <div class="is-ancho">
                        <dt>Descripción</dt>
                        <dd>{{ $insumo->descripcion() }}</dd>
                    </div>
                @endif
            </dl>

            @if ($insumo->esLeche())
                <div class="mt-4 rounded-lg bg-info/10 px-3 py-2.5 text-sm ring-1 ring-inset ring-info/20">
                    <p class="flex items-center gap-1.5 font-semibold text-info"><x-ui.icon name="droplet" class="h-4 w-4" /> Gestionada automáticamente</p>
                    <p class="mt-0.5 text-text-muted">Su stock aumenta desde Acopio y disminuye desde Producción. La leche no se compra.</p>
                </div>
            @endif
        </section>

        {{-- 2. Presentaciones de compra --}}
        @unless ($insumo->esLeche())
            <section class="fichas-seccion" data-presentaciones>
                <div class="fichas-seccion-head">
                    <h3>Presentaciones comerciales</h3>
                    @if ($puedeGestionar && count($panel['presentaciones']) > 0)
                        <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-1">
                            <button type="button" class="fichas-editar" data-presentaciones-editar aria-pressed="false">
                                <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> <span data-presentaciones-editar-texto>Editar</span>
                            </button>
                            <button type="button" class="fichas-editar" data-modal-open="modal-presentacion" data-presentacion="{{ json_encode($datosPresentacion(null)) }}">
                                <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Agregar presentación
                            </button>
                        </div>
                    @endif
                </div>

                @if (count($panel['presentaciones']) === 0)
                    <div class="insumos-vacio">
                        <span class="insumos-vacio-icono"><x-ui.icon name="package" class="h-5 w-5" /></span>
                        <p class="text-sm font-medium text-text">Este insumo todavía no tiene presentaciones de compra.</p>
                        <p class="mt-1 text-xs text-text-muted">Agrega cómo lo compras habitualmente, por ejemplo, saco de 25 kg.</p>
                        @if ($puedeGestionar)
                            <button type="button" class="fichas-btn is-primary is-sm mt-3" data-modal-open="modal-presentacion" data-presentacion="{{ json_encode($datosPresentacion(null)) }}">
                                <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Agregar presentación
                            </button>
                        @endif
                    </div>
                @else
                    <ul class="insumos-presentaciones">
                        @foreach ($panel['presentaciones'] as $presentacion)
                            <li @class(['is-inactiva' => ! $presentacion->estaActiva()])>
                                <span class="insumos-presentacion-icono"><x-ui.icon name="package" class="h-4 w-4" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-text">
                                        {{ $presentacion->etiqueta() }}
                                        @unless ($presentacion->estaActiva())
                                            <span class="ml-1 text-xs font-normal text-text-muted">Inactiva</span>
                                        @endunless
                                    </p>
                                    <p class="text-xs text-text-muted">
                                        1 {{ mb_strtolower($presentacion->nombre()) }} = {{ $fmt($presentacion->cantidad()) }} {{ UnidadMedida::simbolo($presentacion->unidad()) }}@if ($presentacion->unidad() !== $unidad) = {{ $fmt($presentacion->cantidadBase()) }} {{ $simbolo }}@endif de {{ $insumo->nombre() }}
                                    </p>
                                </div>
                                @if ($puedeGestionar)
                                    <button type="button" class="fichas-editar" data-presentacion-fila-editar hidden data-modal-open="modal-presentacion" data-presentacion="{{ json_encode($datosPresentacion($presentacion)) }}" aria-label="Editar presentación {{ $presentacion->etiqueta() }}">
                                        <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-text-muted">Al comprar, cada presentación se convierte sola a {{ $simbolo }}.</p>
                @endif
            </section>
        @endunless

        {{-- 3. Últimos movimientos --}}
        <section class="fichas-seccion">
            <div class="fichas-seccion-head">
                <h3>Últimos movimientos</h3>
                @if ($insumo->esLeche())
                    <a href="{{ route('admin.entregas.index') }}" class="text-xs font-medium text-primary hover:text-primary-dark" title="Los ingresos de leche se consultan en Acopio">Entregas en Acopio</a>
                @else
                    <a href="{{ route('admin.insumos.show', $insumo->id()) }}" class="text-xs font-medium text-primary hover:text-primary-dark">Ver historial</a>
                @endif
            </div>

            @if (count($panel['movimientos']) === 0)
                <p class="text-sm text-text-muted">Todavía no tiene movimientos. Aparecerán aquí las compras, consumos, mermas y ajustes.</p>
            @else
                <ul class="divide-y divide-text-muted/10">
                    @foreach ($panel['movimientos'] as $movimiento)
                        <li class="flex items-start justify-between gap-3 py-2">
                            <div class="min-w-0 text-sm">
                                <p class="flex items-center gap-2 text-text">
                                    <span class="insumos-mov is-{{ $movimiento['clase'] }}">{{ $clasesMovimiento[$movimiento['clase']] }}</span>
                                    <span class="truncate">{{ $movimiento['motivo'] }}</span>
                                </p>
                                <p class="mt-0.5 truncate text-xs text-text-muted">
                                    @if ($movimiento['compra_id'])
                                        <a href="{{ route('admin.compras.show', $movimiento['compra_id']) }}" class="font-medium text-primary hover:text-primary-dark" title="Ver la compra que originó este ingreso">{{ $movimiento['referencia'] }}</a> ·
                                    @elseif ($movimiento['referencia'])
                                        {{ $movimiento['referencia'] }} ·
                                    @endif
                                    {{ $movimiento['fecha']->format('d/m/Y H:i') }}
                                </p>
                            </div>
                            <span class="whitespace-nowrap text-sm font-semibold {{ $movimiento['entrada'] ? 'text-success' : 'text-danger' }}">{{ $movimiento['entrada'] ? '+' : '−' }}{{ $fmt($movimiento['cantidad']) }} {{ $simbolo }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- 4. Consumos recientes (producción) --}}
        @if (count($consumos) > 0)
            <section class="fichas-seccion">
                <div class="fichas-seccion-head">
                    <h3>Consumos recientes</h3>
                </div>
                <ul class="divide-y divide-text-muted/10">
                    @foreach ($consumos as $consumo)
                        <li class="flex items-start justify-between gap-3 py-2 text-sm">
                            <span class="min-w-0 truncate text-text">{{ $consumo['referencia'] ?? $consumo['motivo'] }} <span class="text-xs text-text-muted">· {{ $consumo['fecha']->format('d/m/Y') }}</span></span>
                            <span class="whitespace-nowrap font-semibold text-danger">−{{ $fmt($consumo['cantidad']) }} {{ $simbolo }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- 5. Acciones --}}
        @if ($puedeGestionar)
            <section class="fichas-seccion">
                <div class="fichas-seccion-head">
                    <h3>Acciones</h3>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" class="fichas-btn is-outline is-sm" data-modal-open="modal-editar-insumo" data-insumo-editar data-insumo="{{ json_encode($datosEdicion) }}" aria-label="Editar {{ $insumo->nombre() }}">
                        <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar insumo
                    </button>
                    @if (! $insumo->esLeche() && $puedeComprar)
                        @if ($comprable)
                            <a href="{{ route('admin.compras.index', ['nueva' => 1, 'insumo' => $insumo->id()]) }}" class="fichas-btn is-primary is-sm">
                                <x-ui.icon name="shopping-cart" class="h-3.5 w-3.5" /> Comprar este insumo
                            </a>
                        @else
                            <button type="button" class="fichas-btn is-primary is-sm cursor-not-allowed opacity-50" disabled>
                                <x-ui.icon name="shopping-cart" class="h-3.5 w-3.5" /> Comprar este insumo
                            </button>
                        @endif
                    @endif
                    @unless ($insumo->esLeche())
                        <button type="button" class="fichas-btn is-outline is-sm" data-modal-open="modal-presentacion" data-presentacion="{{ json_encode($datosPresentacion(null)) }}">
                            <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Agregar presentación
                        </button>
                    @endunless
                    <button type="button" class="fichas-btn is-outline is-sm" data-ajuste-abrir data-ajuste-insumo="{{ $insumo->id() }}">
                        <x-ui.icon name="refresh" class="h-3.5 w-3.5" /> Ajustar inventario
                    </button>
                    <a href="{{ route('admin.insumos.show', $insumo->id()) }}" class="fichas-btn is-outline is-sm">
                        <x-ui.icon name="clipboard-list" class="h-3.5 w-3.5" /> Consultar historial
                    </a>
                    @unless ($insumo->esDelSistema())
                        <form method="POST" action="{{ route('admin.insumos.update', $insumo->id()) }}" @if ($insumo->estado() === 'ACTIVO') data-confirm="¿Desactivar {{ $insumo->nombre() }}? Ya no se podrá usar en recetas nuevas ni comprar." @endif>
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="nombre" value="{{ $insumo->nombre() }}">
                            <input type="hidden" name="descripcion" value="{{ $insumo->descripcion() }}">
                            <input type="hidden" name="stock_minimo" value="{{ $insumo->stockMinimo() }}">
                            <input type="hidden" name="tipo" value="{{ $insumo->tipo()->value }}">
                            <input type="hidden" name="estado" value="{{ $insumo->estado() === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO' }}">
                            <button type="submit" class="fichas-btn is-outline is-sm w-full" aria-label="{{ $insumo->estado() === 'ACTIVO' ? 'Desactivar' : 'Activar' }} {{ $insumo->nombre() }}">
                                {{ $insumo->estado() === 'ACTIVO' ? 'Desactivar' : 'Activar' }}
                            </button>
                        </form>
                    @endunless
                </div>
                @if (! $insumo->esLeche() && ! $comprable)
                    <p class="mt-2 text-xs text-text-muted">
                        {{ $insumo->estado() !== 'ACTIVO' ? 'Activa el insumo para poder comprarlo.' : 'Para comprar '.$insumo->nombre().', primero agrega una presentación comercial.' }}
                    </p>
                @endif
            </section>
        @endif
    </div>
</div>
