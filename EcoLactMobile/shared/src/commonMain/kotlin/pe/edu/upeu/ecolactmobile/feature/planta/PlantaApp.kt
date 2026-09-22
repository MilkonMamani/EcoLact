package pe.edu.upeu.ecolactmobile.feature.planta

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.IconButton
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.session.UserSession
import pe.edu.upeu.ecolactmobile.core.ui.navigation.Navigator
import pe.edu.upeu.ecolactmobile.core.ui.state.rememberScreenModel
import pe.edu.upeu.ecolactmobile.di.AppContainer
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesApi
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesScreen
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.IniciarProduccionScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.IniciarProduccionViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.InsumoDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.InsumoDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.LoteDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.LoteDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.LotePendienteDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.LotePendienteDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.MovimientoFormViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.NuevoInsumoScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.NuevoInsumoViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaInicioScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaInicioViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaInsumosViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaInventarioScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaMasScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaPerfilScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaProduccionScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaProduccionViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaProductoDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaProductoDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaProductosViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaSincronizacionScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaSincronizacionViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.RegistrarResultadoScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.RegistrarResultadoViewModel

private val BOTTOM_TABS = listOf(
    Triple(PlantaRoute.Inicio, "🏠", "Inicio"),
    Triple(PlantaRoute.Produccion, "🏭", "Producción"),
    Triple(PlantaRoute.Inventario, "📦", "Inventario"),
    Triple(PlantaRoute.Mas, "☰", "Más"),
)

