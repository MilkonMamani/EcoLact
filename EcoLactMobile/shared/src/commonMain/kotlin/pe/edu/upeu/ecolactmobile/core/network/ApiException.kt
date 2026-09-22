package pe.edu.upeu.ecolactmobile.core.network

/**
 * Errores de API tal como los documenta administrador.md sección 1: 422 (validación/negocio,
 * con `message` + `errors`), 403 (rol/policy) y 404. El código que consuma esto decide qué hacer:
 * un 422 en una operación offline debe ir a la cola de "requiere revisión", nunca reintentarse solo.
 */
sealed class ApiException(message: String) : Exception(message) {

    class Validation(
        override val message: String,
        val errors: Map<String, List<String>> = emptyMap(),
    ) : ApiException(message)

    class Forbidden(override val message: String) : ApiException(message)

    class NotFound(override val message: String) : ApiException(message)

    class Unauthorized(override val message: String) : ApiException(message)

    class ServerError(override val message: String, val statusCode: Int) : ApiException(message)

    class Network(override val message: String, override val cause: Throwable? = null) : ApiException(message)

    class Unknown(override val message: String, val statusCode: Int? = null) : ApiException(message)
}
