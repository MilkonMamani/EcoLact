package pe.edu.upeu.ecolactmobile.feature.ventas

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.session.UserSession
import pe.edu.upeu.ecolactmobile.core.ui.components.ConnectionDot
import pe.edu.upeu.ecolactmobile.core.ui.components.rememberPdfAcciones
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.navigation.*
import pe.edu.upeu.ecolactmobile.core.ui.state.rememberScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.initials
import pe.edu.upeu.ecolactmobile.di.AppContainer
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.*
import pe.edu.upeu.ecolactmobile.feature.proveedor.screens.*
import pe.edu.upeu.ecolactmobile.feature.trabajador.*
import pe.edu.upeu.ecolactmobile.feature.ventas.screens.*

@Composable
fun VentasApp(container: AppContainer, session: UserSession) {
    val onlineFlow = container.connectivityObserver.isOnline
    val online by onlineFlow.collectAsState()
    val gate = rememberScreenModel(session.userId) { AsistenciaGate(AsistenciaApi(container.httpClient), container.cacheRepository, session.userId, onlineFlow) }
    val autorizacion by gate.estado.collectAsState()
    val repository = remember(session.userId) { VentasRepository(container.ventasApi, container.cacheRepository, container.syncQueueRepository, session.userId, { onlineFlow.value }, gate) }
    val vm = rememberScreenModel(session.userId) { VentasViewModel(repository, container.syncManager, onlineFlow) }
    val notificaciones = remember(session.userId) { NotificacionesRepository(container.notificacionesApi, container.cacheRepository, container.syncQueueRepository, session.userId) { onlineFlow.value } }
    val notificacionesVm = rememberScreenModel(session.userId) { ProveedorNotificacionesViewModel(notificaciones, onlineFlow) }
    val noLeidas by remember(notificaciones) { notificaciones.observeNoLeidas() }.collectAsState(0)
    val perfil by vm.perfil.collectAsState()
    val ventas by vm.ventas.collectAsState()
    val catalogo by vm.catalogo.collectAsState()
    val compradores by vm.compradores.collectAsState()
    val comunicados by vm.comunicados.collectAsState()
    val error by vm.error.collectAsState()
    val busy by vm.busy.collectAsState()
    val loading by vm.loading.collectAsState()
    val quote by vm.cotizacion.collectAsState()
    val resultado by vm.resultado.collectAsState()
    val cantidad by vm.cantidad.collectAsState()
    val formaPago by vm.formaPago.collectAsState()
    val nombre = perfil?.value?.nombreCompleto ?: session.username
    val iniciales = initials(perfil?.value?.nombres, perfil?.value?.apellidos, nombre)
    val navigator = remember(session.userId) { Navigator(VentasRoute.INICIO) }
    val drawer = rememberDrawerState(DrawerValue.Closed)
    val scope = rememberCoroutineScope()
    val pdf = rememberPdfAcciones()
    var producto by remember { mutableStateOf<ProductoVenta?>(null) }
    var cliente by remember { mutableStateOf<ClienteVenta?>(null) }
    var tipoCliente by remember { mutableStateOf("PUBLICO") }
    var seleccion by remember { mutableStateOf<Venta?>(null) }
    var confirmarSalida by remember { mutableStateOf(false) }
    val abrir: (VentasRoute) -> Unit = { route ->
        if (route.principal) navigator.replaceAll(route) else navigator.navigate(route)
        scope.launch { drawer.close() }
    }
    LaunchedEffect(resultado?.localId) {
        if (resultado != null) { seleccion = resultado; navigator.replaceAll(VentasRoute.INICIO); navigator.navigate(VentasRoute.CONFIRMACION) }
    }
    val actual = seleccion?.let { original -> ventas.find { (original.localId != null && it.localId == original.localId) || (original.id > 0 && it.id == original.id) } ?: original }
    val resolved = actual
    PlatformBackHandler(drawer.isOpen || navigator.canGoBack || navigator.current != VentasRoute.INICIO) {
        if (drawer.isOpen) scope.launch { drawer.close() } else if (!navigator.pop()) navigator.replaceAll(VentasRoute.INICIO)
    }
    CompositionLocalProvider(LocalAutorizacion provides autorizacion) {
        ModalNavigationDrawer(drawerState = drawer, drawerContent = {
            ModalDrawerSheet(Modifier.fillMaxWidth(0.85f), drawerContainerColor = EcoLactColors.Blanco) {
                Row(Modifier.padding(20.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    InitialsAvatar(iniciales)
                    Column(Modifier.weight(1f)) { Text(nombre, style = MaterialTheme.typography.titleMedium); Text("Personal de ventas", style = MaterialTheme.typography.bodySmall) }
                    ConnectionDot(online)
                }
                HorizontalDivider()
                Column(Modifier.weight(1f).verticalScroll(rememberScrollState())) {
                    listOf(
                        "OPERACIÓN" to listOf(VentasRoute.INICIO, VentasRoute.VENDER, VentasRoute.HISTORIAL),
                        "GESTIÓN" to listOf(VentasRoute.COMPRADORES, VentasRoute.PRODUCTOS),
                        "COMUNICACIÓN" to listOf(VentasRoute.COMUNICADOS, VentasRoute.NOTIFICACIONES),
                        "SISTEMA" to listOf(VentasRoute.QR, VentasRoute.PERFIL),
                    ).forEach { (grupo, rutas) ->
                        DrawerSectionHeader(grupo)
                        rutas.forEach { r -> DrawerItem(if (r == VentasRoute.HISTORIAL) "Historial de ventas" else r.titulo, icono(r), navigator.current == r, onClick = { abrir(r) }, contador = if (r == VentasRoute.NOTIFICACIONES) noLeidas else 0) }
                    }
                }
                HorizontalDivider()
                DrawerItem("Cerrar sesión", EcoLactIcons.Logout, false, onClick = { confirmarSalida = true }, tint = EcoLactColors.Rojo)
            }
        }) {
            Scaffold(containerColor = EcoLactColors.Fondo, topBar = {
                ProviderTopBar(navigator.current.titulo, navigator.current.principal, online, noLeidas, navigator.current != VentasRoute.NOTIFICACIONES,
                    onMenu = { scope.launch { drawer.open() } }, onBack = { navigator.pop() }, onCampana = { navigator.navigate(VentasRoute.NOTIFICACIONES) })
            }, bottomBar = {
                if (navigator.current.principal) VentasBottomNavigation(navigator.current) { abrir(it) }
            }) { padding ->
                Column(Modifier.fillMaxSize().padding(padding)) {
                    if (loading || busy) LinearProgressIndicator(Modifier.fillMaxWidth())
                    error?.let { Text(it, color = EcoLactColors.Rojo, style = MaterialTheme.typography.bodySmall, modifier = Modifier.fillMaxWidth().background(EcoLactColors.RojoClaro).padding(horizontal = 16.dp, vertical = 10.dp)) }
                    Box(Modifier.weight(1f)) {
                        when (navigator.current) {
                            VentasRoute.INICIO -> VentasInicioScreen(nombre, ventas, autorizacion, { abrir(VentasRoute.VENDER) }, { abrir(VentasRoute.HISTORIAL) }, { abrir(VentasRoute.QR) }) { seleccion = it; abrir(VentasRoute.DETALLE) }
                            VentasRoute.PRODUCTOS -> VentasProductosScreen(catalogo?.value?.productos.orEmpty(), online, catalogo?.cachedAtIso)
                            VentasRoute.VENDER -> VentasCatalogoScreen(catalogo?.value?.productos.orEmpty(), online, catalogo?.cachedAtIso) {
                                producto = it; cliente = null; tipoCliente = "PUBLICO"; vm.limpiarVenta(); abrir(VentasRoute.CLIENTE)
                            }
                            VentasRoute.CLIENTE -> VentasClienteScreen(producto, compradores?.value.orEmpty(), catalogo?.value?.proveedores.orEmpty(), onNuevo = { abrir(VentasRoute.NUEVO_COMPRADOR) }) { tipo, c ->
                                tipoCliente = tipo; cliente = c; vm.cambiarPago("CONTADO"); abrir(VentasRoute.RESUMEN)
                            }
                            VentasRoute.RESUMEN -> producto?.let { p -> VentasResumenScreen(p, tipoCliente, cliente, quote, busy, online, cantidad, formaPago,
                                onCantidad = vm::cambiarCantidad, onPago = vm::cambiarPago,
                                onCotizar = { cantidad, pago -> vm.cotizar(VentaRequest(p.id, cantidad, tipoCliente, if (tipoCliente == "PROVEEDOR") cliente?.id else null, if (tipoCliente == "COMPRADOR") cliente?.id else null, pago), p) },
                                onRegistrar = { vm.registrar(p, cliente, nombre) }) }
                            VentasRoute.CONFIRMACION -> resolved?.let { v -> VentasConfirmacionScreen(v, busy, { seleccion = v; abrir(VentasRoute.BOLETA) }, { vm.limpiarVenta(); abrir(VentasRoute.VENDER) }, { abrir(VentasRoute.HISTORIAL) }) }
                            VentasRoute.HISTORIAL -> VentasHistorialScreen(ventas) { seleccion = it; abrir(VentasRoute.DETALLE) }
                            VentasRoute.DETALLE -> resolved?.let { v -> VentasDetalleScreen(v) { seleccion = v; abrir(VentasRoute.BOLETA) } }
                            VentasRoute.BOLETA -> resolved?.takeIf { it.boletaDisponible }?.let { v ->
                                VentasBoletaScreen(v, busy,
                                    descargar = { vm.boletaPdf(v) { pdf.descargar(it, v.nombreArchivoBoleta) } },
                                    compartir = { vm.boletaPdf(v) { pdf.compartir(it, v.nombreArchivoBoleta, v.textoCompartirBoleta) } })
                            }
                            VentasRoute.COMPRADORES -> VentasCompradoresScreen(compradores?.value.orEmpty()) { abrir(VentasRoute.NUEVO_COMPRADOR) }
                            VentasRoute.NUEVO_COMPRADOR -> VentasNuevoCompradorScreen(busy, online) { datos -> vm.nuevoComprador(datos) { navigator.pop() } }
                            VentasRoute.QR -> MiQrTrabajadorContenido(nombre, iniciales, "Personal de ventas", perfil?.value?.dni, perfil?.value?.qr_token, "No hay QR guardado. Conéctate o consulta con el administrador.")
                            VentasRoute.PERFIL -> VentasPerfilScreen(nombre, iniciales, session.username, perfil?.value?.dni) { confirmarSalida = true }
                            VentasRoute.COMUNICADOS -> VentasComunicadosScreen(comunicados?.value.orEmpty())
                            VentasRoute.NOTIFICACIONES -> ProveedorNotificacionesScreen(notificacionesVm)
                        }
                    }
                }
            }
        }
    }
    if (confirmarSalida) AlertDialog(onDismissRequest = { confirmarSalida = false }, title = { Text("Cerrar sesión") },
        text = { Text("Las ventas pendientes permanecerán en este dispositivo para enviarse cuando vuelvas a entrar con esta cuenta.") },
        confirmButton = { TextButton(onClick = { scope.launch { container.authRepository.logout() } }) { Text("Cerrar sesión") } },
        dismissButton = { TextButton(onClick = { confirmarSalida = false }) { Text("Cancelar") } })
}

private fun icono(r: VentasRoute) = when (r) {
    VentasRoute.INICIO -> EcoLactIcons.Home
    VentasRoute.VENDER -> EcoLactIcons.ShoppingCart
    VentasRoute.HISTORIAL -> EcoLactIcons.History
    VentasRoute.COMUNICADOS -> EcoLactIcons.Campaign
    VentasRoute.PRODUCTOS -> EcoLactIcons.Inventory
    VentasRoute.COMPRADORES -> EcoLactIcons.People
    VentasRoute.PERFIL -> EcoLactIcons.Person
    VentasRoute.QR -> EcoLactIcons.QrCode
    VentasRoute.NOTIFICACIONES -> EcoLactIcons.Notifications
    else -> EcoLactIcons.Article
}

/** Inicio · Vender · Historial, con el mismo aspecto que la barra inferior de los otros roles. */
@Composable
private fun VentasBottomNavigation(actual: VentasRoute, onSeleccionar: (VentasRoute) -> Unit) {
    Column(Modifier.fillMaxWidth().background(EcoLactColors.Blanco)) {
        HorizontalDivider(color = EcoLactColors.Bordes)
        Row(Modifier.fillMaxWidth().navigationBarsPadding().height(64.dp)) {
            listOf(VentasRoute.INICIO to "Inicio", VentasRoute.VENDER to "Vender", VentasRoute.HISTORIAL to "Historial").forEach { (r, label) ->
                val selected = r == actual
                Column(
                    Modifier.weight(1f).fillMaxHeight().selectable(selected = selected, role = Role.Tab, onClick = { onSeleccionar(r) }),
                    horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center,
                ) {
                    Box(Modifier.size(width = 56.dp, height = 30.dp).background(if (selected) EcoLactColors.VerdeClaro else Color.Transparent, RoundedCornerShape(15.dp)), contentAlignment = Alignment.Center) {
                        Icon(icono(r), contentDescription = null, tint = if (selected) EcoLactColors.VerdePrincipal else EcoLactColors.TextoSecundario, modifier = Modifier.size(22.dp))
                    }
                    Text(label, style = MaterialTheme.typography.labelMedium, fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium,
                        color = if (selected) EcoLactColors.VerdeOscuro else EcoLactColors.TextoSecundario, modifier = Modifier.padding(top = 2.dp))
                }
            }
        }
    }
}
