@extends('layouts.admin')

@section('title', 'Compras y gastos')
@section('body-class', 'admin-fichas-page compras-page')

{{--
    INVENTARIO > COMPRAS Y GASTOS: el único lugar donde se registran adquisiciones (insumos,
    limpieza, refrigerios, equipos, servicios...). Se centra en QUÉ se compró, dónde, cuánto costó,
    con qué comprobante y si está pagado; los movimientos de dinero se consultan en Caja y lo pagado
    y el saldo de cada compra, en su ficha. Indicadores e historial salen del backend
    (EloquentComprasQuery) con los MISMOS filtros. Clic en una fila = ficha de la compra.
    «Registrar compra» abre el formulario único de 3 etapas (_formulario), con la boleta escaneada
    como opción; ?nueva=1&insumo=ID lo abre con el insumo elegido (botón "Comprar" de Insumos).
    Variables: $filtro, $resumen, $compras, $categorias, $categoriasActivas, $catalogoCompra,
    $proveedoresComerciales, $abrirFormulario, $insumoPreseleccionado.
--}}
@php
    $soles = fn (?float $valor): string => $valor === null ? '—' : 'S/ '.number_format($valor, 2);
    $modelo = \App\Infrastructure\Persistence\Models\CompraInsumoModel::class;
    $estados = \App\Domain\Shared\Enums\EstadoPagoCompra::opciones();
    $masFiltros = $filtro->estadoPago !== null || $filtro->destino !== null;
@endphp

