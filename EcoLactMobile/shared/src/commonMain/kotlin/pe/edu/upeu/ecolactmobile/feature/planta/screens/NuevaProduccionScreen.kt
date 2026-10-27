package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.MensajeError
import pe.edu.upeu.ecolactmobile.feature.calidad.components.PantallaGuardadoLocal
import pe.edu.upeu.ecolactmobile.feature.planta.InsumoFila
import pe.edu.upeu.ecolactmobile.feature.planta.OperacionResult
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.RecetaFila
import pe.edu.upeu.ecolactmobile.feature.planta.RequerimientoInsumo
import pe.edu.upeu.ecolactmobile.feature.planta.RequerimientosResponse
import pe.edu.upeu.ecolactmobile.feature.planta.components.AvisoAtencion
import pe.edu.upeu.ecolactmobile.feature.planta.components.CampoPlanta
import pe.edu.upeu.ecolactmobile.feature.planta.components.SelectorPlanta
import pe.edu.upeu.ecolactmobile.feature.planta.components.TituloBloque
import pe.edu.upeu.ecolactmobile.feature.planta.formatCantidad
import pe.edu.upeu.ecolactmobile.feature.planta.parseDecimal
import pe.edu.upeu.ecolactmobile.feature.planta.requerimientosLocales
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco
import pe.edu.upeu.ecolactmobile.feature.trabajador.NotaRequiereAsistencia

data class NuevaProduccionState(
    val recetas: List<RecetaFila> = emptyList(),
    val receta: RecetaFila? = null,
    val cantidad: String = "",
    val requerimientos: RequerimientosResponse? = null,
    val calculando: Boolean = false,
    val enviando: Boolean = false,
    val error: String? = null,
    val resultado: OperacionResult? = null,
) {
    val cantidadValor: Double? get() = parseDecimal(cantidad)?.takeIf { it > 0.0 }
    val puedeIniciar: Boolean
        get() = receta != null && cantidadValor != null && requerimientos?.puede_iniciar == true && !calculando && !enviando
}

/**
 * Producto (receta activa del ADMINISTRADOR) + cantidad. Con conexión los requerimientos los
 * calcula el servidor; sin conexión se previsualizan con la receta y el stock guardados, y el
 * servidor los vuelve a validar al sincronizar. Nunca se inicia si falta algún insumo.
 */
class NuevaProduccionViewModel(private val repository: PlantaRepository, private val isOnline: StateFlow<Boolean>) : ScreenModel() {
    private val _state = MutableStateFlow(NuevaProduccionState())
    val state: StateFlow<NuevaProduccionState> = _state.asStateFlow()

    private val insumos = MutableStateFlow<List<InsumoFila>>(emptyList())

    init {
        scope.launch {
            repository.observeRecetas().collect { cached ->
                val activas = cached?.value?.filas.orEmpty().filter { it.activa && it.por_unidad.isNotEmpty() }
                _state.update { s -> s.copy(recetas = activas, receta = s.receta?.let { r -> activas.firstOrNull { it.id == r.id } } ?: s.receta) }
            }
        }
        scope.launch { repository.observeInsumos().collect { insumos.value = it?.value?.filas.orEmpty() } }
        scope.launch {
            // Solo recalcula cuando cambia lo que entra al cálculo (no por sus propios resultados).
            combine(_state, insumos, isOnline) { s, ins, online -> Triple(Pair(s.receta, s.cantidadValor), ins, online) }
                .distinctUntilChanged()
                .collectLatest { (seleccion, ins, online) ->
                    val (receta, cantidad) = seleccion
                    if (receta == null || cantidad == null) {
                        _state.update { it.copy(requerimientos = null, calculando = false) }
                        return@collectLatest
                    }
                    if (!online) {
                        _state.update { it.copy(requerimientos = requerimientosLocales(receta, cantidad, ins), calculando = false) }
                        return@collectLatest
                    }
                    _state.update { it.copy(calculando = true) }
                    delay(400)
                    val remoto = runCatching { repository.requerimientos(receta.productoId, cantidad) }.getOrNull()
                    _state.update { it.copy(requerimientos = remoto ?: requerimientosLocales(receta, cantidad, ins), calculando = false) }
                }
        }
    }

    fun onReceta(receta: RecetaFila) = _state.update { it.copy(receta = receta, error = null) }
    fun onCantidad(texto: String) = _state.update { it.copy(cantidad = texto.filter { c -> c.isDigit() || c == '.' || c == ',' }, error = null) }

    fun iniciar() {
        val s = _state.value
        val receta = s.receta ?: return
        val cantidad = s.cantidadValor ?: return
        if (!s.puedeIniciar) return
        scope.launch {
            _state.update { it.copy(enviando = true, error = null) }
            when (val r = repository.iniciarProduccion(receta, cantidad)) {
                is OperacionResult.Rechazado -> _state.update { it.copy(enviando = false, error = r.mensaje) }
                else -> _state.update { it.copy(enviando = false, resultado = r) }
            }
        }
    }
}

