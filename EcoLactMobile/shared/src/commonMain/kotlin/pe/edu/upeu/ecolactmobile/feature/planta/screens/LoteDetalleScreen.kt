package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.MensajeError
import pe.edu.upeu.ecolactmobile.feature.planta.FinalizacionPendiente
import pe.edu.upeu.ecolactmobile.feature.planta.LoteDetalle
import pe.edu.upeu.ecolactmobile.feature.planta.OperacionResult
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.components.AvisoAtencion
import pe.edu.upeu.ecolactmobile.feature.planta.components.CampoPlanta
import pe.edu.upeu.ecolactmobile.feature.planta.components.TituloBloque
import pe.edu.upeu.ecolactmobile.feature.planta.estadoLote
import pe.edu.upeu.ecolactmobile.feature.planta.etiquetaUtilizado
import pe.edu.upeu.ecolactmobile.feature.planta.formatCantidad
import pe.edu.upeu.ecolactmobile.feature.planta.formatFechaHoraLarga
import pe.edu.upeu.ecolactmobile.feature.planta.formatUnidadesLargo
import pe.edu.upeu.ecolactmobile.feature.planta.parseDecimal
import pe.edu.upeu.ecolactmobile.feature.planta.stockNuevo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco
import pe.edu.upeu.ecolactmobile.feature.trabajador.NotaRequiereAsistencia

data class FinalizarState(
    /** Cantidad realmente obtenida escrita por la persona, por id de detalle de producción. */
    val obtenido: Map<Long, String> = emptyMap(),
    val enviando: Boolean = false,
    val error: String? = null,
)

class LoteDetalleViewModel(private val repository: PlantaRepository, isOnline: StateFlow<Boolean>, private val loteId: Long) :
    LocalFirstViewModel<LoteDetalle>(repository.observeLote(loteId), isOnline, { repository.refreshLote(loteId) }, refreshOnStart = true) {

    private val _form = MutableStateFlow(FinalizarState())
    val form: StateFlow<FinalizarState> = _form.asStateFlow()

    val pendiente: StateFlow<FinalizacionPendiente?> =
        repository.observeFinalizacionesPendientes().map { it[loteId] }.stateIn(scope, SharingStarted.Eagerly, null)

    /** Stock actual de cada producto (lo guardado en el teléfono), para "Stock anterior". */
    fun stockActual(productoId: Long): Double = repository.productosGuardados().firstOrNull { it.id == productoId }?.cantidad_actual ?: 0.0

    fun onObtenido(detalleId: Long, texto: String) =
        _form.update { it.copy(obtenido = it.obtenido + (detalleId to texto.filter { c -> c.isDigit() || c == '.' || c == ',' }), error = null) }

    fun finalizar(lote: LoteDetalle) {
        val valores = lote.productos.associate { it.detalle_id to parseDecimal(_form.value.obtenido[it.detalle_id].orEmpty()) }
        if (valores.values.any { it == null || it < 0.0 }) {
            _form.update { it.copy(error = "Indica la cantidad realmente obtenida de cada producto (puede ser 0).") }
            return
        }
        scope.launch {
            _form.update { it.copy(enviando = true, error = null) }
            val r = repository.finalizarProduccion(lote.id, valores.mapValues { it.value!! })
            _form.update { it.copy(enviando = false, error = (r as? OperacionResult.Rechazado)?.mensaje) }
        }
    }
}

