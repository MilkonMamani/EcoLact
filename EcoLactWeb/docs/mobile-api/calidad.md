# EcoLact Móvil — CALIDAD

Contrato de API + diseño final de pantallas para el rol CALIDAD (analista). Hay una pieza nueva importante que el mockup original no contemplaba: **el analista a veces registra la entrega él mismo**, no solo el acopiador — ver sección 4.1.

## 1. Conexión y autenticación

Igual que el resto: `POST /api/v1/login` → Bearer token. Ver `administrador.md` sección 1.

**Este rol mezcla casing igual que Administrador**: los listados que reutilizan `*ListadoQuery` (`entregas`, `analisis-calidad` con `tab=historial`, `proveedores`) devuelven `filas` en **camelCase**; el resto (`dashboard`, `show`, `porEntrega`, `store`, `finalizar`, `comunicados`) en **snake_case**.

## 2. Navegación final

```
Inicio · Pendientes · Historial · Más
```
Arriba: ● Online/Offline · ↻ Sincronización · 🔔 Notificaciones · 👤 Perfil.

## 3. INICIO

`GET /calidad/dashboard?periodo=&desde=&hasta=`
```
data: {
  pendientes_analisis: number,
  kpis_periodo: { analizados, aceptados, observados, rechazados },
  pendientes_filas: [{ id, fecha, proveedorNombre, proveedorDni, zonaNombre, litros }],
  ultimos_analisis_filas: [{ id, fecha, proveedorNombre, proveedorDni, entregaId, zonaNombre, resultado, estado }],
  comunicados_recientes: [{ id, titulo, tipo, fecha_publicacion }]
}
```
Nota: `pendientes_filas` y `ultimos_analisis_filas` vienen con claves internas en camelCase (heredadas del Use Case), aunque el nombre del campo contenedor esté en snake_case.

## 4. PENDIENTES (pantalla operativa principal)

### 4.1 Cómo funciona el acopio real, y qué cambia para el analista

El acopiador **no separa la leche por proveedor**: la junta toda en un solo recipiente. Por eso el flujo real es:

- El acopiador y el analista recorren la zona juntos. En cada proveedor, el analista decide **al azar** si va a muestrear esa entrega.
- **No elegida**: esa leche va directo al acopiador de siempre — el analista no hace nada, no la ve. El Acopiador la registra vía `POST /acopiador/entregas` (sin cambios) y puede que nunca llegue a analizarse.
- **Elegida**: el analista la separa y la analiza ahí mismo, ANTES de que exista cualquier entrega en el sistema.
  - Si el resultado es **ACEPTADA u OBSERVADA**: el analista avisa verbalmente al acopiador, y es **el acopiador quien registra la entrega normalmente** (`POST /acopiador/entregas`, sin cambios) — la app de Calidad no interviene en el registro, solo en el análisis posterior de esa entrega (flujo ya descrito en la sección 4 de más abajo, sin cambios).
  - Si el resultado es **RECHAZADA**: esa leche nunca llega al acopiador (el proveedor "ni se acerca"). Aun así **queda un registro**, para que las advertencias y el historial de calidad del proveedor sigan funcionando — pero lo registra **el propio analista**, con este endpoint nuevo:

`POST /calidad/entregas`
```
body: { metodo_identificacion: "QR"|"DNI", identificador: string, litros: number, observacion?: string }
→ 201 { data: { id, proveedor_id, acopiador_id: null, zona_id, fecha_hora, litros,
                  metodo_identificacion, estado: "PENDIENTE_ANALISIS", observacion } }
```
`acopiador_id` sale `null` — nunca hubo acopiador involucrado. Úsalo **solo** para este caso (muestra que va a rechazarse); para el caso aceptado/observado, no lo uses, el Acopiador ya se encarga.

Justo después, sigue el flujo normal de análisis con el `id` que te devolvió esto: `POST /calidad/analisis-calidad` (más abajo, "Flujo de análisis") con ese `entrega_id`, y `PATCH /calidad/analisis-calidad/{id}/finalizar` para confirmar. Al confirmar como RECHAZADA, el backend **ya genera solo** la advertencia al proveedor (sanción + notificación) — no hace falta ningún paso adicional.

Esos litros **nunca cuentan como leche disponible para producción** (se excluyen automáticamente, ver `encargado-planta.md`), aunque queden registrados.

`GET /calidad/analisis-calidad?tab=pendientes&q=&zona_id=&fecha=&desde=&hasta=`
```
data: {
  tab: "pendientes",
  kpis: { litrosRegistrados, entregas, pendientesAnalisis, analizadas },
  zonas: [{id, nombre}],
  filas: [{ id, fecha, proveedorNombre, proveedorDni, zonaNombre, acopiadorNombre,
             litros, metodoIdentificacion, estado, resultado }]
}
meta: { current_page, per_page, total, last_page }
```
Esto lista **entregas** con `estado=PENDIENTE_ANALISIS` (no análisis ya creados) — filtra en el cliente por lo que necesites mostrar como "análisis iniciados" usando el detalle de cada entrega (ver abajo).

