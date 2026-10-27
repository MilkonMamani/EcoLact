package pe.edu.upeu.ecolactmobile.feature.planta

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
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
import pe.edu.upeu.ecolactmobile.core.ui.state.rememberScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.initials
import pe.edu.upeu.ecolactmobile.core.util.primerNombre
import pe.edu.upeu.ecolactmobile.di.AppContainer
import pe.edu.upeu.ecolactmobile.feature.calidad.components.AvisoSincronizacion
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesRepository
import pe.edu.upeu.ecolactmobile.feature.planta.components.PlantaBottomNavigation
import pe.edu.upeu.ecolactmobile.feature.planta.components.PlantaDrawer
import pe.edu.upeu.ecolactmobile.feature.planta.components.PlantaSeccion
import pe.edu.upeu.ecolactmobile.feature.planta.components.PlantaTab
import pe.edu.upeu.ecolactmobile.feature.planta.screens.CamaraBoletaScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.CamaraBoletaViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.InsumoDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.InsumoDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.LoteDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.LoteDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.NuevaProduccionScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.NuevaProduccionViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaComprasScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaComprasViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaInicioScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaInicioViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaInventarioScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaInventarioViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaMiQrScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaPerfilScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaPerfilViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaProduccionScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaProduccionViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaProductoDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaProductoDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaRecetaDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaRecetasScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.PlantaRecetasViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.RevisarCompraScreen
import pe.edu.upeu.ecolactmobile.feature.planta.screens.RevisarCompraViewModel
import pe.edu.upeu.ecolactmobile.feature.planta.screens.nombreCompleto
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderTopBar
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorNotificacionesScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorNotificacionesViewModel

/**
 * Shell móvil del encargado de planta (mismo lenguaje visual que Proveedor y Calidad):
 * - barra inferior con exactamente 3 pantallas: Inicio, Producción, Inventario;
 * - menú lateral (☰, desde la izquierda) con la navegación agrupada + cerrar sesión;
 * - arriba a la derecha, solo el punto de conexión (verde/naranja) y la campana;
 * - sin "Más" ni "Sincronización": [PlantaSync] envía y descarga solo, con avisos breves.
 * Recetas y precios son de solo lectura (los configura el ADMINISTRADOR).
 */
