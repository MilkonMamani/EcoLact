{{--
    Panel lateral de un insumo, en cuatro bloques: información general, presentaciones de compra (cómo se
    compra; no tocan el stock), últimos movimientos (compra, ajuste, consumo...) y acciones.
    La LECHE se gestiona sola (Acopio la sube, Producción la baja): no muestra compra ni presentaciones.
    Los botones solo abren modales (_modales-panel, _compra-modal, _modales-insumo) con los datos del insumo.
    $panel: array de ObtenerPanelInsumo.
--}}
@php
    $insumo = $panel['insumo'];
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $unidad = $insumo->unidadBase();
    $sinStock = $insumo->stockActual() <= 0.0;
    $bajo = $insumo->stockMinimo() > 0.0 && $insumo->estaBajoMinimo() && ! $sinStock;
    $puedeGestionar = auth()->user()->can('create', \App\Infrastructure\Persistence\Models\InsumoModel::class);
    $activas = array_filter($panel['presentaciones'], fn ($p) => $p->estaActiva());
    $comprable = ! $insumo->esLeche() && count($activas) > 0 && $insumo->estado() === 'ACTIVO';
    $clasesMovimiento = ['compra' => 'Compra', 'ajuste' => 'Ajuste', 'consumo' => 'Producción', 'acopio' => 'Acopio', 'otro' => 'Otro'];
    $unidadesCompatibles = \App\Domain\Produccion\UnidadMedida::opcionesCompatiblesCon($unidad);
    $datosEdicion = [
        'id' => $insumo->id(),
        'nombre' => $insumo->nombre(),
        'descripcion' => $insumo->descripcion(),
        'stock_minimo' => $insumo->stockMinimo(),
        'tipo' => $insumo->tipo()->value,
        'estado' => $insumo->estado(),
        'del_sistema' => $insumo->esDelSistema(),
        'unidad_etiqueta' => \App\Domain\Produccion\UnidadMedida::etiqueta($unidad),
    ];
    $datosPresentacion = fn (?\App\Domain\Produccion\PresentacionInsumo $p) => [
        'titulo' => $p ? 'Editar presentación' : 'Nueva presentación',
        'subtitulo' => $insumo->nombre().' · unidad base '.$unidad,
        'action' => $p ? route('admin.insumos.presentaciones.update', [$insumo->id(), $p->id()]) : route('admin.insumos.presentaciones.store', $insumo->id()),
        'metodo' => $p ? 'PATCH' : 'POST',
        'nombre' => $p?->nombre() ?? '',
        'cantidad' => $p?->cantidad() ?? '',
        'unidad' => $p?->unidad() ?? array_key_first($unidadesCompatibles),
        'estado' => $p?->estado() ?? 'ACTIVA',
        'unidad_base' => $unidad,
        'unidades' => $unidadesCompatibles,
        // Solo para la vista previa de la equivalencia; el servidor la recalcula al guardar.
        'factores' => array_map(fn ($codigo) => \App\Domain\Produccion\UnidadMedida::convertir(1.0, $codigo, $unidad), array_combine(array_keys($unidadesCompatibles), array_keys($unidadesCompatibles))),
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
            </div>
            <p class="mt-1 text-sm text-text-muted">{{ $insumo->tipo()->etiqueta() }} · unidad base {{ $unidad }}</p>
        </div>
        <button type="button" class="fichas-cerrar" data-fichas-cerrar aria-label="Cerrar detalle">
            <x-ui.icon name="x-mark" class="h-5 w-5" />
        </button>
    </div>

    <div class="fichas-panel-cuerpo">
        {{-- 1. Información general --}}
        <section class="fichas-seccion is-primera">
            <div class="fichas-seccion-head">
                <h3>Información general</h3>
            </div>
            <div class="insumos-stock">
                <div>
                    <p class="insumos-stock-rotulo">Stock actual</p>
                    <p @class(['insumos-stock-valor', 'text-danger' => $sinStock, 'text-warning' => $bajo])>{{ $fmt($insumo->stockActual()) }} <span>{{ $unidad }}</span></p>
                </div>
                @if ($sinStock)
                    <x-ui.badge variant="WARNING">Sin stock</x-ui.badge>
                @elseif ($bajo)
                    <x-ui.badge variant="WARNING">Stock bajo</x-ui.badge>
                @else
                    <x-ui.badge status="ACTIVO">Disponible</x-ui.badge>
                @endif
            </div>
            <dl class="fichas-datos mt-3">
                <div>
                    <dt>Tipo</dt>
                    <dd>{{ $insumo->tipo()->etiqueta() }}</dd>
                </div>
                <div>
                    <dt>Unidad base</dt>
                    <dd>{{ \App\Domain\Produccion\UnidadMedida::etiqueta($unidad) }}</dd>
                </div>
                <div>
                    <dt>Stock mínimo</dt>
                    <dd>{{ $fmt($insumo->stockMinimo()) }} {{ $unidad }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>{{ $insumo->estado() === 'ACTIVO' ? 'Activo' : 'Inactivo' }}</dd>
                </div>
                <div class="is-ancho">
                    <dt>Descripción</dt>
                    <dd>{{ $insumo->descripcion() ?: 'Sin descripción.' }}</dd>
                </div>
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
                    <h3>Presentaciones de compra</h3>
                    @if ($puedeGestionar && count($panel['presentaciones']) > 0)
                        <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-1">
                            <button type="button" class="fichas-editar" data-presentaciones-editar aria-pressed="false">
                                <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> <span data-presentaciones-editar-texto>Editar presentaciones</span>
                            </button>
                            <button type="button" class="fichas-editar" data-modal-open="modal-presentacion" data-presentacion="{{ json_encode($datosPresentacion(null)) }}">
                                <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Agregar presentación
                            </button>
                        </div>
                    @endif
                </div>
                <p class="mb-3 text-xs text-text-muted">Cómo se compra en tienda y cuánto trae cada una. Definirlas no suma stock.</p>

                @if (count($panel['presentaciones']) === 0)
                    <div class="rounded-lg border border-dashed border-text-muted/25 px-4 py-4 text-center">
                        <p class="text-sm font-medium text-text">Aún no tiene presentaciones</p>
                        <p class="mt-1 text-xs text-text-muted">Agrega cómo se compra (por ejemplo, Saco de 25 kg) para poder registrar sus compras.</p>
                        @if ($puedeGestionar)
                            <button type="button" class="fichas-btn is-primary is-sm mt-3" data-modal-open="modal-presentacion" data-presentacion="{{ json_encode($datosPresentacion(null)) }}">
                                <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Agregar presentación
                            </button>
                        @endif
                    </div>
                @else
                    <ul class="divide-y divide-text-muted/10 rounded-lg border border-text-muted/15">
                        @foreach ($panel['presentaciones'] as $presentacion)
                            <li @class(['flex items-center justify-between gap-3 px-3 py-2.5', 'opacity-60' => ! $presentacion->estaActiva()])>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-text">
                                        {{ $presentacion->etiqueta() }}
                                        @unless ($presentacion->estaActiva())
                                            <span class="ml-1 text-xs font-normal text-text-muted">Inactiva</span>
                                        @endunless
                                    </p>
                                    <p class="text-xs text-text-muted">1 {{ mb_strtolower($presentacion->nombre()) }} = {{ $fmt($presentacion->cantidadBase()) }} {{ $unidad }}</p>
                                </div>
                                @if ($puedeGestionar)
                                    <button type="button" class="fichas-editar" data-presentacion-fila-editar hidden data-modal-open="modal-presentacion" data-presentacion="{{ json_encode($datosPresentacion($presentacion)) }}" aria-label="Editar presentación {{ $presentacion->etiqueta() }}">
                                        <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endunless

        {{-- 3. Últimos movimientos --}}
        <section class="fichas-seccion">
            <div class="fichas-seccion-head">
                <h3>Últimos movimientos</h3>
                <a href="{{ route('admin.insumos.show', $insumo->id()) }}" class="text-xs font-medium text-primary hover:text-primary-dark">Ver historial</a>
            </div>

            @if (count($panel['movimientos']) === 0)
                <p class="text-sm text-text-muted">Todavía no tiene movimientos.</p>
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
                                        <a href="{{ route('admin.compras-insumo.show', $movimiento['compra_id']) }}" class="font-medium text-primary hover:text-primary-dark">{{ $movimiento['referencia'] }}</a> ·
                                    @elseif ($movimiento['referencia'])
                                        {{ $movimiento['referencia'] }} ·
                                    @endif
                                    {{ $movimiento['fecha']->format('d/m/Y H:i') }}
                                </p>
                            </div>
                            <span class="whitespace-nowrap text-sm font-semibold {{ $movimiento['entrada'] ? 'text-success' : 'text-danger' }}">{{ $movimiento['entrada'] ? '+' : '−' }}{{ $fmt($movimiento['cantidad']) }} {{ $unidad }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- 4. Acciones --}}
        @if ($puedeGestionar)
            <section class="fichas-seccion">
                <div class="fichas-seccion-head">
                    <h3>Acciones</h3>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" class="fichas-btn is-outline is-sm" data-modal-open="modal-editar-insumo" data-insumo-editar data-insumo="{{ json_encode($datosEdicion) }}" aria-label="Editar {{ $insumo->nombre() }}">
                        <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar insumo
                    </button>
                    @unless ($insumo->esLeche())
                        <button type="button" class="fichas-btn is-primary is-sm disabled:cursor-not-allowed disabled:opacity-50" data-compra-abrir="manual" data-compra-insumo="{{ $insumo->id() }}" @disabled(! $comprable)>
                            <x-ui.icon name="shopping-cart" class="h-3.5 w-3.5" /> Registrar compra
                        </button>
                    @endunless
                    <button type="button" class="fichas-btn is-outline is-sm" data-ajuste-abrir data-ajuste-insumo="{{ $insumo->id() }}">
                        <x-ui.icon name="refresh" class="h-3.5 w-3.5" /> Ajustar stock
                    </button>
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
                        {{ $insumo->estado() !== 'ACTIVO' ? 'Activa el insumo para registrar sus compras.' : 'Para registrar compras de '.$insumo->nombre().', primero agrega una presentación de compra.' }}
                    </p>
                @endif
            </section>
        @endif
    </div>
</div>