@Composable
fun PlantaApp(container: AppContainer, session: UserSession) {
    val repository = remember { PlantaRepository(container.plantaApi, container.cacheRepository, container.syncQueueRepository, container.connectivityObserver) }
    val notificacionesApi = remember { NotificacionesApi(container.httpClient) }
    val navigator = remember { Navigator<PlantaRoute>(PlantaRoute.Inicio) }
    val isOnline by container.connectivityObserver.isOnline.collectAsState()

    val current = navigator.current
    val isTopLevel = current in listOf(PlantaRoute.Inicio, PlantaRoute.Produccion, PlantaRoute.Inventario, PlantaRoute.Mas)

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(topBarTitle(current)) },
                navigationIcon = {
                    if (!isTopLevel) IconButton(onClick = { navigator.pop() }) { Text("←") }
                },
                actions = {
                    Row(modifier = Modifier.padding(end = 8.dp)) {
                        Text(if (isOnline) "● En línea" else "● Sin conexión", modifier = Modifier.padding(top = 14.dp, end = 8.dp))
                        IconButton(onClick = { navigator.navigate(PlantaRoute.Sincronizacion) }) { Text("↻") }
                        IconButton(onClick = { navigator.navigate(PlantaRoute.Notificaciones) }) { Text("🔔") }
                    }
                },
            )
        },
        bottomBar = {
            if (isTopLevel) {
                NavigationBar {
                    BOTTOM_TABS.forEach { (route, icon, label) ->
                        NavigationBarItem(
                            selected = current == route,
                            onClick = { navigator.replaceAll(route) },
                            icon = { Text(icon) },
                            label = { Text(label) },
                        )
                    }
                }
            }
        },
    ) { padding ->
        Box(modifier = Modifier.fillMaxSize().padding(padding)) {
            when (val route = current) {
                PlantaRoute.Inicio -> {
                    val vm = rememberScreenModel { PlantaInicioViewModel(repository) }
                    PlantaInicioScreen(
                        viewModel = vm,
                        onRegistrarProduccion = { navigator.navigate(PlantaRoute.IniciarProduccion) },
                        onVerLote = { navigator.navigate(PlantaRoute.LoteDetalle(it)) },
                        onVerInsumo = { navigator.navigate(PlantaRoute.InsumoDetalle(it)) },
                    )
                }
                PlantaRoute.Produccion -> {
                    val vm = rememberScreenModel { PlantaProduccionViewModel(repository) }
                    PlantaProduccionScreen(
                        viewModel = vm,
                        onVerLote = { navigator.navigate(PlantaRoute.LoteDetalle(it)) },
                        onVerLotePendiente = { navigator.navigate(PlantaRoute.LotePendienteDetalle(it)) },
                    )
                }
                PlantaRoute.IniciarProduccion -> {
                    val vm = rememberScreenModel { IniciarProduccionViewModel(repository) }
                    IniciarProduccionScreen(
                        viewModel = vm,
                        onVerLote = { navigator.replaceAll(PlantaRoute.LoteDetalle(it)) },
                        onVerLotePendiente = { navigator.replaceAll(PlantaRoute.LotePendienteDetalle(it)) },
                    )
                }
                is PlantaRoute.LoteDetalle -> {
                    val vm = rememberScreenModel(route.id) { LoteDetalleViewModel(repository, route.id) }
                    LoteDetalleScreen(vm, repository) { navigator.navigate(PlantaRoute.RegistrarResultado(it)) }
                }
                is PlantaRoute.LotePendienteDetalle -> {
                    val vm = rememberScreenModel(route.localId) { LotePendienteDetalleViewModel(repository, route.localId) }
                    LotePendienteDetalleScreen(vm, container.syncQueueRepository) { navigator.pop() }
                }
                is PlantaRoute.RegistrarResultado -> {
                    val vm = rememberScreenModel(route.loteId) { RegistrarResultadoViewModel(repository, route.loteId) }
                    RegistrarResultadoScreen(vm) { navigator.pop() }
                }
                PlantaRoute.Inventario -> {
                    val insumosVm = rememberScreenModel { PlantaInsumosViewModel(repository) }
                    val productosVm = rememberScreenModel { PlantaProductosViewModel(repository) }
                    PlantaInventarioScreen(
                        insumosViewModel = insumosVm,
                        productosViewModel = productosVm,
                        onVerInsumo = { navigator.navigate(PlantaRoute.InsumoDetalle(it)) },
                        onNuevoInsumo = { navigator.navigate(PlantaRoute.NuevoInsumo) },
                        onVerProducto = { navigator.navigate(PlantaRoute.ProductoDetalle(it)) },
                    )
                }
                is PlantaRoute.InsumoDetalle -> {
                    val vm = rememberScreenModel(route.id) { InsumoDetalleViewModel(repository, route.id) }
                    val formVm = rememberScreenModel(route.id) { MovimientoFormViewModel(repository, route.id) { vm.refresh() } }
                    InsumoDetalleScreen(vm, formVm)
                }
                PlantaRoute.NuevoInsumo -> {
                    val vm = rememberScreenModel { NuevoInsumoViewModel(repository) }
                    NuevoInsumoScreen(vm) { navigator.pop() }
                }
                is PlantaRoute.ProductoDetalle -> {
                    val vm = rememberScreenModel(route.id) { PlantaProductoDetalleViewModel(repository, route.id) }
                    PlantaProductoDetalleScreen(vm)
                }
                PlantaRoute.Mas -> PlantaMasScreen { navigator.navigate(it) }
                PlantaRoute.Sincronizacion -> {
                    val vm = rememberScreenModel { PlantaSincronizacionViewModel(repository, container.syncManager, container.connectivityObserver) }
                    PlantaSincronizacionScreen(vm)
                }
                PlantaRoute.Perfil -> PlantaPerfilScreen(session) { container.sessionManager.clear() }
                PlantaRoute.Notificaciones -> {
                    val vm = rememberScreenModel { NotificacionesViewModel(notificacionesApi, container.cacheRepository) }
                    NotificacionesScreen(vm) { navigator.pop() }
                }
            }
        }
    }
}

private fun topBarTitle(route: PlantaRoute): String = when (route) {
    PlantaRoute.Inicio -> "Ecolácteos Huata"
    PlantaRoute.Produccion -> "Producción"
    PlantaRoute.IniciarProduccion -> "Registrar producción"
    is PlantaRoute.LoteDetalle -> "Detalle de lote"
    is PlantaRoute.LotePendienteDetalle -> "Lote pendiente"
    is PlantaRoute.RegistrarResultado -> "Registrar resultado"
    PlantaRoute.Inventario -> "Inventario"
    is PlantaRoute.InsumoDetalle -> "Insumo"
    PlantaRoute.NuevoInsumo -> "Nuevo insumo"
    is PlantaRoute.ProductoDetalle -> "Producto"
    PlantaRoute.Mas -> "Más"
    PlantaRoute.Sincronizacion -> "Sincronización"
    PlantaRoute.Perfil -> "Mi perfil"
    PlantaRoute.Notificaciones -> "Notificaciones"
}
