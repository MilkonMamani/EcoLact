package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.ConfirmarAnalisisResult
import pe.edu.upeu.ecolactmobile.feature.calidad.EstadoCaptura
import pe.edu.upeu.ecolactmobile.feature.calidad.ParametroActivo
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.calidad.components.MensajeError
import pe.edu.upeu.ecolactmobile.feature.calidad.components.PantallaGuardadoLocal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.ResultadoChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.CargandoBox
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoError
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco
import pe.edu.upeu.ecolactmobile.feature.trabajador.NotaRequiereAsistencia

@Composable
fun RevisionAnalisisScreen(viewModel: RevisionAnalisisViewModel, onVerDetalle: (Long) -> Unit, onContinuar: () -> Unit) {
    val state by viewModel.state.collectAsState()
    when {
        state.cargando -> CargandoBox()
        state.errorCarga != null -> EstadoError(state.errorCarga!!, viewModel::cargar)
        else -> when (val r = state.resultado) {
            is ConfirmarAnalisisResult.Confirmado -> AnalisisConfirmado(r, onVerDetalle, onContinuar)
            is ConfirmarAnalisisResult.GuardadoOffline -> PantallaGuardadoLocal(
                titulo = "Sin conexión a Internet",
                descripcion = "El análisis se guardará en el dispositivo y se enviará automáticamente.",
                hechos = listOfNotNull("Foto guardada".takeIf { r.conFoto }, "Análisis guardado en el dispositivo"),
                nota = "Se sincronizará automáticamente cuando vuelva la conexión.",
                onContinuar = onContinuar,
            )
            else -> Formulario(state, viewModel)
        }
    }
}

@Composable
private fun Formulario(state: RevisionUiState, viewModel: RevisionAnalisisViewModel) {
    ProveedorPantalla {
        item {
            Column(Modifier.anchoContenido()) {
                Text(
                    if (state.captura == EstadoCaptura.REQUIERE_REVISION) "Datos detectados" else "Ingresar valores",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold,
                )
                Text(
                    when (state.captura) {
                        EstadoCaptura.REQUIERE_REVISION -> state.mensajeOcr ?: "Revisa cada valor antes de confirmar."
                        EstadoCaptura.PENDIENTE_OCR -> "La foto se leerá cuando vuelva la conexión. Puedes ingresar los valores ahora."
                        null -> "Deja vacío lo que no se midió."
                    },
                    style = MaterialTheme.typography.bodyMedium,
                    color = if (state.captura == EstadoCaptura.PENDIENTE_OCR) EcoLactColors.NaranjaOscuro else EcoLactColors.TextoSecundario,
                )
            }
        }
        if (state.parametros.isEmpty()) {
            item { MensajeError("No hay parámetros de calidad activos configurados.") }
        } else {
            item {
                Column(Modifier.anchoContenido().contenedorBlanco()) {
                    state.parametros.forEachIndexed { i, parametro ->
                        if (i > 0) HorizontalDivider(color = EcoLactColors.Bordes, modifier = Modifier.padding(start = 16.dp))
                        FilaParametro(
                            parametro = parametro,
                            texto = state.valores[parametro.codigo].orEmpty(),
                            invalido = parametro.codigo in state.camposInvalidos,
                            onCambio = { viewModel.onValor(parametro.codigo, it) },
                        )
                    }
                }
            }
        }
        item {
            OutlinedTextField(
                value = state.observacion,
                onValueChange = viewModel::onObservacion,
                label = { Text("Observación (opcional)") },
                shape = RoundedCornerShape(12.dp),
                colors = camposColores(),
                modifier = Modifier.anchoContenido(),
            )
        }
        item { ResultadoEstimadoCard(state) }
        state.error?.let { item { MensajeError(it) } }
        item { BotonPrincipal("Confirmar análisis", viewModel::confirmar, cargando = state.enviando, enabled = state.parametros.isNotEmpty(), operacion = true) }
        item { NotaRequiereAsistencia() }
    }
}

/** Nombre (y unidad) a la izquierda; campo compacto a la derecha. Vacío = "No detectado". */
@Composable
private fun FilaParametro(parametro: ParametroActivo, texto: String, invalido: Boolean, onCambio: (String) -> Unit) {
    Row(
        Modifier.fillMaxWidth().heightIn(min = 56.dp).padding(start = 16.dp, end = 12.dp, top = 6.dp, bottom = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Column(Modifier.weight(1f)) {
            Text(parametro.nombre, style = MaterialTheme.typography.bodyLarge, color = EcoLactColors.TextoPrincipal)
            parametro.unidad?.takeIf { it.isNotBlank() }?.let {
                Text(it, style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
            }
        }
        OutlinedTextField(
            value = texto,
            onValueChange = onCambio,
            placeholder = { Text("No detectado", style = MaterialTheme.typography.bodySmall, textAlign = TextAlign.End, modifier = Modifier.fillMaxWidth()) },
            singleLine = true,
            isError = invalido,
            textStyle = MaterialTheme.typography.bodyLarge.copy(textAlign = TextAlign.End, fontWeight = FontWeight.SemiBold),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal, imeAction = ImeAction.Next),
            shape = RoundedCornerShape(10.dp),
            colors = camposColores(),
            modifier = Modifier.width(132.dp),
        )
    }
}

@Composable
private fun ResultadoEstimadoCard(state: RevisionUiState) {
    val estimado = state.estimado
    Column(Modifier.anchoContenido().contenedorBlanco().padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text("Resultado estimado", style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
            if (estimado != null) StatusChip(estimado.resultado.label, estimado.resultado.tono)
        }
        Text(
            when {
                estimado == null -> "Ingresa los valores para ver el resultado estimado."
                state.reglas.isEmpty() -> "Sin reglas guardadas en el teléfono: el resultado se calculará al confirmar."
                estimado.sinRegla.isNotEmpty() -> "Algunos parámetros no tienen regla vigente. El servidor confirmará el resultado."
                else -> "Vista previa. El resultado final lo valida el servidor al confirmar."
            },
            style = MaterialTheme.typography.bodySmall,
            color = EcoLactColors.TextoSecundario,
        )
    }
}

@Composable
private fun AnalisisConfirmado(r: ConfirmarAnalisisResult.Confirmado, onVerDetalle: (Long) -> Unit, onContinuar: () -> Unit) {
    ProveedorPantalla {
        item {
            Column(Modifier.anchoContenido().padding(top = 24.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Icon(EcoLactIcons.CheckCircle, contentDescription = null, tint = EcoLactColors.VerdePrincipal, modifier = Modifier.size(56.dp))
                Text("Análisis confirmado", style = MaterialTheme.typography.titleLarge)
                ResultadoChip(r.analisis.resultado)
                Text("La muestra salió de pendientes y ya está en el historial.", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, textAlign = TextAlign.Center)
            }
        }
        item { BotonPrincipal("Continuar", onContinuar) }
        item { BotonSecundario("Ver detalle", { onVerDetalle(r.analisis.id) }) }
    }
}

@Composable
private fun camposColores() = OutlinedTextFieldDefaults.colors(
    focusedContainerColor = EcoLactColors.Blanco,
    unfocusedContainerColor = EcoLactColors.Blanco,
    focusedBorderColor = EcoLactColors.VerdePrincipal,
    unfocusedBorderColor = EcoLactColors.Bordes,
)
