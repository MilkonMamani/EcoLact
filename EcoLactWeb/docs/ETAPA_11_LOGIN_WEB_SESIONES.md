# ETAPA 11 — LOGIN WEB + SESIONES

Implementación y validación realizadas el 10 de septiembre de 2026 sobre EcoLactV1 existente (PHP 8.3, Laravel 13, Blade y Vite).

## A. Flujo final

`GET /` → invitado: `/login` → `POST /login` → autenticación nativa con guard `web` → regeneración de sesión → comprobación del rol y estado operativo → área correspondiente.

La raíz y las rutas de login también redirigen a usuarios ya autenticados. Las sesiones con rol inválido o estado no operativo se cierran al pasar por estos puntos de entrada.

Para utilizarlo: ejecutar `php artisan serve`, abrir http://127.0.0.1:8000 e ingresar las credenciales de un usuario existente. Los assets ya están compilados.

## B. Destinos por rol

| Rol | Ruta / URL | Middleware del destino |
| --- | --- | --- |
| ADMINISTRADOR | `admin.dashboard` / `/admin/dashboard` | `web`, `auth`, `active`, `role:ADMINISTRADOR` |
| ACOPIADOR | `acopiador.entregas.index` / `/acopiador/entregas` | `web`, `auth`, `active`, `role:ACOPIADOR` |
| PROVEEDOR | `proveedor.perfil` / `/proveedor/perfil` | `web`, `auth`, `active`, `role:PROVEEDOR` |

Los nombres se comprobaron contra las rutas reales. Se usa `roles.nombre`, nunca un ID numérico.

## C. Archivos creados

- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Http/Controllers/Web/Auth/LoginController.php`
- `app/Http/Controllers/Web/Auth/HomeRedirectController.php`
- `app/Http/Support/SesionWeb.php`
- `resources/views/auth/login.blade.php`
- `resources/css/pages/login.css`
- `resources/js/pages/login.js`
- `public/images/ecolact-rural.svg`
- `tests/Feature/Auth/LoginTest.php`
- `tests/Feature/Web/LoginViewTest.php`
- Este informe. Evidencia visual y resultado del navegador en `storage/app/private/login-qa/`.

## D. Archivos modificados

- `routes/web.php`: raíz, login y logout.
- `bootstrap/app.php`: destinos de invitados y usuarios autenticados.
- `resources/views/components/layout/topbar.blade.php`: formulario de cierre de sesión compartido por los tres layouts.
- `vite.config.js`: entrada del JavaScript del login, que importa su CSS.
- `tests/Feature/ExampleTest.php`: la raíz ahora redirige al login.
- `tests/Feature/Http/Controllers/Web/Admin/DashboardControllerTest.php`: expectativas previas de bienvenida y ausencia de login actualizadas; se mantiene la comprobación de 401 JSON.

Vite regeneró `public/build/`. La comparación con la copia previa confirmó que Domain, Application, Infrastructure, Models, Policies, middleware de negocio, migrations, seeders, Dashboard Query y `routes/api.php` permanecen iguales.

## E. Rutas

| Método | URL | Nombre | Middleware |
| --- | --- | --- | --- |
| GET | `/` | `home` | `web` |
| GET | `/login` | `login` | `web`, `guest` |
| POST | `/login` | `login.store` | `web`, `guest` |
| POST | `/logout` | `logout` | `web`, `auth` |

`GET /logout` devuelve 405. Logout no exige `active`, para permitir salir de una cuenta desactivada.

## F. Seguridad

Autenticación nativa por `username` y `password`; validación HTTP separada, mensajes en español, credenciales incorrectas con mensaje genérico, contraseña excluida del old input y campos escapados en Blade. Se regenera el ID de sesión tras autenticar. El destino se determina exclusivamente por rol, descartando `url.intended` para respetar la navegación pedida.

Ambos formularios incluyen CSRF. La configuración existente de sesiones y cookies se conserva. No se emiten tokens Sanctum ni se guardan contraseñas manualmente en sesiones o JavaScript.

## G. Rate limiting

`RateLimiter` con clave hash de username normalizado e IP: cinco intentos fallidos por 60 segundos. El siguiente intento recibe un mensaje con los segundos restantes. El límite caduca automáticamente y un acceso correcto limpia el contador. Se probaron separación entre usuarios/IP, caducidad y reinicio tras éxito.

## H. Cuentas inactivas

Se reutiliza `User::estaOperativo()`: comprueba `users.estado` y, cuando corresponde, la existencia y estado del proveedor/acopiador vinculado. Tras credenciales correctas de una cuenta no operativa, se cierra autenticación, invalida sesión, regenera CSRF y presenta el mensaje controlado. Los perfiles ausentes también se rechazan. El middleware `active` existente sigue impidiendo operar si la cuenta se desactiva posteriormente.

## I. Recordarme

El checkbox envía `remember` a `Auth::attempt`. Se utiliza la columna existente `remember_token` y la cookie de Laravel. Una prueba elimina la sesión y verifica la recuperación efectiva desde esa cookie (`viaRemember`). Sin seleccionar la opción no se emite cookie persistente. La casilla comienza desmarcada.

## J. Logout

Formulario POST con CSRF en el dropdown del topbar compartido. Ejecuta logout, invalida la sesión, regenera token y redirige con «Sesión cerrada correctamente». Se verificaron eliminación de datos previos, cambio de ID/token y eliminación de la cookie persistente recibida.

## K. Blade

Página independiente con distribución aproximada 42/58, panel café oscuro, marca EcoLact, cuatro conceptos con SVG, lema e ilustración rural local. Formulario crema con etiquetas, campos con iconos, Recordarme, botón teal y pie de acceso seguro. Los errores reciben foco y atributos accesibles; username se conserva y password vuelve vacío.

## L. CSS y JavaScript

Estilos específicos bajo clases `login-*`, tokens de la identidad existente y ajustes de contraste locales para texto secundario y placeholders. Tablet conserva dos paneles; móvil reduce la decoración a la marca. JavaScript vanilla alterna únicamente el tipo del campo, icono y atributos accesibles; el POST funciona sin JavaScript. No se descargaron imágenes ni se añadieron dependencias frontend.

## M. Tests

Suite completa: **426 pruebas, 425 aprobadas, 1 omitida, 1283 aserciones, 0 fallos**. Las 35 pruebas nuevas de login/Blade pasan (230 aserciones en ejecución específica).

La prueba omitida es `EcoLactMySqlSchemaTest`, que requiere explícitamente `ECOLACT_MYSQL_SCHEMA_TEST=1` y dos bases MySQL desechables. Se conserva su condición previa de ejecución. Las pruebas funcionales usan SQLite aislado.

Cobertura: tres roles con POST real y acceso a su pantalla, invitados, middleware guest, validación, credenciales incorrectas, inactividad de usuario/perfil, perfil ausente, rol desconocido/sin relación, regeneración de sesión, logout, recuperación persistente, límites de intentos, autorización entre roles, errores escapados y controles Blade. El dashboard administrativo responde 200 después del login sin retirar middleware.

## N. Build

`npm run build`: correcto con Vite 8.2.2. Entrada independiente del login y SVG incluidos en el manifiesto. Permanece un aviso no bloqueante del plugin de fuentes: optimización de fuentes alternativas requiere el paquete opcional `fontaine`; la fuente principal y los assets compilan correctamente.

## O. Pint y vistas

`php vendor/bin/pint app tests --format agent`: correcto. Se aplicó formato únicamente al nuevo test de login. `php artisan view:clear` y `php artisan view:cache`: correctos. `php artisan route:list --json`: correcto; evidencia guardada en la carpeta de QA.

## P. Migraciones

`php artisan migrate:status`: **34 migraciones ejecutadas**, ninguna pendiente. En la base del proyecto solo se consultó el estado; no se ejecutaron migraciones ni seeders para esta etapa. El navegador utilizó una base temporal de QA independiente.

## Q. Revisión visual y navegador

Chrome headless mediante Playwright ya disponible; no se instalaron paquetes ni navegadores. Se revisaron capturas a **1600, 1280, 768, 390 y 360 px**, sin overflow horizontal. También se comprobaron errores en móvil, foco visible, mostrar/ocultar contraseña, username conservado, contraseña vacía, login administrativo real y logout seguido de intento de acceso protegido. No hubo errores JavaScript.

CSRF: POST de otro origen sin token devuelve **419** tanto en login como en logout. Laravel 13 permite peticiones con `Sec-Fetch-Site: same-origin` por su protección de origen nativa; por ello la prueba adversaria usa `cross-site`. No se alteró ese middleware.

Las imágenes `login-1600.png`, `login-1280.png`, `login-768.png`, `login-390.png`, `login-360.png`, `login-error-360.png` y `login-focus-1280.png`, junto con `results.json`, quedan en `storage/app/private/login-qa/`.

## R. Diferencias y alcance

No existía un asset rural adecuado: se creó una ilustración SVG propia y estilizada siguiendo la composición adjunta. No es una reproducción fotográfica exacta. No existen dashboards dedicados para acopiador/proveedor: se usan sus pantallas funcionales existentes. Se omite recuperación de contraseña porque no tiene funcionalidad implementada. `welcome.blade.php` se conserva sin utilizarlo en la raíz.

Se actualizaron las expectativas antiguas que exigían welcome y ausencia de login. No se construyeron API, recuperación de contraseñas, nuevos dashboards ni autenticación Sanctum para el navegador.

**ETAPA 11 — LOGIN WEB + SESIONES ✅**
