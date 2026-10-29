# Progreso — Manual de Usuario EcoLact Móvil

> Archivo de seguimiento para continuar si la sesión se interrumpe.
> Última actualización: 29/10/2026 — **versión 3.0 generada** (88 páginas, 50 procedimientos): rediseño visual de uso rápido; una pantalla por hoja (captura de 7 cm con sus pasos).
>
> v3.0: cada procedimiento = título + una frase + capturas a la izquierda y pasos numerados (tú, verbo directo) a la derecha + nota breve.
> Sin fichas Propósito/Acceso/Requisitos, sin códigos ni estados en el cuerpo, sin tablas de validaciones:
> mensajes → Anexo C; códigos, estados, capturas pendientes y limitaciones → Anexo D. «Captura pendiente» discreto donde no hay evidencia (15 bloques).
> Respaldo de la v2.0 (docx, pdf, contenido.py, construir_manual.py) en `trabajo/respaldo_v2/`.

## Entregables
| Archivo | Estado |
|---|---|
| `salida/Manual_Usuario_EcoLact_Movil.docx` | Generado; maestro editable (índice, figuras y páginas actualizados por Word) |
| `salida/Manual_Usuario_EcoLact_Movil.pdf` | Exportado con Microsoft Word; 69 páginas, 67 marcadores |
| `flujos/auditoria_roles.md` | Completo |
| `flujos/inventario_funcionalidades.md` | Completo |
| `flujos/observaciones_documentales.md` | Completo |
| `referencias/Manual_Movil_Actual.pdf` | Original intacto (SHA-256 `dc3af21e…6ad7c`) |

## Cómo regenerar
```powershell
cd "C:\CICLO VI\EcoLact\EcoLactManualMovil"
python scripts\extraer_anotaciones.py        # (solo referencia) recuadros del PDF anterior
python scripts\privacidad.py                 # pixela QR, DNI, teléfonos y usuarios -> trabajo\protegidas\
python scripts\construir_manual.py           # Word + PDF (Word COM vía scripts\word_a_pdf.ps1)
python scripts\construir_manual.py --sin-pdf # solo el Word
```
- El texto del manual está en `manual/contenido.py` (capítulos, procedimientos, anclas de las marcas).
- Las marcas se ubican por texto (OCR guardado en `trabajo/ocr_palabras.json`), por botón verde (`("boton", n)`) o por coordenadas (`("caja", …)`).
- Revisión visual: `evidencias/revision_pdf/hoja_NN.png` (hojas de contacto del PDF).

## Decisiones del usuario
- 29/10/2026: solo **navegar** en la BD local; no confirmar operaciones. Resultados no observados = «previsto, pendiente de validación».
- 29/10/2026: no pedir contraseñas por chat; no detener el manual por falta de cuentas; usar el emulador solo para capturas indispensables, con inicio de sesión manual del usuario.
- No se modifica EcoLactMobile, EcoLactWeb ni EcoLactManual (verificado con `git status` al terminar).

## Entorno
- Emulador `Pixel_7` con el APK del 29/10 00:38 instalado desde una copia (sin compilar en el proyecto de la compañera).
- La sesión de prueba del emulador está vencida (`Unauthenticated.`) y la BD local casi vacía → no se tomaron capturas nuevas para el manual; se usaron las auténticas del 28/10/2026 contrastadas con el código. Capturas del emulador del 29/10 en `evidencias/originales/planta/01_inicio…04_insumos` (referencia, no usadas en el manual).

## Estado por capítulo
| Cap. | Rol | Procedimientos | Verificados | Parciales | Solo código |
|---|---|---|---|---|---|
| 2 | General | 5 | 4 | 1 | 0 |
| 3 | Encargado de planta | 12 | 11 | 1 | 0 |
| 4 | Personal de ventas | 8 | 4 | 2 | 2 |
| 5 | Analista de calidad | 8 | 4 | 1 | 3 |
| 6 | Proveedor | 9 | 5 | 3 | 1 |
| 7 | Acopiador | 8 | 6 | 1 | 1 |
| | **Total** | **50** | **34** | **9** | **7** |

## Capturas faltantes (requieren sesión autorizada y datos de prueba)
| Rol | Procedimiento | Pantalla requerida | Motivo | Evidencia disponible |
|---|---|---|---|---|
| Planta | MOB-PLA-005 | «Tomar foto de la boleta», «Datos detectados» | No capturadas en 2026-10-28 | Código `CamaraBoletaScreen`, `RevisarCompraScreen` |
| Ventas | MOB-VEN-003 | «Nuevo comprador» | No capturada | Código `VentasNuevoCompradorScreen` |
| Ventas | MOB-VEN-004 | «Venta registrada» | Confirmación no capturada (se infiere del historial V-00005) | p27_x147 |
| Ventas | MOB-VEN-006 | «Boleta de venta» | Función nueva | Código `VentasScreens.kt` |
| Ventas | MOB-VEN-008 | «Comunicados» | No capturada | Código |
| Calidad | MOB-CAL-002 | «Muestreo», muestra aleatoria, selección manual | Función no documentada antes | Código `MuestreoScreen` |
| Calidad | MOB-CAL-004 | «Tomar foto del ticket», «Ingresar valores», «Análisis confirmado» | No capturadas | p37_x175 (detalle) |
| Calidad | MOB-CAL-005/006 | «Historial», «Proveedores» | No capturadas | Código |
| Proveedor | MOB-PRV-005 | «Detalle de pago» (liquidación) | Sin liquidaciones en la BD de prueba | p47_x197 (lista vacía) |
| Proveedor | MOB-PRV-006/007 | «Detalle de compra», lista de «Comunicados» | Captura anterior era de otro rol | p48_x199, p49_x203 |
| Proveedor | MOB-PRV-009 | «Sincronización» | No capturada | p42_x188 (opción del menú) |
| Acopiador | MOB-ACO-005 | «Recorrido de hoy», paradas, «No entregó» | Función nueva; requiere recorrido asignado | p53_x215 (opción del menú) |
| Acopiador | MOB-ACO-004 | «Corregir entrega» | Requiere un error de sincronización | Código `EntregaDetalleScreen` |

Para completarlas: el usuario inicia sesión a mano en el emulador con cada cuenta de prueba; luego `python scripts\movil.py shot <rol> <nombre>` y se añade la figura en `manual/contenido.py` (marcas con `("caja", …)` usando el XML guardado junto a la captura).

## Controles de calidad realizados
- Word abre (python-docx) y Word COM lo actualizó y exportó; PDF abre (PyMuPDF): 69 páginas.
- Índice actualizado (sin el texto de marcador), figuras numeradas, encabezado y pie en todas las páginas salvo la portada.
- 54 capturas anotadas revisadas una por una; 8 marcas corregidas tras la revisión (p19, p23, p25, p26, p30, p44, p51, p54).
- Sin texto sin tildes del manual anterior («Guia», «aplicacion», «Contrasena», «sesion»).
- Sin fondos de color en el documento; solo gris muy claro en encabezados de tabla.
- Títulos de procedimiento unidos a su captura (sin fichas huérfanas al pie de página).
