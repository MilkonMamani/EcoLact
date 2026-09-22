# Prompt inicial — Desarrollo app móvil Ecolácteos Huata

Copia todo el contenido de abajo (desde "# Desarrollo de la aplicación móvil") y pégalo como primer mensaje en la sesión de Claude Code del proyecto Kotlin.

---

# Desarrollo de la aplicación móvil — Ecolácteos Huata

A partir de este momento se comenzará a desarrollar la aplicación móvil de Ecolácteos Huata, en Kotlin Multiplatform (Compose Multiplatform para la UI).

## 0. Antes de escribir una sola línea de código

El backend real está en Laravel, en `F:\EcoLactV1\EcoLactV1`. **No lo vas a explorar primero.** Ya se hizo ese trabajo: dentro de `F:\EcoLactV1\EcoLactV1\docs\mobile-api\` existen 12 archivos — 6 pares, uno por rol (`administrador`, `acopiador`, `proveedor`, `calidad`, `encargado-planta`, `ventas`):

- **`{rol}.txt`** — el diseño original de cada pantalla (mockup en texto: navegación, KPIs, flujos, wireframes ASCII).
- **`{rol}.md`** — el contrato real de la API para ese rol: cada endpoint con su método, ruta, forma exacta del request y del response JSON, y las correcciones de diseño ya verificadas contra el backend (dónde el mockup no coincide con lo que el servidor realmente acepta o devuelve).

**Tu primera tarea, antes de cualquier otra cosa: lee los 12 archivos completos de esa carpeta, uno por uno.** No asumas, no resumas por encima — cada `.md` tiene el shape exacto de JSON que vas a necesitar para tus data classes de Kotlin.

**Regla de prioridad**: donde el `.txt` (mockup) y el `.md` (contrato real) digan cosas distintas, **manda el `.md`**. Cada `.txt` además trae al final una nota corta marcando explícitamente qué partes de ese mockup no aplican o cambiaron — léela igual, es parte del archivo.

**No inventes** endpoints, campos, ni comportamientos que no estén en esos documentos. Si para una pantalla necesitas algo que no aparece ni en el `.txt` ni en el `.md`, dilo explícitamente antes de asumir cómo debería funcionar — no rellenes el hueco por tu cuenta.

Solo si después de leer todo te queda una duda puntual sobre cómo se comporta el backend de verdad (por ejemplo, verificar el nombre exacto de un campo o el código de un enum), puedes entrar a `F:\EcoLactV1\EcoLactV1` a mirar el código Laravel — pero **nunca lo modifiques**, es de solo lectura/referencia. Este proyecto Kotlin es completamente independiente del repositorio Laravel.

## 1. Identidad visual

La app está dirigida a Ecolácteos Huata: identidad profesional, moderna, limpia, fácil de usar — varios usuarios van a operarla en campo, con poca luz o prisa, así que legibilidad y botones grandes priman sobre estética recargada.

Paleta:

| Uso | Color | Hex |
|---|---|---|
| Verde principal (identidad) | | `#168A4A` |
| Verde oscuro | | `#0E6235` |
| Verde claro | | `#E7F4EC` |
| Azul institucional (información) | | `#1E4F91` |
| Azul oscuro | | `#163B70` |
| Azul claro | | `#EAF1FA` |
| Amarillo/dorado (advertencia) | | `#D9A91A` |
| Amarillo claro | | `#FFF6DB` |
| Rojo (error/crítico) | | `#C9363E` |
| Rojo claro | | `#FCEAEC` |
| Fondo | | `#F6F7F5` |
| Blanco | | `#FFFFFF` |
| Texto principal | | `#202B25` |
| Texto secundario | | `#66736B` |
| Bordes | | `#E1E5E1` |

Verde = identidad de marca. Azul = información. Amarillo = advertencia. Rojo = error/crítico. No mezcles esos significados.

Principios de UI/UX a respetar en cada pantalla: navegación sencilla, información clara, formularios compactos, acciones fácilmente identificables, estados visuales claros (usa los colores de arriba consistentemente para "pendiente/advertencia/error/éxito"), buena legibilidad, consistencia entre pantallas, mínimo de pasos posible.

Las pantallas descritas en los `.txt` deben llevarse a código respetando su estructura visual y su flujo — ajusta solo lo necesario para que funcione bien en móvil y para que coincida con lo que el `.md` dice que el backend realmente soporta.

## 2. Arquitectura offline-first

Ecolácteos Huata no tiene conexión estable en campo. La app necesita:

```
Con Internet:      App → API Laravel (/api/v1/...) → base de datos del sistema
Sin Internet:      App → SQLite local → cola de sincronización → (al volver internet) → API Laravel
```

Usa SQLDelight para el almacenamiento local y Ktor Client para consumir `/api/v1/...` (autenticación Bearer/Sanctum — el login y el manejo de token están documentados en cada `.md`, sección 1). El usuario debe poder seguir operando lo que su rol tenga permitido hacer offline (fíjate en cada `.md`: casi todos los roles tienen una sección final de "Offline" que dice exactamente qué operación de escritura existe para ese rol y qué cuidado necesita).

## 3. Sincronización y duplicados — LEE ESTO CON CUIDADO

Aquí hay algo importante que corregir de raíz: **el backend Laravel actualmente NO tiene un mecanismo de idempotencia** (no hay una clave única de cliente, ni un endpoint que responda "esta operación ya fue procesada"). Cada `.md` que tiene una operación de escritura lo advierte explícitamente en su sección de Offline.

