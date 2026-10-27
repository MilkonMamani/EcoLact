package pe.edu.upeu.ecolactmobile.feature.calidad.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatDiaMes
import pe.edu.upeu.ecolactmobile.core.util.formatHora
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.core.util.formatRelativo
import pe.edu.upeu.ecolactmobile.feature.calidad.ConflictoSeleccion
import pe.edu.upeu.ecolactmobile.feature.calidad.EstadoCaptura
import pe.edu.upeu.ecolactmobile.feature.calidad.MuestraPendiente
import pe.edu.upeu.ecolactmobile.feature.calidad.ResultadoCalidad
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco
import pe.edu.upeu.ecolactmobile.feature.trabajador.LocalAutorizacion

private val RADIO_BOTON = RoundedCornerShape(12.dp)

/** "45 L · Collana 1" */
fun litrosZona(litros: Double, zona: String?): String = listOfNotNull(formatLitros(litros), zona?.takeIf { it.isNotBlank() }).joinToString(" · ")

/** "Hoy · 08:25", "Ayer · 07:50" o "24/10 · 15:40". */
fun fechaConPunto(iso: String): String = formatRelativo(iso).replaceFirst(" ", " · ")

/** "27 oct · 10:15" */
fun diaMesHora(iso: String): String = "${formatDiaMes(iso).lowercase()} · ${formatHora(iso)}"

/**
 * Verde sólido, 52dp, ancho completo: la acción principal de la pantalla. [operacion]: es una
 * operación de trabajo; sin asistencia válida hoy se muestra deshabilitada (el servidor valida igual).
 */
@Composable
fun BotonPrincipal(
    texto: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    cargando: Boolean = false,
    icon: ImageVector? = null,
    operacion: Boolean = false,
) {
    val permitido = !operacion || LocalAutorizacion.current.puedeOperar
    Button(
        onClick = onClick,
        enabled = enabled && permitido && !cargando,
        shape = RADIO_BOTON,
        colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal, contentColor = EcoLactColors.Blanco),
        modifier = modifier.anchoContenido().height(52.dp),
    ) {
        if (cargando) {
            CircularProgressIndicator(modifier = Modifier.size(20.dp), strokeWidth = 2.dp, color = EcoLactColors.Blanco)
        } else {
            icon?.let {
                Icon(it, contentDescription = null, modifier = Modifier.size(20.dp))
                Spacer(Modifier.size(8.dp))
            }
            Text(texto, style = MaterialTheme.typography.titleSmall)
        }
    }
}

/** Contorno verde, misma altura: acción secundaria. [operacion]: igual que en [BotonPrincipal]. */
@Composable
fun BotonSecundario(
    texto: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    icon: ImageVector? = null,
    operacion: Boolean = false,
) {
    val habilitado = enabled && (!operacion || LocalAutorizacion.current.puedeOperar)
    OutlinedButton(
        onClick = onClick,
        enabled = habilitado,
        shape = RADIO_BOTON,
        border = BorderStroke(1.dp, if (habilitado) EcoLactColors.VerdePrincipal else EcoLactColors.Bordes),
        colors = ButtonDefaults.outlinedButtonColors(contentColor = EcoLactColors.VerdeOscuro),
        modifier = modifier.anchoContenido().height(52.dp),
    ) {
        icon?.let {
            Icon(it, contentDescription = null, modifier = Modifier.size(20.dp))
            Spacer(Modifier.size(8.dp))
        }
        Text(texto, style = MaterialTheme.typography.titleSmall)
    }
}

/** Tarjeta pequeña de Inicio: etiqueta arriba, número grande abajo. */
@Composable
fun KpiPequena(label: String, valor: Int, modifier: Modifier = Modifier, destacado: Boolean = false) {
    Column(modifier = modifier.contenedorBlanco().padding(horizontal = 16.dp, vertical = 14.dp)) {
        Text(label, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, maxLines = 1)
        Text(
            "$valor",
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.SemiBold,
            color = if (destacado && valor > 0) EcoLactColors.VerdeOscuro else EcoLactColors.TextoPrincipal,
        )
    }
}

@Composable
fun ResultadoChip(resultado: String?, modifier: Modifier = Modifier) {
    val r = ResultadoCalidad.from(resultado)
    StatusChip(r?.label ?: "Sin resultado", r?.tono ?: Tono.NEUTRAL, modifier)
}

