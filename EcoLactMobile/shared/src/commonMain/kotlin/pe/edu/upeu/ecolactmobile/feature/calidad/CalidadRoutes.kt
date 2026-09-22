package pe.edu.upeu.ecolactmobile.feature.calidad

import pe.edu.upeu.ecolactmobile.core.ui.navigation.Route

sealed class CalidadRoute : Route {
    data object Inicio : CalidadRoute()
    data object Pendientes : CalidadRoute()
    data object Muestreo : CalidadRoute()
    data class Analizar(val entregaId: Long) : CalidadRoute()
    data object Historial : CalidadRoute()
    data class HistorialDetalle(val analisisId: Long) : CalidadRoute()
    data object Mas : CalidadRoute()
    data object Proveedores : CalidadRoute()
    data class ProveedorDetalle(val id: Long) : CalidadRoute()
    data object Comunicados : CalidadRoute()
    data class ComunicadoDetalle(val id: Long) : CalidadRoute()
    data object NuevoComunicado : CalidadRoute()
    data object Sincronizacion : CalidadRoute()
    data object Perfil : CalidadRoute()
    data object Notificaciones : CalidadRoute()
}
