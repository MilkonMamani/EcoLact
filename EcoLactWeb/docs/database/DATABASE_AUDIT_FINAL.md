# Auditoría final de base de datos — EcoLact

Fecha: 2026-10-27. Entorno: Laravel 13.31.0, MySQL 8.4.3 (`ecolactv1`), tests en SQLite en memoria.

Autoridad usada, en este orden: migrations actuales → código actual (Domain, Application, Infrastructure, Http, rutas) → tests actuales → esquema real leído de `information_schema`. `MIGRATION_VALIDATION.md` (2026-09-09, "29 tablas") ya no describía el esquema y no se usó como referencia.

## 1. Resumen

| | Antes | Después de `2026_10_31_100000` |
| --- | ---: | ---: |
| Migrations | 109 (todas Ran) | 110 |
| Tablas | 68 (59 funcionales + 9 técnicas de Laravel/Sanctum) | 68 |
| Foreign keys | 111 (91 RESTRICT, 18 SET NULL, 2 CASCADE en DELETE) | 112 |
| Índices UNIQUE (sin PK) | 49 | 49 |
| Índices no únicos | 112 | 114 |
| CHECK constraints | 0 | 0 |

No se recreó, vació ni borró ninguna tabla de negocio. No se usó `migrate:fresh` ni `migrate:refresh`.

## 2. Lo que el diagnóstico inicial suponía y lo que existe realmente

| Supuesto | Realidad |
| --- | --- |
| Falta `muestreos_calidad` | Existe (`2026_10_29_100000`, Ran) con `UNIQUE(entrega_id)`, FK RESTRICT a `entregas` y `users`, modelo, repositorio, casos de uso, API, web y tests. No se creó nada nuevo. |
| Ventas, compras y producción sin idempotencia en el servidor | Existe: middleware `idempotente` (`App\Http\Middleware\IdempotenciaApi`) + tabla `api_idempotencia` con `UNIQUE(user_id, clave)`. Protege `POST ventas/ventas`, `POST planta/produccion`, `POST planta/produccion/{lote}/finalizar` y `POST planta/compras-insumo`. La app manda `Idempotency-Key` = id local. **No** se agregaron columnas `token_idempotencia` a esas tablas: sería un segundo mecanismo en paralelo para lo mismo. |
| `producto_precios.cantidad_minima` posiblemente ausente | Existe (`2026_10_28_100000`, Ran). El dominio (`ProductoPrecio`) rechaza un mínimo en filas que no sean MAYORISTA o que sea ≤ 0. |
| Acopio | `entregas.token_idempotencia` varchar(64) con `UNIQUE`. `fecha_hora` = hora real del acopio (`fecha_hora_evento` del teléfono); `created_at` = llegada al servidor. La regla de 10 minutos se calcula desde `fecha_hora` (`Entrega::MINUTOS_EDICION_LITROS`). |

## 3. Decisiones por área

### Muestreo de calidad
Entrega → muestreo opcional (`muestreos_calidad`) → análisis (`analisis_calidad`, `UNIQUE(entrega_id)`). Solo se pueden analizar entregas seleccionadas (`RegistrarAnalisisCalidad` exige una muestra pendiente). Estados reales: `EstadoMuestreo` SELECCIONADA → EN_ANALISIS → ANALIZADA; la entrega usa `EstadoEntrega` PENDIENTE_ANALISIS / ANALIZADA / ANULADA. Una entrega sin muestra no tiene fila en `muestreos_calidad` (no hace falta guardar un estado "sin muestreo"). Una sola selección por entrega: si el mismo analista reintenta, recibe la misma muestra; si otro lo intenta, conflicto. La carrera entre dos selecciones simultáneas la resuelve el índice único.

### Idempotencia (offline-first)
- **Acopio**: token en la propia entrega (`UNIQUE`). Un reintento devuelve la entrega existente y no vuelve a ingresar leche. Si dos copias llegan exactamente a la vez, la segunda choca con el índice (500) y la app la reintenta; nunca se duplica.
- **Ventas, compras, producción**: `api_idempotencia`. Cambio de esta auditoría: la clave se reserva **dentro de la misma transacción** que la operación. Antes se reservaba fuera, y una reserva sin respuesta se daba por huérfana a los 120 s. Una operación que tardara más podía ejecutarse dos veces. Ahora un reintento concurrente espera el bloqueo del índice único y recibe la respuesta guardada. Además, la clave queda ligada al **método + ruta concreta**: antes se ligaba al nombre de la ruta, y la clave de "finalizar lote 5" podía devolver esa respuesta al finalizar el lote 6.
- No se compara el cuerpo de la petición: la app reconstruye la compra en cada reintento (con o sin la ruta de la boleta ya subida) y una huella del cuerpo rechazaría reintentos legítimos.
- Finalizar un lote ya FINALIZADO sin la misma clave (otro dispositivo, la web) se rechaza con 422 por el dominio; el producto no ingresa dos veces.