/** Fila de una muestra pendiente: proveedor, litros · zona, hora y botón "Analizar". */
@Composable
fun MuestraFila(muestra: MuestraPendiente, primera: Boolean, ultima: Boolean, onAnalizar: () -> Unit) {
    ProviderListItem(
        primera = primera,
        ultima = ultima,
        onClick = null,
        trailing = {
            Button(
                onClick = onAnalizar,
                enabled = LocalAutorizacion.current.puedeOperar,
                shape = RoundedCornerShape(10.dp),
                contentPadding = PaddingValues(horizontal = 14.dp, vertical = 6.dp),
                colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
                modifier = Modifier.heightIn(min = 40.dp),
            ) { Text("Analizar", style = MaterialTheme.typography.labelLarge) }
        },
    ) {
        ItemTitulo(muestra.proveedorNombre)
        ItemSubtitulo(litrosZona(muestra.litros, muestra.zonaNombre))
        Text(fechaConPunto(muestra.fechaHora), style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
        estadoLocal(muestra)?.let { (texto, tono) -> StatusChip(texto, tono, Modifier.padding(top = 4.dp)) }
    }
}

/** Lo que el teléfono guarda de esa muestra (sin enviar / foto / datos por revisar). */
fun estadoLocal(muestra: MuestraPendiente): Pair<String, Tono>? = when {
    muestra.captura == EstadoCaptura.REQUIERE_REVISION -> "Datos por revisar" to Tono.EXITO
    muestra.captura == EstadoCaptura.PENDIENTE_OCR -> "Foto guardada" to Tono.ATENCION
    muestra.seleccionLocal -> "Sin enviar" to Tono.ATENCION
    else -> null
}

/** "Esta entrega ya fue seleccionada para análisis." tras sincronizar una selección offline. */
@Composable
fun AvisoConflicto(conflicto: ConflictoSeleccion, onDescartar: () -> Unit) {
    Row(
        modifier = Modifier
            .anchoContenido()
            .background(EcoLactColors.NaranjaClaro, RoundedCornerShape(12.dp))
            .padding(start = 16.dp, top = 8.dp, bottom = 8.dp, end = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(conflicto.proveedorNombre, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold, color = EcoLactColors.NaranjaOscuro)
            Text(conflicto.mensaje, style = MaterialTheme.typography.bodySmall, color = EcoLactColors.NaranjaOscuro)
        }
        IconButton(onClick = onDescartar) {
            Icon(EcoLactIcons.Close, contentDescription = "Descartar aviso", tint = EcoLactColors.NaranjaOscuro)
        }
    }
}

/**
 * Confirmación de lo guardado en el teléfono sin conexión (foto, análisis): título, explicación,
 * lista de ✓ y un único botón "Continuar". Mismo lenguaje visual en todas las operaciones offline.
 */
@Composable
fun PantallaGuardadoLocal(
    titulo: String,
    descripcion: String,
    hechos: List<String>,
    nota: String,
    onContinuar: () -> Unit,
    accionSecundaria: Pair<String, () -> Unit>? = null,
) {
    Column(
        modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Spacer(Modifier.height(16.dp))
        Box(Modifier.size(64.dp).background(EcoLactColors.NaranjaClaro, CircleShape), contentAlignment = Alignment.Center) {
            Icon(EcoLactIcons.CloudOff, contentDescription = null, tint = EcoLactColors.Naranja, modifier = Modifier.size(32.dp))
        }
        Text(titulo, style = MaterialTheme.typography.titleLarge, color = EcoLactColors.TextoPrincipal, textAlign = TextAlign.Center)
        Text(descripcion, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, textAlign = TextAlign.Center, modifier = Modifier.anchoContenido())
        Column(modifier = Modifier.anchoContenido().contenedorBlanco().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            hechos.forEach { hecho ->
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    Icon(EcoLactIcons.CheckCircle, contentDescription = null, tint = EcoLactColors.VerdePrincipal, modifier = Modifier.size(20.dp))
                    Text(hecho, style = MaterialTheme.typography.bodyLarge, color = EcoLactColors.TextoPrincipal)
                }
            }
        }
        Text(nota, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, textAlign = TextAlign.Center, modifier = Modifier.anchoContenido())
        Spacer(Modifier.height(8.dp))
        BotonPrincipal("Continuar", onContinuar)
        accionSecundaria?.let { (texto, accion) -> BotonSecundario(texto, accion) }
    }
}

/** Mensaje de error/negocio en rojo suave, sin bloquear la pantalla. */
@Composable
fun MensajeError(texto: String, modifier: Modifier = Modifier) {
    Text(
        texto,
        style = MaterialTheme.typography.bodyMedium,
        color = EcoLactColors.Rojo,
        modifier = modifier.anchoContenido().background(EcoLactColors.RojoClaro, RoundedCornerShape(12.dp)).padding(12.dp),
    )
}