@Composable
fun PlantaApp(container: AppContainer, session: UserSession) {
    val connectivity = container.connectivityObserver
    val isOnlineFlow = connectivity.isOnline
    val repository = remember(session.userId) {
        PlantaRepository(
            api = container.plantaApi,
            cache = container.cacheRepository,
            syncQueue = container.syncQueueRepository,
            compras = container.plantaCompraStore,
            isOnline = { connectivity.isOnline.value },
            userId = session.userId,
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
        PlantaSync(repository, notificaciones, container.syncManager, container.syncQueueRepository, connectivity)
    }
    val navigator = remember { Navigator<PlantaRoute>(PlantaRoute.Inicio) }
    val drawerState = rememberDrawerState(DrawerValue.Closed)
    val scope = rememberCoroutineScope()

    val isOnline by sync.isOnline.collectAsState()
    val aviso by sync.aviso.collectAsState()
    val noLeidas by remember(notificaciones) { notificaciones.observeNoLeidas() }.collectAsState(0)
    val porRevisar by remember(repository) { repository.observeBorradores().map { it.size } }.collectAsState(0)

    // Perfil compartido por la cabecera del menú, Mi QR y Mi perfil.
    val perfilVm = rememberScreenModel(session.userId) { PlantaPerfilViewModel(repository, isOnlineFlow) }
    val perfil by remember(repository) { repository.observePerfil().map { it?.value } }.collectAsState(null)
    val nombre = nombreCompleto(perfil, session.username)
    val iniciales = initials(perfil?.nombres, perfil?.apellidos, nombre)

    var confirmarLogout by remember { mutableStateOf(false) }
    val cerrarSesion: () -> Unit = { scope.launch { container.authRepository.logout() } }
    val pedirCierre: () -> Unit = { if (isOnline) cerrarSesion() else confirmarLogout = true }

    val current = navigator.current
    var ultimaPrincipal by remember { mutableStateOf<PlantaRoute>(PlantaRoute.Inicio) }
    LaunchedEffect(current) { if (current.esPrincipal) ultimaPrincipal = current }

    val abrir: (PlantaRoute) -> Unit = { route ->
        if (route.esPrincipal) {
            navigator.replaceAll(route)
        } else {
            navigator.replaceAll(ultimaPrincipal)
            navigator.navigate(route)
        }
    }

    PlatformBackHandler(enabled = drawerState.isOpen || navigator.canGoBack || current != PlantaRoute.Inicio) {
        when {
            drawerState.isOpen -> scope.launch { drawerState.close() }
            navigator.canGoBack -> navigator.pop()
            else -> navigator.replaceAll(PlantaRoute.Inicio)
        }
    }

    // La cámara ocupa toda la pantalla, sin barras.
    val pantallaCompleta = current == PlantaRoute.CamaraBoleta

    ModalNavigationDrawer(
        drawerState = drawerState,
        gesturesEnabled = current.esPrincipal || drawerState.isOpen,
        scrimColor = Color.Black.copy(alpha = 0.32f),
        drawerContent = {
            PlantaDrawer(
                nombre = nombre,
                initials = iniciales,
                isOnline = isOnline,
                seleccionada = seccionDe(current),
                contadores = mapOf(PlantaSeccion.NOTIFICACIONES to noLeidas, PlantaSeccion.COMPRAS to porRevisar),
                onSeleccionar = { seccion ->
                    abrir(seccion.route)
                    scope.launch { drawerState.close() }
                },
                onCerrarSesion = {
                    scope.launch { drawerState.close() }
                    pedirCierre()
                },
            )
        },
    ) {
        Scaffold(
            containerColor = EcoLactColors.Fondo,
            topBar = {
                if (!pantallaCompleta) {
                    Column {
                        ProviderTopBar(
                            title = titulo(current),
                            principal = current.esPrincipal,
                            isOnline = isOnline,
                            noLeidas = noLeidas,
                            mostrarCampana = current != PlantaRoute.Notificaciones,
                            onMenu = { scope.launch { drawerState.open() } },
                            onBack = { navigator.pop() },
                            onCampana = { navigator.navigate(PlantaRoute.Notificaciones) },
                        )
                        AvisoSincronizacion(aviso)
                    }
                }
            },
            bottomBar = {
                if (current.esPrincipal) {
                    PlantaBottomNavigation(
                        seleccionada = when (current) {
                            PlantaRoute.Inicio -> PlantaTab.INICIO
                            PlantaRoute.Produccion -> PlantaTab.PRODUCCION
                            is PlantaRoute.Inventario -> PlantaTab.INVENTARIO
                            else -> null
                        },
                        onSeleccionar = { tab -> if (tab.route != current) navigator.replaceAll(tab.route) },
                    )
                }
            },
        ) { padding ->
            Box(modifier = Modifier.fillMaxSize().padding(padding)) {
                when (val route = current) {
                    PlantaRoute.Inicio -> {
                        val vm = rememberScreenModel { PlantaInicioViewModel(repository, isOnlineFlow) }
                        PlantaInicioScreen(
                            viewModel = vm,
                            primerNombre = perfil?.nombres?.let(::primerNombre) ?: session.username,
                            onNuevaProduccion = { navigator.navigate(PlantaRoute.NuevaProduccion) },
                            onEscanearBoleta = { navigator.navigate(PlantaRoute.CamaraBoleta) },
                            onMiQr = { abrir(PlantaRoute.MiQr) },
                            onVerTodas = { navigator.replaceAll(PlantaRoute.Produccion) },
                            onVerLote = { navigator.navigate(PlantaRoute.LoteDetalle(it)) },
                        )
                    }
                    PlantaRoute.Produccion -> {
                        val vm = rememberScreenModel { PlantaProduccionViewModel(repository, isOnlineFlow) }
                        PlantaProduccionScreen(
                            viewModel = vm,
                            onNuevaProduccion = { navigator.navigate(PlantaRoute.NuevaProduccion) },
                            onVerLote = { navigator.navigate(PlantaRoute.LoteDetalle(it)) },
                        )
                    }
                    is PlantaRoute.Inventario -> {
                        val vm = rememberScreenModel { PlantaInventarioViewModel(repository, isOnlineFlow) }
                        PlantaInventarioScreen(
                            viewModel = vm,
                            tab = route.tab,
                            onTab = { navigator.replaceAll(PlantaRoute.Inventario(it)) },
                            onVerInsumo = { navigator.navigate(PlantaRoute.InsumoDetalle(it)) },
                            onVerProducto = { navigator.navigate(PlantaRoute.ProductoDetalle(it)) },
                        )
                    }
                    PlantaRoute.NuevaProduccion -> {
                        val vm = rememberScreenModel { NuevaProduccionViewModel(repository, isOnlineFlow) }
                        NuevaProduccionScreen(
                            viewModel = vm,
                            isOnline = isOnline,
                            onIniciada = { id ->
                                navigator.replaceAll(PlantaRoute.Produccion)
                                navigator.navigate(PlantaRoute.LoteDetalle(id))
                            },
                            onGuardadaOffline = { navigator.replaceAll(PlantaRoute.Produccion) },
                        )
                    }
                    is PlantaRoute.LoteDetalle -> {
                        val vm = rememberScreenModel(route.id) { LoteDetalleViewModel(repository, isOnlineFlow, route.id) }
                        LoteDetalleScreen(vm)
                    }
                    is PlantaRoute.InsumoDetalle -> {
                        val vm = rememberScreenModel(route.id) { InsumoDetalleViewModel(repository, isOnlineFlow, route.id) }
                        InsumoDetalleScreen(
                            viewModel = vm,
                            onEscanearBoleta = { navigator.navigate(PlantaRoute.CamaraBoleta) },
                            onCompraManual = { navigator.navigate(PlantaRoute.RevisarCompra(repository.crearBorradorManual(route.id), manual = true)) },
                        )
                    }
                    is PlantaRoute.ProductoDetalle -> {
                        val vm = rememberScreenModel(route.id) { PlantaProductoDetalleViewModel(repository, isOnlineFlow, route.id) }
                        PlantaProductoDetalleScreen(vm)
                    }
                    PlantaRoute.Recetas -> {
                        val vm = rememberScreenModel { PlantaRecetasViewModel(repository, isOnlineFlow) }
                        PlantaRecetasScreen(vm) { navigator.navigate(PlantaRoute.RecetaDetalle(it)) }
                    }
                    is PlantaRoute.RecetaDetalle -> {
                        val vm = rememberScreenModel { PlantaRecetasViewModel(repository, isOnlineFlow) }
                        PlantaRecetaDetalleScreen(vm, route.productoId)
                    }
                    PlantaRoute.Compras -> {
                        val vm = rememberScreenModel { PlantaComprasViewModel(repository, isOnlineFlow) }
                        PlantaComprasScreen(
                            viewModel = vm,
                            onEscanearBoleta = { navigator.navigate(PlantaRoute.CamaraBoleta) },
                            onCompraManual = { navigator.navigate(PlantaRoute.RevisarCompra(repository.crearBorradorManual(), manual = true)) },
                            onAbrirBorrador = { navigator.navigate(PlantaRoute.RevisarCompra(it.id, manual = it.origen == OrigenCompra.MANUAL)) },
                        )
                    }
                    PlantaRoute.CamaraBoleta -> {
                        val vm = rememberScreenModel { CamaraBoletaViewModel(repository) }
                        CamaraBoletaScreen(
                            viewModel = vm,
                            onLeida = { id ->
                                navigator.pop()
                                navigator.navigate(PlantaRoute.RevisarCompra(id))
                            },
                            onRevisarAhora = { id ->
                                navigator.pop()
                                navigator.navigate(PlantaRoute.RevisarCompra(id))
                            },
                            onCerrar = { navigator.pop() },
                        )
                    }
                    is PlantaRoute.RevisarCompra -> {
                        val vm = rememberScreenModel(route.borradorId) { RevisarCompraViewModel(repository, route.borradorId) }
                        RevisarCompraScreen(vm, isOnline) { navigator.pop() }
                    }
                    PlantaRoute.MiQr -> PlantaMiQrScreen(perfilVm, nombre)
                    PlantaRoute.Notificaciones -> {
                        val vm = rememberScreenModel { ProveedorNotificacionesViewModel(notificaciones, isOnlineFlow) }
                        ProveedorNotificacionesScreen(vm)
                    }
                    PlantaRoute.Perfil -> PlantaPerfilScreen(perfilVm, nombre, session.username, pedirCierre)
                }
            }
        }
    }

    if (confirmarLogout) {
        AlertDialog(
            onDismissRequest = { confirmarLogout = false },
            title = { Text("Cerrar sesión sin conexión") },
            text = { Text("Estás sin internet. Lo guardado en el teléfono se enviará al volver a ingresar con conexión. ¿Cerrar sesión de todas formas?") },
            confirmButton = {
                TextButton(onClick = { confirmarLogout = false; cerrarSesion() }) { Text("Cerrar sesión", color = EcoLactColors.Rojo) }
            },
            dismissButton = { TextButton(onClick = { confirmarLogout = false }) { Text("Cancelar") } },
        )
    }
}

/** Opción del menú a resaltar, también dentro de sus detalles. */
private fun seccionDe(route: PlantaRoute): PlantaSeccion? = when (route) {
    PlantaRoute.Inicio -> PlantaSeccion.INICIO
    PlantaRoute.Produccion, PlantaRoute.NuevaProduccion, is PlantaRoute.LoteDetalle -> PlantaSeccion.PRODUCCION
    is PlantaRoute.Inventario -> if (route.tab == 1) PlantaSeccion.PRODUCTOS else PlantaSeccion.INSUMOS
    is PlantaRoute.InsumoDetalle -> PlantaSeccion.INSUMOS
    is PlantaRoute.ProductoDetalle -> PlantaSeccion.PRODUCTOS
    PlantaRoute.Recetas, is PlantaRoute.RecetaDetalle -> PlantaSeccion.RECETAS
    PlantaRoute.Compras, PlantaRoute.CamaraBoleta, is PlantaRoute.RevisarCompra -> PlantaSeccion.COMPRAS
    PlantaRoute.MiQr -> PlantaSeccion.MI_QR
    PlantaRoute.Notificaciones -> PlantaSeccion.NOTIFICACIONES
    PlantaRoute.Perfil -> PlantaSeccion.PERFIL
}

private fun titulo(route: PlantaRoute): String = when (route) {
    PlantaRoute.Inicio -> "Inicio"
    PlantaRoute.Produccion -> "Producción"
    is PlantaRoute.Inventario -> "Inventario"
    PlantaRoute.NuevaProduccion -> "Nueva producción"
    is PlantaRoute.LoteDetalle -> "Detalle de lote"
    is PlantaRoute.InsumoDetalle -> "Detalle de insumo"
    is PlantaRoute.ProductoDetalle -> "Detalle de producto"
    PlantaRoute.Recetas -> "Recetas"
    is PlantaRoute.RecetaDetalle -> "Detalle de receta"
    PlantaRoute.Compras -> "Compras de insumos"
    PlantaRoute.CamaraBoleta -> "Tomar foto de la boleta"
    is PlantaRoute.RevisarCompra -> if (route.manual) "Registrar compra" else "Datos detectados"
    PlantaRoute.MiQr -> "Mi QR / Asistencia"
    PlantaRoute.Notificaciones -> "Notificaciones"
    PlantaRoute.Perfil -> "Mi perfil"
}
