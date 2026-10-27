package pe.edu.upeu.ecolactmobile.feature.planta.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.role
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco

/** Tarjeta pequeña de Inicio: etiqueta arriba (hasta 2 líneas), valor grande abajo. */
@Composable
fun KpiPlanta(label: String, valor: String, modifier: Modifier = Modifier, alerta: Boolean = false) {
    Column(
        modifier = modifier.contenedorBlanco().padding(horizontal = 12.dp, vertical = 12.dp),
        verticalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        Text(label, style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario, maxLines = 2, minLines = 2)
        Text(
            valor,
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.SemiBold,
            color = if (alerta) EcoLactColors.NaranjaOscuro else EcoLactColors.TextoPrincipal,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

/** Acceso rápido: ícono en círculo verde suave + texto corto, mismo tamaño las tres. */
@Composable
fun AccionRapida(label: String, icon: ImageVector, onClick: () -> Unit, modifier: Modifier = Modifier, habilitada: Boolean = true) {
    Column(
        modifier = modifier
            .contenedorBlanco()
            .clickable(enabled = habilitada, role = Role.Button, onClick = onClick)
            .padding(horizontal = 8.dp, vertical = 14.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Box(Modifier.size(44.dp).background(if (habilitada) EcoLactColors.VerdeClaro else EcoLactColors.Fondo, CircleShape), contentAlignment = Alignment.Center) {
            Icon(icon, contentDescription = null, tint = if (habilitada) EcoLactColors.VerdePrincipal else EcoLactColors.TextoSecundario, modifier = Modifier.size(24.dp))
        }
        Text(
            label,
            style = MaterialTheme.typography.labelLarge,
            color = if (habilitada) EcoLactColors.TextoPrincipal else EcoLactColors.TextoSecundario,
            textAlign = TextAlign.Center,
            maxLines = 2,
            minLines = 2,
        )
    }
}

/** "Producción activa ............ Ver todas >" */
@Composable
fun EncabezadoSeccion(titulo: String, accion: String? = null, onAccion: (() -> Unit)? = null) {
    Row(Modifier.anchoContenido(), verticalAlignment = Alignment.CenterVertically) {
        Text(titulo, style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal, modifier = Modifier.weight(1f))
        if (accion != null && onAccion != null) {
            TextButton(onClick = onAccion) {
                Text(accion, style = MaterialTheme.typography.labelLarge, color = EcoLactColors.VerdeOscuro)
                Icon(EcoLactIcons.ChevronRight, contentDescription = null, tint = EcoLactColors.VerdeOscuro, modifier = Modifier.size(18.dp))
            }
        }
    }
}

/** Título de bloque en mayúsculas pequeñas ("REQUERIMIENTOS DE INSUMOS", "PRECIOS VIGENTES"). */
@Composable
fun TituloBloque(texto: String, modifier: Modifier = Modifier) {
    Text(
        texto.uppercase(),
        style = MaterialTheme.typography.labelMedium,
        fontWeight = FontWeight.SemiBold,
        color = EcoLactColors.TextoSecundario,
        modifier = modifier.anchoContenido().padding(top = 4.dp),
    )
}

/** Dos o más pestañas tipo segmento ("En proceso | Finalizados", "Insumos (4) | Productos (6)"). */
@Composable
fun PestanasSegmento(opciones: List<String>, seleccion: Int, onSeleccionar: (Int) -> Unit, modifier: Modifier = Modifier) {
    Row(
        modifier = modifier
            .anchoContenido()
            .background(EcoLactColors.Blanco, RoundedCornerShape(12.dp))
            .border(1.dp, EcoLactColors.Bordes, RoundedCornerShape(12.dp))
            .padding(4.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        opciones.forEachIndexed { index, opcion ->
            val activa = index == seleccion
            Box(
                modifier = Modifier
                    .weight(1f)
                    .heightIn(min = 40.dp)
                    .background(if (activa) EcoLactColors.VerdeClaro else Color.Transparent, RoundedCornerShape(9.dp))
                    .clickable(role = Role.Tab) { onSeleccionar(index) },
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    opcion,
                    style = MaterialTheme.typography.labelLarge,
                    fontWeight = if (activa) FontWeight.SemiBold else FontWeight.Medium,
                    color = if (activa) EcoLactColors.VerdeOscuro else EcoLactColors.TextoSecundario,
                    maxLines = 1,
                )
            }
        }
    }
}

/** Fila de lote: producto, cantidad · código y estado, en un solo bloque blanco agrupado. */
@Composable
fun LoteItem(
    titulo: String,
    subtitulo: String,
    detalle: String?,
    estado: Pair<String, Tono>,
    primera: Boolean,
    ultima: Boolean,
    onClick: (() -> Unit)?,
) {
    ProviderListItem(
        primera = primera,
        ultima = ultima,
        onClick = onClick,
        leading = {
            Box(Modifier.size(40.dp).background(EcoLactColors.VerdeClaro, RoundedCornerShape(10.dp)), contentAlignment = Alignment.Center) {
                Icon(EcoLactIcons.Factory, contentDescription = null, tint = EcoLactColors.VerdePrincipal, modifier = Modifier.size(22.dp))
            }
        },
        trailing = { StatusChip(estado.first, estado.second) },
    ) {
        ItemTitulo(titulo)
        ItemSubtitulo(subtitulo)
        detalle?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario, maxLines = 1) }
    }
}

/** Aviso naranja suave (sin conexión, pendiente, stock insuficiente). Nunca bloquea la pantalla. */
@Composable
fun AvisoAtencion(titulo: String, texto: String?, modifier: Modifier = Modifier, icon: ImageVector = EcoLactIcons.CloudOff) {
    Row(
        modifier = modifier.anchoContenido().background(EcoLactColors.NaranjaClaro, RoundedCornerShape(12.dp)).padding(12.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Icon(icon, contentDescription = null, tint = EcoLactColors.Naranja, modifier = Modifier.size(20.dp))
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(titulo, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold, color = EcoLactColors.NaranjaOscuro)
            texto?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = EcoLactColors.NaranjaOscuro) }
        }
    }
}

