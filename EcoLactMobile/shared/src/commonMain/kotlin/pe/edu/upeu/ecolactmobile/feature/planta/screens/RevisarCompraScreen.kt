package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.MensajeError
import pe.edu.upeu.ecolactmobile.feature.calidad.components.PantallaGuardadoLocal
import pe.edu.upeu.ecolactmobile.feature.planta.BorradorCompra
import pe.edu.upeu.ecolactmobile.feature.planta.CatalogoInsumo
import pe.edu.upeu.ecolactmobile.feature.planta.EstadoBorrador
import pe.edu.upeu.ecolactmobile.feature.planta.LineaBorrador
import pe.edu.upeu.ecolactmobile.feature.planta.OperacionResult
import pe.edu.upeu.ecolactmobile.feature.planta.OrigenCompra
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.components.AvisoAtencion
import pe.edu.upeu.ecolactmobile.feature.planta.components.AvisoInfo
import pe.edu.upeu.ecolactmobile.feature.planta.components.CampoPlanta
import pe.edu.upeu.ecolactmobile.feature.planta.components.SelectorPlanta
import pe.edu.upeu.ecolactmobile.feature.planta.components.TituloBloque
import pe.edu.upeu.ecolactmobile.feature.planta.formatCantidad
import pe.edu.upeu.ecolactmobile.feature.planta.parseDecimal
import pe.edu.upeu.ecolactmobile.feature.planta.redondear4
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco
import pe.edu.upeu.ecolactmobile.feature.trabajador.NotaRequiereAsistencia

/** Una línea en edición: el número se edita como texto para no perder lo que la persona escribe. */
data class LineaEdicion(val linea: LineaBorrador, val cantidad: String)

data class RevisarCompraState(
    val cargado: Boolean = false,
    val existe: Boolean = true,
    val origen: OrigenCompra = OrigenCompra.MANUAL,
    val estado: EstadoBorrador = EstadoBorrador.REQUIERE_REVISION,
    val mensaje: String? = null,
    val catalogo: List<CatalogoInsumo> = emptyList(),
    val proveedor: String = "",
    val comprobante: String = "",
    val fecha: String = "",
    val total: String = "",
    val lineas: List<LineaEdicion> = emptyList(),
    val enviando: Boolean = false,
    val error: String? = null,
    val resultado: OperacionResult? = null,
) {
    fun aBorrador(): BorradorCompra = BorradorCompra(
        proveedor = proveedor.trim().ifBlank { null },
        numeroComprobante = comprobante.trim().ifBlank { null },
        fecha = fecha.trim().ifBlank { null },
        total = parseDecimal(total),
        lineas = lineas.map { it.linea.copy(cantidad = parseDecimal(it.cantidad)) },
    )

    val editable: Boolean get() = estado != EstadoBorrador.PENDIENTE_SYNC && resultado == null
}

/**
 * Revisión de la compra antes de confirmar. Lo que propuso el OCR ("2 sacos") se cruza con la
 * presentación configurada ("Saco 25 kg") y el resultado se ve en la unidad base ("= 50 000 g").
 * Cada cambio se guarda en el teléfono; el stock solo cambia al confirmar (en el servidor).
 */
class RevisarCompraViewModel(private val repository: PlantaRepository, private val borradorId: String) : ScreenModel() {
    private val _state = MutableStateFlow(RevisarCompraState())
    val state: StateFlow<RevisarCompraState> = _state.asStateFlow()

