package pe.edu.upeu.ecolactmobile.feature.planta.screens

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
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRoute

/** Sin Comunicados: encargado-planta.md confirma que este rol todavía no tiene ese endpoint expuesto. */
@Composable
fun PlantaMasScreen(onNavigate: (PlantaRoute) -> Unit) {
    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        ListItemCard(onClick = { onNavigate(PlantaRoute.Notificaciones) }) {
            Text("🔔 Notificaciones", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(PlantaRoute.Sincronizacion) }) {
            Text("↻ Sincronización", style = MaterialTheme.typography.bodyLarge)
        }
        ListItemCard(onClick = { onNavigate(PlantaRoute.Perfil) }) {
            Text("👤 Mi perfil", style = MaterialTheme.typography.bodyLarge)
        }
    }
}
