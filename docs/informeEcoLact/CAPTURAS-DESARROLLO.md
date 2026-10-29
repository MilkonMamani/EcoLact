# Capturas de Desarrollo

Sustituye cada PNG en `docs/assets/images/desarrollo/` por tu captura real, conservando el nombre. Después ejecuta `python -m mkdocs build --clean` desde informeEcoLact (o deja que mkdocs serve regenere el sitio). No necesitas editar Markdown. Si mantienes abierto el navegador, recarga para evitar su caché.

Los PNG actuales son marcadores de texto, no evidencias de herramientas ejecutadas. Oculta contraseñas, tokens, claves y datos personales. El script preparador no sobrescribe ninguna imagen existente.

| Archivo | Qué debe mostrar | Herramienta |
| --- | --- | --- |
| `entorno-desarrollo.png` | El editor abierto en EcoLact y las herramientas instaladas, sin información privada. | VS Code y terminal |
| `estructura-repositorio.png` | Las carpetas EcoLactWeb, EcoLactMobile y docs desplegadas. | Explorador de VS Code |
| `backend-dependencias.png` | La finalización de composer install y la comprobación de requisitos PHP. | PowerShell |
| `backend-servidor.png` | Laravel y Vite ejecutándose en terminales separadas y sus direcciones locales. | Terminales de VS Code |
| `android-studio.png` | EcoLactMobile importado y los módulos androidApp y shared visibles. | Android Studio |
| `mobile-gradle.png` | El resultado real de :androidApp:assembleDebug y la variante debug. | Android Studio o PowerShell |
| `mobile-api.png` | ApiConfig.kt con la dirección del entorno, sin cabeceras ni tokens. | Android Studio |
| `mysql-base-datos.png` | El esquema ecolactv1 y su listado de tablas, sin registros personales. | MySQL Workbench o cliente MySQL |
| `mysql-migraciones.png` | La finalización de migrate y el listado migrate:status. | PowerShell |
| `configuracion-entorno.png` | Un ejemplo saneado de las variables de entorno; contraseñas y claves ocultas. | Editor de código |
| `configuracion-api.png` | ApiConfig.kt y la configuración de Ktor, sin valores de sesión. | Android Studio |
| `git-historial.png` | La rama y los commits de una modificación real. | VS Code o GitHub |
