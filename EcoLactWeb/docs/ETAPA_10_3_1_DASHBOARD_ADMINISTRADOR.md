# EcoLact — Etapa 10.3.1

Dashboard administrativo implementado y validado el 10/09/2026. Alcance: consulta Web con Laravel 13.31.0, PHP 8.3.16, Blade, MySQL y Vite 8.2.2.

**A. Arquitectura final**

```text
DashboardPeriodoRequest (validación HTTP)
    ↓
DashboardController
    ↓
ObtenerDashboardAdministrador + DashboardPeriodo
    ↓
DashboardAdministradorQuery (contrato Application)
    ↓
EloquentDashboardAdministradorQuery (Infrastructure)
    ↓
MySQL: agregados, joins, subconsultas y UNION ALL
    ↓
DashboardAdministradorData
    ↓
admin.dashboard → layouts.admin
```

El controlador únicamente entrega datos validados al caso de uso y pasa un único objeto `dashboard` al Blade. No contiene consultas ni cálculos. No se creó una entidad Domain para Dashboard. Los Models, Entities y reglas del negocio existentes se conservaron.

`DashboardPeriodo` resuelve fechas en `config('app.timezone')`: hoy completo, semana lunes–domingo, mes completo o rango personalizado inclusivo. El filtro no utiliza el período jueves–miércoles de liquidaciones.

**B. KPI, definiciones y fuentes**

| KPI | Definición | Tablas / columnas reales | Filtro |
|---|---|---|---|
| Total acopiado | Suma de litros de entregas válidas | `entregas.litros`, `estado`, `fecha_hora` | `fecha_hora` dentro del período; `estado <> ANULADA` |
| Proveedores atendidos | Proveedores distintos con entrega válida / proveedores activos actuales; porcentaje con un decimal | `entregas.proveedor_id`, `estado`, `fecha_hora`; `proveedores.estado` | Numerador por período y sin anuladas; denominador `ACTIVO` actual. Sin activos: `0 / 0`, `0%` |
| Calidad aceptada | Confirmados aceptados / todos los confirmados × 100 | `analisis_calidad.estado`, `resultado`, `fecha_analisis` | `CONFIRMADO`, por `fecha_analisis`; numerador `ACEPTADA` |
| Entregas observadas | Cantidad de análisis confirmados observados | `analisis_calidad.estado`, `resultado`, `fecha_analisis` | `CONFIRMADO` + `OBSERVADA`, por `fecha_analisis` |
| Total valorizado | Suma de importes individuales ya calculados | `valorizaciones_entrega.importe_total`, `fecha_calculo` | `fecha_calculo` dentro del período; no usa pagos ni liquidaciones |
| Producción | Cantidad de lotes iniciados; desglose por estados reales | `lotes_produccion.fecha_hora_inicio`, `estado` | Inicio dentro del período; desglose `EN_PROCESO` / `FINALIZADO` |

Los totales se agregan en SQL. Litros e importes se convierten a `float` y se normalizan a dos decimales al construir la salida, siguiendo la estrategia numérica existente. No se recalculan precios ni valorizaciones.

**C. Bloques, datos, fuentes y estados vacíos**

| Bloque | Datos | Fuente | Estado vacío |
|---|---|---|---|
| Litros de leche por día | Fecha, etiqueta española, litros y número de entregas; alternancia visual de ambas medidas | `entregas`, agrupación `DATE(fecha_hora)`, sin anuladas | “No hay entregas en este período” |
| Distribución por calidad | Aceptada, observada y rechazada: cantidad, porcentaje y litros válidos asociados | `analisis_calidad` confirmado + `entregas` mediante `entrega_id` | “Sin datos de calidad” |
| Rendimiento por zonas | Nombre real, proveedores distintos, litros válidos, porcentaje de calidad aceptada | `zonas` + dos subconsultas agregadas independientes de entregas y análisis | Sin zonas: “No hay zonas registradas”; zona sin actividad: ceros y calidad “Sin datos” |
| Historial reciente de proveedores | Últimas seis entregas: fecha, proveedor, zona histórica, litros, calidad confirmada, estado y enlace | `entregas` + `proveedores.nombres/apellidos` + `zonas.nombre` + análisis confirmado opcional | “No hay entregas en este período” |
| Actividad reciente | Cinco eventos reales ordenados por fecha y desempate estable | `entregas.fecha_hora`, `analisis_calidad.fecha_analisis`, `liquidaciones.fecha_calculo`, `pagos_proveedor.fecha_pago`, `comunicados.created_at`; usuario vinculado | “No hay actividad reciente” |
| Producción reciente | Último lote del período: código, estado, inicio, litros utilizados y cantidades por producto con su unidad | `lotes_produccion`; `produccion_detalles.cantidad_producida`; `productos.nombre/unidad_medida` | “No existen lotes de producción”; lote sin resultados: mensaje específico |
| Comunicaciones | Publicados del período, destinatarios, lecturas actuales y tres recientes | `comunicados.estado/fecha_publicacion`; `comunicado_destinatarios.leido`; zona opcional | “No hay comunicados publicados”; sin destinatarios: tasa no disponible |

