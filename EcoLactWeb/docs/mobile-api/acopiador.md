# EcoLact Móvil — ACOPIADOR

Contrato de API + diseño final de pantallas para el rol ACOPIADOR. Sin cambios respecto al mockup original en cuanto a alcance (este rol nunca tuvo Auditoría ni stock-bajo-de-producto en su diseño), pero sí hay detalles técnicos del contrato que el mockup no especificaba.

## 1. Conexión y autenticación

Igual que en el resto de roles: `POST /api/v1/login` → Bearer token. Ver `administrador.md` sección 1 para la convención de sobre `{data, meta}`, errores y paginación.

**Todos los endpoints de este rol responden en snake_case** (a diferencia de algunos de Administrador que heredan camelCase de las vistas web). No necesitas el aviso de mezcla de casing aquí.

## 2. Navegación final

```
Inicio · + Registrar · Entregas · Más
```
Arriba: ● Online/Offline · ↻ Sincronización · 🔔 Notificaciones · 👤 Perfil.

## 3. INICIO

`GET /acopiador/dashboard?periodo=&desde=&hasta=`

```
data: {
  kpis: { entregas, litros, proveedoresAtendidos, pendientesAnalisis },
  ultimas_entregas: [{ id, proveedor_id, acopiador_id, zona_id, registrado_por_user_id,
                        fecha_hora, litros, metodo_identificacion, estado, observacion }],
  resultados_por_entrega: { [entregaId]: "ACEPTADA"|"OBSERVADA"|"RECHAZADA"|null },
  proveedores_atendidos: [{ proveedorId, nombre, entregas }],
  comunicados_recientes: [{ id, titulo, tipo, alcance, fecha_publicacion, fecha_evento, estado }]
}
```
Nota: la clave interna `kpis` viene en camelCase (así la produce el Use Case de dashboard), el resto del payload en snake_case. Verifícalo con una llamada real antes de fijar el modelo.

## 4. REGISTRAR ENTREGA

Un solo endpoint cubre todo el flujo de 3 pasos (Identificar → Cantidad → Confirmar): la identificación (QR/DNI) la resuelve el backend en la misma llamada, no hay un paso previo de "buscar proveedor" por API — si quieres mostrar el nombre del proveedor antes de confirmar, resuélvelo tú mismo contra `GET /acopiador/proveedores` filtrando por DNI, o guía al usuario a escanear primero y confirmar directo.

`POST /acopiador/entregas`
```
body: {
  metodo_identificacion: "QR" | "DNI",
  identificador: string,   // DNI: exactamente 8 dígitos. QR: el qr_token escaneado (máx 36 chars)
  litros: number,           // > 0
  fecha_hora?: string,      // ISO 8601, opcional (por defecto "ahora" en servidor)
  observacion?: string
}
→ 201 { data: { id, proveedor_id, acopiador_id, zona_id, registrado_por_user_id,
                 fecha_hora, litros, metodo_identificacion, estado, observacion } }
```
- El acopiador que registra siempre es el usuario autenticado — **no** se envía en el body.
- Si el proveedor no existe o no pertenece a la zona del acopiador, el backend devuelve `422`/`403` con mensaje (no hay un endpoint separado de "validar proveedor").
- **Idempotencia**: el backend NO tiene una clave de deduplicación (ni UUID de cliente ni constraint único por proveedor+fecha). Si reintentas un `POST` porque se perdió la respuesta, puede crear una entrega duplicada. Mitigación recomendada en el cliente: guarda el `id` devuelto en SQLite en cuanto llegue la respuesta y nunca reintentes un registro que ya tiene `id` asignado; para el caso "la petición sí llegó pero la respuesta se perdió", no hay garantía todavía — es una limitación conocida, pendiente de decidir si se resuelve con una columna de idempotencia en el backend.

## 5. ENTREGAS

`GET /acopiador/entregas?proveedor_id=&estado=`
```
data: {
  kpis: { entregas, litros, proveedores, pendientes },
  filas: [{ id, proveedor_id, acopiador_id, zona_id, registrado_por_user_id, fecha_hora,
             litros, metodo_identificacion, estado, observacion, resultado }]
}
```
`estado` de la entrega: `PENDIENTE_ANALISIS | ANALIZADA` (aprox. — confirma los valores exactos con el enum del backend si necesitas mostrarlos todos). `resultado` (solo si ya se analizó): `ACEPTADA | OBSERVADA | RECHAZADA | null`.

`GET /acopiador/entregas/{id}`
```
data: {
  entrega: { id, proveedor_id, acopiador_id, zona_id, registrado_por_user_id, fecha_hora,
             litros, metodo_identificacion, estado, observacion },
  analisis: "ACEPTADA"|"OBSERVADA"|"RECHAZADA"|null   // solo el resultado, no el detalle técnico
}
```
**No hay `PATCH`/`DELETE` de entregas para este rol** (ni siquiera en el backend web) — coincide con la regla del mockup: "editar" una entrega ya enviada solo existe como concepto local en SQLite, antes del primer sync exitoso. Una vez que el servidor la aceptó, ya no se edita desde la app.

## 6. SEGUIMIENTO DE CALIDAD (solo lectura)

`GET /acopiador/seguimiento-calidad?proveedor_id=&estado_analisis=&resultado=`
```
data: {
  kpis: { pendientes, analizadas, aceptadas, con_observaciones },
  filas: [{ id, proveedor_id, fecha_hora, litros, estado, resultado }]
}
```
`GET /acopiador/seguimiento-calidad/{entrega}`
```
data: {
  entrega_id, estado,
  analisis: { id, estado, resultado, fecha_analisis, observacion } | null
}
```

