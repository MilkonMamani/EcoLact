package pe.edu.upeu.ecolactmobile.feature.ventas

import kotlin.test.*

class VentasModelsTest {
    private val producto = ProductoVenta(1, "Queso Paria", 120.0, rangos = listOf(
        RangoVenta("PUBLICO", 0.0, 12.0, 5.0, "PUBLICO"),
        RangoVenta("PUBLICO", 12.0, null, 4.5, "MAYORISTA"),
        RangoVenta("PROVEEDOR", 0.0, null, 4.0, "PROVEEDOR"),
    ))

    @Test fun preciosUsanLosRangosDescargadosDelServidor() {
        assertEquals(CotizacionVenta(5.0, "PUBLICO", 25.0), cotizacionGuardada(producto, "PUBLICO", 5.0))
        assertEquals(CotizacionVenta(4.5, "MAYORISTA", 54.0), cotizacionGuardada(producto, "COMPRADOR", 12.0))
        assertEquals(CotizacionVenta(4.0, "PROVEEDOR", 8.0), cotizacionGuardada(producto, "PROVEEDOR", 2.0))
        // A changed server threshold must apply without changing the app's pricing code.
        val cambio = producto.copy(rangos = listOf(RangoVenta("PUBLICO", 20.0, null, 3.75, "MAYORISTA")))
        assertNull(cotizacionGuardada(cambio, "PUBLICO", 12.0))
        assertEquals(75.0, cotizacionGuardada(cambio, "PUBLICO", 20.0)?.importe_total)
    }

    @Test fun cantidadesInvalidasNoProducenCotizacion() {
        listOf(0.0, -1.0, Double.NaN, Double.POSITIVE_INFINITY).forEach { assertNull(cotizacionGuardada(producto, "PUBLICO", it)) }
    }

    private fun venta() = Venta(id = 18, producto_id = 1, cantidad = 2.0, precio_unitario = 5.0, importe_total = 10.0, tipo_cliente = "PUBLICO", forma_pago = "CONTADO", fecha = "2026-10-27T15:35:00Z")

    @Test fun boletaPublicoEsRepresentacionSinCrearPersona() {
        val v = venta()
        assertEquals("Público general", v.cliente)
        assertEquals("XXXXXXXX", v.documentoBoleta)
        assertNull(v.comprador_id)
        assertNull(v.documento)
        assertEquals("Efectivo", v.pago)
    }

    @Test fun boletaConservaDocumentoRealYDescuentoSeparado() {
        val proveedor = venta().copy(proveedor_id = 1, cliente = "Juan Quispe", documento = "41234567", forma_pago = "DESCUENTO_PLANILLA")
        assertEquals("DNI", proveedor.etiquetaDocumento)
        assertEquals("41234567", proveedor.documentoBoleta)
        assertEquals("Descuento del pago semanal", proveedor.pago)
        val comprador = venta().copy(comprador_id = 2, cliente = "Distribuidora SAC", documento = "20601234567")
        assertEquals("RUC", comprador.etiquetaDocumento)
        assertEquals("20601234567", comprador.documentoBoleta)
    }
}
