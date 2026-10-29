# Auditoría de roles — EcoLactMobile

> Fecha: 29/10/2026. Fuente: código de `EcoLactMobile/shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile` (solo lectura) y rutas `EcoLactWeb/routes/api.php`.
> Evidencia visual: capturas de la app en ejecución del 28/10/2026 (manual anterior) y del emulador Pixel_7 del 29/10/2026.

## Clasificación usada
- **A** Implementado y comprobado visualmente.
- **B** Implementado en código; pendiente de verificación visual.
- **C** Parcialmente implementado.
- **D** No implementado.
- **E** Implementado exclusivamente en EcoLactWeb.

## Cómo decide la app el rol
`App.kt` enruta por `session.role` (enum `core/session/Role.kt`, 6 valores que envía el backend):

| Valor | Interfaz móvil | Decisión |
|---|---|---|
| `ENCARGADO_PLANTA` | `feature/planta/PlantaApp.kt` | **Incluido** (cap. 3) |
| `VENTAS` | `feature/ventas/VentasApp.kt` | **Incluido** (cap. 4) |
| `CALIDAD` | `feature/calidad/CalidadApp.kt` | **Incluido** (cap. 5) |
| `PROVEEDOR` | `feature/proveedor/ProveedorApp.kt` | **Incluido** (cap. 6) |
| `ACOPIADOR` | `feature/acopiador/AcopiadorApp.kt` | **Incluido** (cap. 7) |
| `ADMINISTRADOR` | `RolNoDisponibleEnMovil`: solo mensaje «Este rol (administrador) se gestiona desde el sistema web…» y «Cerrar sesión» | **Excluido** (E). Mencionado en 1.2. |

No hay sub-roles ni permisos configurables. Regla transversal (`feature/trabajador/AsistenciaGate.kt`): sin asistencia válida **hoy** los trabajadores consultan pero no operan; el servidor lo vuelve a validar con el middleware `requiere.asistencia`. El Proveedor no marca asistencia.

---

## 1. Encargado de planta (`ENCARGADO_PLANTA`)
- **Evidencia en código:** `PlantaRoutes.kt` (Inicio, Producción, Inventario, NuevaProduccion, LoteDetalle, InsumoDetalle, ProductoDetalle, Recetas, RecetaDetalle, Compras, CamaraBoleta, RevisarCompra, MiQr, Notificaciones, Perfil); `PlantaApi.kt` → `planta/*`.
- **Pantallas:** las 16 rutas anteriores. Barra inferior Inicio/Producción/Inventario; menú Operación, Inventario, Gestión, Sistema.
- **Permisos:** escribir `planta/produccion`, `planta/produccion/{lote}/finalizar`, `planta/compras-insumo`, `planta/compras-insumo/boleta` (todas con `requiere.asistencia`). Recetas, productos y precios solo lectura.
- **Comprobadas (A):** Inicio, menú, aviso de asistencia, Mi QR, insumos y detalle, compras (lista y confirmación «Compra registrada»), compra manual, nueva producción, finalizar lote, producción, productos, recetas, perfil.
- **Pendientes (B):** cámara «Tomar foto de la boleta» y revisión OCR «Datos detectados».
- **Backend verificado:** `IniciarProduccion` descuenta insumos (una transacción, revalida stock); `FinalizarProduccion` ingresa lo obtenido sin volver a descontar; `RegistrarCompraInsumos` ingresa stock al confirmar y registra pago de caja si hay total; OCR con Tesseract (`TesseractBoletaCompraOcrService`).
- **Decisión:** incluido, 12 procedimientos.

