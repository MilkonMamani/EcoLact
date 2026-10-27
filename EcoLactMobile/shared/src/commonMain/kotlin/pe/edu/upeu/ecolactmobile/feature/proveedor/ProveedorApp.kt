package pe.edu.upeu.ecolactmobile.feature.proveedor

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.DrawerValue
import androidx.compose.material3.ModalNavigationDrawer
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDrawerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.session.UserSession
import pe.edu.upeu.ecolactmobile.core.ui.navigation.Navigator
import pe.edu.upeu.ecolactmobile.core.ui.navigation.PlatformBackHandler
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.state.rememberScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.initials
import pe.edu.upeu.ecolactmobile.di.AppContainer
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorSeccion
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorTab
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderBottomNavigation
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderDrawer
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderTopBar
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorAnalisisDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorAnalisisDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorCompraDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorCompraDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorComunicadoDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorComunicadoDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorComunicadosScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorComunicadosViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorEntregaDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorEntregaDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorEntregasScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorEntregasViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorInicioScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorInicioViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorLiquidacionDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorLiquidacionDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorMiCalidadScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorMiCalidadViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorMisComprasScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorMisComprasViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorNotificacionesScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorNotificacionesViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorPagosScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorPagosViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorPerfilScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorPerfilViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorSincronizacionScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorSincronizacionViewModel

/**
 * Shell móvil del proveedor:
 * - barra inferior con las 3 pantallas frecuentes (Inicio, Entregas, Pagos);
 * - menú lateral (☰, desde la izquierda) con toda la navegación agrupada + cerrar sesión;
 * - arriba a la derecha, el punto de conexión (verde/naranja) y la campana de notificaciones;
 * - las pantallas observan SQLite: [ProveedorSync] las mantiene al día sin que el usuario haga nada.
 */
