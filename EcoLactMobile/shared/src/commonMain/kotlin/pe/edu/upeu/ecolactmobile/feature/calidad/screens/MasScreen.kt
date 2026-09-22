package pe.edu.upeu.ecolactmobile.feature.calidad.screens

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
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRoute

@Composable
fun CalidadMasScreen(onNavigate: (CalidadRoute) -> Unit) {
    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        ListItemCard(onClick = { onNavigate(CalidadRoute.Muestreo) }) {
            Text("🧪 Registrar entrega (muestreo)", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(CalidadRoute.Proveedores) }) {
            Text("👥 Proveedores", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(CalidadRoute.Comunicados) }) {
            Text("📢 Comunicados", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(CalidadRoute.Notificaciones) }) {
            Text("🔔 Notificaciones", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(CalidadRoute.Sincronizacion) }) {
            Text("↻ Sincronización", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(CalidadRoute.Perfil) }) {
            Text("👤 Mi perfil", style = MaterialTheme.typography.bodyLarge)
        }
    }
}
