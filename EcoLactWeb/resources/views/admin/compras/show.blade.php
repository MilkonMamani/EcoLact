@extends('layouts.admin')

@section('title', 'Compra '.$detalle['compra']->codigo())
@section('body-class', 'admin-fichas-page caja-page compras-page')

{{--
    FICHA DE UNA COMPRA (Inventario > Compras y gastos > detalle). Pestañas Resumen, Artículos,
    Comprobantes y Pagos: ?tab= elige la inicial y funciona sin JS (compras.js cambia sin recargar).
    Todo se enlaza por ids guardados: cada línea con su movimiento de stock, cada pago con su
    movimiento de Caja (cabecera y pestaña Pagos), cada comprobante con su archivo (uno solo, el
    mismo que ve Caja).
    Variables: $detalle (ObtenerDetalleCompra), $metodosPago, $tiposDocumento.
--}}
@use('App\Domain\Produccion\UnidadMedida')
@use('App\Domain\Shared\Enums\EstadoRecepcionCompra')
@php
    $compra = $detalle['compra'];
    $soles = fn (?float $valor): string => $valor === null ? '—' : 'S/ '.number_format($valor, 2);
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $modelo = \App\Infrastructure\Persistence\Models\CompraInsumoModel::find($compra->id());
    $tabs = ['resumen' => 'Resumen', 'articulos' => 'Artículos', 'comprobantes' => 'Comprobantes', 'pagos' => 'Pagos'];
    $tab = array_key_exists(request('tab'), $tabs) ? request('tab') : 'resumen';
    $contadores = ['articulos' => count($detalle['lineas']), 'comprobantes' => count($detalle['comprobantes']), 'pagos' => count($detalle['pagos'])];
    $primerComprobante = $detalle['comprobantes'][0] ?? null;
    $pendienteRecibir = $compra->estadoRecepcion() === EstadoRecepcionCompra::PENDIENTE;
@endphp

