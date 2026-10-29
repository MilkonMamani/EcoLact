"""Construye documentación y reportes visuales exclusivamente desde JUnit y logs."""
from pathlib import Path
import collections
import csv
import hashlib
import html
import json
import re
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[3]
DOC = ROOT / 'docs/informeEcoLact/docs'
BASE = DOC / 'assets/evidencias/pruebas'
RUN = '20261029-qa02'
SUMMARY = BASE / 'resumen' / RUN
SUMMARY.mkdir(parents=True, exist_ok=True)

def cases(path):
    result = []
    for t in ET.parse(path).iter('testcase'):
        status = 'aprobada'
        detail = ''
        for tag, value in [('skipped','omitida'),('failure','fallida'),('error','error')]:
            e = t.find(tag)
            if e is not None:
                status = value
                detail = ''.join(e.itertext()).strip() or e.get('message','')
        result.append(dict(name=t.get('name'), classname=t.get('classname', t.get('class','')),
                           file=t.get('file',''), status=status, seconds=float(t.get('time','0')),
                           detail=detail, source=str(path.relative_to(DOC)).replace('\\','/')))
    return result

unit = cases(BASE/'backend'/RUN/'unitarias.xml')
feature = cases(BASE/'backend'/RUN/'funcionales.xml')
integration = cases(BASE/'integracion'/RUN/'integracion.xml')
mobile = [c for p in sorted((BASE/'mobile'/RUN/'junit').glob('*.xml')) for c in cases(p)]
all_cases = unit + feature + mobile
assert len({(c['classname'],c['name']) for c in all_cases}) == len(all_cases), 'Duplicate canonical tests'

def counts(cs):
    n = collections.Counter(c['status'] for c in cs)
    return dict(total=len(cs), passed=n['aprobada'], failures=n['fallida'], errors=n['error'], skipped=n['omitida'],
                executed=len(cs)-n['omitida'], seconds=round(sum(c['seconds'] for c in cs),3))

stats = {k:counts(v) for k,v in [('unit',unit),('feature',feature),('mobile',mobile),('integration',integration),('total',all_cases)]}
(SUMMARY/'resultados.json').write_text(json.dumps(stats,indent=2),encoding='utf-8')
(SUMMARY/'casos.json').write_text(json.dumps(all_cases,ensure_ascii=False,indent=2),encoding='utf-8')
with (SUMMARY/'casos.csv').open('w',newline='',encoding='utf-8-sig') as f:
    w=csv.DictWriter(f,fieldnames=list(all_cases[0])); w.writeheader(); w.writerows(all_cases)

def link(group,filename,run=RUN,label=None):
    return f'[{label or filename}](../assets/evidencias/pruebas/{group}/{run}/{filename})'

def table(headers, rows):
    def clean(x): return str(x).replace('|','&#124;').replace('\n',' ')
    return '<div class="ec-test-table" markdown="1">\n\n|'+'|'.join(headers)+'|\n|'+'|'.join('---' for _ in headers)+'|\n'+''.join('|'+'|'.join(clean(x) for x in row)+'|\n' for row in rows)+'\n</div>\n'

def summary(cs):
    s=counts(cs)
    cards=[('Ejecutadas',s['executed'],''),('Aprobadas',s['passed'],'passed'),('Fallidas / errores',s['failures']+s['errors'],'failed'),('Omitidas',s['skipped'],'skipped'),('Tiempo de casos',str(s['seconds'])+' s','')]
    return '<div class="ec-test-summary">'+''.join(f'<div class="ec-test-card ec-test-{cl}"><strong>{val}</strong>{label}</div>' for label,val,cl in cards)+'</div>\n'

def status(cs):
    s=counts(cs)
    return f"{s['passed']} aprobadas · {s['failures']} fallidas · {s['errors']} errores · {s['skipped']} omitidas"

def page(slug,number,label,title,body):
    (DOC/'pruebas'/f'{slug}.md').write_text(f'''---
title: "{title}"
---

<div class="ec-pruebas" markdown="1">
<div class="ec-test-banner" markdown="1">

:material-test-tube: {number}. {label}

</div>
<div class="ec-test-content" markdown="1">

<span class="ec-test-kicker">EcoLact / 6. Pruebas / Ejecución verificable</span>

# {title}

{body}

<div class="ec-test-conclusion" markdown="1">

Consulta la [estrategia](index.md), el [backend](backend.md), la [app móvil](mobile.md), la [integración](integracion.md) y el [archivo de evidencias](evidencias.md).

</div>
</div>
</div>
''',encoding='utf-8')

def evidence(group,name,title,cs):
    m=json.loads((BASE/group/RUN/(name+'.json')).read_text())
    return f'''<div class="ec-test-evidence" markdown="1">

### {title}

**{m['started']} · salida {m['exit_code']} · {m['duration_seconds']} s de proceso.**

```powershell
{m['command']}
```

{status(cs)}.

![Reporte visual de {title}](../assets/images/pruebas/{group}/{RUN}-{name}.png)

*Reporte visual generado a partir de una ejecución real. Es una captura del reporte HTML; no es una captura directa de la terminal. La imagen resume; el log conserva toda la salida.*

{link(group,name+'.log',label='Log completo')} · {link(group,name+'.json',label='Comando, fecha, duración y salida')} · {link(group,name+'.html',label='Reporte HTML completo')}

</div>'''

