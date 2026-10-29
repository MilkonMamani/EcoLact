---
title: "Configuración del proyecto"
---

<div class="ec-desarrollo" markdown="1">

<div class="ec-dev-banner" markdown="1">

:material-tune: 6. Variables de entorno y configuración

</div>

<div class="ec-dev-content" markdown="1">

<span class="ec-dev-kicker">EcoLact / Guía de desarrollo</span>

# Configuración del proyecto

El backend y la app móvil tienen configuraciones independientes. La coherencia entre ambas permite que una solicitud salga del dispositivo, llegue al servidor correcto y utilice la base prevista.

<div class="ec-dev-grid" markdown="1">

<div class="ec-dev-card" markdown="1">

### Backend Laravel

`.env.example` documenta las variables; `.env` almacena los valores locales y está ignorado por Git. Las variables verificadas son:

<div class="ec-dev-command-table" markdown="1">

| Variable | Finalidad |
| --- | --- |
| APP_NAME | Nombre mostrado por la aplicación; la plantilla dice Laravel, ajustable para el entorno. |
| APP_ENV | Identifica el entorno; local corresponde a desarrollo. |
| APP_DEBUG | Permite detalles de diagnóstico. Desactivar fuera de desarrollo. |
| APP_URL | URL base utilizada por Laravel; no configura la escucha de serve ni la URL de Ktor. |
| DB_CONNECTION | Motor de la conexión; mysql en la plantilla. |
| DB_HOST | Host donde escucha MySQL; no la dirección de Laravel. |
| DB_PORT | Puerto del servicio MySQL. |
| DB_DATABASE | Nombre del esquema: ecolactv1 en la plantilla revisada. |
| DB_USERNAME | Usuario propio de la base de datos. |
| DB_PASSWORD | Contraseña privada de ese usuario. |

</div>

</div>

<div class="ec-dev-card" markdown="1">

### Cliente móvil

`ApiConfig.baseUrl` está definido en Kotlin, no en .env. Actualmente apunta a `http://10.0.2.2:8000/api/v1/`. Conserva el prefijo y la barra final.

`HttpClientFactory` crea Ktor con el motor de plataforma, JSON y la cabecera Bearer obtenida de SessionManager. `expectSuccess=false` permite tratar estados HTTP en la capa del cliente. No escribas un token fijo en ApiConfig.

En emulador se utiliza el alias del anfitrión; en un teléfono físico se necesita la IP real accesible del equipo; para remoto, un dominio HTTPS. Consulta [la configuración de red](mobile.md).

**Comprobación:** confirma la URL efectiva y prueba una solicitud autenticada con un usuario del entorno, sin copiar tokens en el historial ni en capturas.

</div>

</div>

## Aplicar y comprobar cambios

Después de editar .env, desde EcoLactWeb:

<div class="ec-dev-command" markdown="1">

```powershell
php artisan config:clear
```

</div>

**Propósito:** retirar una configuración cacheada para que la aplicación lea los valores actualizados. Reinicia procesos persistentes que dependan de ella. En la app móvil, recompila o vuelve a ejecutar después de cambiar la URL de ApiConfig.

<div class="ec-dev-result" markdown="1">

**Resultado esperado:** Laravel conecta al esquema configurado y Android llega a /api/v1/. Comprueba el entorno con migrate:status y una consulta de prueba. No muestres la configuración completa en la terminal para confirmar una sola variable: puede contener secretos.

</div>

## Configuraciones adicionales del proyecto

<div class="ec-dev-grid" markdown="1">

<div class="ec-dev-card blue" markdown="1">

:material-text-recognition:

### OCR

El backend usa Tesseract local para tickets del analizador y boletas de compra. Instala el ejecutable y el idioma elegido. `TESSERACT_PATH` vacío usa el PATH; `TESSERACT_LANG` usa eng por defecto. El preprocesador de imágenes llama a funciones GD de PHP. Comprueba `tesseract --version`, `tesseract --list-langs` y `php -m`; luego prueba una imagen no sensible. No hay una API OCR en la nube configurada en estos adaptadores.

