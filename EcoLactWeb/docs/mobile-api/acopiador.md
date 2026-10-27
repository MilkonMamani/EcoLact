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

## 10. Offline-first del acopiador (actualización)

- `POST /acopiador/entregas`:
  - Cabecera `Idempotency-Key` (opcional, máx. 64): se guarda como `entregas.token_idempotencia`. Reintentar con la misma clave devuelve la MISMA entrega (resuelve la limitación de la sección 4). Una clave usada por otro usuario → `422`.
  - `fecha_hora` = hora REAL del acopio en el teléfono (también si se sincroniza horas después). Límites (`FechaEventoMovil`): no futura (tolerancia 10 min) y no más de 7 días atrás → `422`. Se normaliza a la zona horaria de la app. `fecha_registro` en la respuesta = llegada al servidor.
  - Errores de negocio (proveedor inactivo, de otra zona, QR/DNI inexistente) → `422` (antes `500`), para que la cola offline los marque "Error de sincronización" sin reintentar.
- Forma de cada entrega (listado, detalle, dashboard, seguimiento): agrega `proveedor_nombre`, `proveedor_dni`, `zona_nombre`, `fecha_registro`, `muestreo_estado` y `control_calidad` (`SIN_MUESTREO`, `SELECCIONADA`, `EN_ANALISIS`, `ACEPTADA`, `OBSERVADA`, `RECHAZADA`, `ANULADA`; ver `ControlCalidadEntrega`). Con el muestreo, una entrega sin muestra es "Sin muestreo", no "Pendiente de análisis".
- `GET /acopiador/entregas?desde=Y-m-d`: acota el historial que guarda el teléfono.
- `GET /acopiador/proveedores`: cada fila trae `qr_token` y `zona_nombre` (identificación por QR sin conexión; solo proveedores de su zona).
- `GET /acopiador/dashboard`: agrega `acopiador` `{ nombres, apellidos, dni, telefono, estado, zona_id, zona_nombre }` (cabecera y "Mi perfil").
- `GET /acopiador/comunicados`: cada fila trae `leido`; abrir el detalle lo marca leído (igual que proveedor).
- `POST …/paradas/{id}/no-entrego`: idempotente (repetirlo no cambia nada); sobre una parada ya `RECOLECTADO` → `422`. `iniciar`/`finalizar` responden `422` en errores de negocio. `fecha_hora_ordeño` usa los mismos límites que `fecha_hora`.
- `GET /acopiador/mi-asistencia` (solo lectura): `{ registrado, qr_token, controla_asistencia, hora_cierre, abierta_hoy, hoy, jornadas[] }`. La asistencia la registra el ADMINISTRADOR escaneando ese QR (`POST /admin/asistencia/qr`); el trabajador no marca su propia asistencia.

### 10.1 Hora del acopio vs. hora de sincronización

- Request (móvil): `fecha_hora_evento` — ISO 8601 **con desfase obligatorio** (`2026-10-27T09:35:12-05:00`), capturada UNA vez al pulsar "Guardar entrega" y guardada en el payload de `sync_queue`; cada reintento reenvía la misma junto con el mismo `Idempotency-Key`. `fecha_hora` se sigue aceptando (versiones anteriores). Sin ninguna → hora del servidor (Web, clientes antiguos).
- Mismo contrato en `POST …/paradas/{id}/acopio` (`fecha_hora_evento`; `fecha_hora_ordeño` por compatibilidad).
- BD: `entregas.fecha_hora` = hora real del acopio (guardada en UTC, convenio del proyecto: 09:35 Perú = 14:35 UTC). `entregas.created_at` = llegada al servidor (`fecha_registro` en la respuesta). No hay columnas nuevas.
- Validación centralizada en `config/acopio.php` (`ACOPIO_MOVIL_MAX_DESFASE_RELOJ_MINUTOS`=10, `ACOPIO_MOVIL_MAX_DIAS_ATRASO`=7): más futura → 422 "no puede estar en el futuro"; más antigua → 422; sin desfase → 422.
- La regla de 10 minutos para corregir litros (`Entrega::MINUTOS_EDICION_LITROS`) cuenta desde `fecha_hora` (el acopio), así que sincronizar tarde no abre una nueva ventana. El móvil aplica la misma regla a entregas aún no enviadas.
