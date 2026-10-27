package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.session.UserSession
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.InitialsAvatar
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

/** calidad.md no expone un endpoint de perfil propio: se muestra lo de la sesión y el nombre de la ficha del analista. */
@Composable
fun CalidadPerfilScreen(session: UserSession, nombre: String, initials: String, onLogout: () -> Unit) {
    ProveedorPantalla {
        item {
            Column(Modifier.anchoContenido().padding(vertical = 8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                InitialsAvatar(initials, large = true)
                Text(nombre, style = MaterialTheme.typography.titleLarge, modifier = Modifier.padding(top = 12.dp))
                Text("Analista de calidad", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
            }
        }
        item {
            SummaryCard {
                SummaryRow("Usuario", session.username, primera = true)
                SummaryRow("Estado", session.estado.lowercase().replaceFirstChar { it.uppercase() })
            }
        }
        item { BotonSecundario("Cerrar sesión", onLogout) }
    }
}