@section('content')
    <div class="fichas compras compra-ficha" data-compra-ficha>
        <a href="{{ route('admin.compras.index') }}" class="compra-volver"><x-ui.icon name="arrow-left" class="h-4 w-4" /> Volver al historial</a>

        <header class="compra-cabecera">
            <div class="min-w-0">
                <x-layout.breadcrumbs :items="[['Inventario', null], ['Compras y gastos', route('admin.compras.index')], [$compra->codigo(), null]]" />
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <h1 class="fichas-title">{{ $compra->codigo() }}</h1>
                    @include('admin.compras._estado-pago', ['estado' => $compra->estadoPago()])
                    @if ($pendienteRecibir)
                        <span class="compras-estado is-por-recibir">Por recibir</span>
                    @endif
                </div>
                <p class="fichas-subtitle">{{ $compra->concepto() ?? 'Compra' }} · {{ $compra->proveedorComercial() ?? 'Sin proveedor comercial' }} · {{ $compra->fecha()->format('d/m/Y') }}</p>
            </div>
            <div class="compra-cabecera-lado">
                <div class="compra-cabecera-total">
                    <span>Importe total</span>
                    <strong>{{ $soles($compra->total()) }}</strong>
                </div>
                {{-- Accesos discretos: cada pago (también los parciales) con su movimiento de Caja. --}}
                @php $pagosEnCaja = collect($detalle['pagos'])->filter(fn ($fila) => $fila['pago']->movimientoCajaId()); @endphp
                @if ($pagosEnCaja->isNotEmpty())
                    <div class="compra-enlaces">
                        @foreach ($pagosEnCaja as $fila)
                            <a href="{{ route('admin.caja.index', ['movimiento' => $fila['pago']->movimientoCajaId()]) }}" class="fichas-btn is-outline is-sm"><x-ui.icon name="wallet" class="h-4 w-4" /> Ver movimiento en Caja{{ $pagosEnCaja->count() > 1 ? ' ('.$soles($fila['pago']->monto()).')' : '' }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        </header>

        <section class="caja-tarjetas compras-tarjetas is-tres" aria-label="Resumen financiero">
            <article class="caja-tarjeta">
                <p class="caja-tarjeta-etiqueta"><span class="caja-punto" aria-hidden="true"><x-ui.icon name="shopping-cart" class="h-3.5 w-3.5" /></span> Importe total</p>
                <p class="caja-tarjeta-valor">{{ $soles($compra->total()) }}</p>
            </article>
            <article class="caja-tarjeta">
                <p class="caja-tarjeta-etiqueta"><span class="caja-punto is-salida" aria-hidden="true"><x-ui.icon name="arrow-up-right" class="h-3.5 w-3.5" /></span> Total pagado</p>
                <p class="caja-tarjeta-valor is-salida">{{ $soles($compra->montoPagado()) }}</p>
            </article>
            <article class="caja-tarjeta">
                <p class="caja-tarjeta-etiqueta"><span class="caja-punto is-pendiente" aria-hidden="true"><x-ui.icon name="clock" class="h-3.5 w-3.5" /></span> Saldo pendiente</p>
                <p @class(['caja-tarjeta-valor', 'is-pendiente' => $compra->saldoPendiente() > 0])>{{ $soles($compra->saldoPendiente()) }}</p>
            </article>
        </section>

        <nav class="compra-tabs" role="tablist" aria-label="Secciones de la compra">
            @foreach ($tabs as $clave => $nombre)
                <a href="{{ request()->fullUrlWithQuery(['tab' => $clave]) }}" role="tab" id="tab-{{ $clave }}" aria-controls="panel-{{ $clave }}" aria-selected="{{ $tab === $clave ? 'true' : 'false' }}" data-tab="{{ $clave }}" @class(['is-activa' => $tab === $clave])>
                    {{ $nombre }}
                    @isset($contadores[$clave])
                        <span class="compra-tabs-n">{{ $contadores[$clave] }}</span>
                    @endisset
                </a>
            @endforeach
        </nav>

        {{-- ------------------------------------------------------------ Resumen --}}
        <section id="panel-resumen" role="tabpanel" aria-labelledby="tab-resumen" class="compra-panel" data-panel="resumen" @if ($tab !== 'resumen') hidden @endif>
            <div class="compra-tarjeta">
                <h2 class="compra-tarjeta-titulo">Datos de la compra</h2>
                <dl class="fichas-datos compra-datos">
                    <div><dt>Código</dt><dd>{{ $compra->codigo() }}</dd></div>
                    <div><dt>Fecha</dt><dd>{{ $compra->fecha()->format('d/m/Y') }}</dd></div>
                    <div><dt>Tipo de compra</dt><dd>{{ $detalle['categoria']?->nombre() ?? '—' }}</dd></div>
                    <div><dt>Comprobante</dt><dd>{{ $compra->tipoComprobante()?->etiqueta() ?? 'Boleta' }}{{ $compra->numeroComprobante() ? ' '.$compra->numeroComprobante() : '' }}</dd></div>
                    <div><dt>Proveedor comercial</dt><dd>{{ $compra->proveedorComercial() ?? '—' }}</dd></div>
                    <div><dt>RUC</dt><dd>{{ $compra->ruc() ?? '—' }}</dd></div>
                    <div><dt>Moneda</dt><dd>{{ $compra->moneda() === 'PEN' ? 'Soles (S/)' : $compra->moneda() }}</dd></div>
                    <div><dt>Origen</dt><dd>{{ $compra->origen() === 'BOLETA' ? 'Boleta escaneada' : 'Registro manual' }}</dd></div>
                    <div><dt>Estado de recepción</dt><dd>{{ $compra->estadoRecepcion()->etiqueta() }}</dd></div>
                    <div><dt>Registrado por</dt><dd>{{ $detalle['registrado_por'] }}</dd></div>
                    @if ($compra->impuesto() > 0)
                        <div><dt>Impuesto</dt><dd>{{ $soles($compra->impuesto()) }}</dd></div>
                    @endif
                    @if ($compra->observacion())
                        <div class="is-ancho"><dt>Observación</dt><dd>{{ $compra->observacion() }}</dd></div>
                    @endif
                </dl>

                @php $insumosCompra = collect($detalle['lineas'])->whereNotNull('insumo_id')->unique('insumo_id'); @endphp
                @if ($insumosCompra->isNotEmpty() || $primerComprobante)
                    <div class="compra-enlaces">
                        @foreach ($insumosCompra as $linea)
                            <a href="{{ route('admin.insumos.index', ['insumo' => $linea['insumo_id']]) }}" class="fichas-btn is-outline is-sm"><x-ui.icon name="package" class="h-4 w-4" /> Ver insumo {{ $linea['insumo'] }}</a>
                        @endforeach
                        @if ($primerComprobante)
                            <a href="{{ route('admin.compras.comprobantes.show', [$compra->id(), $primerComprobante->id()]) }}" target="_blank" rel="noopener" class="fichas-btn is-outline is-sm"><x-ui.icon name="file-text" class="h-4 w-4" /> Ver comprobante</a>
                        @endif
                    </div>
                @endif
            </div>
        </section>

        {{-- ------------------------------------------------------------ Artículos --}}
        <section id="panel-articulos" role="tabpanel" aria-labelledby="tab-articulos" class="compra-panel" data-panel="articulos" @if ($tab !== 'articulos') hidden @endif>
            @if ($pendienteRecibir)
                <div class="compra-aviso">
                    <x-ui.icon name="truck" class="h-5 w-5 shrink-0" />
                    <p class="flex-1"><strong>Los artículos de inventario aún no llegan.</strong> El stock aumentará una sola vez, cuando confirmes la recepción.</p>
                    @can('recibir', $modelo)
                        <form method="POST" action="{{ route('admin.compras.recepcion', $compra->id()) }}" data-confirm="¿Confirmar que llegaron los artículos? Su stock aumentará ahora.">
                            @csrf
                            <button type="submit" class="fichas-btn is-primary is-sm"><x-ui.icon name="check" class="h-4 w-4" /> Confirmar recepción</button>
                        </form>
                    @endcan
                </div>
            @endif

            <div class="fichas-tabla compras-tabla">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Artículo o concepto</th>
                            <th scope="col">Tratamiento</th>
                            <th scope="col">Cantidad</th>
                            <th scope="col" class="is-num">Precio unitario</th>
                            <th scope="col" class="is-num">Subtotal</th>
                            <th scope="col">Efecto en inventario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detalle['lineas'] as $linea)
                            <tr>
                                <td data-etiqueta="Artículo">
                                    @if ($linea['insumo_id'])
                                        <a href="{{ route('admin.insumos.index', ['insumo' => $linea['insumo_id']]) }}" class="fichas-nombre">{{ $linea['descripcion'] }}</a>
                                    @else
                                        <span class="font-medium text-text">{{ $linea['descripcion'] }}</span>
                                    @endif
                                </td>
                                <td class="is-muted" data-etiqueta="Tratamiento">{{ \App\Domain\Shared\Enums\TratamientoCompra::from($linea['tratamiento'])->etiquetaCorta() }}</td>
                                <td data-etiqueta="Cantidad">{{ $linea['presentacion'] }}</td>
                                <td class="is-num" data-etiqueta="Precio">{{ $soles($linea['precio_unitario']) }}@if ($linea['precio_unitario'] !== null) c/u @endif</td>
                                <td class="is-num" data-etiqueta="Subtotal">{{ $soles($linea['subtotal']) }}</td>
                                <td data-etiqueta="Inventario">
                                    @if ($linea['tratamiento'] !== 'INVENTARIO')
                                        <span class="text-text-muted">No mueve stock</span>
                                    @elseif ($linea['movimiento_id'])
                                        <a href="{{ route('admin.insumos.movimientos', ['insumo_id' => $linea['insumo_id']]) }}" class="font-semibold text-success">+{{ $fmt($linea['cantidad_base']) }} {{ UnidadMedida::simbolo((string) $linea['unidad_base']) }}</a>
                                        <span class="block text-xs text-text-muted">Movimiento #{{ $linea['movimiento_id'] }}</span>
                                    @else
                                        <span class="text-warning">+{{ $fmt($linea['cantidad_base']) }} {{ UnidadMedida::simbolo((string) $linea['unidad_base']) }} al recibir</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        @if ($compra->impuesto() > 0)
                            <tr><td colspan="4" class="is-num is-muted">Impuesto</td><td class="is-num">{{ $soles($compra->impuesto()) }}</td><td></td></tr>
                        @endif
                        <tr><td colspan="4" class="is-num font-semibold">Total</td><td class="is-num font-bold">{{ $soles($compra->total()) }}</td><td></td></tr>
                    </tfoot>
                </table>
            </div>
        </section>

        {{-- ------------------------------------------------------------ Comprobantes --}}
        <section id="panel-comprobantes" role="tabpanel" aria-labelledby="tab-comprobantes" class="compra-panel" data-panel="comprobantes" @if ($tab !== 'comprobantes') hidden @endif>
            @if (count($detalle['comprobantes']) === 0)
                <div class="fichas-vacio"><x-ui.icon name="file-text" class="h-5 w-5 shrink-0" /> <span>Esta compra no tiene documentos adjuntos.</span></div>
            @else
                <ul class="compra-documentos">
                    @foreach ($detalle['comprobantes'] as $comprobante)
                        @php $url = route('admin.compras.comprobantes.show', [$compra->id(), $comprobante->id()]); @endphp
                        <li>
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="compra-documento-vista" aria-label="Previsualizar {{ $comprobante->tipo()->etiqueta() }}">
                                @if ($comprobante->esPdf())
                                    <span class="compra-documento-icono"><x-ui.icon name="file-text" class="h-8 w-8" /><small>PDF</small></span>
                                @else
                                    <img src="{{ $url }}" alt="{{ $comprobante->tipo()->etiqueta() }}" loading="lazy">
                                @endif
                            </a>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-text">{{ $comprobante->tipo()->etiqueta() }} @if ($comprobante->tipo()->esDePago()) <span class="text-xs font-normal text-text-muted">· comprobante de pago</span> @endif</p>
                                <p class="truncate text-xs text-text-muted">{{ $comprobante->nombreOriginal() ?? basename($comprobante->ruta()) }}{{ $comprobante->procesadoOcr() ? ' · leído por OCR' : '' }}</p>
                                @if ($comprobante->subidoEn())
                                    <p class="text-xs text-text-muted">{{ \Carbon\CarbonImmutable::instance($comprobante->subidoEn())->timezone(config('app.zona_horaria_negocio'))->format('d/m/Y H:i') }}</p>
                                @endif
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="fichas-btn is-outline is-sm"><x-ui.icon name="eye" class="h-4 w-4" /> Previsualizar</a>
                                    <a href="{{ $url }}?descargar=1" class="fichas-btn is-outline is-sm"><x-ui.icon name="download" class="h-4 w-4" /> Descargar</a>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @can('adjuntarComprobante', $modelo)
                <form method="POST" action="{{ route('admin.compras.comprobantes.store', $compra->id()) }}" enctype="multipart/form-data" class="compra-tarjeta mt-4 grid gap-3 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)_auto] sm:items-end">
                    @csrf
                    <x-forms.select name="tipo" id="comprobante-tipo" label="Documento" :options="$tiposDocumento" value="FACTURA" placeholder="" />
                    <div>
                        <x-forms.label for="comprobante-archivo">Archivo (imagen o PDF, hasta 8 MB)</x-forms.label>
                        <input id="comprobante-archivo" name="archivo" type="file" required accept="image/jpeg,image/png,image/webp,application/pdf" class="block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-2 file:text-sm file:font-semibold file:text-primary-dark">
                    </div>
                    <button type="submit" class="fichas-btn is-primary"><x-ui.icon name="plus" class="h-4 w-4" /> Adjuntar</button>
                </form>
            @endcan
        </section>

        {{-- ------------------------------------------------------------ Pagos --}}
        <section id="panel-pagos" role="tabpanel" aria-labelledby="tab-pagos" class="compra-panel" data-panel="pagos" @if ($tab !== 'pagos') hidden @endif>
            @if (count($detalle['pagos']) === 0)
                <div class="fichas-vacio"><x-ui.icon name="wallet" class="h-5 w-5 shrink-0" /> <span>{{ $compra->total() === null ? 'Esta compra no tiene importe registrado.' : 'Todavía no se registró ningún pago: no salió dinero de Caja por esta compra.' }}</span></div>
            @else
                <div class="fichas-tabla compras-tabla">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Fecha</th>
                                <th scope="col">Pago</th>
                                <th scope="col">Método</th>
                                <th scope="col">N.º de operación</th>
                                <th scope="col">Registrado por</th>
                                <th scope="col" class="is-num">Monto</th>
                                <th scope="col">Caja</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detalle['pagos'] as $fila)
                                @php $pago = $fila['pago']; @endphp
                                <tr>
                                    <td data-etiqueta="Fecha">{{ \Carbon\CarbonImmutable::instance($pago->fecha())->timezone(config('app.zona_horaria_negocio'))->format('d/m/Y H:i') }}</td>
                                    <td data-etiqueta="Pago">{{ $pago->codigo() }}@if ($pago->observacion()) <span class="block text-xs text-text-muted">{{ $pago->observacion() }}</span>@endif</td>
                                    <td class="is-muted" data-etiqueta="Método">{{ $pago->metodoPago()?->etiqueta() ?? 'No registrado' }}</td>
                                    <td class="is-muted" data-etiqueta="Operación">{{ $pago->numeroOperacion() ?? '—' }}</td>
                                    <td class="is-muted" data-etiqueta="Registrado por">{{ $fila['registrado_por'] }}</td>
                                    <td class="is-num font-semibold" data-etiqueta="Monto">{{ $soles($pago->monto()) }}</td>
                                    <td data-etiqueta="Caja">
                                        @if ($pago->movimientoCajaId())
                                            <a href="{{ route('admin.caja.index', ['movimiento' => $pago->movimientoCajaId()]) }}" class="font-medium text-primary hover:text-primary-dark">Ver en Caja</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($compra->saldoPendiente() > 0)
                @can('registrarPago', $modelo)
                    <form method="POST" action="{{ route('admin.compras.pagos.store', $compra->id()) }}" class="compra-tarjeta mt-4 space-y-4" data-pago-form>
                        @csrf
                        <input type="hidden" name="clave_envio" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <h2 class="compra-tarjeta-titulo">Registrar pago</h2>
                        <p class="text-sm text-text-muted">Saldo pendiente: <strong class="text-text">{{ $soles($compra->saldoPendiente()) }}</strong>. Solo se registra en Caja lo que pagas ahora.</p>
                        <div class="grid gap-4 sm:grid-cols-4">
                            <x-forms.input name="monto" id="pago-monto" type="number" step="0.01" min="0.01" :max="$compra->saldoPendiente()" label="Monto (S/)" required :value="number_format($compra->saldoPendiente(), 2, '.', '')" />
                            <x-forms.select name="metodo_pago" id="pago-metodo" label="Método de pago" required :options="$metodosPago" value="EFECTIVO" />
                            <x-forms.input name="numero_operacion" id="pago-operacion" label="N.º de operación" maxlength="60" />
                            <x-forms.input name="observacion" id="pago-observacion" label="Observación" maxlength="255" />
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="fichas-btn is-primary" data-pago-enviar><x-ui.icon name="wallet" class="h-4 w-4" /> Registrar pago</button>
                        </div>
                    </form>
                @endcan
            @endif
        </section>
    </div>
@endsection