# HTML derivado: salida escapada literalmente, sin adornos de terminal ni mensajes inventados.
for group,name,title,cs in [('backend','unitarias','Backend · unitarias',unit),('backend','funcionales','Backend · funcionales',feature),('mobile','mobile-android-host','Móvil · Android Host',mobile),('integracion','integracion','Integración · selección backend',integration)]:
    folder=BASE/group/RUN
    m=json.loads((folder/(name+'.json')).read_text())
    raw=(folder/(name+'.log')).read_text(encoding='utf-8',errors='replace')
    s=counts(cs)
    failures=[c for c in cs if c['status'] in ('fallida','error')]
    failure_html=''.join('<li><b>'+html.escape(c['name'])+'</b><p>'+html.escape(c['detail'].split('\n')[0][:400])+'</p></li>' for c in failures)
    content=f'''<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{title}</title>
<style>body{{font:16px/1.5 system-ui;margin:0;background:#eff4fb;color:#1d344e}}main{{max-width:1120px;margin:30px auto;background:white;padding:32px;border-radius:12px}}h1,h2{{color:#103b86}}.label{{color:#1759c2;font-weight:bold}}.cards{{display:flex;gap:12px;flex-wrap:wrap}}.card{{background:#edf4ff;padding:18px;min-width:135px;border-radius:8px}}.card b{{display:block;font-size:30px}}.bad{{background:#fff0f1;color:#9f2430}}.ok{{background:#e8f5ed;color:#146c43}}pre{{background:#15253c;color:white;white-space:pre-wrap;overflow-wrap:anywhere;padding:20px;border-radius:8px;font-size:13px}}code{{overflow-wrap:anywhere}}li{{margin:12px 0}}small{{color:#536173}}</style>
<main><div class="label">ECOLACT · EVIDENCIA DE EJECUCIÓN · {RUN}</div><h1>{title}</h1>
<p>Reporte visual generado a partir de una ejecución real. Fuente primaria: log y JUnit; no es una terminal simulada.</p>
<p><b>Inicio:</b> {m['started']}<br><b>Fin:</b> {m['ended']}<br><b>Código de salida:</b> {m['exit_code']} · <b>Duración del proceso:</b> {m['duration_seconds']} s</p>
<div class="cards"><div class="card"><b>{s['executed']}</b>Ejecutadas</div><div class="card ok"><b>{s['passed']}</b>Aprobadas</div><div class="card {'bad' if s['failures']+s['errors'] else ''}"><b>{s['failures']+s['errors']}</b>Fallidas / errores</div><div class="card"><b>{s['skipped']}</b>Omitidas</div></div>
<h2>Comando real</h2><pre>{html.escape(m['command'])}</pre><p><small>SHA-256 del log: {m['sha256']}</small></p>
<p><a href="{name}.log">Descargar log completo (copia publicable)</a> · <a href="{name}.json">Metadatos</a></p><p><small>Los secretos detectados se sustituyen por [REDACTADO]. El hash mostrado corresponde a esta copia; los metadatos conservan el hash original cuando hubo redacción.</small></p>
<h2>Interpretación del alcance</h2><p>{'La ejecución contiene fallos. Deben revisarse los casos y sus precondiciones antes de declarar validada la funcionalidad.' if failures else 'Los casos ejecutados aprobaron. Este resultado no acredita dispositivos físicos, iOS ni un flujo completo contra producción.'}</p>
{('<h2>Casos no satisfactorios</h2><ul>'+failure_html+'</ul>') if failures else ''}
<h2>Salida completa del proceso</h2><pre>{html.escape(raw)}</pre></main></html>'''
    (folder/(name+'.html')).write_text(content,encoding='utf-8')

