package pe.edu.upeu.ecolactmobile.feature.proveedor.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatDiaMes
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono

/** En teléfonos grandes o en horizontal el contenido no se estira más allá de esto. */
private val ANCHO_MAXIMO = 600.dp
private val RADIO = 12.dp

fun Modifier.anchoContenido(): Modifier = this.widthIn(max = ANCHO_MAXIMO).fillMaxWidth()

fun Modifier.contenedorBlanco(): Modifier = this
    .background(EcoLactColors.Blanco, RoundedCornerShape(RADIO))
    .border(1.dp, EcoLactColors.Bordes, RoundedCornerShape(RADIO))

/** Lista vertical estándar de las pantallas del proveedor: márgenes, ancho máximo y scroll. */
@Composable
fun ProveedorPantalla(
    modifier: Modifier = Modifier,
    verticalSpacing: Arrangement.Vertical = Arrangement.spacedBy(16.dp),
    content: LazyListScope.() -> Unit,
) {
    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 16.dp, bottom = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = verticalSpacing,
        content = content,
    )
}

/**
 * Despacha los tres estados de una pantalla offline-first. Con datos locales siempre se llega a
 * [content]; [UiState.Error] solo aparece si nunca hubo datos en el teléfono.
 */
@Composable
fun <T> ContenidoLocal(state: UiState<T>, onRetry: () -> Unit, content: @Composable (UiState.Success<T>) -> Unit) {
    when (state) {
        is UiState.Loading -> CargandoBox()
        is UiState.Error -> EstadoError(state.message, onRetry)
        is UiState.Success -> content(state)
    }
}

@Composable
fun CargandoBox() {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        CircularProgressIndicator(color = EcoLactColors.VerdePrincipal)
    }
}

@Composable
fun EstadoError(mensaje: String, onRetry: (() -> Unit)?) {
    Column(
        modifier = Modifier.fillMaxSize().padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(12.dp, Alignment.CenterVertically),
    ) {
        Icon(EcoLactIcons.CloudOff, contentDescription = null, tint = EcoLactColors.TextoSecundario, modifier = Modifier.size(40.dp))
        Text(mensaje, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, textAlign = TextAlign.Center)
        if (onRetry != null) {
            TextButton(onClick = onRetry) { Text("Reintentar", color = EcoLactColors.VerdeOscuro) }
        }
    }
}

@Composable
fun EstadoVacio(mensaje: String, modifier: Modifier = Modifier) {
    Text(
        mensaje,
        style = MaterialTheme.typography.bodyMedium,
        color = EcoLactColors.TextoSecundario,
        textAlign = TextAlign.Center,
        modifier = modifier.anchoContenido().padding(vertical = 32.dp),
    )
}

@Composable
fun SectionHeader(text: String, modifier: Modifier = Modifier) {
    Text(
        text,
        style = MaterialTheme.typography.titleSmall,
        fontWeight = FontWeight.SemiBold,
        color = EcoLactColors.TextoPrincipal,
        modifier = modifier.anchoContenido().padding(bottom = 8.dp),
    )
}

/** Un solo contenedor blanco con filas separadas por líneas finas (en vez de varias tarjetas). */
@Composable
fun SummaryCard(modifier: Modifier = Modifier, content: @Composable ColumnScope.() -> Unit) {
    Column(modifier = modifier.anchoContenido().contenedorBlanco().padding(horizontal = 16.dp), content = content)
}

/** Etiqueta a la izquierda, valor a la derecha con más peso. [primera] omite la línea superior. */
@Composable
fun SummaryRow(label: String, value: String, primera: Boolean = false, destacado: Boolean = false, color: Color? = null) {
    SummaryRowSlot(label, primera) {
        Text(
            value,
            style = if (destacado) MaterialTheme.typography.titleMedium else MaterialTheme.typography.bodyLarge,
            fontWeight = FontWeight.SemiBold,
            color = color ?: if (destacado) EcoLactColors.VerdeOscuro else EcoLactColors.TextoPrincipal,
            textAlign = TextAlign.End,
        )
    }
}

@Composable
fun SummaryRowSlot(label: String, primera: Boolean = false, trailing: @Composable RowScope.() -> Unit) {
    if (!primera) HorizontalDivider(color = EcoLactColors.Bordes)
    Row(
        modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp).padding(vertical = 12.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(label, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, modifier = Modifier.weight(1f))
        trailing()
    }
}

/**
 * Fila de una lista agrupada: todas las filas forman un solo bloque blanco con separadores
 * (esquinas redondeadas solo en la primera y la última), sin una tarjeta por elemento.
 */
