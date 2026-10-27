package pe.edu.upeu.ecolactmobile.feature.planta

import kotlinx.serialization.json.double
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import kotlinx.serialization.json.long
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNull
import kotlin.test.assertTrue

/**
 * Contrato de Api\Planta y reglas que la app aplica sobre datos del servidor: recetas y precios
 * de solo lectura, previsualización offline de requerimientos y compras desde la boleta.
 */
class PlantaModelsTest {

    private val queso = RecetaFila(
        id = 1,
        productoId = 10,
        producto = "Queso Fresco 1 kg",
        productoNombreBase = "Queso Fresco",
        presentacion = "1 kg",
        por_unidad = listOf(
            InsumoPorUnidad(1, "Leche", "L", 0.24, es_leche = true),
            InsumoPorUnidad(2, "Cuajo", "ml", 0.12),
            InsumoPorUnidad(3, "Sal", "g", 3.0),
        ),
    )

    private fun insumos(sal: Double) = listOf(
        InsumoFila(1, "Leche", unidad_base = "L", codigo = "LECHE", stock_actual = 245.0, es_leche = true),
        InsumoFila(2, "Cuajo", unidad_base = "ml", stock_actual = 500.0),
        InsumoFila(3, "Sal", unidad_base = "g", stock_actual = sal),
    )

    @Test
    fun con_stock_suficiente_se_puede_iniciar() {
        // 500 unidades: leche 120 L, cuajo 60 ml, sal 1500 g contra 245 / 500 / 5000.
        val r = requerimientosLocales(queso, 500.0, insumos(sal = 5000.0))

        assertEquals(listOf(120.0, 60.0, 1500.0), r.insumos.map { it.necesario })
        assertTrue(r.insumos.all { it.suficiente })
        assertTrue(r.puede_iniciar)
    }

    @Test
    fun si_falta_sal_se_bloquea_y_se_informa_cuanto_falta() {
        val r = requerimientosLocales(queso, 500.0, insumos(sal = 1000.0))
        val sal = r.insumos.first { it.nombre == "Sal" }

        assertFalse(sal.suficiente)
        assertEquals(1500.0, sal.necesario)
        assertEquals(1000.0, sal.disponible)
        assertEquals(500.0, sal.faltante)
        assertFalse(r.puede_iniciar)
    }

    @Test
    fun produccion_request_envia_producto_y_cantidad_sin_insumos() {
        val json = ApiJson.encodeToString(ProduccionRequest.serializer(), ProduccionRequest(listOf(LineaProduccion(10, 500.0))))
        val obj = ApiJson.parseToJsonElement(json).jsonObject
        val linea = obj["productos"]!!.jsonArray.first().jsonObject

        assertEquals(10L, linea["producto_id"]!!.jsonPrimitive.long)
        assertEquals(500.0, linea["cantidad"]!!.jsonPrimitive.double)
        assertFalse(obj.containsKey("insumos"), "los insumos los calcula el servidor con la receta activa")
    }

    @Test
    fun finalizar_envia_lo_obtenido_por_id_de_detalle() {
        val json = ApiJson.encodeToString(FinalizarProduccionRequest.serializer(), FinalizarProduccionRequest(mapOf("7" to 480.0)))
        val obtenido = ApiJson.parseToJsonElement(json).jsonObject["obtenido"]!!.jsonObject

        assertEquals(480.0, obtenido["7"]!!.jsonPrimitive.double)
        assertEquals(480.0, stockNuevo(0.0, 480.0))
    }

    @Test
    fun precios_del_administrador_se_leen_tal_cual() {
        val detalle = ApiJson.decodeFromString(
            ProductoDetalleResponse.serializer(),
            """
            {"id": 10, "nombre": "Queso Fresco 1 kg", "cantidad_actual": 24,
             "precios": {"publico": {"precio": 5.0}, "mayorista": {"precio": 4.5, "cantidad_minima": 12}, "proveedor": {"precio": 4.0}}}
            """.trimIndent(),
        )

        assertEquals(5.0, detalle.precios.publico?.precio)
        assertEquals(12.0, detalle.precios.mayorista?.cantidad_minima)
        assertEquals(4.0, detalle.precios.proveedor?.precio)
        assertFalse(detalle.precios.sinConfigurar)
    }

    @Test
    fun producto_sin_precios_queda_sin_configurar() {
        val detalle = ApiJson.decodeFromString(
            ProductoDetalleResponse.serializer(),
            """{"id": 11, "nombre": "Yogur", "cantidad_actual": 0, "precios": {"publico": null, "mayorista": null, "proveedor": null}}""",
        )
        assertTrue(detalle.precios.sinConfigurar)
    }

    @Test
    fun la_boleta_dice_2_sacos_y_la_presentacion_configurada_da_50000_g() {
        val ocr = BoletaOcrResponse(
            success = true,
            lineas = listOf(BoletaLineaOcr(texto = "2 SACOS SAL", cantidad = 2.0, presentacion_detectada = "Saco", insumo_id = 3, presentacion_id = 9)),
            cabecera = BoletaCabecera(total = 200.0),
            imagen_boleta = "compras-insumo/boletas/boleta-abc.jpg",
        )
        val borrador = BorradorCompra.desdeOcr(ocr)
        val linea = borrador.lineas.single()

        assertEquals("saco", linea.unidadOcr)
        assertEquals(2.0, linea.cantidad)
        assertTrue(borrador.listaParaConfirmar)
        assertEquals(50_000.0, redondear4(linea.cantidad!! * 25_000.0))
        assertEquals("50 000 g", formatCantidad(50_000.0, "g"))

        val request = borrador.toRequest(ocr.imagen_boleta)
        assertEquals(200.0, request.total)
        assertEquals(9L, request.lineas.single().presentacion_id)
    }

    @Test
    fun una_linea_sin_presentacion_no_se_puede_confirmar() {
        val borrador = BorradorCompra(lineas = listOf(LineaBorrador(insumoId = 3, cantidad = 2.0)))
        assertFalse(borrador.listaParaConfirmar)
        assertTrue(borrador.toRequest(null).lineas.isEmpty())
    }

    @Test
    fun la_leche_es_del_sistema_y_no_se_compra() {
        val leche = InsumoFila(1, "Leche", codigo = "LECHE", stock_actual = 245.0)
        assertEquals(NivelInsumo.LECHE, nivelInsumo(leche))
        assertEquals("Del sistema (acopio)", nivelInsumo(leche).label)
        assertEquals(NivelInsumo.BAJO, nivelInsumo(InsumoFila(4, "Azúcar", stock_actual = 1000.0, esta_bajo_minimo = true)))
    }

    @Test
    fun formatos_de_cantidad() {
        assertEquals("245 L", formatCantidad(245.0, "L"))
        assertEquals("1.5 kg", formatCantidad(1.5, "kg"))
        assertEquals("+20 000 ml", formatCantidadConSigno(20_000.0, "ml", entrada = true))
        assertEquals("-60 ml", formatCantidadConSigno(60.0, "ml", entrada = false))
        assertEquals("500 unid.", formatUnidades(500.0))
        assertEquals("Cuajo utilizado", etiquetaUtilizado("Cuajo"))
        assertEquals("Leche utilizada", etiquetaUtilizado("Leche"))
        assertEquals("Mililitros (ml)", nombreUnidad("ml"))
        assertNull(parseDecimal("abc"))
        assertEquals(4.5, parseDecimal("4,5"))
    }
}
