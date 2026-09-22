package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
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
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorPerfil
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository

class ProveedorPerfilViewModel(repository: ProveedorRepository) :
    ResourceViewModel<ProveedorPerfil>({ repository.perfil() })

/**
 * Solo lectura: proveedor.md confirma que no hay `PATCH` para editar teléfono (ni siquiera el
 * panel web lo permite hoy). No se ofrece ninguna edición aquí.
 */
@Composable
fun ProveedorPerfilScreen(viewModel: ProveedorPerfilViewModel, onLogout: () -> Unit) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val data = s.data
            Column(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                if (s.fromCache) OfflineBanner(s.cachedAtIso)
                Text(data.nombre_completo, style = MaterialTheme.typography.titleLarge)
                Text("Proveedor", color = EcoLactColors.TextoSecundario)
                Text("DNI: ${data.dni}", color = EcoLactColors.TextoSecundario)
                data.telefono?.let { Text("Teléfono: $it", color = EcoLactColors.TextoSecundario) }
                Text("Estado: ${data.estado}", color = EcoLactColors.TextoSecundario)

                Button(
                    onClick = onLogout,
                    modifier = Modifier.fillMaxWidth().height(48.dp).padding(top = 24.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.Rojo),
                ) {
                    Text("Cerrar sesión")
                }
            }
        }
    }
}