## 2. Personal de ventas (`VENTAS`)
- **Evidencia:** `VentasRoutes.kt` (INICIO, VENDER, HISTORIAL, CLIENTE, RESUMEN, CONFIRMACION, DETALLE, BOLETA, COMPRADORES, NUEVO_COMPRADOR, PRODUCTOS, COMUNICADOS, NOTIFICACIONES, QR, PERFIL); `VentasApi.kt` → `ventas/*`.
- **Permisos:** `ventas/ventas` (idempotente + asistencia), `ventas/compradores` (asistencia), `ventas/cotizar`, boleta PDF.
- **Comprobadas (A):** Inicio, productos y precios, compradores (lista), seleccionar cliente, resumen y cálculo, historial (V-00005 confirma una venta registrada), perfil, QR.
- **Pendientes (B):** pantalla «Venta registrada», «Boleta de venta» (Descargar PDF / Compartir), formulario «Nuevo comprador», comunicados.
- **Backend verificado:** `RegistrarVentaProducto` decide el precio (proveedor → PROVEEDOR; cantidad ≥ mínimo → MAYORISTA; resto → PUBLICO); `DESCUENTO_PLANILLA` solo para proveedor y con tope del pago semanal; venta al contado genera entrada de caja.
- **Decisión:** incluido, 8 procedimientos.

## 3. Analista de calidad (`CALIDAD`)
- **Evidencia:** `CalidadApp.kt` (Inicio, Muestreo, Historial, Muestras pendientes, Detalle de entrega, Tomar foto del ticket, Revisar datos, Detalle de análisis, Muestreo directo, Proveedores, Proveedor, Comunicados, Comunicado, Nuevo comunicado, Notificaciones, Mi QR / Asistencia, Mi perfil).
- **Permisos:** `calidad/muestreos`, `calidad/muestreos/aleatorio`, `calidad/entregas`, `calidad/analisis-calidad` (+ `escanear-ticket`, `finalizar`), `calidad/comunicados` (todas con asistencia).
- **Comprobadas (A):** Inicio con pendientes, muestreo directo completo hasta «Muestra registrada», detalle de entrega pendiente, nuevo comunicado y notificación «Aviso creado», perfil.
- **Pendientes (B):** Muestreo aleatorio/manual, cámara del ticket, revisión de valores, «Análisis confirmado», historial, proveedores, Mi QR.
- **Decisión:** incluido, 8 procedimientos. El manual anterior solo cubría el muestreo directo.

## 4. Proveedor (`PROVEEDOR`)
- **Evidencia:** `ProveedorRoutes.kt` y `ProveedorApp.kt` (Inicio, Mis entregas, Detalle de entrega, Mis pagos, Detalle de pago, Mi calidad, Análisis de calidad, Mis compras, Detalle de compra, Comunicados, Comunicado, Notificaciones, Mi perfil, Sincronización).
- **Permisos:** solo lectura (`proveedor/*`); marca notificaciones como leídas.
- **Comprobadas (A):** Inicio con QR, menú, entregas y detalle, Mi calidad y análisis, Mis pagos (vacío), Mis compras (lista), comunicado (detalle), notificaciones, perfil.
- **Pendientes (B):** detalle de pago/liquidación, detalle de compra, lista de comunicados, Sincronización.
- **Decisión:** incluido, 9 procedimientos.

## 5. Acopiador (`ACOPIADOR`)
- **Evidencia:** `AcopiadorApp.kt` (Inicio, Entregas, Proveedores, Registrar entrega, Detalle de entrega, Detalle/Historial de proveedor, Recorrido, Paradas del recorrido, Seguimiento de calidad, Comunicados, Notificaciones, Mi perfil, Mi QR / Asistencia).
- **Permisos:** `acopiador/entregas`, `acopiador/recorridos/*` (iniciar, finalizar, acopio, no-entregó), todas con asistencia.
- **Comprobadas (A):** Inicio, menú, registro por QR y DNI, formulario y «Entrega guardada» con/sin conexión, historial y detalle, proveedores, seguimiento de calidad, comunicados, notificaciones, perfil, QR.
- **Pendientes (B):** Recorrido del día (pantalla nueva), «Corregir entrega» ante error de sincronización.
- **Decisión:** incluido, 8 procedimientos.

## Funciones comunes (cap. 2)
Inicio de sesión (A), estructura de navegación (A), asistencia por QR (A, aviso y QR; habilitación automática B), trabajo sin conexión (C: estados visibles A, envío automático B), notificaciones y cierre de sesión (A).
