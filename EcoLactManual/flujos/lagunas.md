# Lagunas detectadas — Manual Administrador EcoLactWeb

> Fecha de inspección: 2026-10-28  
> Fuente: Inspección de routes/web.php, controladores y vistas.  
> Estas lagunas deben resolverse con capturas UI reales antes de redactar procedimientos.

---

## TIPO A — Funcionalidad observada en código pero sin verificación UI

| ID | Módulo | Descripción de la laguna |
|----|--------|--------------------------|
| LAG-A-001 | Dashboard | No se ha verificado qué KPIs muestra realmente (gráfico de acopio por zona, ventas del día, pagos pendientes). Pendiente captura real. |
| LAG-A-002 | Zonas / Mapa | No verificado si el mapa GeoJSON se renderiza correctamente sin datos de polígono. ¿Qué ocurre si la zona no tiene polígono configurado? |
| LAG-A-003 | Zonas / Asignación | El modal de asignación de proveedor a zona/ruta usa JavaScript para pintar el mapa. No verificado su comportamiento sin JS o sin zona con polígono. |
| LAG-A-004 | Entregas | El estado OBSERVADA y RECHAZADA aparece en el enum de estados. No verificado si el Admin puede ver y filtrar por esos estados desde su panel de entregas. |
| LAG-A-005 | Calidad / OCR | `escanear-ticket` es un OCR local. No verificado qué formato de imagen acepta, qué parámetros extrae y si propone valores correctamente. |
| LAG-A-006 | Insumos / Compras | El OCR de boleta (`compras-insumo/boleta`) propone campos. No verificado qué campos extrae ni el formato esperado de la imagen. |
| LAG-A-007 | Producción | El modal de nueva producción se abre con `?nueva=1`. No verificado si el modal persiste el formulario tras un error de validación. |
| LAG-A-008 | Configuración de pagos | El descuento por calidad OBSERVADA se guarda como ajuste negativo. No verificado cómo se refleja en el comprobante final del proveedor. |
| LAG-A-009 | Liquidaciones | Solo hay una ruta: `imprimir`. No hay pantalla de listado de liquidaciones para el Admin. Pendiente verificar desde dónde se accede al ID de la liquidación. |
| LAG-A-010 | Pagos generales | `pagos/generar` genera pagos desde Configuración. No verificado qué condiciones deben cumplirse para que aparezcan pagos disponibles. |
| LAG-A-011 | Control de asistencia | La ruta existe (`admin.asistencia.index`) pero no se ha inspeccionado la vista. No verificado qué acciones permite (¿solo lectura? ¿puede corregir?). |
| LAG-A-012 | Caja | Es de solo lectura. No verificado qué tipos de movimientos aparecen ni cómo se filtran por categoría. |
| LAG-A-013 | Stock general | La vista consolidada de stock no ha sido inspeccionada. No verificado si diferencia insumos de productos terminados. |
| LAG-A-014 | Comunicados | No verificado qué tipos de destinatarios puede elegir el Administrador (¿todos? ¿por zona? ¿por rol?). |
| LAG-A-015 | Mi perfil | La vista `admin.administrador.show` existe pero no se ha inspeccionado su contenido. ¿Permite editar datos propios? ¿Cambiar contraseña? |

---

## TIPO B — Rutas de solo fragmento (JSON/HTML sin pantalla propia)

Estas rutas son llamadas internas del frontend (fetch/AJAX) y no tienen pantalla independiente.
No requieren procedimiento propio, pero deben mencionarse en el contexto del módulo padre.

