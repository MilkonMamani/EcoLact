# Revisión de Sistema

Fecha: 27 de octubre de 2026 (America/Lima).

## Archivos

- Se actualizaron las seis páginas de `docs/sistema/`: `index.md`,
  `problema-solucion.md`, `actores-roles.md`, `modulos.md`,
  `reglas-negocio.md` y `alcance.md`.
- Se refactorizaron `docs/stylesheets/sistema.css` y `actores.css`.
- Se añadieron 16 imágenes `*-nitida.png` en `docs/assets/images/`.
- `site/` se regeneró mediante MkDocs. Este directorio ya estaba versionado;
  sus diferencias incluyen la aplicación de la configuración local preexistente.
- No se modificaron `mkdocs.yml`, `extra.css`, `docs/index.md`, el código de
  EcoLactWeb/EcoLactMobile ni las fuentes del resto de la documentación.

## Diseño

Cada página implementa su panel de referencia de forma independiente.
Se unificaron encabezados, espaciado, iconos SVG y tipografía; se retiraron
reglas duplicadas, transformaciones al pasar el cursor y usos de `!important`.
Los selectores nuevos quedan dentro de `.ec-sistema`.

Actores utiliza tres columnas y dos filas en escritorio, dos columnas en tablet
y una en móvil. Módulos conserva dos columnas en escritorio y tablet y una en
móvil. Las imágenes mantienen sus proporciones mediante `object-fit: cover`.
Tablas y diagrama tienen desplazamiento local en pantallas estrechas.

Se corrigieron rutas absolutas y rutas relativas de profundidad incorrecta.
Los bloques HTML de tarjetas se preservan como HTML para evitar que
`md_in_html` cierre sus contenedores durante el procesamiento.

## Contenido contrastado

Se ejecutó `git fetch origin`: HEAD y origin/main coincidían en `7590133`.
Se revisaron las rutas web/API, controladores y casos de uso relevantes, y las
pantallas y navegación móvil. Referencias principales desde la raíz del repo:

| Tema | Evidencia |
| --- | --- |
| Canales y roles | `EcoLactWeb/routes/web.php`, `routes/api.php`; `EcoLactMobile/shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/App.kt` y los `*App.kt` de proveedor, acopiador, calidad, planta y ventas |
| Entregas y autorización | `EcoLactWeb/app/Domain/Entrega/Entrega.php`; `app/Application/Acopio/RegistrarEntrega.php`; `app/Application/Recorrido/RegistrarAcopioDeRecorrido.php` |
| Calidad | `app/Application/Calidad/RegistrarAnalisisCalidad.php`, `CalcularResultadoCalidad.php`, `FinalizarAnalisisCalidad.php`; `app/Domain/Calidad/ReglaCalidad.php`; `app/Http/Controllers/Web/Analista/AnalisisCalidadController.php` (bajo EcoLactWeb) |
| Producción y compras | `EcoLactWeb/app/Application/Produccion/IniciarProduccion.php`, `FinalizarProduccion.php`, `RegistrarCompraInsumos.php` |
| Ventas y caja | `EcoLactWeb/app/Application/Ventas/RegistrarVentaProducto.php`; `app/Http/Controllers/Web/Admin/CajaController.php` |
| Operaciones sin conexión | `EcoLactMobile/shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/core/sync/SyncModels.kt` y las pantallas correspondientes |

Correcciones frente a la ilustración de referencia:

- Cinco roles tienen pantallas móviles; el administrador dispone de la web.
- El volumen se registra en litros, mayores que cero.
- El análisis no es un requisito previo para ingresar leche al inventario o
  sincronizar el pago pendiente del proveedor.
- La clasificación se calcula al confirmar. Requiere valores y reglas vigentes;
  RECHAZADA prevalece sobre OBSERVADA. El análisis confirmado es inmutable.
- Las colas de sincronización existentes no se presentan como una función futura.
- Las ampliaciones propuestas se identifican como posibilidades, no como
  funcionalidades implementadas ni compromisos de desarrollo.

## Imágenes

Las ilustraciones anteriores eran ampliaciones borrosas y algunas contenían
fragmentos del borde de la página original. Se generaron versiones nítidas a
partir de cada archivo, conservando sus escenas, personajes y colores.
Los archivos originales y el antiguo diagrama PNG se conservaron.
El diagrama utilizado ahora es Mermaid, basado en las reglas verificadas.
Las interfaces dentro de las ilustraciones no son capturas reales de EcoLact.

## Validación

- `mkdocs build --clean`, desde `docs/informeEcoLact`: correcto, sin errores
  de compilación. Material imprime su aviso general sobre la futura versión
  MkDocs 2.0; no es un fallo de estas páginas.
- Inspección visual de las seis páginas en navegador, incluidos el diagrama
  renderizado y las vistas móviles/tablet.
- Comprobación de las seis páginas a 320, 768, 1024 y 1440 px: sin desbordamiento
  horizontal de página ni imágenes rotas detectadas.
- Se verificaron tres columnas de actores y dos de módulos en escritorio.
- Enlaces y 26 recursos internos comprobados mediante HTTP tanto en `/`
  como en una simulación local de `/EcoLact/`; búsqueda comprobada en subruta.
- `git diff --check` sobre fuentes de Sistema: correcto.
- Se comprobó que `mkdocs.yml` y `extra.css` coinciden byte a byte con sus
  versiones al iniciar el trabajo, y que no hay cambios en el código de negocio.

La composición sigue la referencia, pero las ilustraciones regeneradas no son
copias exactas de sus píxeles. Los textos, etiquetas y decisiones se adaptaron al
código actual. La validación realizada corresponde a la documentación; no se
ejecutaron pruebas funcionales del backend ni de la aplicación móvil.
