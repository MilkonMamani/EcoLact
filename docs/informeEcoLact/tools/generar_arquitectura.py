"""Fuentes editoriales de Arquitectura. No modifica código de EcoLact ni capturas existentes."""
from pathlib import Path
from html import escape
import json
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
REPO = ROOT.parents[1]
DOCS = ROOT / 'docs'
K = 'EcoLactMobile/shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/'
SQL = 'EcoLactMobile/shared/src/commonMain/sqldelight/pe/edu/upeu/ecolactmobile/core/database/'
W = 'EcoLactWeb/'
REV = subprocess.check_output(['git','rev-parse','HEAD'],cwd=REPO,text=True).strip()
BASE = f'https://github.com/MilkonMamani/EcoLact/blob/{REV}/'
CAPTURES = {}
PAGES = []
DIAGRAMS = {}

def capture(name, source, title, description, demonstrates, needle, end_needle=None):
    lines=(REPO/source).read_text(encoding='utf-8').splitlines()
    start=next(i+1 for i,line in enumerate(lines) if needle in line)
    end=min(len(lines),start+25)
    if end_needle:
        end=next(i+1 for i,line in enumerate(lines) if i+1>=start and end_needle in line)
    CAPTURES[name]=dict(file=name+'.png',source=source,title=title,description=description,demonstrates=demonstrates,start=start,end=end)

capture('backend-caso-uso',W+'app/Application/Acopio/RegistrarEntrega.php','Un caso de uso para registrar la entrega',
        'La operación recibe sus dependencias mediante contratos y coordina las reglas de acopio.',
        'La aplicación conoce los contratos del negocio; no necesita construir un modelo Eloquent para expresar la operación.', 'public function __construct(', ') {}')
capture('backend-repositorio',W+'app/Infrastructure/Persistence/Repositories/EloquentEntregaRepository.php','Adaptador de persistencia Eloquent',
        'El repositorio convierte una entidad de dominio en datos persistibles y devuelve una entidad.',
        'EloquentEntregaRepository implementa EntregaRepository. La tecnología de persistencia queda en el adaptador.', 'class EloquentEntregaRepository', 'public function actualizar')
capture('mvvm-viewmodel',K+'feature/acopiador/screens/RegistrarViewModel.kt','Estado y eventos del registro',
        'El ViewModel valida los litros, evita un doble guardado y captura la hora del evento antes de delegar.',
        'onGuardar coordina el evento de la interfaz y publica su resultado mediante StateFlow.', 'fun onGuardar()')
capture('mvvm-vista',K+'feature/acopiador/screens/RegistrarScreen.kt','Compose observa el estado',
        'La vista decide qué etapa mostrar a partir del estado emitido por el ViewModel.',
        'collectAsState conecta el estado observable con la pantalla de escaneo, formulario y resultado.', 'fun AcopiadorRegistrarScreen(', '// ---- 1.')
capture('kmp-configuracion','EcoLactMobile/shared/build.gradle.kts','Configuración del módulo compartido',
        'Gradle declara los destinos Android e iOS y organiza dependencias compartidas y específicas.',
        'La configuración habilita Kotlin Multiplatform; declarar un destino no acredita pruebas funcionales en ese dispositivo.', 'kotlin {', 'sourceSets {')
capture('offline-local-first',K+'core/ui/state/LocalFirstViewModel.kt','Implementación del enfoque Local-First',
        'La pantalla observa una fuente local y conserva los datos existentes cuando la red falla.',
        'combine prioriza la copia disponible y distingue carga, datos y error. La red actualiza esa copia; no reemplaza la pantalla por un error si ya hay información.', 'val state: StateFlow', 'init {')
capture('offline-repository',K+'feature/acopiador/AcopiadorRepository.kt','Guardar una entrega sin señal',
        'El repositorio elige entre enviar y conservar una operación pendiente con la misma clave local.',
        'Sin conexión se llama a encolarEntrega; un fallo de red también puede conservar la operación, sujeto a la autorización local de asistencia.', 'if (!isOnline()) return encolarEntrega', '/**')
capture('sync-manager',K+'core/sync/SyncManager.kt','Despacho y resultado de la sincronización',
        'El motor recorre las operaciones pendientes bajo un bloqueo y usa un manejador según su tipo.',
        'El resultado del envío cambia la fila a SYNCED, NEEDS_REVIEW o PENDING. MAX_INTENTOS_TRANSITORIOS limita los intentos.', 'suspend fun syncNow()')
capture('sync-queue',K+'core/sync/SyncQueueRepository.kt','Cola persistida y reenvíos',
        'El repositorio encapsula las consultas SQLDelight y conserva la clave de una operación al reintentar.',
        'enqueueWithId guarda tipo, payload y fechas; los métodos de transición actualizan el estado de la operación.', 'fun enqueueWithId', 'fun markPending')
capture('dominio-entrega',W+'app/Domain/Entrega/Entrega.php','La entidad protege sus invariantes',
        'Entrega expresa litros, actor, proveedor, fecha y estado como conceptos del negocio.',
        'Su constructor impide litros no positivos y un método de identificación vacío, sin depender de HTTP o Eloquent.', 'public function __construct(', 'public function id()')
capture('bd-sqldelight',SQL+'SyncQueue.sq','Esquema y operaciones SQL de la cola',
        'La tabla local guarda el payload, el estado, el identificador del servidor y el contador de intentos.',
        'Los pendientes son registros SQLite durables; no una lista mantenida solo en memoria.', 'CREATE TABLE sync_queue', 'selectAll:')
capture('api-ktor',K+'core/network/HttpClientFactory.kt','Cliente HTTP compartido',
        'La fábrica configura el motor de plataforma, JSON y la cabecera de la sesión actual.',
        'Ktor concentra configuración de transporte. La autorización Bearer se añade desde SessionManager.', 'fun create(')
capture('api-rutas',W+'routes/api.php','Rutas versionadas por rol',
        'La API agrupa endpoints del acopiador y protege sus escrituras con la comprobación de asistencia.',
        'El registro de entregas existe dentro del prefijo v1, bajo autenticación y restricciones de rol.', "->prefix('acopiador')", "Route::get('seguimiento-calidad'")
capture('seguridad-sanctum',W+'app/Http/Controllers/Api/AuthController.php','Inicio de sesión y emisión de token',
        'El controlador comprueba las credenciales y el acceso antes de crear el token personal de Sanctum.',
        'Hash::check, la comprobación de acceso y createToken forman parte del login real. La captura debe mostrar código, nunca valores de sesión.', '$user = User::where', '$token = $user->createToken')
capture('seguridad-roles',W+'app/Http/Middleware/RoleMiddleware.php','Autorización por nombre de rol',
        'El middleware decide si el usuario autenticado pertenece a uno de los roles permitidos.',
        'Una identidad válida no basta para acceder a cualquier área: se exige el rol autorizado y se responde 403 cuando falta.', 'public function handle', 'return $next')
capture('integracion-controller',W+'app/Http/Controllers/Api/Acopiador/EntregaController.php','La API reutiliza RegistrarEntrega',
        'El controlador transforma la solicitud validada en una llamada al caso de uso compartido con la web.',
        'Gate autoriza la acción, el actor se obtiene de la sesión y RegistrarEntrega ejecuta las reglas centrales.', 'public function store', '} catch')

