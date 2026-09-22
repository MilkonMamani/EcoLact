# EcoLact Móvil — PROVEEDOR

Contrato de API + diseño final de pantallas para el rol PROVEEDOR. Es el rol más simple: **todo es de solo lectura** (ni siquiera el panel web permite que el proveedor edite sus propios datos). Sin cambios de alcance respecto al mockup original (nunca tuvo Auditoría ni stock-bajo en su diseño).

## 1. Conexión y autenticación

Igual que el resto: `POST /api/v1/login` → Bearer token. Ver `administrador.md` sección 1. **Todos los endpoints de este rol responden en snake_case.**

## 2. Navegación final

```
Inicio · Entregas · Pagos · Más
```
Arriba: 🔔 Notificaciones · ● Online/Offline · 👤 Perfil.

## 3. INICIO

`GET /proveedor/dashboard?periodo=&desde=&hasta=`
```
data: {
  kpis: { entregas, litros },
  ultimo_analisis_resultado: "ACEPTADA"|"OBSERVADA"|"RECHAZADA"|null,
  ultima_liquidacion: { id, total_pagar, estado } | null,
  ultima_liquidacion_periodo: { fecha_inicio, fecha_fin } | null,
  por_cobrar: number,
  ultimas_entregas: [{ id, fecha_hora, litros, estado }],
  compras_recientes: [{ id, producto_id, cantidad, importe_total, forma_pago, fecha }],
  comunicados_recientes: [{ id, titulo, tipo, fecha_publicacion }]
}
```

## 4. ENTREGAS

`GET /proveedor/entregas?estado=`
```
data: {
  kpis: { entregas, litros, analizadas, pendientes },
  filas: [{ id, fecha_hora, litros, zona_id, estado, resultado }]
}
```
`GET /proveedor/entregas/{id}`
```
data: {
  entrega: { id, fecha_hora, litros, zona_id, estado },
  analisis: { id, resultado, fecha_analisis } | null
}
```
Siempre acotado al proveedor autenticado (resuelto por el backend desde el token, nunca por un `proveedor_id` que mandes tú).

## 5. PAGOS (agrupa Liquidaciones + Pagos, como pide el mockup)

`GET /proveedor/liquidaciones?estado=`
```
data: {
  filas: [{ id, periodo_id, total_litros_entregados, total_litros_pagables,
             precio_litro_aplicado, subtotal, total_ajustes, total_pagar, estado,
             fecha_calculo, tuvo_incidencia, saldo_pendiente }],
  resumen: { ultima_liquidacion_id, por_cobrar, pagado, total_liquidaciones } | null
}
```
`GET /proveedor/liquidaciones/{id}`
```
data: {
  liquidacion: { ...mismos campos que arriba, sin saldo_pendiente... },
  entregas: [...],           // detalle de litros incluidos en el periodo
  incidencia: ...,
  compras: [{ id, producto_id, cantidad, importe_total, forma_pago, fecha }],
  ajustes: [{ id, tipo, descripcion, monto }],
  pagos: [{ id, fecha_pago, monto, estado, observacion }],
  saldo_pendiente: number
}
```
**No hay `forma_pago` en `pagos`**: el pago al proveedor siempre es en efectivo (sobre sellado), es una regla de negocio fija — no lo pidas en el diseño.

`GET /proveedor/pagos` → `data: [{ id, liquidacion_id, fecha_pago, monto, estado, observacion }]` (array plano, sin `kpis`)
`GET /proveedor/pagos/{id}` → `data: { ...mismo shape... }`

## 6. MÁS

### Mi calidad
`GET /proveedor/analisis?resultado=&estado=`
```
data: {
  kpis: { total, aceptados, observados, rechazados },
  filas: [{ id, entrega_id, resultado, estado, fecha_analisis }]
}
```
`GET /proveedor/analisis/{id}`
```
data: {
  id, entrega_id, resultado, estado, fecha_analisis, observacion,
  entrega: { litros, zona_nombre } | null,
  detalles: [{ parametro_codigo, parametro_nombre, unidad, valor, cumple }],
  sancion: { id, tipo, descripcion } | null
}
```

### Mis compras
`GET /proveedor/compras?producto_id=`
```
data: {
  kpis: { compras, unidades_adquiridas, total_comprado },
  filas: [{ id, producto_id, producto_nombre, cantidad, precio_unitario, importe_total,
             forma_pago, fecha }]
}
```
`GET /proveedor/compras/{id}`
```
data: { id, producto_id, producto_nombre, cantidad, precio_unitario, importe_total,
         forma_pago, fecha, liquidacion_relacionada_id }
```
Nota: `forma_pago` aquí SÍ puede ser `DESCUENTO_PLANILLA` (el proveedor comprando productos con cargo a su próxima liquidación) — es distinto del pago que él recibe, que siempre es efectivo.

### Mi QR
`GET /proveedor/qr` → `data: { qr_token, nombre_completo, dni, zona_id, estado }`
El backend entrega el valor de texto; la app es responsable de renderizarlo como código QR (no hay endpoint que devuelva una imagen).

### Comunicados
`GET /proveedor/comunicados`
```
data: {
  kpis: { total, no_leidos },
  filas: [{ id, titulo, contenido, tipo, fecha_publicacion, fecha_evento, leido }]
}
```
`GET /proveedor/comunicados/{id}` → `data: { id, titulo, contenido, tipo, fecha_publicacion, fecha_evento }` — **abrir el detalle marca el comunicado como leído automáticamente** (efecto secundario del `GET`, no hace falta un endpoint aparte).

### Mi perfil
`GET /proveedor/perfil` → `data: { id, dni, nombres, apellidos, nombre_completo, telefono, zona_id, estado, fecha_registro }`
**No hay `PATCH`**: editar teléfono (u otro dato de contacto) desde la app no está soportado todavía, ni siquiera en el panel web. Si lo necesitas para el mockup, es un endpoint nuevo a pedir aparte (el campo `telefono` ya existe en la tabla, así que sería barato agregarlo).

## 7. Notificaciones
`GET /notificaciones`, `PATCH /notificaciones/{id}/leida` → ver `administrador.md` sección 7.

## 8. Offline
Este rol es 100% lectura, así que offline es simplemente "mostrar la última copia sincronizada con su fecha/hora", sin cola de sincronización de escritura que manejar.
