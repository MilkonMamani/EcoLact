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

    @Test fun boletaSoloParaVentasAceptadasPorElServidor() {
        assertTrue(venta().boletaDisponible)
        assertEquals("EcoLact_Venta_V-00018.pdf", venta().nombreArchivoBoleta)
        assertEquals("EcoLact_Venta_V-00018.pdf", venta().copy(codigoServidor = "V-00018").nombreArchivoBoleta)
        assertFalse(venta().copy(id = 0, localId = "abc").boletaDisponible, "PENDING_SYNC")
        assertFalse(venta().copy(id = 0, localId = "abc", error = "Stock cambió").boletaDisponible, "NEEDS_REVIEW / error")
        assertFalse(venta().copy(estado = "ANULADA").boletaDisponible)
    }

    @Test fun kpisDelDiaSoloCuentanVentasAceptadasYEfectivo() {
        val ahora = pe.edu.upeu.ecolactmobile.core.util.nowIso()
        val ventas = listOf(
            venta().copy(id = 1, fecha = ahora, cantidad = 12.0, importe_total = 54.0),
            venta().copy(id = 2, fecha = ahora, proveedor_id = 9, forma_pago = "DESCUENTO_PLANILLA", cantidad = 2.0, importe_total = 8.0),
            venta().copy(id = 0, localId = "pendiente", fecha = ahora, importe_total = 99.0),
            venta().copy(id = 3, fecha = "2020-01-01T10:00:00Z", importe_total = 500.0),
        )
        val r = resumenDelDia(ventas)
        assertEquals(2, r.ventas)
        assertEquals(54.0, r.cobrado)
        assertEquals(14.0, r.unidades)
    }

    @Test fun productoYDocumentoSePresentanSinInventarDatos() {
        assertEquals("Queso Paria", producto.nombreBase)
        assertNull(producto.presentacion)
        val conPresentacion = producto.copy(nombre = "Queso Paria · 1 kg")
        assertEquals("Queso Paria", conPresentacion.nombreBase)
        assertEquals("1 kg", conPresentacion.presentacion)
        assertEquals("DNI 41234567", ClienteVenta(1, "Juan Torres", "41234567").documentoVisible)
        assertEquals("RUC 20601234567", ClienteVenta(2, "Distribuidora ABC SAC", "20601234567").documentoVisible)
        assertEquals("Sin documento", ClienteVenta(3, "Sin doc").documentoVisible)
        assertEquals("12", formatCantidad(12.0))
        assertEquals("2.50", formatCantidad(2.5))
    }
}