def evidence(name):
    c=CAPTURES[name]
    image='../assets/images/arquitectura/codigo/'+c['file']
    return f'''<section class="ec-arch-evidence" markdown="1">
<div class="ec-arch-evidence-body" markdown="1">

### {c['title']}

{c['description']}

</div>
<div class="ec-arch-evidence-image" markdown="1">

[![Captura de código: {c['title']}]({image})]({image})

</div>
<div class="ec-arch-evidence-body" markdown="1">

**Qué demuestra.** {c['demonstrates']}

<div class="ec-arch-file" markdown="1">

Archivo de imagen: `{c['file']}` · [Abrir imagen]({image})

**Ubicación en GitHub:** [{c['source']}]({BASE}{c['source']}#L{c['start']}-L{c['end']})

Fragmento sugerido para la captura: líneas {c['start']}–{c['end']} de la revisión documentada.

</div>
</div>
</section>
'''

def diagram(name,title,source,explanation):
    if source.startswith('flowchart'):
        source+='\n  classDef default fill:#eaf3ff,stroke:#79a4df,color:#103b86,rx:7,ry:7;\n'
        decisions=list(dict.fromkeys(re.findall(r'\b(\w+)\{',source)))
        if decisions:
            source+='  classDef decision fill:#fff3df,stroke:#d7a052,color:#754c0c;\n  class '+','.join(decisions)+' decision;'
    DIAGRAMS[name]=source+'\n'
    return f'''## {title}

{explanation}

<div class="ec-arch-diagram" markdown="1">

![{title}](../assets/diagrams/arquitectura/{name}.svg)

<div class="ec-arch-controls" markdown="1">

[Ampliar diagrama](../assets/diagrams/arquitectura/{name}.svg)
[Fuente Mermaid](../assets/diagrams/arquitectura/{name}.mmd)

</div>
<details markdown="1">
<summary>Consultar la fuente editable</summary>

```text
{source}
```

</details>
</div>
'''

def cards(items,cls='ec-arch-grid'):
    text=f'<div class="{cls}" markdown="1">\n\n'
    for color,icon,title,body in items:
        text+=f'<div class="ec-arch-card {color}" markdown="1">\n\n:material-{icon}:\n\n### {title}\n\n{body}\n\n</div>\n\n'
    return text+'</div>\n'

def note(text,style=''):
    return f'<div class="ec-arch-note {style}" markdown="1">\n\n{text}\n\n</div>\n'

def table(text):
    return '<div class="ec-arch-table" markdown="1">\n\n'+text.strip()+'\n\n</div>\n'

def page(slug,title,subtitle,icon,tags,intro,body,evidences,sources):
    PAGES.append(dict(slug=slug,title=title,subtitle=subtitle,icon=icon,tags=tags,intro=intro,body=body,evidences=evidences,sources=sources))

page('index','Arquitectura de EcoLact','Un núcleo de negocio, dos formas de trabajar','sitemap',
     ['Cliente–servidor','Laravel + Blade','KMP','API REST','Persistencia local'],
     'EcoLact combina una aplicación Laravel con interfaz web y API, y una aplicación móvil Kotlin Multiplatform con almacenamiento local. El servidor decide qué operaciones son válidas; el teléfono puede conservar información y ciertas escrituras cuando la señal no acompaña el trabajo de campo.',
     diagram('contenedores','Vista C4 de contenedores', '''C4Container
title EcoLact: contenedores y comunicación
Person(admin, "Usuario web", "Administración y operación")
Person(campo, "Usuario móvil", "Acopio y otros roles implementados")
System_Boundary(ecolact, "Sistema EcoLact") {
  Container(laravel, "Aplicación web y backend", "Laravel / PHP / Blade", "Web y API REST con Sanctum; Application, Domain e Infrastructure")
  ContainerDb(central, "Persistencia central", "MySQL previsto", "Registros confirmados; conexión configurable")
  Container(movil, "Aplicación móvil", "KMP / Compose", "MVVM, repositorios, Ktor y sincronización")
  ContainerDb(local, "Persistencia del teléfono", "SQLite / SQLDelight", "Caché, sesión y cola local")
}
Rel(admin, laravel, "Usa vistas Blade", "HTTP / HTML")
Rel(campo, movil, "Consulta y registra")
Rel(movil, laravel, "Envía solicitudes por Ktor", "HTTP / JSON · /api/v1 · Bearer")
Rel(laravel, central, "Lee y escribe", "Eloquent / SQL")
Rel(movil, local, "Lee, guarda y observa", "SQLDelight")
UpdateRelStyle(laravel, central, $offsetY="-30")
UpdateRelStyle(movil, local, $offsetY="-30")
UpdateRelStyle(campo, movil, $offsetX="85", $offsetY="-10")
UpdateLayoutConfig($c4ShapeInRow="2", $c4BoundaryInRow="1")''',
     'La web Blade y la API son entradas del **mismo contenedor Laravel**; no son microservicios separados. Application, Domain e Infrastructure son capas de código dentro de él. El navegador recibe HTML; la app móvil intercambia JSON y mantiene su propia base local.')+
     '## Seis decisiones que organizan el sistema\n\n'+cards([
      ('blue','layers-triple','Laravel y separación de responsabilidades','**Problema:** evitar reglas dispersas en cada controlador. Los casos de uso coordinan el negocio y los contratos separan persistencia. [Ver backend](backend.md).'),
      ('','cellphone-link','KMP y presentación MVVM','**Problema:** compartir lógica sin mezclar cada vista con acceso a datos. Compose observa estado y los repositorios coordinan fuentes. [Ver arquitectura móvil](mobile.md).'),
      ('green','database','MySQL y SQLite con funciones distintas','**Problema:** combinar registros centrales con autonomía del dispositivo. La base local conserva copias y pendientes, no replica todo el servidor. [Ver persistencia](base-datos.md).'),
      ('orange','wifi-off','Offline-first y Local-First','**Problema:** la conectividad intermitente puede interrumpir el acopio rural. Datos descargados y operaciones compatibles permanecen disponibles. [Ver offline-first](offline-first.md).'),
      ('purple','sync','Cola y sincronización diferida','**Problema:** una operación guardada en campo debe llegar al servidor sin ocultar rechazos ni duplicar los casos protegidos. [Ver sincronización](sincronizacion.md).'),
      ('','shield-lock','REST y Sanctum','**Problema:** comunicar el cliente móvil con identidad y permisos verificables. Ktor usa la API; middleware y Policies controlan el acceso. [Ver API](api.md) y [seguridad](seguridad.md).')])+
     note('**Alcance de la explicación.** Las razones expuestas son una justificación técnica deducida de la implementación y del contexto de Huata, Puno. No se presenta como un acta histórica de decisiones del equipo. Se distingue lo implementado de lo configurado o todavía no verificado.')+
     '''## Cómo se conectan los patrones

Cliente–servidor describe la distribución de responsabilidades. Clean Architecture orienta la organización interna de Laravel. MVVM estructura el estado y los eventos de la interfaz móvil. REST define el intercambio HTTP/JSON y Sanctum participa en la autenticación de la API. SQLDelight permite conservar datos SQLite en el dispositivo; la cola conecta las escrituras locales con el servidor cuando es posible.

El backend es modular dentro de una aplicación Laravel. No se ha identificado un despliegue por microservicios ni replicación directa entre MySQL y SQLite. La app nunca accede a la base central por SQL: utiliza la API.
'''+note('**MySQL es el modelo central previsto, no una conexión activa comprobada.** El repositorio incluye configuración MySQL y un esquema SQL de referencia, pero `config/database.php` usa SQLite como valor por defecto si no se indica conexión. No se leyeron archivos de entorno ni se consultó una base en ejecución.','warning')+
     '''## Continuar la lectura

[Entidades y reglas de negocio](dominio.md) explica qué modela el núcleo. [Integración Web, Móvil y Backend](integracion.md) sigue una entrega a través de las dos interfaces.
''', ['kmp-configuracion'],
     [W+'app/Providers/AppServiceProvider.php',W+'routes/web.php',W+'routes/api.php',W+'config/database.php',W+'docs/database/EcoLact_BD_v1_MySQL.sql',K+'di/AppContainer.kt'])

