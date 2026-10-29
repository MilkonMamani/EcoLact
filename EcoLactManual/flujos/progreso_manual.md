# Progreso del Manual de Usuario — Administrador EcoLactWeb

> Archivo de seguimiento para continuar el trabajo si la sesión se interrumpe.
> Última actualización: 2026-10-28 — versión 1.0 (198 páginas; capítulos 1–19 + Anexo A)

## Entregables
- `salida/Manual_Usuario_EcoLact_Administrador.docx` (maestro editable, generado)
- `salida/Manual_Usuario_EcoLact_Administrador.pdf` (exportado con Microsoft Word)
- `salida/Manual_EcoLact_Zonas_Demostracion.*` = prototipo de Antigravity (se conserva como referencia; NO es el entregable)

## Cómo regenerar
```powershell
cd "C:\CICLO VI\EcoLact\EcoLactManual"
.\.venv\Scripts\python.exe scripts\capturar.py login               # iniciar sesión a mano (sin credenciales en archivos)
.\.venv\Scripts\python.exe scripts\capturar.py run capturas\planes\<plan>.json
.\.venv\Scripts\python.exe scripts\construir_manual.py              # Word + PDF (acumulativo: todos los capítulos de manual/manual.json)
.\.venv\Scripts\python.exe scripts\revisar_pdf.py                   # hojas de contacto en evidencias/revision_pdf/
```
- Perfil del navegador (sesión): `%LOCALAPPDATA%\EcoLactManualProfile` (fuera del repositorio).
- Autorización del usuario (28/10/2026): la BD local `ecolactv1` es de PRUEBA; se permiten escrituras con datos ficticios marcados («Zona Prueba Manual», etc.). Nada de pagos reales ni eliminaciones masivas.

## Herramientas verificadas
| Herramienta | Estado |
|---|---|
| Python venv + python-docx 1.2 + Pillow 12 | OK |
| Playwright 1.64 + Chromium | OK |
| PyMuPDF (revisión visual del PDF) | Instalado en .venv |
| LibreOffice | No instalado → PDF con Word COM (`scripts/word_a_pdf.ps1`) |
| Microsoft Word 16 | OK (actualiza índice y campos) |

## Capítulos
| # | Capítulo | Archivo | Estado |
|---|---|---|---|
| 1 | Introducción (alcance, convenciones, mapa funcional, secuencia) | manual/capitulos/01_introduccion.json | Elaborado |
| 2 | Acceso al sistema y navegación (ADM-ACC-001..003) | manual/capitulos/02_acceso.json | **Verificado** (cerrar sesión: parcial, no ejecutado para conservar la sesión) |
| 3 | Zonas y rutas (ADM-ZON-001..007) | manual/capitulos/03_zonas.json | **Verificado**, salvo ADM-ZON-004 (defecto OBS-01) |
| 4 | Personal (ADM-PER-001..007) | manual/capitulos/04_personal.json | **Verificado** (4.5: analista, encargado, ventas verificados) |
| 5 | Proveedores (ADM-PRV-001..004) | 05_proveedores.json | **Verificado** (alta, edición, asignación a Faon/Ruta principal, estado, historial) |
| 6 | Parámetros de calidad (ADM-PAR-001..003) | 06_parametros_calidad.json | **Verificado**; OBS-04 |
| 7 | Configuración de pagos (ADM-CFG-001..003) | 07_configuracion_pagos.json | **Verificado**; OBS-06 |
| 8 | Acopio (ADM-ACO-001..005) | 08_acopio.json | **Verificado**; anular no disponible en UI (OBS-05) |
| 9 | Calidad (ADM-CAL-001..003) | 09_calidad.json | **Verificado** (confirmación tras reglas de referencia; OBS-07 sigue: 500 sin reglas) |
| 10 | Pagar a proveedores (ADM-PPV-001..004) | 10_pagar_proveedores.json | **Verificado** pago ficticio S/ 57.60 |
| 11 | Asistencia y pagos a trabajadores (ADM-ASP-001..005) | 11_asistencia_pagos_trabajadores.json | Consulta verificada; QR/corrección/pago sueldo pendientes; OBS-08 |
| 12 | Insumos y compras (ADM-INS-001..005) | 12_insumos.json | **Verificado** (OCR boleta pendiente) |
| 13 | Recetas (ADM-REC-001..003) | 13_recetas.json | **Verificado** |
| 14 | Producción (ADM-PRD-001..003) | 14_produccion.json | **Verificado** (LOT-00001) |
| 15 | Productos (ADM-PRO-001..003) | 15_productos.json | **Verificado**; /admin/stock sin enlace (OBS-09) |
| 16 | Ventas (ADM-VEN-001..002) | 16_ventas.json | **Verificado** (V-00001, V-00002) |
| 17 | Compradores (ADM-COM-001..002) | 17_compradores.json | **Verificado** alta; edición/estado no existen |
| 18 | Caja (ADM-CAJ-001..004) | 18_caja.json | **Verificado**; sin registro manual |
| 19 | Mi perfil (ADM-PRF-001..002) | 19_mi_perfil.json | **Verificado**; sin edición ni cambio de contraseña |
| — | Excluidos por el usuario: gestión de rutas (proveedores de ruta, orden, recorridos), Comunicados, Notificaciones | — | No se documentan |
| A | Anexo de verificación (tabla automática + observaciones) | manual/capitulos/A_verificacion.json | Elaborado; actualizar con cada capítulo |

