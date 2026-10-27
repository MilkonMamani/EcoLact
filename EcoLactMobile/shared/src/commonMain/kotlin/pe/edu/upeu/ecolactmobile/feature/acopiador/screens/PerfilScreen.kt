package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.session.UserSession
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorPerfil
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.InitialsAvatar
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

/** Datos del acopiador desde la copia local del dashboard (también sin conexión). */
@Composable
fun AcopiadorPerfilScreen(session: UserSession, perfil: AcopiadorPerfil?, nombre: String, iniciales: String, onCerrarSesion: () -> Unit) {
    ProveedorPantalla {
        item {
            Column(Modifier.anchoContenido(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(6.dp)) {
                InitialsAvatar(iniciales, large = true)
                Text(nombre, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal, textAlign = TextAlign.Center)
                Text(listOfNotNull("Acopiador", perfil?.zona_nombre).joinToString(" · "), style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
            }
        }
        item {
            SummaryCard {
                SummaryRow("Usuario", session.username, primera = true)
                perfil?.dni?.let { SummaryRow("DNI", it) }
                perfil?.telefono?.takeIf { it.isNotBlank() }?.let { SummaryRow("Teléfono", it) }
                SummaryRow("Zona asignada", perfil?.zona_nombre ?: "—")
                SummaryRow("Estado", if ((perfil?.estado ?: session.estado) == "ACTIVO") "Activo" else (perfil?.estado ?: session.estado))
            }
        }
        item { BotonSecundario("Cerrar sesión", onCerrarSesion, icon = EcoLactIcons.Logout) }
    }
}
