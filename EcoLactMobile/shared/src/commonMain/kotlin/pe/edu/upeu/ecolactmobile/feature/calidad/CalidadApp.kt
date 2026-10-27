package pe.edu.upeu.ecolactmobile.feature.calidad

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
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.state.rememberScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.initials
import pe.edu.upeu.ecolactmobile.di.AppContainer
import pe.edu.upeu.ecolactmobile.feature.calidad.components.AvisoSincronizacion
import pe.edu.upeu.ecolactmobile.feature.calidad.components.CalidadBottomNavigation
import pe.edu.upeu.ecolactmobile.feature.calidad.components.CalidadDrawer
import pe.edu.upeu.ecolactmobile.feature.calidad.components.CalidadSeccion
import pe.edu.upeu.ecolactmobile.feature.calidad.components.CalidadTab
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadComunicadoDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadComunicadoDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadComunicadosScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadComunicadosViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadHistorialDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadHistorialDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadHistorialScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadHistorialViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadInicioScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadPanelViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadPendientesScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadPerfilScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadProveedorDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadProveedorDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadProveedoresScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CalidadProveedoresViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CamaraTicketScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.CamaraTicketViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.MuestraDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.MuestraDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.MuestreoDirectoScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.MuestreoDirectoViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.MuestreoScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.MuestreoViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.NuevoComunicadoScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.NuevoComunicadoViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.RevisionAnalisisScreen
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.RevisionAnalisisViewModel
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderTopBar
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorNotificacionesScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorNotificacionesViewModel
import pe.edu.upeu.ecolactmobile.feature.trabajador.MiQrTrabajadorScreen
import pe.edu.upeu.ecolactmobile.feature.trabajador.MiQrViewModel

/**
 * Shell móvil del analista de calidad (mismo lenguaje visual que el del proveedor):
 * - barra inferior con exactamente 3 pantallas: Inicio, Muestreo, Historial;
 * - menú lateral (☰, desde la izquierda) con la navegación agrupada + cerrar sesión;
 * - arriba a la derecha, solo el punto de conexión (verde/naranja) y la campana;
 * - sin pantalla de "Sincronización": [CalidadSync] envía y descarga solo, con avisos breves.
 */
