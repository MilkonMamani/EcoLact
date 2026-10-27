package pe.edu.upeu.ecolactmobile.feature.calidad

import pe.edu.upeu.ecolactmobile.core.ui.navigation.Route

/**
 * Pantallas del analista. Tres principales en la barra inferior (Inicio, Muestreo, Historial);
 * el resto se abre desde el menú lateral o desde un detalle y muestra flecha atrás.
 *
 * Flujo principal: Muestreo → muestra seleccionada → Muestras pendientes → MuestraDetalle →
 * Camara (o ingreso manual) → Revision → confirmar.
 */
sealed class CalidadRoute : Route {
    data object Inicio : CalidadRoute()
    data object Muestreo : CalidadRoute()
    data object Historial : CalidadRoute()

    data object Pendientes : CalidadRoute()
    data class MuestraDetalle(val entregaId: Long) : CalidadRoute()
    data class Camara(val entregaId: Long) : CalidadRoute()
    data class Revision(val entregaId: Long) : CalidadRoute()
    data class HistorialDetalle(val analisisId: Long) : CalidadRoute()

    /** Flujo secundario: inspección sorpresa en campo (QR/DNI) antes del acopio normal. */
    data object MuestreoDirecto : CalidadRoute()

    data object Proveedores : CalidadRoute()
    data class ProveedorDetalle(val id: Long) : CalidadRoute()
    data object Comunicados : CalidadRoute()
    data class ComunicadoDetalle(val id: Long) : CalidadRoute()
    data object NuevoComunicado : CalidadRoute()
    data object Notificaciones : CalidadRoute()
    /** Mi QR: el administrador lo escanea para registrar la asistencia (el analista no marca nada). */
    data object MiQr : CalidadRoute()
    data object Perfil : CalidadRoute()

    /** Las tres pantallas de la barra inferior: muestran ☰ en vez de flecha atrás. */
    val esPrincipal: Boolean
        get() = this == Inicio || this == Muestreo || this == Historial
}