total=stats['total']
overview=f'''Las pruebas verifican reglas de negocio, comportamiento observable e interacción entre componentes. Este informe corresponde al árbol local de EcoLact del **29 de octubre de 2026**, con cambios sin commit. Los valores se calcularon desde los casos JUnit, no desde el número de archivos ni de aserciones.

{summary(all_cases)}

**Lectura del resultado:** {status(all_cases)}. “Fallidas / errores” reúne aserciones incumplidas y excepciones; ambas se desglosan en las tablas. Las omitidas fueron descubiertas por el runner pero su cuerpo no se ejecutó. iOS y el flujo extremo a extremo permanecen fuera de este total.

## Niveles de la estrategia

<div class="ec-test-pyramid" role="img" aria-label="Tres niveles: integración, funcionales y API, unitarias">
<div class="ec-test-level"><b>Integración</b><br>Consistencia entre componentes</div>
<div class="ec-test-level"><b>Funcionales / API</b><br>Casos de uso, respuestas y autorización</div>
<div class="ec-test-level"><b>Unitarias</b><br>Entidades, reglas y transformaciones aisladas</div>
</div>

La pirámide expresa niveles de responsabilidad; su tamaño no representa una proporción medida de cobertura.

{table(['Área','Evidencia existente','Alcance y pendiente'],[
('Unitarias','Unit de PHP y commonTest de Kotlin','Entidades, parsers y modelos. Sin porcentaje de cobertura de líneas medido.'),
('Funcionales y casos de uso','Feature/Application y Feature/Web','Reglas, persistencia SQLite, vistas y transacciones. No equivale a un navegador operando la web.'),
('API y autorización','Feature/Http/Api y Feature/Auth','Peticiones internas Laravel, roles, fechas e idempotencia; no usa un servidor remoto.'),
('Móvil y offline','androidHostTest y commonTest en JVM','SQLite en memoria y respuestas controladas. Faltan dispositivos físicos.'),
('Integración','Selección de Feature y pruebas de repositorio móvil','Componentes reales dentro de cada proceso; App → API desplegada → MySQL pendiente.')])}

## Herramientas verificadas

Pest **4.7.8**, PHPUnit **12.5.33** y Laravel Testing sobre Laravel **13.31.0** están instalados. Kotlin Test, Gradle **9.1.0**, SQLDelight y Ktor MockEngine aparecen en las dependencias móviles. SQLite JDBC se emplea en las pruebas host; la configuración PHP utiliza `pdo_sqlite` y `:memory:`. Las versiones exactas del entorno están en [Evidencias](evidencias.md).

## Entorno seguro y reproducibilidad

!!! warning "Alcance temporal de los resultados"
    La auditoría final detectó 15 archivos PHP de aplicación/pruebas diferentes del inventario inicial, con fechas de modificación posteriores al cierre de Feature. Los resultados corresponden a la ejecución registrada y **no validan esas modificaciones posteriores**. El detalle está en [Evidencias](evidencias.md#integridad-privacidad-y-reproduccion).

Se verificó `phpunit.xml`: `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL` vacío. No había `bootstrap/cache/config.php`. Esas variables se fijaron antes de iniciar Artisan; correo y caché son de tipo `array`, la cola es `sync`, broadcast es `null` y los archivos de prueba se dirigieron a almacenamiento temporal.

`EcoLactMySqlSchemaTest.php` contiene conexiones MySQL explícitas. Su condición inicial omite el test salvo que `ECOLACT_MYSQL_SCHEMA_TEST=1`; se impuso **0**. No se ejecutaron migraciones manuales, borrados ni solicitudes modificadoras contra servicios reales. `RefreshDatabase` actúa sobre SQLite de prueba.

El script `scripts/ejecutar_evidencias.py` conserva salida completa y metadatos. Rechaza reutilizar un nombre de log histórico. Para una nueva ejecución se debe elegir otro identificador y volver a auditar cambios en los tests; el script comprueba SQLite y ausencia de caché, pero no sustituye esa inspección.

## Resultados destacados

<div class="ec-test-summary" markdown="1">
<div class="ec-test-card" markdown="1">

### [Backend](backend.md)

Reglas, casos de uso, vistas y autorización.

</div>
<div class="ec-test-card" markdown="1">

### [Aplicación móvil](mobile.md)

Persistencia local y sincronización simulada.

</div>
<div class="ec-test-card" markdown="1">

### [Integración](integracion.md)

Flujos y límites entre componentes.

</div>
<div class="ec-test-card" markdown="1">

### [Evidencias](evidencias.md)

Logs, JUnit, capturas y resultados por caso.

</div>
</div>

## Cómo se contabiliza

El resumen suma **Unit + Feature + Android Host** una sola vez por pareja clase/caso. La prueba inicial y la selección de integración son repeticiones diagnósticas y no se vuelven a sumar. commonTest ya forma parte de Android Host. “Tiempo de casos” suma los tiempos de JUnit; no es la duración de compilación ni el tiempo transcurrido entre ejecuciones paralelas.
'''
page('index',1,'Estrategia de pruebas','Pruebas y validación de EcoLact',overview)

backend_cases=unit+feature
def classify(c):
    f=c['classname']
    for label,terms in [('API REST',['.Http.Api.']),('Autenticación y roles',['.Auth.','RoutesPorRol']),('Proveedores y acopiadores',['Proveedor','Acopiador']),('Zonas y recorridos',['Zona','Ruta','Recorrido']),('Registro de entregas',['Acopio','Entrega']),('Control de calidad',['Calidad','TicketAnalizador']),('Producción e inventario',['Produccion','Planta','Insumo','Producto','Receta','Stock']),('Pagos y liquidaciones',['Pago','Liquidacion','Asistencia','Jornada','Trabajador']),('Ventas y caja',['Venta','Caja','Compra'])]:
        if any(t in f for t in terms): return label
    return 'Transversal y otras comprobaciones'

