"""Genera la guía editorial de Desarrollo. No ejecuta comandos del producto."""
from pathlib import Path
import json
import subprocess

ROOT=Path(__file__).resolve().parents[1]
REPO=ROOT.parents[1]
REV=subprocess.check_output(['git','rev-parse','HEAD'],cwd=REPO,text=True).strip()
GH=f'https://github.com/MilkonMamani/EcoLact/blob/{REV}/'
K='EcoLactMobile/shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/'
PAGES=[]
SOURCES=set()
CAPTURES={
 'entorno-desarrollo':('Entorno de desarrollo','El editor abierto en EcoLact y las herramientas instaladas, sin información privada.','VS Code y terminal'),
 'estructura-repositorio':('Vista del repositorio','Las carpetas EcoLactWeb, EcoLactMobile y docs desplegadas.','Explorador de VS Code'),
 'backend-dependencias':('Dependencias del backend','La finalización de composer install y la comprobación de requisitos PHP.','PowerShell'),
 'backend-servidor':('Servidores de desarrollo','Laravel y Vite ejecutándose en terminales separadas y sus direcciones locales.','Terminales de VS Code'),
 'android-studio':('Proyecto móvil abierto','EcoLactMobile importado y los módulos androidApp y shared visibles.','Android Studio'),
 'mobile-gradle':('Compilación Android','El resultado real de :androidApp:assembleDebug y la variante debug.','Android Studio o PowerShell'),
 'mobile-api':('Dirección de la API móvil','ApiConfig.kt con la dirección del entorno, sin cabeceras ni tokens.','Android Studio'),
 'mysql-base-datos':('Base de desarrollo','El esquema ecolactv1 y su listado de tablas, sin registros personales.','MySQL Workbench o cliente MySQL'),
 'mysql-migraciones':('Estado de las migraciones','La finalización de migrate y el listado migrate:status.','PowerShell'),
 'configuracion-entorno':('Configuración del backend','Un ejemplo saneado de las variables de entorno; contraseñas y claves ocultas.','Editor de código'),
 'configuracion-api':('Configuración del cliente','ApiConfig.kt y la configuración de Ktor, sin valores de sesión.','Android Studio'),
 'git-historial':('Historial de trabajo','La rama y los commits de una modificación real.','VS Code o GitHub'),
}

def block(cls,body): return f'<div class="{cls}" markdown="1">\n\n{body.strip()}\n\n</div>\n\n'
def code(text,lang='powershell'): return block('ec-dev-command',f'```{lang}\n{text.strip()}\n```')
def note(text,warn=False): return block('ec-dev-result'+(' warning' if warn else ''),text)
def table(rows,heads=('Comando','¿Para qué sirve?')):
 return block('ec-dev-command-table','| '+' | '.join(heads)+' |\n| '+' | '.join(['---']*len(heads))+' |\n'+'\n'.join('| '+' | '.join(r)+' |' for r in rows))
def cards(items,cls='ec-dev-grid'):
 return block(cls,''.join(block('ec-dev-card '+color,f':material-{icon}:\n\n### {title}\n\n{text}') for title,text,icon,color in items))
def flow(items):
 return block('ec-dev-flow'+(' five' if len(items)==5 else ''),''.join(block('ec-dev-flow-item',f':material-{icon}:\n\n{title}') for title,icon in items))
def capture(name):
 title,description,tool=CAPTURES[name]
 return block('ec-dev-capture',f'### {title}\n\n[![{title}: espacio reservado para una captura real](../assets/images/desarrollo/{name}.png)](../assets/images/desarrollo/{name}.png)\n\n**Archivo:** `{name}.png`\n\n**Qué debe mostrar:** {description}\n\n**Herramienta:** {tool}.\n\n<div class="ec-dev-meta" markdown="1">\n\nReemplaza el PNG en `docs/assets/images/desarrollo/` con el mismo nombre y vuelve a generar el sitio. No es necesario editar esta página. Mientras se conserva el marcador, no constituye evidencia de ejecución.\n\n</div>')
def step(n,title,purpose,commands,explanation,result,check,lang='powershell'):
 return block('ec-dev-step',f'### Paso {n}. {title}\n\n{purpose}\n\n'+code(commands,lang)+f'**Qué hace:** {explanation}\n\n'+note(f'**Resultado esperado:** {result}\n\n**Comprobación:** {check}'))
DIAGRAMS={
 'servidores':('Laravel y Vite durante el desarrollo','''flowchart TB
 B[Navegador] -->|HTML y acciones| L[Laravel · puerto 8000]
 B -->|CSS, JavaScript y recarga| V[Vite · puerto indicado en terminal]
 L --> D[(MySQL · desarrollo)]
 A[App móvil] -->|JSON · /api/v1/| L
'''),
 'conexion-mobile':('Conectar Android con Laravel','''flowchart TB
 A[App Android] --> K[Ktor · ApiConfig.baseUrl]
 K --> E{Entorno de ejecución}
 E -->|Emulador| M[10.0.2.2:8000/api/v1/]
 E -->|Teléfono físico| F[IP real del equipo · misma red]
 E -->|Remoto| R[Dominio HTTPS del servidor]
 M --> L[Laravel · rutas /api/v1]
 F --> L
 R --> L
'''),
 'mysql':('Aplicación Laravel, conexión y esquema','''flowchart TB
 L[Aplicación Laravel] --> C[config/database.php + variables DB_*]
 C --> P[Conexión MySQL · PDO]
 P --> D[(Esquema ecolactv1)]
 M[Migraciones · estructura] --> D
 S[Seeders · datos iniciales] --> D
'''),
}
def diagram(name):
 title,source=DIAGRAMS[name]
 return block('ec-dev-diagram',f'![{title}](../assets/diagrams/desarrollo/{name}.svg)\n\n[Ampliar diagrama](../assets/diagrams/desarrollo/{name}.svg) · [Fuente Mermaid](../assets/diagrams/desarrollo/{name}.mmd)')
def page(slug,banner,title,icon,intro,body,sources,captures):
 for p in sources:
  assert (REPO/p).exists(),p
  SOURCES.add(p)
 footer=block('ec-dev-sources','<details markdown="1">\n<summary>Fuentes verificables del repositorio</summary>\n\n'+ '\n'.join(f'- [{p}]({GH.replace("/blob/", "/tree/") if (REPO/p).is_dir() else GH}{p})' for p in sources)+'\n\nRevisión de referencia: `'+REV[:12]+'`. Los procedimientos se contrastaron con estas fuentes; los comandos de instalación, migración y compilación del producto no se ejecutaron para elaborar esta guía.\n\n</details>')
 PAGES.append(dict(slug=slug,title=title,banner=banner,icon=icon,intro=intro,body=body+''.join(capture(c) for c in captures)+footer,captures=captures))