@section('content')
    <div class="fichas compras" data-compras-page>
        <header class="compras-header">
            <div class="min-w-0">
                <x-layout.breadcrumbs :items="[['Inventario', null], ['Compras y gastos', null]]" />
                <h1 class="fichas-title">Compras y gastos</h1>
                <p class="fichas-subtitle">Lo que compra EcoLact: artículos, proveedores y comprobantes.</p>
            </div>
            @can('create', $modelo)
                <button type="button" class="fichas-btn is-primary" data-compra-abrir="manual">
                    <x-ui.icon name="plus" class="h-4 w-4" /> Registrar compra
                </button>
            @endcan
        </header>

        {{-- ------------------------------------------------------------ indicadores --}}
        <section class="compras-kpis" aria-label="Resumen de compras{{ $filtro->activo() ? ' para los filtros aplicados' : '' }}">
            <article class="compras-kpi">
                <span class="compras-kpi-icono" aria-hidden="true"><x-ui.icon name="clipboard-list" class="h-4 w-4" /></span>
                <div>
                    <p class="compras-kpi-etiqueta">Compras del periodo</p>
                    <p class="compras-kpi-valor">{{ $resumen->compras }}</p>
                </div>
            </article>
            <article class="compras-kpi">
                <span class="compras-kpi-icono" aria-hidden="true"><x-ui.icon name="shopping-cart" class="h-4 w-4" /></span>
                <div>
                    <p class="compras-kpi-etiqueta">Total comprado</p>
                    <p class="compras-kpi-valor">{{ $soles($resumen->comprado) }}</p>
                </div>
            </article>
        </section>

        {{-- ------------------------------------------------------------ historial --}}
        <section class="compras-historial" aria-labelledby="compras-historial-titulo">
            <h2 id="compras-historial-titulo" class="sr-only">Historial de compras</h2>

            <form method="GET" action="{{ route('admin.compras.index') }}" class="compras-filtros" data-compras-filtros>
                <label class="campo-icono-caja compras-buscador">
                    <span class="sr-only">Buscar por artículo, proveedor o comprobante</span>
                    <x-ui.icon name="magnifying-glass" class="campo-icono-simbolo h-[18px] w-[18px]" />
                    <input type="search" name="q" value="{{ $filtro->busqueda }}" placeholder="Buscar artículo, proveedor o comprobante..." autocomplete="off" maxlength="100">
                </label>

                <div class="compras-rango">
                    <x-ui.icon name="calendar" class="h-4 w-4 shrink-0" />
                    <label><span class="sr-only">Desde</span><input type="date" name="desde" value="{{ $filtro->desde?->format('Y-m-d') }}"></label>
                    <span aria-hidden="true">–</span>
                    <label><span class="sr-only">Hasta</span><input type="date" name="hasta" value="{{ $filtro->hasta?->format('Y-m-d') }}"></label>
                </div>

                <label>
                    <span class="sr-only">Categoría</span>
                    <select name="categoria" class="pt-campo">
                        <option value="">Todas las categorías</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->id() }}" @selected($filtro->categoriaId === $categoria->id())>{{ $categoria->nombre() }}</option>
                        @endforeach
                    </select>
                </label>

                <details class="compras-mas-filtros" {{ $masFiltros ? 'open' : '' }}>
                    <summary>Más filtros @if ($masFiltros)<span class="compras-mas-filtros-n">{{ (int) ($filtro->estadoPago !== null) + (int) ($filtro->destino !== null) }}</span>@endif <x-ui.icon name="chevron-down" class="compras-mas-filtros-flecha h-4 w-4" /></summary>
                    <div class="compras-mas-filtros-cuerpo">
                        <label>
                            <span class="compras-filtro-etiqueta">Estado de pago</span>
                            <select name="estado" class="pt-campo">
                                <option value="">Todos los estados</option>
                                @foreach ($estados as $valor => $etiqueta)
                                    <option value="{{ $valor }}" @selected($filtro->estadoPago?->value === $valor)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span class="compras-filtro-etiqueta">Destino</span>
                            <select name="destino" class="pt-campo">
                                <option value="">Inventario y gastos</option>
                                <option value="INVENTARIO" @selected($filtro->destino === 'INVENTARIO')>Con ingreso a inventario</option>
                                <option value="GASTO" @selected($filtro->destino === 'GASTO')>Solo gastos</option>
                            </select>
                        </label>
                    </div>
                </details>

                <noscript><button type="submit" class="fichas-btn is-primary is-sm">Filtrar</button></noscript>
                @if ($filtro->activo())
                    <a href="{{ route('admin.compras.index') }}" class="compras-limpiar">Limpiar</a>
                @endif
            </form>

            @if ($compras->isEmpty())
                <div class="fichas-vacio">
                    <x-ui.icon name="shopping-cart" class="h-5 w-5 shrink-0" />
                    @if ($filtro->activo())
                        <span><strong>No hay compras para estos filtros.</strong> Prueba con otras fechas, categoría o búsqueda.</span>
                    @else
                        <span><strong>Aún no hay compras registradas.</strong> Registra la primera con «Registrar compra».</span>
                    @endif
                </div>
            @else
                <div class="fichas-tabla compras-tabla">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col" class="compras-col-fecha">Fecha</th>
                                <th scope="col">Compra</th>
                                <th scope="col">Proveedor comercial</th>
                                <th scope="col" class="is-num">Importe total</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="compras-col-accion"><span class="sr-only">Acción</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($compras as $compra)
                                <tr data-compra-fila data-url="{{ route('admin.compras.show', $compra->id) }}" tabindex="0">
                                    <td class="is-muted" data-etiqueta="Fecha">{{ $compra->fecha->format('d/m/Y') }}</td>
                                    <td class="compras-celda-concepto" data-etiqueta="Compra">
                                        <a href="{{ route('admin.compras.show', $compra->id) }}" class="compras-concepto" title="{{ $compra->descripcion() }}">{{ $compra->descripcion() }}</a>
                                        <span class="compras-sub">
                                            <span @class(['compras-destino', 'is-'.strtolower($compra->destino)])>{{ $compra->destinoEtiqueta() }}</span>
                                            @if ($compra->comprobante)
                                                <span>{{ $compra->comprobante }}</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td class="compras-celda-proveedor" data-etiqueta="Proveedor">{{ $compra->proveedor ?? 'Sin proveedor' }}</td>
                                    <td class="is-num compras-celda-total" data-etiqueta="Importe total">{{ $soles($compra->total) }}</td>
                                    <td data-etiqueta="Estado">
                                        @include('admin.compras._estado-pago', ['estado' => $compra->estadoPago])
                                        @if ($compra->estadoRecepcion === \App\Domain\Shared\Enums\EstadoRecepcionCompra::PENDIENTE)
                                            <span class="compras-estado is-por-recibir">Por recibir</span>
                                        @endif
                                    </td>
                                    <td class="compras-col-accion">
                                        <a href="{{ route('admin.compras.show', $compra->id) }}" class="insumos-accion" title="Ver detalle de {{ $compra->codigo }}" aria-label="Ver detalle de {{ $compra->codigo }}">
                                            <x-ui.icon name="chevron-right" class="h-4 w-4" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <x-tables.pagination :paginator="$compras" noun="compras" class="fichas-pager" />
        </section>
    </div>

    @can('create', $modelo)
        @include('admin.compras._formulario')
    @endcan
@endsection
