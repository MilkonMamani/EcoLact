# Implementación de Desarrollo — EcoLact

La sección se implementó en el proyecto MkDocs existente, sin modificar el backend, la app móvil ni la base de datos.

## Archivos creados o modificados

- `docs/desarrollo/index.md` — Comenzar a desarrollar EcoLact
- `docs/desarrollo/repositorio.md` — Organización del proyecto
- `docs/desarrollo/backend.md` — Backend Laravel 13
- `docs/desarrollo/mobile.md` — Aplicación móvil con Kotlin Multiplatform
- `docs/desarrollo/base-datos.md` — Configuración de MySQL
- `docs/desarrollo/configuracion.md` — Configuración del proyecto
- `docs/desarrollo/git.md` — Control de versiones con Git
- `docs/stylesheets/desarrollo.css`: estilos delimitados por `.ec-desarrollo`, comandos oscuros, cards, tablas, flujos y marcadores punteados.
- `mkdocs.yml`: submenú Desarrollo y registro de desarrollo.css; conserva las seis secciones y las opciones anteriores.
- `docs/assets/images/desarrollo/`: doce marcadores PNG explícitos; no contienen terminales, código ni interfaces ficticias.
- `docs/assets/diagrams/desarrollo/`: tres fuentes Mermaid y tres SVG locales.
- `CAPTURAS-DESARROLLO.md`: instrucciones, nombre, contenido y herramienta para cada imagen real.
- `tools/generar_desarrollo.py`, `preparar_capturas_desarrollo.py`, `renderizar_desarrollo.cjs`, `validar_desarrollo.cjs`, `comprobar_desarrollo.cjs`, `desarrollo-manifest.json`: generación y comprobación de la documentación.
- `validacion-desarrollo/`: capturas del sitio, resultados, comprobación de enlaces, fuentes remotas y referencia de preservación.
- `site/`: salida de MkDocs regenerada; sus páginas incluyen el nuevo enlace CSS y submenú.

## Procedimientos documentados

Obtención del repositorio; herramientas y requisitos; estructura y ubicación de cambios; Composer, entorno y APP_KEY; preparación de MySQL, migraciones y seeders; Laravel y Vite; importación de Android Studio, JDK, SDK y Gradle Wrapper; configuración de API para emulador, teléfono y servidor; compilación Android y ejecución desde el IDE; alcance iOS; OCR, mapas y persistencia local; flujo Git y Pull Request propuesto.

Los pasos importantes incluyen propósito, comando o acción, explicación, resultado esperado y comprobación. Se advierte específicamente sobre sobrescribir .env, regenerar APP_KEY, efectos de los seeders, límites HTTP de Android y operaciones destructivas de base de datos.

## Fuentes y comandos contrastados

43 referencias a archivos o directorios locales comprobados. Los enlaces apuntan a la revisión `75901337489dbe3c61bf5e95357ea365790ab565`.

Se consultaron por el conector GitHub y se contrastaron con el contenido local:

- `EcoLactWeb/composer.json`: install, configuración, scripts dev/setup y Laravel/PHP declarados.
- `EcoLactWeb/package.json`: npm run dev y npm run build.
- `EcoLactWeb/database/seeders/DatabaseSeeder.php`: los siete seeders llamados por db:seed.
- `EcoLactMobile/settings.gradle.kts`: módulos :androidApp y :shared.
- `EcoLactMobile/androidApp/build.gradle.kts`: variante debug, SDK y objetivo JVM.
- `EcoLactMobile/gradle/wrapper/gradle-wrapper.properties`: Gradle 9.1.0.
- `EcoLactMobile/shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/core/network/ApiConfig.kt`: URL real del emulador y prefijo /api/v1/.