Los comunicados deben estar `PUBLICADO` y su fecha de publicación no puede ser futura. Los destinatarios se cuentan por asignación comunicado–usuario; una persona destinataria de dos comunicados representa dos asignaciones. La tasa refleja las lecturas actuales de los comunicados publicados en el período, no una reconstrucción histórica de lectura al cierre del período.

Los contadores de calidad consideran todos los confirmados del período, como requiere la definición. Sus litros asociados excluyen entregas anuladas. El historial mantiene visibles las anuladas para consulta; el total acopiado, el gráfico y los litros por zona las excluyen. La calidad de cada zona usa la fecha del análisis, mientras su acopio usa la fecha de entrega.

El gráfico presenta únicamente fechas con entregas válidas; no genera datos para fechas sin registros. Incluye una tabla HTML accesible con los mismos valores.

**D. Archivos creados**

| Archivo | Responsabilidad |
|---|---|
| [ObtenerDashboardAdministrador.php](<C:/CICLO VI/EcoLactV1/app/Application/Dashboard/ObtenerDashboardAdministrador.php>) | Caso de uso de lectura |
| [DashboardAdministradorData.php](<C:/CICLO VI/EcoLactV1/app/Application/Dashboard/DashboardAdministradorData.php>) | DTO `readonly` con los nueve bloques |
| [DashboardPeriodo.php](<C:/CICLO VI/EcoLactV1/app/Application/Dashboard/DashboardPeriodo.php>) | Resolución del intervalo temporal |
| [DashboardAdministradorQuery.php](<C:/CICLO VI/EcoLactV1/app/Application/Dashboard/Contracts/DashboardAdministradorQuery.php>) | Contrato de lectura |
| [EloquentDashboardAdministradorQuery.php](<C:/CICLO VI/EcoLactV1/app/Infrastructure/Persistence/Queries/EloquentDashboardAdministradorQuery.php>) | Consultas y normalización |
| [DashboardController.php](<C:/CICLO VI/EcoLactV1/app/Http/Controllers/Web/Admin/DashboardController.php>) | Endpoint administrativo invocable |
| [DashboardPeriodoRequest.php](<C:/CICLO VI/EcoLactV1/app/Http/Requests/Dashboard/DashboardPeriodoRequest.php>) | Validación de filtros y mensajes españoles |
| [dashboard.blade.php](<C:/CICLO VI/EcoLactV1/resources/views/admin/dashboard.blade.php>) | Vista del dashboard |
| [admin-dashboard.js](<C:/CICLO VI/EcoLactV1/resources/js/pages/admin-dashboard.js>) | Gráficos y controles locales |
| [admin-dashboard.css](<C:/CICLO VI/EcoLactV1/resources/css/pages/admin-dashboard.css>) | Estilos compatibles con la paleta existente |
| [DashboardFixtures.php](<C:/CICLO VI/EcoLactV1/tests/Support/DashboardFixtures.php>) | Datos aislados de prueba |
| [EloquentDashboardAdministradorQueryTest.php](<C:/CICLO VI/EcoLactV1/tests/Feature/Infrastructure/Persistence/EloquentDashboardAdministradorQueryTest.php>) | Pruebas de lectura y agregación |
| [DashboardControllerTest.php](<C:/CICLO VI/EcoLactV1/tests/Feature/Http/Controllers/Web/Admin/DashboardControllerTest.php>) | Permisos, filtros y HTTP |
| [DashboardPeriodoTest.php](<C:/CICLO VI/EcoLactV1/tests/Feature/Application/Dashboard/DashboardPeriodoTest.php>) | Semana, cambio de año, febrero y zona horaria |
| [DashboardViewTest.php](<C:/CICLO VI/EcoLactV1/tests/Feature/Web/DashboardViewTest.php>) | Blade, JSON, nombres, escape y sidebar |
| Este informe | Reporte de entrega A–R |