    init {
        scope.launch { repository.observeCatalogo().collect { c -> _state.update { it.copy(catalogo = c?.value.orEmpty()) } } }
        scope.launch {
            var estadoAnterior: EstadoBorrador? = null
            repository.observeBorrador(borradorId).collect { b ->
                if (b == null) {
                    if (_state.value.resultado == null) _state.update { it.copy(cargado = true, existe = false) }
                    return@collect
                }
                // Se recargan los datos al abrir y cuando llega el OCR de una foto tomada sin conexión.
                val recargar = estadoAnterior == null || (estadoAnterior == EstadoBorrador.PENDIENTE_OCR && b.estado != EstadoBorrador.PENDIENTE_OCR)
                estadoAnterior = b.estado
                _state.update { s ->
                    val base = s.copy(cargado = true, origen = b.origen, estado = b.estado, mensaje = b.mensaje)
                    if (!recargar) {
                        base
                    } else {
                        base.copy(
                            proveedor = b.datos.proveedor.orEmpty(),
                            comprobante = b.datos.numeroComprobante.orEmpty(),
                            fecha = b.datos.fecha.orEmpty(),
                            total = b.datos.total?.let { formatCantidad(it).replace(" ", "") }.orEmpty(),
                            lineas = b.datos.lineas.ifEmpty { listOf(LineaBorrador()) }
                                .map { LineaEdicion(it, it.cantidad?.let { c -> formatCantidad(c).replace(" ", "") }.orEmpty()) },
                        )
                    }
                }
            }
        }
    }

    private fun editar(cambio: (RevisarCompraState) -> RevisarCompraState) {
        if (!_state.value.editable) return
        _state.update { cambio(it).copy(error = null) }
        repository.guardarBorrador(borradorId, _state.value.aBorrador())
    }

    fun onProveedor(v: String) = editar { it.copy(proveedor = v) }
    fun onComprobante(v: String) = editar { it.copy(comprobante = v) }
    fun onFecha(v: String) = editar { it.copy(fecha = v) }
    fun onTotal(v: String) = editar { it.copy(total = v.filter { c -> c.isDigit() || c == '.' || c == ',' }) }

    fun onInsumo(index: Int, insumo: CatalogoInsumo) = editarLinea(index) { l ->
        // Si el insumo tiene una sola presentación configurada, se elige sola.
        val unica = insumo.presentaciones.singleOrNull()?.id
        l.copy(linea = l.linea.copy(insumoId = insumo.id, presentacionId = unica))
    }

    fun onPresentacion(index: Int, presentacionId: Long) = editarLinea(index) { it.copy(linea = it.linea.copy(presentacionId = presentacionId)) }
    fun onCantidad(index: Int, texto: String) = editarLinea(index) { it.copy(cantidad = texto.filter { c -> c.isDigit() || c == '.' || c == ',' }) }

    fun agregarLinea() = editar { it.copy(lineas = it.lineas + LineaEdicion(LineaBorrador(), "")) }
    fun quitarLinea(index: Int) = editar { it.copy(lineas = it.lineas.filterIndexed { i, _ -> i != index }) }

    private fun editarLinea(index: Int, cambio: (LineaEdicion) -> LineaEdicion) =
        editar { s -> s.copy(lineas = s.lineas.mapIndexed { i, l -> if (i == index) cambio(l) else l }) }

    fun confirmar() {
        val datos = _state.value.aBorrador()
        if (!datos.listaParaConfirmar) {
            _state.update { it.copy(error = "Completa el insumo, la presentación y la cantidad de cada línea.") }
            return
        }
        scope.launch {
            _state.update { it.copy(enviando = true, error = null) }
            when (val r = repository.confirmarCompra(borradorId, datos)) {
                is OperacionResult.Rechazado -> _state.update { it.copy(enviando = false, error = r.mensaje) }
                else -> _state.update { it.copy(enviando = false, resultado = r) }
            }
        }
    }

    fun descartar() = repository.descartarBorrador(borradorId)
}

