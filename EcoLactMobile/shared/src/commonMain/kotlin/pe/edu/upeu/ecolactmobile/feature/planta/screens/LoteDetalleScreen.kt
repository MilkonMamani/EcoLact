package pe.edu.upeu.ecolactmobile.feature.planta.screens

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
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.components.SectionTitle
import pe.edu.upeu.ecolactmobile.core.ui.components.SyncStatusChip
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.planta.LoteDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.ResultadoPendienteView

class LoteDetalleViewModel(repository: PlantaRepository, private val loteId: Long) :
    ResourceViewModel<LoteDetalleResponse>({ repository.loteDetalle(loteId) }) {
    fun resultadoPendiente(repository: PlantaRepository): ResultadoPendienteView? =
        repository.resultadosPendientesDeSincronizar().firstOrNull { it.loteId == loteId }
}

@Composable
fun LoteDetalleScreen(viewModel: LoteDetalleViewModel, repository: PlantaRepository, onRegistrarResultado: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val lote = s.data.lote
            val resultadoPendiente = viewModel.resultadoPendiente(repository)
            LazyColumn(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        if (s.fromCache) OfflineBanner(s.cachedAtIso)
                        Text(lote.codigo, style = MaterialTheme.typography.titleLarge)
                        Text("● ${lote.estado}", color = EcoLactColors.AzulInstitucional)
                        lote.fecha_hora_inicio?.let { Text("Inicio: $it", color = EcoLactColors.TextoSecundario) }
                        lote.fecha_hora_fin?.let { Text("Fin: $it", color = EcoLactColors.TextoSecundario) }
                        Text("${lote.litros_leche_utilizados} L utilizados")
                        lote.observacion?.let { Text(it, color = EcoLactColors.TextoSecundario) }

                        when {
                            resultadoPendiente != null -> SyncStatusChip(resultadoPendiente.status)
                            lote.estado == "EN_PROCESO" -> Button(
                                onClick = { onRegistrarResultado(lote.id) },
                                modifier = Modifier.fillMaxWidth().height(52.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
                            ) { Text("Registrar resultado") }
                        }
                    }
                }

                if (lote.insumos_utilizados.isNotEmpty()) {
                    item { SectionTitle("Insumos") }
                    items(lote.insumos_utilizados) { i ->
                        Text("${i.insumoNombre ?: "Insumo #${i.insumoId}"}: ${i.cantidadUsada} ${i.unidadMedida ?: ""}")
                    }
                }

                if (lote.productos_obtenidos.isNotEmpty()) {
                    item { SectionTitle("Resultado") }
                    items(lote.productos_obtenidos) { p ->
                        Text("${p.cantidadProducida} ${p.unidadMedida ?: ""} · ${p.productoNombre ?: "Producto #${p.productoId}"}")
                    }
                }
            }
        }
    }
}
