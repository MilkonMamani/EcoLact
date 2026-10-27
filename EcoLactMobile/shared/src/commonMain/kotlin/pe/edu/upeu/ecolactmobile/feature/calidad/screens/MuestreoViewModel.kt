package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import pe.edu.upeu.ecolactmobile.feature.calidad.EntregaElegible
import pe.edu.upeu.ecolactmobile.feature.calidad.MetodoSeleccion
import pe.edu.upeu.ecolactmobile.feature.calidad.PropuestaAleatoria
import pe.edu.upeu.ecolactmobile.feature.calidad.SeleccionResult
import pe.edu.upeu.ecolactmobile.feature.calidad.TipoControl

enum class ModoMuestreo { INICIO, ALEATORIA, MANUAL }

/** Universo de entregas por fecha (mismo criterio que RangoMuestreo del backend). */
enum class FechaMuestreo(val api: String, val label: String) {
    HOY("hoy", "Hoy"),
    AYER("ayer", "Ayer"),
    SEMANA("semana", "Últimos 7 días"),
}

/** Confirmación de una muestra recién elegida (bottom sheet de selección manual). */
data class SeleccionEnCurso(
    val entrega: EntregaElegible,
    val tipoControl: TipoControl = TipoControl.RUTINARIO,
    val observacion: String = "",
)

data class MuestraElegida(val proveedorNombre: String, val entregaId: Long, val guardadaLocal: Boolean)

data class MuestreoUiState(
    val modo: ModoMuestreo = ModoMuestreo.INICIO,
    // Aleatoria
    val sorteando: Boolean = false,
    val propuesta: PropuestaAleatoria? = null,
    val vistas: List<Long> = emptyList(),
    // Manual
    val fecha: FechaMuestreo = FechaMuestreo.HOY,
    val busqueda: String = "",
    val elegibles: List<EntregaElegible> = emptyList(),
    val cargandoLista: Boolean = false,
    val listaGuardadaEn: String? = null,
    val seleccion: SeleccionEnCurso? = null,
    // Comunes
    val confirmando: Boolean = false,
    val error: String? = null,
    val elegida: MuestraElegida? = null,
) {
    /** La búsqueda se aplica en el teléfono: sirve igual sin conexión. */
    val elegiblesFiltradas: List<EntregaElegible>
        get() {
            val q = busqueda.trim()
            if (q.isEmpty()) return elegibles
            return elegibles.filter { it.proveedorNombre.contains(q, ignoreCase = true) || it.proveedorDni.orEmpty().contains(q) }
        }
}

/**
 * Selección de muestras. La aleatoria la sortea el servidor (requiere conexión); la manual
 * funciona también sin conexión con la última lista descargada y se encola.
 */
class MuestreoViewModel(private val repository: CalidadRepository) : ScreenModel() {
    private val _state = MutableStateFlow(MuestreoUiState())
    val state: StateFlow<MuestreoUiState> = _state.asStateFlow()

    fun volver() = _state.update { it.copy(modo = ModoMuestreo.INICIO, propuesta = null, vistas = emptyList(), seleccion = null, error = null) }

    fun cerrarElegida() = _state.update { it.copy(elegida = null) }

    // ---- Aleatoria ----

    fun elegirAlAzar() {
        _state.update { it.copy(modo = ModoMuestreo.ALEATORIA, propuesta = null, vistas = emptyList(), elegida = null, error = null) }
        sortear()
    }

    /** "Elegir otra": el servidor sortea de nuevo sin repetir las ya vistas. */
    fun elegirOtra() = sortear()

    private fun sortear() {
        scope.launch {
            _state.update { it.copy(sorteando = true, error = null) }
            repository.sortear(_state.value.vistas)
                .onSuccess { p -> _state.update { it.copy(sorteando = false, propuesta = p, vistas = it.vistas + p.entrega.entregaId) } }
                .onFailure { e -> _state.update { it.copy(sorteando = false, error = e.message ?: "No se pudo elegir una muestra") } }
        }
    }

    fun confirmarAleatoria() {
        val propuesta = _state.value.propuesta ?: return
        confirmar(propuesta.entrega, MetodoSeleccion.ALEATORIA, null, null, propuesta.propuesta_token)
    }

    // ---- Manual ----

    fun seleccionarManualmente() {
        _state.update { it.copy(modo = ModoMuestreo.MANUAL, elegida = null, error = null) }
        cargarElegibles()
    }

    fun onBusqueda(texto: String) = _state.update { it.copy(busqueda = texto) }

    fun onFecha(fecha: FechaMuestreo) {
        _state.update { it.copy(fecha = fecha) }
        cargarElegibles()
    }

    fun cargarElegibles() {
        scope.launch {
            _state.update { it.copy(cargandoLista = true, error = null) }
            try {
                when (val r = repository.elegibles(_state.value.fecha.api)) {
                    is CacheOutcome.Fresh -> _state.update { it.copy(cargandoLista = false, elegibles = r.data, listaGuardadaEn = null) }
                    is CacheOutcome.Stale -> _state.update { it.copy(cargandoLista = false, elegibles = r.data, listaGuardadaEn = r.cachedAtIso) }
                }
            } catch (e: Exception) {
                _state.update { it.copy(cargandoLista = false, elegibles = emptyList(), error = e.message ?: "No se pudo cargar las entregas") }
            }
        }
    }

    fun abrirSeleccion(entrega: EntregaElegible) = _state.update { it.copy(seleccion = SeleccionEnCurso(entrega), error = null) }

    fun cerrarSeleccion() = _state.update { it.copy(seleccion = null) }

    fun onTipoControl(tipo: TipoControl) = _state.update { s -> s.copy(seleccion = s.seleccion?.copy(tipoControl = tipo)) }

    fun onObservacion(texto: String) = _state.update { s -> s.copy(seleccion = s.seleccion?.copy(observacion = texto)) }

    fun confirmarManual() {
        val seleccion = _state.value.seleccion ?: return
        confirmar(seleccion.entrega, MetodoSeleccion.MANUAL, seleccion.tipoControl, seleccion.observacion, null)
    }

    private fun confirmar(entrega: EntregaElegible, metodo: MetodoSeleccion, tipo: TipoControl?, observacion: String?, token: String?) {
        scope.launch {
            _state.update { it.copy(confirmando = true, error = null) }
            when (val r = repository.seleccionar(entrega, metodo, tipo, observacion, token)) {
                SeleccionResult.Confirmada, SeleccionResult.GuardadaOffline -> _state.update {
                    MuestreoUiState(
                        fecha = it.fecha,
                        elegida = MuestraElegida(entrega.proveedorNombre, entrega.entregaId, guardadaLocal = r == SeleccionResult.GuardadaOffline),
                    )
                }
                is SeleccionResult.Conflicto -> _state.update {
                    it.copy(
                        confirmando = false,
                        seleccion = null,
                        propuesta = null,
                        elegibles = it.elegibles.filterNot { e -> e.entregaId == entrega.entregaId },
                        error = r.mensaje,
                    )
                }
                is SeleccionResult.Rechazada -> _state.update { it.copy(confirmando = false, error = r.mensaje) }
            }
        }
    }
}
