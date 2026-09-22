# EcoLact Móvil — VENTAS

Contrato de API + diseño final de pantallas para el rol VENTAS. Es el rol con más ajustes reales frente al mockup original: **no hay carrito multi-producto atómico**, **`forma_pago` es obligatorio** aunque el mockup nunca lo pide, y el "comprador" puede en realidad ser un proveedor pagando con cargo a su liquidación. Todo corregido abajo.

## 1. Conexión y autenticación
Ver `administrador.md` sección 1. `GET /ventas/ventas` (listado) devuelve `filas` en **camelCase**; todo lo demás, **snake_case**.

## 2. Navegación final
```
Inicio · + Vender · Ventas · Más
```
Arriba: ● Online/Offline · ↻ Sincronización · 🔔 Notificaciones · 👤 Perfil.

## 3. INICIO

`GET /ventas/dashboard?periodo=&desde=&hasta=`
```
data: {
  kpis: { ventas, totalVendido, productosVendidos, ticketPromedio },
  ventas_recientes: [{ id, producto_id, proveedor_id, comprador_id, tipo_cliente, cantidad,
                         importe_total, forma_pago, estado, fecha }],
  stock_critico: [{ producto, stock, unidad }],
  mas_vendidos: [{ producto, cantidad }],
  notificaciones_recientes: [{ id, tipo, titulo, mensaje, leido }]
}
```

⚠️ **`stock_critico` no es "stock bajo"**: solo incluye productos con `stock = 0` (agotados). No existe umbral de mínimo para productos terminados (a diferencia de insumos), así que no hay forma de detectar "está por agotarse" — solo "ya se agotó". Rotula esta sección como **"Agotados"**, no "Stock bajo", para no prometer algo que el dato no respalda.

## 4. VENDER (nueva venta)

`GET /ventas/ventas/crear` — datos de apoyo para armar el wizard:
```
data: {
  productos: [{ id, nombre, unidad_medida, stock }],
  compradores: [{ id, nombre, tipo_cliente }],
  proveedores: [{ id, nombre_completo, dni }]
}
```
**El paso 1 "Comprador" del mockup debe buscar en DOS fuentes**: `compradores` (mayorista/público externos) Y `proveedores` (un proveedor también puede comprar productos, pagando con cargo a su próxima liquidación vía `DESCUENTO_PLANILLA`). El mockup original solo mostraba compradores externos tipo "Bodega San José" — agrega esta variante.

### Sin carrito atómico
`POST /ventas/ventas` registra **un producto por llamada** — el esquema no agrupa varias líneas en un solo "ticket". Para el carrito de N productos del mockup:
1. Llama el endpoint una vez por producto, en secuencia.
2. Agrupa las respuestas en el cliente (mismo comprador + timestamp cercano) para mostrarlas como "un ticket" de N productos y el total sumado.
3. Si una llamada falla a mitad del carrito, no hay rollback automático — decide tú cómo lo comunicas (ej. "2 de 3 productos registrados, reintenta el tercero") y qué offreces (reintentar solo lo que falló).

`POST /ventas/ventas`
```
body: {
  producto_id: int,
  tipo_cliente: "PROVEEDOR" | "MAYORISTA" | "PUBLICO",   // el mockup decía "Minorista": es "PUBLICO"
  cantidad: number,
  forma_pago: "CONTADO" | "DESCUENTO_PLANILLA",            // obligatorio — agrégalo al paso Resumen/Confirmar
  proveedor_id?: int,   // requerido si tipo_cliente = PROVEEDOR
  comprador_id?: int    // opcional, si el comprador es externo y ya está registrado
}
→ 201 { data: { id, producto_id, cantidad, precio_unitario, importe_total, forma_pago, estado, fecha } }
```
- El precio se calcula en servidor (precio vigente según producto + tipo de cliente) — nunca lo mandes tú, ni lo edites en el resumen antes de confirmar.
- `DESCUENTO_PLANILLA` solo es válido si `tipo_cliente = PROVEEDOR`, y el backend valida que el importe no supere el saldo estimado que el proveedor tiene disponible en su periodo de liquidación actual. Si se excede, devuelve `422`.
- Errores de stock insuficiente también son `422` con mensaje — valida en el cliente contra el `stock` que trae `GET /ventas/ventas/crear`, pero no confíes solo en eso (puede cambiar entre que cargaste la pantalla y confirmaste).