@Composable
fun ProveedorApp(container: AppContainer, session: UserSession) {
    val connectivity = container.connectivityObserver
    val isOnlineFlow = connectivity.isOnline
    val repository = remember(session.userId) {
        ProveedorRepository(
            api = container.proveedorApi,
            cache = container.cacheRepository,
            syncQueue = container.syncQueueRepository,
            userId = session.userId,
            isOnline = { connectivity.isOnline.value },
        )
    }
    val notificaciones = remember(session.userId) {
        NotificacionesRepository(
            api = container.notificacionesApi,
            cache = container.cacheRepository,
            syncQueue = container.syncQueueRepository,
            userId = session.userId,
            isOnline = { connectivity.isOnline.value },
        )
    }
    val sync = rememberScreenModel(session.userId) {
        ProveedorSync(repository, notificaciones, container.syncManager, connectivity)
    }
    val navigator = remember { Navigator<ProveedorRoute>(ProveedorRoute.Inicio) }
    val drawerState = rememberDrawerState(DrawerValue.Closed)
    val scope = rememberCoroutineScope()

    val isOnline by sync.isOnline.collectAsState()
    val noLeidas by remember(notificaciones) { notificaciones.observeNoLeidas() }.collectAsState(0)
    val comunicadosNuevos by remember(repository) {
        repository.observeComunicados().map { c -> c?.value?.filas?.count { it.leido == false } ?: 0 }
    }.collectAsState(0)

    // Cabecera del menú: del perfil guardado (sirve también a la pantalla "Mi perfil").
    val perfilVm = rememberScreenModel(session.userId) { ProveedorPerfilViewModel(repository, isOnlineFlow) }
    val perfilState by perfilVm.state.collectAsState()
    val perfil = (perfilState as? UiState.Success)?.data
    val nombre = perfil?.nombre_completo ?: session.username
    val subtitulo = listOfNotNull("Proveedor", perfil?.zona_nombre).joinToString(" · ")

    var confirmarLogout by remember { mutableStateOf(false) }
    val cerrarSesion: () -> Unit = { scope.launch { container.authRepository.logout() } }

    val current = navigator.current
    // Pantalla principal a la que vuelve "atrás" desde una sección abierta en el menú.
    var ultimaPrincipal by remember { mutableStateOf<ProveedorRoute>(ProveedorRoute.Inicio) }
    LaunchedEffect(current) { if (current.esPrincipal) ultimaPrincipal = current }

    val abrir: (ProveedorRoute) -> Unit = { route ->
        if (route.esPrincipal) {
            navigator.replaceAll(route)
        } else {
            navigator.replaceAll(ultimaPrincipal)
            navigator.navigate(route)
        }
    }

    PlatformBackHandler(enabled = drawerState.isOpen || navigator.canGoBack || current != ProveedorRoute.Inicio) {
        when {
            drawerState.isOpen -> scope.launch { drawerState.close() }
            navigator.canGoBack -> navigator.pop()
            else -> navigator.replaceAll(ProveedorRoute.Inicio)
        }
    }

    ModalNavigationDrawer(
        drawerState = drawerState,
        // En pantallas secundarias el gesto de borde no abre el menú: ahí lo esperado es "volver".
        gesturesEnabled = current.esPrincipal || drawerState.isOpen,
        scrimColor = Color.Black.copy(alpha = 0.32f),
        drawerContent = {
            ProviderDrawer(
                nombre = nombre,
                subtitulo = subtitulo,
                initials = initials(perfil?.nombres, perfil?.apellidos, nombre),
                isOnline = isOnline,
                seleccionada = seccionDe(current),
                noLeidas = mapOf(
                    ProveedorSeccion.NOTIFICACIONES to noLeidas,
                    ProveedorSeccion.COMUNICADOS to comunicadosNuevos,
                ),
                onSeleccionar = { seccion ->
                    abrir(seccion.route)
                    scope.launch { drawerState.close() }
                },
                onCerrarSesion = {
                    scope.launch { drawerState.close() }
                    // Sin internet no se podrá volver a entrar hasta reconectar: se avisa antes.
                    if (isOnline) cerrarSesion() else confirmarLogout = true
                },
            )
        },
    ) {
        Scaffold(
            containerColor = EcoLactColors.Fondo,
            topBar = {
                ProviderTopBar(
                    title = titulo(current),
                    principal = current.esPrincipal,
                    isOnline = isOnline,
                    noLeidas = noLeidas,
                    mostrarCampana = current != ProveedorRoute.Notificaciones,
                    onMenu = { scope.launch { drawerState.open() } },
                    onBack = { navigator.pop() },
                    onCampana = { navigator.navigate(ProveedorRoute.Notificaciones) },
                )
            },
            bottomBar = {
                if (current.esPrincipal) {
                    ProviderBottomNavigation(
                        seleccionada = ProveedorTab.entries.firstOrNull { it.route == current },
                        onSeleccionar = { tab -> if (tab.route != current) navigator.replaceAll(tab.route) },
                    )
                }
            },
        ) { padding ->
            Box(modifier = Modifier.fillMaxSize().padding(padding)) {
                when (val route = current) {
                    ProveedorRoute.Inicio -> {
                        val vm = rememberScreenModel { ProveedorInicioViewModel(repository, isOnlineFlow) }
                        ProveedorInicioScreen(vm, nombreFallback = nombre)
                    }
                    ProveedorRoute.Entregas -> {
                        val vm = rememberScreenModel { ProveedorEntregasViewModel(repository, isOnlineFlow) }
                        ProveedorEntregasScreen(vm) { navigator.navigate(ProveedorRoute.EntregaDetalle(it)) }
                    }
                    is ProveedorRoute.EntregaDetalle -> {
                        val vm = rememberScreenModel(route.id) { ProveedorEntregaDetalleViewModel(repository, isOnlineFlow, route.id) }
                        ProveedorEntregaDetalleScreen(vm) { navigator.navigate(ProveedorRoute.AnalisisDetalle(it)) }
                    }
                    ProveedorRoute.Pagos -> {
                        val vm = rememberScreenModel { ProveedorPagosViewModel(repository, isOnlineFlow) }
                        ProveedorPagosScreen(vm) { navigator.navigate(ProveedorRoute.LiquidacionDetalle(it)) }
                    }
                    is ProveedorRoute.LiquidacionDetalle -> {
                        val vm = rememberScreenModel(route.id) { ProveedorLiquidacionDetalleViewModel(repository, isOnlineFlow, route.id) }
                        ProveedorLiquidacionDetalleScreen(vm)
                    }
                    ProveedorRoute.MiCalidad -> {
                        val vm = rememberScreenModel { ProveedorMiCalidadViewModel(repository, isOnlineFlow) }
                        ProveedorMiCalidadScreen(vm) { navigator.navigate(ProveedorRoute.AnalisisDetalle(it)) }
                    }
                    is ProveedorRoute.AnalisisDetalle -> {
                        val vm = rememberScreenModel(route.id) { ProveedorAnalisisDetalleViewModel(repository, isOnlineFlow, route.id) }
                        ProveedorAnalisisDetalleScreen(vm)
                    }
                    ProveedorRoute.MisCompras -> {
                        val vm = rememberScreenModel { ProveedorMisComprasViewModel(repository, isOnlineFlow) }
                        ProveedorMisComprasScreen(vm) { navigator.navigate(ProveedorRoute.CompraDetalle(it)) }
                    }
                    is ProveedorRoute.CompraDetalle -> {
                        val vm = rememberScreenModel(route.id) { ProveedorCompraDetalleViewModel(repository, isOnlineFlow, route.id) }
                        ProveedorCompraDetalleScreen(vm) { navigator.navigate(ProveedorRoute.LiquidacionDetalle(it)) }
                    }
                    ProveedorRoute.Comunicados -> {
                        val vm = rememberScreenModel { ProveedorComunicadosViewModel(repository, isOnlineFlow) }
                        ProveedorComunicadosScreen(vm) { navigator.navigate(ProveedorRoute.ComunicadoDetalle(it)) }
                    }
                    is ProveedorRoute.ComunicadoDetalle -> {
                        val vm = rememberScreenModel(route.id) { ProveedorComunicadoDetalleViewModel(repository, isOnlineFlow, route.id) }
                        ProveedorComunicadoDetalleScreen(vm)
                    }
                    ProveedorRoute.Notificaciones -> {
                        val vm = rememberScreenModel { ProveedorNotificacionesViewModel(notificaciones, isOnlineFlow) }
                        ProveedorNotificacionesScreen(vm)
                    }
                    // Reutiliza el ViewModel de la cabecera del menú: una sola carga del perfil.
                    ProveedorRoute.Perfil -> ProveedorPerfilScreen(perfilVm)
                    ProveedorRoute.Sincronizacion -> {
                        val vm = rememberScreenModel {
                            ProveedorSincronizacionViewModel(sync, repository, container.syncQueueRepository, session.userId)
                        }
                        ProveedorSincronizacionScreen(vm)
                    }
                }
            }
        }
    }

    if (confirmarLogout) {
        AlertDialog(
            onDismissRequest = { confirmarLogout = false },
            title = { Text("Cerrar sesión sin conexión") },
            text = { Text("Estás sin internet. Para volver a ingresar necesitarás conexión. ¿Cerrar sesión de todas formas?") },
            confirmButton = {
                TextButton(onClick = { confirmarLogout = false; cerrarSesion() }) {
                    Text("Cerrar sesión", color = EcoLactColors.Rojo)
                }
            },
            dismissButton = {
                TextButton(onClick = { confirmarLogout = false }) { Text("Cancelar") }
            },
        )
    }
}

