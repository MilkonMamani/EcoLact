package pe.edu.upeu.ecolactmobile.feature.calidad.screens

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
import pe.edu.upeu.ecolactmobile.core.ui.components.ResultadoCalidadChip
import pe.edu.upeu.ecolactmobile.core.ui.components.SectionTitle
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadDashboard
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository

class CalidadInicioViewModel(repository: CalidadRepository) :
    ResourceViewModel<CalidadDashboard>({ repository.dashboard() })

@Composable
fun CalidadInicioScreen(
    viewModel: CalidadInicioViewModel,
    onIrAPendientes: () -> Unit,
    onVerEntrega: (Long) -> Unit,
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
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        if (s.fromCache) OfflineBanner(s.cachedAtIso)

                        KpiRow {
                            KpiCard("🧪", "${data.pendientes_analisis}", "Pendientes", Modifier.weight(1f))
                            KpiCard("✅", "${data.kpis_periodo.analizados}", "Analizadas", Modifier.weight(1f))
                        }
                        KpiRow {
                            KpiCard("🟠", "${data.kpis_periodo.observados}", "Observadas", Modifier.weight(1f))
                            KpiCard("🔴", "${data.kpis_periodo.rechazados}", "Rechazadas", Modifier.weight(1f))
                        }

                        Button(
                            onClick = onIrAPendientes,
                            modifier = Modifier.fillMaxWidth().height(64.dp),
                            shape = RoundedCornerShape(16.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
                        ) {
                            Text("🧪 ANALIZAR ENTREGA · ${data.pendientes_analisis} pendientes")
                        }
                    }
                }

                if (data.pendientes_filas.isNotEmpty()) {
                    item { SectionTitle("Pendientes") }
                    items(data.pendientes_filas.take(5), key = { it.id }) { pendiente ->
                        ListItemCard(onClick = { onVerEntrega(pendiente.id) }) {
                            Text(pendiente.proveedorNombre ?: "Proveedor", style = MaterialTheme.typography.bodyLarge)
                            Text("${pendiente.litros} L · ${pendiente.zonaNombre ?: ""}", color = EcoLactColors.TextoSecundario)
                        }
                    }
                }

                if (data.ultimos_analisis_filas.isNotEmpty()) {
                    item { SectionTitle("Últimos análisis") }
                    items(data.ultimos_analisis_filas, key = { it.id }) { analisis ->
                        ListItemCard {
                            Text(analisis.proveedorNombre ?: "Proveedor", style = MaterialTheme.typography.bodyLarge)
                            ResultadoCalidadChip(analisis.resultado, Modifier.padding(top = 4.dp))
                        }
                    }
                }

                if (data.comunicados_recientes.isNotEmpty()) {
                    item { SectionTitle("Comunicados") }
                    items(data.comunicados_recientes, key = { it.id }) { comunicado ->
                        ListItemCard(onClick = { onVerComunicado(comunicado.id) }) {
                            Text(comunicado.titulo)
                            Text(comunicado.fecha_publicacion, color = EcoLactColors.TextoSecundario)
                        }
                    }
                }
            }
        }
    }
}
