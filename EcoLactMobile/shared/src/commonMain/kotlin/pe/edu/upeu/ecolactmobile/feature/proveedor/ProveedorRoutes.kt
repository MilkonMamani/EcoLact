package pe.edu.upeu.ecolactmobile.feature.proveedor

import pe.edu.upeu.ecolactmobile.core.ui.navigation.Route

/**
 * Pantallas del proveedor. Tres principales en la barra inferior (Inicio, Entregas, Pagos); el
 * resto se abre desde el menú lateral o desde un detalle y muestra flecha atrás. El QR vive en
 * Inicio (ya no es pantalla aparte) y "Liquidaciones" se presenta al usuario como "Pagos".
 */
sealed class ProveedorRoute : Route {
    data object Inicio : ProveedorRoute()
    data object Entregas : ProveedorRoute()
    data class EntregaDetalle(val id: Long) : ProveedorRoute()
    data object Pagos : ProveedorRoute()

    /** El backend lo llama liquidación; en la UI es "Detalle de pago". */
    data class LiquidacionDetalle(val id: Long) : ProveedorRoute()
    data object MiCalidad : ProveedorRoute()
    data class AnalisisDetalle(val id: Long) : ProveedorRoute()
    data object MisCompras : ProveedorRoute()
    data class CompraDetalle(val id: Long) : ProveedorRoute()
    data object Comunicados : ProveedorRoute()
    data class ComunicadoDetalle(val id: Long) : ProveedorRoute()
    data object Notificaciones : ProveedorRoute()
    data object Perfil : ProveedorRoute()
    data object Sincronizacion : ProveedorRoute()

    /** Las tres pantallas de la barra inferior: muestran ☰ en vez de flecha atrás. */
    val esPrincipal: Boolean
        get() = this == Inicio || this == Entregas || this == Pagos
}