`GET /ventas/ventas/{id}`
```
data: { id, producto_id, producto_nombre, proveedor_id, comprador_id, comprador_nombre,
         tipo_cliente, cantidad, precio_unitario, importe_total, forma_pago, estado, fecha }
```

## 5. VENTAS (historial)

`GET /ventas/ventas?periodo=&desde=&hasta=&q=&tipo_cliente=&forma_pago=&estado=&page=&per_page=` (camelCase en `filas`)
```
data: {
  kpis: { ventas, totalVendido, productosVendidos, compradores },
  filas: [{ id, fecha, productoNombre, clienteNombre, clienteTipo, cantidad, importeTotal, estado }]
}
meta: {...}
```
Nota: la fila del listado **no trae `formaPago`** — si necesitas mostrarlo en la lista, pide el detalle de cada venta o dime si prefieres que lo agregue al listado (es un cambio pequeño).

## 6. MÁS

### Productos (consultar stock y precio — no hay pantalla de "Precios" separada)
`GET /ventas/productos?buscar=&estado_stock=disponible|agotado`
```
data: { kpis: { productos, stock_total, agotados }, filas: [{ id, nombre, unidad_medida, cantidad_actual, sin_stock }] }
```
`GET /ventas/productos/{id}` → `data: { id, nombre, unidad_medida, cantidad_actual, estado, movimientos: [{fecha, tipo, cantidad, origen}] }`
`GET /ventas/productos/movimientos?producto_id=&tipo=&desde=&hasta=` → `data: { kpis: {total, entradas, salidas}, filas: [{id, producto_id, producto_nombre, tipo, cantidad, fecha, observacion}] }`
`POST /ventas/productos/{id}/ingreso` (recepción de inventario que llega de Planta) → body `{ cantidad, lote_produccion_id?, observacion? }` → `201 { data: { id, cantidad_actual } }`

**Precios**: no hay endpoint expuesto para configurar precios desde móvil (es exclusivo de ADMINISTRADOR vía web). El precio vigente ya viene calculado en `POST /ventas/ventas`, no necesitas mostrarlo editable.

### Compradores
`GET /ventas/compradores?buscar=&tipo_cliente=` → `data: [{ id, nombre, documento, telefono, tipo_cliente, estado }]` (array plano)
`POST /ventas/compradores` → body `{ nombre, tipo_cliente: "MAYORISTA"|"PUBLICO", documento?, telefono? }` → `201 { data: {...} }`

### Comunicados
**No hay endpoint de comunicados para este rol** — ni siquiera el panel web se lo da a VENTAS (confirmado revisando `routes/web.php`). No lo incluyas en "Más" salvo que decidan agregarlo a propósito.

### Notificaciones
Ver `administrador.md` sección 7.

## 7. Offline — el caso más delicado de los 6 roles

El stock puede cambiar en otros dispositivos mientras uno está offline. La app debe:
- Mostrar claramente "Los precios y el stock corresponden a la última sincronización" con su hora.
- Al sincronizar una venta pendiente, si el servidor devuelve `422` por stock insuficiente (porque otro dispositivo vendió primero), **no reintentar automáticamente**: mover esa venta a una cola de "requiere revisión" para que el vendedor decida (ajustar cantidad, cancelar).
- Recordatorio del punto 8 de `acopiador.md`: tampoco hay clave de idempotencia server-side aquí. Aplica la misma mitigación (guardar el `id` de servidor apenas llega, no reintentar si ya lo tienes).
