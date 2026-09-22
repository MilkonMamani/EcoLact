package pe.edu.upeu.ecolactmobile.feature.planta

import kotlinx.serialization.json.double
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import kotlinx.serialization.json.long
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertTrue

/**
 * Etapa 10: guarda el contrato real confirmado en encargado-planta.md contra el que se corrigió
 * PlantaModels.kt en la Etapa 3 — el bug original (seleccionar entregas / enviar insumos a mano)
 * era justo este tipo de desalineación silenciosa entre el DTO y el backend real.
 */
class PlantaModelsTest {

    @Test
    fun iniciarProduccionRequest_no_envia_codigo_ni_entregas() {
        val json = ApiJson.encodeToString(
            IniciarProduccionRequest.serializer(),
            IniciarProduccionRequest(litros_utilizados = 120.5),
        )
        val obj = ApiJson.parseToJsonElement(json).jsonObject

        assertEquals(120.5, obj["litros_utilizados"]!!.jsonPrimitive.double)
        assertFalse(obj.containsKey("codigo"), "codigo lo genera el servidor, no debe enviarse")
        assertFalse(obj.containsKey("entregas_utilizadas"), "el acopio no se separa por entrega")
    }

    @Test
    fun registrarResultadoRequest_no_envia_insumos() {
        val json = ApiJson.encodeToString(
            RegistrarResultadoRequest.serializer(),
            RegistrarResultadoRequest(producto_id = 7, cantidad_producida = 50.0),
        )
        val obj = ApiJson.parseToJsonElement(json).jsonObject

        assertEquals(7L, obj["producto_id"]!!.jsonPrimitive.long)
        assertTrue(obj.containsKey("marcar_finalizado"), "tiene default true, debe seguir presente")
        assertFalse(obj.containsKey("insumos_utilizados"), "el backend los calcula solo desde la receta")
    }

    @Test
    fun lecheDisponibleResponse_decodifica_el_campo_real_del_backend() {
        val decoded = ApiJson.decodeFromString(LecheDisponibleResponse.serializer(), """{"litros_disponibles": 340.0}""")
        assertEquals(340.0, decoded.litros_disponibles)
    }

    @Test
    fun loteDetalleResponse_no_espera_insumos_activos_ni_entregas_utilizadas() {
        // Shape real de Api\Planta\ProduccionController::show() (Etapa 1/3): { lote: {...}, productos_activos: [...] }
        val respuesta = """
            {
              "lote": {
                "id": 1, "codigo": "LOT-00001", "estado": "EN_PROCESO",
                "litros_leche_utilizados": 100.0
              },
              "productos_activos": [{"id": 5, "nombre": "Yogurt"}]
            }
        """.trimIndent()
        val decoded = ApiJson.decodeFromString(LoteDetalleResponse.serializer(), respuesta)
        assertEquals("LOT-00001", decoded.lote.codigo)
        assertEquals(1, decoded.productos_activos.size)
    }
}