/** Nota informativa en verde suave ("Este QR es utilizado por el administrador..."). */
@Composable
fun AvisoInfo(texto: String, modifier: Modifier = Modifier) {
    Row(
        modifier = modifier.anchoContenido().background(EcoLactColors.VerdeClaro, RoundedCornerShape(12.dp)).padding(12.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Icon(EcoLactIcons.Info, contentDescription = null, tint = EcoLactColors.VerdePrincipal, modifier = Modifier.size(20.dp))
        Text(texto, style = MaterialTheme.typography.bodySmall, color = EcoLactColors.VerdeOscuro)
    }
}

/** Campo de texto del formulario, con el estilo de la app. */
@Composable
fun CampoPlanta(
    valor: String,
    onCambio: (String) -> Unit,
    label: String,
    modifier: Modifier = Modifier,
    numerico: Boolean = false,
    sufijo: String? = null,
    prefijo: String? = null,
    error: Boolean = false,
) {
    OutlinedTextField(
        value = valor,
        onValueChange = onCambio,
        label = { Text(label) },
        singleLine = true,
        isError = error,
        suffix = sufijo?.let { { Text(it, color = EcoLactColors.TextoSecundario) } },
        prefix = prefijo?.let { { Text("$it ", color = EcoLactColors.TextoSecundario) } },
        keyboardOptions = if (numerico) KeyboardOptions(keyboardType = KeyboardType.Decimal) else KeyboardOptions.Default,
        shape = RoundedCornerShape(12.dp),
        colors = OutlinedTextFieldDefaults.colors(
            focusedBorderColor = EcoLactColors.VerdePrincipal,
            focusedLabelColor = EcoLactColors.VerdeOscuro,
            unfocusedBorderColor = EcoLactColors.Bordes,
            focusedContainerColor = EcoLactColors.Blanco,
            unfocusedContainerColor = EcoLactColors.Blanco,
        ),
        modifier = modifier.anchoContenido(),
    )
}

/** Selector desplegable con etiqueta: "Producto (receta)", "Insumo", "Presentación". */
@Composable
fun <T> SelectorPlanta(
    label: String,
    valor: String?,
    opciones: List<T>,
    texto: (T) -> String,
    onSeleccionar: (T) -> Unit,
    modifier: Modifier = Modifier,
    placeholder: String = "Seleccionar",
    error: Boolean = false,
) {
    var abierto by remember { mutableStateOf(false) }
    Column(modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Text(label, style = MaterialTheme.typography.labelMedium, color = EcoLactColors.TextoSecundario)
        Box {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(min = 52.dp)
                    .background(EcoLactColors.Blanco, RoundedCornerShape(12.dp))
                    .border(1.dp, if (error) EcoLactColors.Rojo else EcoLactColors.Bordes, RoundedCornerShape(12.dp))
                    .semantics { role = Role.DropdownList }
                    .clickable(enabled = opciones.isNotEmpty()) { abierto = true }
                    .padding(start = 16.dp, end = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    valor ?: placeholder,
                    style = MaterialTheme.typography.bodyLarge,
                    color = if (valor == null) EcoLactColors.TextoSecundario else EcoLactColors.TextoPrincipal,
                    modifier = Modifier.weight(1f),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                Icon(EcoLactIcons.ArrowDropDown, contentDescription = null, tint = EcoLactColors.TextoSecundario)
            }
            DropdownMenu(expanded = abierto, onDismissRequest = { abierto = false }, containerColor = EcoLactColors.Blanco) {
                opciones.forEach { opcion ->
                    DropdownMenuItem(
                        text = { Text(texto(opcion)) },
                        onClick = {
                            abierto = false
                            onSeleccionar(opcion)
                        },
                    )
                }
            }
        }
    }
}
