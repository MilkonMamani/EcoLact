package pe.edu.upeu.ecolactmobile.feature.trabajador

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.serialization.Serializable
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import pe.edu.upeu.ecolactmobile.core.ui.components.QrCodeImage
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.initials
import pe.edu.upeu.ecolactmobile.feature.planta.components.AvisoInfo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.InitialsAvatar
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco

// "Mi QR" de los trabajadores de EcoLact (acopiador, analista de calidad, encargado de planta).
// El QR es el `qr_token` de su ficha en `trabajadores`: la misma identidad de asistencia, pagos e
// historial laboral. El trabajador solo lo MUESTRA; la jornada la registra el ADMINISTRADOR
// escaneándolo (el backend rechaza que un trabajador llame a ese endpoint).

@Serializable
data class JornadaAsistencia(val fecha: String, val estado: String, val origen: String? = null, val hora_marcacion: String? = null)

/** GET {rol}/mi-asistencia. Se guarda en SQLite para dibujar el QR sin conexión. */
@Serializable
data class MiAsistencia(
    val registrado: Boolean = false,
    val trabajador_id: Long? = null,
    val user_id: Long? = null,
    val nombres: String? = null,
    val apellidos: String? = null,
    val dni: String? = null,
    val qr_token: String? = null,
    val controla_asistencia: Boolean = false,
    val hora_cierre: String = "07:00",
    val abierta_hoy: Boolean = false,
    val hoy: String? = null,
    val jornadas: List<JornadaAsistencia> = emptyList(),
) {
    val nombreCompleto: String? get() = listOfNotNull(nombres, apellidos).joinToString(" ").trim().ifBlank { null }
}

/**
 * Lee solo lo guardado: abrir "Mi QR" no llama al servidor si ya hay copia (la sincronización
 * automática de cada rol la mantiene al día). Solo la primera vez en el teléfono se descarga.
 */
class MiQrViewModel(source: Flow<CachedValue<MiAsistencia>?>, isOnline: StateFlow<Boolean>, refresher: suspend () -> Unit) :
    LocalFirstViewModel<MiAsistencia>(source, isOnline, refresher)

@Composable
fun MiQrTrabajadorScreen(viewModel: MiQrViewModel, rol: String, nombreFallback: String) {
    val state by viewModel.state.collectAsState()
    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val a = s.data
        MiQrTrabajadorContenido(
            nombre = a.nombreCompleto ?: nombreFallback,
            iniciales = initials(a.nombres, a.apellidos, a.nombreCompleto ?: nombreFallback),
            rol = rol,
            dni = a.dni,
            qrToken = a.qr_token,
            sinQr = if (!a.registrado) "Todavía no estás registrado como trabajador. Consulta con el administrador." else "Tu QR de asistencia no está activo. Consulta con el administrador.",
        )
    }
}

/** Iniciales, nombre, rol, DNI, QR grande y la nota de que lo escanea el administrador. Sin botones. */
@Composable
fun MiQrTrabajadorContenido(nombre: String, iniciales: String, rol: String, dni: String?, qrToken: String?, sinQr: String) {
    ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(16.dp)) {
        item {
            Column(Modifier.anchoContenido(), horizontalAlignment = Alignment.CenterHorizontally) {
                InitialsAvatar(iniciales, large = true)
                Text(nombre, style = MaterialTheme.typography.titleLarge, modifier = Modifier.padding(top = 12.dp), textAlign = TextAlign.Center)
                Text(rol, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                dni?.let { Text("DNI: $it", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario) }
            }
        }
        item {
            if (qrToken == null) {
                EstadoVacio(sinQr)
            } else {
                BoxWithConstraints(Modifier.anchoContenido(), contentAlignment = Alignment.Center) {
                    // QR grande sin desbordar en 360dp: ~80% del ancho, máximo 300dp.
                    val lado = (maxWidth * 0.8f).coerceAtMost(300.dp)
                    Column(
                        Modifier.contenedorBlanco().padding(20.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                    ) {
                        Box(Modifier.background(EcoLactColors.Blanco, RoundedCornerShape(8.dp))) {
                            QrCodeImage(qrToken, Modifier.size(lado).semantics { contentDescription = "Código QR de asistencia" })
                        }
                        Text(
                            "Muestra este código para registrar tu asistencia.",
                            style = MaterialTheme.typography.bodyMedium,
                            color = EcoLactColors.TextoPrincipal,
                            textAlign = TextAlign.Center,
                        )
                    }
                }
            }
        }
        item { AvisoInfo("Este QR es utilizado por el administrador para registrar tu asistencia diaria.") }
    }
}
