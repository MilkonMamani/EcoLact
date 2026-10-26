{{--
    Resumen de venta (columna derecha, siempre visible). Lo rellena ventas-pos.js como
    previsualización; el importe que vale es el que recalcula RegistrarVentaProducto.
--}}
<aside class="vpos-resumen" aria-labelledby="vpos-resumen-titulo">
    <h2 id="vpos-resumen-titulo" class="vpos-resumen-kicker">Resumen de venta</h2>

    <p class="vpos-resumen-vacio" data-vpos-resumen-vacio>Selecciona un producto y una cantidad para calcular la venta.</p>

    <div data-vpos-resumen hidden>
        <p class="vpos-resumen-producto" data-vpos-r="producto"></p>
        <p class="vpos-resumen-cliente" data-vpos-r="cliente"></p>

        <dl class="vpos-resumen-datos">
            <div><dt>Cantidad</dt><dd data-vpos-r="cantidad">—</dd></div>
            <div><dt>Precio aplicado</dt><dd data-vpos-r="tipo">—</dd></div>
            <div><dt>Precio unitario</dt><dd data-vpos-r="unitario">—</dd></div>
        </dl>

        <div class="vpos-resumen-total">
            <span>Total</span>
            <strong data-vpos-r="total">S/ 0.00</strong>
        </div>

        <dl class="vpos-resumen-datos">
            <div><dt>Stock actual</dt><dd data-vpos-r="stock">—</dd></div>
            <div><dt>Stock después</dt><dd data-vpos-r="stockDespues">—</dd></div>
            <div><dt>Pago</dt><dd data-vpos-r="pago">Contado</dd></div>
        </dl>
    </div>

    <p class="vpos-resumen-motivo" data-vpos-motivo hidden></p>

    <button type="submit" class="fichas-btn is-primary vpos-confirmar" data-vpos-confirmar>
        <x-ui.icon name="check" class="h-4 w-4" /> Confirmar venta
    </button>
</aside>
