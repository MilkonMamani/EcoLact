package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn
import pe.edu.upeu.ecolactmobile.core.database.Sync_queue
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.LecturaPendiente
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncQueueRepository
import pe.edu.upeu.ecolactmobile.core.sync.SyncStatus
import pe.edu.upeu.ecolactmobile.core.ui.components.ConnectionDot
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatRelativo
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorSync
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorSyncStatus
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SectionHeader
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRowSlot
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

data class OperacionPendiente(val id: String, val descripcion: String, val conError: Boolean, val error: String?)

data class SincronizacionVista(
    val online: Boolean,
    val status: ProveedorSyncStatus,
    val ultimaSincronizacionIso: String?,
    val operaciones: List<OperacionPendiente>,
) {
    val pendientes get() = operaciones.count { !it.conError }
    val errores get() = operaciones.count { it.conError }
}

/**
 * Solo consulta: la sincronización es automática ([ProveedorSync]). Muestra únicamente las
 * operaciones de la cola que pertenecen a este proveedor.
 */
class ProveedorSincronizacionViewModel(
    sync: ProveedorSync,
    repository: ProveedorRepository,
    syncQueue: SyncQueueRepository,
    private val userId: Long,
) : ScreenModel() {
    val vista: StateFlow<SincronizacionVista> = combine(
        sync.isOnline,
        sync.status,
        repository.observeUltimaSincronizacion(),
        syncQueue.observeAll(),
    ) { online, status, ultima, cola ->
        SincronizacionVista(online, status, ultima?.value, cola.mapNotNull(::aOperacion))
    }.stateIn(scope, SharingStarted.Eagerly, SincronizacionVista(sync.isOnline.value, sync.status.value, null, emptyList()))

    private fun aOperacion(fila: Sync_queue): OperacionPendiente? {
        val descripcion = when (fila.operation_type) {
            SyncOperationTypes.NOTIFICACION_MARCAR_LEIDA -> "Notificación marcada como leída"
            SyncOperationTypes.PROVEEDOR_LEER_COMUNICADO -> "Comunicado leído"
            else -> return null
        }
        val lectura = runCatching { ApiJson.decodeFromString<LecturaPendiente>(fila.payload_json) }.getOrNull()
        if (lectura?.user_id != userId) return null
        return when (fila.status) {
            SyncStatus.PENDING.name, SyncStatus.SYNCING.name -> OperacionPendiente(fila.id, descripcion, conError = false, error = null)
            SyncStatus.NEEDS_REVIEW.name -> OperacionPendiente(fila.id, descripcion, conError = true, error = fila.error_message)
            else -> null
        }
    }
}

@Composable
fun ProveedorSincronizacionScreen(viewModel: ProveedorSincronizacionViewModel) {
    val vista by viewModel.vista.collectAsState()

    val (estadoTexto, estadoTono) = when (val status = vista.status) {
        ProveedorSyncStatus.Offline -> "En espera de conexión" to Tono.ATENCION
        ProveedorSyncStatus.Syncing -> "Sincronizando…" to Tono.NEUTRAL
        ProveedorSyncStatus.AlDia -> "Al día" to Tono.EXITO
        is ProveedorSyncStatus.Fallo -> "Se reintentará solo" to Tono.ATENCION
    }

    ProveedorPantalla {
        item {
            SummaryCard {
                SummaryRowSlot("Estado actual", primera = true) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        ConnectionDot(vista.online)
                        Text(
                            if (vista.online) "Con conexión" else "Sin conexión",
                            style = MaterialTheme.typography.bodyLarge,
                            fontWeight = FontWeight.SemiBold,
                            color = EcoLactColors.TextoPrincipal,
                        )
                    }
                }
                SummaryRowSlot("Sincronización") { StatusChip(estadoTexto, estadoTono) }
                SummaryRow("Última sincronización", vista.ultimaSincronizacionIso?.let { formatRelativo(it) } ?: "Aún no")
                SummaryRow("Pendientes", "${vista.pendientes}")
                SummaryRow("Errores", "${vista.errores}", color = if (vista.errores > 0) EcoLactColors.Rojo else null)
            }
        }
        (vista.status as? ProveedorSyncStatus.Fallo)?.let { fallo ->
            item {
                Text(
                    "El servidor no respondió: ${fallo.mensaje}",
                    style = MaterialTheme.typography.bodySmall,
                    color = EcoLactColors.TextoSecundario,
                    modifier = Modifier.anchoContenido(),
                )
            }
        }

        if (vista.operaciones.isNotEmpty()) {
            item { SectionHeader("Por enviar") }
            itemsIndexed(vista.operaciones, key = { _, op -> op.id }) { index, op ->
                ProviderListItem(
                    primera = index == 0,
                    ultima = index == vista.operaciones.lastIndex,
                    onClick = null,
                    trailing = { StatusChip(if (op.conError) "Error" else "Pendiente", if (op.conError) Tono.ERROR else Tono.ATENCION) },
                ) {
                    ItemTitulo(op.descripcion, fontWeight = FontWeight.Normal)
                    op.error?.let { ItemSubtitulo(it) }
                }
            }
        }

        item {
            Column(Modifier.anchoContenido()) {
                Text(
                    "Los datos se actualizan solos cuando hay conexión. Sin conexión puedes seguir usando la app con lo guardado en el teléfono.",
                    style = MaterialTheme.typography.bodySmall,
                    color = EcoLactColors.TextoSecundario,
                )
            }
        }
    }
}
