package pe.edu.upeu.ecolactmobile.core.ui.navigation

import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue

/**
 * Navegación manual con pila propia — sin librería externa (ver decisión en la arquitectura).
 * Genérico sobre `T` para que cada shell de rol (`ProveedorApp`, etc.) trabaje con su propio
 * sealed de rutas sin necesitar castear `current` de vuelta desde el marcador `Route` base.
 */
class Navigator<T : Route>(startDestination: T) {
    private val backstack = mutableStateListOf(startDestination)

    var current: T by mutableStateOf(startDestination)
        private set

    val canGoBack: Boolean
        get() = backstack.size > 1

    fun navigate(route: T) {
        backstack.add(route)
        current = route
    }

    fun pop(): Boolean {
        if (!canGoBack) return false
        backstack.removeAt(backstack.lastIndex)
        current = backstack.last()
        return true
    }

    /** Reemplaza toda la pila (ej. tras login, o al hacer logout). */
    fun replaceAll(route: T) {
        backstack.clear()
        backstack.add(route)
        current = route
    }
}
