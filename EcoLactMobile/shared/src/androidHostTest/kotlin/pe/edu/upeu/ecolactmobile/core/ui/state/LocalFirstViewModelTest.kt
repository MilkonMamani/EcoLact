package pe.edu.upeu.ecolactmobile.core.ui.state

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withTimeout
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import kotlin.test.AfterTest
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertIs
import kotlin.test.assertTrue

/** Reglas offline-first: con copia local nunca hay pantalla de error, y la UI sigue a SQLite. */
class LocalFirstViewModelTest {
    private val vms = mutableListOf<ScreenModel>()

    @AfterTest
    fun tearDown() = vms.forEach { it.onDispose() }

    private fun <T> vm(
        source: MutableStateFlow<CachedValue<T>?>,
        online: MutableStateFlow<Boolean>,
        refreshOnStart: Boolean = false,
        refresher: suspend () -> Unit,
    ) = LocalFirstViewModel(source, online, refresher, refreshOnStart).also { vms += it }

    private suspend fun <T> LocalFirstViewModel<T>.await(predicate: (UiState<T>) -> Boolean): UiState<T> =
        withTimeout(5_000) { state.first(predicate) }

    @Test
    fun con_copia_local_un_fallo_de_red_no_tapa_los_datos() = runBlocking<Unit> {
        val source = MutableStateFlow<CachedValue<String>?>(CachedValue("pagos guardados", "2026-10-26T18:20:00Z"))
        val vm = vm(source, MutableStateFlow(true), refreshOnStart = true) { error("servidor caído") }

        val s = vm.await { it is UiState.Success }
        assertEquals("pagos guardados", (s as UiState.Success).data)
        // Tras el fallo del refresco sigue en Success.
        vm.refresh()
        assertIs<UiState.Success<String>>(vm.await { it !is UiState.Loading })
    }

    @Test
    fun offline_con_copia_local_muestra_datos_marcados_como_guardados() = runBlocking<Unit> {
        val source = MutableStateFlow<CachedValue<String>?>(CachedValue("entregas", "2026-10-26T18:20:00Z"))
        var llamadas = 0
        val vm = vm(source, MutableStateFlow(false), refreshOnStart = true) { llamadas++ }

        val s = vm.await { it is UiState.Success } as UiState.Success
        assertTrue(s.fromCache)
        assertEquals(0, llamadas, "sin conexión no se intenta la red")
    }

    @Test
    fun offline_sin_copia_local_muestra_error_explicativo() = runBlocking<Unit> {
        val vm = vm(MutableStateFlow<CachedValue<String>?>(null), MutableStateFlow(false)) {}
        assertIs<UiState.Error>(vm.await { it is UiState.Error })
    }

    @Test
    fun la_ui_cambia_cuando_cambia_sqlite_y_al_reconectar_se_refresca_sola() = runBlocking<Unit> {
        val source = MutableStateFlow<CachedValue<String>?>(null)
        val online = MutableStateFlow(false)
        // El "servidor" escribe en la fuente local, como hace el repositorio con SQLite.
        val vm = vm(source, online, refreshOnStart = true) { source.value = CachedValue("Semana 43 · Pagado", "2026-10-27T10:00:00Z") }

        assertIs<UiState.Error>(vm.await { it is UiState.Error })
        online.value = true
        val s = vm.await { it is UiState.Success } as UiState.Success
        assertEquals("Semana 43 · Pagado", s.data)
        assertEquals(false, s.fromCache)
    }
}