groups=collections.defaultdict(list)
for c in backend_cases: groups[classify(c)].append(c)
group_table=table(['Grupo','Casos descubiertos','Aprobadas','Fallidas','Errores','Omitidas'],[(k,len(v),counts(v)['passed'],counts(v)['failures'],counts(v)['errors'],counts(v)['skipped']) for k,v in groups.items()])
relevant=[('Registro de entregas','RegistrarEntregaTest','Registrar entrega válida, identidad DNI/QR, zona y estado','Crear proveedor y acopiador, ejecutar RegistrarEntrega y comprobar entidad y notificación.'),
('Control de calidad','CalidadNoBloqueaProduccionTest','Mantener independencia de calidad y producción','Registrar leche sin análisis, producir y comprobar stock tras analizar.'),
('Pagos','SincronizacionAutomaticaTest','Generar y recalcular pagos pendientes sin duplicados','Registrar entregas, confirmar análisis y repetir sincronización; verificar transacción y saldos.'),
('Liquidaciones','GenerarLiquidacionTest','Aplicar precio semanal y evitar duplicación','Configurar precio e incidencias, generar liquidación y verificar importe y estado.'),
('API REST','AcopiadorMovilApiTest','Fechas offline, autorización e idempotencia','Enviar peticiones internas Laravel y comparar respuestas y filas SQLite.'),
('Autenticación','RoleMiddlewareTest','Permitir rol correcto y denegar acceso indebido','Ejecutar middleware con usuario correcto, incorrecto y sin autenticación.'),
('Ventas y caja','CajaTest','Saldo, movimientos únicos y atomicidad','Registrar eventos financieros y consultar caja; provocar fallo para verificar reversión.')]
relevant_rows=[]
for group,name,goal,procedure in relevant:
    cs=[c for c in feature if c['classname'].split('.')[-1]==name]
    assert cs,name
    src=cs[0]['file'].split('::')[0].replace('\\','/')
    relevant_rows.append((group,f'`{src}`',goal,procedure,status(cs),link('backend','funcionales.xml',label='JUnit')))

bad=[c for c in feature if c['status'] in ('error','fallida')]
failure_table=table(['Caso','Tipo','Mensaje real','Evidencia'],[(c['classname'].split('.')[-1]+' · '+c['name'],c['status'],html.escape(c['detail'].split('\n')[0]),link('backend','funcionales.xml',label='JUnit y traza')) for c in bad])
backend=f'''Se inspeccionaron `EcoLactWeb/tests/Unit` (13 archivos `*Test.php`), `tests/Feature` (260 archivos `*Test.php`), `phpunit.xml` y las dependencias instaladas. Los siete archivos prioritarios solicitados existen; sus rutas reales aparecen debajo. Los archivos pueden contener varias funciones y datasets: no equivalen al número de casos ejecutados.

{summary(backend_cases)}

## Procedimiento de ejecución

Primero se ejecutó `RegistrarEntregaTest.php`: 6 casos aprobados. A continuación se ejecutaron las suites Unit y Feature. Ambas cubren las suites declaradas en `phpunit.xml`; no se añadió una ejecución general redundante. El intento inicial con `--no-interaction` terminó con salida 2 antes de descubrir casos; el runner lo rechazó y se retiró únicamente ese argumento, conservando el log.

```powershell
php -v
composer --version
composer show --direct
php artisan test --colors=never --testsuite=Unit --log-junit=<ruta-unitarias.xml>
php artisan test --colors=never --testsuite=Feature --log-junit=<ruta-funcionales.xml>
```

Los argumentos de los procesos y el entorno aislado se registran en los metadatos. Para reproducir desde la raíz del repositorio con un identificador nuevo, se puede usar `python docs/informeEcoLact/scripts/ejecutar_evidencias.py <nuevo-id> backend unitarias` y después `backend funcionales`. El script aplica el aislamiento antes de iniciar Artisan. Las representaciones de argumentos en los reportes no incluyen las variables de entorno; no deben pegarse aisladamente en otra consola. No se modificaron reglas de negocio, pruebas PHP ni archivos Kotlin para cambiar los resultados.

## Clasificación funcional

{group_table}

La clasificación asigna cada caso una sola vez: API y autenticación tienen prioridad, seguidas por palabras de la clase. Las categorías agrupan resultados, no representan cobertura de todos los requisitos de cada módulo. El inventario exhaustivo está en {link('resumen','casos.csv',label='casos CSV')}.

## Pruebas relevantes: objetivo, condición, procedimiento y resultado

{table(['Grupo','Prueba o suite','Objetivo y condición','Procedimiento','Resultado real','Evidencia'],relevant_rows)}

Un caso aprobado respalda las condiciones y aserciones que ese test ejecutó; no verifica automáticamente todas las combinaciones, el navegador ni MySQL. Los casos fallidos no sostienen el comportamiento esperado.

## Fallos y errores conservados

{failure_table}

**Análisis de la selección de integración:** los dos casos de `AcopiadorMovilApiTest` calculan el evento con `now()->subHours(2)` y `subMinutes(90)`, mientras el fixture registra asistencia de hoy. La ejecución ocurrió cerca de la 01:18 en Lima; esos eventos corresponden al día anterior. `RequiereAsistencia` valida el día del evento. Esto es consistente con el HTTP 403 observado y apunta a dependencia del reloj en el escenario; no demuestra un defecto en conversión UTC. Se recomienda fijar fecha/hora y asistencia coherentes en una revisión posterior del test.

En `EntregaAlimentaLecheTest`, la preparación intenta pagar una obligación semanal antes de su fecha programada. `RegistrarPagoObligacion` lanza la excepción con fecha **04/11/2026**, antes de llegar a la comprobación de anulación. El objetivo de ese caso permanece sin validar en esta ejecución.

Los otros **15 errores** son discrepancias concretas entre fixtures y contratos actuales: 12 casos de `ProveedorPagosModalTest` intentan crear dos obligaciones del mismo proveedor en la misma semana y chocan con la restricción única `(proveedor_id, semana_proveedor)`; 2 casos de `EloquentZonasListadoQueryTest` construyen la consulta sin el argumento `Reloj` obligatorio; 1 caso de `EloquentProductosListadoQueryTest` consulta `kpis`, propiedad que el DTO actual no contiene. Los tests no alcanzan sus comprobaciones finales.

Los **4 fallos de vistas** restantes corresponden a texto de paginación esperado en acopio, diferencia de título/rango en dashboard, texto de bienvenida esperado en login y `data-pendiente="1"` esperado en una fila de pagos que devuelve `0`. Se requiere revisar cada expectativa frente al comportamiento deseado; no se cambiaron textos, contratos ni reglas para ocultarlos.

## Evidencias del backend

{evidence('backend','unitarias','Pruebas unitarias',unit)}

{evidence('backend','funcionales','Pruebas funcionales',feature)}

## Conclusión

La suite unitaria aprobó sus {len(unit)} casos. Feature obtuvo {status(feature)}. Los resultados respaldan los casos aprobados bajo SQLite y muestran condiciones pendientes de corrección o revisión. No permiten afirmar que todo el backend esté validado. La equivalencia del esquema MySQL sigue omitida por seguridad.
'''
page('backend',2,'Pruebas del backend','Validación de Laravel y reglas del negocio',backend)