/** Opción del menú a resaltar, también cuando se está dentro de uno de sus detalles. */
private fun seccionDe(route: ProveedorRoute): ProveedorSeccion? = when (route) {
    ProveedorRoute.Inicio -> ProveedorSeccion.INICIO
    ProveedorRoute.Entregas, is ProveedorRoute.EntregaDetalle -> ProveedorSeccion.ENTREGAS
    ProveedorRoute.Pagos, is ProveedorRoute.LiquidacionDetalle -> ProveedorSeccion.PAGOS
    ProveedorRoute.MiCalidad, is ProveedorRoute.AnalisisDetalle -> ProveedorSeccion.CALIDAD
    ProveedorRoute.MisCompras, is ProveedorRoute.CompraDetalle -> ProveedorSeccion.COMPRAS
    ProveedorRoute.Comunicados, is ProveedorRoute.ComunicadoDetalle -> ProveedorSeccion.COMUNICADOS
    ProveedorRoute.Notificaciones -> ProveedorSeccion.NOTIFICACIONES
    ProveedorRoute.Perfil -> ProveedorSeccion.PERFIL
    ProveedorRoute.Sincronizacion -> ProveedorSeccion.SINCRONIZACION
}

private fun titulo(route: ProveedorRoute): String = when (route) {
    ProveedorRoute.Inicio -> "Inicio"
    ProveedorRoute.Entregas -> "Mis entregas"
    is ProveedorRoute.EntregaDetalle -> "Detalle de entrega"
    ProveedorRoute.Pagos -> "Mis pagos"
    is ProveedorRoute.LiquidacionDetalle -> "Detalle de pago"
    ProveedorRoute.MiCalidad -> "Mi calidad"
    is ProveedorRoute.AnalisisDetalle -> "Análisis de calidad"
    ProveedorRoute.MisCompras -> "Mis compras"
    is ProveedorRoute.CompraDetalle -> "Detalle de compra"
    ProveedorRoute.Comunicados -> "Comunicados"
    is ProveedorRoute.ComunicadoDetalle -> "Comunicado"
    ProveedorRoute.Notificaciones -> "Notificaciones"
    ProveedorRoute.Perfil -> "Mi perfil"
    ProveedorRoute.Sincronizacion -> "Sincronización"
}
