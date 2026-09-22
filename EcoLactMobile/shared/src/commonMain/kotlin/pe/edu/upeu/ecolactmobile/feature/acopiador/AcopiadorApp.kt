package pe.edu.upeu.ecolactmobile.feature.acopiador

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
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorComunicadoDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorComunicadoDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorComunicadosScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorComunicadosViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorEntregaDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorEntregaDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorEntregasScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorEntregasViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorInicioScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorInicioViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorMasScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorPerfilScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorProveedorDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorProveedorDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorProveedoresScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorProveedoresViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorRegistrarScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.EntregaPendienteDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.EntregaPendienteDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.RegistrarViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.SeguimientoDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.SeguimientoDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.SincronizacionScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.SincronizacionViewModel
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesApi
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesScreen
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesViewModel

private val BOTTOM_TABS = listOf(
    Triple(AcopiadorRoute.Inicio, "🏠", "Inicio"),
    Triple(AcopiadorRoute.Registrar, "＋", "Registrar"),
    Triple(AcopiadorRoute.Entregas, "🚚", "Entregas"),
    Triple(AcopiadorRoute.Mas, "☰", "Más"),
)

@Composable
fun AcopiadorApp(container: AppContainer, session: UserSession) {
    val repository = remember {
        AcopiadorRepository(container.acopiadorApi, container.cacheRepository, container.syncQueueRepository, container.connectivityObserver)
    }
    val notificacionesApi = remember { NotificacionesApi(container.httpClient) }
    val navigator = remember { Navigator<AcopiadorRoute>(AcopiadorRoute.Inicio) }
    val isOnline by container.connectivityObserver.isOnline.collectAsState()

    val current = navigator.current
    val isTopLevel = current in listOf(AcopiadorRoute.Inicio, AcopiadorRoute.Registrar, AcopiadorRoute.Entregas, AcopiadorRoute.Mas)

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(topBarTitle(current)) },
                navigationIcon = {
                    if (!isTopLevel) {
                        IconButton(onClick = { navigator.pop() }) { Text("←") }
                    }
                },
                actions = {
                    Row(modifier = Modifier.padding(end = 8.dp)) {
                        Text(if (isOnline) "● En línea" else "● Sin conexión", modifier = Modifier.padding(top = 14.dp, end = 8.dp))
                        IconButton(onClick = { navigator.navigate(AcopiadorRoute.Sincronizacion) }) { Text("↻") }
                        IconButton(onClick = { navigator.navigate(AcopiadorRoute.Notificaciones) }) { Text("🔔") }
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
                AcopiadorRoute.Inicio -> {
                    val vm = rememberScreenModel { AcopiadorInicioViewModel(repository) }
                    AcopiadorInicioScreen(
                        viewModel = vm,
                        onRegistrar = { navigator.replaceAll(AcopiadorRoute.Registrar) },
                        onVerEntregaPendiente = { navigator.navigate(AcopiadorRoute.EntregaPendienteDetalle(it)) },
                        onVerEntrega = { navigator.navigate(AcopiadorRoute.EntregaDetalle(it)) },
                    )
                }
                AcopiadorRoute.Registrar -> {
                    val vm = rememberScreenModel { RegistrarViewModel(repository) }
                    AcopiadorRegistrarScreen(
                        viewModel = vm,
                        onVerEntrega = { navigator.navigate(AcopiadorRoute.EntregaDetalle(it)) },
                        onVerEntregaPendiente = { navigator.navigate(AcopiadorRoute.EntregaPendienteDetalle(it)) },
                    )
                }
                AcopiadorRoute.Entregas -> {
                    val vm = rememberScreenModel { AcopiadorEntregasViewModel(repository) }
                    AcopiadorEntregasScreen(
                        viewModel = vm,
                        onVerEntrega = { navigator.navigate(AcopiadorRoute.EntregaDetalle(it)) },
                        onVerEntregaPendiente = { navigator.navigate(AcopiadorRoute.EntregaPendienteDetalle(it)) },
                    )
                }
                is AcopiadorRoute.EntregaDetalle -> {
                    val vm = rememberScreenModel(route.id) { AcopiadorEntregaDetalleViewModel(repository, route.id) }
                    AcopiadorEntregaDetalleScreen(vm) { navigator.navigate(AcopiadorRoute.SeguimientoDetalle(it)) }
                }
                is AcopiadorRoute.EntregaPendienteDetalle -> {
                    val vm = rememberScreenModel(route.localId) { EntregaPendienteDetalleViewModel(repository, route.localId) }
                    EntregaPendienteDetalleScreen(vm) { navigator.pop() }
                }
                is AcopiadorRoute.SeguimientoDetalle -> {
                    val vm = rememberScreenModel(route.entregaId) { SeguimientoDetalleViewModel(repository, route.entregaId) }
                    SeguimientoDetalleScreen(vm)
                }
                AcopiadorRoute.Mas -> AcopiadorMasScreen { navigator.navigate(it) }
                AcopiadorRoute.Proveedores -> {
                    val vm = rememberScreenModel { AcopiadorProveedoresViewModel(repository) }
                    AcopiadorProveedoresScreen(vm) { navigator.navigate(AcopiadorRoute.ProveedorDetalle(it)) }
                }
                is AcopiadorRoute.ProveedorDetalle -> {
                    val vm = rememberScreenModel(route.id) { AcopiadorProveedorDetalleViewModel(repository, route.id) }
                    AcopiadorProveedorDetalleScreen(vm) { navigator.replaceAll(AcopiadorRoute.Registrar) }
                }
                AcopiadorRoute.Comunicados -> {
                    val vm = rememberScreenModel { AcopiadorComunicadosViewModel(repository) }
                    AcopiadorComunicadosScreen(vm) { navigator.navigate(AcopiadorRoute.ComunicadoDetalle(it)) }
                }
                is AcopiadorRoute.ComunicadoDetalle -> {
                    val vm = rememberScreenModel(route.id) { AcopiadorComunicadoDetalleViewModel(repository, route.id) }
                    AcopiadorComunicadoDetalleScreen(vm)
                }
                AcopiadorRoute.Sincronizacion -> {
                    val vm = rememberScreenModel {
                        SincronizacionViewModel(repository, container.syncManager, container.connectivityObserver)
                    }
                    SincronizacionScreen(vm) { navigator.navigate(AcopiadorRoute.EntregaPendienteDetalle(it)) }
                }
                AcopiadorRoute.Perfil -> AcopiadorPerfilScreen(session) { container.sessionManager.clear() }
                AcopiadorRoute.Notificaciones -> {
                    val vm = rememberScreenModel { NotificacionesViewModel(notificacionesApi, container.cacheRepository) }
                    NotificacionesScreen(vm) { navigator.pop() }
                }
            }
        }
    }
}

private fun topBarTitle(route: AcopiadorRoute): String = when (route) {
    AcopiadorRoute.Inicio -> "Ecolácteos Huata"
    AcopiadorRoute.Registrar -> "Registrar entrega"
    AcopiadorRoute.Entregas -> "Mis entregas"
    is AcopiadorRoute.EntregaDetalle -> "Detalle de entrega"
    is AcopiadorRoute.EntregaPendienteDetalle -> "Entrega pendiente"
    is AcopiadorRoute.SeguimientoDetalle -> "Resultado de calidad"
    AcopiadorRoute.Mas -> "Más"
    AcopiadorRoute.Proveedores -> "Proveedores de mi zona"
    is AcopiadorRoute.ProveedorDetalle -> "Proveedor"
    AcopiadorRoute.Comunicados -> "Comunicados"
    is AcopiadorRoute.ComunicadoDetalle -> "Comunicado"
    AcopiadorRoute.Sincronizacion -> "Sincronización"
    AcopiadorRoute.Perfil -> "Mi perfil"
    AcopiadorRoute.Notificaciones -> "Notificaciones"
}
