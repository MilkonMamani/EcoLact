package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.core.util.hoy
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorPerfil
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.EntregaVista
import pe.edu.upeu.ecolactmobile.feature.acopiador.FiltroPeriodo
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.CampoBusqueda
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.EntregaFila
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.FiltrosPeriodo
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.KpiTexto
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.fechaLarga
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.horaOFecha
import pe.edu.upeu.ecolactmobile.feature.acopiador.entregasDelDia
import pe.edu.upeu.ecolactmobile.feature.acopiador.filtrarEntregas
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

/**
 * Historial único del acopiador (servidor + teléfono, sin duplicados). Lo usan Inicio, Entregas
 * y el historial de un proveedor ([proveedorId]). Los filtros se aplican en el teléfono: funcionan
 * igual sin conexión.
 */
class AcopiadorEntregasViewModel(
    repository: AcopiadorRepository,
    isOnline: StateFlow<Boolean>,
    private val proveedorId: Long? = null,
) : LocalFirstViewModel<List<EntregaVista>>(repository.observeEntregas(), isOnline, { repository.refreshEntregas() }) {
    private val _periodo = MutableStateFlow(FiltroPeriodo.TODAS)
    val periodo: StateFlow<FiltroPeriodo> = _periodo.asStateFlow()

    private val _texto = MutableStateFlow("")
    val texto: StateFlow<String> = _texto.asStateFlow()

    val filtradas: StateFlow<List<EntregaVista>> = combine(state, _periodo, _texto) { s, periodo, texto ->
        val todas = (s as? UiState.Success)?.data.orEmpty().filter { proveedorId == null || it.proveedorId == proveedorId }
        filtrarEntregas(todas, periodo, texto, hoy())
    }.stateIn(scope, SharingStarted.Eagerly, emptyList())

    fun onPeriodo(periodo: FiltroPeriodo) { _periodo.value = periodo }
    fun onTexto(texto: String) { _texto.value = texto }
}

private const val ULTIMAS_EN_INICIO = 5

/**
 * Hola, Juan Pérez · HOY · dos tarjetas (leche registrada / entregas realizadas, contando también
 * las pendientes del teléfono) · botón grande "Registrar entrega" · últimas entregas.
 * El botón siempre está, aunque todavía no haya datos guardados.
 */
@Composable
fun AcopiadorInicioScreen(
    viewModel: AcopiadorEntregasViewModel,
    perfil: AcopiadorPerfil?,
    nombreFallback: String,
    onRegistrar: () -> Unit,
    onVerEntrega: (String) -> Unit,
    onVerTodas: () -> Unit,
) {
    val state by viewModel.state.collectAsState()
    val dia = hoy()
    val entregas = (state as? UiState.Success)?.data.orEmpty()
    val deHoy = entregasDelDia(entregas, dia)

    ProveedorPantalla {
        item {
            Column(Modifier.anchoContenido()) {
                Text(
                    "Hola, ${perfil?.nombreCompleto ?: nombreFallback}",
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.SemiBold,
                    color = EcoLactColors.TextoPrincipal,
                )
                Text(
                    listOfNotNull("Acopiador", perfil?.zona_nombre).joinToString(" · "),
                    style = MaterialTheme.typography.bodyMedium,
                    color = EcoLactColors.TextoSecundario,
                )
                (state as? UiState.Success)?.takeIf { it.fromCache && !it.cachedAtIso.isNullOrBlank() }?.let { DatosGuardadosNote(it.cachedAtIso) }
            }
        }
        item {
            Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("HOY", style = MaterialTheme.typography.labelMedium, color = EcoLactColors.TextoSecundario)
                Text(fechaLarga(dia), style = MaterialTheme.typography.titleMedium, color = EcoLactColors.TextoPrincipal)
                Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    KpiTexto("Leche registrada", formatLitros(deHoy.sumOf { it.litros }), Modifier.weight(1f))
                    KpiTexto("Entregas realizadas", "${deHoy.size}", Modifier.weight(1f))
                }
            }
        }
        item { BotonPrincipal("Registrar entrega", onRegistrar, icon = EcoLactIcons.Add, operacion = true) }
        item {
            Row(Modifier.anchoContenido(), verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "Últimas entregas",
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.SemiBold,
                    color = EcoLactColors.TextoPrincipal,
                    modifier = Modifier.weight(1f),
                )
                if (entregas.isNotEmpty()) {
                    TextButton(onClick = onVerTodas) {
                        Text("Ver todas", color = EcoLactColors.VerdeOscuro)
                        Icon(EcoLactIcons.ChevronRight, contentDescription = null, tint = EcoLactColors.VerdeOscuro)
                    }
                }
            }
        }
        when (val s = state) {
            is UiState.Loading -> item {
                Box(Modifier.fillMaxWidth().padding(24.dp), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = EcoLactColors.VerdePrincipal)
                }
            }
            is UiState.Error -> item { EstadoVacio("Todavía no hay entregas guardadas en el teléfono.") }
            is UiState.Success -> {
                val ultimas = s.data.take(ULTIMAS_EN_INICIO)
                if (ultimas.isEmpty()) {
                    item { EstadoVacio("Aún no registras entregas.") }
                } else {
                    itemsIndexed(ultimas, key = { _, e -> e.clave }) { i, e ->
                        EntregaFila(e, primera = i == 0, ultima = i == ultimas.lastIndex, onClick = { onVerEntrega(e.clave) })
                    }
                }
            }
        }
    }
}

