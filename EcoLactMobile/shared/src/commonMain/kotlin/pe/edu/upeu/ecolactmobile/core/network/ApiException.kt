package pe.edu.upeu.ecolactmobile.core.network

/**
 * Errores de API tal como los documenta administrador.md sección 1: 422 (validación/negocio,
 * con `message` + `errors`), 403 (rol/policy) y 404. El código que consuma esto decide qué hacer:
 * un 422 en una operación offline debe ir a la cola de "requiere revisión", nunca reintentarse solo.
 */
sealed class ApiException(message: String) : Exception(message) {

    companion object {
        /** 403 del backend (RequiereAsistencia): el trabajador no tiene asistencia válida ese día. */
        const val CODIGO_ASISTENCIA_REQUERIDA = "ASISTENCIA_REQUERIDA"

        /** Mensaje fijo del rechazo por asistencia (en línea), igual para todos los roles. */
        const val MENSAJE_ASISTENCIA_REQUERIDA = "Necesitas registrar tu asistencia antes de realizar esta operación."
    }


    class Validation(
        override val message: String,
        val errors: Map<String, List<String>> = emptyMap(),
    ) : ApiException(message)

    /** [code]: código de negocio del backend, p. ej. [CODIGO_ASISTENCIA_REQUERIDA]. */
    class Forbidden(override val message: String, val code: String? = null) : ApiException(message) {
        val esAsistenciaRequerida: Boolean get() = code == CODIGO_ASISTENCIA_REQUERIDA
    }

    class NotFound(override val message: String) : ApiException(message)

    /** 409: la operación choca con algo que otro usuario ya hizo (ej. muestra ya seleccionada). No se reintenta. */
    class Conflict(override val message: String) : ApiException(message)

    class Unauthorized(override val message: String) : ApiException(message)

    class ServerError(override val message: String, val statusCode: Int) : ApiException(message)

    class Network(override val message: String, override val cause: Throwable? = null) : ApiException(message)

    class Unknown(override val message: String, val statusCode: Int? = null) : ApiException(message)
}