</div>

<div class="ec-dev-card green" markdown="1">

:material-map-marker-radius:

### Mapas

`GOOGLE_MAPS_API_KEY` y `GOOGLE_MAPS_MAP_ID` se leen en services.php. También existen `GOOGLE_MAPS_DEFAULT_LAT`, `_LNG`, `_ZOOM` y `GOOGLE_MAPS_ZONAS_LAT`, `_LNG`, `_ZOOM`. Configura la clave de Maps JavaScript con restricciones de API y referrer. Esta clave se entrega al navegador por diseño; no debe confundirse con una credencial secreta de servidor. Verifica el mapa de zonas en la web.

</div>

<div class="ec-dev-card orange" markdown="1">

:material-database-outline:

### Archivos locales

SQLDelight utiliza SQLite en el dispositivo para caché, sesión y cola. Los drivers de plataforma abren ecolact.db; no es la base MySQL del servidor. Conserva pendientes antes de borrar datos de la app. SessionManager guarda el token local como texto: no publiques el archivo de base de datos ni copias de sesión.

</div>

<div class="ec-dev-card purple" markdown="1">

:material-laptop:

### Entorno de desarrollo

Usa el SDK y JDK configurados en Android Studio, local.properties propio y una URL de API accesible. El permiso HTTP está limitado a hosts en network_security_config.xml. El servidor de Laravel y MySQL tienen puertos distintos; Vite añade un tercer proceso para recursos web.

</div>

</div>

## Claves y evidencias saneadas

APP_KEY se genera en la instalación nueva y no se publica. ECOLACT_ADMIN_USERNAME y ECOLACT_ADMIN_PASSWORD configuran el usuario del seeder; deben tener valores propios antes de sembrar. No incluyas contraseñas, tokens, claves privadas o datos de usuarios en commits, incidencias o capturas. Usa una copia saneada de la plantilla para documentar el entorno.

La sustitución de los marcadores de esta página debe mostrar nombres de variables y estructura, nunca sus valores privados.




<details markdown="1">
<summary>Fuentes verificables del repositorio</summary>

- [EcoLactWeb/.env.example](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/EcoLactWeb/.env.example)
- [EcoLactWeb/.gitignore](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/EcoLactWeb/.gitignore)
- [EcoLactWeb/config/services.php](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/EcoLactWeb/config/services.php)
- [EcoLactWeb/app/Infrastructure/Ocr/TesseractOcr.php](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/EcoLactWeb/app/Infrastructure/Ocr/TesseractOcr.php)
- [EcoLactWeb/app/Infrastructure/Ocr/PreprocesadorImagenTicket.php](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/EcoLactWeb/app/Infrastructure/Ocr/PreprocesadorImagenTicket.php)
- [EcoLactMobile/shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/core/network/ApiConfig.kt](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/EcoLactMobile/shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/core/network/ApiConfig.kt)
- [EcoLactMobile/shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/core/network/HttpClientFactory.kt](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/EcoLactMobile/shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/core/network/HttpClientFactory.kt)
- [EcoLactMobile/shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/core/session/SessionManager.kt](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/EcoLactMobile/shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/core/session/SessionManager.kt)
- [EcoLactMobile/androidApp/src/main/res/xml/network_security_config.xml](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/EcoLactMobile/androidApp/src/main/res/xml/network_security_config.xml)

Revisión de referencia: `75901337489d`. Los procedimientos se contrastaron con estas fuentes; los comandos de instalación, migración y compilación del producto no se ejecutaron para elaborar esta guía.

</details>

</div>

<div class="ec-dev-nav" markdown="1">

[← Configuración de MySQL](base-datos.md)

[Control de versiones con Git →](git.md)

</div>

</div>

</div>

