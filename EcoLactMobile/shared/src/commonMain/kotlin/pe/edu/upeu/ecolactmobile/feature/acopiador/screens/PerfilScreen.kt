package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.session.UserSession
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors

/**
 * acopiador.md no expone un endpoint de perfil propio para este rol (a diferencia de Proveedor):
 * solo se muestra lo que ya trae la sesión de login (usuario, rol, estado). DNI/zona asignada/
 * teléfono que pedía el mockup (acopiador.txt sección 15) no están disponibles todavía — pedir
 * el endpoint aparte si se necesitan.
 */
@Composable
fun AcopiadorPerfilScreen(session: UserSession, onLogout: () -> Unit) {
    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Text(session.username, style = MaterialTheme.typography.titleLarge)
        Text("Acopiador", color = EcoLactColors.TextoSecundario)
        Text("Estado: ${session.estado}", color = EcoLactColors.TextoSecundario)

        Button(
            onClick = onLogout,
            modifier = Modifier.fillMaxWidth().height(48.dp).padding(top = 24.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.Rojo),
        ) {
            Text("Cerrar sesión")
        }
    }
}
