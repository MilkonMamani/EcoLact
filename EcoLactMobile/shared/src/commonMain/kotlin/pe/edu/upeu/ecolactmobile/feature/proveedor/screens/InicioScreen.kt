package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.components.QrCodeImage
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.core.util.formatSoles
import pe.edu.upeu.ecolactmobile.core.util.primerNombre
import pe.edu.upeu.ecolactmobile.feature.proveedor.MiQrResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorDashboard
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorPerfil
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SectionHeader
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco

/** QR + resumen semanal + datos para el saludo. Cualquiera puede faltar sin tumbar la pantalla. */
data class ProveedorInicioData(val qr: MiQrResponse?, val dashboard: ProveedorDashboard?, val perfil: ProveedorPerfil?)

class ProveedorInicioViewModel(repository: ProveedorRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<ProveedorInicioData>(
        source = combine(repository.observeQr(), repository.observeDashboard(), repository.observePerfil()) { qr, dashboard, perfil ->
            val partes = listOfNotNull(qr, dashboard)
            if (partes.isEmpty()) return@combine null
            // La hora más antigua es la más honesta para "Datos guardados · …".
            CachedValue(ProveedorInicioData(qr?.value, dashboard?.value, perfil?.value), partes.minOf { it.cachedAtIso })
        },
        isOnline = isOnline,
        refresher = {
            repository.refreshQr()
            repository.refreshDashboard()
        },
    )

@Composable
fun ProveedorInicioScreen(viewModel: ProveedorInicioViewModel, nombreFallback: String) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val (qr, dashboard, perfil) = s.data
        val nombre = perfil?.nombres?.let(::primerNombre) ?: primerNombre(qr?.nombre_completo ?: nombreFallback)
        val zona = perfil?.zona_nombre ?: qr?.zona_nombre

        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(20.dp)) {
            item {
                Column(Modifier.anchoContenido()) {
                    Text(
                        "Hola, $nombre",
                        style = MaterialTheme.typography.titleLarge,
                        color = EcoLactColors.TextoPrincipal,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                    Text(
                        listOfNotNull("Proveedor", zona).joinToString(" · "),
                        style = MaterialTheme.typography.bodyMedium,
                        color = EcoLactColors.TextoSecundario,
                    )
                }
            }
            item { ProveedorQrSection(qr) }
            item { WeeklySummary(dashboard) }
            if (s.fromCache) {
                item { DatosGuardadosNote(s.cachedAtIso, Modifier.anchoContenido()) }
            }
        }
    }
}

/** El QR es lo principal de Inicio: ~55% del ancho disponible, con mínimo y máximo para no romper ningún teléfono. */
@Composable
private fun ProveedorQrSection(qr: MiQrResponse?) {
    Column(
        modifier = Modifier.anchoContenido().contenedorBlanco().padding(horizontal = 20.dp, vertical = 20.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text(
            "Mi código QR",
            style = MaterialTheme.typography.titleSmall,
            fontWeight = FontWeight.SemiBold,
            color = EcoLactColors.TextoPrincipal,
            modifier = Modifier.fillMaxWidth(),
        )
        if (qr == null) {
            Text(
                "Tu código QR se descargará la próxima vez que tengas conexión.",
                style = MaterialTheme.typography.bodyMedium,
                color = EcoLactColors.TextoSecundario,
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(vertical = 24.dp),
            )
            return@Column
        }
        BoxWithConstraints(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
            val lado = (maxWidth * 0.55f).coerceIn(150.dp, 280.dp)
            QrCodeImage(qr.qr_token, Modifier.size(lado).semantics { contentDescription = "Código QR para registrar tu entrega" })
        }
        Text("DNI ${qr.dni}", style = MaterialTheme.typography.bodyLarge, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
        Text(
            "Muéstralo al acopiador para registrar tu entrega.",
            style = MaterialTheme.typography.bodyMedium,
            color = EcoLactColors.TextoSecundario,
            textAlign = TextAlign.Center,
        )
    }
}

@Composable
private fun WeeklySummary(dashboard: ProveedorDashboard?) {
    Column(Modifier.anchoContenido()) {
        SectionHeader("Esta semana")
        if (dashboard == null) {
            Text(
                "El resumen se mostrará cuando haya conexión.",
                style = MaterialTheme.typography.bodyMedium,
                color = EcoLactColors.TextoSecundario,
            )
            return@Column
        }
        SummaryCard {
            SummaryRow("Litros entregados", formatLitros(dashboard.kpis.litros), primera = true)
            SummaryRow("Entregas registradas", "${dashboard.kpis.entregas}")
            SummaryRow("Pago pendiente", formatSoles(dashboard.por_cobrar), destacado = true)
        }
    }
}
