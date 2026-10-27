package pe.edu.upeu.ecolactmobile.feature.acopiador

import pe.edu.upeu.ecolactmobile.core.ui.navigation.Route

/**
 * Pantallas del acopiador. Tres en la barra inferior (Inicio, Entregas, Proveedores); las demás
 * secciones se abren desde el menú lateral (también con ☰) y los detalles muestran flecha atrás.
 *
 * Registrar entrega es UN solo flujo (QR o DNI → litros → guardar), también desde una parada
 * de recorrido ([parada]) o desde el detalle de un proveedor ([proveedorId], ya identificado).
 */
sealed class AcopiadorRoute : Route {
    data object Inicio : AcopiadorRoute()
    data object Entregas : AcopiadorRoute()
    data object Proveedores : AcopiadorRoute()

    data class Registrar(val proveedorId: Long? = null, val parada: ParadaRef? = null) : AcopiadorRoute()
    data class EntregaDetalle(val clave: String) : AcopiadorRoute()
    data class ProveedorDetalle(val id: Long) : AcopiadorRoute()
    data class ProveedorHistorial(val id: Long) : AcopiadorRoute()

    data object Recorrido : AcopiadorRoute()
    data class RecorridoDetalle(val id: Long) : AcopiadorRoute()
    data object Seguimiento : AcopiadorRoute()
    data object Comunicados : AcopiadorRoute()
    data class ComunicadoDetalle(val id: Long) : AcopiadorRoute()
    data object Notificaciones : AcopiadorRoute()
    data object Perfil : AcopiadorRoute()
    /** Mi QR: el administrador lo escanea para registrar la asistencia (el acopiador no marca nada). */
    data object MiQr : AcopiadorRoute()

    /** Secciones de nivel superior: muestran ☰ en vez de flecha atrás. */
    val esPrincipal: Boolean
        get() = this in setOf(Inicio, Entregas, Proveedores, Recorrido, Seguimiento, Comunicados, Notificaciones, Perfil, MiQr)

    val conBarraInferior: Boolean
        get() = this == Inicio || this == Entregas || this == Proveedores
}