@Composable
fun RevisarCompraScreen(viewModel: RevisarCompraViewModel, isOnline: Boolean, onListo: () -> Unit) {
    val state by viewModel.state.collectAsState()

    when (val r = state.resultado) {
        is OperacionResult.Confirmado -> {
            PantallaCompraRegistrada(r.codigo, onListo)
            return
        }
        is OperacionResult.GuardadoOffline -> {
            PantallaGuardadoLocal(
                titulo = "Compra guardada en el teléfono",
                descripcion = "Se registrará automáticamente cuando vuelva la conexión.",
                hechos = listOf("Compra revisada y confirmada", "Pendiente de envío"),
                nota = "El stock de los insumos se actualizará cuando el servidor registre la compra.",
                onContinuar = onListo,
            )
            return
        }
        else -> Unit
    }

    if (!state.cargado) return
    if (!state.existe) {
        EstadoVacio("Esta compra ya no está en el teléfono.")
        return
    }

    ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(14.dp)) {
        if (state.origen == OrigenCompra.BOLETA && state.editable) {
            item {
                Text(
                    "Revisa y corrige los datos detectados por OCR antes de confirmar.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = EcoLactColors.TextoSecundario,
                    modifier = Modifier.anchoContenido(),
                )
            }
        }
        when (state.estado) {
            EstadoBorrador.PENDIENTE_OCR -> item {
                AvisoAtencion("Foto guardada", "La boleta se leerá cuando vuelva la conexión. Puedes asociar los insumos a mano ahora.")
            }
            EstadoBorrador.PENDIENTE_SYNC -> item {
                AvisoAtencion("Pendiente de envío", "Esta compra ya fue confirmada y se registrará al volver la conexión.")
            }
            EstadoBorrador.REQUIERE_REVISION -> state.mensaje?.let { item { AvisoAtencion("Revisa esta compra", it, icon = EcoLactIcons.Warning) } }
        }
        if (state.catalogo.isEmpty()) {
            item { AvisoInfo("El catálogo de insumos todavía no se descargó en el teléfono. Se descargará al tener conexión.") }
        }

        state.lineas.forEachIndexed { index, linea ->
            item(key = "l-$index") {
                TarjetaLinea(
                    linea = linea,
                    catalogo = state.catalogo,
                    editable = state.editable,
                    puedeQuitar = state.lineas.size > 1,
                    onInsumo = { viewModel.onInsumo(index, it) },
                    onPresentacion = { viewModel.onPresentacion(index, it) },
                    onCantidad = { viewModel.onCantidad(index, it) },
                    onQuitar = { viewModel.quitarLinea(index) },
                )
            }
        }
        if (state.editable) {
            item {
                TextButton(onClick = viewModel::agregarLinea, modifier = Modifier.anchoContenido()) {
                    Icon(EcoLactIcons.Add, contentDescription = null, tint = EcoLactColors.VerdeOscuro, modifier = Modifier.size(18.dp))
                    Text("Agregar insumo", color = EcoLactColors.VerdeOscuro, modifier = Modifier.padding(start = 6.dp))
                }
            }
        }

        item { TituloBloque("Comprobante") }
        item { CampoPlanta(state.proveedor, viewModel::onProveedor, "Proveedor comercial (opcional)") }
        item { CampoPlanta(state.comprobante, viewModel::onComprobante, "N° de comprobante (opcional)") }
        item { CampoPlanta(state.fecha, viewModel::onFecha, "Fecha (AAAA-MM-DD, opcional)") }
        item { CampoPlanta(state.total, viewModel::onTotal, "Total de la compra", numerico = true, prefijo = "S/") }

        if (!isOnline && state.editable) {
            item { AvisoAtencion("Sin conexión", "La compra se guardará en el teléfono y se registrará al volver la conexión.") }
        }
        state.error?.let { item { MensajeError(it) } }
        if (state.editable) {
            item {
                BotonPrincipal(
                    texto = "Confirmar compra",
                    onClick = viewModel::confirmar,
                    enabled = state.aBorrador().listaParaConfirmar,
                    cargando = state.enviando,
                    operacion = true,
                )
            }
            item { NotaRequiereAsistencia() }
            item {
                TextButton(onClick = { viewModel.descartar(); onListo() }, modifier = Modifier.anchoContenido()) {
                    Text("Descartar", color = EcoLactColors.Rojo)
                }
            }
        }
    }
}

