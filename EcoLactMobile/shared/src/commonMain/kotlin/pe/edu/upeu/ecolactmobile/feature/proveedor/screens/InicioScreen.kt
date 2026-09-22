package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
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
import pe.edu.upeu.ecolactmobile.core.ui.components.KpiCard
import pe.edu.upeu.ecolactmobile.core.ui.components.KpiRow
import pe.edu.upeu.ecolactmobile.core.ui.components.ListItemCard
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.components.ResultadoCalidadChip
import pe.edu.upeu.ecolactmobile.core.ui.components.SectionTitle
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorDashboard
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository

class ProveedorInicioViewModel(repository: ProveedorRepository) :
    ResourceViewModel<ProveedorDashboard>({ repository.dashboard() })

@Composable
fun ProveedorInicioScreen(
    viewModel: ProveedorInicioViewModel,
    onVerEntrega: (Long) -> Unit,
    onVerLiquidacion: (Long) -> Unit,
    onVerComunicado: (Long) -> Unit,
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
                    if (s.fromCache) OfflineBanner(s.cachedAtIso)
                }
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        SectionTitle("Esta semana")
                        KpiRow {
                            KpiCard("🥛", "${data.kpis.litros} L", "Litros", Modifier.weight(1f))
                            KpiCard("🚚", "${data.kpis.entregas}", "Entregas", Modifier.weight(1f))
                        }
                        KpiRow {
                            KpiCard("💰", "S/ ${data.por_cobrar}", "Por cobrar", Modifier.weight(1f))
                            KpiCard(
                                "🧪",
                                data.ultimo_analisis_resultado ?: "—",
                                "Último análisis",
                                Modifier.weight(1f),
                            )
                        }
                    }
                }

                data.ultimas_entregas.firstOrNull()?.let { ultima ->
                    item {
                        Column {
                            SectionTitle("Última entrega")
                            ListItemCard(onClick = { onVerEntrega(ultima.id) }) {
                                Text("${ultima.litros} L", style = MaterialTheme.typography.titleMedium)
                                Text(ultima.fecha_hora, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                                ResultadoCalidadChip(ultima.resultado, Modifier.padding(top = 4.dp))
                            }
                        }
                    }
                }

                data.ultima_liquidacion?.let { liq ->
                    item {
                        Column {
                            SectionTitle("Mi liquidación")
                            ListItemCard(onClick = { onVerLiquidacion(liq.id) }) {
                                data.ultima_liquidacion_periodo?.let {
                                    Text(
                                        "${it.fecha_inicio} - ${it.fecha_fin}",
                                        style = MaterialTheme.typography.bodyMedium,
                                        color = EcoLactColors.TextoSecundario,
                                    )
                                }
                                Text("S/ ${liq.total_pagar}", style = MaterialTheme.typography.titleMedium)
                                Text(liq.estado, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                            }
                        }
                    }
                }

                if (data.comunicados_recientes.isNotEmpty()) {
                    item { SectionTitle("Avisos") }
                    items(data.comunicados_recientes, key = { it.id }) { comunicado ->
                        ListItemCard(onClick = { onVerComunicado(comunicado.id) }) {
                            Text(comunicado.titulo, style = MaterialTheme.typography.bodyLarge)
                            Text(
                                comunicado.fecha_publicacion,
                                style = MaterialTheme.typography.bodyMedium,
                                color = EcoLactColors.TextoSecundario,
                            )
                        }
                    }
                }
            }
        }
    }
}