Esto significa que, por ahora, la prevención de duplicados es un problema que la **app** tiene que mitigar por su cuenta, sin una garantía perfecta del servidor:

- En cuanto el servidor confirme una operación (te devuelva su `id`), guarda ese `id` en SQLite inmediatamente y marca el registro local como sincronizado. Nunca reintentes un registro que ya tiene `id` de servidor.
- El caso que **no** se puede resolver solo del lado del cliente es: la petición sí llegó al servidor y se guardó, pero la respuesta se perdió en el camino (ej. se cortó la conexión justo después). Ahí, sin saberlo, un reintento crearía un duplicado real en el servidor.
- No implementes una solución falsa para ese caso (como comparar por fecha+cantidad, que puede fallar con operaciones legítimas parecidas). Si te encuentras con esta limitación al construir la cola de sincronización, dilo explícitamente en vez de inventar una mitigación poco confiable — es una decisión pendiente entre el backend y el negocio (agregar una clave de idempotencia real en Laravel), no algo que se resuelva solo en Kotlin.

El flujo de cola de sincronización, en lo que sí es confiable:

```
Registro local → Pendiente de sincronización → Envío al backend →
Backend confirma (devuelve id) → Marcado como sincronizado (guarda ese id)
```

Si el backend responde con error de validación o negocio (422, por ejemplo stock insuficiente o proveedor fuera de zona), esa operación pasa a una cola de "requiere revisión", nunca se reintenta sola en loop — cada `.md` documenta qué errores puede devolver cada endpoint.

## 4. Login y acceso por rol

Primera pantalla a construir: Login (usuario + contraseña + "Iniciar sesión"), contra `POST /api/v1/login` (documentado en la sección 1 de cualquiera de los `.md`, es igual para los 6 roles). La respuesta trae el token y el rol del usuario (`data.user.role`). A partir de ahí, la navegación, el menú inferior y las pantallas disponibles dependen exclusivamente de ese rol — no muestres nada de otro rol.

Los 6 roles son exactamente: `ADMINISTRADOR`, `ACOPIADOR`, `PROVEEDOR`, `CALIDAD`, `ENCARGADO_PLANTA`, `VENTAS`. No hay más, no hay sub-roles ni permisos configurables.

## 5. Liquidaciones e impresión

Ya existe en el backend un flujo de liquidaciones con filtros (zona, período) y generación de PDF, documentado en `administrador.md` (sección 5.1) y `proveedor.md` (sección 5) — son endpoints que devuelven directamente el binario del PDF (`Content-Type: application/pdf`), no hay que reconstruir esa lógica ni generar el PDF en el cliente. Solo hay que consumir esos endpoints y darle al usuario la opción de ver/descargar el documento.

## 6. Casos especiales que necesitan una decisión tuya antes de construir la pantalla

- **QR de Proveedor/Acopiador** (`proveedor.md` sección "Mi QR", `acopiador.md` sección "Registrar entrega"): el backend solo entrega un `qr_token` de texto. La app del proveedor tiene que **dibujarlo** como imagen QR (librería recomendada: ZXing o el generador de ML Kit) y la app del acopiador tiene que **leerlo** con la cámara (ML Kit Barcode Scanning o ZXing + CameraX) y mandar ese texto tal cual como `identificador` al registrar la entrega. Esto ya está verificado de punta a punta contra el código Laravel — funciona.
- **OCR de Calidad** (`calidad.md`, pasos de Evidencia/Parámetros): el backend NO ejecuta OCR, todo valor se guarda como `origen: MANUAL` sin importar cómo llegó. Si se va a implementar lectura automática de la foto, tiene que correr en el propio celular (ej. Google ML Kit Text Recognition, on-device y offline) — y el analista siempre debe poder revisar/corregir antes de confirmar. No lo des por soportado del lado del servidor.

## 7. Orden de desarrollo

1. Leer completos los 12 archivos de `docs/mobile-api/` (paso 0 de este documento).
2. Definir la arquitectura del proyecto Kotlin Multiplatform (módulos, capas: red/Ktor, local/SQLDelight, dominio, UI/Compose).
3. Configurar el cliente Ktor contra `/api/v1/` y el manejo de token.
4. Configurar SQLDelight y el esquema local necesario para las operaciones offline de cada rol.
5. Diseñar el mecanismo de cola de sincronización (sección 3 de este documento).
6. Construir la pantalla de Login + resolución de rol.
7. Construir la navegación base por rol (bottom nav + cabecera con notificaciones/online-offline/perfil, según cada `.md`/`.txt`).
8. Implementar las pantallas de cada rol en este orden sugerido: Proveedor (más simple, todo lectura) → Acopiador → Calidad → Encargado de Planta → Ventas → Administrador (el más grande).
9. Integrar cada pantalla contra su endpoint real del `.md` correspondiente, con manejo de estados de carga/error.
10. Implementar las operaciones offline soportadas por cada rol.
11. Implementar la sincronización y sus estados visuales (pendiente/sincronizando/sincronizado/error).
12. Integrar liquidaciones + visor/descarga de PDF.
13. Pruebas con y sin conexión.
14. Pruebas de sincronización, incluyendo el caso conocido de duplicado sin resolver (sección 3) — documentar cómo se comporta hoy, no ocultarlo.

Empieza por el paso 0.
