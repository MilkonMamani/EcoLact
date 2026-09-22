package pe.edu.upeu.ecolactmobile.core.ui.state

import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.remember
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel

/**
 * Reemplazo liviano de `androidx.lifecycle.ViewModel` para esta app. La navegación es manual
 * (ver `core.ui.navigation.Navigator`, sin `NavHost`/`ViewModelStoreOwner`), así que atarse a
 * `viewModelScope` dejaría corrutinas vivas después de salir de la pantalla — nadie llamaría a
 * `onCleared()`. Aquí el scope se cancela explícitamente desde [rememberScreenModel] cuando el
 * composable que lo creó sale de composición.
 */
abstract class ScreenModel {
    protected val scope = CoroutineScope(SupervisorJob())

    open fun onDispose() {
        scope.cancel()
    }
}

@Composable
fun <T : ScreenModel> rememberScreenModel(key: Any? = null, factory: () -> T): T {
    val model = remember(key) { factory() }
    DisposableEffect(model) {
        onDispose { model.onDispose() }
    }
    return model
}
