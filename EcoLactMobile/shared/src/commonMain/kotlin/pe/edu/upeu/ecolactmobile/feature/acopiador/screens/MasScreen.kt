package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.ListItemCard
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRoute

@Composable
fun AcopiadorMasScreen(onNavigate: (AcopiadorRoute) -> Unit) {
    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        ListItemCard(onClick = { onNavigate(AcopiadorRoute.Proveedores) }) {
            Text("👥 Proveedores de mi zona", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(AcopiadorRoute.Comunicados) }) {
            Text("📢 Comunicados", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(AcopiadorRoute.Notificaciones) }) {
            Text("🔔 Notificaciones", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(AcopiadorRoute.Sincronizacion) }) {
            Text("↻ Sincronización", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(AcopiadorRoute.Perfil) }) {
            Text("👤 Mi perfil", style = MaterialTheme.typography.bodyLarge)
        }
    }
}
