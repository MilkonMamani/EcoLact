{{--
    INSUMOS (ADMINISTRADOR): centro de EXISTENCIAS, tabla + panel lateral (mismo patrón que Proveedores).
    Cada acción hace UNA cosa:
      Nuevo insumo      crea la ficha (stock 0, no es una compra) y dice cómo se mide (peso, volumen, unidades),
      Presentaciones    definen cómo se compra (Saco = 25 kg); no tocan el stock (panel del insumo),
      Comprar este insumo  NO registra nada aquí: abre Inventario > Compras y gastos con el insumo elegido
                        (el único registro de compras; el stock sube al recibir la compra),
      Ver movimientos   historial de entradas y salidas de todos los insumos,
      Ajustar stock     corrige el stock (merma, pérdida, corrección, inventario físico): fila o panel.
    La LECHE es del sistema: su stock sube con Acopio y baja con Producción; no se compra ni tiene
    presentaciones. ?insumo=ID mantiene la selección. El Estado de cada fila es el de sus existencias
    (Insumo::nivelStock); un insumo inactivo se marca junto a su nombre.
    En pantallas pequeñas cada fila se muestra como tarjeta (admin-insumos.css, data-etiqueta).
    Variables: $insumos, $kpis, $ultimasCompras, $ultimosMovimientos, $tipos, $familiasUnidad, $panel (o null),
    $catalogoCompra, $motivosAjuste, $insumosAjuste.
--}}
@extends('layouts.admin')

@section('title', 'Insumos')
@section('body-class', 'admin-fichas-page')
@section('contenedor', 'max-w-[1600px]')

@use('App\Domain\Produccion\UnidadMedida')
@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $modelo = \App\Infrastructure\Persistence\Models\InsumoModel::class;
    $seleccionadoId = $panel ? $panel['insumo']->id() : null;
    $storeRoute = 'admin.insumos.store';
    // Solo se puede comprar un insumo que ya tiene cómo comprarse (presentación activa).
    $comprables = array_column(array_filter($catalogoCompra, fn ($item) => count($item['presentaciones']) > 0), 'id');
    $puedeComprar = auth()->user()->can('create', \App\Infrastructure\Persistence\Models\CompraInsumoModel::class);
    $familiaCorta = ['MASA' => 'Peso', 'VOLUMEN' => 'Volumen', 'CONTEO' => 'Unidades'];
@endphp

