package pe.edu.upeu.ecolactmobile.core.ui.navigation

/**
 * Marcador de una pantalla navegable. Deliberadamente NO es `sealed`: cada feature (auth,
 * proveedor, acopiador, ...) define sus propias rutas en su propio paquete, y una jerarquía
 * `sealed` en Kotlin exige que todas las subclases vivan en el mismo paquete que la clase base,
 * lo cual obligaría a apilar las rutas de los 6 roles en un solo archivo gigante. El despachador
 * de navegación (`AppNavHost`) hace un `when` con `else` para lo que no reconoce.
 */
interface Route
