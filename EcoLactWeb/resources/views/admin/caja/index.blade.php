@extends('layouts.admin')

@section('title', 'Caja')
@section('body-class', 'admin-fichas-page caja-page')

{{--
    CAJA: billetera de EcoLact. Solo lectura: saldo arriba, movimientos abajo, detalle en un panel
    lateral. Todo sale de movimientos_caja (EloquentCajaQuery); los movimientos nacen en Ventas,
    Pagos y Compras y gastos (solo lo realmente pagado), por eso aquí no hay "+ Nuevo movimiento" ni edición de montos.
    - Las tarjetas (saldo y "hoy") no dependen de los filtros del historial.
    - Los filtros se aplican solos al cambiar (admin-caja.js); sin JS, el botón Filtrar.
    - Clic en ">" o en la fila = panel de detalle por fetch; sin JS, ?movimiento=ID.
--}}
@php
    $soles = fn (float $valor): string => 'S/ '.number_format($valor, 2);
    $iconos = ['VENTA' => 'shopping-cart', 'PROVEEDOR' => 'milk-jar', 'TRABAJADOR' => 'user', 'INSUMOS' => 'package', 'GASTOS' => 'file-text', 'SALDO_INICIAL' => 'wallet'];
    $filtrosActivos = request()->anyFilled(['q', 'tipo', 'categoria', 'desde', 'hasta']);
    $meses = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'set.', 'oct.', 'nov.', 'dic.'];
    $fechaCorta = fn (\DateTimeInterface $fecha): string => $fecha->format('j').' '.$meses[(int) $fecha->format('n') - 1].' '.$fecha->format('Y');
@endphp