page('index','1. Empezar: requisitos y preparación','Comenzar a desarrollar EcoLact','rocket-launch',
 'Esta guía prepara el entorno para trabajar con Laravel, Kotlin Multiplatform y la documentación. El objetivo es llegar a una web accesible y una app Android capaz de comunicarse con el mismo backend, antes de modificar funcionalidades.',
 '## De la descarga a una prueba real\n\n'+flow([('Obtener código','source-repository'),('Configurar herramientas','tools'),('Ejecutar proyectos','play-circle'),('Desarrollar y probar','test-tube')])+
 step(1,'Obtener una copia del repositorio','Abre PowerShell en la carpeta donde quieras guardar una **copia nueva**. Git descarga el historial y permite trabajar en una rama propia. Si ya tienes EcoLact, abre esa carpeta; no clones encima de ella.',
 'git clone https://github.com/MilkonMamani/EcoLact.git\ncd EcoLact\ngit status','clone crea EcoLact; cd establece el directorio de trabajo y status muestra la rama y los cambios locales.','La raíz contiene EcoLactWeb, EcoLactMobile y docs.','Comprueba esas tres carpetas en el explorador y que git status reconoce el repositorio.')+
 '## Herramientas y compatibilidad\n\nLas versiones siguientes provienen de los manifiestos y del bloqueo de dependencias revisados, no de la versión instalada en este equipo.\n\n'+cards([
 ('PHP','`composer.json` declara **^8.3** y Laravel **^13.17**. El intérprete y sus extensiones deben satisfacer también `composer.lock`; verifica con `composer check-platform-reqs`.','language-php','blue'),
 ('Composer','Instala las bibliotecas PHP y respeta el archivo de bloqueo con `composer install`. No se fija aquí una versión de Composer que el proyecto no declara.','package-variant',''),
 ('Node.js y npm','Vite 8.2.2 y laravel-vite-plugin 3.2.0 bloqueados exigen `^20.19.0` o `>=22.12.0`; la dependencia opcional multiplex exige `>=22.13.0`. Node **22.13.0 o superior dentro de 22.x** satisface estos rangos; no significa que sea la única versión válida. npm no tiene versión fijada.','nodejs','green'),
 ('MySQL','Servicio de base de datos para la guía local. `.env.example` selecciona MySQL y `ecolactv1`; no fija una versión del servidor. Necesitas un usuario propio con permisos sobre la base de desarrollo.','database',''),
 ('Git','Obtiene el repositorio, permite revisar diferencias y registra cambios. Confirma su disponibilidad con `git --version`.','git',''),
 ('Android Studio','Abre EcoLactMobile, sincroniza Gradle y administra SDK y dispositivos. Selecciona una versión compatible con **AGP 9.0.1**.','android-studio','blue'),
 ('JDK de Gradle','AGP 9.0 requiere **JDK 17** como mínimo. JVM 11 en los módulos define el bytecode objetivo: **no** autoriza ejecutar Gradle con JDK 11. Selecciona JDK 17 para Gradle y comprueba el valor efectivo con el Wrapper.','language-java','orange'),
 ('Gradle Wrapper','Incluido: **Gradle 9.1.0**. Usa `gradlew.bat` en Windows; no hace falta instalar otro Gradle global. SDK Android: compile/target **36**, minSdk **24**.','elephant',''),
 ('Navegador','Permite revisar la web, la consola y las solicitudes. La app móvil usa la API, mientras el navegador carga la interfaz Blade y los recursos de Vite.','web',''),
 ('Editor de código','VS Code puede usarse para PHP, configuración y Markdown; Android Studio facilita el trabajo móvil. Conserva los formatos y archivos de bloqueo del repositorio.','microsoft-visual-studio-code','purple'),
 ],'ec-dev-tools')+
 'La distinción entre JDK y bytecode se apoya en la [compatibilidad oficial de AGP 9.0](https://developer.android.com/build/releases/agp-9-0-0-release-notes). El catálogo declara Kotlin 2.4.10 y Compose Multiplatform 1.11.1; se debe comprobar que todas las dependencias resuelvan al sincronizar, sin cambiar versiones para ocultar un error.\n\n'+
 '## Comprobar las herramientas\n\n'+code('php --version\ncomposer --version\nnode --version\nnpm --version\ngit --version\njava -version')+
 note('**Resultado esperado:** cada programa muestra su versión. Si uno no se reconoce, revisa su instalación y PATH y abre una terminal nueva. `java -version` describe la terminal; Android Studio puede usar otro JDK de Gradle.')+
 '## Windows y macOS\n\nEn Windows usa PowerShell, rutas entre comillas si contienen espacios, Android Studio con SDK 36 y un emulador o teléfono con depuración USB. MySQL y Laravel se ejecutan como procesos distintos.\n\nEn macOS, la copia inicial del entorno se hace con `cp .env.example .env` y el Wrapper con `./gradlew`. **iOS necesita macOS y Xcode**, el proyecto `iosApp/iosApp.xcodeproj` y una configuración de firma cuando corresponda. Declarar destinos iOS no demuestra que la app esté completamente validada.\n\n'+
 '## Ruta de lectura\n\nContinúa con [la estructura](repositorio.md), prepara [Laravel](backend.md) y [MySQL](base-datos.md), y luego configura [Android](mobile.md). Revisa [las variables](configuracion.md) antes de conectar los proyectos y [Git](git.md) antes de compartir cambios.\n',
 ['EcoLactWeb/composer.json','EcoLactWeb/composer.lock','EcoLactWeb/package.json','EcoLactWeb/package-lock.json','EcoLactWeb/.env.example','EcoLactMobile/gradle/libs.versions.toml','EcoLactMobile/gradle/wrapper/gradle-wrapper.properties','EcoLactMobile/androidApp/build.gradle.kts'],['entorno-desarrollo'])

