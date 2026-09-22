# EcoLact Móvil — ADMINISTRADOR

Contrato de API + diseño final de pantallas para el rol ADMINISTRADOR, ya ajustado contra el backend real (Laravel). Este documento reemplaza al mockup original en todo lo que cambió: **se eliminó Auditoría** (no existe en el backend, ni siquiera en el panel web) **y toda alerta/columna de "stock bajo" de producto terminado** (no hay `stock_minimo` para productos, solo para insumos). No las menciones ni las diseñes.

## 1. Conexión y autenticación

- Base URL: `https://<host>/api/v1`
- Login: `POST /login` con `{ "username": "...", "password": "..." }` → `{ "data": { "token": "...", "user": { "id", "username", "role", "estado", "debe_cambiar_password", "ultimo_acceso" } } }`
- Todas las demás rutas van con header `Authorization: Bearer {token}` (Sanctum).
- `POST /logout`, `GET /me` disponibles para cualquier rol autenticado.
- Errores: `422` (validación, `{message, errors}`), `403` (rol/policy), `404`.

### ⚠️ Advertencia de formato: mezcla de camelCase y snake_case

El backend no es 100% consistente en el casing de las claves JSON (arrastrado de cuando esta API se construyó reutilizando código que originalmente alimentaba las vistas web):

- Los endpoints de **listado** que reutilizan las `*ListadoQuery` ya existentes (`zonas`, `acopiadores`, `proveedores`, `entregas`, `liquidaciones`, `pagos`, `analisis-calidad`, `produccion`, `ventas`, `comunicados`, y el `dashboard`) devuelven `kpis` y `filas` en **camelCase** (`proveedorNombre`, `zonaNombre`, `totalAcopiado`, etc.).
- Los endpoints de **detalle** (`show`) y los nuevos construidos para esta app (`usuarios`, `roles`, `encargados-planta`, `personal-ventas`, `analistas-calidad`) devuelven en **snake_case** (`proveedor_id`, `fecha_hora`, `nombre_completo`, etc.).

Configura tu deserializador (`kotlinx.serialization`) por endpoint, no globalmente. Cada bloque de abajo indica el casing real.

### Paginación

Los endpoints de listado devuelven `meta: { current_page, per_page, total, last_page }`. Parámetros de query: `page`, `per_page` (máx. 100).

## 2. Navegación final

```
Inicio · Operación · Negocio · Gestión
```
Arriba, globales: 🔔 Notificaciones · ● Online/Offline · ↻ Sincronización · 👤 Perfil.

## 3. INICIO

`GET /admin/dashboard?periodo=hoy|semana|mes|personalizado&desde=YYYY-MM-DD&hasta=YYYY-MM-DD` (camelCase)

```
data: {
  periodo: { nombre, desde, hasta, etiqueta },
  kpis: { totalAcopiado, proveedoresAtendidos, proveedoresActivos, porcentajeAtendidos,
          calidadAceptada, analisisConfirmados, entregasObservadas, totalValorizado,
          produccion, produccionPorEstado: { [estado]: int } },
  litrosPorDia: [{ fecha, etiqueta, litros, entregas }],
  distribucionCalidad: [{ resultado, cantidad, porcentaje, litros }],
  rendimientoZonas: [{ id, nombre, proveedores, litros, confirmados, calidad }],
  entregasRecientes: [{ id, fecha, proveedorNombre, zonaNombre, litros, calidad, estado }],
  actividadReciente: [{ tipo, id, titulo, detalle, fecha }],
  produccionReciente: { id, codigo, estado, fecha, litros, productos: [{nombre, unidad, cantidad}] } | null,
  comunicaciones: { publicados, destinatarios, leidos, tasaLectura, recientes: [{id, titulo, fecha, alcance}] }
}
```

**Nota de diseño**: este endpoint **no trae un bloque "Atención requerida"** como bloque separado. Constrúyelo en el cliente combinando `distribucionCalidad` (rechazos/observados), `entregasRecientes` con `estado=PENDIENTE_ANALISIS`, y `rendimientoZonas`. No hay alerta de stock (eliminada).

