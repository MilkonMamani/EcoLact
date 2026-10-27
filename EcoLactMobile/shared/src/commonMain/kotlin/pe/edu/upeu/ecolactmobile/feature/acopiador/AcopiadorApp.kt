package pe.edu.upeu.ecolactmobile.feature.acopiador

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
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.session.UserSession
import pe.edu.upeu.ecolactmobile.core.ui.navigation.Navigator
import pe.edu.upeu.ecolactmobile.core.ui.navigation.PlatformBackHandler
import pe.edu.upeu.ecolactmobile.core.ui.state.rememberScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.initials
import pe.edu.upeu.ecolactmobile.di.AppContainer
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.AcopiadorBottomNavigation
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.AcopiadorDrawer
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.AcopiadorSeccion
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.AcopiadorTab
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorComunicadoDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorComunicadoDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorComunicadosScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorComunicadosViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorEntregaDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorEntregaDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorEntregasScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorEntregasViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorInicioScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorPerfilScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorProveedorDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorProveedorDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorProveedorHistorialScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorProveedoresScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorProveedoresViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorRecorridoDetalleScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorRecorridoDetalleViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorRecorridoScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorRecorridosViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorRegistrarScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorSeguimientoScreen
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.AcopiadorSeguimientoViewModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.screens.RegistrarViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.components.AvisoSincronizacion
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesRepository
import pe.edu.upeu.ecolactmobile.feature.trabajador.AsistenciaApi
import pe.edu.upeu.ecolactmobile.feature.trabajador.AsistenciaGate
import pe.edu.upeu.ecolactmobile.feature.trabajador.AsistenciaRequeridaBanner
import pe.edu.upeu.ecolactmobile.feature.trabajador.LocalAutorizacion
import pe.edu.upeu.ecolactmobile.feature.trabajador.MiQrTrabajadorScreen
import pe.edu.upeu.ecolactmobile.feature.trabajador.MiQrViewModel
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderTopBar
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorNotificacionesScreen
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.ProveedorNotificacionesViewModel

/**
 * Shell móvil del acopiador (mismo lenguaje visual que Proveedor y Calidad):
 * - barra inferior con exactamente 3 pantallas: Inicio, Entregas, Proveedores;
 * - "Registrar entrega" es la acción grande de Inicio (y también está en el menú);
 * - menú lateral (☰, desde la izquierda) agrupado: Operación, Gestión, Comunicación, Sistema;
 * - arriba a la derecha solo el punto de conexión (verde/naranja) y la campana;
 * - sin pantalla de "Sincronización": [AcopiadorSync] envía y descarga solo.
 *
 * Pensado para trabajar en campo sin cobertura: todo se lee de SQLite y todo se guarda primero
 * en el teléfono.
 */