page('repositorio','2. Estructura del repositorio','Organización del proyecto','folder-multiple',
 'EcoLact reúne tres áreas dentro de un mismo repositorio. Ubicar cada responsabilidad antes de editar evita mezclar reglas de negocio, interfaces y documentación.',
 '## Árbol de trabajo\n\nVista resumida de directorios comprobados; se omiten carpetas generadas y auxiliares.\n\n'+block('ec-dev-folder-tree','''```text
EcoLact/
├── EcoLactWeb/
│   ├── app/
│   │   ├── Application/
│   │   ├── Domain/
│   │   ├── Http/
│   │   └── Infrastructure/
│   ├── routes/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── resources/
│   ├── tests/
│   ├── composer.json
│   ├── package.json
│   └── .env.example
├── EcoLactMobile/
│   ├── androidApp/
│   ├── iosApp/
│   ├── shared/src/
│   │   ├── commonMain/
│   │   ├── androidMain/
│   │   └── iosMain/
│   ├── gradle/
│   ├── gradlew.bat
│   ├── build.gradle.kts
│   └── settings.gradle.kts
└── docs/informeEcoLact/
    ├── mkdocs.yml
    └── docs/
        ├── desarrollo/
        ├── assets/
        └── stylesheets/
```''')+
 cards([('Backend Laravel','`app/` contiene dominio, casos de uso, adaptadores y controladores; `routes/` registra entradas web y API. `resources/` contiene las vistas y recursos frontend; `tests/` conserva pruebas.','server','blue'),('Aplicación KMP','`shared/` comparte lógica y UI. `androidApp/` es la entrada Android; `iosApp/` contiene el proyecto Xcode. `settings.gradle.kts` incluye los módulos Android y shared.','cellphone-link','green'),('Documentación','`mkdocs.yml` define navegación y extensiones. Se edita `docs/`, mientras `site/` es salida de compilación. No se crea otro proyecto MkDocs.','book-open-page-variant','purple')])+
 '## ¿Dónde modificar una funcionalidad?\n\n'+table([
 ('Modificar una regla de negocio','`EcoLactWeb/app/Domain/` o `EcoLactWeb/app/Application/`.'),
 ('Modificar un endpoint','`EcoLactWeb/routes/api.php` y `EcoLactWeb/app/Http/Controllers/Api/`.'),
 ('Modificar una vista web','`EcoLactWeb/resources/views/` y el controlador correspondiente.'),
 ('Modificar una pantalla móvil','`EcoLactMobile/shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/feature/`.'),
 ('Modificar persistencia local','Archivos `.sq` de `shared/src/commonMain/sqldelight/` y repositorios de `core/database/` o cada funcionalidad.'),
 ('Modificar documentación','`docs/informeEcoLact/docs/`; estilos específicos en `docs/stylesheets/`.'),
 ],('Necesidad','Dónde buscar'))+
 note('**Ejemplo práctico:** para cambiar una entrega, sigue la pantalla móvil hasta su repositorio, identifica el endpoint y revisa `RegistrarEntrega.php`. La regla compartida por web y API debe mantenerse en el lugar donde realmente se aplica; consulta [Arquitectura](../arquitectura/backend.md).')+
 '## Verificar que estás en la carpeta correcta\n\n'+code('Get-Location\nGet-ChildItem -Name')+
 '**Resultado esperado:** en la raíz aparecen los tres proyectos. Ejecuta Composer y npm desde `EcoLactWeb`, el Wrapper desde `EcoLactMobile` y MkDocs desde `docs/informeEcoLact`. Cambiar de carpeta evita buscar manifiestos o tareas en el proyecto equivocado.\n',
 ['EcoLactWeb/app/Domain','EcoLactWeb/app/Application','EcoLactWeb/app/Http/Controllers/Api','EcoLactWeb/resources/views','EcoLactWeb/tests','EcoLactMobile/settings.gradle.kts','EcoLactMobile/shared/src/commonMain/sqldelight','EcoLactMobile/shared/src/androidMain','EcoLactMobile/shared/src/iosMain','EcoLactMobile/iosApp/iosApp.xcodeproj','docs/informeEcoLact/mkdocs.yml'],['estructura-repositorio'])

