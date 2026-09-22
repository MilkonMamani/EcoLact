package pe.edu.upeu.ecolactmobile.feature.acopiador

import pe.edu.upeu.ecolactmobile.core.ui.navigation.Route

sealed class AcopiadorRoute : Route {
    data object Inicio : AcopiadorRoute()
    data object Registrar : AcopiadorRoute()
    data object Entregas : AcopiadorRoute()
    data class EntregaDetalle(val id: Long) : AcopiadorRoute()
    data class EntregaPendienteDetalle(val localId: String) : AcopiadorRoute()
    data class SeguimientoDetalle(val entregaId: Long) : AcopiadorRoute()
    data object Mas : AcopiadorRoute()
    data object Proveedores : AcopiadorRoute()
    data class ProveedorDetalle(val id: Long) : AcopiadorRoute()
    data object Comunicados : AcopiadorRoute()
    data class ComunicadoDetalle(val id: Long) : AcopiadorRoute()
    data object Sincronizacion : AcopiadorRoute()
    data object Perfil : AcopiadorRoute()
    data object Notificaciones : AcopiadorRoute()
}