- "Leche acopiada" / "Entregas" / "Pendientes de análisis" → de `kpis`.
- "Operación de hoy" por zona → `rendimientoZonas`.
- "Resumen del negocio" (Finanzas/Producción/Ventas) → compón con `kpis.totalValorizado` + `produccionReciente` + un side-call liviano a `GET /admin/ventas?periodo=hoy` si quieres el monto vendido hoy (el dashboard no trae ventas).
- "Actividad reciente" → `actividadReciente`.

## 4. OPERACIÓN

### 4.1 Acopio
- `GET /admin/entregas?q=&zona_id=&acopiador_id=&estado=&fecha=&desde=&hasta=&page=&per_page=` (camelCase en `filas`)
  → `data: { kpis: {litrosRegistrados, entregas, pendientesAnalisis, analizadas}, zonas: [{id,nombre}], acopiadores: [{id,nombre}], filas: [{id, fecha, proveedorNombre, proveedorDni, zonaNombre, acopiadorNombre, litros, metodoIdentificacion, estado, resultado}] }`, `meta`.
- `GET /admin/entregas/{id}` (snake_case) → `data: { id, proveedor_id, acopiador_id, zona_id, registrado_por_user_id, fecha_hora, litros, metodo_identificacion, estado, observacion }`

### 4.2 Calidad (solo lectura — analizar/confirmar es del rol CALIDAD)
- `GET /admin/analisis-calidad?q=&zona_id=&resultado=&estado=&fecha=&desde=&hasta=&page=&per_page=` (camelCase en `filas`) → `data: { kpis: {total, aceptadas, observadas, rechazadas}, zonas: [{id,nombre}], filas: [{id, fecha, proveedorNombre, proveedorDni, entregaId, zonaNombre, resultado, estado, confirmadoPor}] }`, `meta`.
- `GET /admin/analisis-calidad/{id}` (snake_case) → `data: { id, entrega_id, imagen_path, estado, resultado, fecha_analisis, confirmado_por_user_id, observacion }`

## 5. NEGOCIO

### 5.1 Finanzas — Liquidaciones
- `GET /admin/liquidaciones?q=&estado=&incidencia=&zona_id=&periodo_id=&page=&per_page=` (camelCase en `filas`) → `data: { kpis: {proveedoresLiquidados, litrosEntregados, totalPagar, pendientesPago}, zonas, periodos: [{id,label}], filas: [{id, proveedorNombre, proveedorDni, periodoDesde, periodoHasta, litrosEntregados, litrosPagables, precioAplicado, tuvoIncidencia, totalPagar, estado}] }`, `meta`.
- `GET /admin/liquidaciones/{id}` (snake_case, anidado) → `data: { liquidacion: {...}, proveedor_nombre, proveedor_dni, entregas, incidencia, compras: [...], ajustes: [...], pagos: [...], saldo_pendiente }`
- `GET /admin/liquidaciones/{id}/pdf` y `GET /admin/liquidaciones/periodos/{periodo}/pdf?zona_id=` → PDF binario (`Content-Type: application/pdf`), no JSON. Úsalos para el botón "Imprimir".

### 5.2 Finanzas — Pagos
- `GET /admin/pagos?q=&estado=&zona_id=&page=&per_page=` (camelCase en `filas`) → `data: { kpis: {pendientesPago, pagosRealizados, montoPendiente, montoPagado}, zonas, filas: [{liquidacionId, proveedorNombre, proveedorDni, periodoDesde, periodoHasta, monto, estadoPago, fechaPago}] }`, `meta`.
- `GET /admin/pagos/{id}` (snake_case) → `data: { id, liquidacion_id, fecha_pago, monto, estado, registrado_por_user_id, observacion }`
- **No hay `forma_pago`** en el pago a proveedor: siempre es efectivo en sobre sellado (regla de negocio fija).