**E. Archivos modificados**

- [AppServiceProvider.php](<C:/CICLO VI/EcoLactV1/app/Providers/AppServiceProvider.php>): binding del contrato; formato Pint.
- [web.php](<C:/CICLO VI/EcoLactV1/routes/web.php>): una ruta dentro del grupo administrativo existente.
- [admin.blade.php](<C:/CICLO VI/EcoLactV1/resources/views/layouts/admin.blade.php>): enlace activo Dashboard, clase opcional de página y título de barra superior.
- [topbar.blade.php](<C:/CICLO VI/EcoLactV1/resources/views/components/layout/topbar.blade.php>): respeta un título explícito; su comportamiento predeterminado se conserva en los demás módulos.
- [app.js](<C:/CICLO VI/EcoLactV1/resources/js/app.js>): importación dinámica del módulo únicamente cuando existe el dashboard.
- [package.json](<C:/CICLO VI/EcoLactV1/package.json>) y [package-lock.json](<C:/CICLO VI/EcoLactV1/package-lock.json>): Chart.js y dependencia transitiva.
- [composer.lock](<C:/CICLO VI/EcoLactV1/composer.lock>): Boost actualizado de 2.8.0 a 2.8.1 durante la preparación exigida por AGENTS.md. Composer reescribió `composer.json` conservando la restricción `^2.8` de Boost.

La instalación obligatoria de Boost también regeneró `CLAUDE.md`, su configuración `.mcp.json` / `boost.json` y las guías existentes bajo `.claude/skills/`. No se cambiaron sus agentes seleccionados. `AGENTS.md` conserva el bootstrap porque la configuración existente selecciona `claude_code`; se leyeron las guías efectivamente generadas en `CLAUDE.md`.

Vite regeneró los artefactos ignorados de `public/build/`. Las capturas de revisión están en `storage/app/private/dashboard-qa/`; usan datos ficticios únicamente de SQLite en memoria, claramente separados de MySQL. El servidor temporal se detuvo y sus archivos HTML se eliminaron.

No existe directorio `.git` en esta copia del proyecto. Este inventario corresponde a las acciones de la sesión; se compararon los archivos PHP preexistentes de `app/` y `tests/` con una copia previa y únicamente cambió `AppServiceProvider.php` entre ellos.

**F. Ruta creada**

```text
GET|HEAD /admin/dashboard
Nombre: admin.dashboard
Acción: App\Http\Controllers\Web\Admin\DashboardController
Middleware: web → auth → active → role:ADMINISTRADOR
```

ADMINISTRADOR activo: 200. ACOPIADOR y PROVEEDOR: 403. Administrador inactivo: 403. Invitado con `Accept: application/json`: 401, siguiendo la estrategia existente antes de Login Web. El acceso HTML continúa requiriendo una sesión autenticada; no se creó Login ni se alteró la autenticación. La raíz `/` sigue renderizando `welcome`.

**G. Contrato creado**

```php
DashboardAdministradorQuery::obtener(DashboardPeriodo $periodo): DashboardAdministradorData
```

**H. Implementación Infrastructure**

`EloquentDashboardAdministradorQuery` usa los modelos existentes y Query Builder dentro de Infrastructure. Utiliza `SUM`, `COUNT`, `COUNT DISTINCT`, `GROUP BY`, joins y subconsultas para no multiplicar litros al incorporar relaciones.

El feed se obtiene mediante una sola consulta `UNION ALL`; cada fuente se limita a cinco filas antes de ordenar el resultado combinado. El listado de zonas no consulta dentro del bucle. El lote reciente requiere, como máximo, una consulta adicional para todos sus productos. Las pruebas comprueban que añadir quince zonas no incrementa el número de consultas y que la Query solo ejecuta `SELECT`.

