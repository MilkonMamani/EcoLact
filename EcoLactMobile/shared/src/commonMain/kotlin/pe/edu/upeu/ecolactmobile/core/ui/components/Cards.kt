package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors

/** Tarjeta KPI compacta usada en Inicio de todos los roles (ej. "128.5 L · Esta semana"). */
@Composable
fun KpiCard(
    icon: String,
    value: String,
    label: String,
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(EcoLactColors.Blanco, RoundedCornerShape(12.dp))
            .border(1.dp, EcoLactColors.Bordes, RoundedCornerShape(12.dp))
            .let { if (onClick != null) it.clickable(onClick = onClick) else it }
            .padding(16.dp),
    ) {
        Text("$icon $value", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.TextoPrincipal)
        Text(label, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
    }
}

@Composable
fun KpiRow(content: @Composable RowScope.() -> Unit) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = androidx.compose.foundation.layout.Arrangement.spacedBy(12.dp),
        content = content,
    )
}

/** Tarjeta de item de lista con chevron ">" a la derecha, base de casi todas las listas de la app. */
@Composable
fun ListItemCard(
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .background(EcoLactColors.Blanco, RoundedCornerShape(12.dp))
            .border(1.dp, EcoLactColors.Bordes, RoundedCornerShape(12.dp))
            .let { if (onClick != null) it.clickable(onClick = onClick) else it }
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(modifier = Modifier.weight(1f), content = content)
        if (onClick != null) {
            Text(">", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.TextoSecundario)
        }
    }
}
