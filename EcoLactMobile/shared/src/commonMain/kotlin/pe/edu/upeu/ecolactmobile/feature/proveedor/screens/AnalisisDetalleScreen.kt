package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

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
import pe.edu.upeu.ecolactmobile.core.ui.components.ChipTone
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.components.ResultadoCalidadChip
import pe.edu.upeu.ecolactmobile.core.ui.components.SectionTitle
import pe.edu.upeu.ecolactmobile.core.ui.components.StatusChip
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.proveedor.AnalisisDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository

class ProveedorAnalisisDetalleViewModel(repository: ProveedorRepository, analisisId: Long) :
    ResourceViewModel<AnalisisDetalleResponse>({ repository.analisisDetalle(analisisId) })

@Composable
fun ProveedorAnalisisDetalleScreen(viewModel: ProveedorAnalisisDetalleViewModel) {
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
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        if (s.fromCache) OfflineBanner(s.cachedAtIso)
                        ResultadoCalidadChip(data.resultado)
                        data.entrega?.let {
                            Text("${it.litros} L" + (it.zona_nombre?.let { z -> " · $z" } ?: ""), color = EcoLactColors.TextoSecundario)
                        }
                        Text(data.fecha_analisis, color = EcoLactColors.TextoSecundario)
                        data.observacion?.let {
                            Text(it, style = MaterialTheme.typography.bodyMedium)
                        }
                    }
                }

                if (data.detalles.isNotEmpty()) {
                    item { SectionTitle("Parámetros") }
                    items(data.detalles) { param ->
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                        ) {
                            Text(param.parametro_nombre)
                            Text(
                                "${param.valor}${param.unidad?.let { " $it" } ?: ""}",
                                color = when (param.cumple) {
                                    true -> EcoLactColors.VerdePrincipal
                                    false -> EcoLactColors.Rojo
                                    else -> EcoLactColors.TextoSecundario
                                },
                            )
                        }
                    }
                }

                data.sancion?.let { sancion ->
                    item {
                        Column {
                            SectionTitle("Sanción")
                            StatusChip("${sancion.tipo}${sancion.descripcion?.let { " · $it" } ?: ""}", ChipTone.ERROR)
                        }
                    }
                }
            }
        }
    }
}
