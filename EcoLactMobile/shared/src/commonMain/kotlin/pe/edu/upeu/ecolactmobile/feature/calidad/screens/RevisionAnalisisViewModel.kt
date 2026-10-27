package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.util.formatDecimal
import pe.edu.upeu.ecolactmobile.core.util.nowIso
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import pe.edu.upeu.ecolactmobile.feature.calidad.CapturaLocal
import pe.edu.upeu.ecolactmobile.feature.calidad.ConfirmarAnalisisResult
import pe.edu.upeu.ecolactmobile.feature.calidad.EstadoCaptura
import pe.edu.upeu.ecolactmobile.feature.calidad.ParametroActivo
import pe.edu.upeu.ecolactmobile.feature.calidad.ReglaVigente
import pe.edu.upeu.ecolactmobile.feature.calidad.ResultadoEstimado
import pe.edu.upeu.ecolactmobile.feature.calidad.estimarResultado
import pe.edu.upeu.ecolactmobile.feature.calidad.ordenarPorParametro
import pe.edu.upeu.ecolactmobile.feature.calidad.parseValor

data class RevisionUiState(
    val cargando: Boolean = true,
    val errorCarga: String? = null,
    val parametros: List<ParametroActivo> = emptyList(),
    val reglas: List<ReglaVigente> = emptyList(),
    /** Texto de cada campo por código; vacío = "No detectado" (nunca 0). */
    val valores: Map<String, String> = emptyMap(),
    /** Lo que propuso el OCR, para marcar origen OCR/MANUAL de cada valor. */
    val propuestosOcr: Map<String, Double> = emptyMap(),
    val captura: EstadoCaptura? = null,
    val mensajeOcr: String? = null,
    val observacion: String = "",
    val enviando: Boolean = false,
    val error: String? = null,
    val resultado: ConfirmarAnalisisResult? = null,
) {
    val numericos: Map<String, Double?> get() = valores.mapValues { (_, texto) -> parseValor(texto) }

    /** Solo vista previa con las reglas guardadas; el servidor decide al confirmar. */
    val estimado: ResultadoEstimado? get() = estimarResultado(numericos, reglas)

    val camposInvalidos: Set<String>
        get() = valores.filter { (_, texto) -> texto.isNotBlank() && parseValor(texto) == null }.keys
}

/**
 * Revisión obligatoria de los valores antes de confirmar. Los valores del OCR (inmediato o
 * remoto) solo se PROPONEN: el analista siempre pulsa "Confirmar análisis". Lo editado se guarda
 * como borrador local en cada cambio, así nada se pierde sin conexión.
 */
class RevisionAnalisisViewModel(private val repository: CalidadRepository, private val entregaId: Long) : ScreenModel() {
    private val _state = MutableStateFlow(RevisionUiState())
    val state: StateFlow<RevisionUiState> = _state.asStateFlow()
    private var editado = false

    init {
        cargar()
        // Si el OCR remoto termina mientras se está en esta pantalla, se proponen sus valores
        // (sin pisar lo que el analista ya escribió).
        scope.launch { repository.observeCaptura(entregaId).collect(::aplicarCaptura) }
    }

    fun cargar() {
        scope.launch {
            _state.update { it.copy(cargando = true, errorCarga = null) }
            try {
                val previo = repository.analisisPrevio(entregaId)
                val parametros = ordenarPorParametro(previo.parametros) { it.codigo }
                val borrador = repository.obtenerBorradorLocal(entregaId)
                editado = borrador != null
                _state.update { s ->
                    s.copy(
                        cargando = false,
                        parametros = parametros,
                        reglas = previo.reglas,
                        valores = parametros.associate { p ->
                            val valor = borrador?.valores?.get(p.codigo) ?: s.propuestosOcr[p.codigo]
                            p.codigo to (valor?.let(::textoValor) ?: s.valores[p.codigo].orEmpty())
                        },
                        observacion = borrador?.observacion ?: s.observacion,
                    )
                }
            } catch (e: Exception) {
                _state.update { it.copy(cargando = false, errorCarga = e.message ?: "No se pudo cargar los parámetros") }
            }
        }
    }

    private fun aplicarCaptura(captura: CapturaLocal?) {
        _state.update { s ->
            val propuestos = captura?.valoresDetectados.orEmpty()
            s.copy(
                captura = captura?.estado,
                mensajeOcr = captura?.mensajeOcr,
                propuestosOcr = propuestos,
                valores = if (editado || propuestos.isEmpty()) s.valores else s.valores.mapValues { (codigo, texto) -> propuestos[codigo]?.let(::textoValor) ?: texto },
            )
        }
    }

    fun onValor(codigo: String, texto: String) {
        editado = true
        _state.update { it.copy(valores = it.valores + (codigo to texto), error = null) }
        guardarBorrador()
    }

    fun onObservacion(texto: String) {
        _state.update { it.copy(observacion = texto) }
        guardarBorrador()
    }

    private fun guardarBorrador() {
        val s = _state.value
        repository.guardarBorradorLocal(entregaId, s.numericos.filterValues { it != null }.mapValues { it.value!! }, s.observacion.ifBlank { null })
    }

    fun confirmar() {
        val s = _state.value
        if (s.camposInvalidos.isNotEmpty()) {
            _state.update { it.copy(error = "Revisa los valores marcados: deben ser números.") }
            return
        }
        val valores = s.numericos.filterValues { it != null }.mapValues { it.value!! }
        if (valores.isEmpty()) {
            _state.update { it.copy(error = "Ingresa al menos un valor para confirmar el análisis.") }
            return
        }
        // OCR si el analista dejó el valor propuesto tal cual; MANUAL si lo escribió o corrigió.
        val origenes = valores.mapValues { (codigo, valor) -> if (s.propuestosOcr[codigo] == valor) "OCR" else "MANUAL" }
        val fechaAnalisis = nowIso()
        scope.launch {
            _state.update { it.copy(enviando = true, error = null) }
            when (val r = repository.confirmarAnalisis(entregaId, valores, origenes, s.observacion.ifBlank { null }, fechaAnalisis)) {
                is ConfirmarAnalisisResult.Rechazado -> _state.update { it.copy(enviando = false, error = r.mensaje) }
                else -> _state.update { it.copy(enviando = false, resultado = r) }
            }
        }
    }
}

/** 4.2 → "4.2"; 1.028 → "1.028"; sin ceros sobrantes. */
internal fun textoValor(valor: Double): String = formatDecimal(valor, 3).trimEnd('0').trimEnd('.')