page('backend','Clean Architecture en Laravel','Reglas de negocio detrás de contratos','layers-triple',
     ['Presentación','Application','Domain','Infrastructure'],
     'La organización de EcoLact aplica principios de Clean Architecture: el código que coordina una entrega depende de entidades y contratos, mientras Eloquent resuelve la persistencia. La frontera es útil para compartir operaciones entre web y móvil sin repetir sus reglas.',
     cards([
       ('blue','web','Presentación','Controladores, Form Requests, rutas y serialización. Interpretan HTTP, autorizan la acción y preparan HTML o JSON.'),
       ('green','cog-outline','Aplicación','Los casos de uso coordinan permisos de negocio, entidades, transacciones y operaciones relacionadas.'),
       ('purple','cube-outline','Dominio','Entidades, enumeraciones y contratos representan el negocio, sus estados y restricciones.'),
       ('orange','database','Infraestructura','Modelos y repositorios Eloquent, adaptación de datos y transacciones Laravel implementan los contratos.')], 'ec-arch-layers')+
     diagram('backend-ejecucion','Recorrido de una solicitud de entrega', '''flowchart TB
  H[Solicitud Web o API] --> R[Form Request: validar formato]
  R --> C[Controller: Gate y actor autenticado]
  C --> U[RegistrarEntrega: reglas y coordinación]
  U --> D[Entidad Entrega: invariantes]
  U --> T[TransactionManager: unidad transaccional]
  T --> E[EloquentEntregaRepository: persistir]
  E --> DB[(Base central)]
  T --> I[Ingreso de leche, pendiente y notificación]
  DB --> O[Entidad resultante]
  O --> S[Controller: respuesta HTML o JSON]''',
     '**Estas flechas representan llamadas y resultados durante la ejecución.** RegistrarEntrega valida proveedor, acopiador y alcance de la operación. Su transacción incluye entrega, ingreso de leche, sincronización del pago pendiente y notificación.')+
     diagram('backend-dependencias','Inversión de dependencias', '''flowchart TB
  C[Controlador HTTP] -->|usa| U[Application: RegistrarEntrega]
  U -->|depende del contrato| I[Domain: EntregaRepository]
  U -->|crea y utiliza| D[Domain: Entrega]
  E[Infrastructure: EloquentEntregaRepository] -->|implementa| I
  E -->|adapta| D
  E --> M[Modelo Eloquent]
  P[AppServiceProvider] -. vincula contrato e implementación .-> I
  P -. registra adaptador .-> E''',
     '**Aquí las flechas representan dependencias del código, no el orden temporal.** La infraestructura implementa el contrato definido hacia el núcleo. En ejecución el caso de uso recibe el adaptador concreto mediante la vinculación de AppServiceProvider.')+
     '''## RegistrarEntrega como ejemplo verificable

1. El controlador obtiene el actor desde la sesión, valida la entrada y autoriza la acción con Gate.
2. El caso de uso busca al proveedor por QR o DNI, comprueba estados y aplica las reglas de zona o el contexto del recorrido.
3. La entidad impide una entrega con litros no positivos. El caso de uso no confía solo en el formulario.
4. Los repositorios guardan los datos a través de sus contratos. `LaravelTransactionManager` adapta el contrato de transacción a `DB::transaction`.
5. El controlador web redirige al historial; el de API serializa la entidad en JSON. La operación de negocio es compartida.

## Qué problema resuelve la separación

Cambiar una respuesta JSON no exige reescribir las reglas de acopio. Una prueba puede sustituir un contrato por un doble, y la persistencia puede evolucionar sin obligar al caso de uso a crear modelos Eloquent. Son ventajas de diseño; esta revisión no ejecuta pruebas de negocio ni demuestra independencia absoluta de todo el framework.
'''+note('**Una aplicación de principios, no una pureza absoluta.** RegistrarEntrega utiliza el helper `now()` de Laravel. Otros casos de uso, como RegistrarAsistenciaQr, importan excepciones de Illuminate. Además, algunas consultas de presentación usan modelos directamente. Por ello se describe una separación pragmática de responsabilidades, no un núcleo completamente ajeno al framework.','warning'),
     ['backend-caso-uso','backend-repositorio'],
     [W+'app/Domain/Entrega/EntregaRepository.php',W+'app/Providers/AppServiceProvider.php',W+'app/Application/Shared/TransactionManager.php',W+'app/Infrastructure/Persistence/LaravelTransactionManager.php',W+'app/Http/Requests/Entrega/StoreEntregaRequest.php',W+'app/Application/Pago/RegistrarAsistenciaQr.php'])

page('mobile','Arquitectura móvil: KMP y MVVM','Interfaz reactiva y lógica compartida','cellphone-link',
     ['Kotlin Multiplatform','Compose','StateFlow','Repository'],
     'El módulo shared reúne la lógica común de la app: pantallas Compose, estado, repositorios, transporte y persistencia. Las piezas que necesitan funciones del sistema operativo se implementan en source sets específicos.',
     diagram('mobile-mvvm','MVVM aplicado al registro de una entrega', '''flowchart TB
  V[Vista Compose: RegistrarScreen] -->|eventos de usuario| VM[RegistrarViewModel: estado y eventos]
  VM -->|onGuardar| R[AcopiadorRepository]
  VM -. StateFlow y collectAsState .-> V
  R --> L[CacheRepository y SyncQueueRepository]
  L --> DB[(SQLite mediante SQLDelight)]
  R --> API[AcopiadorApi]
  API --> K[Ktor Client]
  K --> B[API REST Laravel]
  DB -. cambios observables .-> R''',
     'El diagrama separa presentación y acceso a datos. El repositorio coordina la copia local y la API; no debe interpretarse como que toda escritura se guarda localmente antes de intentar la red.')+
     cards([
       ('blue','monitor','Vista','`AcopiadorRegistrarScreen` observa `state` con `collectAsState()` y muestra ESCANEAR, FORMULARIO o RESULTADO.'),
       ('purple','swap-horizontal','ViewModel','`RegistrarViewModel` recibe eventos, valida los litros, captura una sola vez la hora del acopio y publica el resultado.'),
       ('green','source-branch','Repository','`AcopiadorRepository` identifica proveedores, combina consultas locales con pendientes y decide enviar o encolar.'),
       ('orange','database','Fuentes de datos','SQLDelight conserva caché y cola; Ktor envía solicitudes y traduce respuestas del backend.')])+
     '''## Qué ocurre al pulsar Guardar

La vista delega en `onGuardar()`. El ViewModel valida que los litros sean positivos y evita otro envío mientras está guardando. Captura la hora real y llama al repositorio. Una entrega confirmada permite actualizar consultas; una guardada sin red se muestra como pendiente; un rechazo informa el motivo sin presentarlo como éxito.

Este recorrido constituye una implementación MVVM por responsabilidades. **El ejemplo hereda de `ScreenModel`, no de `androidx.lifecycle.ViewModel`.** La clase propia crea un scope y lo cancela en `onDispose`; `rememberScreenModel` vincula su vida a la composición. No se atribuyen aquí `viewModelScope`, Hilt o Koin: `AppContainer` realiza la composición manual de dependencias.

## Código común y código específico
'''+table('''| Ubicación | Responsabilidad comprobada |
| --- | --- |
| `shared/src/commonMain` | Compose, ViewModel/ScreenModel, repositorios, modelos, Ktor y contratos de plataforma |
| `shared/src/androidMain` | Motor OkHttp, AndroidSqliteDriver y observación de redes Android |
| `shared/src/iosMain` | Motor Darwin, NativeSqliteDriver y adaptaciones iOS |
| `shared/build.gradle.kts` | Destinos Android, iosArm64 e iosSimulatorArm64; dependencias y base EcoLactDatabase |''')+
     note('**Portabilidad configurada no equivale a validación en iOS.** `ConnectivityObserver.ios.kt` está marcado como pendiente y devuelve siempre `true`. No ofrece todavía detección real de señal. Esta documentación no afirma que las funciones hayan sido compiladas ni probadas en iOS.','warning')+
     '''## Justificación técnica

KMP reduce la duplicación de estado, reglas de presentación y acceso a datos entre plataformas. Compose separa la descripción de pantalla de sus eventos. El intercambio por StateFlow permite actualizar la interfaz después de sincronizar sin reconstruir manualmente toda la navegación. Las adaptaciones por plataforma permiten usar motores HTTP y SQLite adecuados a cada sistema.
''', ['mvvm-viewmodel','mvvm-vista'],
     ['EcoLactMobile/shared/build.gradle.kts',K+'core/ui/state/ScreenModel.kt',K+'di/AppContainer.kt',K+'feature/acopiador/AcopiadorRepository.kt','EcoLactMobile/shared/src/iosMain/kotlin/pe/edu/upeu/ecolactmobile/core/sync/ConnectivityObserver.ios.kt'])

