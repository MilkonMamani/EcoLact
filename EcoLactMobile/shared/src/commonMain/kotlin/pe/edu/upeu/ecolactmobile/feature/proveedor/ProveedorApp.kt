package pe.edu.upeu.ecolactmobile.feature.proveedor

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
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
import pe.edu.upeu.ecolactmobile.core.ui.navigation.Navigator
import pe.edu.upeu.ecolactmobile.core.ui.state.rememberScreenModel
import pe.edu.upeu.ecolactmobile.di.AppContainer
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesApi
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesScreen
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorAnalisisDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorAnalisisDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorComunicadoDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorComunicadoDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorComunicadosScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorComunicadosViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorCompraDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorCompraDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorEntregaDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorEntregaDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorEntregasScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorEntregasViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorInicioScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorInicioViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorLiquidacionDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorLiquidacionDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorMasScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorMiCalidadScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorMiCalidadViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorMiQrScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorMiQrViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorMisComprasScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorMisComprasViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorPagosScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorPagosViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorPerfilScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorPerfilViewModel

private val BOTTOM_TABS = listOf(
    Triple(ProveedorRoute.Inicio, "🏠", "Inicio"),
    Triple(ProveedorRoute.Entregas, "🚚", "Entregas"),
    Triple(ProveedorRoute.Pagos, "💰", "Pagos"),
    Triple(ProveedorRoute.Mas, "☰", "Más"),
)

@Composable
fun ProveedorApp(container: AppContainer) {
    val api = remember { ProveedorApi(container.httpClient) }
    val repository = remember { ProveedorRepository(api, container.cacheRepository) }
    val notificacionesApi = remember { NotificacionesApi(container.httpClient) }
    val navigator = remember { Navigator<ProveedorRoute>(ProveedorRoute.Inicio) }
    val isOnline by container.connectivityObserver.isOnline.collectAsState()

    val current = navigator.current
    val isTopLevel = current in listOf(ProveedorRoute.Inicio, ProveedorRoute.Entregas, ProveedorRoute.Pagos, ProveedorRoute.Mas)

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
                        IconButton(onClick = { navigator.navigate(ProveedorRoute.Notificaciones) }) {
                            Text("🔔")
                        }
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
            ProveedorRoute.Inicio -> {
                val vm = rememberScreenModel { ProveedorInicioViewModel(repository) }
                ProveedorInicioScreen(
                    viewModel = vm,
                    onVerEntrega = { navigator.navigate(ProveedorRoute.EntregaDetalle(it)) },
                    onVerLiquidacion = { navigator.navigate(ProveedorRoute.LiquidacionDetalle(it)) },
                    onVerComunicado = { navigator.navigate(ProveedorRoute.ComunicadoDetalle(it)) },
                )
            }
            ProveedorRoute.Entregas -> {
                val vm = rememberScreenModel { ProveedorEntregasViewModel(repository) }
                ProveedorEntregasScreen(vm) { navigator.navigate(ProveedorRoute.EntregaDetalle(it)) }
            }
            is ProveedorRoute.EntregaDetalle -> {
                val vm = rememberScreenModel(route.id) { ProveedorEntregaDetalleViewModel(repository, route.id) }
                ProveedorEntregaDetalleScreen(vm)
            }
            ProveedorRoute.Pagos -> {
                val vm = rememberScreenModel { ProveedorPagosViewModel(repository) }
                ProveedorPagosScreen(vm) { navigator.navigate(ProveedorRoute.LiquidacionDetalle(it)) }
            }
            is ProveedorRoute.LiquidacionDetalle -> {
                val vm = rememberScreenModel(route.id) { ProveedorLiquidacionDetalleViewModel(repository, route.id) }
                ProveedorLiquidacionDetalleScreen(vm)
            }
            ProveedorRoute.Mas -> ProveedorMasScreen { navigator.navigate(it) }
            ProveedorRoute.MiCalidad -> {
                val vm = rememberScreenModel { ProveedorMiCalidadViewModel(repository) }
                ProveedorMiCalidadScreen(vm) { navigator.navigate(ProveedorRoute.AnalisisDetalle(it)) }
            }
            is ProveedorRoute.AnalisisDetalle -> {
                val vm = rememberScreenModel(route.id) { ProveedorAnalisisDetalleViewModel(repository, route.id) }
                ProveedorAnalisisDetalleScreen(vm)
            }
            ProveedorRoute.MisCompras -> {
                val vm = rememberScreenModel { ProveedorMisComprasViewModel(repository) }
                ProveedorMisComprasScreen(vm) { navigator.navigate(ProveedorRoute.CompraDetalle(it)) }
            }
            is ProveedorRoute.CompraDetalle -> {
                val vm = rememberScreenModel(route.id) { ProveedorCompraDetalleViewModel(repository, route.id) }
                ProveedorCompraDetalleScreen(vm)
            }
            ProveedorRoute.MiQr -> {
                val vm = rememberScreenModel { ProveedorMiQrViewModel(repository) }
                ProveedorMiQrScreen(vm)
            }
            ProveedorRoute.Comunicados -> {
                val vm = rememberScreenModel { ProveedorComunicadosViewModel(repository) }
                ProveedorComunicadosScreen(vm) { navigator.navigate(ProveedorRoute.ComunicadoDetalle(it)) }
            }
            is ProveedorRoute.ComunicadoDetalle -> {
                val vm = rememberScreenModel(route.id) { ProveedorComunicadoDetalleViewModel(repository, route.id) }
                ProveedorComunicadoDetalleScreen(vm)
            }
            ProveedorRoute.Perfil -> {
                val vm = rememberScreenModel { ProveedorPerfilViewModel(repository) }
                ProveedorPerfilScreen(vm) { container.sessionManager.clear() }
            }
            ProveedorRoute.Notificaciones -> {
                val vm = rememberScreenModel { NotificacionesViewModel(notificacionesApi, container.cacheRepository) }
                NotificacionesScreen(vm) { navigator.pop() }
            }
        }
        }
    }
}

private fun topBarTitle(route: ProveedorRoute): String = when (route) {
    ProveedorRoute.Inicio -> "Ecolácteos Huata"
    ProveedorRoute.Entregas -> "Mis entregas"
    is ProveedorRoute.EntregaDetalle -> "Detalle de entrega"
    ProveedorRoute.Pagos -> "Mis pagos"
    is ProveedorRoute.LiquidacionDetalle -> "Liquidación"
    ProveedorRoute.Mas -> "Más"
    ProveedorRoute.MiCalidad -> "Mi calidad"
    is ProveedorRoute.AnalisisDetalle -> "Análisis"
    ProveedorRoute.MisCompras -> "Mis compras"
    is ProveedorRoute.CompraDetalle -> "Detalle de compra"
    ProveedorRoute.MiQr -> "Mi código QR"
    ProveedorRoute.Comunicados -> "Comunicados"
    is ProveedorRoute.ComunicadoDetalle -> "Comunicado"
    ProveedorRoute.Perfil -> "Mi perfil"
    ProveedorRoute.Notificaciones -> "Notificaciones"
}
