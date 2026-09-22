package pe.edu.upeu.ecolactmobile.core.network

import kotlinx.serialization.json.Json

/**
 * Instancia única de Json para todo el proyecto. Deliberadamente SIN JsonNamingStrategy
 * global: el backend mezcla camelCase y snake_case (incluso dentro de la misma respuesta,
 * ver administrador.md sección 1), así que cada DTO anota @SerialName explícitamente en
 * los campos donde la clave JSON no coincide con el nombre idiomático en Kotlin. Aplicar
 * una estrategia global de conversión sería incorrecto para los objetos anidados que no siguen
 * el casing del contenedor.
 */
val ApiJson: Json = Json {
    ignoreUnknownKeys = true
    isLenient = true
    encodeDefaults = true
    explicitNulls = false
}
