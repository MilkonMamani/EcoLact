package pe.edu.upeu.ecolactmobile.core.sync

import kotlinx.coroutines.flow.StateFlow

/** Observa conectividad real de red (no solo "hay wifi", sino "hay ruta a internet"). */
expect class ConnectivityObserver {
    val isOnline: StateFlow<Boolean>
}