### Stock
- **Insumos**: `insumos.stock_actual` es un saldo cacheado. La única escritura es `RegistrarMovimientoInsumo`, que bloquea la fila (`FOR UPDATE`), rechaza el saldo negativo y guarda el `insumo_movimientos` en la misma transacción. `ActualizarInsumo` nunca toca el stock. La única escritura directa es el comando de mantenimiento `LimpiadorDatosProduccion` (reconstrucción controlada).
- **Productos**: sin tabla de saldo (`producto_stock` se eliminó a propósito). El saldo es la suma con signo de `producto_movimientos`. Venta y ajuste bloquean la fila del producto antes de validar.
- **Leche**: insumo del sistema `codigo = 'LECHE'`. No admite presentaciones (`PresentacionInsumo::para`), no se compra (`CalcularCompraInsumos`), un ajuste solo puede registrar pérdida. Sube por acopio y baja por producción.
- **Producción**: `IniciarProduccion` recalcula la receta, bloquea insumos, crea lote + detalles con receta/versión y guarda la fotografía de consumo en `lote_produccion_insumos` (`UNIQUE(lote, insumo)`). `FinalizarProduccion` ingresa solo la **cantidad obtenida real**, guarda la merma y la observación de cierre.

### Caja
`movimientos_caja` no guarda saldo: saldo = Σ ENTRADA − Σ SALIDA. `UNIQUE(referencia_tipo, referencia_id)` impide duplicar el dinero de una operación. Entradas: venta al CONTADO. Salidas: pago a proveedor, pago a trabajador, compra con total. Una venta DESCUENTO_PLANILLA no genera caja: se descuenta en el pago semanal. Cada movimiento se registra en la misma transacción que su operación (`CajaTest`).

### Precios
`producto_precios` versionado con `fecha_inicio` / `fecha_fin` / `estado`. `ConfigurarPreciosProducto` cierra la versión anterior al registrar otra. La venta guarda la fotografía (`tipo_cliente`, `precio_unitario`, `importe_total`); cambiar un precio no recalcula ventas pasadas.

### Ventas — integridad del cliente
`RegistrarVentaProducto` exige: público → sin proveedor ni comprador; comprador → solo `comprador_id`; proveedor → solo `proveedor_id`, único caso que admite DESCUENTO_PLANILLA. No se agregaron CHECK: el proyecto no usa ninguno y SQLite los trataría distinto; la regla vive en Application, con tests.

### Presentaciones de insumo
**No** se agregó `UNIQUE(insumo_id, nombre)`: el mismo nombre puede tener contenido distinto ("Saco" de 25 kg y "Saco" de 50 kg) y una presentación inactiva puede coexistir con su reemplazo. `GuardarPresentacionInsumo` rechaza el duplicado real: mismo nombre normalizado (sin tildes ni mayúsculas) + mismo contenido + ambas activas. Un índice no puede expresar esa regla.

### Asistencia
`trabajadores`: `UNIQUE(user_id)`, `UNIQUE(qr_token)` (un QR estable por trabajador; el del proveedor está en `proveedores.qr_token`, otro contexto). `jornadas_personal`: `UNIQUE(trabajador_id, fecha)`, FK RESTRICT a `trabajadores`; las migrations de `user_id` terminaron en `trabajador_id` (`2026_09_29_090004`). La regla "sin jornada PRESENTE: consultar sí, operar no" la aplica el servidor (middleware `requiere.asistencia`) en todas las escrituras de Acopiador, Calidad, Planta y Ventas, web y API. Corrección de esta auditoría: faltaba en `POST calidad/comunicados` de la web (la API ya lo tenía).

### Foreign keys
Históricos con `ON DELETE RESTRICT` (ventas, compras, pagos, caja, producción, entregas, análisis). `SET NULL` solo en enlaces opcionales (quién confirmó, presentación de compra, movimiento enlazado). `CASCADE` solo donde el hijo no existe sin su cabecera: `receta_detalles → recetas` (una receta usada en producción ya no se puede borrar por `produccion_detalles` RESTRICT), `obligacion_pago_detalles → obligaciones_pago` (una obligación pagada queda protegida por `pagos` RESTRICT) y, desde esta auditoría, `api_idempotencia → users` (tabla técnica).

