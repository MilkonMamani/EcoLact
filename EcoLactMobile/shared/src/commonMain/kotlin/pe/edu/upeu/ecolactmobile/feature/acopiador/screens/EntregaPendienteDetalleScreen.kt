package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import pe.edu.upeu.ecolactmobile.core.ui.components.EmptyBox
import pe.edu.upeu.ecolactmobile.core.ui.components.SyncStatusChip
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.PendingEntregaView

/**
 * "Editar"/"Eliminar" solo mientras la entrega no se haya sincronizado (acopiador.txt sección 7):
 * es un concepto 100% local en SQLite, el backend no tiene PATCH/DELETE para este rol. Una fila
 * NEEDS_REVIEW se puede corregir y se vuelve a encolar como PENDING.
 */
class EntregaPendienteDetalleViewModel(
    private val repository: AcopiadorRepository,
    private val localId: String,
) : ScreenModel() {
    private val _item = MutableStateFlow<PendingEntregaView?>(null)
    val item: StateFlow<PendingEntregaView?> = _item.asStateFlow()

    private val _litros = MutableStateFlow("")
    val litros: StateFlow<String> = _litros.asStateFlow()

    private val _observacion = MutableStateFlow("")
    val observacion: StateFlow<String> = _observacion.asStateFlow()

    private val _deleted = MutableStateFlow(false)
    val deleted: StateFlow<Boolean> = _deleted.asStateFlow()

    init {
        load()
    }

    fun load() {
        val found = repository.entregasPendientes().firstOrNull { it.localId == localId }
        _item.value = found
        found?.let {
            _litros.value = it.request.litros.toString()
            _observacion.value = it.request.observacion.orEmpty()
        }
    }

    fun onLitrosChange(value: String) {
        _litros.value = value
    }

    fun onObservacionChange(value: String) {
        _observacion.value = value
    }

    fun guardar() {
        val current = _item.value ?: return
        val litrosValue = _litros.value.toDoubleOrNull() ?: return
        val nuevaRequest = current.request.copy(litros = litrosValue, observacion = _observacion.value.ifBlank { null })
        if (current.status == "NEEDS_REVIEW") {
            repository.corregirYReintentar(current.localId, nuevaRequest)
        } else {
            repository.editarEntregaPendiente(current.localId, nuevaRequest)
        }
        load()
    }

    fun eliminar() {
        repository.eliminarEntregaPendiente(localId)
        _deleted.value = true
    }
}

@Composable
fun EntregaPendienteDetalleScreen(viewModel: EntregaPendienteDetalleViewModel, onEliminada: () -> Unit) {
    val item by viewModel.item.collectAsState()
    val litros by viewModel.litros.collectAsState()
    val observacion by viewModel.observacion.collectAsState()
    val deleted by viewModel.deleted.collectAsState()

    LaunchedEffect(deleted) {
        if (deleted) onEliminada()
    }

    val current = item
    if (current == null) {
        EmptyBox("Este registro ya no está disponible")
        return
    }

    val editable = current.status == "PENDING" || current.status == "NEEDS_REVIEW"

    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        SyncStatusChip(current.status)

        current.errorMessage?.let {
            Text("Motivo: $it", color = EcoLactColors.Rojo)
        }

        Text("Método: ${current.request.metodo_identificacion} · ${current.request.identificador}", color = EcoLactColors.TextoSecundario)

        OutlinedTextField(
            value = litros,
            onValueChange = viewModel::onLitrosChange,
            label = { Text("Litros") },
            enabled = editable,
            keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(keyboardType = KeyboardType.Decimal),
            modifier = Modifier.fillMaxWidth(),
        )
        OutlinedTextField(
            value = observacion,
            onValueChange = viewModel::onObservacionChange,
            label = { Text("Observación") },
            enabled = editable,
            modifier = Modifier.fillMaxWidth(),
        )

        if (editable) {
            Button(
                onClick = viewModel::guardar,
                modifier = Modifier.fillMaxWidth(),
                colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
            ) {
                Text(if (current.status == "NEEDS_REVIEW") "Corregir y reintentar" else "Guardar cambios")
            }
            OutlinedButton(
                onClick = viewModel::eliminar,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text("Eliminar registro", color = EcoLactColors.Rojo)
            }
        } else {
            Text("Esta entrega ya está siendo enviada al sistema.", color = EcoLactColors.TextoSecundario)
        }
    }
}
