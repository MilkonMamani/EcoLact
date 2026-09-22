package pe.edu.upeu.ecolactmobile.core.cache

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

    fun clear(key: String) = queries.deleteCache(key)
    fun clearAll() = queries.clearAllCache()
}

data class CachedValue<T>(val value: T, val cachedAtIso: String)
