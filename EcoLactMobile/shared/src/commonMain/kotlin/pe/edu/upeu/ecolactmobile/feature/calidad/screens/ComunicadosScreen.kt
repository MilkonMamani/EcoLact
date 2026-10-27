package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.EmptyBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ListItemCard
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadComunicado
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import pe.edu.upeu.ecolactmobile.feature.trabajador.LocalAutorizacion

class CalidadComunicadosViewModel(repository: CalidadRepository) :
    ResourceViewModel<List<CalidadComunicado>>({ repository.comunicados() })

/** A diferencia de Acopiador/Proveedor, Calidad sí puede crear comunicados (calidad.md sección 6). */
@Composable
fun CalidadComunicadosScreen(viewModel: CalidadComunicadosViewModel, onVerComunicado: (Long) -> Unit, onNuevo: () -> Unit) {
    val state by viewModel.state.collectAsState()

    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        Button(
            onClick = onNuevo,
            enabled = LocalAutorizacion.current.puedeOperar,
            modifier = Modifier.fillMaxWidth().height(48.dp).padding(bottom = 12.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            Text("+ Nuevo comunicado")
        }

        when (val s = state) {
            is UiState.Loading -> LoadingBox()
            is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
            is UiState.Success -> {
                Column {
                    if (s.fromCache) OfflineBanner(s.cachedAtIso, Modifier.padding(bottom = 12.dp))
                    if (s.data.isEmpty()) {
                        EmptyBox("No hay comunicados")
                    } else {
                        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            items(s.data, key = { it.id }) { comunicado ->
                                ListItemCard(onClick = { onVerComunicado(comunicado.id) }) {
                                    Text(comunicado.titulo, style = MaterialTheme.typography.titleMedium)
                                    Text(comunicado.fecha_publicacion, color = EcoLactColors.TextoSecundario)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

class CalidadComunicadoDetalleViewModel(repository: CalidadRepository, comunicadoId: Long) :
    ResourceViewModel<CalidadComunicado>({ repository.comunicadoDetalle(comunicadoId) })

@Composable
fun CalidadComunicadoDetalleScreen(viewModel: CalidadComunicadoDetalleViewModel) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val data = s.data
            Column(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                if (s.fromCache) OfflineBanner(s.cachedAtIso)
                Text(data.titulo, style = MaterialTheme.typography.titleLarge)
                Text(data.fecha_publicacion, color = EcoLactColors.TextoSecundario)
                data.contenido?.let { Text(it, style = MaterialTheme.typography.bodyLarge) }
            }
        }
    }
}