/** Insumo · Cantidad · Unidad OCR · Presentación · Resultado en unidad base. */
@Composable
private fun TarjetaLinea(
    linea: LineaEdicion,
    catalogo: List<CatalogoInsumo>,
    editable: Boolean,
    puedeQuitar: Boolean,
    onInsumo: (CatalogoInsumo) -> Unit,
    onPresentacion: (Long) -> Unit,
    onCantidad: (String) -> Unit,
    onQuitar: () -> Unit,
) {
    val insumo = catalogo.firstOrNull { it.id == linea.linea.insumoId }
    val presentacion = insumo?.presentaciones?.firstOrNull { it.id == linea.linea.presentacionId }
    val cantidad = parseDecimal(linea.cantidad)

    Column(Modifier.anchoContenido().contenedorBlanco().padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(insumo?.nombre ?: "Insumo", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
            if (editable && puedeQuitar) {
                IconButton(onClick = onQuitar) { Icon(EcoLactIcons.Close, contentDescription = "Quitar línea", tint = EcoLactColors.TextoSecundario) }
            }
        }
        linea.linea.textoOcr?.let {
            Text("Leído en la boleta: $it", style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
        }
        if (editable) {
            SelectorPlanta(
                label = "Insumo",
                valor = insumo?.nombre,
                opciones = catalogo,
                texto = { it.nombre },
                onSeleccionar = onInsumo,
                error = insumo == null,
            )
        }
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
            if (editable) {
                CampoPlanta(linea.cantidad, onCantidad, "Cantidad", Modifier.weight(1f), numerico = true, error = cantidad == null || cantidad <= 0.0)
            } else {
                DatoLinea("Cantidad", linea.cantidad, Modifier.weight(1f))
            }
            DatoLinea("Unidad OCR", linea.linea.unidadOcr ?: "—", Modifier.weight(1f))
        }
        if (editable) {
            SelectorPlanta(
                label = "Presentación",
                valor = presentacion?.etiqueta,
                opciones = insumo?.presentaciones.orEmpty(),
                texto = { it.etiqueta },
                onSeleccionar = { onPresentacion(it.id) },
                placeholder = if (insumo == null) "Elige primero el insumo" else if (insumo.presentaciones.isEmpty()) "Sin presentaciones configuradas" else "Seleccionar",
                error = insumo != null && presentacion == null,
            )
        } else {
            DatoLinea("Presentación", presentacion?.etiqueta ?: "—")
        }
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Text("Resultado", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, modifier = Modifier.weight(1f))
            Text(
                if (presentacion != null && cantidad != null && cantidad > 0.0) {
                    "= ${formatCantidad(redondear4(cantidad * presentacion.cantidad_base), insumo.unidad_base)}"
                } else {
                    "—"
                },
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
                color = EcoLactColors.VerdeOscuro,
            )
        }
    }
}

@Composable
private fun DatoLinea(label: String, valor: String, modifier: Modifier = Modifier) {
    Column(modifier) {
        Text(label, style = MaterialTheme.typography.labelMedium, color = EcoLactColors.TextoSecundario)
        Text(valor, style = MaterialTheme.typography.bodyLarge, color = EcoLactColors.TextoPrincipal)
    }
}

@Composable
private fun PantallaCompraRegistrada(referencia: String?, onListo: () -> Unit) {
    Column(
        Modifier.anchoContenido().padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Icon(EcoLactIcons.CheckCircle, contentDescription = null, tint = EcoLactColors.VerdePrincipal, modifier = Modifier.size(56.dp).padding(top = 16.dp))
        Text("Compra registrada", style = MaterialTheme.typography.titleLarge)
        Text(
            listOfNotNull(referencia, "El stock de los insumos ya fue actualizado.").joinToString(" · "),
            style = MaterialTheme.typography.bodyMedium,
            color = EcoLactColors.TextoSecundario,
        )
        BotonPrincipal("Continuar", onListo)
    }
}
