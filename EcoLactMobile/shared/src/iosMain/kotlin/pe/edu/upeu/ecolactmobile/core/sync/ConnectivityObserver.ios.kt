package pe.edu.upeu.ecolactmobile.core.sync

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

/**
 * TODO(iOS): placeholder sin verificar. Esta app se está desarrollando en una máquina Windows
 * sin Xcode/macOS disponible, así que esta implementación no se ha podido compilar ni probar
 * todavía. Reemplazar por un monitor real (NWPathMonitor vía cinterop a Network.framework, o
 * SCNetworkReachability) antes de confiar en el estado offline/online de la app en iOS — de
 * momento siempre reporta "en línea", lo cual haría que la cola de sincronización intente
 * enviar operaciones aunque no haya red real.
 */
actual class ConnectivityObserver {
    private val _isOnline = MutableStateFlow(true)
    actual val isOnline: StateFlow<Boolean> = _isOnline
}
