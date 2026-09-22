# Zonas → Rutas → Recorridos de acopio (con Google Maps)

Documentación de la mejora que agrega Rutas y Recorridos de acopio al módulo Zonas, desacopla el registro de proveedores de su zona, y añade Google Maps para visualizar puntos de entrega. Complementa (no reemplaza) `index.md` y `docs/mobile-api/acopiador.md` (sección 9, para el cliente móvil).

## 1. Relaciones finales

```
zonas 1──N rutas 1──N ruta_detalles N──1 proveedores
                 └N recorridos_acopio 1──N recorrido_detalles N──1 proveedores
                                                    │
                                                    └── entregas.recorrido_detalle_id (nullable)
```

- **Ruta**: configuración vigente de proveedores y su orden de visita dentro de una zona. `ruta_detalles` contiene *solo* las asignaciones vigentes (una fila por proveedor activo en esa ruta); no conviven filas vigentes e históricas, por lo que sus restricciones únicas (`proveedor_id` único global, `(ruta_id, orden_visita)` único) son simples de mantener en MySQL.
- **proveedor_ruta_historial**: registro de auditoría de cada asignación/traslado (mismo patrón que la ya existente `proveedor_zona_historial`), sin restricción única — la vigencia la gestiona la aplicación (`AsignarProveedorARuta`/`QuitarProveedorDeRuta`), cerrando el registro vigente antes de abrir uno nuevo.
- **Recorrido** (`recorridos_acopio`): ejecución concreta de una ruta, por un acopiador, en una fecha operativa. Solo lo crea el ADMINISTRADOR desde el módulo Zonas ("asignar acopiador y abrir recorrido"); el acopiador únicamente abre, opera y consulta los que ya le fueron asignados.
- **Parada** (`recorrido_detalles`): copia congelada (proveedor, orden, ubicación) tomada al abrir el recorrido. Por eso un recorrido ya creado se sigue viendo igual aunque después cambie la ruta, la ubicación del proveedor o su asignación — la prueba `AbrirRecorridoTest` (*"un recorrido histórico conserva sus paradas aunque la ruta cambie después"*) lo verifica explícitamente.
- **Acopio**: sigue siendo una fila de `entregas` (sin tabla nueva). Se le agregaron `recorrido_detalle_id` (nullable — enlaza el acopio con la parada que lo originó; las entregas registradas fuera de un recorrido, como el flujo manual existente o el de CALIDAD, no lo usan) y `token_idempotencia` (único, protege contra doble envío/reintento/concurrencia).

`proveedores.zona_id` **se conserva** (ahora nullable) como caché sincronizada automáticamente por `AsignarProveedorARuta`/`QuitarProveedorDeRuta` (reutilizando el `ActualizarProveedor` que ya mantenía `proveedor_zona_historial`), en vez de eliminarse en esta misma entrega. La fuente de verdad real ya es `ruta_detalles`/`rutas`. Ver la sección 6 para el criterio de retiro definitivo de la columna.

## 2. Flujo del acopiador

1. El ADMINISTRADOR, desde `Zonas → (zona) → Rutas → (ruta)`, agrega proveedores existentes a la ruta (búsqueda por nombre/DNI, tipo "carrito"), define su orden, y asigna un acopiador para abrir el recorrido del día.
2. El acopiador entra a **Mis recorridos**, abre el recorrido de hoy y ve el mapa + la lista ordenada de paradas (pendiente=azul, recolectado=verde, no entregó=naranja, siempre con texto/leyenda además del color).
3. Identifica a un proveedor por DNI (búsqueda manual, siempre disponible) o escaneando su QR interno (Barcode Detection API si el navegador la soporta).
4. El backend valida que ese proveedor sea una parada de *este* recorrido (rechaza con mensaje claro si es de otro recorrido o no existe) y solo entonces se muestra el formulario de acopio.
5. Al guardar litros (y, opcionalmente, fecha/hora de ordeño y observación), el backend registra la entrega y actualiza la parada a "Recolectado" **en una sola transacción**: si algo falla, la parada permanece pendiente y no se crea nada.
6. El frontend actualiza marcador, lista y contadores (recolectados/pendientes/no entregó/litros) recién después de la confirmación del servidor — nunca de forma optimista, y nunca desde `localStorage`.
7. "No entregó" es una acción aparte, con motivo obligatorio, que no crea ningún acopio.

