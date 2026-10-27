package pe.edu.upeu.ecolactmobile.feature.ventas

enum class VentasRoute(val titulo: String, val principal: Boolean = false) : pe.edu.upeu.ecolactmobile.core.ui.navigation.Route {
    INICIO("Inicio", true), VENDER("Nueva venta", true), HISTORIAL("Historial", true),
    CLIENTE("Seleccionar cliente"), RESUMEN("Resumen de venta"), CONFIRMACION("Venta"),
    DETALLE("Detalle de venta"), BOLETA("Boleta de venta"),
    COMPRADORES("Compradores"), NUEVO_COMPRADOR("Nuevo comprador"), PRODUCTOS("Productos"),
    COMUNICADOS("Comunicados"), NOTIFICACIONES("Notificaciones"), QR("Mi QR"), PERFIL("Mi perfil"),
}