| Ruta | Propósito | Módulo padre |
|------|-----------|--------------|
| `GET /admin/proveedores/{prov}/panel` | Panel lateral de proveedor | Proveedores |
| `GET /admin/acopiadores/{acop}/panel` | Panel lateral de acopiador | Acopiadores |
| `GET /admin/zonas/{zona}/panel` | Panel lateral de zona | Zonas |
| `GET /admin/zonas/panel/sin-asignar` | Panel de proveedores sin zona | Zonas |
| `GET /admin/rutas/{ruta}/panel` | Panel de ruta (acordeón de zona) | Rutas |
| `GET /admin/entregas/{entrega}/detalle` | Modal "Ver entrega" | Entregas |
| `GET /admin/entregas/{entrega}/analisis-calidad/detalle` | Modal "Ver análisis" | Calidad |
| `GET /admin/parametros-calidad/{param}/panel` | Panel de parámetro | Calidad/Parámetros |
| `GET /admin/insumos/{insumo}/panel` | Panel de insumo | Insumos |
| `GET /admin/caja/{movimiento}/panel` | Detalle de movimiento de caja | Caja |
| `GET /admin/configuracion-pagos/trabajadores/{userId}/panel` | Panel de sueldo de trabajador | Config. Pagos |
| `GET /admin/pagos/trabajadores/{oblig}/panel` | Panel de pago trabajador | Pagos Trabajadores |
| `GET /admin/encargados-planta/{enc}/panel` | Panel de encargado | Personal Planta |
| `GET /admin/personal-ventas/{pv}/panel` | Panel de personal ventas | Personal Ventas |
| `GET /admin/analistas-calidad/{anal}/panel` | Panel de analista | Analistas |
| `GET /admin/proveedores/{prov}/entregas` | Entregas de un proveedor | Proveedores |
| `GET /admin/proveedores/{prov}/ventas` | Ventas a un proveedor | Ventas |
| `GET /admin/zonas/proveedores/buscar` | Búsqueda JSON de proveedores | Zonas |
| `GET /admin/rutas/{ruta}/proveedores-disponibles` | Búsqueda JSON para agregar a ruta | Rutas |
| `GET /admin/entregas/buscar-proveedor` | Búsqueda por DNI para alta de entrega | Entregas |
| `GET /admin/pagos/proveedores/comprobante/{oblig}` | Comprobante individual imprimible | Pagos Proveedores |
| `GET /admin/pagos/trabajadores/{oblig}/comprobante` | Comprobante de trabajador | Pagos Trabajadores |
| `GET /admin/ventas/saldo-proveedor/{prov}` | Saldo del proveedor para ventas | Ventas |

---

## TIPO C — Rutas que requieren verificación de reglas de negocio

| ID | Ruta | Regla de negocio a verificar |
|----|------|------------------------------|
| LAG-C-001 | Eliminar zona | La zona debe estar sin proveedores ni acopiadores activos. ¿Qué mensaje de error muestra si no se cumple? |
| LAG-C-002 | Desactivar zona | No se puede desactivar una zona con acopiadores o proveedores activos (regla en ActualizarZona). Verificar mensaje de error en UI. |
| LAG-C-003 | Desactivar proveedor | ¿Qué condiciones impiden desactivar un proveedor? (¿entregas pendientes? ¿liquidaciones abiertas?) |
| LAG-C-004 | Reactivar proveedor | Requiere ruta_id. ¿La UI muestra un selector de zonas/rutas disponibles? |
| LAG-C-005 | Reactivar acopiador | Requiere zona_id. ¿La UI muestra zonas activas? |
| LAG-C-006 | Análisis CONFIRMADO | Es inmutable incluso para ADMINISTRADOR (excepción explícita en AppServiceProvider). ¿La UI oculta el botón de editar para análisis CONFIRMADOS? |
| LAG-C-007 | Quitar proveedor de ruta | ¿La UI pide motivo? ¿Hay restricciones de negocio (ej. recorridos activos)? |
| LAG-C-008 | Ajuste de stock de leche | Solo admite restas (campo `solo_resta: true` en InsumoController). ¿La UI bloquea las sumas para la leche? |
| LAG-C-009 | Pago a proveedor | El pago solo se puede marcar al confirmar impresión del comprobante. ¿La UI requiere que el modal de impresión se abra antes de habilitar el botón? |
| LAG-C-010 | Ajuste de pago trabajador | Solo posible cuando la obligación está en estado PENDIENTE. ¿La UI deshabilita el botón de ajuste si ya está PAGADO? |

---

## TIPO D — Módulos detectados en vistas pero sin ruta propia inspeccionada

| Módulo | Observación |
|--------|-------------|
| Recetas | Las vistas `admin/recetas/*` existen pero no se inspeccionó su contenido Blade. Pendiente verificar formulario de creación y gestión de versiones en UI. |
| Fichas de personal | El directorio `admin/fichas-personal` existe en vistas pero no hay ruta directa. Posiblemente es un componente compartido. Pendiente inspección. |

---

## Estado de verificación global

| Estado | Cantidad |
|--------|----------|
| Implementado / verificado en código | 100 operaciones |
| Verificado en UI (captura real) | 0 |
| Pendiente de comprobar | 100 (todas requieren capturas UI) |
| Lagunas de reglas de negocio | 10 |
| Fragmentos sin pantalla propia (no documentar aparte) | 22 |
