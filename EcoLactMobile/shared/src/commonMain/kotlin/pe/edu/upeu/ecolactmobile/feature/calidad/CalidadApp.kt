package pe.edu.upeu.ecolactmobile.feature.calidad

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
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.AnalizarScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.AnalizarViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadComunicadoDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadComunicadoDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadComunicadosScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadComunicadosViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadHistorialDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadHistorialDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadHistorialScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadHistorialViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadInicioScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadInicioViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadMasScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadPendientesScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadPendientesViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadPerfilScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadProveedorDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadProveedorDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadProveedoresScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadProveedoresViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadSincronizacionScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadSincronizacionViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.MuestreoScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.MuestreoViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.NuevoComunicadoScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.NuevoComunicadoViewModel
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesApi
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesScreen
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesViewModel

private val BOTTOM_TABS = listOf(
    Triple(CalidadRoute.Inicio, "🏠", "Inicio"),
    Triple(CalidadRoute.Pendientes, "🧪", "Pendientes"),
    Triple(CalidadRoute.Historial, "📋", "Historial"),
    Triple(CalidadRoute.Mas, "☰", "Más"),
)

@Composable
fun CalidadApp(container: AppContainer, session: UserSession) {
    val repository = remember {
        CalidadRepository(container.calidadApi, container.cacheRepository, container.syncQueueRepository, container.connectivityObserver, container.database)
    }
    val notificacionesApi = remember { NotificacionesApi(container.httpClient) }
    val navigator = remember { Navigator<CalidadRoute>(CalidadRoute.Inicio) }
    val isOnline by container.connectivityObserver.isOnline.collectAsState()

    val current = navigator.current
    val isTopLevel = current in listOf(CalidadRoute.Inicio, CalidadRoute.Pendientes, CalidadRoute.Historial, CalidadRoute.Mas)

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
                        IconButton(onClick = { navigator.navigate(CalidadRoute.Sincronizacion) }) { Text("↻") }
                        IconButton(onClick = { navigator.navigate(CalidadRoute.Notificaciones) }) { Text("🔔") }
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
                CalidadRoute.Inicio -> {
                    val vm = rememberScreenModel { CalidadInicioViewModel(repository) }
                    CalidadInicioScreen(
                        viewModel = vm,
                        onIrAPendientes = { navigator.replaceAll(CalidadRoute.Pendientes) },
                        onVerEntrega = { navigator.navigate(CalidadRoute.Analizar(it)) },
                        onVerComunicado = { navigator.navigate(CalidadRoute.ComunicadoDetalle(it)) },
                    )
                }
                CalidadRoute.Pendientes -> {
                    val vm = rememberScreenModel { CalidadPendientesViewModel(repository) }
                    CalidadPendientesScreen(vm) { navigator.navigate(CalidadRoute.Analizar(it)) }
                }
                is CalidadRoute.Analizar -> {
                    val vm = rememberScreenModel(route.entregaId) { AnalizarViewModel(repository, route.entregaId) }
                    AnalizarScreen(vm) { navigator.navigate(CalidadRoute.HistorialDetalle(it)) }
                }
                CalidadRoute.Historial -> {
                    val vm = rememberScreenModel { CalidadHistorialViewModel(repository) }
                    CalidadHistorialScreen(vm) { navigator.navigate(CalidadRoute.HistorialDetalle(it)) }
                }
                is CalidadRoute.HistorialDetalle -> {
                    val vm = rememberScreenModel(route.analisisId) { CalidadHistorialDetalleViewModel(repository, route.analisisId) }
                    CalidadHistorialDetalleScreen(vm)
                }
                CalidadRoute.Mas -> CalidadMasScreen { navigator.navigate(it) }
                CalidadRoute.Muestreo -> {
                    val vm = rememberScreenModel { MuestreoViewModel(repository) }
                    MuestreoScreen(vm, onVerHistorialDetalle = { navigator.navigate(CalidadRoute.Analizar(it)) }, onListo = { navigator.pop() })
                }
                CalidadRoute.Proveedores -> {
                    val vm = rememberScreenModel { CalidadProveedoresViewModel(repository) }
                    CalidadProveedoresScreen(vm) { navigator.navigate(CalidadRoute.ProveedorDetalle(it)) }
                }
                is CalidadRoute.ProveedorDetalle -> {
                    val vm = rememberScreenModel(route.id) { CalidadProveedorDetalleViewModel(repository, route.id) }
                    CalidadProveedorDetalleScreen(vm)
                }
                CalidadRoute.Comunicados -> {
                    val vm = rememberScreenModel { CalidadComunicadosViewModel(repository) }
                    CalidadComunicadosScreen(
                        viewModel = vm,
                        onVerComunicado = { navigator.navigate(CalidadRoute.ComunicadoDetalle(it)) },
                        onNuevo = { navigator.navigate(CalidadRoute.NuevoComunicado) },
                    )
                }
                is CalidadRoute.ComunicadoDetalle -> {
                    val vm = rememberScreenModel(route.id) { CalidadComunicadoDetalleViewModel(repository, route.id) }
                    CalidadComunicadoDetalleScreen(vm)
                }
                CalidadRoute.NuevoComunicado -> {
                    val vm = rememberScreenModel { NuevoComunicadoViewModel(repository) }
                    NuevoComunicadoScreen(vm) { navigator.pop() }
                }
                CalidadRoute.Sincronizacion -> {
                    val vm = rememberScreenModel {
                        CalidadSincronizacionViewModel(repository, container.syncManager, container.connectivityObserver)
                    }
                    CalidadSincronizacionScreen(vm)
                }
                CalidadRoute.Perfil -> CalidadPerfilScreen(session) { container.sessionManager.clear() }
                CalidadRoute.Notificaciones -> {
                    val vm = rememberScreenModel { NotificacionesViewModel(notificacionesApi, container.cacheRepository) }
                    NotificacionesScreen(vm) { navigator.pop() }
                }
            }
        }
    }
}

private fun topBarTitle(route: CalidadRoute): String = when (route) {
    CalidadRoute.Inicio -> "Ecolácteos Huata"
    CalidadRoute.Pendientes -> "Análisis pendientes"
    CalidadRoute.Muestreo -> "Registrar entrega (muestreo)"
    is CalidadRoute.Analizar -> "Analizar entrega"
    CalidadRoute.Historial -> "Historial"
    is CalidadRoute.HistorialDetalle -> "Análisis"
    CalidadRoute.Mas -> "Más"
    CalidadRoute.Proveedores -> "Proveedores"
    is CalidadRoute.ProveedorDetalle -> "Proveedor"
    CalidadRoute.Comunicados -> "Comunicados"
    is CalidadRoute.ComunicadoDetalle -> "Comunicado"
    CalidadRoute.NuevoComunicado -> "Nuevo comunicado"
    CalidadRoute.Sincronizacion -> "Sincronización"
    CalidadRoute.Perfil -> "Mi perfil"
    CalidadRoute.Notificaciones -> "Notificaciones"
}