@Composable
fun ProviderListItem(
    primera: Boolean,
    ultima: Boolean,
    onClick: (() -> Unit)?,
    modifier: Modifier = Modifier,
    leading: (@Composable () -> Unit)? = null,
    trailing: (@Composable () -> Unit)? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    val shape = RoundedCornerShape(
        topStart = if (primera) RADIO else 0.dp,
        topEnd = if (primera) RADIO else 0.dp,
        bottomStart = if (ultima) RADIO else 0.dp,
        bottomEnd = if (ultima) RADIO else 0.dp,
    )
    Column(modifier = modifier.anchoContenido().background(EcoLactColors.Blanco, shape)) {
        if (!primera) HorizontalDivider(color = EcoLactColors.Bordes, modifier = Modifier.padding(start = 16.dp))
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 56.dp)
                .let { if (onClick != null) it.clickable(onClick = onClick) else it }
                .padding(start = 16.dp, end = if (onClick != null) 8.dp else 16.dp, top = 12.dp, bottom = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            leading?.invoke()
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp), content = content)
            trailing?.invoke()
            if (onClick != null) {
                Icon(EcoLactIcons.ChevronRight, contentDescription = null, tint = EcoLactColors.TextoSecundario)
            }
        }
    }
}

/** Texto principal de una fila de lista (una línea). */
@Composable
fun ItemTitulo(text: String, color: Color = EcoLactColors.TextoPrincipal, fontWeight: FontWeight = FontWeight.SemiBold) {
    Text(text, style = MaterialTheme.typography.bodyLarge, fontWeight = fontWeight, color = color, maxLines = 1, overflow = TextOverflow.Ellipsis)
}

@Composable
fun ItemSubtitulo(text: String) {
    Text(text, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, maxLines = 2, overflow = TextOverflow.Ellipsis)
}

/** Estado con punto + texto (nunca solo color), fondo suave. */
@Composable
fun StatusChip(label: String, tono: Tono, modifier: Modifier = Modifier) {
    val (fondo, texto) = when (tono) {
        Tono.EXITO -> EcoLactColors.VerdeClaro to EcoLactColors.VerdeOscuro
        Tono.ATENCION -> EcoLactColors.NaranjaClaro to EcoLactColors.NaranjaOscuro
        Tono.ERROR -> EcoLactColors.RojoClaro to EcoLactColors.Rojo
        Tono.NEUTRAL -> EcoLactColors.Fondo to EcoLactColors.TextoSecundario
    }
    Row(
        modifier = modifier
            .background(fondo, RoundedCornerShape(50))
            .padding(horizontal = 10.dp, vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Box(Modifier.size(6.dp).background(texto, CircleShape))
        Text(label, style = MaterialTheme.typography.labelMedium, color = texto, maxLines = 1)
    }
}

/** Filtro compacto: un chip con el valor actual que despliega las opciones. [activo] = distinto del valor por defecto. */
@Composable
fun FiltroDesplegable(label: String, activo: Boolean, opciones: List<String>, onSeleccionar: (Int) -> Unit) {
    var abierto by remember { mutableStateOf(false) }
    Box {
        FilterChip(
            selected = activo,
            onClick = { abierto = true },
            label = { Text(label, maxLines = 1) },
            trailingIcon = { Icon(EcoLactIcons.ArrowDropDown, contentDescription = null, modifier = Modifier.size(18.dp)) },
            shape = RoundedCornerShape(50),
            colors = FilterChipDefaults.filterChipColors(
                containerColor = EcoLactColors.Blanco,
                selectedContainerColor = EcoLactColors.VerdeClaro,
                selectedLabelColor = EcoLactColors.VerdeOscuro,
                selectedTrailingIconColor = EcoLactColors.VerdeOscuro,
            ),
        )
        DropdownMenu(expanded = abierto, onDismissRequest = { abierto = false }, containerColor = EcoLactColors.Blanco) {
            opciones.forEachIndexed { index, opcion ->
                DropdownMenuItem(
                    text = { Text(opcion) },
                    onClick = {
                        abierto = false
                        onSeleccionar(index)
                    },
                )
            }
        }
    }
}

/** "29 / OCT" apilado a la izquierda de una fila de lista. */
@Composable
fun FechaBadge(fecha: String) {
    val partes = formatDiaMes(fecha).split(" ")
    Column(
        modifier = Modifier.widthIn(min = 44.dp).background(EcoLactColors.Fondo, RoundedCornerShape(10.dp)).padding(horizontal = 8.dp, vertical = 6.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(partes.first(), style = MaterialTheme.typography.titleMedium, color = EcoLactColors.TextoPrincipal)
        if (partes.size > 1) {
            Text(partes[1], style = MaterialTheme.typography.labelSmall, color = EcoLactColors.TextoSecundario)
        }
    }
}
