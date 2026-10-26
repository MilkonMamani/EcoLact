{{--
    INSUMOS (ADMINISTRADOR): tabla + panel lateral (mismo patrón que Proveedores). Cada acción hace UNA cosa:
      Nuevo insumo      crea la ficha (stock 0, no es una compra),
      Presentaciones    definen cómo se compra (Saco = 25 kg); no tocan el stock (panel del insumo),
      Registrar compra  / Escanear boleta: lo ÚNICO que sube el stock, convirtiendo a la unidad base,
      Ajustar stock     corrige el stock (merma, pérdida, corrección, inventario físico).
    La LECHE es del sistema: su stock sube con Acopio y baja con Producción; no se compra ni tiene
    presentaciones. ?insumo=ID mantiene la selección.
    Variables: $insumos, $kpis, $ultimasCompras, $tipos, $unidades, $panel (o null), $catalogoCompra,
    $motivosAjuste, $insumosAjuste.
--}}
@extends('layouts.admin')

@section('title', 'Insumos')
@section('body-class', 'admin-fichas-page')
@section('contenedor', 'max-w-[1600px]')

@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $modelo = \App\Infrastructure\Persistence\Models\InsumoModel::class;
    $seleccionadoId = $panel ? $panel['insumo']->id() : null;
    $storeRoute = 'admin.insumos.store';
    // Solo se puede comprar un insumo que ya tiene cómo comprarse (presentación activa).
    $comprables = array_column(array_filter($catalogoCompra, fn ($item) => count($item['presentaciones']) > 0), 'id');
    $pasos = [
        ['plus', 'Crear insumo', 'La ficha: nombre, tipo y unidad base. Stock 0.'],
        ['package', 'Presentaciones', 'Cómo se compra: Saco = 25 kg. No suma stock.'],
        ['shopping-cart', 'Registrar compra', 'Convierte a la unidad base y suma stock.'],
        ['refresh', 'Ajustar stock', 'Merma, pérdida, corrección o inventario.'],
    ];
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
                            <p class="fichas-subtitle">Materias primas, insumos y envases necesarios para producción.</p>
                        </div>
                        @can('create', $modelo)
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="fichas-btn is-primary" data-modal-open="modal-nuevo-insumo">
                                    <x-ui.icon name="plus" class="h-4 w-4" /> Nuevo insumo
                                </button>
                                <button type="button" class="fichas-btn is-outline" data-compra-abrir="manual">
                                    <x-ui.icon name="shopping-cart" class="h-4 w-4" /> Registrar compra
                                </button>
                                <button type="button" class="fichas-btn is-outline" data-compra-abrir="boleta">
                                    <x-ui.icon name="camera" class="h-4 w-4" /> Escanear boleta
                                </button>
                                <button type="button" class="fichas-btn is-outline" data-ajuste-abrir>
                                    <x-ui.icon name="refresh" class="h-4 w-4" /> Ajustar stock
                                </button>
                            </div>
                        @endcan
                    </header>

                    {{-- Cómo se mueve un insumo: cada botón de arriba es uno de estos pasos. --}}
                    <ol class="insumos-flujo" aria-label="Cómo funciona el módulo de insumos">
                        @foreach ($pasos as $indice => [$icono, $titulo, $texto])
                            <li>
                                <span class="insumos-flujo-numero">{{ $indice + 1 }}</span>
                                <div class="min-w-0">
                                    <p class="insumos-flujo-titulo"><x-ui.icon :name="$icono" class="h-3.5 w-3.5" /> {{ $titulo }}</p>
                                    <p class="insumos-flujo-texto">{{ $texto }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    <form method="GET" action="{{ route('admin.insumos.index') }}" class="fichas-filtros mt-4" data-fichas-filtros>
                        <label class="fichas-search">
                            <span class="sr-only">Buscar insumo</span>
                            <x-ui.icon name="magnifying-glass" class="h-5 w-5" />
                            <input type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar insumo..." autocomplete="off">
                        </label>
                        <div class="fichas-filtros-fila">
                            <x-forms.select name="tipo" label="Tipo" :options="$tipos" :value="request('tipo')" placeholder="Todos" :sticky="false" />
                            <x-forms.select name="nivel" label="Nivel de stock" :options="['normal' => 'Normal', 'bajo' => 'Bajo el mínimo', 'sin_stock' => 'Sin stock']" :value="request('nivel')" placeholder="Todos" :sticky="false" />
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
                        <x-ui.empty-state class="mt-4 bg-surface" title="No hay insumos para mostrar" description="Crea la ficha de los insumos que usa producción (sal, cuajo, azúcar, envases...)." />
                    @else
                        <div class="fichas-tabla is-compacta insumos-tabla is-loading" data-fichas-tabla>
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">Insumo</th>
                                        <th scope="col" class="hidden 2xl:table-cell">Tipo</th>
                                        <th scope="col" class="hidden 2xl:table-cell">Unidad base</th>
                                        <th scope="col" class="is-num">Stock actual</th>
                                        <th scope="col" class="is-num hidden lg:table-cell">Stock mínimo</th>
                                        <th scope="col" class="is-centro">Estado</th>
                                        <th scope="col" class="hidden md:table-cell">Última compra</th>
                                        <th scope="col" class="is-centro">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="fichas-skeleton" aria-hidden="true">
                                    @for ($i = 0; $i < min(8, count($insumos)); $i++)
                                        <tr>
                                            <td><span class="fichas-bone is-lg"></span></td>
                                            <td class="hidden 2xl:table-cell"><span class="fichas-bone is-md"></span></td>
                                            <td class="hidden 2xl:table-cell"><span class="fichas-bone is-xs"></span></td>
                                            <td><span class="fichas-bone is-md ml-auto"></span></td>
                                            <td class="hidden lg:table-cell"><span class="fichas-bone is-sm ml-auto"></span></td>
                                            <td class="is-centro"><span class="fichas-bone is-xs mx-auto"></span></td>
                                            <td class="hidden md:table-cell"><span class="fichas-bone is-sm"></span></td>
                                            <td class="is-centro"><span class="fichas-bone is-xs mx-auto"></span></td>
                                        </tr>
                                    @endfor
                                </tbody>
                                <tbody data-fichas-filas>
                                    @foreach ($insumos as $insumo)
                                        @php
                                            $sinStock = $insumo->stockActual() <= 0.0;
                                            $bajo = $insumo->stockMinimo() > 0.0 && $insumo->estaBajoMinimo() && ! $sinStock;
                                            $ultimaCompra = $ultimasCompras[$insumo->id()] ?? null;
                                            $comprable = in_array($insumo->id(), $comprables, true);
                                        @endphp
                                        <tr data-fichas-fila data-ficha-id="{{ $insumo->id() }}" data-url-panel="{{ route('admin.insumos.panel', $insumo->id()) }}" tabindex="0" title="Ver detalle" aria-selected="{{ $insumo->id() === $seleccionadoId ? 'true' : 'false' }}" @class(['is-selected' => $insumo->id() === $seleccionadoId])>
                                            <td>
                                                <a href="{{ route('admin.insumos.index', ['insumo' => $insumo->id()]) }}" class="fichas-nombre" data-fichas-seleccionar tabindex="-1">{{ $insumo->nombre() }}</a>
                                                @if ($insumo->esDelSistema())
                                                    <span class="insumos-sistema" title="Insumo del sistema: entra por Acopio y sale por Producción.">Del sistema</span>
                                                @endif
                                                {{-- En pantallas medianas tipo y unidad van bajo el nombre. --}}
                                                <p class="text-xs text-text-muted 2xl:hidden">{{ $insumo->tipo()->etiqueta() }} · {{ $insumo->unidadBase() }}</p>
                                            </td>
                                            <td class="is-muted hidden 2xl:table-cell">{{ $insumo->tipo()->etiqueta() }}</td>
                                            <td class="is-muted hidden 2xl:table-cell">{{ \App\Domain\Produccion\UnidadMedida::etiqueta($insumo->unidadBase()) }}</td>
                                            <td class="is-num">
                                                <span @class(['font-semibold', 'text-danger' => $sinStock, 'text-warning' => $bajo])>{{ $fmt($insumo->stockActual()) }} {{ $insumo->unidadBase() }}</span>
                                                @if ($sinStock)
                                                    <span class="block text-xs text-danger">Sin stock</span>
                                                @elseif ($bajo)
                                                    <span class="block text-xs text-warning">Stock bajo</span>
                                                @endif
                                            </td>
                                            <td class="is-num is-muted hidden lg:table-cell">{{ $fmt($insumo->stockMinimo()) }} {{ $insumo->unidadBase() }}</td>
                                            <td class="is-centro">
                                                @if ($insumo->estado() === 'ACTIVO')
                                                    <x-ui.badge compact status="ACTIVO">Activo</x-ui.badge>
                                                @else
                                                    <x-ui.badge compact status="INACTIVO">Inactivo</x-ui.badge>
                                                @endif
                                            </td>
                                            <td class="is-muted hidden md:table-cell">
                                                @if ($insumo->esLeche())
                                                    <span class="text-xs">Por Acopio</span>
                                                @elseif ($ultimaCompra)
                                                    {{ $ultimaCompra->format('d/m/Y') }}
                                                @else
                                                    <span class="text-xs">Sin compras</span>
                                                @endif
                                            </td>
                                            <td class="is-centro">
                                                @can('create', $modelo)
                                                    <div class="insumos-acciones">
                                                        @unless ($insumo->esLeche())
                                                            @if ($comprable)
                                                                <button type="button" class="insumos-accion" data-compra-abrir="manual" data-compra-insumo="{{ $insumo->id() }}" title="Registrar compra de {{ $insumo->nombre() }}" aria-label="Registrar compra de {{ $insumo->nombre() }}">
                                                                    <x-ui.icon name="shopping-cart" class="h-4 w-4" />
                                                                </button>
                                                            @else
                                                                <button type="button" class="insumos-accion" disabled title="Primero agrega una presentación de compra" aria-label="{{ $insumo->nombre() }} aún no tiene presentaciones de compra">
                                                                    <x-ui.icon name="shopping-cart" class="h-4 w-4" />
                                                                </button>
                                                            @endif
                                                        @endunless
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
        @include('admin.insumos._compra-modal')
    @endcan
@endsection
