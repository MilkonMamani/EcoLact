package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.components.ResultadoCalidadChip
import pe.edu.upeu.ecolactmobile.core.ui.components.SectionTitle
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.AnalisisDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository

class CalidadHistorialDetalleViewModel(repository: CalidadRepository, analisisId: Long) :
    ResourceViewModel<AnalisisDetalleResponse>({ repository.historialDetalle(analisisId) })

@Composable
fun CalidadHistorialDetalleScreen(viewModel: CalidadHistorialDetalleViewModel) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val data = s.data
            LazyColumn(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        if (s.fromCache) OfflineBanner(s.cachedAtIso)
                        data.entrega?.proveedor_nombre?.let { Text(it, style = MaterialTheme.typography.titleLarge) }
                        data.entrega?.let { Text("${it.litros} L · ${it.zona_nombre ?: ""}", color = EcoLactColors.TextoSecundario) }
                        ResultadoCalidadChip(data.resultado)
                        data.observacion?.let { Text(it) }
                    }
                }

                if (data.detalles.isNotEmpty()) {
                    item { SectionTitle("Parámetros") }
                    items(data.detalles, key = { it.parametro_id }) { param ->
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text(param.parametro_nombre)
                            Column(horizontalAlignment = androidx.compose.ui.Alignment.End) {
                                Text("${param.valor} ${param.unidad.orEmpty()}")
                                Text(
                                    when (param.cumple) {
                                        true -> "✓ Dentro del rango"
                                        false -> "✗ Fuera del rango"
                                        null -> "Sin regla configurada"
                                    },
                                    color = when (param.cumple) {
                                        true -> EcoLactColors.VerdePrincipal
                                        false -> EcoLactColors.Rojo
                                        null -> EcoLactColors.TextoSecundario
                                    },
                                )
                            }
                        }
                    }
                }

                item {
                    Column {
                        SectionTitle("Evidencia")
                        Text(if (data.imagen_path != null) "Fotografía adjunta" else "Sin fotografía", color = EcoLactColors.TextoSecundario)
                        Text("Origen: MANUAL", color = EcoLactColors.TextoSecundario)
                    }
                }
            }
        }
    }
}
