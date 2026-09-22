{{--
    Hoja "Pago semanal a proveedor" (A4). Se usa igual en la miniatura de la pantalla,
    en el modal de vista previa, en el papel y en la página individual. Recibe:
    - $c: App\Application\Pago\ComprobantePagoProveedor
--}}
@php
    $soles = fn (float $valor): string => ($valor < 0 ? '- ' : '').'S/ '.number_format(abs($valor), 2);
    $hayPenalidad = abs($c->penalidad) >= 0.005;
    $compacta = count($c->descuentos) > 6;
@endphp
<article class="pp-hoja @if ($compacta) pp-compacta @endif" data-hoja-id="{{ $c->obligacionId }}" aria-label="Pago semanal a proveedor · {{ $c->proveedorNombre }}">
    <header class="pp-cabecera">
        <div class="pp-marca">
            <img src="{{ asset('images/ecolact-mark.svg') }}" alt="">
            <div><strong>EcoLact</strong><small>Del campo a tu mesa</small></div>
        </div>
        <div class="pp-lema">
            <span>Productores de hoy, una mejor mañana</span>
            <i></i>
            <x-ui.icon name="leaf" class="" />
        </div>
    </header>

    <div class="pp-titulo-fila">
        <div class="pp-titulo-caja">
            <span class="pp-icono-caja"><x-ui.icon name="calendar-coin" class="" /></span>
            <div>
                <h1>Pago semanal a proveedor</h1>
                <p>Comprobante de pago por la entrega de leche y descuentos</p>
            </div>
        </div>
        <div class="pp-semana">
            <x-ui.icon name="calendar-days" class="" />
            <div>
                <strong>{{ $c->semanaEtiqueta }}</strong>
                <span>{{ $c->rango }}</span>
                @if ($c->estaPagado() && $c->pagadoEl)
                    <em class="pp-pagado-sello not-italic">Pagado el {{ $c->pagadoEl }}@if ($c->pagadoPor) · por {{ $c->pagadoPor }}@endif</em>
                @endif
            </div>
        </div>
    </div>

    <section class="pp-seccion">
        <div class="pp-seccion-cabecera">
            <span class="pp-seccion-icono"><x-ui.icon name="user" class="" /></span>
            <h2>1. Datos del proveedor</h2>
        </div>
        <dl class="pp-datos">
            <div><dt>Nombre del proveedor</dt><dd>{{ $c->proveedorNombre }}</dd></div>
            <div><dt>DNI</dt><dd>{{ $c->dni }}</dd></div>
            <div><dt>Zona</dt><dd>{{ $c->zona }}</dd></div>
            <div><dt>Código de proveedor</dt><dd>{{ $c->codigo }}</dd></div>
        </dl>
    </section>

    <section class="pp-seccion">
        <div class="pp-seccion-cabecera">
            <span class="pp-seccion-icono"><x-ui.icon name="milk-jar" class="" /></span>
            <h2>2. Detalle de entrega de leche</h2>
        </div>
        <table class="pp-tabla">
            <thead>
                <tr>
                    <th class="pp-izq" style="width: 25%">Concepto</th>
                    <th class="pp-izq" style="width: 29%">Detalle</th>
                    <th style="width: 13%">Cantidad /<br>Valor</th>
                    <th style="width: 13%">Precio por litro</th>
                    <th style="width: 20%">Importe (S/)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="pp-izq">Litros pagables</td>
                    <td class="pp-izq pp-suave">Leche de calidad</td>
                    <td>{{ rtrim(rtrim(number_format($c->litros, 2, '.', ''), '0'), '.') }} L</td>
                    <td class="pp-suave">{{ $soles($c->precioLitro) }}</td>
                    <td class="pp-importe">{{ $soles($c->importeLeche) }}</td>
                </tr>
                <tr>
                    <td class="pp-izq">{{ $c->penalidad > 0 ? 'Bonificación por calidad' : 'Penalidad por calidad' }}</td>
                    <td class="pp-izq pp-suave">{{ $c->detallePenalidad }}</td>
                    <td>{{ $hayPenalidad ? ($c->penalidad < 0 ? '- ' : '+ ').number_format(abs($c->penalidadLitros), 2) : '-' }}</td>
                    <td class="pp-suave">{{ $hayPenalidad ? $soles($c->precioLitro) : '-' }}</td>
                    <td class="pp-importe">{{ $hayPenalidad ? $soles($c->penalidad) : $soles(0) }}</td>
                </tr>
                <tr>
                    <td class="pp-izq">Ajuste</td>
                    <td class="pp-izq pp-suave">Sin ajuste adicional</td>
                    <td>-</td>
                    <td class="pp-suave">-</td>
                    <td class="pp-importe">{{ $soles(0) }}</td>
                </tr>
            </tbody>
        </table>
        <div class="pp-subtotal"><span>Subtotal de leche</span><strong>{{ $soles($c->subtotalLeche) }}</strong></div>
    </section>

    <section class="pp-seccion">
        <div class="pp-seccion-cabecera">
            <span class="pp-seccion-icono"><x-ui.icon name="shopping-cart" class="" /></span>
            <div>
                <h2>3. Productos comprados / descuentos</h2>
                <p>Productos adquiridos por el proveedor, que se descuentan de su pago.</p>
            </div>
        </div>
        <table class="pp-tabla">
            <thead>
                <tr>
                    <th class="pp-izq" style="width: 28%">Producto</th>
                    <th style="width: 17%">Presentación</th>
                    <th style="width: 15%">Cantidad</th>
                    <th style="width: 20%">Precio unitario</th>
                    <th style="width: 20%">Importe (S/)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($c->descuentos as $descuento)
                    <tr>
                        <td class="pp-izq">{{ $descuento['producto'] }}</td>
                        <td class="pp-suave">{{ $descuento['presentacion'] }}</td>
                        <td class="pp-suave">{{ $descuento['cantidad'] }}</td>
                        <td class="pp-suave">{{ $descuento['precioUnitario'] > 0 ? $soles($descuento['precioUnitario']) : '-' }}</td>
                        <td class="pp-importe">{{ $soles($descuento['importe']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="pp-vacio">Sin compras descontadas en esta semana.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="pp-subtotal"><span>Subtotal de descuentos</span><strong>{{ $c->subtotalDescuentos > 0 ? '- ' : '' }}S/ {{ number_format($c->subtotalDescuentos, 2) }}</strong></div>
    </section>

    <section class="pp-total">
        <span class="pp-icono-caja"><x-ui.icon name="banknote" class="" /></span>
        <div>
            <h3>Monto final total a entregar</h3>
            <p>Pago en efectivo</p>
        </div>
        <strong>S/ {{ number_format($c->totalFinal, 2) }}</strong>
    </section>

    <div class="pp-firmas">
        <div class="pp-firma">
            <x-ui.icon name="pen-line" class="" />
            <div class="pp-firma-linea"></div>
            <h4>Recibí conforme</h4>
            <dl>
                <div><dt>Nombre:</dt><dd></dd></div>
                <div><dt>DNI:</dt><dd></dd></div>
                <div><dt>Fecha:</dt><dd class="pp-fecha"><i></i>/<i></i>/ {{ $c->anioFirma }}</dd></div>
            </dl>
        </div>
        <div class="pp-firma">
            <x-ui.icon name="pen-line" class="" />
            <div class="pp-firma-linea"></div>
            <h4>Entregado por (EcoLact)</h4>
            <dl>
                <div><dt>Nombre:</dt><dd></dd></div>
                <div><dt>Cargo:</dt><dd></dd></div>
                <div><dt>Fecha:</dt><dd class="pp-fecha"><i></i>/<i></i>/ {{ $c->anioFirma }}</dd></div>
            </dl>
        </div>
    </div>

    <footer class="pp-pie">
        <div class="pp-pie-frase">
            <x-ui.icon name="leaf" class="" />
            <p>¡Gracias por tu esfuerzo, juntos llevamos nutrición a más familias!</p>
        </div>
        <div class="pp-pie-marca"><strong>EcoLact</strong><small>Del campo a tu mesa</small></div>
    </footer>
</article>