@Composable
fun NuevaProduccionScreen(
    viewModel: NuevaProduccionViewModel,
    isOnline: Boolean,
    onIniciada: (Long) -> Unit,
    onGuardadaOffline: () -> Unit,
) {
    val state by viewModel.state.collectAsState()
    val resultado = state.resultado

    LaunchedEffect(resultado) { if (resultado is OperacionResult.Confirmado) onIniciada(resultado.serverId) }

    if (resultado is OperacionResult.GuardadoOffline) {
        PantallaGuardadoLocal(
            titulo = "Producción guardada en el teléfono",
            descripcion = "Se enviará automáticamente cuando vuelva la conexión.",
            hechos = listOfNotNull(state.receta?.let { "${it.nombreProducto} · ${formatCantidad(state.cantidadValor ?: 0.0)} unidades" }, "Insumos verificados con el último stock guardado"),
            nota = "Al sincronizar, el sistema vuelve a revisar el inventario. Si cambió, te avisará y no se creará la producción.",
            onContinuar = onGuardadaOffline,
        )
        return
    }

    ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(14.dp)) {
        if (state.recetas.isEmpty()) {
            item { EstadoVacio("No hay recetas activas para producir. Las configura el administrador.") }
            return@ProveedorPantalla
        }
        item {
            SelectorPlanta(
                label = "Producto (receta)",
                valor = state.receta?.let { etiquetaReceta(it) },
                opciones = state.recetas,
                texto = ::etiquetaReceta,
                onSeleccionar = viewModel::onReceta,
            )
        }
        item {
            CampoPlanta(state.cantidad, viewModel::onCantidad, "Cantidad a producir", numerico = true, sufijo = "unidades")
        }
        if (!isOnline) {
            item {
                AvisoAtencion(
                    "Se guardará localmente.",
                    "Esta producción se registrará en el dispositivo y se sincronizará cuando vuelva la conexión.",
                )
            }
        }
        val requerimientos = state.requerimientos
        if (state.receta != null && state.cantidadValor != null) {
            item { TituloBloque("Requerimientos de insumos") }
            item {
                if (requerimientos == null) {
                    Row(Modifier.anchoContenido().padding(16.dp), horizontalArrangement = Arrangement.Center) {
                        CircularProgressIndicator(Modifier.size(24.dp), color = EcoLactColors.VerdePrincipal)
                    }
                } else {
                    TablaRequerimientos(requerimientos.insumos)
                }
            }
            requerimientos?.insumos?.filterNot { it.suficiente }?.forEach { falta ->
                item { AlertaFaltante(falta) }
            }
        }
        state.error?.let { item { MensajeError(it) } }
        item {
            BotonPrincipal(
                texto = "Iniciar producción",
                onClick = viewModel::iniciar,
                enabled = state.puedeIniciar,
                cargando = state.enviando,
                operacion = true,
            )
        }
        item { NotaRequiereAsistencia() }
    }
}

fun etiquetaReceta(receta: RecetaFila): String = listOfNotNull(receta.nombreProducto, receta.presentacion).joinToString(" · ")

/** Insumo | Necesario | Disponible, con ✓ o advertencia por fila. */
@Composable
private fun TablaRequerimientos(filas: List<RequerimientoInsumo>) {
    Column(Modifier.anchoContenido().contenedorBlanco().padding(horizontal = 12.dp, vertical = 4.dp)) {
        Row(Modifier.fillMaxWidth().padding(vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
            Text("Insumo", style = MaterialTheme.typography.labelMedium, color = EcoLactColors.TextoSecundario, modifier = Modifier.weight(1.1f))
            Text("Necesario", style = MaterialTheme.typography.labelMedium, color = EcoLactColors.TextoSecundario, modifier = Modifier.weight(1f), textAlign = TextAlign.End)
            Text("Disponible", style = MaterialTheme.typography.labelMedium, color = EcoLactColors.TextoSecundario, modifier = Modifier.weight(1f), textAlign = TextAlign.End)
            Row(Modifier.width(28.dp)) {}
        }
        filas.forEach { fila ->
            HorizontalDivider(color = EcoLactColors.Bordes)
            Row(Modifier.fillMaxWidth().padding(vertical = 12.dp), verticalAlignment = Alignment.CenterVertically) {
                Text(fila.nombre, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1.1f), maxLines = 1)
                Text(formatCantidad(fila.necesario, fila.unidad), style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f), textAlign = TextAlign.End)
                Text(
                    formatCantidad(fila.disponible, fila.unidad),
                    style = MaterialTheme.typography.bodyMedium,
                    color = if (fila.suficiente) EcoLactColors.TextoPrincipal else EcoLactColors.Rojo,
                    modifier = Modifier.weight(1f),
                    textAlign = TextAlign.End,
                )
                Row(Modifier.width(28.dp), horizontalArrangement = Arrangement.End) {
                    Icon(
                        if (fila.suficiente) EcoLactIcons.Check else EcoLactIcons.Warning,
                        contentDescription = if (fila.suficiente) "Suficiente" else "Insuficiente",
                        tint = if (fila.suficiente) EcoLactColors.VerdePrincipal else EcoLactColors.Rojo,
                        modifier = Modifier.size(18.dp),
                    )
                }
            }
        }
    }
}

/** Stock insuficiente: Necesario / Disponible / Faltan. Bloquea "Iniciar producción". */
@Composable
private fun AlertaFaltante(fila: RequerimientoInsumo) {
    AvisoAtencion(
        titulo = "${fila.nombre}: stock insuficiente",
        texto = "Necesario: ${formatCantidad(fila.necesario, fila.unidad)}\nDisponible: ${formatCantidad(fila.disponible, fila.unidad)}\nFaltan: ${formatCantidad(fila.faltante, fila.unidad)}",
        icon = EcoLactIcons.Warning,
    )
}
