# Observaciones documentales — revisión del manual anterior

> Documento revisado: `referencias/Manual_Movil_Actual.pdf` (60 páginas, sin modificar).
> Resultado: `salida/Manual_Usuario_EcoLact_Movil.docx` / `.pdf` (versión 2.0, 69 páginas).

## 1. Contenido que se conservó
- Las 76 capturas originales son auténticas (app del 28/10/2026); se reutilizaron 54 tras contrastarlas con el código actual. Las restantes eran duplicadas (portadas de rol, inicio repetido) o no correspondían a su texto (ver 3).
- Los procedimientos del Encargado de planta eran correctos y completos: se conservaron con sus marcas originales (incrustadas en la imagen), corrigiendo solo la redacción y dos afirmaciones (ver 2).
- Ejemplos útiles (Yogurt lúcumo 30 unidades, Mantequilla cremosa, Queso Paria 3 + 19 = 22).

## 2. Afirmaciones corregidas (no coincidían con la app o eran absolutas)
| Pág. anterior | Afirmación | Corrección según el código |
|---|---|---|
| 4 | «Escanea la boleta…: el stock sube solo» | La boleta se lee por OCR, el usuario revisa y confirma; el stock sube al registrarse la compra en el servidor. |
| 11 | «el stock se actualiza al instante» | Sin conexión la compra queda «Pendiente de envío» y el stock cambia al sincronizar. |
| 12 | «descuenta los insumos» | Correcto, pero sin conexión el servidor revalida al sincronizar y puede rechazar la producción si el inventario cambió. |
| 30–37 | Capítulo de Calidad titulado «Confirmar y analizar» | Solo mostraba el muestreo directo; no documentaba el muestreo aleatorio/manual ni el registro y confirmación del análisis. |
| 56 | «sincroniza automáticamente» | Se mantiene como comportamiento previsto; no se probó la transición en esta revisión. |
| 19–60 | No mencionaban la asistencia para Ventas, Calidad y Acopiador | El código exige asistencia del día para operar en esos roles (`AsistenciaGate`, `requiere.asistencia`). |

## 3. Capturas mal ubicadas o de otro rol
- Pág. 48 (Proveedor › Mis compras): la «Pantalla siguiente» era **«Análisis de calidad»**, no el detalle de la compra. Ahora esa captura ilustra MOB-PRV-004.
- Pág. 49 (Proveedor › Comunicados): la primera captura era **«Detalle de proveedor» del Acopiador** (punto naranja, botón «Registrar entrega»). Se descartó para el Proveedor.
- Pág. 46 (Mi calidad): el texto describía parámetros que no aparecían en la captura. Ahora se acompaña de la pantalla de análisis.
- Págs. 55/56: la misma captura «Entrega guardada» se usaba en dos secciones (se mantiene, con su función distinta en cada procedimiento).

## 4. Marcas (recuadros y números) incorrectas
Al recuperar las anotaciones vectoriales del PDF se comprobó que varias no señalaban el control descrito: págs. 25 (cantidad), 26 (importe y botón), 44 (filtro «Semana» sobre el texto del encabezado), 56, 57, 58, 59 y 60 (recuadros desplazados hacia abajo) y 20 (dos marcas con el número 2). En la versión 2.0 todas las marcas se ubicaron sobre el texto real del control (OCR) o sobre el botón (detección de color) y se revisaron visualmente (`evidencias/anotadas/`, `evidencias/ledger.json`).

## 5. Ortografía y estilo
- Capítulos 2 a 5 (págs. 19–60) sin tildes ni eñes: «Guia», «aplicacion», «sesion», «Contrasena», «codigo», «Analisis», «Menu», «Anade», «continues». Corregido.
- Numeración inconsistente entre roles (Planta «02 / 16», Ventas «02», Proveedor «02 / 11») y el índice general remitía a rangos de páginas. Sustituido por numeración de capítulos, códigos MOB-XXX-NNN e índice automático de Word.
- Tratamiento mixto (tú/usted) y frases incompletas en las leyendas. Unificado en usted con verbos imperativos.

## 6. Datos personales
El manual anterior mostraba QR de asistencia, DNI, teléfonos y usuarios de prueba. En 2.0 se pixelaron (QR, números de 7 o más dígitos y usuarios; `scripts/privacidad.py`, registro en `trabajo/privacidad.json`).

## 7. Diseño
Se eliminaron fondos verdes, tarjetas, portada oscura y bloques decorativos. Diseño nuevo: A4, Arial 10,5 pt, títulos negros, tablas y recuadros con borde negro fino, encabezados de tabla en gris muy claro, encabezado y pie uniformes, estilos de Word (Título 1/2, Descripción, Índice). Las capturas conservan sus colores; solo las marcas son rojas.

## 8. Observaciones para el equipo de desarrollo (no se modificó nada)
- Texto de notificación de la app/servidor: «Se creo con exito un comunicado para a general» (faltan tildes y sobra «a»). Visible en la captura p39_x180.
- «Nuevo comunicado» pide «ID de zona» e «IDs de usuario separados por coma»: poco usable para el analista.
- En capturas de Calidad (p32, p34, p38) aparece la barra flotante del teclado del emulador sobre la interfaz.