page('offline-first','Arquitectura offline-first','Continuidad de trabajo en Huata, Puno','wifi-off',
     ['Local-First','SQLite','Caché observable','Operaciones compatibles'],
     'El contexto de acopio rural de Huata, Puno, puede presentar conectividad limitada o intermitente. Una aplicación que exigiera una respuesta de internet para cada consulta o registro podría detener la ruta. EcoLact conserva información y admite determinadas operaciones pendientes para reducir esa dependencia.',
     cards([
       ('green','wifi','Con conexión','Consultar y actualizar información; enviar operaciones; sincronizar la copia local; recibir confirmaciones y errores del servidor. La señal disponible no garantiza que cada petición termine correctamente.'),
       ('orange','wifi-off','Sin conexión','Consultar datos descargados; registrar operaciones compatibles y autorizadas localmente; conservar pendientes en SQLite; continuar parte del trabajo y sincronizar después.')], 'ec-arch-offline')+
     diagram('offline-senal','Con señal / Sin señal', '''flowchart TB
  U[Usuario abre una consulta] --> L{¿Hay copia local?}
  L -->|Sí| V[Mostrar datos y fecha de actualización]
  L -->|No| N{¿Hay conexión?}
  N -->|No| E[Informar ausencia de datos locales]
  N -->|Sí| API[Consultar API]
  V --> C{¿Hay conexión?}
  C -->|Sí| API
  API --> S[Guardar respuesta en SQLite]
  S --> O[La fuente observable actualiza la vista]
  C -->|No| KEEP[Conservar la información disponible]
  W[Usuario registra una operación] --> P{¿Admite cola y permiso local?}
  P -->|Sí, sin red| Q[Guardar PENDING en SQLite]
  Q --> R[Enviar al recuperar conexión]
  P -->|No| A[Solicitar conexión o resolver autorización]''',
     'Las consultas y las escrituras tienen decisiones diferentes. Mantener una consulta visible no significa permitir cualquier modificación sin conexión.')+
     '''## Local-First: la interfaz observa una copia persistida

`CacheRepository` guarda la respuesta JSON y `cached_at`. Su método `observe()` usa consultas SQLDelight convertidas a Flow. `LocalFirstViewModel` combina esa fuente con conectividad, actualización y error para producir un `StateFlow<UiState<T>>`.

Si existe una copia, se muestra `Success` aunque falle la actualización remota. Si no existe y se descarga, se muestra `Loading`; si no se puede obtener, se muestra `Error`. El refresco escribe en SQLite y la observación actualiza la pantalla. Así la interrupción de red no borra información ya disponible.

El indicador `fromCache` del ViewModel se basa en estar sin conexión, no en demostrar que el dato es reciente. La fecha almacenada permite interpretar su antigüedad incluso cuando el teléfono vuelve a estar en línea.

## No todas las escrituras siguen el mismo orden

Para una entrega, `AcopiadorRepository` genera una clave local y conserva la hora del evento. **Con conexión intenta enviar primero**; si funciona, registra la operación local como sincronizada. Sin conexión o ante determinados fallos, la encola siempre que `puedeOperarSinConexion()` lo permita. Un rechazo de validación o permiso recibido en línea se muestra como rechazo.

Por tanto, Local-First describe especialmente la lectura de datos y el mantenimiento de la copia. No significa que todas las funciones escriban primero en SQLite ni que exista una transacción distribuida entre teléfono y servidor.

## Matriz de disponibilidad real
'''+table('''| Acción | Sin conexión | Condición o límite |
| --- | --- | --- |
| Ver proveedores, recorridos e historial | Con datos previamente guardados | No recupera detalles que nunca se descargaron |
| Registrar entrega | Puede quedar pendiente | Proveedor identificable y comprobación local de asistencia válida |
| Iniciar recorrido / marcar No entregó | Operaciones de cola | Reglas locales y autorización final del backend |
| Finalizar recorrido | No | Requiere red y que no queden registros del recorrido por enviar |
| Selección manual de muestra / captura de ticket | Hay soporte local o cola | El procesamiento remoto y la confirmación deben completarse al enviar |
| Sorteo de muestra | No | Depende del servidor y su propuesta firmada |
| Venta al contado | Puede quedar pendiente | Precio y stock conocidos; revalidación posterior |
| Venta con descuento de planilla | No | Necesita consultar saldo disponible |
| Primer inicio de sesión | No | Debe validar credenciales y recibir un token |''')+
     '''## Sesión, permisos y datos pendientes

Restaurar una sesión guardada permite recuperar el contexto local, pero no comprueba de nuevo en ese instante la vigencia del token, el rol o el estado de cuenta del servidor. El backend revisa autenticación, autorización, asistencia y reglas cuando recibe la operación. Un cambio de proveedor, stock, precio o permisos puede producir una revisión pendiente.

La conectividad Android usa redes validadas y, para ciertas redes no validadas, un sondeo HTTP al servidor. Una respuesta de ese sondeo prueba alcance, no aceptación de una operación. iOS conserva el observador provisional que siempre informa conexión.
'''+note('**Compromiso arquitectónico.** Se prioriza continuidad con datos locales y consistencia posterior de las operaciones aceptadas. No se promete actualidad permanente, disponibilidad universal ni sincronización garantizada con la app cerrada.','warning'),
     ['offline-local-first','offline-repository'],
     [K+'core/cache/CacheRepository.kt',K+'feature/auth/AuthRepository.kt',K+'feature/acopiador/AcopiadorRepository.kt',K+'feature/calidad/CalidadRepository.kt',K+'feature/ventas/VentasRepository.kt','EcoLactMobile/shared/src/androidMain/kotlin/pe/edu/upeu/ecolactmobile/core/sync/ConnectivityObserver.android.kt'])

