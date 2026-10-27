package pe.edu.upeu.ecolactmobile.feature.acopiador.components

import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import kotlinx.datetime.LocalDate
import kotlinx.datetime.TimeZone
import kotlinx.datetime.isoDayNumber
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatHora
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.core.util.hoy
import pe.edu.upeu.ecolactmobile.core.util.parseFechaLocal
import pe.edu.upeu.ecolactmobile.feature.acopiador.ControlCalidad
import pe.edu.upeu.ecolactmobile.feature.acopiador.EntregaVista
import pe.edu.upeu.ecolactmobile.feature.acopiador.EstadoRegistro
import pe.edu.upeu.ecolactmobile.feature.acopiador.FiltroPeriodo
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco

// ---- Fechas en español corto ----

private val MESES = listOf("ene.", "feb.", "mar.", "abr.", "may.", "jun.", "jul.", "ago.", "set.", "oct.", "nov.", "dic.")
private val DIAS = listOf("Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom")

/** "Lun, 27 oct. 2026" */
fun fechaLarga(fecha: LocalDate): String {
    val s = fecha.toString() // 2026-10-27
    return "${DIAS[fecha.dayOfWeek.isoDayNumber - 1]}, ${s.substring(8, 10).trimStart('0')} ${MESES[s.substring(5, 7).toInt() - 1]} ${s.substring(0, 4)}"
}

/** "14 abr. 2025 · 09:35" */
fun fechaHoraLarga(iso: String, zona: TimeZone = TimeZone.currentSystemDefault()): String {
    val f = parseFechaLocal(iso, zona) ?: return iso
    val s = f.toString()
    return "${s.substring(8, 10).trimStart('0')} ${MESES[s.substring(5, 7).toInt() - 1]} ${s.substring(0, 4)} · ${s.substring(11, 16)}"
}

/** Hoy → "09:35"; otro día → "14 oct. · 09:35". */
fun horaOFecha(iso: String, zona: TimeZone = TimeZone.currentSystemDefault()): String {
    val f = parseFechaLocal(iso, zona) ?: return iso
    if (f.date == hoy(zona)) return formatHora(iso, zona)
    val s = f.toString()
    return "${s.substring(8, 10).trimStart('0')} ${MESES[s.substring(5, 7).toInt() - 1]} · ${s.substring(11, 16)}"
}

// ---- Estados ----

/** ✓ Sincronizada (verde) · ● Pendiente (naranja) · ! Error (rojo, solo si el servidor la rechazó). */
@Composable
fun EstadoRegistroIndicador(registro: EstadoRegistro, modifier: Modifier = Modifier) {
    Row(modifier = modifier, verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
        when (registro) {
            EstadoRegistro.SINCRONIZADA -> {
                Icon(EcoLactIcons.CheckCircle, contentDescription = null, tint = EcoLactColors.VerdePrincipal, modifier = Modifier.size(16.dp))
                Text("Sincronizada", style = MaterialTheme.typography.labelMedium, color = EcoLactColors.VerdeOscuro)
            }
            EstadoRegistro.PENDIENTE -> {
                Box(Modifier.padding(horizontal = 4.dp).size(8.dp).background(EcoLactColors.Naranja, CircleShape))
                Text("Pendiente", style = MaterialTheme.typography.labelMedium, color = EcoLactColors.NaranjaOscuro)
            }
            EstadoRegistro.ERROR -> {
                Icon(EcoLactIcons.Error, contentDescription = null, tint = EcoLactColors.Rojo, modifier = Modifier.size(16.dp))
                Text("Error", style = MaterialTheme.typography.labelMedium, color = EcoLactColors.Rojo)
            }
        }
    }
}

fun ControlCalidad.tono(): Tono = when (this) {
    ControlCalidad.ACEPTADA -> Tono.EXITO
    ControlCalidad.OBSERVADA -> Tono.ATENCION
    ControlCalidad.RECHAZADA, ControlCalidad.ANULADA -> Tono.ERROR
    ControlCalidad.SIN_MUESTREO, ControlCalidad.SELECCIONADA, ControlCalidad.EN_ANALISIS -> Tono.NEUTRAL
}