### 5.3 Producción
- `GET /admin/produccion?q=&estado=&periodo=&desde=&hasta=&page=&per_page=` (camelCase en `filas`) → `data: { kpis: {enProceso, finalizados, lecheUtilizada, productosObtenidos}, filas: [{id, codigo, inicio, fin, litros, productos, estado}] }`, `meta`.
- `GET /admin/produccion/{id}` (snake_case) → `data: { id, codigo, estado, fecha_hora_inicio, fecha_hora_fin, litros_leche_utilizados, observacion, insumos_utilizados: [{insumoId, insumoNombre, unidadMedida, cantidadUsada}], productos_obtenidos: [{productoId, productoNombre, unidadMedida, cantidadProducida}] }`
  - Ojo: `insumos_utilizados`/`productos_obtenidos` son arrays snake_case por fuera, pero cada objeto interno usa camelCase (así los devuelve el Use Case). Verifica con una llamada real antes de generar el modelo.
  - El acopio no separa la leche por entrega (se junta toda), así que no existe un desglose "qué entrega alimentó este lote" — solo el total en `litros_leche_utilizados`.
  - Los insumos se calculan y descuentan automáticamente según la receta del producto (ver más abajo) — el Encargado de Planta no los escribe a mano.

**Receta de insumos por producto** (config, exclusiva de Administrador):
- `GET /admin/productos/{producto}/receta` (snake_case) → `data: [{ insumoId, insumoNombre, unidadMedida, cantidadPorUnidad }]` (todo el array en camelCase, heredado del Use Case).
- `PUT /admin/productos/{producto}/receta` body: `{ ingredientes: [{ insumo_id: int, cantidad_por_unidad: number }] }` → reemplaza toda la receta del producto (lo que no envíes, se elimina). `cantidad_por_unidad` = cuánto de ese insumo se necesita para producir UNA unidad del producto (ej. 0.005 kg de cuajo por queso paria). Sin pantalla móvil todavía — solo vía API.

### 5.4 Inventario
- `GET /admin/stock` (snake_case) → `data: [{ tipo: "PRODUCTO"|"INSUMO", id, nombre, unidad_medida, cantidad_actual, stock_minimo, estado }]` — `stock_minimo` es `null` siempre para `tipo=PRODUCTO` (no existe ese concepto en el backend).
- `GET /admin/stock/{tipo}/{id}` (`tipo` = `producto` o `insumo`) (snake_case) → `data: { tipo, id, nombre, unidad_medida, cantidad_actual, stock_minimo, estado, movimientos: [{fecha, tipo, cantidad, origen}] }`

### 5.5 Ventas
- `GET /admin/ventas?periodo=&desde=&hasta=&q=&tipo_cliente=&forma_pago=&estado=&page=&per_page=` (camelCase en `filas`) → `data: { kpis, filas }`, `meta`.
- `GET /admin/ventas/{id}` (snake_case) → `data: { id, producto_id, producto_nombre, proveedor_id, comprador_id, comprador_nombre, tipo_cliente, cantidad, precio_unitario, importe_total, forma_pago, estado, fecha }`
- `tipo_cliente` real: `PROVEEDOR | MAYORISTA | PUBLICO`. `forma_pago` real: `CONTADO | DESCUENTO_PLANILLA`.

## 6. GESTIÓN

### 6.1 Comunicados
- `GET /admin/comunicados?q=&tipo=&estado=&periodo=&desde=&hasta=&page=&per_page=` (camelCase en `filas`) → `data: { kpis: {total, publicados, borradores, destinatariosAlcanzados}, filas: [{id, titulo, tipo, destinatarios, fecha, estado}] }`, `meta`.
- `GET /admin/comunicados/{id}` (snake_case) → `data: { id, titulo, contenido, tipo, alcance, zona_id, fecha_publicacion, fecha_evento, estado, destinatarios: [{userId, nombre, dni, leido, fechaLectura}], leidos, no_leidos }` (dentro de `destinatarios`, claves camelCase — heredado de `ObtenerDetalleComunicado`).
- `POST /admin/comunicados` body: `{ titulo, contenido, tipo, alcance: "INDIVIDUAL"|"ZONA"|"GENERAL", destinatarios?: [userId,...], zona_id? (requerido si alcance=ZONA), fecha_evento?, estado?, fecha_publicacion? }` → `201` con el comunicado creado.
  - **`ZONA` y `GENERAL` solo resuelven proveedores** automáticamente. Para llegar a personal interno (Encargados de planta, Ventas, Calidad) usa `alcance: "INDIVIDUAL"` con la lista de `user_id` que obtienes de `GET /admin/encargados-planta`, `/admin/personal-ventas`, `/admin/analistas-calidad` (cada fila trae el `id` del registro, no el `user_id` directamente — pídeme si necesitas exponer también `user_id` en esas filas).