`GET /calidad/entregas/{entrega}/analisis-calidad` → detalle antes de analizar:
```
data: {
  analisis: { id, entrega_id, imagen_path, estado, resultado, fecha_analisis,
               confirmado_por_user_id, observacion } | null,
  parametros: [{ id, codigo, nombre, unidad }]   // solo parámetros activos
}
```
Si `analisis` es `null`, la entrega todavía no tiene ningún borrador — es el caso "pendiente" puro. Si `analisis` existe y `estado != CONFIRMADO`, es el caso "análisis iniciado, continuar".

### Flujo de análisis — Evidencia → Parámetros → Resultado → Confirmar

**Paso 1-3 (Evidencia + Parámetros + Resultado) = un solo `POST`.** El backend no tiene pasos separados: guardas/actualizas todo junto, y puedes llamarlo varias veces mientras el análisis siga en borrador (así se implementa "continuar analizando").

`POST /calidad/analisis-calidad`
```
multipart/form-data:
  entrega_id: int (requerido)
  valores[CODIGO]: number   // ej. valores[DENSIDAD]=1.029, valores[PH]=6.72 — indexado por CÓDIGO del parámetro, no por id
  imagen?: file (jpg/jpeg/png, máx 5MB)
  observacion?: string
→ 201 { data: { id, entrega_id, imagen_path, estado, resultado, fecha_analisis,
                  confirmado_por_user_id, observacion } }
```
`resultado` sigue siendo `null` hasta que se confirma — el backend **no** calcula ACEPTADA/OBSERVADA/RECHAZADA en este paso, eso ocurre solo al finalizar (correcto: el mockup ya pedía que el analista revise antes de confirmar, esto lo refuerza el propio backend).

Para "Sin regla configurada": si mandas un valor cuyo parámetro no tiene una `regla_calidad` vigente, el backend lo guarda igual (no lo rechaza), pero no lo usará para calcular el resultado. Muéstralo tal como lo describe el mockup, sin inventar un "✓ Aceptado".

### Paso 4 — Confirmar

`PATCH /calidad/analisis-calidad/{analisis}/finalizar` (sin body)
```
→ { data: { id, entrega_id, imagen_path, estado: "CONFIRMADO", resultado: "ACEPTADA"|"OBSERVADA"|"RECHAZADA",
              fecha_analisis, confirmado_por_user_id, observacion } }
```
Después de esto, `POST /calidad/analisis-calidad` sobre la misma entrega devuelve error (`422`) — es inmutable, tal como pide el mockup.

## 5. HISTORIAL

`GET /calidad/analisis-calidad?tab=historial&q=&zona_id=&resultado=&estado=&fecha=&desde=&hasta=`
```
data: {
  tab: "historial",
  kpis: { total, aceptadas, observadas, rechazadas },
  zonas: [{id, nombre}],
  filas: [{ id, fecha, proveedorNombre, proveedorDni, entregaId, zonaNombre, resultado, estado, confirmadoPor }]
}
meta: {...}
```
`GET /calidad/analisis-calidad/{id}` (detalle, con trazabilidad completa)
```
data: {
  id, entrega_id, imagen_path, estado, resultado, fecha_analisis,
  entrega: { id, litros, fecha_hora, proveedor_nombre, zona_nombre } | null,
  detalles: [{ parametro_id, parametro_codigo, parametro_nombre, unidad, valor, origen, confianza_ocr, cumple }],
  observacion
}
```
`origen` de cada valor: `"MANUAL" | "OCR"` — pero el backend actual **no ejecuta OCR**: todo `store` guarda `origen: "MANUAL"` siempre. Si tu diseño de pantalla asume lectura automática de la foto, aclara con el equipo si el OCR se implementará después o si el flujo real es "ingreso manual siempre" (con la foto solo como evidencia adjunta, sin lectura).

## 6. MÁS

- `GET /calidad/proveedores?q=&estado=&zona_id=&seguimiento=` (camelCase en `filas`, mismo shape que `administrador.md` 6.2)
- `GET /calidad/proveedores/{id}` (snake_case) → `data: { proveedor: {id, dni, nombre_completo, zona_id, estado, advertencias_count}, entregas: [{id, fecha_hora, litros, estado}], analisis: [{id, entrega_id, resultado, fecha_analisis}] }`
- `GET /calidad/comunicados` → `data: [{ id, titulo, contenido, tipo, alcance, fecha_publicacion, fecha_evento, estado }]` (array plano)
- `GET /calidad/comunicados/{id}` → `data: { ...mismo shape... }`
- `POST /calidad/comunicados` → **CALIDAD sí puede crear comunicados** (a diferencia de Acopiador/Proveedor), mismo body que en `administrador.md` 6.1. Útil para "proponer capacitaciones".

## 7. Notificaciones
Ver `administrador.md` sección 7.

## 8. Offline — caso importante ya conocido

El analista solo puede trabajar offline sobre entregas **ya sincronizadas previamente** al dispositivo. Si un acopiador registra una entrega mientras ambos están sin conexión, el analista no puede verla hasta que el acopiador sincronice y el analista vuelva a descargar. No hay sincronización directa entre dispositivos — es una limitación de diseño aceptada, no de esta API en particular.
