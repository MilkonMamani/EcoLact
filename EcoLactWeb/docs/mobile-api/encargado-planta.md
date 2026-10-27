# EcoLact Móvil — ENCARGADO DE PLANTA

Contrato de API + diseño final de pantallas para ENCARGADO_PLANTA. El flujo de "Registrar producción" ya quedó ajustado a como realmente funciona el acopio: la leche NO se separa por entrega (el acopiador junta todo lo recolectado en un solo recipiente), así que el encargado registra directamente el litraje total, sin elegir entregas. Los insumos tampoco se escriben a mano: se calculan solos a partir de la receta del producto. Está detallado abajo.

## 1. Conexión y autenticación
Ver `administrador.md` sección 1. Este rol mezcla casing: `produccion` (listado) usa **camelCase** en `filas`; el resto, **snake_case**.

## 2. Navegación final
```
Inicio · Producción · Inventario · Más
```
Arriba: ● Online/Offline · ↻ Sincronización · 🔔 Notificaciones · 👤 Perfil.

## 3. INICIO

`GET /planta/dashboard?periodo=&desde=&hasta=`
```
data: {
  kpis: { enCurso, lotesProducidos, litrosUtilizados, productosProducidos },
  produccion_actual: [{ id, codigo, fecha_hora_inicio, fecha_hora_fin, litros_leche_utilizados, estado }],
  produccion_reciente: [{ ...mismo shape... }],
  insumos_bajo_minimo: [{ id, nombre, unidad_medida, stock_actual, stock_minimo }],
  inventario: { total, suficiente, bajo, sinStock },
  productos_disponibles: [{ producto, stock, unidad }],
  notificaciones_recientes: [{ id, tipo, titulo, mensaje, leido }]
}
```
`kpis` e `inventario` traen claves camelCase (heredado del Use Case), el resto snake_case.

**No hay alerta de "stock bajo de producto terminado"** en `productos_disponibles` — solo tiene nombre/stock/unidad, sin umbral (no existe `stock_minimo` para productos). No la diseñes. Las alertas de stock SÍ existen y son reales para **insumos** (`insumos_bajo_minimo`).

## 4. PRODUCCIÓN

`GET /planta/produccion?q=&estado=&periodo=&desde=&hasta=` (camelCase en `filas`)
```
data: {
  kpis: { enProceso, finalizados, lecheUtilizada, productosObtenidos },
  filas: [{ id, codigo, inicio, fin, litros, productos, estado }]
}
meta: {...}
```

### Registrar producción — flujo real y definitivo

**Paso "Leche"**: primero consulta el acumulado disponible (todo lo recibido menos todo lo ya usado en otros lotes — no está separado por entrega ni por día; tampoco cuenta la leche de muestras que Calidad ya confirmó como RECHAZADA, esa nunca llegó a acopiarse):
`GET /planta/produccion/leche-disponible` → `data: { litros_disponibles: number }`

`POST /planta/produccion`
```
body: {
  litros_utilizados: number,               // > 0 y <= litros_disponibles
  fecha_hora_inicio?: string,
  observacion?: string
}
→ 201 { data: { id, codigo, estado: "EN_PROCESO" } }
```
`codigo` lo genera el servidor solo (`LOT-00001`, `LOT-00002`, ...) — nunca lo pidas al usuario. Si `litros_utilizados` supera lo disponible, el backend responde `422` con el mensaje indicando disponible vs. solicitado.

`GET /planta/produccion/{lote}` (detalle + catálogo de apoyo para el paso "Resultado")
```
data: {
  lote: {
    id, codigo, estado, fecha_hora_inicio, fecha_hora_fin, litros_leche_utilizados, observacion,
    insumos_utilizados: [{ insumoId, insumoNombre, unidadMedida, cantidadUsada }],
    productos_obtenidos: [{ productoId, productoNombre, unidadMedida, cantidadProducida }]
  },
  productos_activos: [{ id, nombre, unidad_medida }]
}
```
Nota: dentro de `lote.insumos_utilizados` / `productos_obtenidos`, cada objeto usa **camelCase** internamente, aunque el nombre del array padre esté en snake_case.

**Paso "Resultado" + "Confirmar" = un solo `POST`, sin paso de insumos:**
`POST /planta/produccion/{lote}/resultado`
```
body: {
  producto_id: int,
  cantidad_producida: number,
  marcar_finalizado?: boolean,             // default true
  fecha_hora_fin?: string
}
→ 201 { data: {
    id, codigo, estado, fecha_hora_inicio, fecha_hora_fin, litros_leche_utilizados, observacion,
    insumos_utilizados, productos_obtenidos
  } }
```
Los insumos consumidos **ya no se envían**: el servidor los calcula solo (receta del producto × `cantidad_producida`) y los descuenta del inventario automáticamente — la respuesta los muestra igual, para que la pantalla de confirmación siga mostrando qué se consumió. Si el producto no tiene receta configurada todavía, no se consume ningún insumo (no bloquea la producción). Si algún insumo no alcanza, responde `422` con el detalle.

