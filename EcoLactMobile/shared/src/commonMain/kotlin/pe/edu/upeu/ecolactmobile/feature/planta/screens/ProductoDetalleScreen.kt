package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
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
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.ProductoDetalleResponse

class PlantaProductoDetalleViewModel(repository: PlantaRepository, productoId: Long) :
    ResourceViewModel<ProductoDetalleResponse>({ repository.productoDetalle(productoId) })

/** Solo lectura: el ajuste de inventario de productos es de VENTAS (encargado-planta.md sección 5). */
@Composable
fun PlantaProductoDetalleScreen(viewModel: PlantaProductoDetalleViewModel) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val data = s.data
            Column(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                if (s.fromCache) OfflineBanner(s.cachedAtIso)
                Text(data.nombre, style = MaterialTheme.typography.titleLarge)
                Text("${data.cantidad_actual} ${data.unidad_medida ?: ""}", style = MaterialTheme.typography.titleMedium)

                if (data.movimientos.isNotEmpty()) {
                    SectionTitle("Movimientos recientes")
                    data.movimientos.forEach {
                        Text("${it.fecha} · ${it.tipo} ${it.cantidad} · ${it.origen ?: ""}", color = EcoLactColors.TextoSecundario)
                    }
                }
            }
        }
    }
}
