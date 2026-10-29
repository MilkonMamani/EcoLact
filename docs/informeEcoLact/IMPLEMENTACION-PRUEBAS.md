# Implementación de la sección 6. Pruebas

Fecha de ejecución: 29/10/2026, zona America/Lima. Commit base: `75901337489dbe3c61bf5e95357ea365790ab565`, con cambios locales previos. No se hicieron commits ni pushes.

## Archivos

- Cinco páginas completas en `docs/pruebas/`: `index.md`, `backend.md`, `mobile.md`, `integracion.md`, `evidencias.md`.
- CSS exclusivo `docs/stylesheets/pruebas.css`; registro en `mkdocs.yml`, conservando las seis secciones y su navegación.
- Logs, metadatos, reportes JUnit, HTML y CSV en `docs/assets/evidencias/pruebas/{backend,mobile,integracion,resumen}/20261029-qa02/`.
- Entorno e intento inicial en `20261029-qa01`; compilación final en `resumen/20261029-qa04/`.
- Cuatro capturas de reportes reales en `docs/assets/images/pruebas/`; no son terminales simuladas.
- Quince capturas de revisión del sitio y reporte en `validacion-pruebas/20261029-qa02/`.
- Scripts de captura, documentación, sanitización, validación y auditoría en `scripts/`.
- Sitio generado actualizado en `site/`.

## Ejecuciones

Se verificaron PHP, Composer, Java, Gradle y las dependencias instaladas. Se ejecutaron:

```text
php artisan test --colors=never tests/Feature/Application/Acopio/RegistrarEntregaTest.php --log-junit=...
php artisan test --colors=never --testsuite=Unit --log-junit=...
php artisan test --colors=never --testsuite=Feature --log-junit=...
php artisan test --colors=never --filter=<selección de integración> --log-junit=...
.\gradlew.bat :shared:testAndroidHostTest --console=plain --rerun-tasks
mkdocs build --clean
```

Los comandos completos, fechas y códigos de salida están en los JSON contiguos a cada log. El script `ejecutar_evidencias.py` establece SQLite en memoria y almacenamiento temporal; no pegar comandos de prueba sin revisar antes el entorno. La selección de integración se detalla en su JSON.

| Conjunto | Aprobadas | Fallos de aserción | Errores | Omitidas |
|---|---:|---:|---:|---:|
| Backend Unit | 86 | 0 | 0 | 0 |
| Backend Feature | 1820 | 6 | 16 | 1 |
| Android Host (incluye commonTest) | 93 | 0 | 0 | 0 |
| **Total único** | **1999** | **6** | **16** | **1** |

2.022 casos descubiertos; 2.021 ejecutados. La prueba inicial (6 aprobadas) y la selección de integración (62 aprobadas, 2 fallos y 1 error) repiten casos de Feature y no se vuelven a sumar. Se conservaron todas las trazas. Los tiempos de proceso están en metadatos; la suma JUnit es 241,028 segundos y no incluye compilación.

## Hallazgos

- Dos respuestas API 403 en casos con evento calculado antes de medianoche y asistencia de hoy.
- Un pago bloqueado antes del miércoles programado, durante la preparación del escenario.
- Doce casos del modal de pagos bloqueados por la restricción única proveedor/semana.
- Dos constructores de consulta sin el argumento Reloj y una propiedad `kpis` ausente en el DTO actual.
- Cuatro discrepancias de expectativas en vistas: acopio, dashboard, login y fila de pago pendiente.
- Se conservó un primer intento rechazado por `--no-interaction`, antes de ejecutar casos. El wrapper también tuvo un error de codificación al imprimir el primer build ya exitoso; se corrigió y el build final concluyó con salida 0.

MySQL quedó omitido mediante `ECOLACT_MYSQL_SCHEMA_TEST=0`. Un caso específico iOS y la ejecución Apple de commonTest no se ejecutaron por carecer de macOS/Xcode. No se ejecutaron dispositivos físicos ni App → API real → MySQL.

## Privacidad y verificación

Una vista fallida incluyó una clave de mapas y tokens CSRF. Se redactaron de las copias publicables; los originales quedaron fuera del sitio, en `%TEMP%/ecolact-qa-originales-20261029`. Los registros de redacción y hashes distinguen original y copia saneada. No se publicaron `.env` ni datos extraídos de una base real.

MkDocs compiló con salida **0**. Se conservaron el aviso global del tema y la información sobre un Markdown preexistente de Flujos fuera del navbar. Las **15 vistas** (cinco páginas × escritorio/tablet/móvil) pasaron la revisión de imágenes, enlaces y desbordamiento. La auditoría comprueba hashes de fuentes, logs, cifras Pest/JUnit y ausencia de secretos conocidos.

La auditoría final detectó **15 archivos PHP modificados después del cierre de Feature**, ajenos a esta implementación documental. Sus hashes y fechas están en `cambios-posteriores.json`; el inventario inicial se preservó. Los resultados no validan esas modificaciones posteriores. No se repitieron suites para atribuirles artificialmente el resultado anterior.

La evidencia valida escenarios concretos bajo SQLite y simulación HTTP. Los 22 resultados no satisfactorios, MySQL omitido y las plataformas pendientes impiden declarar completamente validado EcoLact. Esta implementación no modificó reglas de negocio ni tests para cambiar resultados.
