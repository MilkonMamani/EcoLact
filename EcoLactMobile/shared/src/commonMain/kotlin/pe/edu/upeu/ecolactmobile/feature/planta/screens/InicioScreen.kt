package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
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
import pe.edu.upeu.ecolactmobile.core.ui.components.KpiCard
import pe.edu.upeu.ecolactmobile.core.ui.components.KpiRow
import pe.edu.upeu.ecolactmobile.core.ui.components.ListItemCard
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.components.SectionTitle
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaDashboard
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository

class PlantaInicioViewModel(repository: PlantaRepository) :
    ResourceViewModel<PlantaDashboard>({ repository.dashboard() })

@Composable
fun PlantaInicioScreen(
    viewModel: PlantaInicioViewModel,
    onRegistrarProduccion: () -> Unit,
    onVerLote: (Long) -> Unit,
    onVerInsumo: (Long) -> Unit,
) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val data = s.data
            LazyColumn(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        if (s.fromCache) OfflineBanner(s.cachedAtIso)

                        KpiRow {
                            KpiCard("🏭", "${data.kpis.enCurso}", "Lotes en proceso", Modifier.weight(1f))
                            KpiCard("🥛", "${data.kpis.litrosUtilizados} L", "Leche usada", Modifier.weight(1f))
                        }
                        KpiRow {
                            KpiCard("📦", "${data.kpis.productosProducidos}", "Producidas hoy", Modifier.weight(1f))
                            KpiCard("⚠", "${data.insumos_bajo_minimo.size}", "Alertas de insumos", Modifier.weight(1f))
                        }

                        Button(
                            onClick = onRegistrarProduccion,
                            modifier = Modifier.fillMaxWidth().height(64.dp),
                            shape = RoundedCornerShape(16.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
                        ) {
                            Text("+ Registrar producción")
                        }
                    }
                }

                if (data.insumos_bajo_minimo.isNotEmpty()) {
                    item { SectionTitle("Atención requerida") }
                    items(data.insumos_bajo_minimo, key = { it.id }) { insumo ->
                        ListItemCard(onClick = { onVerInsumo(insumo.id) }) {
                            Text("🔴 ${insumo.nombre}", style = MaterialTheme.typography.bodyLarge)
                            Text("${insumo.stock_actual} ${insumo.unidad_medida ?: ""} disponible", color = EcoLactColors.Rojo)
                        }
                    }
                }

                if (data.produccion_actual.isNotEmpty()) {
                    item { SectionTitle("Producción activa") }
                    items(data.produccion_actual, key = { it.id }) { lote ->
                        ListItemCard(onClick = { onVerLote(lote.id) }) {
                            Text(lote.codigo, style = MaterialTheme.typography.titleMedium)
                            Text("● ${lote.estado}", color = EcoLactColors.AzulInstitucional)
                            Text("${lote.litros_leche_utilizados} L", color = EcoLactColors.TextoSecundario)
                        }
                    }
                }
            }
        }
    }
}