db_example='DB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=ecolactv1\nDB_USERNAME=usuario_local\nDB_PASSWORD=valor_privado'
page('backend','3. Configurar y ejecutar backend','Backend Laravel 13','server',
 'El backend atiende la interfaz web y la API móvil. El procedimiento parte de una copia nueva y una base de desarrollo: instala bibliotecas, configura el entorno y levanta los procesos necesarios.',
 step(1,'Instalar dependencias','Desde la raíz de EcoLact, entra al proyecto PHP. Composer resuelve el autoload y las bibliotecas que necesita Laravel.',
 'cd EcoLactWeb\ncomposer install\ncomposer check-platform-reqs','cd cambia el directorio; install usa composer.lock cuando existe. La comprobación contrasta PHP y extensiones con los paquetes instalados.','Se crea vendor/ y los requisitos de plataforma se cumplen.','Comprueba vendor/autoload.php y revisa que Composer no indique extensiones o versiones incompatibles.')+
 capture('backend-dependencias')+
 step(2,'Crear el entorno local','La plantilla contiene nombres y valores de ejemplo. Cópiala **solo si .env no existe**; si ya existe, revisa la configuración propia sin sobrescribirla. Genera la clave únicamente para una instalación nueva.',
 'if (-not (Test-Path .env)) { Copy-Item .env.example .env }\n# Solo en una instalación nueva, sin APP_KEY configurada:\nphp artisan key:generate','La copia prepara las variables locales; key:generate establece la clave criptográfica usada por Laravel. No es la contraseña de MySQL ni un token de Sanctum.','Existe .env y APP_KEY tiene un valor generado localmente.','Comprueba su presencia en el editor sin mostrarla en capturas. No regeneres una clave de un entorno en uso: puede invalidar información cifrada.')+
 note('`.env` está ignorado por Git en EcoLactWeb. Configura credenciales propias y no lo compartas; la guía solo muestra valores ficticios. Verifica también `APP_ENV=local` y usa `APP_DEBUG=true` exclusivamente en desarrollo.')+
 step(3,'Preparar la conexión MySQL','Inicia MySQL y crea una base vacía de desarrollo siguiendo [Base de datos](base-datos.md). Después ajusta estas variables en .env; sustituye los valores ficticios.',db_example,
 'DB_CONNECTION selecciona el motor; host, puerto y nombre identifican el servicio y el esquema. Usuario y contraseña pertenecen a tu instalación.','Laravel apunta a la base ecolactv1 del entorno elegido.','Confirma en el cliente MySQL el servidor y esquema seleccionados antes de migrar. Si había configuración cacheada, ejecuta php artisan config:clear.','dotenv')+
 step(4,'Aplicar migraciones','Con la conexión confirmada, crea o actualiza la estructura versionada. Estas órdenes se ejecutan desde EcoLactWeb.',
 'php artisan config:clear\nphp artisan migrate\nphp artisan migrate:status','config:clear descarta configuración cacheada; migrate aplica migraciones pendientes y status lista su estado.','Las migraciones de esta revisión figuran aplicadas.','Revisa migrate:status y las tablas en MySQL. Si hay un fallo, identifica la migración y causa antes de repetir; no borres la base para ocultarlo.')+
 step(5,'Cargar datos iniciales','Revisa DatabaseSeeder y configura ECOLACT_ADMIN_USERNAME y ECOLACT_ADMIN_PASSWORD con valores locales propios antes de continuar. Los seeders no representan una carga completa de datos operativos.',
 'php artisan db:seed','Invoca los siete seeders registrados: roles, administrador, parámetros de calidad, precio de leche, reglas de pago, zonas y delimitaciones iniciales.','Los catálogos y el usuario inicial están disponibles.','Consulta las tablas de catálogo y prueba el acceso con tus credenciales. Revisa el comportamiento de actualización descrito en [Base de datos](base-datos.md).')+
 note('**Reejecutar seeders modifica datos.** AdminUserSeeder usa updateOrInsert y vuelve a escribir la contraseña del usuario elegido; también activa el cambio obligatorio de contraseña. No ejecutes db:seed indiscriminadamente en una base compartida.',True)+
 step(6,'Iniciar Laravel','Abre una terminal en EcoLactWeb y mantenla activa. El servidor de desarrollo atiende rutas web y API.',
 'php artisan serve','Inicia el servidor local; normalmente anuncia http://127.0.0.1:8000. Usa siempre la dirección realmente mostrada.','La web responde en el navegador y la terminal permanece ejecutándose.','Abre la dirección anunciada. Si solicita recursos frontend o la página aparece sin estilos, completa el paso de Vite.')+
 step(7,'Ejecutar Vite','En otra terminal ubicada en EcoLactWeb, instala las dependencias JavaScript y arranca el servidor de recursos.',
 'npm install\nnpm run dev','install prepara node_modules; dev ejecuta vite según package.json. Vite sirve CSS/JavaScript y recarga cambios; Laravel sigue atendiendo la aplicación.','Ambos procesos permanecen activos y la web carga sus recursos.','Recarga la web y comprueba en las herramientas del navegador que CSS y JavaScript no fallan. La terminal de Vite anuncia su propio puerto.')+
 diagram('servidores')+
 '## Comandos de referencia\n\n'+table([
 ('`cd EcoLactWeb`','Entrar al proyecto PHP desde la raíz.'),('`composer install`','Instalar las dependencias PHP bloqueadas.'),('`Copy-Item .env.example .env`','Crear la configuración inicial, solo si .env no existe.'),('`php artisan key:generate`','Generar APP_KEY en una instalación nueva.'),('`php artisan migrate`','Aplicar cambios de estructura pendientes.'),('`php artisan db:seed`','Ejecutar los seeders registrados y sus actualizaciones.'),('`php artisan serve`','Atender la aplicación en desarrollo.'),('`npm install`','Instalar dependencias frontend.'),('`npm run dev`','Iniciar Vite y su recarga de recursos.'),('`npm run build`','Generar los recursos compilados, sin dejar Vite activo.'),('`composer run dev`','Alternativa definida en composer.json: coordina servidor, queue:listen y Vite. No usar simultáneamente con los procesos ya iniciados.')])+
 note('El script `composer run setup` existe, pero encadena generación de clave y migraciones con `--force`. Para la primera configuración se documentan los pasos separados, de modo que puedas revisar el entorno antes de modificarlo.')+
 '## Errores frecuentes y comprobaciones\n\n'+table([
 ('Composer no reconocido','Revisa instalación y PATH; abre otra terminal y ejecuta composer --version.'),('Base no disponible','Comprueba servicio, host, puerto, usuario y existencia del esquema; no confundir MySQL con el servidor web.'),('Falta .env','Crea la copia inicial y APP_KEY. Si ya existe, no lo reemplaces para resolver otro error.'),('Recursos frontend faltantes','Ejecuta npm install y npm run dev desde EcoLactWeb, o genera npm run build.'),('Puerto ocupado','Identifica el proceso o usa php artisan serve --port=8001. Actualiza la URL móvil para ese puerto.'),('PHP incompatible o extensiones ausentes','Revisa el PHP de la terminal y composer check-platform-reqs. La conexión MySQL necesita pdo_mysql; el preprocesado OCR usa GD.')],('Situación','Qué comprobar')),
 ['EcoLactWeb/composer.json','EcoLactWeb/composer.lock','EcoLactWeb/package.json','EcoLactWeb/.env.example','EcoLactWeb/.gitignore','EcoLactWeb/config/database.php','EcoLactWeb/database/seeders/DatabaseSeeder.php','EcoLactWeb/database/seeders/AdminUserSeeder.php'],['backend-servidor'])

