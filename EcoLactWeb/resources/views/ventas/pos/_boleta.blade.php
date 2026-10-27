{{--
    Boleta de venta (comprobante interno, sin IGV ni datos SUNAT). La misma hoja se ve en el
    detalle web y se imprime en el PDF (pdf.boleta-venta): $boleta viene de BoletaVenta::datos().
--}}
<div class="boleta">
    <div class="boleta-cabecera">
        <p class="boleta-marca">EcoLact</p>
        <p class="boleta-tipo">BOLETA DE VENTA</p>
        <p class="boleta-codigo">{{ $boleta['codigo'] }}</p>
    </div>

    <table class="boleta-fila">
        <tr>
            <td><span class="boleta-etiqueta">Fecha</span>{{ $boleta['fecha'] }}</td>
            <td class="boleta-derecha"><span class="boleta-etiqueta">Hora</span>{{ $boleta['hora'] }}</td>
        </tr>
    </table>

    <table class="boleta-fila boleta-separada">
        <tr>
            <td><span class="boleta-etiqueta">Cliente</span><strong>{{ $boleta['cliente'] }}</strong></td>
        </tr>
        <tr>
            <td><span class="boleta-etiqueta">{{ $boleta['etiquetaDocumento'] }}</span>{{ $boleta['documento'] }}</td>
        </tr>
    </table>

    <table class="boleta-detalle boleta-separada">
        <thead>
            <tr>
                <th>Cant.</th>
                <th>Producto</th>
                <th class="boleta-derecha">P. unit.</th>
                <th class="boleta-derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $boleta['cantidad'] }}</td>
                <td>{{ $boleta['producto'] }}</td>
                <td class="boleta-derecha">S/ {{ number_format($boleta['precioUnitario'], 2) }}</td>
                <td class="boleta-derecha">S/ {{ number_format($boleta['total'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="boleta-fila boleta-separada boleta-total">
        <tr>
            <td>TOTAL</td>
            <td class="boleta-derecha">S/ {{ number_format($boleta['total'], 2) }}</td>
        </tr>
    </table>

    <div class="boleta-separada">
        <span class="boleta-etiqueta">Forma de pago</span>
        <strong>{{ $boleta['formaPago'] }}</strong>
        @if ($boleta['descuento'])
            <p class="boleta-nota">Este monto se descontará del próximo pago semanal del proveedor.</p>
        @endif
    </div>
</div>