/** Historial de entregas: buscar por proveedor o DNI + Todas / Hoy / Esta semana / Este mes. */
@Composable
fun AcopiadorEntregasScreen(viewModel: AcopiadorEntregasViewModel, onVerEntrega: (String) -> Unit) {
    val state by viewModel.state.collectAsState()
    val filtradas by viewModel.filtradas.collectAsState()
    val periodo by viewModel.periodo.collectAsState()
    val texto by viewModel.texto.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { success ->
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
            item {
                Column(Modifier.anchoContenido().padding(bottom = 12.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Historial de entregas", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
                    CampoBusqueda(texto, viewModel::onTexto, "Buscar proveedor o DNI")
                    FiltrosPeriodo(periodo, viewModel::onPeriodo)
                    if (success.fromCache && !success.cachedAtIso.isNullOrBlank()) DatosGuardadosNote(success.cachedAtIso)
                }
            }
            if (filtradas.isEmpty()) {
                item { EstadoVacio(if (success.data.isEmpty()) "Aún no registras entregas." else "No hay entregas con ese filtro.") }
            } else {
                itemsIndexed(filtradas, key = { _, e -> e.clave }) { i, e ->
                    EntregaFila(e, primera = i == 0, ultima = i == filtradas.lastIndex, onClick = { onVerEntrega(e.clave) })
                }
            }
        }
    }
}

/** Historial de un proveedor: misma lista, filas por fecha ("14 oct. · 09:35 / 20 L"). */
@Composable
fun AcopiadorProveedorHistorialScreen(viewModel: AcopiadorEntregasViewModel, nombreProveedor: String, onVerEntrega: (String) -> Unit) {
    val state by viewModel.state.collectAsState()
    val filtradas by viewModel.filtradas.collectAsState()
    val periodo by viewModel.periodo.collectAsState()
    val texto by viewModel.texto.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { _ ->
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
            item {
                Column(Modifier.anchoContenido().padding(bottom = 12.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text(nombreProveedor, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
                    CampoBusqueda(texto, viewModel::onTexto, "Buscar en historial")
                    FiltrosPeriodo(periodo, viewModel::onPeriodo)
                }
            }
            if (filtradas.isEmpty()) {
                item { EstadoVacio("Sin entregas en este periodo.") }
            } else {
                itemsIndexed(filtradas, key = { _, e -> e.clave }) { i, e ->
                    EntregaFila(
                        e,
                        primera = i == 0,
                        ultima = i == filtradas.lastIndex,
                        onClick = { onVerEntrega(e.clave) },
                        titulo = horaOFecha(e.fechaHora),
                        subtitulo = formatLitros(e.litros),
                    )
                }
            }
        }
    }
}