page('sincronizacion','Sincronización de datos','Del pendiente local a la respuesta del servidor','sync',
     ['PENDING','SYNCING','SYNCED','NEEDS_REVIEW'],
     'La cola local conserva operaciones que deben comunicarse al backend. SyncManager las despacha por tipo, interpreta el resultado y deja visible qué fue confirmado, qué sigue pendiente y qué necesita revisión.',
     diagram('sync-secuencia','Una entrega sin conexión', '''sequenceDiagram
  participant A as App KMP
  participant D as SQLite
  participant S as SyncManager
  participant B as API Laravel
  participant M as MySQL
  A->>D: Guardar clave local, payload y hora (PENDING)
  Note over A,D: Registro local todavía no confirmado
  A->>S: Sincronizar al recuperar conectividad
  S->>D: Consultar pendientes en orden
  S->>D: SYNCING e incrementar intentos
  S->>B: Handler / Ktor: entrega + Idempotency-Key
  B->>B: Autenticar, autorizar y validar
  alt Operación aceptada
    B->>M: Transacción y persistencia
    M-->>B: Confirmación
    B-->>S: Resultado e identificador
    S->>D: SYNCED y server_id
    A->>B: Actualizar consultas
    B-->>A: Información confirmada
    A->>D: Actualizar copia local
  else Rechazo para revisión
    B-->>S: Validación / permiso / registro inexistente
    S->>D: NEEDS_REVIEW y motivo
  else Fallo transitorio
    S->>D: PENDING o NEEDS_REVIEW al alcanzar el límite
  end''',
     'El ejemplo corresponde al registro de una entrega por el acopiador. MySQL representa el destino central previsto; la conexión efectiva de Laravel depende de su configuración. El manejador utiliza Ktor, no una conexión directa desde SQLite a MySQL.')+
     diagram('sync-estados','Estados y transiciones comprobados', '''stateDiagram-v2
  [*] --> PENDING: Encolar
  PENDING --> SYNCING: Manejador disponible y canPush
  SYNCING --> SYNCED: Success y server_id
  SYNCING --> PENDING: Fallo transitorio antes del límite
  SYNCING --> NEEDS_REVIEW: Rechazo o límite alcanzado
  NEEDS_REVIEW --> PENDING: Corrección y retryFromReview
  SYNCING --> PENDING: Recuperación específica del rol al reiniciar
  SYNCED --> [*]: No entra en selectPending''',
     'La recuperación de un envío interrumpido está implementada por componentes concretos, como AcopiadorSync; no es una garantía universal añadida por el diagrama. Un pendiente sin manejador o cuyo `canPush` no permite enviarlo conserva su estado.')+
     cards([
       ('orange','clock-outline','PENDING','Guardado localmente, todavía debe enviarse. La selección SQL usa fecha de creación y rowid para desempatar.'),
       ('blue','sync','SYNCING','Envío en curso; `markSyncing` incrementa `attempt_count` antes de llamar al manejador.'),
       ('green','check-circle-outline','SYNCED','Respuesta aceptada y `server_id` conservado. No forma parte de la consulta de pendientes.'),
       ('purple','alert-circle-outline','NEEDS_REVIEW','Se conserva el payload y el motivo. No vuelve a enviarse automáticamente hasta que un flujo de revisión lo permita.')], 'ec-arch-sync')+
     '''## Detección, orden y reintentos

SyncManager usa un Mutex para evitar barridos simultáneos y registra manejadores según `operation_type`. Su observador genérico reacciona al retorno de conectividad. AcopiadorSync también sincroniza al entrar o volver la señal mientras su interfaz está activa, actualiza consultas después del envío y repite normalmente cada cinco minutos; los errores de actualización tienen espera creciente hasta ese intervalo.

El límite del motor es **cinco intentos transitorios**, contando el envío que alcanza el límite. No son cinco reintentos adicionales a un primer intento. La tabla incrementa un contador de intentos por operación; `retryFromReview` no lo reinicia. El manejador del acopiador clasifica 422, 403 y 404 como rechazos; los demás errores capturados pueden entrar como transitorios. No todos los manejadores tienen necesariamente la misma clasificación.

## Conflictos que requieren una decisión

Un pendiente puede corresponder a un proveedor desactivado, un recorrido cerrado o datos que ya no son válidos. Se muestra el motivo y se conserva la operación para revisión: **no existe una fusión automática universal de conflictos**. Para entregas, el repositorio permite corregir payload pendiente o rechazado, preservando la hora real; cambiar litros está limitado a diez minutos desde el evento. El descarte explícito está limitado a entregas rechazadas en ese flujo.

## Idempotencia: garantías por operación
'''+table('''| Operación | Protección comprobada |
| --- | --- |
| Entrega directa | Idempotency-Key llega a RegistrarEntrega; búsqueda por token, comprobación del usuario e índice único |
| Acopio de una parada | Clave local enviada como token de idempotencia y cabecera; delega en RegistrarEntrega |
| Inicio/cierre de producción, compra de insumos y venta | Middleware `idempotente` en esas rutas API; guarda respuesta por usuario y clave |
| Inicio de recorrido / No entregó | Comportamiento específico del caso de uso; no equivale a usar el middleware en todas las rutas |
| Guardar/finalizar análisis de calidad | El manejador no usa la misma cabecera; no se atribuye la garantía genérica |''')+
     note('**Repetir no es editar.** El middleware de idempotencia distingue método y ruta, pero no compara el cuerpo. Una operación ya confirmada devuelve su respuesta guardada; cambiar el payload con la misma clave no transforma la operación original. No hay atomicidad distribuida: si la respuesta se pierde, se depende de la protección del endpoint al reintentar.'),
     ['sync-manager','sync-queue'],
     [K+'core/sync/SyncManager.kt',SQL+'SyncQueue.sq',K+'feature/acopiador/AcopiadorSync.kt',K+'feature/acopiador/AcopiadorSyncHandler.kt',K+'feature/calidad/CalidadSyncHandler.kt',W+'app/Http/Middleware/IdempotenciaApi.php',W+'database/migrations/2026_09_19_090008_alter_entregas_add_recorrido_y_token.php'])

page('dominio','Entidades y reglas de negocio','El lenguaje del centro de acopio en el código','cube-outline',
     ['Entidades','Invariantes','Contratos','Estados'],
     'El dominio representa las personas, operaciones y restricciones del negocio lácteo. Una entidad tiene significado propio: una entrega no es solamente una fila de base de datos, y un pago no es solamente un importe mostrado en una pantalla.',
     diagram('dominio-acopio','Relaciones de acopio, calidad y pago', '''flowchart TB
  P[Proveedor] -->|realiza| E[Entrega]
  A[Acopiador] -->|registra cuando corresponde| E
  E -->|puede tener un análisis| C[Análisis de calidad]
  E -->|referenciada por| V[Valorización de entrega]
  C -. referencia opcional .-> V
  P -->|titular de| L[Liquidación]
  L -->|contiene| D[Detalle de liquidación]
  D -->|referencia| V
  L -->|referenciada por| PA[Pago de proveedor]''',
     'Las relaciones se sustentan en los identificadores de las entidades y en las restricciones de persistencia. Se incluyen Valorización y Detalle para no inventar una relación directa que omita cómo se enlazan entrega y liquidación. Una entrega puede existir sin análisis; `acopiadorId` es nullable.')+
     diagram('dominio-produccion','Relaciones de producción', '''flowchart TB
  P[Producto] -->|tiene versiones de| R[Receta]
  R -->|define ingredientes mediante| RD[Detalle de receta]
  RD -->|referencia| I[Insumo]
  L[Lote de producción] -->|guarda consumos en| LI[LoteProduccionInsumo]
  LI -->|referencia| I
  L -->|planifica y obtiene mediante| PD[ProduccionDetalle]
  PD -->|referencia| P
  PD -. conserva receta y versión utilizadas .-> R''',
     'La receta describe requerimientos; el lote conserva consumos y cantidades realmente obtenidas. La leche es un insumo que entra por acopio. No se dibuja una asignación directa de entregas concretas a lotes: la migración correspondiente elimina ese enlace antiguo si está vacío.')+
     '''## Responsabilidades de las entidades
'''+table('''| Concepto | Significado y regla relevante |
| --- | --- |
| Proveedor y Acopiador | Identidad, estado y contexto de participación en acopio |
| Entrega | Litros, fecha, actor, proveedor y estado; cantidad positiva |
| Análisis de calidad | Mediciones y resultado; al confirmar queda inmutable |
| Liquidación y Pago | Cálculo del periodo y registro del desembolso son conceptos distintos |
| Insumo y Receta | Existencias y requerimientos por producto y versión |
| Lote y Producto | Ejecución productiva y resultado disponible para ventas |''')+
     '''## Una regla que no depende de la pantalla

El constructor de `Entrega` rechaza litros menores o iguales a cero y un método de identificación vacío. Esa restricción se ejecuta al crear la entidad, aunque la petición provenga de web, app o una prueba. La entidad también expresa la ventana de edición de litros mediante `permiteEditarLitros()` y la constante de diez minutos.

La entidad no decide todo por sí sola. Comprobar que un proveedor está activo o que el acopiador puede operar en su zona exige consultar otros registros; `RegistrarEntrega` coordina esas reglas. Los Form Requests validan formato, las Policies autorizan acciones y los casos de uso orquestan la operación. Esta distribución evita atribuir toda la lógica a una sola clase.

El modelo de dominio tampoco es un esquema físico completo. Se omiten tablas auxiliares, consultas y detalles administrativos para mostrar únicamente relaciones relevantes y confirmadas. Los repositorios del dominio definen contratos; las entidades del ejemplo no heredan de Eloquent.
''', ['dominio-entrega'],
     [W+'app/Domain/Entrega/Entrega.php',W+'app/Domain/Calidad/AnalisisCalidad.php',W+'app/Domain/Pago/ValorizacionEntrega.php',W+'app/Domain/Pago/LiquidacionDetalle.php',W+'app/Domain/Pago/PagoProveedor.php',W+'app/Domain/Produccion/Receta.php',W+'app/Domain/Produccion/RecetaDetalle.php',W+'app/Domain/Produccion/ProduccionDetalle.php',W+'app/Domain/Produccion/LoteProduccionInsumo.php',W+'database/migrations/2026_09_21_100009_drop_lote_produccion_entregas_table.php'])