mobile_explanations={
'AcopiadorOfflineTest':'QR/DNI cacheado, entrega pendiente persistente, reconciliación sin duplicados, hora original, error de zona y recorrido offline.',
'CalidadOfflineTest':'Selección, foto, análisis, conflicto y migración local. Usa HttpClient sin MockEngine, pero el repositorio se fuerza offline; las respuestas posteriores se aplican localmente.',
'CalidadMuestreoSyncTest':'Selección aleatoria autorizada por servidor simulado, reconexión ordenada y conflictos; OCR queda pendiente de revisión humana.',
'PlantaOfflineTest':'QR local, producción rechazada como conflicto, comprobante pendiente de OCR y compra encolada una sola vez.',
'VentasSyncTest':'Venta en efectivo, boleta tras sincronizar, stock reservado, usuario distinto, rechazos y reintento con misma clave y fecha.',
'LocalFirstViewModelTest':'Conservar datos locales ante fallos de red y mostrar estados offline sin ocultar la copia existente.',
'AsistenciaGateTest':'Solo asistencia confirmada para hoy permite operar offline; la asistencia anterior no autoriza y el servidor simulado revalida.',
'MiQrTrabajadorTest':'Disponibilidad local del QR propio y tratamiento de respuesta remota controlada.'}
mobile_rows=[]
for cls in sorted({c['classname'] for c in mobile}):
    cs=[c for c in mobile if c['classname']==cls]
    name=cls.split('.')[-1]
    found=list((ROOT/'EcoLactMobile/shared/src').glob('**/'+name+'.kt'))
    src=str(found[0].relative_to(ROOT/'EcoLactMobile')).replace('\\','/') if found else name
    mobile_rows.append((f'`{src}`',mobile_explanations.get(name,'Aserciones de lógica compartida, modelos, formato o presentación; consultar nombres de casos en JUnit.'),len(cs),status(cs),link('mobile','junit/'+Path(cs[0]['source']).name,label='JUnit')))