## Correcciones al inventario de Antigravity (verificadas en código)
- `ADM-ZONA-CREAR-001`: el alta (`StoreZonaRequest`) solo recibe `nombre` (obligatorio, máx. 100, único y sin equivalentes) y `descripcion`. Color y polígono se guardan desde «Editar zona → Editar delimitación» (`UpdateZonaRequest`). La zona nace ACTIVA.
- Zonas no tiene página de detalle: `zonas/{zona}` redirige al listado con el panel abierto.
- Eliminar zona usa `window.confirm` nativo (no aparece en capturas; se documenta su texto literal).

## Problemas detectados
- 28/10: primer intento de inicio de sesión manual expiró (15 min sin login). Relanzado sin límite de espera.
- Google Maps no carga en Chromium headless sin WebGL: capturar.py usa SwiftShader (ARGS_GL).
- **OBS-01 (defecto EcoLactWeb):** editor de delimitación falla en zonas SIN polígono (`resources/js/pages/zonas-mapa.js`: `new google.maps.Polygon({paths: []})` deja `getPath()` undefined → `escucharContorno()` lanza «Cannot read properties of undefined (reading 'addListener')»). Reproducido en Chromium y Chrome con GPU. No se corrige (fuera de alcance).
- **OBS-02:** la leyenda del mapa principal (.zonas-leyenda) queda por encima del modal «Editar zona».
- **OBS-03:** título del inicio «Analitics Dashboard» (errata).
- Capturas de Zonas 2/3 hechas a 1920x1080 (a 1440 el menú lateral se superpone al modal ancho). Resto a 1440x900.

## Datos ficticios creados (BD de prueba)
- «Acopiador Prueba Manual» DNI 99000001, usuario acopiador.prueba.manual (contraseña aleatoria NO registrada), zona «Zona Prueba Manual», teléfono 900000001.
- «Zona Prueba Manual» (activa, sin polígono) + «Ruta Prueba Manual».
- «Zona Prueba Eliminar» creada y eliminada.

## Evidencias
- Originales: `evidencias/originales/<módulo>/` + `*.registro.json` (rectángulos medidos en el DOM, textos leídos, diálogos).
- Anotadas: `evidencias/anotadas/<capítulo>/` (generadas por construir_manual.py).
- Trazabilidad: `evidencias/ledger.json` (regenerado en cada construcción).
- Planes reproducibles: `capturas/planes/*.json`.

## Pendientes y avisos para el usuario
- **Precio de la leche:** al guardar el precio de prueba S/ 1.20 (autorizado) se cerró una vigencia anterior de S/ 1.70 (01/01/2020–27/10/2026). Restaurar con «Editar configuración» si corresponde.
- **PDF:** el PDF maestro estaba abierto en Adobe Acrobat; el PDF nuevo se dejó en `evidencias/Manual_PDF_pendiente_de_copiar.pdf` hasta poder copiarlo.
- **Word:** tras cierres forzados, Word marcó el DOCX («provocó un error grave»); `word_a_pdf.ps1` ahora trabaja sobre una copia temporal con nombre único y registra etapas en `evidencias/word_a_pdf.log`.
- Calidad: confirmar análisis requiere reglas vigentes para TODOS los parámetros activos (hoy ninguno tiene); pedir autorización si se desea verificar.
- Respaldo previo a la continuación: `respaldos/v0.3_2026-10-28/`.

## Mejoras de herramientas (28/10)
- capturar.py: `conservar_texto` (difumina filas reales), `difuminar_textos` (texto exacto, p. ej. usuario), `ir_atributo`.
- construir_manual.py: imágenes JPEG 4:4:4 solo para el Word (`evidencias/docx/`), `pantalla.registro`.
- capturar.py: acciones `clic_pos`, `seleccionar`, `escribir_secreto` (contraseña aleatoria nunca guardada), `dialogos`; viewport por plan; registro incremental.
- anotacion.py: el número se coloca en el lado con menos bordes (sin tapar otros controles); opción `posicion` por paso.
- construir_manual.py: `limitar_x` para mensajes a todo lo ancho, `figuras_validacion` (marca «!»), anexo automático de verificación, ledger.json, y aborta si un control queda fuera de la captura.

## Próximas operaciones (versión 1.0 entregada)
1. Cerrar Acrobat y copiar `evidencias/Manual_PDF_pendiente_de_copiar.pdf` a `salida/` (o volver a ejecutar `construir_manual.py`).
2. HECHO (28/10): reglas de referencia configuradas con autorización y ADM-CAL-003 verificado (resultado «Aceptada»). Ajustar las reglas a valores oficiales.
3. Verificar ADM-ASP-002/003/005 cuando la asistencia esté abierta y exista una obligación de sueldo pendiente.
4. Revisar con desarrollo OBS-01 a OBS-09.

## Próximas operaciones (histórico)
1. Personal: comprobar alta de analista/encargado/ventas con datos ficticios (DNI 99000002…).
2. Capítulo 5 Proveedores: alta ficticia, asignación a zona/ruta desde Zonas.
3. Capítulo 6 Rutas: agregar proveedor ficticio, orden, abrir recorrido con el acopiador ficticio.
4. Seguir el orden de la Introducción (parámetros, pagos, acopio, calidad, pagos…).