## 3. Instalación y migración

```bash
composer install
npm install
cp .env.example .env   # si no existe ya
php artisan key:generate
```

**Antes de migrar en una base de datos con datos reales, haz un respaldo.** Las migraciones nuevas (`database/migrations/2026_09_19_*`) son aditivas y no reescriben ninguna migración existente:

```bash
php artisan migrate
```

Qué hace cada una:

| Migración | Efecto |
|---|---|
| `090000_create_rutas_table` | Tabla `rutas`. |
| `090001_create_ruta_detalles_table` | Tabla `ruta_detalles` (solo asignaciones vigentes). |
| `090002_create_proveedor_ruta_historial_table` | Auditoría de asignaciones/traslados. |
| `090003_alter_proveedores_add_ubicacion` | Agrega `latitud`, `longitud`, `referencia_ubicacion` (nullable) a `proveedores`. |
| `090004_alter_proveedores_zona_id_nullable` | `proveedores.zona_id` pasa a nullable (un proveedor nuevo se registra sin zona). |
| `090005_backfill_rutas_desde_proveedores_zona` | **Migración de datos**: por cada zona con proveedores ya asignados, crea una "Ruta principal" y traslada esos proveedores a `ruta_detalles`, con orden determinista (por `proveedores.id` ascendente, es decir por antigüedad de registro) y su registro correspondiente en `proveedor_ruta_historial`. En una base de datos vacía (tests) no hace nada. **`down()` es intencionalmente un no-op** — deshacer un backfill de datos borrando filas arriesgaría eliminar ediciones legítimas hechas después desde el módulo Rutas. |
| `090006_create_recorridos_acopio_table` / `090007_create_recorrido_detalles_table` | Recorridos y sus paradas. |
| `090008_alter_entregas_add_recorrido_y_token` | Agrega `recorrido_detalle_id` y `token_idempotencia` (único) a `entregas`. |

No se incluye una migración que elimine físicamente `proveedores.zona_id`: por diseño, se mantiene como caché sincronizada para que reportes, filtros y políticas existentes que todavía la leen (por ejemplo `ProveedorPolicy`, el listado admin de proveedores, `RegistrarEntrega` en el flujo manual sin recorrido) sigan funcionando sin cambios mientras se verifica la transición completa. Antes de retirarla en una futura migración, confirma que para cada proveedor con asignación vigente, `proveedores.zona_id` coincide con `ruta_detalles → rutas → zona_id` (puedes hacerlo con una consulta simple, o extendiendo `tests/Feature/Database/Migrations/BackfillRutasDesdeProveedoresZonaTest.php`).

## 4. Configuración de Google Maps

Variables nuevas en `.env` (ver `.env.example`):

```
GOOGLE_MAPS_API_KEY=
GOOGLE_MAPS_MAP_ID=
```

Pasos en Google Cloud Console:

1. Crea (o reutiliza) un proyecto y **habilita facturación** — la Maps JavaScript API la exige incluso dentro de la capa gratuita mensual. No se creó ningún recurso de facturación automáticamente; este paso es manual y del equipo.
2. Habilita la **Maps JavaScript API**.
3. Crea una **API key** y restríngela:
   - **Restricción de aplicación**: "Sitios web", con los dominios/referrers exactos donde se sirve la app (ej. `https://tudominio.com/*`, y `http://localhost:8000/*` para desarrollo).
   - **Restricción de API**: solo "Maps JavaScript API".
   - Esta clave es **pública por diseño** (se imprime en el HTML para que el navegador la use): las restricciones de dominio/API de arriba son la protección real, no el secreto de la clave.
4. Crea un **Map ID** (Google Maps Platform → Map Management) para poder usar marcadores avanzados (`AdvancedMarkerElement`), y colócalo en `GOOGLE_MAPS_MAP_ID`.
5. Pega la clave y el Map ID en `.env` y en el `.env` de cada entorno donde se despliegue (no se comparten entre entornos).

Esta aplicación no usa ninguna API de Google exclusiva de servidor, así que no existe una credencial "de backend" separada que proteger.

**Si falta la clave o Google Maps falla al cargar**: cada pantalla (Ruta admin, Recorrido del acopiador) muestra un aviso y sigue funcionando en modo "sin mapa" — la lista, la búsqueda y el registro manual de acopio no dependen del mapa.

