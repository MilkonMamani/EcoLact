package pe.edu.upeu.ecolactmobile.feature.planta

import pe.edu.upeu.ecolactmobile.core.ui.navigation.Route

sealed class PlantaRoute : Route {
    data object Inicio : PlantaRoute()
    data object Produccion : PlantaRoute()
    data object IniciarProduccion : PlantaRoute()
    data class LoteDetalle(val id: Long) : PlantaRoute()
    data class LotePendienteDetalle(val localId: String) : PlantaRoute()
    data class RegistrarResultado(val loteId: Long) : PlantaRoute()
    data object Inventario : PlantaRoute()
    data class InsumoDetalle(val id: Long) : PlantaRoute()
    data object NuevoInsumo : PlantaRoute()
    data class ProductoDetalle(val id: Long) : PlantaRoute()
    data object Mas : PlantaRoute()
    data object Sincronizacion : PlantaRoute()
    data object Perfil : PlantaRoute()
    data object Notificaciones : PlantaRoute()
}