@Composable
fun LoteDetalleScreen(viewModel: LoteDetalleViewModel) {
    val state by viewModel.state.collectAsState()
    val form by viewModel.form.collectAsState()
    val pendiente by viewModel.pendiente.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val lote = s.data
        val nombre = lote.productos.mapNotNull { it.producto_nombre }.joinToString(", ").ifBlank { "Lote ${lote.codigo}" }
        val planificado = lote.productos.sumOf { it.cantidad_planificada }
        val unidad = lote.productos.firstOrNull()?.unidad_medida

        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(14.dp)) {
            item {
                Row(Modifier.anchoContenido(), verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text(nombre, style = MaterialTheme.typography.titleLarge, color = EcoLactColors.TextoPrincipal)
                        Text("Lote ${lote.codigo}", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                    }
                    val (texto, tono) = estadoLote(lote.estado)
                    StatusChip(texto, tono)
                }
            }
            item {
                SummaryCard {
                    SummaryRow("Cantidad planificada", formatUnidadesLargo(planificado, unidad), primera = true)
                    lote.insumos_utilizados.forEach { insumo ->
                        SummaryRow(etiquetaUtilizado(insumo.insumo_nombre ?: "Insumo"), formatCantidad(insumo.cantidad_usada, insumo.unidad_base))
                    }
                    lote.fecha_hora_inicio?.let { SummaryRow("Fecha de inicio", formatFechaHoraLarga(it)) }
                    if (!lote.enProceso) {
                        SummaryRow("Cantidad obtenida", formatUnidadesLargo(lote.productos.sumOf { it.cantidad_obtenida ?: 0.0 }, unidad), destacado = true)
                        lote.fecha_hora_fin?.let { SummaryRow("Fecha de fin", formatFechaHoraLarga(it)) }
                    }
                }
            }

            val pend = pendiente
            when {
                !lote.enProceso -> Unit
                pend != null && pend.conflicto != null -> item { MensajeError("No se pudo finalizar: ${pend.conflicto}") }
                pend != null -> item {
                    AvisoAtencion("Finalización guardada en el teléfono", "Se enviará automáticamente cuando vuelva la conexión.")
                }
                else -> {
                    item { TituloBloque("Finalizar producción") }
                    lote.productos.forEach { producto ->
                        item(key = "o-${producto.detalle_id}") {
                            CampoPlanta(
                                valor = form.obtenido[producto.detalle_id].orEmpty(),
                                onCambio = { viewModel.onObtenido(producto.detalle_id, it) },
                                label = if (lote.productos.size > 1) "${producto.producto_nombre}: cantidad obtenida" else "Cantidad obtenida realmente",
                                numerico = true,
                                sufijo = "unidades",
                            )
                        }
                    }
                    item { TituloBloque("Actualización de stock") }
                    lote.productos.forEach { producto ->
                        item(key = "s-${producto.detalle_id}") {
                            val anterior = viewModel.stockActual(producto.producto_id)
                            val obtenido = parseDecimal(form.obtenido[producto.detalle_id].orEmpty()) ?: 0.0
                            ActualizacionStock(
                                titulo = producto.producto_nombre.takeIf { lote.productos.size > 1 },
                                anterior = anterior,
                                obtenido = obtenido,
                            )
                        }
                    }
                    form.error?.let { item { MensajeError(it) } }
                    item { BotonPrincipal("Finalizar producción", { viewModel.finalizar(lote) }, cargando = form.enviando, operacion = true) }
                    item { NotaRequiereAsistencia() }
                }
            }
        }
    }
}

/** Stock anterior + Cantidad obtenida = Stock nuevo. */
@Composable
private fun ActualizacionStock(titulo: String?, anterior: Double, obtenido: Double) {
    Column(Modifier.anchoContenido().contenedorBlanco().padding(horizontal = 16.dp, vertical = 8.dp)) {
        titulo?.let { Text(it, style = MaterialTheme.typography.labelLarge, color = EcoLactColors.TextoSecundario, modifier = Modifier.padding(top = 4.dp)) }
        FilaStock("Stock anterior", formatCantidad(anterior), null)
        FilaStock("Cantidad obtenida", formatCantidad(obtenido), "+")
        HorizontalDivider(color = EcoLactColors.Bordes)
        FilaStock("Stock nuevo", formatCantidad(stockNuevo(anterior, obtenido)), "=", destacado = true)
    }
}

@Composable
private fun FilaStock(label: String, valor: String, operador: String?, destacado: Boolean = false) {
    Row(Modifier.fillMaxWidth().padding(vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
        Text(operador.orEmpty(), style = MaterialTheme.typography.titleMedium, color = EcoLactColors.TextoSecundario, modifier = Modifier.padding(end = 8.dp))
        Text(label, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, modifier = Modifier.weight(1f))
        Text(
            valor,
            style = if (destacado) MaterialTheme.typography.titleMedium else MaterialTheme.typography.bodyLarge,
            fontWeight = FontWeight.SemiBold,
            color = if (destacado) EcoLactColors.VerdeOscuro else EcoLactColors.TextoPrincipal,
        )
    }
}
