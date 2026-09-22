package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.ChipTone
import pe.edu.upeu.ecolactmobile.core.ui.components.EmptyBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ListItemCard
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.components.StatusChip
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.planta.InsumosListResponse
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.ProductosListResponse

class PlantaInsumosViewModel(repository: PlantaRepository) :
    ResourceViewModel<InsumosListResponse>({ repository.insumos() })

class PlantaProductosViewModel(repository: PlantaRepository) :
    ResourceViewModel<ProductosListResponse>({ repository.productos() })

@Composable
fun PlantaInventarioScreen(
    insumosViewModel: PlantaInsumosViewModel,
    productosViewModel: PlantaProductosViewModel,
    onVerInsumo: (Long) -> Unit,
    onNuevoInsumo: () -> Unit,
    onVerProducto: (Long) -> Unit,
) {
    var tab by remember { mutableStateOf(0) }

    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            FilterChip(selected = tab == 0, onClick = { tab = 0 }, label = { Text("Insumos") })
            FilterChip(selected = tab == 1, onClick = { tab = 1 }, label = { Text("Productos") })
        }

        if (tab == 0) {
            Button(
                onClick = onNuevoInsumo,
                modifier = Modifier.fillMaxWidth().height(44.dp).padding(vertical = 8.dp),
                colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
            ) { Text("+ Registrar insumo") }

            val state by insumosViewModel.state.collectAsState()
            when (val s = state) {
                is UiState.Loading -> LoadingBox()
                is UiState.Error -> ErrorBox(s.message, onRetry = insumosViewModel::refresh)
                is UiState.Success -> {
                    Column {
                        if (s.fromCache) OfflineBanner(s.cachedAtIso, Modifier.padding(bottom = 12.dp))
                        if (s.data.filas.isEmpty()) {
                            EmptyBox("No hay insumos registrados")
                        } else {
                            LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                items(s.data.filas, key = { it.id }) { insumo ->
                                    ListItemCard(onClick = { onVerInsumo(insumo.id) }) {
                                        Text(insumo.nombre, style = MaterialTheme.typography.bodyLarge)
                                        Text("${insumo.stock_actual} ${insumo.unidad_medida ?: ""}", color = EcoLactColors.TextoSecundario)
                                        StatusChip(
                                            if (insumo.esta_bajo_minimo) "🔴 Bajo" else "🟢 Normal",
                                            if (insumo.esta_bajo_minimo) ChipTone.ERROR else ChipTone.EXITO,
                                            Modifier.padding(top = 4.dp),
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            val state by productosViewModel.state.collectAsState()
            when (val s = state) {
                is UiState.Loading -> LoadingBox()
                is UiState.Error -> ErrorBox(s.message, onRetry = productosViewModel::refresh)
                is UiState.Success -> {
                    Column(modifier = Modifier.padding(top = 8.dp)) {
                        if (s.fromCache) OfflineBanner(s.cachedAtIso, Modifier.padding(bottom = 12.dp))
                        if (s.data.filas.isEmpty()) {
                            EmptyBox("No hay productos registrados")
                        } else {
                            LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                items(s.data.filas, key = { it.id }) { producto ->
                                    ListItemCard(onClick = { onVerProducto(producto.id) }) {
                                        Text(producto.nombre, style = MaterialTheme.typography.bodyLarge)
                                        Text(
                                            "${producto.cantidad_actual} ${producto.unidad_medida ?: ""}",
                                            color = if (producto.sin_stock) EcoLactColors.Rojo else EcoLactColors.TextoSecundario,
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