@section('content')
    <div class="fichas caja" data-caja-page data-url-panel="{{ route('admin.caja.panel', '__ID__') }}">
        <header class="caja-header">
            <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="rounded-lg p-2 text-text transition hover:bg-text-muted/10 lg:hidden">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
            </button>
            <div class="min-w-0">
                <x-layout.breadcrumbs :items="[['Finanzas', null], ['Caja', null]]" />
                <h1 class="fichas-title">Caja</h1>
                <p class="fichas-subtitle">Control de ingresos y egresos de EcoLact.</p>
            </div>
        </header>

        {{-- ------------------------------------------------------------ tarjetas --}}
        <section class="caja-tarjetas" aria-label="Resumen de caja">
            <article class="caja-saldo" data-caja-saldo>
                <div class="caja-saldo-cabecera">
                    <span class="caja-saldo-icono" aria-hidden="true"><x-ui.icon name="wallet" class="h-5 w-5" /></span>
                    <p class="caja-saldo-etiqueta">Saldo disponible</p>
                    <button type="button" class="caja-ojo" data-caja-ojo aria-pressed="false" aria-label="Ocultar saldo" title="Ocultar saldo">
                        <x-ui.icon name="eye" class="h-5 w-5 caja-ojo-ver" />
                        <x-ui.icon name="eye-off" class="h-5 w-5 caja-ojo-oculto" />
                    </button>
                </div>
                <p class="caja-saldo-monto" data-caja-monto>
                    <span class="caja-monto-real">{{ $soles($resumen->saldo) }}</span>
                    <span class="caja-monto-oculto" aria-hidden="true">S/ ••••••</span>
                </p>
                <p class="caja-saldo-nota">Entradas menos salidas registradas</p>
            </article>

            <article class="caja-tarjeta">
                <p class="caja-tarjeta-etiqueta"><span class="caja-punto is-entrada" aria-hidden="true"><x-ui.icon name="arrow-down-left" class="h-3.5 w-3.5" /></span> Ingresos de hoy</p>
                <p class="caja-tarjeta-valor is-entrada">{{ $soles($resumen->ingresosHoy) }}</p>
            </article>

            <article class="caja-tarjeta">
                <p class="caja-tarjeta-etiqueta"><span class="caja-punto is-salida" aria-hidden="true"><x-ui.icon name="arrow-up-right" class="h-3.5 w-3.5" /></span> Salidas de hoy</p>
                <p class="caja-tarjeta-valor is-salida">{{ $soles($resumen->salidasHoy) }}</p>
            </article>

            <article class="caja-tarjeta">
                <p class="caja-tarjeta-etiqueta"><span class="caja-punto" aria-hidden="true"><x-ui.icon name="clipboard-list" class="h-3.5 w-3.5" /></span> Movimientos de hoy</p>
                <p class="caja-tarjeta-valor">{{ $resumen->movimientosHoy }}</p>
            </article>
        </section>

        {{-- ------------------------------------------------------------ historial --}}
        <section class="caja-historial" aria-labelledby="caja-historial-titulo">
            <h2 id="caja-historial-titulo" class="caja-historial-titulo">Últimos movimientos</h2>

            <form method="GET" action="{{ route('admin.caja.index') }}" class="caja-filtros" data-caja-filtros>
                <label class="campo-icono-caja caja-buscador">
                    <span class="sr-only">Buscar por concepto, referencia o persona</span>
                    <x-ui.icon name="magnifying-glass" class="campo-icono-simbolo h-[18px] w-[18px]" />
                    <input type="search" name="q" value="{{ $filtro->busqueda }}" placeholder="Buscar por concepto, referencia o persona..." autocomplete="off" maxlength="100">
                </label>

                <div class="caja-rango" title="{{ $fechaCorta($filtro->desde) }} - {{ $fechaCorta($filtro->hasta) }}">
                    <x-ui.icon name="calendar" class="h-4 w-4 shrink-0" />
                    <label><span class="sr-only">Desde</span><input type="date" name="desde" value="{{ $filtro->desde->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}"></label>
                    <span aria-hidden="true">–</span>
                    <label><span class="sr-only">Hasta</span><input type="date" name="hasta" value="{{ $filtro->hasta->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}"></label>
                </div>

                <label>
                    <span class="sr-only">Tipo</span>
                    <select name="tipo" class="pt-campo">
                        <option value="">Todos los tipos</option>
                        <option value="ENTRADA" @selected($filtro->tipo?->value === 'ENTRADA')>Entradas</option>
                        <option value="SALIDA" @selected($filtro->tipo?->value === 'SALIDA')>Salidas</option>
                    </select>
                </label>

                <label>
                    <span class="sr-only">Categoría</span>
                    <select name="categoria" class="pt-campo">
                        <option value="">Todas las categorías</option>
                        @foreach (\App\Domain\Shared\Enums\CategoriaMovimientoCaja::cases() as $categoria)
                            <option value="{{ $categoria->value }}" @selected($filtro->categoria === $categoria)>{{ $categoria->etiquetaPlural() }}</option>
                        @endforeach
                    </select>
                </label>

                <noscript><button type="submit" class="fichas-btn is-primary is-sm">Filtrar</button></noscript>
                @if ($filtrosActivos)
                    <a href="{{ route('admin.caja.index') }}" class="fichas-btn is-outline is-sm caja-limpiar">Limpiar</a>
                @endif
            </form>

            <div class="caja-lista" data-caja-lista>
                <div class="caja-lista-cabecera" aria-hidden="true">
                    <span>Fecha y hora</span>
                    <span>Movimiento / concepto</span>
                    <span>Referencia</span>
                    <span>Tipo</span>
                    <span class="is-num">Monto</span>
                    <span class="is-accion">Acción</span>
                </div>

                <ul role="list">
                    @forelse ($movimientos as $movimiento)
                        @php $seleccionado = $seleccion?->id === $movimiento->id; @endphp
                        <li>
                            <a
                                href="{{ request()->fullUrlWithQuery(['movimiento' => $movimiento->id]) }}"
                                @class(['caja-fila', 'is-selected' => $seleccionado])
                                data-caja-fila
                                data-movimiento-id="{{ $movimiento->id }}"
                                aria-label="Ver detalle: {{ $movimiento->concepto }} {{ $movimiento->montoConSigno() }}"
                            >
                                <span class="caja-fecha">{{ $fechaCorta($movimiento->fecha) }} · {{ $movimiento->fecha->format('H:i') }}</span>
                                <span class="caja-concepto">
                                    <span @class(['caja-categoria-icono', 'is-'.strtolower($movimiento->categoria->value)]) aria-hidden="true"><x-ui.icon :name="$iconos[$movimiento->categoria->value]" class="h-[18px] w-[18px]" /></span>
                                    <span class="min-w-0">
                                        <span class="caja-concepto-titulo">{{ $movimiento->concepto }}</span>
                                        @if ($movimiento->descripcion)
                                            <span class="caja-concepto-sub">{{ $movimiento->descripcion }}</span>
                                        @endif
                                    </span>
                                </span>
                                <span class="caja-referencia">{{ $movimiento->referencia }}</span>
                                <span @class(['caja-tipo', 'is-entrada' => $movimiento->esEntrada(), 'is-salida' => ! $movimiento->esEntrada()])>{{ $movimiento->tipo->etiqueta() }}</span>
                                <span @class(['caja-monto', 'is-entrada' => $movimiento->esEntrada(), 'is-salida' => ! $movimiento->esEntrada()])>{{ $movimiento->montoConSigno() }}</span>
                                <span class="caja-accion" aria-hidden="true"><x-ui.icon name="chevron-right" class="h-4 w-4" /></span>
                            </a>
                        </li>
                    @empty
                        <li class="caja-vacio">
                            @if ($filtrosActivos)
                                <strong>No hay movimientos para estos filtros</strong> Prueba con otras fechas, tipo o categoría.
                            @else
                                <strong>Aún no hay movimientos en este periodo</strong> Las ventas al contado, los pagos y lo que se paga en Compras y gastos aparecerán aquí automáticamente.
                            @endif
                        </li>
                    @endforelse
                </ul>
            </div>

            <x-tables.pagination :paginator="$movimientos" noun="movimientos" class="fichas-pager" />
        </section>

        {{-- ------------------------------------------------------------ detalle --}}
        <div class="caja-velo" data-caja-velo @if (! $seleccion) hidden @endif></div>
        <aside @class(['caja-panel', 'is-abierto' => $seleccion]) data-caja-panel aria-label="Detalle del movimiento" aria-live="polite" @if (! $seleccion) aria-hidden="true" @endif>
            @if ($seleccion)
                @include('admin.caja._detalle', ['movimiento' => $seleccion])
            @endif
        </aside>

        <template data-caja-panel-skeleton>
            <x-fichas.panel-skeleton />
        </template>
    </div>
@endsection
