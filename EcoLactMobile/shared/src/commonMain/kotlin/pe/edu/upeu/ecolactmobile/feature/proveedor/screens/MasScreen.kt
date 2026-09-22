package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

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
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRoute

@Composable
fun ProveedorMasScreen(onNavigate: (ProveedorRoute) -> Unit) {
    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        ListItemCard(onClick = { onNavigate(ProveedorRoute.MiCalidad) }) {
            Text("🧪 Mi calidad", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(ProveedorRoute.Comunicados) }) {
            Text("📢 Comunicados", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(ProveedorRoute.MisCompras) }) {
            Text("🛒 Mis compras", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(ProveedorRoute.Notificaciones) }) {
            Text("🔔 Notificaciones", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(ProveedorRoute.MiQr) }) {
            Text("▣ Mi código QR", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(ProveedorRoute.Perfil) }) {
            Text("👤 Mi perfil", style = MaterialTheme.typography.bodyLarge)
        }
    }
}
