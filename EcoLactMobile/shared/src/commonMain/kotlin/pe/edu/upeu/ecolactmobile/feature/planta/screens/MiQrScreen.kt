package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.initials
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaPerfil
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.InitialsAvatar
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.trabajador.MiQrTrabajadorContenido

/** Perfil + QR guardados en SQLite: "Mi QR" se dibuja sin conexión, sin pedir nada a la API. */
class PlantaPerfilViewModel(repository: PlantaRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<PlantaPerfil>(repository.observePerfil(), isOnline, { repository.refreshPerfil() })

fun nombreCompleto(perfil: PlantaPerfil?, fallback: String): String =
    perfil?.let { listOfNotNull(it.nombres, it.apellidos).joinToString(" ").trim() }?.ifBlank { null } ?: fallback

/**
 * Solo MUESTRA el QR del trabajador (mismo diseño que Acopiador y Calidad): la asistencia la
 * registra el ADMINISTRADOR escaneándolo. Aquí no existe ninguna marcación.
 */
@Composable
fun PlantaMiQrScreen(viewModel: PlantaPerfilViewModel, nombreFallback: String) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val perfil = s.data
        val nombre = nombreCompleto(perfil, nombreFallback)
        MiQrTrabajadorContenido(
            nombre = nombre,
            iniciales = initials(perfil.nombres, perfil.apellidos, nombre),
            rol = perfil.rol,
            dni = perfil.dni,
            qrToken = perfil.qr_token,
            sinQr = "Todavía no tienes un QR de asistencia. Pídelo al administrador.",
        )
    }
}

@Composable
fun PlantaPerfilScreen(viewModel: PlantaPerfilViewModel, nombreFallback: String, usuario: String, onLogout: () -> Unit) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val perfil = s.data
        val nombre = nombreCompleto(perfil, nombreFallback)
        ProveedorPantalla {
            item {
                Column(Modifier.anchoContenido().padding(vertical = 8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    InitialsAvatar(initials(perfil.nombres, perfil.apellidos, nombre), large = true)
                    Text(nombre, style = MaterialTheme.typography.titleLarge, modifier = Modifier.padding(top = 12.dp))
                    Text(perfil.rol, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                }
            }
            item {
                SummaryCard {
                    SummaryRow("Usuario", perfil.username ?: usuario, primera = true)
                    perfil.dni?.let { SummaryRow("DNI", it) }
                    perfil.telefono?.let { SummaryRow("Teléfono", it) }
                    SummaryRow("Rol", perfil.rol)
                }
            }
            item { BotonSecundario("Cerrar sesión", onLogout) }
        }
    }
}
