package pe.edu.upeu.ecolactmobile.core.network

import io.ktor.client.call.body
import io.ktor.client.statement.HttpResponse
import io.ktor.client.statement.bodyAsText
import io.ktor.http.HttpStatusCode
import io.ktor.http.isSuccess
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive

/** Desenvuelve `{ "data": ... }` o lanza [ApiException] con el mensaje/errores que devuelve el backend. */
suspend inline fun <reified T> HttpResponse.toEnvelopeData(): T {
    if (status.isSuccess()) {
        val envelope: ApiEnvelope<T> = body()
        return envelope.data
    }
    throw toApiException()
}

/** Para endpoints que no envuelven en `data` (ej. `PATCH .../leida` que solo devuelve `{message}`). */
suspend fun HttpResponse.requireSuccess(): HttpResponse {
    if (!status.isSuccess()) throw toApiException()
    return this
}

suspend fun HttpResponse.toApiException(): ApiException {
    val bodyText = runCatching { bodyAsText() }.getOrDefault("")
    val (message, errors) = parseErrorBody(bodyText)
    val code = parseCode(bodyText)
    return when (status) {
        HttpStatusCode.Unauthorized -> ApiException.Unauthorized(message ?: "No autorizado")
        HttpStatusCode.Forbidden -> if (code == ApiException.CODIGO_ASISTENCIA_REQUERIDA) {
            ApiException.Forbidden(ApiException.MENSAJE_ASISTENCIA_REQUERIDA, code)
        } else {
            ApiException.Forbidden(message ?: "Esta acción no está permitida para su rol", code)
        }
        HttpStatusCode.NotFound -> ApiException.NotFound(message ?: "No encontrado")
        HttpStatusCode.Conflict -> ApiException.Conflict(message ?: "La operación entra en conflicto con otra ya registrada")
        HttpStatusCode.UnprocessableEntity -> ApiException.Validation(message ?: "Error de validación", errors)
        else -> if (status.value >= 500) {
            ApiException.ServerError(message ?: "Error del servidor", status.value)
        } else {
            ApiException.Unknown(message ?: "Error inesperado (${status.value})", status.value)
        }
    }
}

private fun parseCode(bodyText: String): String? =
    runCatching { ApiJson.parseToJsonElement(bodyText).jsonObject["code"]?.jsonPrimitive?.contentOrNull }.getOrNull()

private fun parseErrorBody(bodyText: String): Pair<String?, Map<String, List<String>>> {
    if (bodyText.isBlank()) return null to emptyMap()
    return try {
        val json = ApiJson.parseToJsonElement(bodyText).jsonObject
        val message = json["message"]?.jsonPrimitive?.contentOrNull
        val errors = json["errors"]?.jsonObject?.mapValues { (_, value) ->
            value.jsonArray.map { it.jsonPrimitive.content }
        } ?: emptyMap()
        message to errors
    } catch (e: Exception) {
        null to emptyMap()
    }
}