⚠️ A diferencia del `GET /planta/produccion/{lote}`, aquí la respuesta **no** viene anidada bajo una clave `lote` — el objeto del lote está directo en `data`.

**"Merma %" del mockup no se persiste en ningún lado** (ni el campo existe en el backend). Si quieres mostrarla, calcúlala en el cliente como referencia visual (litros usados vs. unidades obtenidas), pero no la envíes ni esperes que el servidor la guarde.

### Receta de insumos (configuración, la define el Administrador)

No hay pantalla móvil para esto todavía — solo existe vía API, gestionada desde el rol ADMINISTRADOR (`GET`/`PUT /admin/productos/{producto}/receta`, documentado en `administrador.md`). El Encargado de Planta solo ve el resultado (los insumos ya descontados), nunca la configura.

## 5. INVENTARIO

### Insumos
`GET /planta/insumos?buscar=&nivel=bajo|normal|sin_stock`
```
data: {
  kpis: { insumos, stock_bajo, con_stock, sin_stock },
  filas: [{ id, nombre, unidad_medida, stock_actual, stock_minimo, estado, esta_bajo_minimo }]
}
```
`GET /planta/insumos/{id}` → `data: { insumo: {...}, movimientos: [{ id, tipo, motivo, cantidad, fecha, observacion }] }` (últimos 10)
`GET /planta/insumos/movimientos?insumo_id=&tipo=&desde=&hasta=` → `data: { kpis: {total, entradas, salidas}, filas: [{ id, insumo_id, insumo_nombre, tipo, motivo, cantidad, fecha, observacion }] }`
`POST /planta/insumos` (registrar insumo nuevo) → body `{ nombre, unidad_medida, stock_minimo, stock_inicial? }` → `201 { data: {...insumo...} }`
`POST /planta/insumos/{id}/movimiento` (ajuste manual, no el consumo automático de producción) → body `{ tipo: "ENTRADA"|"SALIDA", motivo, cantidad, observacion? }` → `200 { data: {...insumo actualizado...} }`, o `422 { message }` si el stock quedaría negativo.

### Productos (solo lectura — el ajuste de inventario de productos es de VENTAS)
`GET /planta/productos?buscar=` → `data: { kpis: {productos, stock_total, agotados}, filas: [{ id, nombre, unidad_medida, cantidad_actual, sin_stock }] }`
`GET /planta/productos/{id}` → `data: { tipo: "PRODUCTO", id, nombre, unidad_medida, cantidad_actual, estado, movimientos: [{fecha, tipo, cantidad, origen}] }`

## 6. MÁS
- `GET /notificaciones`, `PATCH /notificaciones/{id}/leida` → ver `administrador.md` sección 7.
- **No hay endpoint de comunicados para este rol todavía** (no estaba en el alcance que pediste construir). Pídelo aparte si lo necesitas — el dominio ya soporta lectura de comunicados dirigidos a cualquier usuario, es solo exponerlo.

## 7. Offline — conflicto de stock
Si dos dispositivos consumen el mismo insumo mientras ambos están offline, el servidor los serializa: cada inicio de producción vuelve a calcular la receta y bloquea los insumos (`FOR UPDATE`) antes de descontar, así que el stock nunca queda negativo ni inconsistente. El `POST` que llegue cuando ya no alcanza recibe `422 { message, motivos }` y la app lo deja en "revisión requerida".

## 8. Reintentos y hora real del evento (auditoría 2026-10-27)
- `POST /planta/produccion`, `POST /planta/produccion/{lote}/finalizar` y `POST /planta/compras-insumo` exigen la cabecera `Idempotency-Key` = id local de la operación. Un reintento con la misma clave devuelve la respuesta original (`Idempotent-Replayed: true`) sin crear otro lote, consumir insumos, ingresar producto, crear otra compra ni otra salida de caja. La clave queda ligada al método + ruta concreta: reutilizarla en otro lote devuelve `422`.
- Las tres aceptan `fecha_hora_evento` (ISO 8601 con desfase). Producción guarda con ella `fecha_hora_inicio` / `fecha_hora_fin` y la fecha de los movimientos de stock; si el cierre llega con una hora anterior al inicio (reloj del teléfono), el lote cierra a la hora de inicio. Una compra sin `fecha` de boleta queda con el día del evento en la zona del negocio.
- `finalizar` acepta `observacion` (observación de cierre).