Columnas `*_id` sin FK, a propósito: `movimientos_caja.referencia_id`, `insumo_movimientos.origen_id`, `notificaciones.referencia_id`, `auditorias.registro_id` (polimórficas), `recetas.producto_activo_id` (marcador para "una receta activa por producto") y las técnicas `sessions.user_id` / `personal_access_tokens.tokenable_id`.

### Estados
`PENDIENTE` (obligaciones de pago), `PENDIENTE_ANALISIS` (entrega) y `PENDIENTE_SYNC` / `PENDIENTE_OCR` (solo en SQLite del móvil) significan cosas distintas; no hay variantes duplicadas que unificar.

## 4. Cambios de esta auditoría

**Migration nueva** `2026_10_31_100000_reforzar_integridad_para_datos_reales.php`:
- FK `fk_api_idempotencia_user_id` (`api_idempotencia.user_id → users.id`, CASCADE). Antes borra solo filas técnicas sin valor: reservas sin respuesta del middleware anterior y claves de usuarios inexistentes. Ninguna corresponde a una operación registrada.
- Índice `idx_ventas_producto_fecha(fecha)`: dashboard por rango e historiales.
- Índice `idx_lotes_produccion_estado_inicio(estado, fecha_hora_inicio)`: listados de lotes.

**Código**:
- `IdempotenciaApi`: reserva transaccional + clave ligada a método y ruta concreta.
- API de planta: `POST produccion` y `POST produccion/{lote}/finalizar` ahora usan `fecha_hora_evento`; antes la ignoraban y guardaban la hora de sincronización. `finalizar` además guarda la `observacion`, que antes descartaba. `POST compras-insumo` sin fecha de boleta usa el día del evento.
- `POST planta/insumos/{id}/movimiento` (web y API): calculaba el stock objetivo con una lectura sin bloqueo y luego ajustaba a ese valor absoluto. Un consumo que llegara entre medio quedaba pisado (+10 manual con 50 consumidos en medio terminaba en +60). Ahora usa `RegistrarAjusteInsumo::porDiferencia`, que aplica la cantidad sobre el saldo leído con la fila bloqueada. La API además devuelve 422 (antes 500) si se intenta sumar leche.
- `FinalizarProduccion`: si el cierre llega con una hora anterior al inicio (reloj de otro teléfono), cierra a la hora de inicio.
- `RegistrarVentaProducto`: mensaje "El precio cambió" (estaba mal codificado).
- `routes/web.php`: `requiere.asistencia` en `POST calidad/comunicados`.

**Tests**:
- `tests/Feature/Database/EsquemaDatosRealesTest.php` (50 casos): tablas, columnas, UNIQUE clave, FK y rechazo de huérfanos. Verifica que el error sea de clave foránea y no de otra columna.
- `PlantaMovilApiTest`: reintento de inicio (1 lote, 3 consumos), finalizar ×2 con la misma clave (1 ingreso), clave reutilizada en otro lote (422), hora real de inicio/cierre, compra ×3 (1 compra, 2 detalles, 2 movimientos, 1 salida de caja), fecha de compra offline.
- `PlantaMovilApiTest`: movimiento manual de insumo (suma sobre el saldo actual, salida mayor al stock → 422, entrada de leche → 422).
- `VentasMovilApiTest`: el reintento deja también un solo movimiento de producto VENTA.

## 5. Validación

- `php artisan migrate:status`: 109 Ran antes de esta auditoría; `2026_10_31_100000` revisada con `migrate --pretend` (1 DELETE en `api_idempotencia`, 1 FK, 2 índices).
- `php artisan test` (SQLite): 1850 tests; 1829 pasan, 1 omitido, 4 fallos y 16 errores **anteriores y ajenos a esta auditoría**: textos de vistas (Dashboard, Login, paginación de Acopio), fixture de `ProveedorPagosModalTest` contra `UNIQUE(proveedor_id, semana_proveedor)`, constructores desactualizados en tests de `EloquentZonasListadoQuery` / `ProductosListadoData`, y un test que depende del día de la semana. Todos los tests de esquema, idempotencia, caja, muestreo, asistencia, ventas y producción pasan.

## 6. Pendientes conocidos (no bloquean la carga)

- Aplicar `2026_10_31_100000` en MySQL local **después de un backup** y releer el esquema real.
- Corregir los 20 tests ajenos listados arriba.
- La salida de caja de una compra lleva la hora de registro en el servidor, no la del evento offline (la compra sí guarda el día del evento).
- `api_idempotencia` no se depura; crece con cada escritura móvil. Si se agrega limpieza, conservar al menos el máximo de días que la app puede tener operaciones en cola (`config/acopio.php → registro_movil.max_dias_atraso`).
- Los formularios web de compras/ventas no usan `Idempotency-Key`; dependen de deshabilitar el botón al enviar (el POS de ventas ya lo hace).
