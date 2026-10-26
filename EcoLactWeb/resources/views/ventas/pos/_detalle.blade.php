{{--
    Detalle comercial de una venta (panel lateral; fragmento pedido por fetch o incluido al abrir
    la ruta show). Muestra el precio y el tipo de precio guardados al vender, no los actuales.
--}}
<div class="caja-detalle vpos-detalle">
    <div class="caja-detalle-cabecera">
        <p class="caja-detalle-kicker">Venta {{ $venta['codigo'] }}</p>
        <button type="button" class="caja-cerrar" data-vpos-cerrar aria-label="Cerrar detalle">
            <x-ui.icon name="x-mark" class="h-5 w-5" />
        </button>
    </div>

    <div class="caja-detalle-resumen">
        <h2 class="caja-detalle-titulo">{{ $venta['producto'] }}</h2>
        <p class="caja-detalle-monto">S/ {{ number_format($venta['total'], 2) }}</p>
        <p class="vpos-detalle-sub">{{ $venta['cantidad'] }} {{ $venta['cantidad'] === '1' ? 'unidad' : 'unidades' }} × S/ {{ number_format($venta['precioUnitario'], 2) }}</p>
    </div>

    <dl class="caja-detalle-datos">
        <div><dt>Fecha</dt><dd>{{ $venta['fecha'] }}</dd></div>
        <div><dt>Cliente</dt><dd>{{ $venta['cliente'] }}@if ($venta['clienteDocumento'])<span class="vpos-detalle-doc">{{ $venta['clienteDocumento'] }}</span>@endif</dd></div>
        <div><dt>Producto</dt><dd>{{ $venta['producto'] }}</dd></div>
        <div><dt>Cantidad</dt><dd>{{ $venta['cantidad'] }} {{ $venta['cantidad'] === '1' ? 'unidad' : 'unidades' }}</dd></div>
        <div><dt>Precio aplicado</dt><dd>{{ $venta['tipoPrecio'] }}</dd></div>
        <div><dt>Precio unitario</dt><dd>S/ {{ number_format($venta['precioUnitario'], 2) }}</dd></div>
        <div><dt>Total</dt><dd><strong>S/ {{ number_format($venta['total'], 2) }}</strong></dd></div>
        <div><dt>Forma de pago</dt><dd>{{ $venta['formaPago'] }}</dd></div>
        <div><dt>Registrado por</dt><dd>{{ $venta['registradoPor'] }}</dd></div>
        <div><dt>Estado</dt><dd>{{ ucfirst(mb_strtolower($venta['estado'])) }}</dd></div>
    </dl>

    @if ($venta['descuento'])
        <p class="caja-detalle-nota">Este importe se descuenta del pago semanal del proveedor.</p>
    @endif
</div>