### 6.2 Usuarios (vista compuesta)
- `GET /admin/usuarios?tipo=&estado=&buscar=` (snake_case) → `data: { kpis: {total, activos}, filas: [{ tipo: "ACOPIADOR"|"PROVEEDOR"|"ENCARGADO_PLANTA"|"VENTAS"|"CALIDAD", id, nombre_completo, dni, estado, zona_id }] }`
  - `id` es el id del registro de ese tipo (acopiador.id, proveedor.id, etc.), **no** el `user_id`. Para el detalle, usa el endpoint propio de cada tipo:
    - ACOPIADOR → `GET /admin/acopiadores/{id}`
    - PROVEEDOR → `GET /admin/proveedores/{id}`
    - ENCARGADO_PLANTA → `GET /admin/encargados-planta/{id}`
    - VENTAS → `GET /admin/personal-ventas/{id}`
    - CALIDAD → `GET /admin/analistas-calidad/{id}`
  - **No hay "Cambiar zona" ni "Restablecer contraseña" ni "Desactivar cuenta" expuestos todavía** en esta API (existen en el backend web para algunos tipos, pero no los expuse aún). Si los necesitas para el mockup, pídemelos aparte.

- `GET /admin/acopiadores?q=&estado=&zona_id=&page=&per_page=` (camelCase en `filas`) → `data: { kpis, zonas, filas: [{id, nombre, dni, zonaNombre, telefono, entregas, estado}] }`, `meta`.
- `GET /admin/acopiadores/{id}` (snake_case) → `data: { acopiador: {id, user_id, zona_id, dni, nombres, apellidos, nombre_completo, telefono, estado, fecha_ingreso}, entregas: [...], zona_historial: [...] }`
- `GET /admin/proveedores?q=&estado=&zona_id=&seguimiento=&page=&per_page=` (camelCase en `filas`) → `data: { kpis, zonas, filas: [{id, nombre, dni, zonaNombre, telefono, advertencias, estado}] }`, `meta`.
- `GET /admin/proveedores/{id}` (snake_case) → `data: { proveedor: {...}, entregas: [...], analisis: [...], liquidaciones: [...], compras: [...], zona_historial: [...] }`
- `GET /admin/encargados-planta` / `GET /admin/encargados-planta/{id}` (snake_case) → lista/detalle: `{ id, dni, nombres, apellidos, nombre_completo, telefono, estado, fecha_ingreso }`
- `GET /admin/personal-ventas` / `GET /admin/personal-ventas/{id}` (snake_case) → mismo shape que encargados-planta.
- `GET /admin/analistas-calidad` / `GET /admin/analistas-calidad/{id}` (snake_case) → mismo shape.

### 6.3 Roles
- `GET /admin/roles` (snake_case) → `data: [{ id, nombre, descripcion, usuarios_count }]`
- No hay constructor de roles/permisos (deliberado: los 6 roles son fijos). La pantalla solo lista roles + conteo; al entrar a un rol, filtra `GET /admin/usuarios?tipo=...` en el cliente.

### 6.4 Configuración
No incluida en esta ronda (precios de leche, reglas de calidad, reglas de pago existen completas en el backend pero no están expuestas todavía en esta API). Pídemelas si las necesitas para el mockup de Configuración.

### 6.5 ~~Auditoría~~
Eliminada. No existe en el backend (ni tabla poblada ni lógica). No la incluyas en el diseño.

## 7. Notificaciones (compartido, todos los roles)
- `GET /notificaciones` (snake_case) → `data: [{ id, tipo, titulo, mensaje, referencia_tipo, referencia_id, leido, fecha_lectura }]`
- `PATCH /notificaciones/{id}/leida` → `200 { message }`

## 8. Lo que el Administrador NO puede hacer desde móvil (por diseño, backend lo soporta pero se excluyó a propósito)
Registrar entrega, análisis, producción o venta; crear proveedor/usuario/zona; configurar calidad o precios. Todo eso es de solo lectura/supervisión para este rol.
