package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.theme.StatusColors

enum class ChipTone { EXITO, INFO, ADVERTENCIA, ERROR, NEUTRAL }

@Composable
fun StatusChip(text: String, tone: ChipTone, modifier: Modifier = Modifier) {
    val (bg, fg) = when (tone) {
        ChipTone.EXITO -> StatusColors.ExitoFondo to StatusColors.Exito
        ChipTone.INFO -> StatusColors.InfoFondo to StatusColors.Info
        ChipTone.ADVERTENCIA -> StatusColors.AdvertenciaFondo to StatusColors.Advertencia
        ChipTone.ERROR -> StatusColors.ErrorFondo to StatusColors.Error
        ChipTone.NEUTRAL -> MaterialTheme.colorScheme.surfaceVariant to MaterialTheme.colorScheme.onSurfaceVariant
    }
    Text(
        text = text,
        color = fg,
        style = MaterialTheme.typography.labelLarge,
        modifier = modifier
            .background(bg, RoundedCornerShape(8.dp))
            .padding(horizontal = 10.dp, vertical = 4.dp),
    )
}

/** Resultado de calidad: ACEPTADA (verde) / OBSERVADA (amarillo) / RECHAZADA (rojo). */
@Composable
fun ResultadoCalidadChip(resultado: String?, modifier: Modifier = Modifier) {
    val (label, tone) = when (resultado) {
        "ACEPTADA" -> "🟢 Aceptada" to ChipTone.EXITO
        "OBSERVADA" -> "🟠 Observada" to ChipTone.ADVERTENCIA
        "RECHAZADA" -> "🔴 Rechazada" to ChipTone.ERROR
        else -> "🟡 Pendiente de análisis" to ChipTone.NEUTRAL
    }
    StatusChip(label, tone, modifier)
}

/** Estado de sincronización local, distinto del resultado de negocio (nunca mezclar ambos). */
@Composable
fun SyncStatusChip(status: String, modifier: Modifier = Modifier) {
    val (label, tone) = when (status) {
        "PENDING" -> "🟡 Pendiente de sincronización" to ChipTone.ADVERTENCIA
        "SYNCING" -> "↻ Sincronizando" to ChipTone.INFO
        "SYNCED" -> "✓ Sincronizada" to ChipTone.EXITO
        "NEEDS_REVIEW" -> "⚠ Requiere revisión" to ChipTone.ERROR
        else -> status to ChipTone.NEUTRAL
    }
    StatusChip(label, tone, modifier)
}
