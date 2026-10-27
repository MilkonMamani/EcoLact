package pe.edu.upeu.ecolactmobile.feature.calidad.components

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalDrawerSheet
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.ConnectionDot
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRoute
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.DrawerItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.DrawerSectionHeader
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.InitialsAvatar

/** Grupos del menú lateral, en orden. */
enum class GrupoMenuCalidad(val titulo: String) { OPERACION("Operación"), CONSULTA("Consulta"), COMUNICACION("Comunicación"), SISTEMA("Sistema") }

/** Destinos del menú lateral. Sin "Sincronización": es automática. */
enum class CalidadSeccion(val route: CalidadRoute, val label: String, val icon: ImageVector, val grupo: GrupoMenuCalidad) {
    INICIO(CalidadRoute.Inicio, "Inicio", EcoLactIcons.Home, GrupoMenuCalidad.OPERACION),
    MUESTREO(CalidadRoute.Muestreo, "Muestreo", EcoLactIcons.Science, GrupoMenuCalidad.OPERACION),
    PENDIENTES(CalidadRoute.Pendientes, "Muestras pendientes", EcoLactIcons.Assignment, GrupoMenuCalidad.OPERACION),
    HISTORIAL(CalidadRoute.Historial, "Historial de análisis", EcoLactIcons.History, GrupoMenuCalidad.OPERACION),
    PROVEEDORES(CalidadRoute.Proveedores, "Proveedores", EcoLactIcons.People, GrupoMenuCalidad.CONSULTA),
    COMUNICADOS(CalidadRoute.Comunicados, "Comunicados", EcoLactIcons.Campaign, GrupoMenuCalidad.COMUNICACION),
    NOTIFICACIONES(CalidadRoute.Notificaciones, "Notificaciones", EcoLactIcons.Notifications, GrupoMenuCalidad.COMUNICACION),
    MI_QR(CalidadRoute.MiQr, "Mi QR", EcoLactIcons.QrCode, GrupoMenuCalidad.SISTEMA),
    PERFIL(CalidadRoute.Perfil, "Mi perfil", EcoLactIcons.Person, GrupoMenuCalidad.SISTEMA),
}

/** Pestañas de la barra inferior: exactamente tres. */
enum class CalidadTab(val route: CalidadRoute, val label: String, val icon: ImageVector) {
    INICIO(CalidadRoute.Inicio, "Inicio", EcoLactIcons.Home),
    MUESTREO(CalidadRoute.Muestreo, "Muestreo", EcoLactIcons.Science),
    HISTORIAL(CalidadRoute.Historial, "Historial", EcoLactIcons.History),
}

@Composable
fun CalidadDrawer(
    nombre: String,
    initials: String,
    isOnline: Boolean,
    seleccionada: CalidadSeccion?,
    contadores: Map<CalidadSeccion, Int>,
    onSeleccionar: (CalidadSeccion) -> Unit,
    onCerrarSesion: () -> Unit,
) {
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
                    Text(nombre, style = MaterialTheme.typography.titleMedium, color = EcoLactColors.TextoPrincipal, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    Text("Analista de calidad", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, maxLines = 1)
                }
                ConnectionDot(isOnline)
            }
            HorizontalDivider(color = EcoLactColors.Bordes)

            Column(modifier = Modifier.weight(1f).verticalScroll(rememberScrollState()).padding(vertical = 4.dp)) {
                GrupoMenuCalidad.entries.forEach { grupo ->
                    DrawerSectionHeader(grupo.titulo)
                    CalidadSeccion.entries.filter { it.grupo == grupo }.forEach { seccion ->
                        DrawerItem(
                            label = seccion.label,
                            icon = seccion.icon,
                            selected = seccion == seleccionada,
                            contador = contadores[seccion] ?: 0,
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

/** Barra inferior blanca, compacta, borde superior fino; activa = verde con fondo verde muy suave. */
@Composable
fun CalidadBottomNavigation(seleccionada: CalidadTab?, onSeleccionar: (CalidadTab) -> Unit) {
    Column(modifier = Modifier.fillMaxWidth().background(EcoLactColors.Blanco)) {
        HorizontalDivider(color = EcoLactColors.Bordes)
        Row(modifier = Modifier.fillMaxWidth().navigationBarsPadding().height(64.dp)) {
            CalidadTab.entries.forEach { tab ->
                val selected = tab == seleccionada
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
                        Icon(
                            tab.icon,
                            contentDescription = null,
                            tint = if (selected) EcoLactColors.VerdePrincipal else EcoLactColors.TextoPrincipal.copy(alpha = 0.75f),
                            modifier = Modifier.size(22.dp),
                        )
                    }
                    Text(
                        tab.label,
                        style = MaterialTheme.typography.labelMedium,
                        fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium,
                        color = if (selected) EcoLactColors.VerdeOscuro else EcoLactColors.TextoPrincipal.copy(alpha = 0.75f),
                        modifier = Modifier.padding(top = 2.dp),
                    )
                }
            }
        }
    }
}

/** "Enviando análisis…" / "Sincronización completada": aviso temporal bajo la barra superior. */
@Composable
fun AvisoSincronizacion(texto: String?) {
    AnimatedVisibility(visible = texto != null) {
        Row(
            modifier = Modifier.fillMaxWidth().background(EcoLactColors.VerdeClaro).padding(horizontal = 16.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            if (texto == "Sincronización completada") {
                Icon(EcoLactIcons.CheckCircle, contentDescription = null, tint = EcoLactColors.VerdePrincipal, modifier = Modifier.size(16.dp))
            } else {
                CircularProgressIndicator(modifier = Modifier.size(14.dp), strokeWidth = 2.dp, color = EcoLactColors.VerdePrincipal)
            }
            Text(texto.orEmpty(), style = MaterialTheme.typography.bodySmall, color = EcoLactColors.VerdeOscuro)
        }
    }
}