@Composable
fun AcopiadorApp(container: AppContainer, session: UserSession) {
    val connectivity = container.connectivityObserver
    val isOnlineFlow = connectivity.isOnline
    // Regla global: sin asistencia válida hoy se consulta todo, pero no se opera (el servidor valida igual).
    val asistencia = rememberScreenModel(session.userId) {
        AsistenciaGate(AsistenciaApi(container.httpClient), container.cacheRepository, session.userId, connectivity.isOnline)
    }
    val autorizacion by asistencia.estado.collectAsState()
    val repository = remember(session.userId) {
        AcopiadorRepository(
            api = container.acopiadorApi,
            cache = container.cacheRepository,
            syncQueue = container.syncQueueRepository,
            isOnline = { connectivity.isOnline.value },
            userId = session.userId,
            puedeOperarSinConexion = { asistencia.puedeOperarSinConexion() },
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
        AcopiadorSync(repository, notificaciones, container.syncManager, container.syncQueueRepository, connectivity)
    }
    val navigator = remember { Navigator<AcopiadorRoute>(AcopiadorRoute.Inicio) }
    val drawerState = rememberDrawerState(DrawerValue.Closed)
    val scope = rememberCoroutineScope()

    val isOnline by sync.isOnline.collectAsState()
    val aviso by sync.aviso.collectAsState()
    val noLeidas by remember(notificaciones) { notificaciones.observeNoLeidas() }.collectAsState(0)
    val perfil by remember(repository) { repository.observePerfil() }.collectAsState(null)
    val comunicadosNoLeidos by remember(repository) { repository.observeComunicados().map { c -> c?.value?.count { !it.leido } ?: 0 } }.collectAsState(0)

    // Historial compartido por Inicio y Entregas (una sola suscripción a SQLite).
    val entregasVm = rememberScreenModel(session.userId) { AcopiadorEntregasViewModel(repository, isOnlineFlow) }

    val nombre = perfil?.nombreCompleto ?: session.username
    val iniciales = initials(perfil?.nombres, perfil?.apellidos, nombre)
    val subtitulo = listOfNotNull("Acopiador", perfil?.zona_nombre).joinToString(" · ")

    var confirmarLogout by remember { mutableStateOf(false) }
    val cerrarSesion: () -> Unit = { scope.launch { container.authRepository.logout() } }
    val pedirCierre: () -> Unit = { if (isOnline) cerrarSesion() else confirmarLogout = true }

    val current = navigator.current
    var ultimaPrincipal by remember { mutableStateOf<AcopiadorRoute>(AcopiadorRoute.Inicio) }
    LaunchedEffect(current) { if (current.esPrincipal) ultimaPrincipal = current }

    val abrir: (AcopiadorRoute) -> Unit = { route ->
        if (route.esPrincipal) {
            navigator.replaceAll(route)
        } else {
            navigator.replaceAll(ultimaPrincipal)
            navigator.navigate(route)
        }
    }

    PlatformBackHandler(enabled = drawerState.isOpen || navigator.canGoBack || current != AcopiadorRoute.Inicio) {
        when {
            drawerState.isOpen -> scope.launch { drawerState.close() }
            navigator.canGoBack -> navigator.pop()
            else -> navigator.replaceAll(AcopiadorRoute.Inicio)
        }
    }

    ModalNavigationDrawer(
        drawerState = drawerState,
        gesturesEnabled = current.esPrincipal || drawerState.isOpen,
        scrimColor = Color.Black.copy(alpha = 0.32f),
        drawerContent = {
            AcopiadorDrawer(
                nombre = nombre,
                subtitulo = subtitulo,
                initials = iniciales,
                isOnline = isOnline,
                seleccionada = seccionDe(current),
                contadores = mapOf(AcopiadorSeccion.NOTIFICACIONES to noLeidas, AcopiadorSeccion.COMUNICADOS to comunicadosNoLeidos),
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
                Column {
                    ProviderTopBar(
                        title = titulo(current),
                        principal = current.esPrincipal,
                        isOnline = isOnline,
                        noLeidas = noLeidas,
                        mostrarCampana = current != AcopiadorRoute.Notificaciones,
                        onMenu = { scope.launch { drawerState.open() } },
                        onBack = { navigator.pop() },
                        onCampana = { navigator.navigate(AcopiadorRoute.Notificaciones) },
                    )
                    AvisoSincronizacion(aviso)
                }
            },
            bottomBar = {
                if (current.conBarraInferior) {
                    AcopiadorBottomNavigation(
                        seleccionada = AcopiadorTab.entries.firstOrNull { it.route == current },
                        onSeleccionar = { tab -> if (tab.route != current) navigator.replaceAll(tab.route) },
                    )
                }
            },
        ) { padding ->
            CompositionLocalProvider(LocalAutorizacion provides autorizacion) {
                Column(modifier = Modifier.fillMaxSize().padding(padding)) {
                    // Un solo aviso, en Inicio; las acciones se deshabilitan donde están.
                    if (current == AcopiadorRoute.Inicio) {
                        AsistenciaRequeridaBanner(autorizacion, onVerQr = { abrir(AcopiadorRoute.MiQr) }, Modifier.padding(start = 16.dp, end = 16.dp, top = 12.dp))
                    }
                    Box(modifier = Modifier.fillMaxWidth().weight(1f)) {
                when (val route = current) {
                    AcopiadorRoute.Inicio -> AcopiadorInicioScreen(
                        viewModel = entregasVm,
                        perfil = perfil,
                        nombreFallback = session.username,
                        onRegistrar = { navigator.navigate(AcopiadorRoute.Registrar()) },
                        onVerEntrega = { navigator.navigate(AcopiadorRoute.EntregaDetalle(it)) },
                        onVerTodas = { navigator.replaceAll(AcopiadorRoute.Entregas) },
                    )
                    AcopiadorRoute.Entregas -> AcopiadorEntregasScreen(entregasVm) { navigator.navigate(AcopiadorRoute.EntregaDetalle(it)) }
                    AcopiadorRoute.Proveedores -> {
                        val vm = rememberScreenModel { AcopiadorProveedoresViewModel(repository, isOnlineFlow) }
                        AcopiadorProveedoresScreen(vm) { navigator.navigate(AcopiadorRoute.ProveedorDetalle(it)) }
                    }
                    is AcopiadorRoute.Registrar -> {
                        val vm = rememberScreenModel(route) { RegistrarViewModel(repository, route.proveedorId, route.parada) }
                        AcopiadorRegistrarScreen(
                            viewModel = vm,
                            onVerHistorial = { navigator.replaceAll(AcopiadorRoute.Entregas) },
                            onVolverRecorrido = { navigator.pop() },
                            onConfirmadaEnLinea = sync::sincronizarAhora,
                        )
                    }
                    is AcopiadorRoute.EntregaDetalle -> {
                        val vm = rememberScreenModel(route.clave) { AcopiadorEntregaDetalleViewModel(repository, isOnlineFlow, route.clave) }
                        AcopiadorEntregaDetalleScreen(vm, onDescartada = { navigator.pop() })
                    }
                    is AcopiadorRoute.ProveedorDetalle -> {
                        val vm = rememberScreenModel(route.id) { AcopiadorProveedorDetalleViewModel(repository, isOnlineFlow, route.id) }
                        AcopiadorProveedorDetalleScreen(
                            viewModel = vm,
                            onRegistrar = { navigator.navigate(AcopiadorRoute.Registrar(proveedorId = route.id)) },
                            onVerHistorial = { navigator.navigate(AcopiadorRoute.ProveedorHistorial(route.id)) },
                        )
                    }
                    is AcopiadorRoute.ProveedorHistorial -> {
                        val vm = rememberScreenModel(route.id) { AcopiadorEntregasViewModel(repository, isOnlineFlow, proveedorId = route.id) }
                        val nombreProveedor = remember(route.id) { repository.proveedorGuardado(route.id)?.nombre_completo ?: "Proveedor" }
                        AcopiadorProveedorHistorialScreen(vm, nombreProveedor) { navigator.navigate(AcopiadorRoute.EntregaDetalle(it)) }
                    }
                    AcopiadorRoute.Recorrido -> {
                        val vm = rememberScreenModel { AcopiadorRecorridosViewModel(repository, isOnlineFlow) }
                        AcopiadorRecorridoScreen(vm) { navigator.navigate(AcopiadorRoute.RecorridoDetalle(it)) }
                    }
                    is AcopiadorRoute.RecorridoDetalle -> {
                        val vm = rememberScreenModel(route.id) { AcopiadorRecorridoDetalleViewModel(repository, isOnlineFlow, route.id) }
                        AcopiadorRecorridoDetalleScreen(vm) { parada ->
                            navigator.navigate(
                                AcopiadorRoute.Registrar(parada = ParadaRef(route.id, parada.parada.id, parada.parada.proveedor_id)),
                            )
                        }
                    }
                    AcopiadorRoute.Seguimiento -> {
                        val vm = rememberScreenModel { AcopiadorSeguimientoViewModel(repository, isOnlineFlow) }
                        AcopiadorSeguimientoScreen(vm) { navigator.navigate(AcopiadorRoute.EntregaDetalle(claveServidor(it))) }
                    }
                    AcopiadorRoute.Comunicados -> {
                        val vm = rememberScreenModel { AcopiadorComunicadosViewModel(repository, isOnlineFlow) }
                        AcopiadorComunicadosScreen(vm) { navigator.navigate(AcopiadorRoute.ComunicadoDetalle(it)) }
                    }
                    is AcopiadorRoute.ComunicadoDetalle -> {
                        val vm = rememberScreenModel(route.id) { AcopiadorComunicadoDetalleViewModel(repository, isOnlineFlow, route.id) }
                        AcopiadorComunicadoDetalleScreen(vm)
                    }
                    AcopiadorRoute.Notificaciones -> {
                        val vm = rememberScreenModel { ProveedorNotificacionesViewModel(notificaciones, isOnlineFlow) }
                        ProveedorNotificacionesScreen(vm)
                    }
                    AcopiadorRoute.Perfil -> AcopiadorPerfilScreen(session, perfil, nombre, iniciales, pedirCierre)
                    AcopiadorRoute.MiQr -> {
                        val vm = rememberScreenModel { MiQrViewModel(repository.observeAsistencia(), isOnlineFlow) { repository.refreshAsistencia() } }
                        MiQrTrabajadorScreen(vm, rol = "Acopiador", nombreFallback = nombre)
                    }
                }
            }
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
private fun seccionDe(route: AcopiadorRoute): AcopiadorSeccion? = when (route) {
    AcopiadorRoute.Inicio -> AcopiadorSeccion.INICIO
    is AcopiadorRoute.Registrar -> AcopiadorSeccion.REGISTRAR
    AcopiadorRoute.Entregas, is AcopiadorRoute.EntregaDetalle -> AcopiadorSeccion.ENTREGAS
    AcopiadorRoute.Proveedores, is AcopiadorRoute.ProveedorDetalle, is AcopiadorRoute.ProveedorHistorial -> AcopiadorSeccion.PROVEEDORES
    AcopiadorRoute.Recorrido, is AcopiadorRoute.RecorridoDetalle -> AcopiadorSeccion.RECORRIDO
    AcopiadorRoute.Seguimiento -> AcopiadorSeccion.SEGUIMIENTO
    AcopiadorRoute.Comunicados, is AcopiadorRoute.ComunicadoDetalle -> AcopiadorSeccion.COMUNICADOS
    AcopiadorRoute.Notificaciones -> AcopiadorSeccion.NOTIFICACIONES
    AcopiadorRoute.Perfil -> AcopiadorSeccion.PERFIL
    AcopiadorRoute.MiQr -> AcopiadorSeccion.MI_QR
}

private fun titulo(route: AcopiadorRoute): String = when (route) {
    AcopiadorRoute.Inicio -> "Inicio"
    AcopiadorRoute.Entregas -> "Entregas"
    AcopiadorRoute.Proveedores -> "Proveedores"
    is AcopiadorRoute.Registrar -> "Registrar entrega"
    is AcopiadorRoute.EntregaDetalle -> "Detalle de entrega"
    is AcopiadorRoute.ProveedorDetalle -> "Detalle de proveedor"
    is AcopiadorRoute.ProveedorHistorial -> "Historial de proveedor"
    AcopiadorRoute.Recorrido -> "Recorrido"
    is AcopiadorRoute.RecorridoDetalle -> "Paradas del recorrido"
    AcopiadorRoute.Seguimiento -> "Seguimiento de calidad"
    AcopiadorRoute.Comunicados -> "Comunicados"
    is AcopiadorRoute.ComunicadoDetalle -> "Comunicado"
    AcopiadorRoute.Notificaciones -> "Notificaciones"
    AcopiadorRoute.Perfil -> "Mi perfil"
    AcopiadorRoute.MiQr -> "Mi QR / Asistencia"
}
