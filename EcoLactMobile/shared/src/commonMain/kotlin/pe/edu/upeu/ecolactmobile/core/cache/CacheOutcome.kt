package pe.edu.upeu.ecolactmobile.core.cache

/** "Cache-then-network con fallback": intenta red, si falla sirve la última copia local con su hora. */
sealed class CacheOutcome<out T> {
    data class Fresh<T>(val data: T) : CacheOutcome<T>()
    data class Stale<T>(val data: T, val cachedAtIso: String) : CacheOutcome<T>()
}

/**
 * Sin conexión y sin copia local: el dato nunca se descargó en este teléfono. El mensaje está
 * pensado para mostrarse tal cual al usuario.
 */
class NoLocalDataException(
    message: String = "Sin conexión. Esta información todavía no está guardada en el teléfono; se descargará cuando vuelva internet.",
) : Exception(message)

suspend inline fun <reified T> CacheRepository.fetchCached(
    key: String,
    crossinline fetch: suspend () -> T,
): CacheOutcome<T> {
    return try {
        val fresh = fetch()
        put(key, fresh)
        CacheOutcome.Fresh(fresh)
    } catch (e: Exception) {
        val cached = get<T>(key)
        if (cached != null) {
            CacheOutcome.Stale(cached.value, cached.cachedAtIso)
        } else {
            throw e
        }
    }
}

/**
 * Variante offline-first de [fetchCached]: si el dispositivo ya sabe que no hay red, no intenta
 * la petición (evita esperar el timeout en campo) y sirve directamente la copia local.
 */
suspend inline fun <reified T> CacheRepository.fetchOfflineFirst(
    key: String,
    isOnline: Boolean,
    crossinline fetch: suspend () -> T,
): CacheOutcome<T> {
    if (!isOnline) {
        val cached = runCatching { get<T>(key) }.getOrNull() ?: throw NoLocalDataException()
        return CacheOutcome.Stale(cached.value, cached.cachedAtIso)
    }
    return fetchCached(key, fetch)
}
