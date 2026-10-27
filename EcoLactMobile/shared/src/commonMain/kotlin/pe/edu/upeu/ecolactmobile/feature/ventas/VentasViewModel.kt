package pe.edu.upeu.ecolactmobile.feature.ventas

import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.sync.SyncManager
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.util.nowIso

class VentasViewModel(
    private val repository: VentasRepository, private val sync: SyncManager,
    val online: StateFlow<Boolean>,
) : ScreenModel() {
    val catalogo = repository.catalogo.stateIn(scope, SharingStarted.Eagerly, null)
    val compradores = repository.compradores.stateIn(scope, SharingStarted.Eagerly, null)
    val perfil = repository.perfil.stateIn(scope, SharingStarted.Eagerly, null)
    val comunicados = repository.comunicados.stateIn(scope, SharingStarted.Eagerly, null)
    val ventas = repository.ventas.stateIn(scope, SharingStarted.Eagerly, emptyList())
    val error = MutableStateFlow<String?>(null)
    val busy = MutableStateFlow(false)
    val loading = MutableStateFlow(true)
    val cotizacion = MutableStateFlow<CotizacionVenta?>(null)
    val resultado = MutableStateFlow<Venta?>(null)
    val cantidad = MutableStateFlow("1")
    val formaPago = MutableStateFlow("CONTADO")
    private var cotizacionRequest: VentaRequest? = null

    init {
        scope.launch {
            online.collectLatest { conectado ->
                if (!conectado) { loading.value = false; return@collectLatest }
                while (true) {
                    try { sync.syncNow(); repository.refresh(); error.value = null }
                    catch (e: CancellationException) { throw e }
                    catch (e: Exception) { error.value = e.message ?: "No se pudieron actualizar los datos." }
                    finally { loading.value = false }
                    delay(60_000)
                }
            }
        }
    }

    fun limpiarVenta() { cotizacion.value = null; cotizacionRequest = null; resultado.value = null; error.value = null; cantidad.value = "1"; formaPago.value = "CONTADO" }
    fun cambiarCantidad(valor: String) { cantidad.value = valor; invalidarCotizacion() }
    fun cambiarPago(valor: String) { formaPago.value = valor; invalidarCotizacion() }
    fun invalidarCotizacion() { cotizacion.value = null; cotizacionRequest = null }
    fun cotizar(request: VentaRequest, producto: ProductoVenta) = ejecutar {
        cotizacion.value = null
        val valor = repository.cotizar(request, producto)
        cotizacionRequest = request
        cotizacion.value = valor
    }

    fun registrar(producto: ProductoVenta, cliente: ClienteVenta?, vendedor: String) = ejecutar {
        val quote = cotizacion.value ?: return@ejecutar
        val request = cotizacionRequest ?: return@ejecutar
        val venta = repository.registrar(request.copy(fecha_hora_evento = nowIso()), producto, cliente, quote, vendedor)
        resultado.value = venta
        cotizacion.value = null
        cotizacionRequest = null
        if (online.value) { sync.syncNow(); repository.refresh() }
    }

    fun nuevoComprador(datos: NuevoComprador, onSuccess: () -> Unit) = ejecutar {
        repository.registrarComprador(datos); onSuccess()
    }

    private fun ejecutar(block: suspend () -> Unit) {
        if (busy.value) return
        busy.value = true; error.value = null
        scope.launch {
            try { block() }
            catch (e: CancellationException) { throw e }
            catch (e: Exception) { error.value = e.message ?: "No se pudo completar la operación." }
            finally { busy.value = false }
        }
    }
}