page('base-datos','Persistencia y bases de datos','Base central y copia local con responsabilidades distintas','database',
     ['MySQL previsto','SQLite móvil','SQLDelight','Transacciones'],
     'El servidor conserva las operaciones aceptadas del negocio; el teléfono conserva datos útiles para trabajar y operaciones que aún debe enviar. Esta separación responde a necesidades distintas y no implica dos bases de datos idénticas.',
     cards([
       ('blue','database','MySQL · Servidor','Modelo central previsto: registros y relaciones de negocio, operaciones confirmadas y consultas administrativas. Laravel accede mediante repositorios y consultas de infraestructura.'),
       ('green','cellphone','SQLite / SQLDelight · Móvil','Caché y datos descargados, sesión local, borradores y operaciones pendientes con sus estados. Los drivers abren `ecolact.db` en el dispositivo.')])+
     diagram('persistencia','Cómo viajan y se conservan los datos', '''flowchart TB
  UI[Compose: consultas y eventos] --> R[Repositorios móviles]
  R --> CACHE[(SQLite: copias y caché)]
  R --> QUEUE[(SQLite: sync_queue)]
  SES[SessionManager] --> SESSION[(SQLite: session)]
  QUEUE --> S[SyncManager y Ktor]
  S --> API[API Laravel: validación]
  API --> U[Casos de uso y repositorios Eloquent]
  U --> CENTRAL[(Base central: MySQL previsto)]
  CENTRAL --> JSON[Respuesta JSON de consultas]
  JSON --> CACHE
  CACHE -. Flow observable .-> UI''',
     'Los tres bloques SQLite representan responsabilidades dentro de la base local, no tres servidores. La copia se actualiza mediante respuestas API; no existe una flecha de replicación SQL directa entre bases.')+
     '''## Qué se almacena localmente
'''+table('''| Recurso SQLDelight | Contenido y función |
| --- | --- |
| `CacheEntry.sq` | Respuesta JSON por clave y fecha `cached_at` |
| `Entrega.sq`, `Proveedor.sq` y otros esquemas | Datos locales de entidades para consulta; su uso concreto depende del repositorio de cada funcionalidad |
| `SyncQueue.sq` | Clave local, tipo, payload, estado, fechas, server_id, error y attempt_count |
| `Session.sq` | Token y datos mínimos de la sesión restaurable |
| Esquemas de borradores de calidad y compras | Trabajo local aún no confirmado |''')+
     '''SQLDelight genera la API de consultas a partir de archivos `.sq`. La aplicación define `EcoLactDatabase` en Gradle y usa drivers diferentes por plataforma: AndroidSqliteDriver y NativeSqliteDriver. No se trata de un ORM Eloquent ejecutándose en el teléfono.

## Consistencia y responsabilidades

Una operación guardada en la cola es durable localmente, pero todavía no forma parte del registro central confirmado. Su identificador local y `server_id` cumplen funciones distintas. La caché puede estar desactualizada; una copia de una respuesta no sustituye a las reglas del servidor ni reserva definitivamente stock.

En Laravel, los repositorios transforman entidades y modelos. Las transacciones agrupan efectos relacionados, como entrega e ingreso de leche. Índices únicos apoyan restricciones como el token de entrega y la única selección de análisis por entrega. Las migraciones describen la evolución del modelo; un SQL de referencia anterior no sustituye revisar esa evolución.
'''+note('**Configuración comprobada, despliegue no inspeccionado.** Existe una conexión `mysql` y el archivo `EcoLact_BD_v1_MySQL.sql`; el valor por defecto de `DB_CONNECTION` en el código es `sqlite`. No se afirma qué motor está usando una instalación concreta. No se ejecutaron migraciones ni se leyeron archivos `.env`.','warning')+
     '''## Por qué dos persistencias

La base central permite reunir y relacionar operaciones de varios usuarios. SQLite evita perder disponibilidad local al desaparecer la señal. Separarlas evita exigir a un teléfono toda la información administrativa y permite que el servidor mantenga la decisión final sobre los registros aceptados. El coste es gestionar antigüedad de caché, pendientes, rechazos y actualizaciones de esquemas.
''', ['bd-sqldelight'],
     [W+'config/database.php',W+'docs/database/EcoLact_BD_v1_MySQL.sql',SQL+'CacheEntry.sq',SQL+'Entrega.sq',SQL+'Session.sq','EcoLactMobile/shared/build.gradle.kts','EcoLactMobile/shared/src/androidMain/kotlin/pe/edu/upeu/ecolactmobile/core/database/DatabaseDriverFactory.android.kt','EcoLactMobile/shared/src/iosMain/kotlin/pe/edu/upeu/ecolactmobile/core/database/DatabaseDriverFactory.ios.kt'])

