package pe.edu.upeu.ecolactmobile.core.cache

/** "Cache-then-network con fallback": intenta red, si falla sirve la última copia local con su hora. */
sealed class CacheOutcome<out T> {
    data class Fresh<T>(val data: T) : CacheOutcome<T>()
    data class Stale<T>(val data: T, val cachedAtIso: String) : CacheOutcome<T>()
}

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
