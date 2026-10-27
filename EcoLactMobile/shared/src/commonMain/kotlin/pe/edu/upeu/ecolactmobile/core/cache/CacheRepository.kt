package pe.edu.upeu.ecolactmobile.core.cache

import app.cash.sqldelight.coroutines.asFlow
import app.cash.sqldelight.coroutines.mapToOneOrNull
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map
import pe.edu.upeu.ecolactmobile.core.database.EcoLactDatabase
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.util.nowIso

/**
 * Cache genérica de solo-lectura: guarda la última respuesta exitosa de un endpoint (serializada
 * como JSON) junto a su hora, para que cualquier pantalla pueda mostrar "datos guardados" con su
 * fecha cuando no hay conexión (ver sección 2/8 de cada .md — la regla es siempre mostrar la
 * hora de la última sincronización, nunca dar a entender que un dato es en tiempo real).
 */
class CacheRepository(database: EcoLactDatabase) {
    @PublishedApi
    internal val queries = database.cacheEntryQueries

    inline fun <reified T> put(key: String, value: T) {
        queries.upsertCache(key, ApiJson.encodeToString(value), nowIso())
    }

    inline fun <reified T> get(key: String): CachedValue<T>? {
        val row = queries.selectCache(key).executeAsOneOrNull() ?: return null
        return CachedValue(ApiJson.decodeFromString(row.payload_json), row.cached_at)
    }

    /**
     * Fuente observable para el patrón "API → SQLite → UI": emite la copia guardada ahora y
     * otra vez cada vez que alguien hace [put] sobre la misma clave (ej. la sincronización
     * automática al volver internet), sin que la pantalla tenga que volver a pedir nada.
     * Una copia que ya no se puede leer (formato de una versión anterior) se trata como ausente.
     */
    inline fun <reified T> observe(key: String): Flow<CachedValue<T>?> =
        queries.selectCache(key)
            .asFlow()
            .mapToOneOrNull(Dispatchers.Default)
            .distinctUntilChanged()
            .map { row ->
                row?.let { runCatching { CachedValue<T>(ApiJson.decodeFromString(it.payload_json), it.cached_at) }.getOrNull() }
            }

    fun clear(key: String) = queries.deleteCache(key)
    fun clearAll() = queries.clearAllCache()
}

data class CachedValue<T>(val value: T, val cachedAtIso: String)