@Composable
fun CalidadApp(container: AppContainer, session: UserSession) {
    val connectivity = container.connectivityObserver
    val isOnlineFlow = connectivity.isOnline
    val repository = remember(session.userId) {
        CalidadRepository(
            api = container.calidadApi,
            cache = container.cacheRepository,
            syncQueue = container.syncQueueRepository,
            capturas = container.calidadCapturaStore,
            isOnline = { connectivity.isOnline.value },
            database = container.database,
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
        CalidadSync(repository, notificaciones, container.syncManager, container.syncQueueRepository, connectivity)
    }
    val navigator = remember { Navigator<CalidadRoute>(CalidadRoute.Inicio) }
    val drawerState = rememberDrawerState(DrawerValue.Closed)
    val scope = rememberCoroutineScope()

    val isOnline by sync.isOnline.collectAsState()
    val aviso by sync.aviso.collectAsState()
    val noLeidas by remember(notificaciones) { notificaciones.observeNoLeidas() }.collectAsState(0)

    // Panel compartido por Inicio, Muestras pendientes y la cabecera del menú.
    val panelVm = rememberScreenModel(session.userId) { CalidadPanelViewModel(repository, isOnlineFlow) }
    val analista by remember(repository) { repository.observePanel().map { it?.value?.analista } }.collectAsState(null)
    val pendientes by remember(repository) { repository.observePanel().map { it?.value?.pendientes?.size ?: 0 } }.collectAsState(0)
    val nombre = analista?.let { "${it.nombres} ${it.apellidos}".trim() } ?: session.username
    val iniciales = initials(analista?.nombres, analista?.apellidos, nombre)

    var confirmarLogout by remember { mutableStateOf(false) }
    val cerrarSesion: () -> Unit = { scope.launch { container.authRepository.logout() } }
    val pedirCierre: () -> Unit = { if (isOnline) cerrarSesion() else confirmarLogout = true }

    val current = navigator.current
    var ultimaPrincipal by remember { mutableStateOf<CalidadRoute>(CalidadRoute.Inicio) }
    LaunchedEffect(current) { if (current.esPrincipal) ultimaPrincipal = current }

    val abrir: (CalidadRoute) -> Unit = { route ->
        if (route.esPrincipal) {
            navigator.replaceAll(route)
        } else {
            navigator.replaceAll(ultimaPrincipal)
            navigator.navigate(route)
        }
    }

    PlatformBackHandler(enabled = drawerState.isOpen || navigator.canGoBack || current != CalidadRoute.Inicio) {
        when {
            drawerState.isOpen -> scope.launch { drawerState.close() }
            navigator.canGoBack -> navigator.pop()
            else -> navigator.replaceAll(CalidadRoute.Inicio)
        }
    }

    // La cámara ocupa toda la pantalla, sin barras.
    val pantallaCompleta = current is CalidadRoute.Camara

    ModalNavigationDrawer(
        drawerState = drawerState,
        gesturesEnabled = current.esPrincipal || drawerState.isOpen,
        scrimColor = Color.Black.copy(alpha = 0.32f),
        drawerContent = {
            CalidadDrawer(
                nombre = nombre,
                initials = iniciales,
                isOnline = isOnline,
                seleccionada = seccionDe(current),
                contadores = mapOf(CalidadSeccion.NOTIFICACIONES to noLeidas, CalidadSeccion.PENDIENTES to pendientes),
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
                            mostrarCampana = current != CalidadRoute.Notificaciones,
                            onMenu = { scope.launch { drawerState.open() } },
                            onBack = { navigator.pop() },
                            onCampana = { navigator.navigate(CalidadRoute.Notificaciones) },
                        )
                        AvisoSincronizacion(aviso)
                    }
                }
            },
            bottomBar = {
                if (current.esPrincipal) {
                    CalidadBottomNavigation(
                        seleccionada = CalidadTab.entries.firstOrNull { it.route == current },
                        onSeleccionar = { tab -> if (tab.route != current) navigator.replaceAll(tab.route) },
                    )
                }
            },
        ) { padding ->
            Box(modifier = Modifier.fillMaxSize().padding(padding)) {
                when (val route = current) {
                    CalidadRoute.Inicio -> CalidadInicioScreen(
                        viewModel = panelVm,
                        nombreFallback = nombre,
                        onAnalizar = { navigator.navigate(CalidadRoute.MuestraDetalle(it)) },
                        onVerTodas = { abrir(CalidadRoute.Pendientes) },
                        onIrAMuestreo = { navigator.replaceAll(CalidadRoute.Muestreo) },
                    )
                    CalidadRoute.Muestreo -> {
                        val vm = rememberScreenModel { MuestreoViewModel(repository) }
                        MuestreoScreen(
                            viewModel = vm,
                            isOnline = isOnline,
                            onAnalizar = { navigator.navigate(CalidadRoute.MuestraDetalle(it)) },
                            onVerPendientes = { abrir(CalidadRoute.Pendientes) },
                            onMuestreoDirecto = { navigator.navigate(CalidadRoute.MuestreoDirecto) },
                        )
                    }
                    CalidadRoute.Historial -> {
                        val vm = rememberScreenModel { CalidadHistorialViewModel(repository, isOnlineFlow) }
                        CalidadHistorialScreen(vm) { navigator.navigate(CalidadRoute.HistorialDetalle(it)) }
                    }
                    CalidadRoute.Pendientes -> CalidadPendientesScreen(
                        viewModel = panelVm,
                        onAnalizar = { navigator.navigate(CalidadRoute.MuestraDetalle(it)) },
                        onIrAMuestreo = { navigator.replaceAll(CalidadRoute.Muestreo) },
                    )
                    is CalidadRoute.MuestraDetalle -> {
                        val vm = rememberScreenModel(route.entregaId) { MuestraDetalleViewModel(repository, isOnlineFlow, route.entregaId) }
                        MuestraDetalleScreen(
                            viewModel = vm,
                            onEscanear = { navigator.navigate(CalidadRoute.Camara(route.entregaId)) },
                            onIngresarManual = { navigator.navigate(CalidadRoute.Revision(route.entregaId)) },
                        )
                    }
                    is CalidadRoute.Camara -> {
                        val muestreoId = panelVm.state.value.let { s ->
                            (s as? UiState.Success)?.data?.pendientes?.firstOrNull { it.entregaId == route.entregaId }?.muestreoId
                        }
                        val vm = rememberScreenModel(route.entregaId) { CamaraTicketViewModel(repository, route.entregaId, muestreoId) }
                        CamaraTicketScreen(
                            viewModel = vm,
                            onLeida = {
                                navigator.pop()
                                navigator.navigate(CalidadRoute.Revision(route.entregaId))
                            },
                            onIngresarManual = {
                                navigator.pop()
                                navigator.navigate(CalidadRoute.Revision(route.entregaId))
                            },
                            onCerrar = { navigator.pop() },
                        )
                    }
                    is CalidadRoute.Revision -> {
                        val vm = rememberScreenModel(route.entregaId) { RevisionAnalisisViewModel(repository, route.entregaId) }
                        RevisionAnalisisScreen(
                            viewModel = vm,
                            onVerDetalle = { id ->
                                navigator.replaceAll(CalidadRoute.Historial)
                                navigator.navigate(CalidadRoute.HistorialDetalle(id))
                            },
                            onContinuar = { navigator.replaceAll(ultimaPrincipal) },
                        )
                    }
                    is CalidadRoute.HistorialDetalle -> {
                        val vm = rememberScreenModel(route.analisisId) { CalidadHistorialDetalleViewModel(repository, isOnlineFlow, route.analisisId) }
                        CalidadHistorialDetalleScreen(vm)
                    }
                    CalidadRoute.MuestreoDirecto -> {
                        val vm = rememberScreenModel { MuestreoDirectoViewModel(repository) }
                        MuestreoDirectoScreen(
                            viewModel = vm,
                            onAnalizar = {
                                navigator.pop()
                                navigator.navigate(CalidadRoute.MuestraDetalle(it))
                            },
                            onListo = { navigator.pop() },
                        )
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
                    CalidadRoute.Notificaciones -> {
                        val vm = rememberScreenModel { ProveedorNotificacionesViewModel(notificaciones, isOnlineFlow) }
                        ProveedorNotificacionesScreen(vm)
                    }
                    CalidadRoute.MiQr -> {
                        val vm = rememberScreenModel { MiQrViewModel(repository.observeAsistencia(), isOnlineFlow) { repository.refreshAsistencia() } }
                        MiQrTrabajadorScreen(vm, rol = "Analista de calidad", nombreFallback = nombre)
                    }
                    CalidadRoute.Perfil -> CalidadPerfilScreen(session, nombre, iniciales, pedirCierre)
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
private fun seccionDe(route: CalidadRoute): CalidadSeccion? = when (route) {
    CalidadRoute.Inicio -> CalidadSeccion.INICIO
    CalidadRoute.Muestreo, CalidadRoute.MuestreoDirecto -> CalidadSeccion.MUESTREO
    CalidadRoute.Pendientes, is CalidadRoute.MuestraDetalle, is CalidadRoute.Camara, is CalidadRoute.Revision -> CalidadSeccion.PENDIENTES
    CalidadRoute.Historial, is CalidadRoute.HistorialDetalle -> CalidadSeccion.HISTORIAL
    CalidadRoute.Proveedores, is CalidadRoute.ProveedorDetalle -> CalidadSeccion.PROVEEDORES
    CalidadRoute.Comunicados, is CalidadRoute.ComunicadoDetalle, CalidadRoute.NuevoComunicado -> CalidadSeccion.COMUNICADOS
    CalidadRoute.Notificaciones -> CalidadSeccion.NOTIFICACIONES
    CalidadRoute.MiQr -> CalidadSeccion.MI_QR
    CalidadRoute.Perfil -> CalidadSeccion.PERFIL
}

private fun titulo(route: CalidadRoute): String = when (route) {
    CalidadRoute.Inicio -> "Inicio"
    CalidadRoute.Muestreo -> "Muestreo"
    CalidadRoute.Historial -> "Historial"
    CalidadRoute.Pendientes -> "Muestras pendientes"
    is CalidadRoute.MuestraDetalle -> "Detalle de entrega"
    is CalidadRoute.Camara -> "Tomar foto del ticket"
    is CalidadRoute.Revision -> "Revisar datos"
    is CalidadRoute.HistorialDetalle -> "Detalle de análisis"
    CalidadRoute.MuestreoDirecto -> "Muestreo directo"
    CalidadRoute.Proveedores -> "Proveedores"
    is CalidadRoute.ProveedorDetalle -> "Proveedor"
    CalidadRoute.Comunicados -> "Comunicados"
    is CalidadRoute.ComunicadoDetalle -> "Comunicado"
    CalidadRoute.NuevoComunicado -> "Nuevo comunicado"
    CalidadRoute.Notificaciones -> "Notificaciones"
    CalidadRoute.MiQr -> "Mi QR / Asistencia"
    CalidadRoute.Perfil -> "Mi perfil"
}
