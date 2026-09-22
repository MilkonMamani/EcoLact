package pe.edu.upeu.ecolactmobile.core.network

import kotlinx.serialization.KSerializer
import kotlinx.serialization.builtins.MapSerializer
import kotlinx.serialization.builtins.nullable
import kotlinx.serialization.builtins.serializer
import kotlinx.serialization.descriptors.PrimitiveKind
import kotlinx.serialization.descriptors.PrimitiveSerialDescriptor
import kotlinx.serialization.descriptors.SerialDescriptor
import kotlinx.serialization.encoding.Decoder
import kotlinx.serialization.encoding.Encoder
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonDecoder
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.JsonTransformingSerializer
import kotlinx.serialization.json.double

/**
 * Laravel (PHP) serializa un array asociativo vacío como `[]`, no `{}` — PHP no distingue un
 * array vacío de un mapa vacío. Cualquier `Map<String, T>` que el backend pueda devolver vacío
 * necesita este tratamiento o kotlinx.serialization falla con "Expected start of the object '{',
 * but had '[' instead". Verificado en vivo contra `GET /admin/dashboard` (`kpis.produccionPorEstado`).
 */
object IntMapOrEmptyArraySerializer : JsonTransformingSerializer<Map<String, Int>>(
    MapSerializer(String.serializer(), Int.serializer()),
) {
    override fun transformDeserialize(element: JsonElement): JsonElement =
        if (element is JsonArray) JsonObject(emptyMap()) else element
}

object NullableStringMapOrEmptyArraySerializer : JsonTransformingSerializer<Map<String, String?>>(
    MapSerializer(String.serializer(), String.serializer().nullable),
) {
    override fun transformDeserialize(element: JsonElement): JsonElement =
        if (element is JsonArray) JsonObject(emptyMap()) else element
}

/**
 * Laravel devuelve `null` (en vez de `0`) en promedios/tasas/porcentajes calculados cuando no hay
 * datos para dividir (ej. `ticketPromedio` sin ventas, `tasaLectura` sin destinatarios —
 * verificado en vivo). Trata `null` como `0.0` en vez de fallar la deserialización completa.
 */
object NullAsZeroDoubleSerializer : KSerializer<Double> {
    override val descriptor: SerialDescriptor = PrimitiveSerialDescriptor("NullAsZeroDouble", PrimitiveKind.DOUBLE)

    override fun deserialize(decoder: Decoder): Double {
        val jsonDecoder = decoder as? JsonDecoder ?: return decoder.decodeDouble()
        val element = jsonDecoder.decodeJsonElement()
        return if (element is JsonNull) 0.0 else (element as JsonPrimitive).double
    }

    override fun serialize(encoder: Encoder, value: Double) = encoder.encodeDouble(value)
}
