package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.components.QrCodeImage
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.proveedor.MiQrResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository

class ProveedorMiQrViewModel(repository: ProveedorRepository) :
    ResourceViewModel<MiQrResponse>({ repository.miQr() })

@Composable
fun ProveedorMiQrScreen(viewModel: ProveedorMiQrViewModel) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val data = s.data
            Column(
                modifier = Modifier.fillMaxSize().padding(24.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                if (s.fromCache) OfflineBanner(s.cachedAtIso)

                QrCodeImage(data.qr_token, Modifier.size(240.dp))

                Text(data.nombre_completo, style = MaterialTheme.typography.titleMedium)
                Text("DNI: ${data.dni}", color = EcoLactColors.TextoSecundario)
                Text("Estado: ${data.estado}", color = EcoLactColors.TextoSecundario)

                Text(
                    "Muestre este código al acopiador para identificar su cuenta durante la entrega.",
                    color = EcoLactColors.TextoSecundario,
                    style = MaterialTheme.typography.bodyMedium,
                )
            }
        }
    }
}