## 5. QR y escaneo — qué se soporta realmente

- El QR es el **identificador opaco interno de EcoLact** (`proveedores.qr_token`, ya existente antes de esta mejora) — no codifica DNI ni otro dato personal.
- El escaneo con cámara usa la **Barcode Detection API** del navegador (`BarcodeDetector`, formato `qr_code`). Donde no está disponible (navegador sin soporte, o cámara denegada), se muestra un mensaje claro y la búsqueda manual por DNI sigue disponible siempre.
- No se soporta ni se afirma soportar lectura de DNI físico ni otros documentos de identidad.

## 6. Backend y seguridad

- El acopiador solo accede a recorridos donde `recorrido.acopiador_id` coincide con su propio registro de Acopiador (`RecorridoAcopioPolicy`), nunca por el ID recibido en la URL/petición.
- `RegistrarAcopioDeRecorrido` verifica de nuevo esa pertenencia dentro del Use Case (no solo en la Policy), y reutiliza `RegistrarEntrega` existente en vez de duplicar la lógica de registro (mismo camino para valorización, calidad y liquidación).
- Idempotencia: `token_idempotencia` es único en `entregas`; un reintento con el mismo token devuelve la entrega ya creada. La defensa real contra una carrera concurrente es el índice único de base de datos, no solo la comprobación previa en PHP.
- La reordenación de paradas (`ReordenarRutaDetalles`) se hace en una transacción de dos fases (desplazar a valores negativos únicos, luego aplicar el orden final) para no chocar contra el índice único `(ruta_id, orden_visita)` a mitad de camino.

## 7. Pruebas ejecutadas

`php artisan test` (Pest, SQLite en memoria): **749 de 781 pasando**. Los **11 fallos restantes son previos a esta mejora** (pertenecen a otro trabajo en curso sin commitear sobre Liquidaciones/Pagos a proveedores, ajeno a este alcance — verificado comparando contra el estado sin estos cambios) y no se modificaron ni se intentó ocultarlos.

Cobertura nueva (43 pruebas): registro/duplicados de rutas, asignación y traslado de proveedores con historial, reordenación sin conflicto de índice único, retiro y reingreso de un proveedor a una ruta, apertura idempotente de un recorrido, congelamiento de paradas e inmutabilidad histórica, identificación por DNI/QR (incluyendo proveedor ajeno al recorrido), registro de acopio exitoso/fallido, idempotencia y entregas múltiples legítimas, autorización por Policy, "no entregó" sin crear acopio, coordenadas nulas/fuera de rango, y la migración de backfill.

**No verificado en este entorno** (requiere navegador real y credenciales): la carga efectiva de Google Maps, el escaneo con cámara, y la geolocalización del navegador. El build de assets (`npm run build`) sí se ejecutó y compiló sin errores.

## 8. Limitaciones y pendientes

- `proveedores.zona_id` no se elimina en esta entrega (ver sección 3) — es una decisión deliberada, no un olvido.
- No se implementó anulación de acopios: el proyecto no tenía (ni tiene tras esta mejora) un Use Case `AnularEntrega`, así que no había nada que integrar; `EstadoEntrega::ANULADA` existe en el enum pero ningún flujo lo usa todavía.
- El acopiador nunca crea su propio recorrido (solo el ADMINISTRADOR lo asigna). Si en el futuro se quiere autoservicio ("abrir hoy la misma ruta de ayer"), es una extensión aparte, no incluida aquí.
- El alcance de Maps es mostrar puntos y su orden; una eventual línea entre paradas sería una conexión visual, no una ruta real por carreteras — no se agregó cálculo ni optimización de rutas.
- `EcoLactMobile` (app Kotlin Multiplatform) no se tocó: se añadió el contrato Api equivalente (`docs/mobile-api/acopiador.md`, sección 9) para que ese cliente lo consuma cuando corresponda, pero no hay UI nativa implementada en esta entrega.
- Las migraciones se verificaron con el conjunto de pruebas (SQLite) y con un test dedicado que ejecuta el backfill contra datos "heredados"; no se ejecutaron contra la base de datos MySQL real de desarrollo/producción en esta sesión — corresponde al equipo ejecutarlas ahí, con respaldo previo.
