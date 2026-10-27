package pe.edu.upeu.ecolactmobile.feature.planta

import pe.edu.upeu.ecolactmobile.core.ui.navigation.Route

/**
 * Pantallas del encargado de planta. Tres principales en la barra inferior (Inicio, Producción,
 * Inventario); el resto se abre desde el menú lateral o desde un detalle y muestra flecha atrás.
 * Recetas y Productos son de solo lectura: no hay rutas para editarlos ni para fijar precios.
 */
sealed class PlantaRoute : Route {
    data object Inicio : PlantaRoute()
    data object Produccion : PlantaRoute()

    /** [tab]: 0 = Insumos, 1 = Productos (el menú lateral abre cada uno por separado). */
    data class Inventario(val tab: Int = 0) : PlantaRoute()

    data object NuevaProduccion : PlantaRoute()
    data class LoteDetalle(val id: Long) : PlantaRoute()
    data class InsumoDetalle(val id: Long) : PlantaRoute()
    data class ProductoDetalle(val id: Long) : PlantaRoute()
    data object Recetas : PlantaRoute()
    data class RecetaDetalle(val productoId: Long) : PlantaRoute()

    data object Compras : PlantaRoute()

    /** Cámara a pantalla completa: "Tomar foto de la boleta". */
    data object CamaraBoleta : PlantaRoute()

    /** Revisión de una compra antes de confirmar: datos detectados por OCR o registro [manual]. */
    data class RevisarCompra(val borradorId: String, val manual: Boolean = false) : PlantaRoute()

    data object MiQr : PlantaRoute()
    data object Notificaciones : PlantaRoute()
    data object Perfil : PlantaRoute()

    /** Las tres pantallas de la barra inferior: muestran ☰ en vez de flecha atrás. */
    val esPrincipal: Boolean
        get() = this == Inicio || this == Produccion || this is Inventario
}
