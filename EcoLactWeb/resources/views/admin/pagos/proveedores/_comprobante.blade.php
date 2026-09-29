{{--
    Hoja A4 "Comprobante de pago" semanal de un proveedor: documento formal (misma estética
    que el comprobante de trabajadores). Se usa igual en la miniatura de la pantalla, en el
    modal de vista previa, en el papel y en la página individual; solo cambia la escala
    (resources/css/components/pago-semanal.css). Solo pinta ComprobantePagoProveedor: no
    recalcula litros, precios, calidad, compras ni totales.

    @var \App\Application\Pago\ComprobantePagoProveedor $c
--}}
@php
    $soles = fn (float $valor): string => ($valor < 0 ? '− ' : '').'S/ '.number_format(abs($valor), 2);
    $litros = fn (float $valor): string => rtrim(rtrim(number_format($valor, 2, '.', ','), '0'), '.').' L';
    $empresa = config('empresa.nombre') ?: 'EcoLact';
    $semanaNumero = preg_replace('/\D+/', '', $c->semanaEtiqueta) ?: $c->semanaEtiqueta;
@endphp
<article class="pp-hoja" data-hoja-id="{{ $c->obligacionId }}" aria-label="Comprobante de pago {{ $c->numero() }} · {{ $c->proveedorNombre }}">
    <div class="pp-cuerpo">
        <header class="pp-cabecera">
            <div class="pp-marca">
                <strong>{{ $empresa }}</strong>
                <span>Gestión integral</span>
            </div>
            <div class="pp-documento">
                <h1>Comprobante de pago</h1>
                <p>N.º {{ $c->numero() }}</p>
            </div>
        </header>

        <dl class="pp-resumen">
            <div><dt>Fecha de emisión</dt><dd>{{ $c->fechaEmision() }}</dd></div>
            <div><dt>Semana</dt><dd>{{ $semanaNumero }}</dd></div>
            <div><dt>Periodo</dt><dd>{{ str_replace(' - ', ' – ', $c->rango) }}</dd></div>
        </dl>
        @if ($c->estaPagado() && $c->pagadoEl)
            <p class="pp-pagado" data-hoja-pagado>Pagado el {{ $c->pagadoEl }}@if ($c->pagadoPor) · Registrado por {{ $c->pagadoPor }}@endif</p>
        @else
            <p class="pp-pagado">Estado: {{ $c->estaPagado() ? 'Pagado' : 'Pendiente' }}</p>
        @endif

        <section class="pp-proveedor" aria-label="Proveedor">
            <p class="pp-etiqueta">Proveedor</p>
            <p class="pp-proveedor-nombre">{{ $c->proveedorNombre }}</p>
            <dl class="pp-proveedor-datos">
                <div><dt>DNI</dt><dd>{{ $c->dni }}</dd></div>
                <div><dt>Zona</dt><dd>{{ $c->zona }}</dd></div>
                <div><dt>Código</dt><dd>{{ $c->codigo }}</dd></div>
            </dl>
        </section>

        <section class="pp-seccion" aria-label="Detalle de pago">
            <h2 class="pp-etiqueta">Detalle de pago</h2>
            <table class="pp-tabla">
                <thead>
                    <tr>
                        <th scope="col">Concepto</th>
                        <th scope="col">Detalle</th>
                        <th scope="col" class="is-num">Cantidad</th>
                        <th scope="col" class="is-num">Precio</th>
                        <th scope="col" class="is-num">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Leche pagable</td>
                        <td>{{ $litros($c->litros) }} de leche entregada</td>
                        <td class="is-num">{{ $litros($c->litros) }}</td>
                        <td class="is-num">{{ $soles($c->precioLitro) }}</td>
                        <td class="is-num">{{ $soles($c->importeLeche) }}</td>
                    </tr>
                    @if ($c->tieneAjusteDeCalidad())
                    <tr>
                        <td>{{ $c->conceptoCalidad() }}</td>
                        <td>{{ $c->detalleCalidad() }}</td>
                        <td class="is-num">{{ $c->tieneAjusteDeCalidad() ? ($c->penalidadLitros < 0 ? '− ' : '+ ').$litros(abs($c->penalidadLitros)) : '—' }}</td>
                        <td class="is-num">{{ $c->tieneAjusteDeCalidad() ? $soles($c->precioLitro) : '—' }}</td>
                        <td @class(['is-num', 'is-negativo' => $c->penalidad <= -0.005])>{{ $soles($c->penalidad) }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
            @if (! $c->tieneAjusteDeCalidad())
                <p class="pp-nota">{{ $c->conceptoCalidad() }}</p>
            @endif
            <div class="pp-subtotal"><span>Subtotal por leche</span><strong>{{ $soles($c->subtotalLeche) }}</strong></div>
        </section>

        <section class="pp-seccion" aria-label="Compras descontadas">
            <h2 class="pp-etiqueta">Compras descontadas</h2>
            <p class="pp-nota">Productos adquiridos por el proveedor durante la semana y descontados de su pago.</p>
            @if ($c->descuentos === [])
                <p class="pp-sin-compras" data-sin-compras>No se registraron compras para descontar durante esta semana.</p>
            @else
                <table class="pp-tabla">
                    <thead>
                        <tr>
                            <th scope="col">Producto</th>
                            <th scope="col">Presentación</th>
                            <th scope="col" class="is-num">Cantidad</th>
                            <th scope="col" class="is-num">Precio unitario</th>
                            <th scope="col" class="is-num">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($c->descuentos as $descuento)
                            <tr>
                                <td>{{ $descuento['producto'] }}</td>
                                <td>{{ $descuento['presentacion'] }}</td>
                                <td class="is-num">{{ $descuento['cantidad'] }}</td>
                                <td class="is-num">{{ $descuento['precioUnitario'] > 0 ? $soles($descuento['precioUnitario']) : '—' }}</td>
                                <td class="is-num">{{ $soles($descuento['importe']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <div class="pp-subtotal"><span>Total compras descontadas</span><strong>{{ $c->subtotalDescuentos > 0 ? '− ' : '' }}S/ {{ number_format($c->subtotalDescuentos, 2) }}</strong></div>
        </section>

        <section class="pp-cierre" aria-label="Resumen">
            <div class="pp-totales">
                <p class="pp-etiqueta">Resumen</p>
                <dl>
                    <div><dt>Importe de leche</dt><dd>{{ $soles($c->importeLeche) }}</dd></div>
                    <div><dt>Bonificación / penalidad</dt><dd>{{ $soles($c->penalidad) }}</dd></div>
                    <div><dt>Compras descontadas</dt><dd>{{ $c->subtotalDescuentos > 0 ? '− ' : '' }}S/ {{ number_format($c->subtotalDescuentos, 2) }}</dd></div>
                    <div class="is-total"><dt>Total a entregar</dt><dd data-hoja-total>S/ {{ number_format($c->totalFinal, 2) }}</dd></div>
                </dl>
            </div>
            <div class="pp-forma-pago">
                <p class="pp-etiqueta">Forma de pago</p>
                <p>Efectivo</p>
            </div>
        </section>
    </div>

    <footer class="pp-firmas">
        <div class="pp-firma">
            <span class="pp-firma-linea"></span>
            <p class="pp-firma-titulo">Recibí conforme</p>
            <p>Nombre: {{ $c->proveedorNombre }}</p>
            <p>DNI: {{ $c->dni }}</p>
            <p>Fecha: ___ / ___ / ______</p>
        </div>
        <div class="pp-firma">
            <span class="pp-firma-linea"></span>
            <p class="pp-firma-titulo">Entregado por {{ $empresa }}</p>
            <p>Nombre: ______________________</p>
            <p>Cargo: _______________________</p>
            <p>Fecha: ___ / ___ / ______</p>
        </div>
    </footer>
</article>
