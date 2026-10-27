package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.initials
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorPerfil
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.InitialsAvatar
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRowSlot
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

class ProveedorPerfilViewModel(repository: ProveedorRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<ProveedorPerfil>(repository.observePerfil(), isOnline, { repository.refreshPerfil() })

/**
 * Solo lectura: el backend no expone edición de datos propios (PerfilController no tiene PATCH).
 * Cerrar sesión vive en el menú lateral.
 */
@Composable
fun ProveedorPerfilScreen(viewModel: ProveedorPerfilViewModel) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val data = s.data
        ProveedorPantalla {
            item {
                Column(
                    modifier = Modifier.anchoContenido(),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    InitialsAvatar(initials(data.nombres, data.apellidos, data.nombre_completo), large = true)
                    Text(
                        data.nombre_completo,
                        style = MaterialTheme.typography.titleMedium,
                        color = EcoLactColors.TextoPrincipal,
                        textAlign = TextAlign.Center,
                    )
                }
            }
            item {
                SummaryCard {
                    SummaryRow("Nombre", data.nombre_completo, primera = true)
                    SummaryRow("DNI", data.dni)
                    data.zona_nombre?.let { SummaryRow("Zona", it) }
                    SummaryRowSlot("Estado") {
                        StatusChip(
                            data.estado.lowercase().replaceFirstChar { it.uppercase() },
                            if (data.estado == "ACTIVO") Tono.EXITO else Tono.NEUTRAL,
                        )
                    }
                    data.telefono?.takeIf { it.isNotBlank() }?.let { SummaryRow("Teléfono", it) }
                }
            }
        }
    }
}
