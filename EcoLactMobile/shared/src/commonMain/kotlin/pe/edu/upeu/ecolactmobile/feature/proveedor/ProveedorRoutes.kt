package pe.edu.upeu.ecolactmobile.feature.proveedor

import pe.edu.upeu.ecolactmobile.core.ui.navigation.Route

sealed class ProveedorRoute : Route {
    data object Inicio : ProveedorRoute()
    data object Entregas : ProveedorRoute()
    data class EntregaDetalle(val id: Long) : ProveedorRoute()
    data object Pagos : ProveedorRoute()
    data class LiquidacionDetalle(val id: Long) : ProveedorRoute()
    data object Mas : ProveedorRoute()
    data object MiCalidad : ProveedorRoute()
    data class AnalisisDetalle(val id: Long) : ProveedorRoute()
    data object MisCompras : ProveedorRoute()
    data class CompraDetalle(val id: Long) : ProveedorRoute()
    data object MiQr : ProveedorRoute()
    data object Comunicados : ProveedorRoute()
    data class ComunicadoDetalle(val id: Long) : ProveedorRoute()
    data object Perfil : ProveedorRoute()
    data object Notificaciones : ProveedorRoute()
}
