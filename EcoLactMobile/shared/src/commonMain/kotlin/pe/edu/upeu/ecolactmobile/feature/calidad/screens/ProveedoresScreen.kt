package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadProveedoresListResponse
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.CargandoBox
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoError
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

class CalidadProveedoresViewModel(private val repository: CalidadRepository) : ScreenModel() {
    private val _state = MutableStateFlow<UiState<CalidadProveedoresListResponse>>(UiState.Loading)
    val state: StateFlow<UiState<CalidadProveedoresListResponse>> = _state.asStateFlow()

    private val _q = MutableStateFlow("")
    val q: StateFlow<String> = _q.asStateFlow()

    init { load() }

    fun onQChange(value: String) {
        _q.value = value
        load()
    }

    fun load() {
        scope.launch {
            _state.value = UiState.Loading
            try {
                when (val outcome = repository.proveedores(q = _q.value.ifBlank { null })) {
                    is CacheOutcome.Fresh -> _state.value = UiState.Success(outcome.data, false)
                    is CacheOutcome.Stale -> _state.value = UiState.Success(outcome.data, true, outcome.cachedAtIso)
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "No se pudieron cargar los proveedores")
            }
        }
    }
}

@Composable
fun CalidadProveedoresScreen(viewModel: CalidadProveedoresViewModel, onVerProveedor: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()
    val q by viewModel.q.collectAsState()
    val s = state

    ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
        item {
            OutlinedTextField(
                value = q,
                onValueChange = viewModel::onQChange,
                placeholder = { Text("Buscar proveedor o DNI...") },
                leadingIcon = { Icon(EcoLactIcons.Search, contentDescription = null) },
                singleLine = true,
                shape = RoundedCornerShape(12.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedContainerColor = EcoLactColors.Blanco,
                    unfocusedContainerColor = EcoLactColors.Blanco,
                    focusedBorderColor = EcoLactColors.VerdePrincipal,
                    unfocusedBorderColor = EcoLactColors.Bordes,
                ),
                modifier = Modifier.anchoContenido().padding(bottom = 12.dp),
            )
        }
        when (s) {
            is UiState.Loading -> item { CargandoBox() }
            is UiState.Error -> item { EstadoError(s.message, viewModel::load) }
            is UiState.Success -> {
                val filas = s.data.filas
                if (s.fromCache) item { DatosGuardadosNote(s.cachedAtIso, Modifier.anchoContenido().padding(bottom = 8.dp)) }
                if (filas.isEmpty()) item { EstadoVacio("No se encontraron proveedores.") }
                itemsIndexed(filas, key = { _, p -> p.id }) { i, proveedor ->
                    ProviderListItem(primera = i == 0, ultima = i == filas.lastIndex, onClick = { onVerProveedor(proveedor.id) }) {
                        ItemTitulo(proveedor.nombre)
                        ItemSubtitulo(listOfNotNull(proveedor.zonaNombre, "DNI ${proveedor.dni}").joinToString(" · "))
                        if (proveedor.advertencias > 0) {
                            StatusChip("${proveedor.advertencias} advertencias", Tono.ATENCION, Modifier.padding(top = 4.dp))
                        }
                    }
                }
            }
        }
    }
}