mobile_body=f'''Se inspeccionaron `shared/src/commonTest`, `shared/src/androidHostTest`, `shared/src/iosTest` y `shared/build.gradle.kts`. La tarea host ejecuta pruebas comunes y Android JVM; no requiere arrancar un emulador.

{summary(mobile)}

## Preparación y comando real

```powershell
java -version
.\\gradlew.bat --version
.\\gradlew.bat :shared:testAndroidHostTest --console=plain --rerun-tasks
```

Se usó `--rerun-tasks` para forzar la ejecución actual y evitar presentar un resultado `UP-TO-DATE` como prueba nueva. Gradle informó **33 tareas ejecutadas** y salida **0**. Java del lanzador es Temurin **17.0.17**; el archivo `gradle-daemon-jvm.properties` solicita un daemon **Java 21 Azul**. No se deben confundir ambos entornos. Se conservaron advertencias de Kotlin sobre clases `expect/actual` en beta.

## Casos y alcance real

{table(['Archivo real','Objetivo y condición comprobada','Casos JUnit','Resultado','Evidencia'],mobile_rows)}

**Procedimiento común:** crear el esquema SQLDelight sobre `JdbcSqliteDriver.IN_MEMORY`, sembrar datos de prueba, ejecutar el repositorio o handler y verificar filas, estados o respuestas. Los tests de modelos y ViewModel pueden prescindir de la base de datos. Los detalles por función y sus tiempos están en {link('resumen','casos.csv',label='el inventario por caso')}.

## Offline-first e integración simulada

SQLite JDBC y las consultas SQLDelight son componentes reales ejecutados en memoria. `CacheRepository`, `SyncQueueRepository`, repositorios y handlers trabajan sobre esos datos. En los tests de sincronización, **Ktor MockEngine reemplaza al servidor HTTP**. El caso `CalidadOfflineTest` configura `isOnline = {{ false }}` y cambia estados de forma local; tampoco constituye una conexión con Laravel.

Se comprueban persistencia de pendientes, claves idempotentes, conflictos visibles, reintentos y restricciones de asistencia. La reapertura que simulan los tests no acredita recuperación ante apagado físico, batería agotada, cambio real de red ni restricciones del sistema Android.

## Evidencia de Android Host

{evidence('mobile','mobile-android-host','Pruebas Android Host',mobile)}

También está disponible el {link('mobile','gradle-report/index.html',label='reporte HTML original de Gradle')} con clases, métodos y tiempos.

## Compatibilidad iOS

<div class="ec-test-not-run ec-test-card" markdown="1">

**NO EJECUTADA:** `shared/src/iosTest/kotlin/pe/edu/upeu/ecolactmobile/SharedLogicIOSTest.kt` contiene una prueba `example`. Windows no dispone de Xcode ni de un destino Apple para ejecutar `iosArm64` o `iosSimulatorArm64`. Los casos commonTest aprobados en JVM no certifican su ejecución en iOS.

</div>

## Conclusión

Los {len(mobile)} casos host aprobaron y respaldan los escenarios controlados descritos. Quedan pendientes la ejecución nativa iOS, pruebas instrumentadas en dispositivos y un flujo completo con API real y MySQL aislado. No se midió cobertura de líneas ni rendimiento de producción.
'''
page('mobile',3,'Pruebas de aplicación móvil','Validación de Kotlin Multiplatform y offline-first',mobile_body)

integration_rows=[]
for cls in sorted({c['classname'] for c in integration}):
    cs=[c for c in integration if c['classname']==cls]
    integration_rows.append((cls.split('.')[-1],status(cs),link('integracion','integracion.xml',label='JUnit')))
integration_body=f'''Esta sección distingue integración entre componentes dentro del proceso de prueba e integración extremo a extremo con infraestructura desplegada. La selección backend repite casos de Feature para observar flujos representativos; **no aumenta el total de pruebas únicas**.

{summary(integration)}

## Integración del backend

{table(['Suite seleccionada','Resultado real','Evidencia'],integration_rows)}

`EntregaAlimentaLecheTest` comprueba altas, actualización y anulación de entrega junto con stock; el escenario que primero paga una obligación produjo una excepción antes de verificar la anulación. `SincronizacionAutomaticaTest` conecta entrega, inventario, pago pendiente, análisis y recálculo: también comprueba reversión transaccional e idempotencia. `CalidadNoBloqueaProduccionTest` verifica que calidad no bloquee uso de leche. `IniciarProduccionTest` comprueba consumo de insumos. `CajaTest` integra pagos, ventas, compras y movimientos financieros. Los tests API y roles verifican el ingreso al caso de uso y sus restricciones.

El procedimiento fue ejecutar los tests existentes con un filtro explícito, fixtures sintéticos y `RefreshDatabase` sobre SQLite. No se inició un servidor web remoto ni se modificaron los casos para obtener aprobaciones.

## Integración de la app móvil

{table(['Interacción','Componentes reales','Parte simulada','Resultado / evidencia'],[
('SQLite ↔ repositorio','SQLDelight, JDBC en memoria, caché y cola','Datos de prueba y reloj controlado en ciertos casos','Aprobada en los casos host; ver mobile.md'),
('Cola ↔ sincronización','SyncManager y handlers','Disponibilidad de red y respuestas HTTP','AcopiadorOfflineTest, CalidadMuestreoSyncTest, VentasSyncTest aprobados'),
('HTTP ↔ procesamiento','Ktor Client y serialización','MockEngine sustituye Laravel','Respuesta, error y reconciliación controlados; no es API desplegada'),
('Idempotencia y reintento','Claves y estado de operaciones','Timeout, rechazo y conflicto preparados por el test','Los casos host aprobaron; validación equivalente de API en Feature tiene alcance separado')])}

## Recorrido de integración

<div class="ec-test-flow" role="img" aria-label="KMP, repositorio móvil, SQLite SQLDelight, cliente Ktor, API Laravel, casos de uso y MySQL">
<div class="ec-test-node">Aplicación KMP</div><span>↓</span>
<div class="ec-test-node">Repositorio móvil ↔ SQLite / SQLDelight</div><span>↓</span>
<div class="ec-test-node">Ktor Client</div><span>↓ HTTP</span>
<div class="ec-test-node">API Laravel</div><span>↓</span>
<div class="ec-test-node">Casos de uso</div><span>↓</span>
<div class="ec-test-node">MySQL</div>
</div>

**El diagrama representa la arquitectura objetivo.** En esta ejecución, el tramo móvil termina en MockEngine; el tramo backend empieza en peticiones internas Laravel y termina en SQLite en memoria. La flecha Ktor → API real → MySQL **no fue ejecutada**.

## Evidencias e interpretación

{evidence('integracion','integracion','Integración del backend',integration)}

La evidencia móvil se reutiliza desde [Android Host](mobile.md#evidencia-de-android-host); no se fabricó un segundo reporte de ejecución. Su log y reportes JUnit corresponden al mismo proceso.

## Cobertura pendiente y conclusión

La selección obtuvo {status(integration)}. Las interacciones aprobadas funcionan para sus escenarios en SQLite, mientras los casos no satisfactorios requieren revisar fecha de evento, asistencia y fecha de pago. Falta una prueba automática completa **App → API real → MySQL** en un entorno desechable, con dispositivo, autenticación real y conectividad variable. También faltan pruebas de concurrencia y recuperación frente a interrupciones físicas; no se les asigna un número de casos inventado.
'''
page('integracion',4,'Pruebas de integración','Integración entre componentes de EcoLact',integration_body)