**I. DTO / read model**

`DashboardAdministradorData` es `final readonly`, con documentación de las formas de los arrays y estas propiedades: `periodo`, `kpis`, `litrosPorDia`, `distribucionCalidad`, `rendimientoZonas`, `entregasRecientes`, `actividadReciente`, `produccionReciente`, `comunicaciones`.

Blade recibe nombres y fechas listos para mostrar, sin modificar Entities Domain. Los datasets se codifican con `Illuminate\Support\Js::encode`, se escapan como atributos HTML y se leen con `JSON.parse`. No hay consultas desde JavaScript ni uso de `eval`.

**J. Chart.js**

Instalado `chart.js@4.5.1` mediante npm, junto con `@kurkle/color`. npm informó cero vulnerabilidades. Los controladores, escalas y elementos necesarios se importan explícitamente; se integran con Vite, sin CDN. El chunk del dashboard se carga únicamente en esta página.

**K. CSS / JS**

El módulo JS crea barras por fecha y un donut de calidad, permite alternar litros/número de entregas y abre el filtro personalizado. Respeta la preferencia de movimiento reducido. Las tablas y listados mantienen los datos accesibles si no se dibuja el canvas.

Los estilos reutilizan los tokens existentes crema, teal y caramelo, además de los componentes `ui.card`, `ui.stat-card`, `ui.badge`, `ui.empty-state` y los formularios existentes. Las tarjetas pasan por seis, tres, dos y una columna según el ancho disponible. Las tablas desplazan horizontalmente dentro de su contenedor.

Se encontró que `.ecolact-sidebar { position: relative; }` prevalecía sobre las utilidades de Tailwind y reservaba 288 px en móvil. Se corrigió mediante selectores de `admin-dashboard-page`, con sidebar fijo en móvil y sticky en escritorio, sin cambiar los sidebars de los otros roles.

**L. Pruebas añadidas**

48 casos nuevos, siguiendo Pest + `RefreshDatabase` y las fixtures existentes:

- 15 de Query: BD vacía, límites diarios, anuladas, sumas decimales, proveedores distintos, ausencia de activos, confirmados/borradores, observadas, valorización individual y su fecha, agrupación diaria, separación e historial de zonas, lotes/productos, comunicaciones, feed, límite de seis entregas, ausencia de N+1 y solo lectura.
- 22 HTTP: administrador, ambos roles rechazados, inactivo, invitado JSON sin Login, todos los filtros, fechas iguales, nueve variantes inválidas, errores de sesión, fechas ajenas ignoradas y raíz conservada.
- 5 de período: lunes, domingo, cruce de año, febrero bisiesto y zona horaria.
- 6 de Blade: KPI, filtro activo, fechas, JSON numérico, nombres reales, escape de HTML, estados vacíos y enlace activo del sidebar.

Revisión adicional en Chrome con Playwright ya disponible en el entorno: 1600, 1280, 768 y 390 px sin desbordamiento de página; cambio real del canvas al alternar métricas; formulario personalizado; apertura/cierre de sidebar; capturas con datos y sin datos; cero errores JavaScript. Se renderizó el ciclo HTTP autenticado usando exclusivamente una base SQLite en memoria. No se añadieron paquetes de pruebas al proyecto.

**M. Resultado exacto de `php artisan test --compact`**

```json
{"tool":"pest","result":"passed","tests":391,"passed":390,"assertions":1052,"duration_ms":16339,"skipped":1}
```

Salida 0. La única omisión es la prueba preexistente `EcoLactMySqlSchemaTest`, que exige activar explícitamente `ECOLACT_MYSQL_SCHEMA_TEST=1` y crea dos bases MySQL descartables. No corresponde a las pruebas nuevas del dashboard. Se ejecutó adicionalmente la Query real contra el MySQL local en modo lectura: terminó correctamente, con KPI en cero y `auditorias = 0`.

**N. Resultado de `npm run build`**

Salida 0. Vite 8.2.2 transformó nueve módulos y terminó en 692 ms en la última ejecución. Artefactos principales:

