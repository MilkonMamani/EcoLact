package pe.edu.upeu.ecolactmobile.feature.proveedor.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalDrawerSheet
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import pe.edu.upeu.ecolactmobile.core.ui.components.ConnectionDot
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRoute

/** Grupos del menú lateral, en orden. */
enum class GrupoMenu(val titulo: String) { OPERACION("Operación"), GESTION("Gestión"), COMUNICACION("Comunicación"), SISTEMA("Sistema") }

/** Destinos del menú lateral. Inicio/Entregas/Pagos también están en la barra inferior. */
enum class ProveedorSeccion(val route: ProveedorRoute, val label: String, val icon: ImageVector, val grupo: GrupoMenu) {
    INICIO(ProveedorRoute.Inicio, "Inicio", EcoLactIcons.Home, GrupoMenu.OPERACION),
    ENTREGAS(ProveedorRoute.Entregas, "Mis entregas", EcoLactIcons.LocalShipping, GrupoMenu.OPERACION),
    CALIDAD(ProveedorRoute.MiCalidad, "Mi calidad", EcoLactIcons.Science, GrupoMenu.OPERACION),
    PAGOS(ProveedorRoute.Pagos, "Mis pagos", EcoLactIcons.Payments, GrupoMenu.GESTION),
    COMPRAS(ProveedorRoute.MisCompras, "Mis compras", EcoLactIcons.ShoppingBag, GrupoMenu.GESTION),
    COMUNICADOS(ProveedorRoute.Comunicados, "Comunicados", EcoLactIcons.Campaign, GrupoMenu.COMUNICACION),
    NOTIFICACIONES(ProveedorRoute.Notificaciones, "Notificaciones", EcoLactIcons.Notifications, GrupoMenu.COMUNICACION),
    PERFIL(ProveedorRoute.Perfil, "Mi perfil", EcoLactIcons.Person, GrupoMenu.SISTEMA),
    SINCRONIZACION(ProveedorRoute.Sincronizacion, "Sincronización", EcoLactIcons.Sync, GrupoMenu.SISTEMA),
}

/** Pestañas de la barra inferior: solo las acciones frecuentes. */
enum class ProveedorTab(val route: ProveedorRoute, val label: String, val icon: ImageVector) {
    INICIO(ProveedorRoute.Inicio, "Inicio", EcoLactIcons.Home),
    ENTREGAS(ProveedorRoute.Entregas, "Entregas", EcoLactIcons.LocalShipping),
    PAGOS(ProveedorRoute.Pagos, "Pagos", EcoLactIcons.Payments),
}

/** Sin foto de perfil: círculo verde claro con las iniciales ("MM"). */
@Composable
fun InitialsAvatar(initials: String, large: Boolean = false) {
    Box(
        modifier = Modifier
            .size(if (large) 64.dp else 44.dp)
            .background(EcoLactColors.VerdeClaro, CircleShape),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            initials,
            style = if (large) MaterialTheme.typography.titleLarge else MaterialTheme.typography.titleMedium,
            color = EcoLactColors.VerdeOscuro,
        )
    }
}

/**
 * ☰/←   Título                    ●  🔔
 * El punto es la conexión; la campana tiene su propio punto para notificaciones sin leer.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProviderTopBar(
    title: String,
    principal: Boolean,
    isOnline: Boolean,
    noLeidas: Int,
    mostrarCampana: Boolean,
    onMenu: () -> Unit,
    onBack: () -> Unit,
    onCampana: () -> Unit,
) {
    Column {
        TopAppBar(
            title = {
                Text(title, style = MaterialTheme.typography.titleMedium, maxLines = 1, overflow = TextOverflow.Ellipsis)
            },
            navigationIcon = {
                if (principal) {
                    IconButton(onClick = onMenu) {
                        Icon(EcoLactIcons.Menu, contentDescription = "Abrir menú", tint = EcoLactColors.TextoPrincipal)
                    }
                } else {
                    IconButton(onClick = onBack) {
                        Icon(EcoLactIcons.ArrowBack, contentDescription = "Volver", tint = EcoLactColors.TextoPrincipal)
                    }
                }
            },
            actions = {
                ConnectionDot(isOnline, Modifier.padding(horizontal = 8.dp))
                if (mostrarCampana) {
                    NotificationBell(noLeidas, onCampana)
                } else {
                    Spacer(Modifier.width(8.dp))
                }
            },
            colors = TopAppBarDefaults.topAppBarColors(
                containerColor = EcoLactColors.Blanco,
                titleContentColor = EcoLactColors.TextoPrincipal,
            ),
        )
        HorizontalDivider(color = EcoLactColors.Bordes)
    }
}

/** Campana con un punto verde cuando hay notificaciones sin leer (distinto del punto de conexión). */
@Composable
fun NotificationBell(noLeidas: Int, onClick: () -> Unit) {
    val descripcion = if (noLeidas > 0) "Notificaciones, $noLeidas sin leer" else "Notificaciones"
    IconButton(onClick = onClick, modifier = Modifier.semantics { contentDescription = descripcion }) {
        Box {
            Icon(EcoLactIcons.Notifications, contentDescription = null, tint = EcoLactColors.TextoPrincipal)
            if (noLeidas > 0) {
                Box(
                    Modifier
                        .align(Alignment.TopEnd)
                        .offset(x = 1.dp, y = (-1).dp)
                        .size(9.dp)
                        .background(EcoLactColors.Blanco, CircleShape)
                        .padding(1.5.dp)
                        .background(EcoLactColors.VerdePrincipal, CircleShape),
                )
            }
        }
    }
}