@Composable
fun ControlCalidadChip(control: String?, modifier: Modifier = Modifier) {
    val c = ControlCalidad.from(control) ?: ControlCalidad.SIN_MUESTREO
    StatusChip(c.label, c.tono(), modifier)
}

// ---- Filas ----

/** María Quispe / 20 L · 09:35 / ✓ Sincronizada */
@Composable
fun EntregaFila(
    entrega: EntregaVista,
    primera: Boolean,
    ultima: Boolean,
    onClick: () -> Unit,
    titulo: String = entrega.proveedorNombre,
    subtitulo: String = "${formatLitros(entrega.litros)} · ${horaOFecha(entrega.fechaHora)}",
) {
    ProviderListItem(
        primera = primera,
        ultima = ultima,
        onClick = onClick,
        trailing = { EstadoRegistroIndicador(entrega.registro) },
    ) {
        ItemTitulo(titulo)
        ItemSubtitulo(subtitulo)
    }
}

// ---- Búsqueda y filtros ----

@Composable
fun CampoBusqueda(valor: String, onChange: (String) -> Unit, placeholder: String, modifier: Modifier = Modifier) {
    OutlinedTextField(
        value = valor,
        onValueChange = onChange,
        placeholder = { Text(placeholder) },
        leadingIcon = { Icon(EcoLactIcons.Search, contentDescription = null, tint = EcoLactColors.TextoSecundario) },
        singleLine = true,
        shape = RoundedCornerShape(12.dp),
        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
        colors = OutlinedTextFieldDefaults.colors(
            focusedContainerColor = EcoLactColors.Blanco,
            unfocusedContainerColor = EcoLactColors.Blanco,
            focusedBorderColor = EcoLactColors.VerdePrincipal,
            unfocusedBorderColor = EcoLactColors.Bordes,
        ),
        modifier = modifier.anchoContenido(),
    )
}

/** Chips de una sola selección; con scroll horizontal para que entren en 360dp. */
@Composable
fun <T> FiltrosChips(opciones: List<T>, seleccionada: T, label: (T) -> String, onSeleccionar: (T) -> Unit, modifier: Modifier = Modifier) {
    Row(
        modifier = modifier.anchoContenido().horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        opciones.forEach { opcion ->
            val activa = opcion == seleccionada
            FilterChip(
                selected = activa,
                onClick = { onSeleccionar(opcion) },
                label = { Text(label(opcion), fontWeight = if (activa) FontWeight.SemiBold else FontWeight.Normal) },
                shape = RoundedCornerShape(50),
                colors = FilterChipDefaults.filterChipColors(
                    containerColor = EcoLactColors.Blanco,
                    selectedContainerColor = EcoLactColors.VerdeClaro,
                    selectedLabelColor = EcoLactColors.VerdeOscuro,
                ),
            )
        }
    }
}

@Composable
fun FiltrosPeriodo(seleccionado: FiltroPeriodo, onSeleccionar: (FiltroPeriodo) -> Unit) =
    FiltrosChips(FiltroPeriodo.entries, seleccionado, { it.label }, onSeleccionar)

/** Tarjeta pequeña de Inicio: etiqueta arriba, valor grande abajo (texto, para "245 L"). */
@Composable
fun KpiTexto(label: String, valor: String, modifier: Modifier = Modifier) {
    Column(
        modifier = modifier.contenedorBlanco().padding(horizontal = 16.dp, vertical = 14.dp),
    ) {
        Text(label, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, maxLines = 1)
        Text(valor, style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal, maxLines = 1)
    }
}

/** Línea "Etiqueta / valor" apilada para tarjetas de detalle. */
@Composable
fun DatoApilado(label: String, valor: String, modifier: Modifier = Modifier) {
    Column(modifier = modifier.fillMaxWidth().padding(vertical = 10.dp)) {
        Text(label, style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
        Text(valor, style = MaterialTheme.typography.bodyLarge, color = EcoLactColors.TextoPrincipal)
    }
}