page('api','Comunicación mediante API REST','Un contrato HTTP y JSON entre móvil y servidor','api',
     ['/api/v1','HTTP + JSON','Ktor Client','Roles'],
     'La app usa Ktor para comunicarse con rutas versionadas de Laravel. Los endpoints adaptan las solicitudes al modelo del backend y devuelven datos o errores; no exponen acceso directo a la base de datos.',
     diagram('api-recorrido','Recorrido de una solicitud API', '''flowchart TB
  A[Aplicación KMP] --> K[Ktor Client]
  K --> API[API REST: /api/v1]
  API --> M[Middleware: sesión, estado y rol]
  M --> R[Form Request y autorización Gate]
  R --> C[Controller]
  C --> U[Use Case]
  U --> RE[Contrato Repository]
  RE --> E[Adaptador Eloquent]
  E --> DB[(Persistencia central)]
  C -. respuesta JSON y estado HTTP .-> K''',
     'La cadena muestra responsabilidades. Los middleware adicionales, como asistencia o idempotencia, dependen del endpoint. Las rutas `v1` de `routes/api.php` se montan bajo `/api` desde la configuración de Laravel.')+
     '''## Organización y transporte

Existen grupos `admin`, `acopiador`, `calidad`, `planta`, `ventas` y `proveedor`, con restricciones de rol y estado de cuenta. Login está disponible antes de la autenticación; logout y me están bajo `auth:sanctum`. La existencia de un endpoint no demuestra una pantalla móvil equivalente.

`HttpClientFactory` configura el motor específico de plataforma, serialización JSON, URL base y Bearer desde SessionManager. Usa `expectSuccess = false`: las funciones de lectura de respuesta interpretan explícitamente el estado HTTP. Muchas respuestas se envuelven en `data`; otras operaciones devuelven solo `message`, por lo que existen `toEnvelopeData()` y `requireSuccess()`.

## Ejemplo real de contrato: registrar entrega

**Endpoint implementado:** `POST /api/v1/acopiador/entregas`. El siguiente ejemplo es ilustrativo: el DNI, la clave y el token son valores de ejemplo, no datos ni credenciales reales.

```http
POST /api/v1/acopiador/entregas
Authorization: Bearer TOKEN_DE_SESION
Content-Type: application/json
Accept: application/json
Idempotency-Key: ejemplo-operacion-local-001
```

```json
{
  "metodo_identificacion": "DNI",
  "identificador": "12345678",
  "litros": 12.5,
  "fecha_hora_evento": "2026-10-28T06:20:00-05:00",
  "observacion": "Ejemplo documental"
}
```

El identificador debe corresponder a un proveedor real y habilitado en una ejecución efectiva. El actor no se envía como `acopiador_user_id`: se obtiene del usuario autenticado. `StoreEntregaMovilRequest` toma la clave desde la cabecera y admite la hora del evento; la validación impone formato y límites antes del caso de uso.

Al aceptar, el controlador devuelve **201** con una entrega serializada dentro de `data`, que incluye su identificador, litros, estado y fechas. `fecha_hora` representa el evento de campo; `fecha_registro`, su llegada al servidor. El ejemplo no se ejecutó contra el sistema.

## Errores que la app puede interpretar
'''+table('''| HTTP | Interpretación en el cliente |
| --- | --- |
| 401 | No autorizado: clase Unauthorized |
| 403 | Prohibido; puede incluir el código ASISTENCIA_REQUERIDA |
| 404 | Recurso no encontrado |
| 409 | Conflicto |
| 422 | Validación, con mensaje y errores por campo cuando existen |
| 5xx | Error de servidor |
| Otros no exitosos | Error Unknown según el adaptador |''')+
     '''La API de entregas convierte DomainException e InvalidArgumentException a 422. No se generaliza ese mapeo a todas las rutas: el manejador de sincronización de cada funcionalidad determina qué resultado va a revisión o a reintento. Versionar y centralizar el transporte reduce acoplamiento, pero no elimina la necesidad de mantener compatibles los contratos JSON.
''', ['api-ktor','api-rutas'],
     [W+'routes/api.php',W+'bootstrap/app.php',W+'app/Http/Requests/Entrega/StoreEntregaMovilRequest.php',W+'app/Http/Controllers/Api/Acopiador/EntregaController.php',W+'app/Http/Controllers/Api/Acopiador/Concerns/SerializaEntregasAcopiador.php',K+'feature/acopiador/AcopiadorApi.kt',K+'core/network/ApiResponseHandling.kt'])

page('seguridad','Autenticación y seguridad','Identidad, permisos y contexto de cada operación','shield-lock',
     ['Sanctum','Bearer','Middleware','Policies'],
     'Autenticar responde quién realiza la petición; autorizar determina si esa persona puede ejecutar la acción sobre ese recurso. EcoLact aplica ambos controles y añade restricciones de estado de cuenta y asistencia en las rutas correspondientes.',
     diagram('seguridad-login','Secuencia de autenticación móvil', '''sequenceDiagram
  actor U as Usuario
  participant A as App / Ktor
  participant B as Laravel / Sanctum
  participant D as Base central
  participant L as SQLite local
  U->>A: Introducir credenciales
  A->>B: POST /api/v1/login
  B->>B: Límite de intentos y Hash::check
  B->>B: Verificar acceso de la cuenta
  alt Acceso válido
    B->>D: Crear token personal
    B-->>A: data: token y usuario
    A->>L: SessionManager.save
    A->>B: Solicitud con Authorization Bearer
    B->>B: auth:sanctum, rol, estado y Policy
    B-->>A: Resultado o rechazo
  else Credenciales o acceso inválidos
    B-->>A: Error de validación o acceso
  end
  U->>A: Cerrar sesión
  A->>B: POST /api/v1/logout
  B->>D: Eliminar token actual si la petición llega autorizada
  A->>L: Limpiar sesión local incluso si falla la red''',
     'La secuencia representa el cliente móvil con token personal. La web Laravel utiliza su sesión web; no se supone que todo acceso Blade use el mismo token Bearer del teléfono.')+
     cards([
       ('blue','account-check','Autenticación','Login verifica username y contraseña con Hash::check; Sanctum identifica al portador del token en rutas protegidas.'),
       ('purple','shield-account','Autorización','RoleMiddleware compara nombres de rol; Gate y Policies verifican el alcance de las acciones sobre los recursos.')])+
     '''## Controles implementados

El login aplica un límite de intentos por combinación de usuario e IP, consulta el usuario y valida la contraseña. `SesionWeb::errorAcceso` verifica que exista un rol válido y que la cuenta pueda acceder. Solo después se actualiza el último acceso y se crea un token Sanctum.

`auth:sanctum` protege los grupos autenticados. `RoleMiddleware` devuelve 401 sin identidad y 403 si no tiene un rol permitido. `EntregaPolicy`, por ejemplo, restringe al proveedor a sus entregas y al acopiador a su zona; Calidad tiene lectura de cualquier zona. El caso de uso conserva las reglas definitivas del negocio.

**El estado de cuenta no se resume en “inactivo = sin acceso”.** `EnsureUserIsActive` admite GET/HEAD para cuentas de trabajador o proveedor en modo de solo consulta y rechaza escrituras. Otras cuentas no operativas pierden acceso; el middleware puede revocar el token usado o cerrar la sesión web. Además, las rutas operativas protegidas comprueban asistencia.

## Cómo se guarda la sesión móvil actualmente

`SessionManager` conserva el token y datos de usuario en la tabla SQLDelight `session` y publica un StateFlow en memoria. `restore()` recupera esa fila al iniciar la app; `HttpClientFactory` toma `currentToken()` para construir la cabecera Bearer.

La columna del token es `TEXT`. En los drivers y en SessionManager revisados **no se implementa cifrado explícito del token ni integración con Keychain o Keystore**. No se atribuyen al proyecto esas protecciones ni una renovación automática de token que no se haya comprobado. La existencia de un token local tampoco demuestra que siga autorizado en el servidor.

## Cierre de sesión y límites

El logout API elimina `currentAccessToken()`. La app intenta el logout remoto y luego limpia su sesión local incluso si la petición falla. Por eso **cerrar sesión sin red no confirma la revocación remota**. Tampoco implica borrar toda la caché o la cola: `SessionManager.clear()` elimina la fila de sesión, no todas las tablas.

Sanctum resuelve identidad por token; no sustituye Policies, reglas de negocio ni validación de entradas. La configuración de HTTPS y las protecciones del despliegue no se verificaron en esta revisión. Los ejemplos y marcadores de la documentación no contienen secretos ni credenciales reales.
''', ['seguridad-sanctum','seguridad-roles'],
     [W+'app/Http/Middleware/EnsureUserIsActive.php',W+'app/Http/Support/SesionWeb.php',W+'app/Policies/EntregaPolicy.php',W+'app/Http/Middleware/RequiereAsistencia.php',K+'core/session/SessionManager.kt',SQL+'Session.sq',K+'feature/auth/AuthRepository.kt'])

