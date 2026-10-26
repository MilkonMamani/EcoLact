{{--
    Hoja A4 "Comprobante de pago" semanal de un proveedor. Se usa igual en el panel derecho,
    en el modal de vista previa, en el papel y en la página individual; solo cambia la escala
    (resources/css/components/pago-semanal.css). Explica el cálculo paso a paso (litros, precio
    base, resultado semanal, ajuste, precio aplicado, compras y total), pero NO calcula nada:
    todas las cifras vienen de ComprobantePagoProveedor.

    @var \App\Application\Pago\ComprobantePagoProveedor $c
--}}
@php
    $soles = fn (float $valor): string => ($valor < -0.004 ? '− ' : '').'S/ '.number_format(abs($valor), 2);
    $porLitro = fn (float $valor): string => $soles($valor).' / L';
    $litros = fn (float $valor): string => rtrim(rtrim(number_format($valor, 2, '.', ','), '0'), '.').' L';
    $empresa = config('empresa.nombre') ?: 'EcoLact';
    $semanaNumero = preg_replace('/\D+/', '', $c->semanaEtiqueta) ?: $c->semanaEtiqueta;
    $resultado = $c->resultadoSemanal();
    $ajuste = $c->ajustePorLitro();
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

        <section class="pp-proveedor" aria-label="Datos del pago">
            <dl class="pp-datos">
                <div class="is-ancho"><dt>Proveedor</dt><dd class="pp-proveedor-nombre">{{ $c->proveedorNombre }}</dd></div>
                <div><dt>DNI</dt><dd>{{ $c->dni }}</dd></div>
                <div><dt>Zona</dt><dd>{{ $c->zona }}</dd></div>
                <div><dt>Código</dt><dd>{{ $c->codigo }}</dd></div>
                <div><dt>Semana</dt><dd>{{ $semanaNumero }}</dd></div>
                <div><dt>Periodo semanal</dt><dd>{{ str_replace(' - ', ' – ', $c->rango) }}</dd></div>
                <div><dt>Fecha de emisión</dt><dd>{{ $c->fechaEmision() }}</dd></div>
                <div><dt>Estado</dt><dd data-hoja-estado>{{ $c->etiquetaEstado() }}</dd></div>
            </dl>
            @if ($c->estaPagado() && $c->pagadoEl)
                <p class="pp-pagado" data-hoja-pagado>Pagado el {{ $c->pagadoEl }}@if ($c->pagadoPor) · Registrado por {{ $c->pagadoPor }}@endif</p>
            @endif
        </section>

        <section class="pp-seccion" aria-label="Detalle de pago">
            <h2 class="pp-etiqueta">Detalle de pago</h2>
            <dl class="pp-calculo">
                <div><dt>Litros de la semana</dt><dd>{{ $litros($c->litrosDeLaSemana()) }}</dd></div>
                <div><dt>Precio base</dt><dd>{{ $porLitro($c->precioLitro) }}</dd></div>
                <div><dt>Resultado semanal</dt><dd><span class="pp-resultado is-{{ strtolower($resultado) }}">{{ $c->etiquetaResultado() }}</span></dd></div>
                @if ($resultado === 'OBSERVADA')
                    <div>
                        <dt>Penalización semanal<small>La penalización se aplica a todos los litros de la semana.</small></dt>
                        <dd class="is-negativo">{{ $porLitro($ajuste) }}</dd>
                    </div>
                @elseif ($resultado !== 'RECHAZADA')
                    <div><dt>Ajuste calidad</dt><dd>{{ $porLitro($ajuste) }}</dd></div>
                @endif
                <div><dt>Precio aplicado</dt><dd>{{ $porLitro($c->precioAplicadoPorLitro()) }}</dd></div>
                <div class="is-subtotal"><dt>Importe por leche</dt><dd>{{ $soles($c->subtotalLeche) }}</dd></div>
            </dl>
            @if ($c->esNoPagable())
                <p class="pp-aviso" data-hoja-no-pagable>La semana no es pagable por resultado de calidad rechazado.</p>
            @endif
        </section>

        @if ($c->descuentos !== [])
            <section class="pp-seccion" aria-label="Descuentos">
                <h2 class="pp-etiqueta">Descuentos</h2>
                <table class="pp-tabla">
                    <thead>
                        <tr>
                            <th scope="col">Compras en planta</th>
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
                                <td class="is-num">{{ $soles(-$descuento['importe']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        <section class="pp-cierre" aria-label="Total">
            <dl class="pp-totales">
                <div><dt>Importe por leche</dt><dd>{{ $soles($c->subtotalLeche) }}</dd></div>
                <div><dt>Compras descontadas</dt><dd>{{ $c->subtotalDescuentos > 0 ? $soles(-$c->subtotalDescuentos) : 'S/ 0.00' }}</dd></div>
                <div class="is-total"><dt>Total a pagar</dt><dd data-hoja-total>S/ {{ number_format($c->totalFinal, 2) }}</dd></div>
            </dl>
            <p class="pp-forma-pago">Forma de pago: Efectivo</p>
        </section>
    </div>

    <footer class="pp-firmas">
        <div class="pp-firma">
            <span class="pp-firma-linea"></span>
            <p class="pp-firma-titulo">Firma del proveedor</p>
            <p>{{ $c->proveedorNombre }} · DNI {{ $c->dni }}</p>
        </div>
        <div class="pp-firma">
            <span class="pp-firma-linea"></span>
            <p class="pp-firma-titulo">Firma autorizada</p>
            <p>{{ $empresa }}</p>
        </div>
    </footer>
</article>
