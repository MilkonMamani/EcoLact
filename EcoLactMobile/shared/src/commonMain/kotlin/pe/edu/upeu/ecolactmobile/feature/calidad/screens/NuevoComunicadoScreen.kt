package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilterChip
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import pe.edu.upeu.ecolactmobile.feature.calidad.CrearComunicadoRequest

data class NuevoComunicadoUiState(
    val titulo: String = "",
    val contenido: String = "",
    val tipo: String = "AVISO",
    val alcance: String = "GENERAL",
    val zonaId: String = "",
    val destinatarios: String = "",
    val enviando: Boolean = false,
    val error: String? = null,
    val creado: Boolean = false,
)

class NuevoComunicadoViewModel(private val repository: CalidadRepository) : ScreenModel() {
    private val _state = MutableStateFlow(NuevoComunicadoUiState())
    val state: StateFlow<NuevoComunicadoUiState> = _state.asStateFlow()

    fun onTituloChange(v: String) = _state.update { it.copy(titulo = v) }
    fun onContenidoChange(v: String) = _state.update { it.copy(contenido = v) }
    fun onTipoChange(v: String) = _state.update { it.copy(tipo = v) }
    fun onAlcanceChange(v: String) = _state.update { it.copy(alcance = v) }
    fun onZonaIdChange(v: String) = _state.update { it.copy(zonaId = v) }
    fun onDestinatariosChange(v: String) = _state.update { it.copy(destinatarios = v) }

    fun enviar() {
        val current = _state.value
        if (current.titulo.isBlank() || current.contenido.isBlank()) {
            _state.update { it.copy(error = "Título y contenido son obligatorios") }
            return
        }
        scope.launch {
            _state.update { it.copy(enviando = true, error = null) }
            val request = CrearComunicadoRequest(
                titulo = current.titulo,
                contenido = current.contenido,
                tipo = current.tipo,
                alcance = current.alcance,
                zona_id = if (current.alcance == "ZONA") current.zonaId.toLongOrNull() else null,
                destinatarios = if (current.alcance == "INDIVIDUAL") {
                    current.destinatarios.split(",").mapNotNull { it.trim().toLongOrNull() }
                } else null,
            )
            repository.crearComunicado(request)
                .onSuccess { _state.update { it.copy(enviando = false, creado = true) } }
                .onFailure { e -> _state.update { it.copy(enviando = false, error = e.message ?: "No se pudo crear el comunicado") } }
        }
    }
}

@Composable
fun NuevoComunicadoScreen(viewModel: NuevoComunicadoViewModel, onCreado: () -> Unit) {
    val state by viewModel.state.collectAsState()

    LaunchedEffect(state.creado) {
        if (state.creado) onCreado()
    }

    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        OutlinedTextField(state.titulo, viewModel::onTituloChange, label = { Text("Título") }, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(state.contenido, viewModel::onContenidoChange, label = { Text("Contenido") }, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(state.tipo, viewModel::onTipoChange, label = { Text("Tipo") }, modifier = Modifier.fillMaxWidth())

        Text("Destinatarios")
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            listOf("GENERAL", "ZONA", "INDIVIDUAL").forEach { alcance ->
                FilterChip(selected = state.alcance == alcance, onClick = { viewModel.onAlcanceChange(alcance) }, label = { Text(alcance) })
            }
        }

        if (state.alcance == "ZONA") {
            OutlinedTextField(state.zonaId, viewModel::onZonaIdChange, label = { Text("ID de zona") }, modifier = Modifier.fillMaxWidth())
        }
        if (state.alcance == "INDIVIDUAL") {
            OutlinedTextField(
                state.destinatarios,
                viewModel::onDestinatariosChange,
                label = { Text("IDs de usuario separados por coma") },
                modifier = Modifier.fillMaxWidth(),
            )
        }

        state.error?.let { Text(it, color = EcoLactColors.Rojo) }

        Button(
            onClick = viewModel::enviar,
            enabled = !state.enviando,
            modifier = Modifier.fillMaxWidth().height(52.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            if (state.enviando) CircularProgressIndicator(modifier = Modifier.height(20.dp), color = EcoLactColors.Blanco)
            else Text("Publicar comunicado")
        }
    }
}