page('integracion','Integración Web, Móvil y Backend','Dos interfaces, operaciones de negocio compartidas','lan-connect',
     ['Blade','API REST','Casos de uso','Consistencia diferida'],
     'La web y la app tienen formas de interacción diferentes, pero convergen en casos de uso del backend. La separación permite mantener reglas centrales mientras la interfaz móvil incorpora almacenamiento local para el trabajo de campo.',
     diagram('integracion-completa','Integración de extremo a extremo', '''flowchart TB
  W[Web: navegador y Blade] --> WC[Controlador Web]
  A[App KMP: Compose y estado] --> R[Repository móvil]
  R --> Q[(SQLite: caché y pendientes)]
  R --> K[Ktor: envío en línea]
  Q --> S[SyncManager: envío diferido]
  S --> API[API REST: controlador móvil]
  K --> API
  WC --> U[Application: casos de uso compartidos]
  API --> U
  U --> D[Domain: entidades y contratos]
  U --> E[Repositorios de infraestructura]
  E -. implementan contratos .-> D
  E --> DB[(Persistencia central)]
  DB --> C[Consultas del backend]
  C --> W
  C --> Q''',
     'Las flechas sólidas muestran interacción y circulación de información; la flecha discontinua señala una dependencia del código. La actualización de SQLite representa respuestas obtenidas mediante la API, no acceso directo de la base central al teléfono.')+
     '''## La misma entrega desde dos clientes

En la web, `Web/Acopiador/EntregaController::store` autoriza con Gate, toma datos validados y llama a `RegistrarEntrega`. En la API, `Api/Acopiador/EntregaController::store` utiliza el mismo caso de uso y añade el tratamiento de hora del evento e idempotencia móvil. El actor proviene de la sesión en ambos caminos.

La diferencia está en la adaptación: el controlador web redirige a una vista; el controlador API devuelve JSON y un estado HTTP. Los dos reutilizan las validaciones de negocio, los contratos de repositorio y los efectos transaccionales de la operación. No hace falta duplicar el cálculo de inventario en una pantalla para que una entrega lo actualice.

## Consistencia entre clientes

Las operaciones aceptadas convergen en la persistencia central y pueden consultarse desde ambas interfaces. La copia móvil se actualiza al refrescar o sincronizar; no se afirma que el navegador reciba una notificación en tiempo real ni que un pendiente local ya sea visible en la web.

Las transacciones protegen los cambios relacionados dentro del servidor. La cola protege la conservación de ciertas operaciones en el teléfono. Son dos ámbitos distintos: no hay una única transacción que abarque el dispositivo y Laravel. La idempotencia y la revisión de errores ayudan a reconciliar los resultados de envíos diferidos.

## Beneficios y límites de la arquitectura actual
'''+cards([
       ('green','source-branch','Reutilización','El ejemplo de acopio comparte el mismo caso de uso entre Web y API. Las adaptaciones HTTP pueden cambiar sin reescribir esa operación.'),
       ('blue','layers-triple','Responsabilidades claras','Vista, estado, repositorio, caso de uso y persistencia tienen funciones identificables y fuentes concretas para revisarlas.'),
       ('orange','wifi-off','Consistencia diferida','La caché puede estar antigua y el backend puede rechazar una operación pendiente. No existe fusión automática universal de conflictos.'),
       ('purple','cellphone-cog','Portabilidad con límites','Hay destinos Android e iOS, pero el observador iOS es provisional. La configuración compartida no acredita pruebas en ambas plataformas.')])+
     '''La separación del backend es pragmática: algunos casos de uso conservan dependencias de Laravel y algunos controladores consultan modelos para preparar información. La sesión móvil persiste el token sin una capa de cifrado explícita en el código revisado. La idempotencia depende de cada endpoint, y el motor central efectivamente desplegado no se verificó.

## Respuesta al contexto de EcoLact

En Huata, una arquitectura de consultas locales y envíos diferidos reduce interrupciones por conectividad sin trasladar al dispositivo la decisión definitiva sobre el negocio. Las reglas centralizadas mantienen un criterio común para acopio, calidad, inventario y pagos. Los pendientes visibles y las referencias de código permiten explicar tanto los beneficios como los límites del sistema implementado.
''', ['integracion-controller'],
     [W+'app/Http/Controllers/Web/Acopiador/EntregaController.php',W+'app/Http/Controllers/Api/Acopiador/EntregaController.php',W+'app/Application/Acopio/RegistrarEntrega.php',K+'feature/acopiador/AcopiadorRepository.kt',K+'core/sync/SyncManager.kt'])

for folder in ['arquitectura','assets/images/arquitectura/codigo','assets/diagrams/arquitectura']:
    (DOCS/folder).mkdir(parents=True,exist_ok=True)

for i,p in enumerate(PAGES):
    for source in p['sources']:
        assert (REPO/source).is_file(),source
    sources='\n'.join(f'- [{source}]({BASE}{source})' for source in dict.fromkeys(p['sources']))
    tags=''.join(f'<span>{escape(tag)}</span>' for tag in p['tags'])
    ev='\n'.join(evidence(name) for name in p['evidences'])
    links=[]
    if i>0:links.append(f'[← {PAGES[i-1]["title"]}]({PAGES[i-1]["slug"]}.md)')
    if p['slug']!='index':links.append('[Vista general](index.md)')
    if i+1<len(PAGES):links.append(f'[{PAGES[i+1]["title"]} →]({PAGES[i+1]["slug"]}.md)')
    text=f'''---
title: "{p['title']}"
description: "{p['subtitle']}. Arquitectura verificada en las fuentes de EcoLact."
---

<div class="ec-arquitectura" markdown="1">
<div class="ec-arch-banner" markdown="1">

:material-{p['icon']}: {i+1:02d} · {p['title']}

</div>
<div class="ec-arch-content" markdown="1">

<span class="ec-arch-kicker">EcoLact / Arquitectura de software</span>

# {p['title']}

<p class="ec-arch-subtitle">{p['subtitle']}</p>

{p['intro']}

<div class="ec-arch-tech">{tags}</div>

{p['body']}

## Evidencias de implementación

Las referencias identifican código revisado. El área de imagen está preparada para la captura manual del archivo indicado; el marcador visual no constituye una captura de la implementación.

{ev}

<details class="ec-arch-sources" markdown="1">
<summary>Fuentes adicionales y alcance de la revisión</summary>

Revisión estática del repositorio local: 28 de octubre de 2026. Enlaces a la revisión `{REV[:12]}`; la existencia de cada archivo y los fragmentos sugeridos se comprobaron localmente. No se ejecutaron servicios de negocio ni se verificó un despliegue.

{sources}

</details>

<div class="ec-arch-links" markdown="1">

{chr(10).join(link+chr(10) for link in links)}

</div>
</div>
</div>
'''
    (DOCS/'arquitectura'/f'{p["slug"]}.md').write_text(text,encoding='utf-8')

for name,source in DIAGRAMS.items():
    (DOCS/'assets/diagrams/arquitectura'/f'{name}.mmd').write_text(source,encoding='utf-8')

manifest={'revision':REV,'pages':[{'slug':p['slug'],'title':p['title'],'captures':p['evidences']} for p in PAGES],
          'diagrams':list(DIAGRAMS),'captures':list(CAPTURES.values()),'sources':sorted(set(s for p in PAGES for s in p['sources'])|set(c['source'] for c in CAPTURES.values()))}
(ROOT/'tools/arquitectura-manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2),encoding='utf-8')
print(f'{len(PAGES)} páginas; {len(DIAGRAMS)} diagramas; {len(CAPTURES)} posiciones de captura; {len(manifest["sources"])} fuentes verificadas.')