page('mobile','4. Configurar y ejecutar aplicación móvil','Aplicación móvil con Kotlin Multiplatform','cellphone-cog',
 'El módulo compartido reúne lógica y UI; Android tiene su propia aplicación de entrada y iOS su proyecto Xcode. Esta guía compila la variante Android de desarrollo y conecta el cliente a Laravel.',
 '## Paso 1. Abrir Android Studio\n\nSelecciona **Open** y abre `EcoLactMobile`, donde está `settings.gradle.kts`. No abras solo shared: el proyecto raíz coordina plugins, repositorios y módulos. **Resultado esperado:** aparecen androidApp y shared; compruébalo en la vista Project.\n\n'+capture('android-studio')+
 '## Paso 2. Sincronizar Gradle\n\nSelecciona JDK 17 para Gradle en la configuración de Android Studio, instala SDK Platform 36 y las herramientas que solicite el proyecto. Ejecuta **Sync Project with Gradle Files**. Gradle lee los scripts, descarga plugins y dependencias y construye el modelo de tareas. Necesita red para la primera resolución y un SDK localizado por Android Studio o local.properties.\n\n'+code('.\\gradlew.bat --version\n.\\gradlew.bat projects')+
 note('**Resultado esperado:** el Wrapper informa Gradle 9.1.0 y un JVM compatible, y projects reconoce :androidApp y :shared. **Comprobación:** revisa Gradle JVM en el IDE y la salida del Wrapper; el Java de otra terminal puede ser distinto. No copies local.properties de otro equipo.')+
 '## Paso 3. Reconocer la estructura\n\n'+block('ec-dev-folder-tree','''```text
EcoLactMobile/
├── androidApp/         # Aplicación Android
├── iosApp/             # Proyecto Xcode
├── shared/             # Kotlin y Compose compartidos
├── gradle/             # Catálogo y Wrapper
├── gradlew.bat
├── build.gradle.kts
└── settings.gradle.kts
```''')+
 'Los módulos usan objetivo JVM 11. El catálogo declara AGP 9.0.1, Kotlin 2.4.10 y SQLDelight 2.3.2. Estas declaraciones describen el repositorio; la sincronización debe confirmar su resolución en el equipo.\n\n'+
 '## Paso 4. Configurar la API\n\nEn `shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/core/network/ApiConfig.kt`, el valor actual es:\n\n'+code('var baseUrl: String = "http://10.0.2.2:8000/api/v1/"','kotlin')+
 'El comentario antiguo dice que falta definir el host, pero el valor ejecutable ya apunta al emulador. La URL debe terminar en `/`; los paths del cliente, como `login`, no empiezan con barra. Cambiar `APP_URL` en Laravel **no** reconfigura ApiConfig.\n\n'+table([
 ('Emulador Android','10.0.2.2 es el alias del equipo anfitrión. Laravel puede atender en 127.0.0.1:8000. localhost dentro de Android apunta al propio Android.'),('Android físico','Usa la IP real del equipo obtenida con ipconfig, la misma red y un puerto accesible. No copies una IP de ejemplo como si fuera universal.'),('Servidor remoto','Usa el dominio real con HTTPS y el prefijo /api/v1/. Confirma certificado y disponibilidad del servidor.')],('Entorno','Dirección y comprobación'))+
 diagram('conexion-mobile')+
 'Para una prueba en teléfono físico, Laravel debe escuchar fuera del loopback. Desde EcoLactWeb, en una red de desarrollo controlada:\n\n'+code('php artisan serve --host=0.0.0.0 --port=8000')+
 'Permite el puerto correspondiente en el firewall de la red privada y configura la IP real en ApiConfig. **0.0.0.0 es una dirección de escucha, no la URL que debes poner en el teléfono.** El archivo `androidApp/src/main/res/xml/network_security_config.xml` permite HTTP solo para los hosts enumerados: una nueva IP local puede requerir una entrada de desarrollo explícita. No permite cualquier red local por el mero comentario del archivo. Para un servidor remoto utiliza HTTPS.\n\n'+
 note('**Comprobación:** confirma que la web del servidor sea accesible desde el dispositivo y después realiza un login de prueba desde la app. Una respuesta HTTP 401/403 significa que existe comunicación pero falta autenticación o autorización; no equivale a un fallo de red.')+
 capture('mobile-api')+
 step(5,'Compilar Android','En PowerShell, situado en EcoLactMobile, construye el paquete debug. No es una publicación ni una instalación automática en el dispositivo.',
 '.\\gradlew.bat :androidApp:assembleDebug','El Wrapper ejecuta la tarea de empaquetado debug del módulo androidApp y sus dependencias.','La compilación termina correctamente y produce un APK debug.','Revisa androidApp/build/outputs/apk/debug/ y la salida final de Gradle. Si falla, conserva el primer error relevante antes de cambiar configuración.')+
 capture('mobile-gradle')+
 '## Paso 6. Ejecutar la aplicación\n\nCrea un emulador compatible con minSdk 24 o conecta un teléfono con depuración USB autorizada. En Android Studio selecciona la configuración del módulo **androidApp**, el dispositivo y **Run**. Mantén Laravel activo. **Resultado esperado:** la pantalla inicial se muestra y permite probar el acceso; verifica una consulta de datos con un usuario del entorno. El APK compilado por sí solo no demuestra conectividad ni corrección funcional.\n\n'+
 '## Paso 7. Integración iOS\n\nEn macOS abre `iosApp/iosApp.xcodeproj` con Xcode y selecciona simulador o dispositivo compatible. shared declara `iosArm64` e `iosSimulatorArm64` y genera el framework estático Shared. Revisa firma y configuración antes de ejecutar en dispositivo. No hay un destino Intel iosX64 declarado.\n\n'+note('No se afirma validación completa de iOS. El observador `ConnectivityObserver.ios.kt` revisado conserva una implementación provisional que informa conexión; requiere trabajo y pruebas en macOS. Véase [Offline-first](../arquitectura/offline-first.md).',True)+
 '## Comandos y diagnóstico\n\n'+table([('`.\\gradlew.bat --version`','Consultar Gradle y JVM efectivos.'),('`.\\gradlew.bat projects`','Listar los módulos reconocidos.'),('`.\\gradlew.bat :androidApp:assembleDebug`','Construir el APK de desarrollo.'),('`.\\gradlew.bat :androidApp:assembleDebug --stacktrace`','Repetir con detalles del error cuando haga falta diagnosticar.'),('`./gradlew :androidApp:assembleDebug`','Equivalente del Wrapper desde una terminal macOS.')])+
 'Si Gradle no sincroniza, revisa JVM, SDK, acceso a google()/Maven Central y el primer error de resolución. Si Laravel no responde, comprueba host, puerto, firewall y permiso HTTP para ese host. Si la API devuelve errores de acceso, revisa credenciales, rol y estado del usuario sin registrar tokens en capturas.\n',
 ['EcoLactMobile/settings.gradle.kts','EcoLactMobile/build.gradle.kts','EcoLactMobile/gradle/libs.versions.toml','EcoLactMobile/gradle/wrapper/gradle-wrapper.properties','EcoLactMobile/androidApp/build.gradle.kts','EcoLactMobile/shared/build.gradle.kts',K+'core/network/ApiConfig.kt',K+'core/network/HttpClientFactory.kt','EcoLactMobile/androidApp/src/main/res/xml/network_security_config.xml','EcoLactMobile/iosApp/iosApp.xcodeproj','EcoLactMobile/shared/src/iosMain/kotlin/pe/edu/upeu/ecolactmobile/core/sync/ConnectivityObserver.ios.kt'],[])