@section('content')
    <div data-insumos-page data-url-update="{{ route('admin.insumos.update', '__ID__') }}" @if (old('nombre') !== null && ! old('lineas') && ($errors->any() || session('error'))) data-reabrir-nuevo @endif>
        <div class="fichas" data-fichas-page data-fichas-param="insumo">
            <div class="fichas-layout">
                <section aria-label="Listado de insumos">
                    <header class="fichas-header">
                        <div class="min-w-0">
                            <x-layout.breadcrumbs :items="[['Inventario', null], ['Insumos', null]]" />
                            <h1 class="fichas-title">Insumos</h1>
                            <p class="fichas-subtitle">Administra las existencias y materiales utilizados en la producción.</p>
                        </div>
                        <div class="insumos-header-acciones">
                            @can('create', $modelo)
                                <button type="button" class="fichas-btn is-primary" data-modal-open="modal-nuevo-insumo">
                                    <x-ui.icon name="plus" class="h-4 w-4" /> Nuevo insumo
                                </button>
                            @endcan
                        </div>
                    </header>

                    <form method="GET" action="{{ route('admin.insumos.index') }}" class="fichas-filtros mt-5" data-fichas-filtros>
                        <label class="fichas-search">
                            <span class="sr-only">Buscar insumo</span>
                            <x-ui.icon name="magnifying-glass" class="h-5 w-5" />
                            <input type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar insumo..." autocomplete="off">
                        </label>
                        <div class="fichas-filtros-fila">
                            <x-forms.select name="tipo" label="Categoría" :options="$tipos" :value="request('tipo')" placeholder="Todas" :sticky="false" />
                            <x-forms.select name="nivel" label="Stock" :options="['normal' => 'Disponible', 'bajo' => 'Stock bajo', 'sin_stock' => 'Sin stock']" :value="request('nivel')" placeholder="Todos" :sticky="false" />
                            <div class="fichas-filtros-acciones">
                                <button type="submit" class="fichas-btn is-dark">Filtrar</button>
                                @if (request()->anyFilled(['buscar', 'tipo', 'nivel']))
                                    <a href="{{ route('admin.insumos.index') }}" class="fichas-btn is-outline">Limpiar</a>
                                @endif
                            </div>
                        </div>
                    </form>

                    <p class="insumos-resumen" aria-label="Resumen del inventario">
                        <span><strong>{{ $kpis['insumos'] }}</strong> insumos</span>
                        <span class="is-ok"><strong>{{ $kpis['conStock'] }}</strong> con stock</span>
                        <span class="is-bajo"><strong>{{ $kpis['stockBajo'] }}</strong> con stock bajo</span>
                        <span class="is-sin"><strong>{{ $kpis['sinStock'] }}</strong> sin stock</span>
                    </p>

                    @if (count($insumos) === 0)
                        <x-ui.empty-state class="mt-4 bg-surface" title="No hay insumos para mostrar" description="Crea la ficha de los materiales que usa producción: sal, cuajo, azúcar, envases..." />
                    @else
                        <div class="fichas-tabla is-compacta insumos-tabla is-loading" data-fichas-tabla>
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">Insumo</th>
                                        <th scope="col" class="hidden xl:table-cell">Categoría</th>
                                        <th scope="col" class="hidden md:table-cell">Unidad</th>
                                        <th scope="col" class="is-num">Stock disponible</th>
                                        <th scope="col" class="is-num hidden lg:table-cell">Stock mínimo</th>
                                        <th scope="col" class="is-centro">Estado</th>
                                        <th scope="col" class="hidden 2xl:table-cell">Último movimiento</th>
                                        <th scope="col" class="is-centro">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="fichas-skeleton" aria-hidden="true">
                                    @for ($i = 0; $i < min(8, count($insumos)); $i++)
                                        <tr>
                                            <td><span class="fichas-bone is-lg"></span></td>
                                            <td class="hidden xl:table-cell"><span class="fichas-bone is-md"></span></td>
                                            <td class="hidden md:table-cell"><span class="fichas-bone is-xs"></span></td>
                                            <td><span class="fichas-bone is-md ml-auto"></span></td>
                                            <td class="hidden lg:table-cell"><span class="fichas-bone is-sm ml-auto"></span></td>
                                            <td class="is-centro"><span class="fichas-bone is-sm mx-auto"></span></td>
                                            <td class="hidden 2xl:table-cell"><span class="fichas-bone is-sm"></span></td>
                                            <td class="is-centro"><span class="fichas-bone is-xs mx-auto"></span></td>
                                        </tr>
                                    @endfor
                                </tbody>
                                <tbody data-fichas-filas>
                                    @foreach ($insumos as $insumo)
                                        @php
                                            $nivel = $insumo->nivelStock();
                                            $simbolo = UnidadMedida::simbolo($insumo->unidadBase());
                                            $ultimaCompra = $ultimasCompras[$insumo->id()] ?? null;
                                            $ultimoMovimiento = $ultimosMovimientos[$insumo->id()] ?? null;
                                            $comprable = in_array($insumo->id(), $comprables, true);
                                        @endphp
                                        <tr data-fichas-fila data-ficha-id="{{ $insumo->id() }}" data-url-panel="{{ route('admin.insumos.panel', $insumo->id()) }}" tabindex="0" title="Ver detalle" aria-selected="{{ $insumo->id() === $seleccionadoId ? 'true' : 'false' }}" @class(['is-selected' => $insumo->id() === $seleccionadoId, 'is-inactivo' => $insumo->estado() !== 'ACTIVO'])>
                                            <td class="insumos-celda-nombre">
                                                <a href="{{ route('admin.insumos.index', ['insumo' => $insumo->id()]) }}" class="fichas-nombre" data-fichas-seleccionar tabindex="-1">{{ $insumo->nombre() }}</a>
                                                @if ($insumo->esDelSistema())
                                                    <span class="insumos-sistema" title="Insumo del sistema: entra por Acopio y sale por Producción.">Del sistema</span>
                                                @endif
                                                @if ($insumo->estado() !== 'ACTIVO')
                                                    <span class="insumos-inactivo">Inactivo</span>
                                                @endif
                                                {{-- En pantallas medianas la categoría y la unidad van bajo el nombre. --}}
                                                <p class="text-xs text-text-muted xl:hidden">{{ $insumo->tipo()->etiqueta() }}<span class="md:hidden"> · {{ $simbolo }}</span></p>
                                                <p class="text-xs text-text-muted">
                                                    @if ($insumo->esLeche())
                                                        Por Acopio
                                                    @elseif ($ultimaCompra)
                                                        Última compra {{ $ultimaCompra->format('d/m/Y') }}
                                                    @else
                                                        Sin compras
                                                    @endif
                                                </p>
                                            </td>
                                            <td class="is-muted hidden xl:table-cell">{{ $insumo->tipo()->etiqueta() }}</td>
                                            <td class="hidden md:table-cell" title="{{ UnidadMedida::etiqueta($insumo->unidadBase()) }}">
                                                <span class="insumos-unidad">{{ $simbolo }}</span>
                                                <span class="block text-xs text-text-muted">{{ $familiaCorta[UnidadMedida::familia($insumo->unidadBase())] }}</span>
                                            </td>
                                            <td class="is-num" data-etiqueta="Stock disponible">
                                                <span @class(['font-semibold', 'text-danger' => $nivel === 'SIN_STOCK', 'text-warning' => $nivel === 'BAJO'])>{{ $fmt($insumo->stockActual()) }} {{ $simbolo }}</span>
                                            </td>
                                            <td class="is-num is-muted hidden lg:table-cell" data-etiqueta="Stock mínimo">{{ $fmt($insumo->stockMinimo()) }} {{ $simbolo }}</td>
                                            <td class="is-centro" data-etiqueta="Estado">@include('admin.insumos._nivel', ['nivel' => $nivel])</td>
                                            <td class="is-muted hidden 2xl:table-cell" data-etiqueta="Último movimiento">{{ $ultimoMovimiento ? \Carbon\CarbonImmutable::instance($ultimoMovimiento)->timezone(config('app.zona_horaria_negocio'))->format('d/m/Y') : 'Sin movimientos' }}</td>
                                            <td class="is-centro insumos-celda-acciones">
                                                @can('create', $modelo)
                                                    <div class="insumos-acciones">
                                                        @if (! $insumo->esLeche() && $puedeComprar)
                                                            @if ($comprable)
                                                                <a href="{{ route('admin.compras.index', ['nueva' => 1, 'insumo' => $insumo->id()]) }}" class="insumos-accion" title="Comprar {{ $insumo->nombre() }} (Compras y gastos)" aria-label="Comprar {{ $insumo->nombre() }}">
                                                                    <x-ui.icon name="shopping-cart" class="h-4 w-4" />
                                                                </a>
                                                            @else
                                                                <button type="button" class="insumos-accion" disabled title="Primero agrega una presentación de compra" aria-label="{{ $insumo->nombre() }} aún no tiene presentaciones de compra">
                                                                    <x-ui.icon name="shopping-cart" class="h-4 w-4" />
                                                                </button>
                                                            @endif
                                                        @endif
                                                        <button type="button" class="insumos-accion" data-ajuste-abrir data-ajuste-insumo="{{ $insumo->id() }}" title="Ajustar stock de {{ $insumo->nombre() }}" aria-label="Ajustar stock de {{ $insumo->nombre() }}">
                                                            <x-ui.icon name="refresh" class="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <noscript><style>.fichas-tabla.is-loading [data-fichas-filas] { display: table-row-group; } .fichas-tabla.is-loading .fichas-skeleton { display: none; }</style></noscript>
                    @endif
                </section>

                <aside class="fichas-panel" data-fichas-panel aria-live="polite" aria-label="Detalle del insumo">
                    @if ($panel)
                        @include('admin.insumos._panel', ['panel' => $panel])
                    @else
                        @include('admin.insumos._panel-vacio')
                    @endif
                </aside>
            </div>

            <template data-fichas-panel-vacio>
                @include('admin.insumos._panel-vacio')
            </template>

            <template data-fichas-panel-skeleton>
                <div class="fichas-panel-cargando" aria-busy="true" aria-label="Cargando">
                    <div class="fichas-panel-head">
                        <div class="grid flex-1 gap-2.5">
                            <span class="fichas-bone is-xl"></span>
                            <span class="fichas-bone is-lg"></span>
                        </div>
                    </div>
                    <div class="fichas-panel-cuerpo grid gap-4">
                        @foreach (['is-md', 'is-xl', 'is-lg', 'is-xl', 'is-md', 'is-xl', 'is-lg'] as $ancho)
                            <span class="fichas-bone {{ $ancho }}"></span>
                        @endforeach
                    </div>
                </div>
            </template>
        </div>

        @include('insumos._modales-insumo')
    </div>

    @can('create', $modelo)
        @include('admin.insumos._modales-panel')
    @endcan
@endsection
