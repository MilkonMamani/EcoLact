package pe.edu.upeu.ecolactmobile.core.sync

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import android.net.NetworkRequest
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.network.ApiConfig
import java.net.HttpURLConnection
import java.net.URL

/**
 * "En línea" = conectividad USABLE, no solo "hay Wi-Fi":
 * 1. alguna red que Android ya VALIDÓ con salida real a internet (`NET_CAPABILITY_VALIDATED`), o
 * 2. una red que dice tener internet pero Android no pudo validar (su sondeo a Google está
 *    bloqueado o es lento en algunas redes) y desde la que el servidor de EcoLact SÍ responde.
 *    Este caso se comprueba con una petición corta y se repite cada [REINTENTO_SONDEO_MS],
 *    porque Android no avisa cuando ese servidor vuelve a responder.
 *
 * Un Wi-Fi conectado sin salida (ni a internet ni al servidor) queda "sin conexión".
 *
 * Se llevan conjuntos de redes en vez de un booleano porque los callbacks llegan por red: al
 * pasar de Wi-Fi a datos móviles, `onLost(wifi)` puede llegar después de `onAvailable(datos)`.
 */
actual class ConnectivityObserver(context: Context) {
    private val connectivityManager =
        context.applicationContext.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager

    private val lock = Any()
    private val validatedNetworks = mutableSetOf<Network>()
    private val unvalidatedNetworks = mutableSetOf<Network>()
    private var servidorAlcanzable = false

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var sondeo: Job? = null

    private val _isOnline = MutableStateFlow(activeNetworkValidated())
    actual val isOnline: StateFlow<Boolean> = _isOnline.asStateFlow()

    init {
        val request = NetworkRequest.Builder()
            .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
            .build()
        connectivityManager.registerNetworkCallback(request, object : ConnectivityManager.NetworkCallback() {
            override fun onAvailable(network: Network) {
                connectivityManager.getNetworkCapabilities(network)?.let { classify(network, it) }
            }

            override fun onCapabilitiesChanged(network: Network, networkCapabilities: NetworkCapabilities) =
                classify(network, networkCapabilities)

            override fun onLost(network: Network) = update {
                validatedNetworks.remove(network)
                unvalidatedNetworks.remove(network)
            }
        })
    }

    private fun classify(network: Network, capabilities: NetworkCapabilities) = update {
        if (capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)) {
            validatedNetworks.add(network)
            unvalidatedNetworks.remove(network)
        } else {
            validatedNetworks.remove(network)
            unvalidatedNetworks.add(network)
        }
    }

    private fun update(change: () -> Unit) {
        synchronized(lock) {
            change()
            publish()
            val necesitaSondeo = validatedNetworks.isEmpty() && unvalidatedNetworks.isNotEmpty()
            if (necesitaSondeo && sondeo?.isActive != true) {
                sondeo = scope.launch { sondearServidor() }
            } else if (!necesitaSondeo) {
                sondeo?.cancel()
                sondeo = null
                servidorAlcanzable = false
            }
        }
    }

    private fun publish() {
        _isOnline.value = validatedNetworks.isNotEmpty() || (unvalidatedNetworks.isNotEmpty() && servidorAlcanzable)
    }

    private suspend fun sondearServidor() {
        while (scope.isActive) {
            val red = synchronized(lock) { unvalidatedNetworks.firstOrNull() } ?: return
            val alcanzable = servidorResponde(red)
            synchronized(lock) {
                servidorAlcanzable = alcanzable
                publish()
            }
            delay(REINTENTO_SONDEO_MS)
        }
    }

    /** Cualquier respuesta HTTP (incluso 404) prueba que el servidor es alcanzable por esa red. */
    private fun servidorResponde(red: Network): Boolean = runCatching {
        val conexion = red.openConnection(URL(ApiConfig.baseUrl)) as HttpURLConnection
        try {
            conexion.connectTimeout = TIMEOUT_SONDEO_MS
            conexion.readTimeout = TIMEOUT_SONDEO_MS
            conexion.requestMethod = "HEAD"
            conexion.responseCode > 0
        } finally {
            conexion.disconnect()
        }
    }.getOrDefault(false)

    private fun activeNetworkValidated(): Boolean {
        val network = connectivityManager.activeNetwork ?: return false
        val capabilities = connectivityManager.getNetworkCapabilities(network) ?: return false
        return capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET) &&
            capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)
    }

    private companion object {
        const val TIMEOUT_SONDEO_MS = 3_000
        const val REINTENTO_SONDEO_MS = 15_000L
    }
}