@Composable
fun ProviderDrawer(
    nombre: String,
    subtitulo: String,
    initials: String,
    isOnline: Boolean,
    seleccionada: ProveedorSeccion?,
    noLeidas: Map<ProveedorSeccion, Int>,
    onSeleccionar: (ProveedorSeccion) -> Unit,
    onCerrarSesion: () -> Unit,
) {
    // ~80% del ancho; ModalDrawerSheet lo limita a su ancho máximo en pantallas grandes.
    ModalDrawerSheet(
        modifier = Modifier.fillMaxWidth(0.8f),
        drawerContainerColor = EcoLactColors.Blanco,
        drawerShape = RoundedCornerShape(topEnd = 16.dp, bottomEnd = 16.dp),
    ) {
        Column(modifier = Modifier.fillMaxHeight()) {
            Row(
                modifier = Modifier.fillMaxWidth().padding(start = 20.dp, end = 20.dp, top = 12.dp, bottom = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                InitialsAvatar(initials)
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        nombre,
                        style = MaterialTheme.typography.titleMedium,
                        color = EcoLactColors.TextoPrincipal,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                    Text(
                        subtitulo,
                        style = MaterialTheme.typography.bodyMedium,
                        color = EcoLactColors.TextoSecundario,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                ConnectionDot(isOnline)
            }
            HorizontalDivider(color = EcoLactColors.Bordes)

            Column(modifier = Modifier.weight(1f).verticalScroll(rememberScrollState()).padding(vertical = 4.dp)) {
                GrupoMenu.entries.forEach { grupo ->
                    DrawerSectionHeader(grupo.titulo)
                    ProveedorSeccion.entries.filter { it.grupo == grupo }.forEach { seccion ->
                        DrawerItem(
                            label = seccion.label,
                            icon = seccion.icon,
                            selected = seccion == seleccionada,
                            contador = noLeidas[seccion] ?: 0,
                            onClick = { onSeleccionar(seccion) },
                        )
                    }
                }
            }

            HorizontalDivider(color = EcoLactColors.Bordes)
            DrawerItem(
                label = "Cerrar sesión",
                icon = EcoLactIcons.Logout,
                selected = false,
                onClick = onCerrarSesion,
                tint = EcoLactColors.Rojo,
                modifier = Modifier.navigationBarsPadding().padding(vertical = 8.dp),
            )
        }
    }
}

/** Título de grupo del menú lateral ("OPERACIÓN"). Compartido por las shells de cada rol. */
@Composable
fun DrawerSectionHeader(text: String) {
    Text(
        text.uppercase(),
        style = MaterialTheme.typography.labelSmall.copy(letterSpacing = 0.8.sp),
        color = EcoLactColors.TextoSecundario.copy(alpha = 0.8f),
        modifier = Modifier.padding(start = 28.dp, end = 24.dp, top = 12.dp, bottom = 4.dp),
    )
}

/** Opción del menú lateral: ícono + texto, fondo verde suave si está activa. Compartido por las shells de cada rol. */
@Composable
fun DrawerItem(
    label: String,
    icon: ImageVector,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    contador: Int = 0,
    tint: Color? = null,
) {
    val textColor = tint ?: if (selected) EcoLactColors.VerdeOscuro else EcoLactColors.TextoPrincipal
    val iconColor = tint ?: if (selected) EcoLactColors.VerdePrincipal else EcoLactColors.TextoSecundario
    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 0.dp)
            .heightIn(min = 48.dp)
            .background(if (selected) EcoLactColors.VerdeClaro else Color.Transparent, RoundedCornerShape(12.dp))
            .selectable(selected = selected, role = Role.Tab, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Icon(icon, contentDescription = null, tint = iconColor, modifier = Modifier.size(22.dp))
        Text(
            label,
            style = MaterialTheme.typography.bodyLarge,
            fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Normal,
            color = textColor,
            modifier = Modifier.weight(1f),
        )
        if (contador > 0) {
            Text(
                if (contador > 99) "99+" else "$contador",
                style = MaterialTheme.typography.labelSmall,
                color = EcoLactColors.Blanco,
                modifier = Modifier
                    .semantics { contentDescription = "$contador sin leer" }
                    .background(EcoLactColors.VerdePrincipal, RoundedCornerShape(50))
                    .padding(horizontal = 7.dp, vertical = 2.dp),
            )
        }
    }
}

/** Barra inferior blanca, compacta, con borde superior fino y sin sombra. */
@Composable
fun ProviderBottomNavigation(seleccionada: ProveedorTab?, onSeleccionar: (ProveedorTab) -> Unit) {
    Column(modifier = Modifier.fillMaxWidth().background(EcoLactColors.Blanco)) {
        HorizontalDivider(color = EcoLactColors.Bordes)
        Row(modifier = Modifier.fillMaxWidth().navigationBarsPadding().height(64.dp)) {
            ProveedorTab.entries.forEach { tab ->
                val selected = tab == seleccionada
                val color = if (selected) EcoLactColors.VerdePrincipal else EcoLactColors.TextoSecundario
                Column(
                    modifier = Modifier
                        .weight(1f)
                        .fillMaxHeight()
                        .selectable(selected = selected, role = Role.Tab, onClick = { onSeleccionar(tab) }),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center,
                ) {
                    Box(
                        modifier = Modifier
                            .size(width = 56.dp, height = 30.dp)
                            .background(if (selected) EcoLactColors.VerdeClaro else Color.Transparent, RoundedCornerShape(15.dp)),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(tab.icon, contentDescription = null, tint = color, modifier = Modifier.size(22.dp))
                    }
                    Text(
                        tab.label,
                        style = MaterialTheme.typography.labelMedium,
                        fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium,
                        color = if (selected) EcoLactColors.VerdeOscuro else EcoLactColors.TextoSecundario,
                        modifier = Modifier.padding(top = 2.dp),
                    )
                }
            }
        }
    }
}

