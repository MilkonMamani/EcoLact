package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.util.fechaHoraEventoIso
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorProveedorFila
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.Identificacion
import pe.edu.upeu.ecolactmobile.feature.acopiador.MetodoIdentificacion
import pe.edu.upeu.ecolactmobile.feature.acopiador.ParadaRef
import pe.edu.upeu.ecolactmobile.feature.acopiador.RegistrarEntregaResult
import pe.edu.upeu.ecolactmobile.feature.acopiador.dniValido

enum class PasoRegistro { ESCANEAR, FORMULARIO, RESULTADO }

data class RegistrarUiState(
    val paso: PasoRegistro = PasoRegistro.ESCANEAR,
    /** Cambia para reiniciar la cámara tras un QR no reconocido. */
    val intentoEscaneo: Int = 0,
    val identificando: Boolean = false,
    val avisoEscaneo: String? = null,
    val mostrandoDni: Boolean = false,
    val dni: String = "",
    val dniError: String? = null,
    val proveedor: AcopiadorProveedorFila? = null,
    val metodo: MetodoIdentificacion = MetodoIdentificacion.QR,
    val litros: String = "",
    val observacion: String = "",
    val litrosError: String? = null,
    val errorGuardar: String? = null,
    val guardando: Boolean = false,
    val resultado: RegistrarEntregaResult? = null,
    val litrosGuardados: Double = 0.0,
    val horaGuardada: String? = null,
)

/**
 * Un solo flujo para registrar una entrega: escanear QR (o DNI) → proveedor identificado →
 * litros + observación → guardar → resultado. Lo usan Inicio, el menú, el detalle de un
 * proveedor ([proveedorInicialId], ya identificado) y cada parada de recorrido ([parada]).
 *
 * La identificación se resuelve contra la lista de la zona guardada en el teléfono (también sin
 * señal); guardar nunca se bloquea por falta de internet: queda "Pendiente" y se envía sola.
 */
class RegistrarViewModel(
    private val repository: AcopiadorRepository,
    proveedorInicialId: Long?,
    private val parada: ParadaRef?,
) : ScreenModel() {
    private val _state = MutableStateFlow(RegistrarUiState())
    val state: StateFlow<RegistrarUiState> = _state.asStateFlow()

    val desdeRecorrido: Boolean get() = parada != null

    init {
        proveedorInicialId?.let(repository::proveedorGuardado)?.takeIf { it.activo }?.let { proveedor ->
            _state.update { it.copy(paso = PasoRegistro.FORMULARIO, proveedor = proveedor, metodo = MetodoIdentificacion.DNI) }
        }
    }

    // ---- Identificar ----

    fun onQrEscaneado(token: String) {
        if (_state.value.identificando) return
        identificar(MetodoIdentificacion.QR, token)
    }

    fun onReintentarEscaneo() = _state.update { it.copy(avisoEscaneo = null, intentoEscaneo = it.intentoEscaneo + 1) }

    fun onAbrirDni() = _state.update { it.copy(mostrandoDni = true, dniError = null) }
    fun onCerrarDni() = _state.update { it.copy(mostrandoDni = false, dni = "", dniError = null) }
    fun onDniChange(valor: String) = _state.update { it.copy(dni = valor.filter(Char::isDigit).take(8), dniError = null) }

    fun onBuscarDni() {
        val dni = _state.value.dni
        if (!dniValido(dni)) {
            _state.update { it.copy(dniError = "El DNI debe tener 8 dígitos.") }
            return
        }
        identificar(MetodoIdentificacion.DNI, dni)
    }

    private fun identificar(metodo: MetodoIdentificacion, identificador: String) {
        scope.launch {
            _state.update { it.copy(identificando = true, avisoEscaneo = null, dniError = null) }
            val mensajeError: String? = when (val r = repository.identificar(metodo, identificador)) {
                is Identificacion.Identificado -> {
                    if (parada != null && r.proveedor.id != parada.proveedor_id) {
                        "${r.proveedor.nombre_completo} no es el proveedor de esta parada."
                    } else {
                        _state.update {
                            it.copy(
                                paso = PasoRegistro.FORMULARIO,
                                proveedor = r.proveedor,
                                metodo = metodo,
                                mostrandoDni = false,
                                dni = "",
                                identificando = false,
                            )
                        }
                        null
                    }
                }
                is Identificacion.NoPermitido -> r.mensaje
                is Identificacion.NoEncontrado -> r.mensaje
            }
            if (mensajeError != null) {
                _state.update {
                    if (metodo == MetodoIdentificacion.DNI) it.copy(identificando = false, dniError = mensajeError)
                    else it.copy(identificando = false, avisoEscaneo = mensajeError)
                }
            }
        }
    }

    /** "Cambiar": volver a identificar a otro proveedor. */
    fun onCambiarProveedor() = _state.update {
        RegistrarUiState(intentoEscaneo = it.intentoEscaneo + 1, litros = it.litros, observacion = it.observacion)
    }

    // ---- Litros y guardar ----

    fun onLitrosChange(valor: String) = _state.update {
        it.copy(litros = valor.replace(',', '.').filter { c -> c.isDigit() || c == '.' }.take(7), litrosError = null, errorGuardar = null)
    }

    fun onObservacionChange(valor: String) = _state.update { it.copy(observacion = valor.take(500)) }

    fun onGuardar() {
        val actual = _state.value
        val proveedor = actual.proveedor ?: return
        val litros = actual.litros.toDoubleOrNull()
        if (litros == null || litros <= 0.0) {
            _state.update { it.copy(litrosError = "Ingresa una cantidad mayor a 0.") }
            return
        }
        if (actual.guardando) return
        // Hora del acopio: se captura AQUÍ, una sola vez, y viaja intacta en cada reintento.
        val hora = fechaHoraEventoIso()
        scope.launch {
            _state.update { it.copy(guardando = true, errorGuardar = null) }
            when (val r = repository.registrarEntrega(proveedor, actual.metodo, litros, actual.observacion, parada, fechaHoraEvento = hora)) {
                is RegistrarEntregaResult.Rechazada -> _state.update { it.copy(guardando = false, errorGuardar = r.mensaje) }
                else -> _state.update {
                    it.copy(guardando = false, paso = PasoRegistro.RESULTADO, resultado = r, litrosGuardados = litros, horaGuardada = hora)
                }
            }
        }
    }

    fun onRegistrarOtra() = _state.update { RegistrarUiState(intentoEscaneo = it.intentoEscaneo + 1) }
}
