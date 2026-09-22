package pe.edu.upeu.ecolactmobile.feature.calidad

import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNull

/** Etapa 10: contrato de `POST /calidad/entregas` confirmado en calidad.md:44-50 (backend). */
class CalidadModelsTest {

    @Test
    fun entregaMuestreadaRequest_tiene_las_claves_exactas_del_backend() {
        val json = ApiJson.encodeToString(
            CalidadEntregaMuestreadaRequest.serializer(),
            CalidadEntregaMuestreadaRequest(metodo_identificacion = "DNI", identificador = "12345678", litros = 15.0),
        )
        val obj = ApiJson.parseToJsonElement(json).jsonObject

        assertEquals("DNI", obj["metodo_identificacion"]!!.jsonPrimitive.content)
        assertEquals("12345678", obj["identificador"]!!.jsonPrimitive.content)
        assertEquals(15.0, obj["litros"]!!.jsonPrimitive.content.toDouble())
    }

    @Test
    fun entregaMuestreada_decodifica_acopiador_id_null() {
        // El backend siempre devuelve acopiador_id: null aquí (nunca hubo acopiador involucrado).
        val respuesta = """
            {"id": 42, "proveedor_id": 3, "acopiador_id": null, "zona_id": 1,
             "fecha_hora": "2026-01-01T10:00:00Z", "litros": 15.0,
             "metodo_identificacion": "DNI", "estado": "PENDIENTE_ANALISIS"}
        """.trimIndent()
        val decoded = ApiJson.decodeFromString(CalidadEntregaMuestreada.serializer(), respuesta)

        assertEquals(42L, decoded.id)
        assertNull(decoded.acopiador_id)
        assertEquals("PENDIENTE_ANALISIS", decoded.estado)
    }
}