El resto de la comprobación se apoya en el código local: package-lock.json para Node, .env.example para ecolactv1 y nombres de variables, seeders concretos para sus efectos, drivers y configuración de red. Se consultó la [compatibilidad oficial de AGP 9.0](https://developer.android.com/build/releases/agp-9-0-0-release-notes) para diferenciar JDK 17 de bytecode JVM 11.

«Verificado» significa contrastado con fuentes y configuración, no ejecutado contra una instalación funcional. No se ejecutaron Composer/npm para instalar el producto, Gradle para compilar Android, migraciones, seeders ni escrituras en MySQL.

## Diagramas y capturas

- Flujo de inicio: cuatro cards en una fila de escritorio y columna en móvil.
- Flujo Git: cinco cards con flechas, presentado como propuesta.
- `servidores.svg`: navegador, Laravel, Vite, app y MySQL.
- `conexion-mobile.svg`: emulador, teléfono y servidor remoto.
- `mysql.svg`: Laravel → configuración → conexión PDO/MySQL → esquema; migraciones y seeders.

- `entorno-desarrollo.png` — El editor abierto en EcoLact y las herramientas instaladas, sin información privada.
- `estructura-repositorio.png` — Las carpetas EcoLactWeb, EcoLactMobile y docs desplegadas.
- `backend-dependencias.png` — La finalización de composer install y la comprobación de requisitos PHP.
- `backend-servidor.png` — Laravel y Vite ejecutándose en terminales separadas y sus direcciones locales.
- `android-studio.png` — EcoLactMobile importado y los módulos androidApp y shared visibles.
- `mobile-gradle.png` — El resultado real de :androidApp:assembleDebug y la variante debug.
- `mobile-api.png` — ApiConfig.kt con la dirección del entorno, sin cabeceras ni tokens.
- `mysql-base-datos.png` — El esquema ecolactv1 y su listado de tablas, sin registros personales.
- `mysql-migraciones.png` — La finalización de migrate y el listado migrate:status.
- `configuracion-entorno.png` — Un ejemplo saneado de las variables de entorno; contraseñas y claves ocultas.
- `configuracion-api.png` — ApiConfig.kt y la configuración de Ktor, sin valores de sesión.
- `git-historial.png` — La rama y los commits de una modificación real.

Los doce PNG existentes se sustituyen por capturas reales con el mismo nombre. Al regenerar MkDocs se muestran automáticamente sin cambiar Markdown. En mkdocs serve se regeneran al guardar; una publicación estática requiere reconstruir y publicar el sitio. El script preparador nunca sobrescribe imágenes existentes.

## Validación realizada

- `python -m mkdocs build --clean`: correcto. Material emitió su aviso general sobre MkDocs 2.0 y MkDocs informó de la página preexistente de fuentes de Flujos fuera de nav. Sin errores de compilación ni enlaces nuevos ausentes.
- Navegación Edge/Playwright: siete páginas × tres tamaños (1440, 768 y 390), más diez recorridos de regresión: **31 resultados sin fallos**.
- **615 referencias locales**, incluidos fragmentos: ninguna ausente.
- Tres diagramas renderizados con Mermaid 11.12.0; sus SVG son XML válido y cargan en las páginas.
- Botón de copiar probado en un bloque de cada una de las siete páginas: contenido correcto, normalizando finales de línea de Windows.
- Doce imágenes de captura disponibles; sin imágenes rotas ni desbordamientos del documento. Código y tablas admiten desplazamiento interno cuando hace falta.
- Flujos de cuatro y cinco cards comprobados en una fila de escritorio; dos columnas de configuración se apilan en móvil.
- Revisión visual de capturas de las siete páginas y de la vista móvil de configuración.
- **155 archivos documentales fuera de Desarrollo sin cambios**, verificados por SHA-256. La configuración restante coincide con la referencia anterior.
- Sin cambios de fuentes Laravel/Kotlin, migraciones o seeders; sin commits ni pushes.

## Limitaciones y tareas manuales

No se recibió la imagen del mosaico de siete mockups: el adjunto disponible contiene solo texto. Se aplicó su descripción visual, sin afirmar una comparación exacta con una imagen ausente.

El usuario debe aportar las capturas reales. Quien instale el sistema debe configurar sus herramientas, credenciales y servidor, aplicar migraciones/seeders sobre una base de desarrollo revisada y comprobar la ejecución real de Laravel y Android. iOS necesita macOS/Xcode y pruebas específicas; su observador de conectividad sigue provisional en el código inspeccionado.
