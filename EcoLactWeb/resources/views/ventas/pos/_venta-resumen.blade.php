{{--
    Resumen de venta (columna derecha, sticky en escritorio). Lo rellena ventas-pos.js como
    previsualización; el importe que vale es el que recalcula RegistrarVentaProducto.
    En móvil, la barra inferior repite total + "Confirmar venta" para no tener que bajar.
--}}
<aside class="vpos-resumen" aria-labelledby="vpos-resumen-titulo" data-vpos-resumen-panel>
    <div class="vpos-resumen-cabecera">
        <h2 id="vpos-resumen-titulo" class="vpos-resumen-kicker">Resumen de venta</h2>
        <span class="vpos-estado-form" data-vpos-estado>Incompleta</span>
    </div>

    <dl class="vpos-resumen-datos">
        <div><dt>Comprador</dt><dd data-vpos-r="cliente">Público general</dd></div>
        <div><dt>Producto</dt><dd data-vpos-r="producto">—</dd></div>
        <div><dt>Cantidad</dt><dd data-vpos-r="cantidad">—</dd></div>
        <div><dt>Precio unitario</dt><dd><span data-vpos-r="unitario">—</span> <small class="vpos-resumen-tipo" data-vpos-r="tipo"></small></dd></div>
    </dl>

    <div class="vpos-resumen-total">
        <span>Total</span>
        <strong data-vpos-r="total">S/ 0.00</strong>
    </div>

    <dl class="vpos-resumen-datos">
        <div><dt>Stock disponible</dt><dd data-vpos-r="stock">—</dd></div>
        <div><dt>Stock resultante</dt><dd data-vpos-r="stockDespues">—</dd></div>
        <div><dt>Forma de pago</dt><dd data-vpos-r="pago">Contado</dd></div>
    </dl>

    <p class="vpos-resumen-motivo" data-vpos-motivo role="status" aria-live="polite"></p>

    <button type="submit" class="fichas-btn is-primary vpos-confirmar" data-vpos-confirmar>
        <x-ui.icon name="check" class="h-4 w-4" /> Confirmar venta
    </button>
</aside>

<div class="vpos-barra" data-vpos-barra>
    <div class="min-w-0">
        <span class="vpos-barra-etiqueta">Total</span>
        <strong class="vpos-barra-total" data-vpos-r="totalBarra">S/ 0.00</strong>
    </div>
    <button type="submit" class="fichas-btn is-primary" data-vpos-confirmar>
        <x-ui.icon name="check" class="h-4 w-4" /> Confirmar venta
    </button>
</div>