page('base-datos','5. Base de datos y seeders','Configuración de MySQL','database-cog',
 'MySQL conserva los registros centrales utilizados por Laravel. Preparar una base local identificable permite aplicar la estructura versionada y revisar los catálogos sin intervenir sobre información operativa real.',
 '## Paso 1. Preparar el servicio\n\nInicia MySQL mediante el gestor de tu instalación y abre un cliente como MySQL Workbench. Comprueba el servidor, puerto y usuario que vas a utilizar. PHP necesita pdo_mysql para establecer la conexión; el cliente gráfico por sí solo no demuestra que PHP pueda conectarse. No se fija una versión de MySQL que el repositorio no declara.\n\n'+
 step(2,'Crear la base de desarrollo','La plantilla `.env.example` conserva DB_DATABASE=ecolactv1. Ejecuta el siguiente SQL en tu servidor local de desarrollo, con una cuenta autorizada. Si ese nombre ya contiene datos, utiliza otra base y actualiza DB_DATABASE.',
 'CREATE DATABASE IF NOT EXISTS ecolactv1\n  CHARACTER SET utf8mb4\n  COLLATE utf8mb4_unicode_ci;',
 'Crea el esquema si no existe; no crea un usuario ni concede permisos. Charset y collation coinciden con los valores predeterminados de la conexión MySQL del proyecto.','Existe el esquema destinado a esta copia.','Selecciona el esquema en el cliente. IF NOT EXISTS no valida que una base existente esté vacía ni que corresponda a desarrollo.','sql')+
 step(3,'Configurar Laravel','En EcoLactWeb/.env establece estos valores como plantilla; usuario_local y valor_privado son marcadores, no credenciales funcionales.',db_example,
 'Selecciona MySQL y localiza la base y la cuenta con acceso. config/database.php puede usar SQLite si no se declara DB_CONNECTION; esta guía elige MySQL explícitamente.','La aplicación tiene la conexión del entorno correcto.','Comprueba la correspondencia con el cliente MySQL y ejecuta php artisan config:clear desde EcoLactWeb.','dotenv')+
 diagram('mysql')+
 step(4,'Aplicar la estructura','Las migraciones son la referencia versionada para construir y evolucionar las tablas. Revisa el entorno antes de ejecutar.',
 'php artisan migrate\nphp artisan migrate:status','migrate aplica archivos pendientes de database/migrations; status muestra cuáles quedaron ejecutados.','El esquema contiene las tablas previstas por esta revisión.','Revisa el estado de todas las migraciones y el listado de tablas. Hay archivos que agregan columnas, índices o transforman estructuras: no importes además un SQL histórico como segundo procedimiento normal.')+
 step(5,'Cargar los seeders','Define primero ECOLACT_ADMIN_USERNAME y ECOLACT_ADMIN_PASSWORD con datos propios. Revisa los siete seeders que llama DatabaseSeeder.',
 'php artisan db:seed','Inserta o actualiza los datos iniciales según cada implementación; no crea una demostración completa de todos los módulos.','Roles, administrador, parámetros, precio inicial, reglas de pago y zonas quedan disponibles.','Consulta los catálogos y verifica el acceso inicial. Las delimitaciones referenciales deben revisarse por el administrador.')+
 '## Qué crea o modifica cada seeder\n\n'+table([
 ('RoleSeeder','Seis roles. updateOrInsert actualiza indicadores de remuneración y control de asistencia.'),('AdminUserSeeder','Crea o actualiza el usuario de ECOLACT_ADMIN_USERNAME; vuelve a escribir el hash de la contraseña, lo activa y exige cambio de contraseña.'),('ParametroCalidadSeeder','Siete parámetros: densidad, grasa, proteína, lactosa, pH, agua añadida y temperatura. Actualiza su descripción, unidad y estado.'),('PrecioLecheSeeder','Precio inicial de 1.70 por litro, vigente desde 2020-01-01, asociado al administrador configurado. Si no lo encuentra, no lo crea.'),('ReglaPagoCalidadSeeder','Reglas monetarias para ACEPTADA, OBSERVADA y RECHAZADA. La regla observada tiene ajuste -0.10.'),('ZonaSeeder','Collana 1, Collana 2, Faon y Yasin. Evita duplicar nombres equivalentes y completa colores faltantes.'),('ZonaDelimitacionSeeder','Añade polígonos operativos referenciales alrededor de Huata solo si falta geometría. No son límites oficiales; conserva delimitaciones editadas.')],('Seeder','Comportamiento observado'))+
 note('**No confundir repetibilidad con ausencia de cambios.** Varios seeders usan updateOrInsert: repetirlos puede sobrescribir valores editados y restablecer la contraseña del administrador. Las delimitaciones iniciales requieren validación operativa.',True)+
 step(6,'Verificar la conexión y estructura','En el cliente MySQL, abre la misma base que configuraste en Laravel.',
 'USE ecolactv1;\nSELECT DATABASE();\nSHOW TABLES;\nSELECT nombre FROM roles;\nSELECT nombre FROM zonas;',
 'Las consultas identifican el esquema, enumeran las tablas y revisan catálogos sin mostrar datos personales.','Aparecen las tablas migradas y los roles y zonas sembrados.','Compara con php artisan migrate:status: el cliente MySQL y Artisan deben observar la misma base. No basta con que Workbench conecte a otro servidor.','sql')+
 note('`migrate:fresh --seed` **elimina las tablas existentes** y vuelve a crear y sembrar la base. No forma parte del procedimiento normal. Solo tiene sentido en un entorno de pruebas desechable cuya pérdida de datos se haya aceptado.',True),
 ['EcoLactWeb/.env.example','EcoLactWeb/config/database.php','EcoLactWeb/database/migrations','EcoLactWeb/database/seeders/DatabaseSeeder.php']+[f'EcoLactWeb/database/seeders/{s}Seeder.php' for s in ['Role','AdminUser','ParametroCalidad','PrecioLeche','ReglaPagoCalidad','Zona','ZonaDelimitacion']],['mysql-base-datos','mysql-migraciones'])