## 7. MÁS

- `GET /acopiador/proveedores?estado=&buscar=`
  ```
  data: {
    kpis: { proveedores, activos, inactivos },
    filas: [{ id, dni, nombres, apellidos, nombre_completo, telefono, zona_id, estado,
               entregas, ultima_entrega }]
  }
  ```
  Ya viene **acotado a la zona del acopiador autenticado** — no hace falta (ni se puede) pedir otras zonas.
- `GET /acopiador/proveedores/{id}`
  ```
  data: {
    proveedor: { id, dni, nombres, apellidos, nombre_completo, telefono, zona_id, estado },
    resumen: { entregas_totales, litros_entregados, ultima_entrega, pendientes_analisis },
    ultimo_analisis_resultado: "ACEPTADA"|"OBSERVADA"|"RECHAZADA"|null
  }
  ```
- `GET /acopiador/comunicados` → `data: [{ id, titulo, contenido, tipo, alcance, fecha_publicacion, fecha_evento, estado }]` (sin `kpis`, es un array plano)
- `GET /acopiador/comunicados/{id}` → `data: { ...mismo shape... }`
- `GET /notificaciones`, `PATCH /notificaciones/{id}/leida` → ver `administrador.md` sección 7.

## 8. Offline / sincronización — recordatorio de diseño

Solo `POST /acopiador/entregas` es una operación de escritura para este rol. El resto de pantallas (Inicio, Entregas, Seguimiento, Más) son lectura y pueden servirse desde caché local cuando no hay conexión, mostrando la hora de la última sincronización. La cola de sincronización solo necesita manejar un tipo de operación (`entrega nueva`), lo cual simplifica bastante el diseño frente a Ventas o Calidad.

## 9. Recorridos de acopio (Zonas → Rutas → Recorridos)

Nuevo módulo: el ADMINISTRADOR configura, por Zona, una o varias **Rutas** (lista ordenada de proveedores) desde el panel web, y asigna un acopiador a esa ruta abriendo un **Recorrido** (la ejecución concreta de la ruta en una fecha operativa). Este rol **nunca crea sus propios recorridos**: solo consulta y opera los que ya le fueron asignados. Todos los endpoints siguen el mismo contrato Bearer token + `{data: ...}` que el resto de este rol.

- `GET /acopiador/recorridos` → `data: [{ id, ruta_id, fecha_operativa, estado, iniciado_en, finalizado_en }]`. `estado`: `PENDIENTE | EN_PROGRESO | FINALIZADO | CANCELADO`.
- `GET /acopiador/recorridos/{id}` — detalle completo para la pantalla de mapa + lista:
  ```
  data: {
    id, ruta_id, ruta_nombre, zona_nombre, estado, fecha_operativa,
    progreso: { recolectados, pendientes, no_entrego, litros_validos },
    paradas: [{ id, proveedor_id, proveedor_nombre, proveedor_dni, orden_visita,
                latitud, longitud, referencia_ubicacion, estado, observacion, litros_registrados }]
  }
  ```
  `latitud`/`longitud` son `number|null` (nunca `0,0` como sustituto de "sin ubicación"). `estado` de una parada: `PENDIENTE | RECOLECTADO | NO_ENTREGO`.
- `PATCH /acopiador/recorridos/{id}/iniciar` y `PATCH /acopiador/recorridos/{id}/finalizar` → devuelven el mismo shape que el `show`, ya actualizado.
- `POST /acopiador/recorridos/{id}/identificar` — primer paso del flujo (identificar SIN registrar nada todavía):
  ```
  body: { metodo: "DNI"|"QR", identificador: string }
  → 200 { data: { parada_id } }  |  422 { message: "..." } si el proveedor no existe o no pertenece a este recorrido
  ```
  El QR soportado es únicamente el `qr_token` interno de EcoLact (mismo valor que ya usa `POST /acopiador/entregas`); no hay lectura de documentos físicos.
- `POST /acopiador/recorridos/{id}/paradas/{paradaId}/acopio` — registra el acopio de esa parada:
  ```
  body: { litros: number (>0), token_idempotencia: string (obligatorio), fecha_hora_ordeño?: string, observacion?: string }
  → 201 { data: { entrega_id, litros, parada: { id, estado } } }
  ```
  **Esto sí resuelve la limitación de idempotencia documentada en la sección 4**: `token_idempotencia` es obligatorio aquí y está respaldado por un índice único en `entregas.token_idempotencia` — reintentar con el mismo valor (por timeout, doble tap, etc.) devuelve la MISMA entrega en vez de duplicarla. Genera un UUID nuevo por cada intento de registro real (no por cada reintento del mismo intento). No marca la parada como recolectada si la petición falla: el estado permanece `PENDIENTE` y puedes reintentar con el mismo token.
- `POST /acopiador/recorridos/{id}/paradas/{paradaId}/no-entrego`:
  ```
  body: { motivo: string (obligatorio) }
  → 200 { data: { parada: { id, estado, observacion } } }
  ```
  No crea ninguna entrega.
- Autorización: cada endpoint verifica que el recorrido pertenezca al acopiador autenticado (nunca al ID de la URL por sí solo); un recorrido ajeno responde `403`.