| Artefacto | Tamaño | Gzip |
|---|---:|---:|
| `admin-dashboard-kWLyW5Rg.js` | 161.79 kB | 56.34 kB |
| `admin-dashboard-kXIEb8SO.css` | 4.54 kB | 1.25 kB |
| `app-C2zq9tq9.js` | 3.45 kB | 1.36 kB |
| `app-DnjFxiT2.css` | 74.84 kB | 14.89 kB |

El plugin de fuentes informa que falta el paquete opcional `fontaine` para optimizar fuentes de respaldo. Es un aviso de la configuración existente; las fuentes se generaron y el build pasó. No se añadió una dependencia extra para silenciarlo.

**O. Resultado Pint**

Se ejecutó `php vendor/bin/pint app tests --format agent`, equivalente Windows del comando solicitado. En la primera ejecución ordenó imports/espacios del Provider y ajustó formato de un test nuevo. Resultado final, salida 0:

```json
{"tool":"pint","result":"passed"}
```

**P. Migraciones, rutas y vistas**

- `php artisan migrate:status`: salida 0; las 34 migraciones figuran `Ran`, ninguna pendiente. La última es `2026_09_10_005703_alter_analisis_calidad_resultado_nullable`, batch 4.
- `php artisan route:list`: salida 0; 81 rutas, incluido `admin.dashboard`. Se verificaron también los middleware mediante `--name=admin.dashboard -vv`.
- `php artisan view:clear`: salida 0, `Compiled views cleared successfully.`
- `php artisan view:cache`: salida 0, `Blade templates cached successfully.`

No se ejecutaron migraciones contra MySQL ni se modificaron migrations o seeders. Las migraciones ejecutadas para tests y revisión visual se limitaron a SQLite en memoria.

**Q. Diferencias e inconsistencias resueltas**

1. El framework instalado es Laravel 13.31.0; Composer exige PHP `^8.3` y el ejecutable real es 8.3.16. Se usaron estas versiones existentes.
2. El importe real es `valorizaciones_entrega.importe_total`; se filtra por `fecha_calculo`.
3. `users` tiene `username`, no `name`; el saludo muestra el usuario autenticado.
4. `auditorias` está vacía y no tiene escritores en la aplicación. El feed combina operaciones reales sin insertar auditorías.
5. No existe una columna de fecha de confirmación separada; los análisis del feed usan `fecha_analisis`, identificada en el texto como “Fecha del análisis”.
6. La aplicación usa UTC. Se respetó la configuración y se muestra la zona horaria junto al período; no se cambió a America/Lima unilateralmente.
7. Los proveedores activos son un inventario actual, no un snapshot histórico. Para períodos anteriores, el cociente entre proveedores atendidos y activos actuales puede superar el 100% si cambiaron los estados. La UI identifica el denominador como activos actuales; sin activos muestra `0 / 0` según lo solicitado.
8. Se corrigió el conflicto del sidebar móvil únicamente dentro de esta página y se eliminó el saludo duplicado mediante el título opcional de topbar.
9. SQLite representa algunas sumas decimales como flotantes; la salida se normaliza una sola vez a dos decimales, mientras MySQL conserva el agregado DECIMAL antes de la conversión.
10. Boost generó instrucciones para el agente ya configurado (`claude_code`), conservando el `AGENTS.md` inicial; se leyeron las instrucciones generadas.

Las consultas utilizan las APIs documentadas de [Query Builder de Laravel 13](https://laravel.com/framework/docs/13.x/queries). La integración del gráfico sigue la [documentación de módulos de Chart.js](https://www.chartjs.org/docs/latest/getting-started/integration.html).

**R. Información no presentada por falta de datos o soporte real**

- La consulta sobre MySQL local no encontró actividad operativa para el mes actual: el dashboard muestra ceros y estados vacíos. No se insertaron ejemplos en esa base.
- No se muestra rendimiento combinado de productos: el esquema permite productos con unidades distintas y no asigna litros a cada producto para obtener un rendimiento individual fiable.
- No se inventa tasa de lectura cuando no existen destinatarios registrados.
- No se presentan auditorías ficticias, tendencias comparativas ni fechas de confirmación inexistentes.

ETAPA 10.3.1 — DASHBOARD ADMINISTRADOR REAL ✅

Trabajo detenido en esta etapa. No se implementó Etapa 11, Login, logout ni API adicional.