backend_vars=table([
 ('APP_NAME','Nombre mostrado por la aplicación; la plantilla dice Laravel, ajustable para el entorno.'),('APP_ENV','Identifica el entorno; local corresponde a desarrollo.'),('APP_DEBUG','Permite detalles de diagnóstico. Desactivar fuera de desarrollo.'),('APP_URL','URL base utilizada por Laravel; no configura la escucha de serve ni la URL de Ktor.'),('DB_CONNECTION','Motor de la conexión; mysql en la plantilla.'),('DB_HOST','Host donde escucha MySQL; no la dirección de Laravel.'),('DB_PORT','Puerto del servicio MySQL.'),('DB_DATABASE','Nombre del esquema: ecolactv1 en la plantilla revisada.'),('DB_USERNAME','Usuario propio de la base de datos.'),('DB_PASSWORD','Contraseña privada de ese usuario.')],('Variable','Finalidad'))
page('configuracion','6. Variables de entorno y configuración','Configuración del proyecto','tune',
 'El backend y la app móvil tienen configuraciones independientes. La coherencia entre ambas permite que una solicitud salga del dispositivo, llegue al servidor correcto y utilice la base prevista.',
 block('ec-dev-grid',block('ec-dev-card','### Backend Laravel\n\n`.env.example` documenta las variables; `.env` almacena los valores locales y está ignorado por Git. Las variables verificadas son:\n\n'+backend_vars)+block('ec-dev-card','### Cliente móvil\n\n`ApiConfig.baseUrl` está definido en Kotlin, no en .env. Actualmente apunta a `http://10.0.2.2:8000/api/v1/`. Conserva el prefijo y la barra final.\n\n`HttpClientFactory` crea Ktor con el motor de plataforma, JSON y la cabecera Bearer obtenida de SessionManager. `expectSuccess=false` permite tratar estados HTTP en la capa del cliente. No escribas un token fijo en ApiConfig.\n\nEn emulador se utiliza el alias del anfitrión; en un teléfono físico se necesita la IP real accesible del equipo; para remoto, un dominio HTTPS. Consulta [la configuración de red](mobile.md).\n\n**Comprobación:** confirma la URL efectiva y prueba una solicitud autenticada con un usuario del entorno, sin copiar tokens en el historial ni en capturas.'))+
 '## Aplicar y comprobar cambios\n\nDespués de editar .env, desde EcoLactWeb:\n\n'+code('php artisan config:clear')+
 '**Propósito:** retirar una configuración cacheada para que la aplicación lea los valores actualizados. Reinicia procesos persistentes que dependan de ella. En la app móvil, recompila o vuelve a ejecutar después de cambiar la URL de ApiConfig.\n\n'+note('**Resultado esperado:** Laravel conecta al esquema configurado y Android llega a /api/v1/. Comprueba el entorno con migrate:status y una consulta de prueba. No muestres la configuración completa en la terminal para confirmar una sola variable: puede contener secretos.')+
 '## Configuraciones adicionales del proyecto\n\n'+cards([
 ('OCR','El backend usa Tesseract local para tickets del analizador y boletas de compra. Instala el ejecutable y el idioma elegido. `TESSERACT_PATH` vacío usa el PATH; `TESSERACT_LANG` usa eng por defecto. El preprocesador de imágenes llama a funciones GD de PHP. Comprueba `tesseract --version`, `tesseract --list-langs` y `php -m`; luego prueba una imagen no sensible. No hay una API OCR en la nube configurada en estos adaptadores.','text-recognition','blue'),
 ('Mapas','`GOOGLE_MAPS_API_KEY` y `GOOGLE_MAPS_MAP_ID` se leen en services.php. También existen `GOOGLE_MAPS_DEFAULT_LAT`, `_LNG`, `_ZOOM` y `GOOGLE_MAPS_ZONAS_LAT`, `_LNG`, `_ZOOM`. Configura la clave de Maps JavaScript con restricciones de API y referrer. Esta clave se entrega al navegador por diseño; no debe confundirse con una credencial secreta de servidor. Verifica el mapa de zonas en la web.','map-marker-radius','green'),
 ('Archivos locales','SQLDelight utiliza SQLite en el dispositivo para caché, sesión y cola. Los drivers de plataforma abren ecolact.db; no es la base MySQL del servidor. Conserva pendientes antes de borrar datos de la app. SessionManager guarda el token local como texto: no publiques el archivo de base de datos ni copias de sesión.','database-outline','orange'),
 ('Entorno de desarrollo','Usa el SDK y JDK configurados en Android Studio, local.properties propio y una URL de API accesible. El permiso HTTP está limitado a hosts en network_security_config.xml. El servidor de Laravel y MySQL tienen puertos distintos; Vite añade un tercer proceso para recursos web.','laptop','purple'),
 ])+
 '## Claves y evidencias saneadas\n\nAPP_KEY se genera en la instalación nueva y no se publica. ECOLACT_ADMIN_USERNAME y ECOLACT_ADMIN_PASSWORD configuran el usuario del seeder; deben tener valores propios antes de sembrar. No incluyas contraseñas, tokens, claves privadas o datos de usuarios en commits, incidencias o capturas. Usa una copia saneada de la plantilla para documentar el entorno.\n\nLa sustitución de los marcadores de esta página debe mostrar nombres de variables y estructura, nunca sus valores privados.\n',
 ['EcoLactWeb/.env.example','EcoLactWeb/.gitignore','EcoLactWeb/config/services.php','EcoLactWeb/app/Infrastructure/Ocr/TesseractOcr.php','EcoLactWeb/app/Infrastructure/Ocr/PreprocesadorImagenTicket.php',K+'core/network/ApiConfig.kt',K+'core/network/HttpClientFactory.kt',K+'core/session/SessionManager.kt','EcoLactMobile/androidApp/src/main/res/xml/network_security_config.xml'],['configuracion-entorno','configuracion-api'])

