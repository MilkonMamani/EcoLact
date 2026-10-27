package pe.edu.upeu.ecolactmobile.feature.ventas

import androidx.compose.runtime.Composable
import androidx.compose.ui.tooling.preview.Preview
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactTheme
import pe.edu.upeu.ecolactmobile.feature.ventas.screens.VentasBoletaScreen
import pe.edu.upeu.ecolactmobile.feature.ventas.screens.VentasHistorialScreen
import pe.edu.upeu.ecolactmobile.feature.ventas.screens.VentasInicioScreen
import pe.edu.upeu.ecolactmobile.feature.ventas.screens.VentasResumenScreen
import pe.edu.upeu.ecolactmobile.feature.trabajador.Autorizacion

@Preview(name = "360dp", widthDp = 360, heightDp = 800)
@Preview(name = "375dp", widthDp = 375, heightDp = 812)
@Preview(name = "390dp", widthDp = 390, heightDp = 844)
@Preview(name = "412dp", widthDp = 412, heightDp = 915)
@Preview(name = "430dp", widthDp = 430, heightDp = 932)
@Preview(name = "480dp", widthDp = 480, heightDp = 960)
@Composable
fun VentasBoletaPreview() {
    EcoLactTheme {
        VentasBoletaScreen(Venta(id = 18, producto_id = 1, producto_nombre = "Queso Paria · 1 kg",
            comprador_id = 2, cliente = "Distribuidora San Miguel SAC", documento = "20601234567",
            cantidad = 5.0, precio_unitario = 4.5, importe_total = 22.5, tipo_cliente = "MAYORISTA",
            forma_pago = "CONTADO", fecha = "2026-10-27T15:35:00Z"))
    }
}

private fun ejemploVenta() = Venta(id = 18, producto_id = 1, producto_nombre = "Queso Paria · 1 kg", comprador_id = 2,
    cliente = "Distribuidora San Miguel SAC", documento = "20601234567", cantidad = 12.0, precio_unitario = 18.0,
    importe_total = 216.0, tipo_cliente = "MAYORISTA", forma_pago = "CONTADO", fecha = "2026-10-27T15:35:00Z")

@Preview(widthDp = 360, heightDp = 800)
@Composable
fun VentasHistorialPreview() {
    EcoLactTheme { VentasHistorialScreen(listOf(ejemploVenta(), ejemploVenta().copy(id = 17, cliente = "Público general"),
        ejemploVenta().copy(id = 0, localId = "abc12345", cliente = "Juan Torres"))) {} }
}

@Preview(widthDp = 360, heightDp = 800)
@Composable
fun VentasInicioPreview() {
    EcoLactTheme { VentasInicioScreen("Lucía Pérez", listOf(ejemploVenta()), Autorizacion.HABILITADA, {}, {}, {}) {} }
}

@Preview(widthDp = 360, heightDp = 800)
@Composable
fun VentasResumenPreview() {
    EcoLactTheme { VentasResumenScreen(ProductoVenta(1, "Queso Paria · 1 kg", 120.0), "PROVEEDOR",
        ClienteVenta(3, "Juan Quispe", "41234567"), CotizacionVenta(4.0, "PROVEEDOR", 8.0), false, false, "2", "CONTADO", {}, {}, { _, _ -> }, {}) }
}
