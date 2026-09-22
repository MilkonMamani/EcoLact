package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors

@Composable
fun LoadingBox(modifier: Modifier = Modifier) {
    Box(modifier = modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        CircularProgressIndicator(color = EcoLactColors.VerdePrincipal)
    }
}

@Composable
fun ErrorBox(message: String, onRetry: (() -> Unit)? = null, modifier: Modifier = Modifier) {
    Column(
        modifier = modifier.fillMaxSize().padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = androidx.compose.foundation.layout.Arrangement.Center,
    ) {
        Text("⚠", style = MaterialTheme.typography.titleLarge, color = EcoLactColors.Rojo)
        Text(message, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoPrincipal)
        if (onRetry != null) {
            Button(onClick = onRetry, modifier = Modifier.padding(top = 12.dp)) {
                Text("Reintentar")
            }
        }
    }
}

@Composable
fun EmptyBox(message: String, modifier: Modifier = Modifier) {
    Box(modifier = modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Text(message, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
    }
}

/** "Sin conexión · mostrando datos guardados hasta las..." — banner consistente en toda la app. */
@Composable
fun OfflineBanner(cachedAtLabel: String?, modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .background(EcoLactColors.AmarilloClaro, RoundedCornerShape(8.dp))
            .padding(12.dp),
    ) {
        Text(
            text = "● Sin conexión" + if (cachedAtLabel != null) "\nDatos actualizados hasta $cachedAtLabel" else "\nMostrando datos guardados",
            color = EcoLactColors.TextoPrincipal,
            style = MaterialTheme.typography.bodyMedium,
        )
    }
}

@Composable
fun SectionTitle(text: String, modifier: Modifier = Modifier) {
    Text(
        text = text,
        style = MaterialTheme.typography.titleMedium,
        color = EcoLactColors.TextoPrincipal,
        modifier = modifier.padding(bottom = 8.dp),
    )
}