page('git','7. Git y convenciones de desarrollo','Control de versiones con Git','git',
 'Git permite comprender y revisar la evolución de EcoLact. El flujo siguiente es una propuesta colaborativa; no implica que el repositorio exija ramas protegidas, aprobaciones o una convención de commits específica.',
 flow([('Clonar repositorio','source-repository'),('Crear rama','source-branch'),('Realizar cambios','file-edit'),('Registrar commits','source-commit'),('Pull Request','source-pull')])+
 '## Procedimiento propuesto\n\nClona únicamente si aún no tienes una copia. Desde la carpeta padre:\n\n'+code('git clone https://github.com/MilkonMamani/EcoLact.git\ncd EcoLact')+
 'Desde la raíz del repositorio, comprueba el estado y crea una rama para una tarea concreta. La rama parte de la revisión en la que estés situado.\n\n'+code('git status\ngit switch -c feature/nueva-funcionalidad')+
 note('**Resultado esperado:** trabajas en una rama identificable. **Comprobación:** git status muestra la nueva rama. Si había cambios previos, revisa a quién pertenecen y no los incluyas automáticamente en tu tarea.')+
 'Implementa el cambio, actualiza la documentación afectada y ejecuta las comprobaciones pertinentes. Después revisa las diferencias antes de prepararlas:\n\n'+code('git diff\ngit status\ngit add .\ngit diff --cached\ngit commit -m "Descripción del cambio"')+
 '**Qué hace:** `git add .` prepara todos los cambios del directorio actual y sus descendientes, incluidas eliminaciones y archivos nuevos no ignorados. Si no todos pertenecen a la tarea, utiliza `git add ruta/del/archivo` en su lugar. `git diff --cached` permite revisar exactamente lo que entrará en el commit.\n\n'+note('**Resultado esperado:** el commit contiene un cambio coherente y sin secretos. **Comprobación:** usa git status y git log --oneline -5; revisa los archivos incluidos, no solo el mensaje.')+
 'Cuando tengas permiso de escritura en el repositorio, publica la rama:\n\n'+code('git push -u origin feature/nueva-funcionalidad')+
 'El comando envía commits y configura el seguimiento de la rama remota. Si no tienes acceso de escritura, usa un fork y su remoto propio. En GitHub crea un Pull Request hacia la rama acordada por el equipo; explica problema, cambio y comprobaciones. **Resultado esperado:** el diff es revisable; comprueba la rama destino antes de solicitar revisión.\n\n'+
 '## Comandos principales\n\n'+table([('`git clone URL`','Descargar una nueva copia y su historial.'),('`git status`','Ver rama y archivos modificados o preparados.'),('`git switch -c feature/nueva-funcionalidad`','Crear una rama desde la revisión actual y cambiar a ella.'),('`git diff`','Revisar cambios todavía no preparados.'),('`git add .`','Preparar todos los cambios bajo el directorio actual; revisarlos antes.'),('`git diff --cached`','Revisar el contenido del próximo commit.'),('`git commit -m "Descripción del cambio"`','Registrar los cambios preparados en el historial local.'),('`git push -u origin feature/nueva-funcionalidad`','Publicar la rama con permisos y establecer seguimiento.'),('`git log --oneline -5`','Consultar los cinco commits recientes de la rama.')])+
 '## Convenciones sugeridas y reglas comprobadas\n\n'+table([
 ('Ramas · propuesta','feature/ para nuevas funciones, fix/ para correcciones y docs/ para documentación. Usa nombres breves con guiones.'),('Commits · propuesta','Describe una acción concreta y su propósito. Un commit debería agrupar cambios relacionados, sin imponer un formato que el repositorio no exige.'),('Revisión · propuesta','Revisa diff y staged; separa cambios ajenos y adjunta resultados reales de las comprobaciones.'),('Configuración · existente','EcoLactWeb/.gitignore excluye .env, vendor y node_modules. EcoLactMobile/.gitignore excluye local.properties y salidas build. Un archivo ya seguido no deja de estarlo solo por añadirlo a .gitignore.'),('Kotlin · existente','gradle.properties declara kotlin.code.style=official. Mantén las convenciones de los archivos vecinos.'),('Backend · existente','EcoLactWeb/AGENTS.md pide respetar la estructura y convenciones existentes. Estas pautas no acreditan una protección de ramas en GitHub.'),('Documentación · propuesta','Edita Markdown y recursos fuente; compila MkDocs. site/ contiene archivos seguidos en esta copia, así que revisa su diff y sigue el criterio del equipo para entregarlos.'),('Pull Request · propuesta','Explica el resultado para un revisor que no participó en el trabajo. Incluye capturas reales si cambió la interfaz y comunica limitaciones de prueba.')],('Ámbito','Convención o evidencia'))+
 '## Comprobar la documentación antes del PR\n\nEn un entorno Python con MkDocs, Material y las extensiones configuradas instalados, desde la raíz:\n\n'+code('cd docs/informeEcoLact\npython -m mkdocs build --clean')+
 '**Resultado esperado:** la construcción finaliza sin errores; abre las páginas afectadas y revisa enlaces, capturas y móvil. La compilación documental no sustituye las pruebas del backend o Android cuando se modifican sus funcionalidades.\n',
 ['EcoLactWeb/.gitignore','EcoLactMobile/.gitignore','EcoLactMobile/gradle.properties','EcoLactWeb/AGENTS.md','EcoLactWeb/composer.json','docs/informeEcoLact/mkdocs.yml'],['git-historial'])

out=ROOT/'docs/desarrollo'
out.mkdir(exist_ok=True)
for i,p in enumerate(PAGES):
 links=[]
 if i: links.append(f"[← {PAGES[i-1]['title']}]({PAGES[i-1]['slug']}.md)")
 if i+1<len(PAGES): links.append(f"[{PAGES[i+1]['title']} →]({PAGES[i+1]['slug']}.md)")
 text=f'---\ntitle: "{p["title"]}"\n---\n\n'+block('ec-desarrollo',block('ec-dev-banner',f':material-{p["icon"]}: {p["banner"]}')+block('ec-dev-content','<span class="ec-dev-kicker">EcoLact / Guía de desarrollo</span>\n\n# '+p['title']+'\n\n'+p['intro']+'\n\n'+p['body']+block('ec-dev-nav','\n\n'.join(links))))
 (out/(p['slug']+'.md')).write_text(text,encoding='utf8')
dg=ROOT/'docs/assets/diagrams/desarrollo';dg.mkdir(parents=True,exist_ok=True)
for name,(_,source) in DIAGRAMS.items():
 (dg/(name+'.mmd')).write_text(source+'\nclassDef default fill:#eaf3ff,stroke:#7ca5df,color:#103b86;\n',encoding='utf8')
manifest={'revision':REV,'pages':[{k:p[k] for k in ['slug','title']} for p in PAGES],'diagrams':list(DIAGRAMS),'captures':[dict(file=n+'.png',title=v[0],description=v[1],tool=v[2]) for n,v in CAPTURES.items()],'sources':sorted(SOURCES)}
(ROOT/'tools/desarrollo-manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2),encoding='utf8')
print(f'{len(PAGES)} páginas, {len(CAPTURES)} capturas, {len(DIAGRAMS)} diagramas, {len(SOURCES)} fuentes.')