executions=[('backend','smoke','20261029-qa01'),('backend','smoke',RUN),('backend','unitarias',RUN),('backend','funcionales',RUN),('mobile','mobile-android-host',RUN),('integracion','integracion',RUN)]
execution_rows=[]
for group,name,run in executions:
    m=json.loads((BASE/group/run/(name+'.json')).read_text())
    execution_rows.append((name+' / '+run,m['started'],m['duration_seconds'],m['exit_code'],link(group,name+'.log',run,'Log')+' · '+link(group,name+'.json',run,'Metadatos')))
evidence_body=f'''Este archivo reúne los registros originales, los reportes estructurados y sus capturas visuales. Las imágenes se obtienen automáticamente del HTML generado desde las salidas reales. **La fuente primaria es el log completo y el XML JUnit**, incluso cuando la captura no muestra todas las líneas.

{summary(all_cases)}

<div class="ec-test-card ec-test-not-run" markdown="1">

**No ejecutadas fuera del total:** 1 caso específico iOS; ejecución nativa de commonTest pendiente; flujo App → API desplegada → MySQL y pruebas de dispositivo sin conteo de casos definido. El caso MySQL descubierto se cuenta solo en “omitidas”, no nuevamente aquí.

</div>

## Entorno, fecha y revisión

{table(['Dato','Valor comprobado','Fuente'],[
('Fecha y zona','29/10/2026 · America/Lima (UTC−05:00)','Metadatos ISO de cada comando; logs internos pueden usar UTC'),
('Sistema operativo','Windows 11, amd64',link('resumen','gradle.log','20261029-qa01','Gradle')),
('PHP','8.3.16; extensiones pdo_sqlite y sqlite3 disponibles',link('resumen','php.log','20261029-qa01','PHP')+' · '+link('resumen','php-modules.log','20261029-qa01','Extensiones')),
('Laravel / Pest / PHPUnit','13.31.0 / 4.7.8 / 12.5.33',link('resumen','dependencias.log','20261029-qa01','Composer')+' · '+link('backend','smoke.log','20261029-qa01','Runner')),
('Composer','2.8.8',link('resumen','composer.log','20261029-qa01','Versión')),
('Java del lanzador','Temurin 17.0.17; daemon configurado Java 21 Azul',link('resumen','java.log','20261029-qa01','Java')+' · '+link('resumen','gradle.log','20261029-qa01','Gradle')),
('Gradle','9.1.0',link('resumen','gradle.log','20261029-qa01','Versión')),
('Commit base','75901337489dbe3c61bf5e95357ea365790ab565',link('resumen','git-commit.log','20261029-qa01','Git')),
('Árbol local','Contiene cambios previos; no equivale a un checkout limpio del commit',link('resumen','git-estado.log','20261029-qa01','Estado')+' · '+link('resumen','fuentes-sha256.json',label='Hashes de fuentes'))])}

## Registro de comandos

{table(['Ejecución','Inicio real UTC−05:00','Segundos de proceso','Salida','Registro completo'],execution_rows)}

El primer intento terminó antes de ejecutar tests por `Unknown option --no-interaction`; se conservó íntegramente. La opción se retiró y la prueba inicial aprobó. Un código de salida no cero del runner no se transforma en una cantidad ficticia de casos fallidos.

## Estadística verificable sin duplicados

{table(['Conjunto','Descubiertas','Ejecutadas','Aprobadas','Fallidas','Errores','Omitidas'],[(k,s['total'],s['executed'],s['passed'],s['failures'],s['errors'],s['skipped']) for k,s in stats.items() if k!='integration'])}

Solo se suman Unit, Feature y Android Host. Los {len(integration)} casos de la selección de integración y los 6 del smoke son subconjuntos repetidos y quedan fuera del total. El tiempo de las cards es la suma de tiempos de casos JUnit; la tabla anterior conserva el tiempo real de cada proceso, incluida compilación y arranque.

{link('resumen','resultados.json',label='Resumen JSON')} · {link('resumen','casos.csv',label='Todos los casos CSV')} · {link('resumen','casos.json',label='Todos los casos y errores JSON')}.

## Galería: Backend

{evidence('backend','unitarias','Backend: unitarias',unit)}

{evidence('backend','funcionales','Backend: funcionales',feature)}

## Galería: Móvil

{evidence('mobile','mobile-android-host','Móvil: Android Host',mobile)}

## Galería: Integración

{evidence('integracion','integracion','Integración: flujos seleccionados',integration)}

## Integridad, privacidad y reproducción

Cada comando tiene inicio, fin, directorio, salida y SHA-256 del log publicable. Los JUnit móviles se copiaron del resultado recién generado por Gradle; no se usaron reportes históricos. El inventario de fuentes identifica el árbol examinado. Una vista fallida incluyó una clave de mapas y tokens de formulario: se sustituyeron por `[REDACTADO]` en las copias publicables, sin truncar errores ni trazas. Los metadatos distinguen el hash original y el saneado; {link('resumen','redacciones.json',label='el registro de redacciones')} identifica los archivos afectados. Los originales se preservaron fuera de la documentación, en almacenamiento temporal local, y no se enlazan ni se publican. Los nombres y datos de escenarios provienen de fixtures; no se incluyeron archivos `.env` ni registros de una base real.

**Cambios posteriores detectados:** al comparar 1.456 fuentes con el inventario inicial, 15 archivos PHP de aplicación/pruebas tenían hashes distintos y fechas de modificación posteriores al cierre de la suite Feature (01:20:54, UTC−05:00). Esta implementación documental no los editó. Se conserva el {link('resumen','cambios-posteriores.json',label='listado de cambios y fechas')} y el inventario original; no se actualizaron hashes para ocultar la diferencia. No se atribuyen los resultados de la ejecución anterior a ese código posterior. Para validar la nueva revisión, debe repetirse la selección afectada en un árbol estable y guardarse como otra ejecución.

Las capturas son reportes HTML del navegador, no pantallas de terminal. La herramienta de automatización de escritorio tiene restringida la operación de terminales; los comandos se ejecutaron mediante el shell y se capturó su reporte derivado. No se solicitó captura manual.

## Observaciones y limitaciones

- Feature presenta {stats['feature']['failures']} fallos de aserción y {stats['feature']['errors']} errores. La lista completa y sus trazas se conservan en [Backend](backend.md#fallos-y-errores-conservados).
- MySQL se omite deliberadamente: no se habilitó una conexión a un servidor existente para probar creación y eliminación de bases.
- Los escenarios basados en `now()` pueden depender del día y hora. En esta ejecución hay eventos que cruzan medianoche y un pago anterior a su fecha permitida.
- Kotlin produjo advertencias `expect/actual`; la tarea host terminó con salida 0. Las advertencias no equivalen a tests omitidos.
- No se ejecutaron iOS, dispositivos físicos, servicios remotos reales, concurrencia ni pruebas de carga. No se calculó cobertura porcentual de código.
- Los tests de API se ejecutaron dentro de Laravel; los de sincronización móvil usan respuestas simuladas. No hubo end-to-end contra producción.

## Validación de la documentación

`mkdocs build --clean` terminó con salida **0**. Se conservó el aviso informativo global de Material y la página preexistente `assets/images/flujos/FUENTES_IMAGENES.md` fuera del navbar; no se alteraron las otras secciones. El primer wrapper tuvo un error al imprimir caracteres de consola después de una compilación exitosa; se corrigió la salida UTF-8 del wrapper y se conserva el registro de la compilación.

La revisión automatizada de las cinco páginas a **1440, 768 y 390 px** obtuvo **15 vistas sin fallos** de carga, enlaces locales, imágenes o desbordamiento global. Se comprobaron las seis pestañas superiores. Las tablas amplias admiten desplazamiento horizontal dentro de su contenedor. El resultado está en {link('resumen','validacion-responsive.json',label='el reporte de validación responsive')}; las capturas de revisión se conservan en `validacion-pruebas/20261029-qa02` fuera de los resultados de tests.

## Conclusión general de calidad

Se comprobaron satisfactoriamente {total['passed']} casos únicos bajo las condiciones descritas. Se observaron {total['failures']} fallos de aserción y {total['errors']} errores; {total['skipped']} casos quedaron omitidos por el runner. La evidencia respalda comportamientos concretos, pero **no certifica el sistema completo**. Se recomienda revisar las precondiciones temporales de los casos fallidos, resolver las discrepancias restantes, ejecutar iOS en macOS y preparar una integración extremo a extremo sobre infraestructura aislada antes de ampliar las conclusiones.
'''
page('evidencias',5,'Evidencias y resultados','Resultados de la ejecución de pruebas',evidence_body)
print(json.dumps(stats,indent=2))
