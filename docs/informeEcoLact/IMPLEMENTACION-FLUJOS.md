# Implementación y validación de Flujos

Fecha: 28 de octubre de 2026.

## Archivos implementados

Los nueve archivos existentes solo contenían un título provisional. Se completaron y se añadieron dos páginas:

1. [Vista general](docs/flujos/index.md)
2. [Acopio de leche](docs/flujos/acopio.md)
3. [Control de calidad](docs/flujos/calidad.md)
4. [Valorización y pagos](docs/flujos/liquidacion-pagos.md)
5. [Consulta del proveedor](docs/flujos/consulta-proveedor.md) — nuevo
6. [Producción](docs/flujos/produccion.md)
7. [Insumos y compras](docs/flujos/insumos.md)
8. [Ventas y compradores](docs/flujos/ventas-caja.md)
9. [Caja y finanzas](docs/flujos/finanzas.md)
10. [Asistencia y personal](docs/flujos/personal-asistencia.md) — nuevo
11. [Integración móvil y web](docs/flujos/integracion.md)

Se agregó `docs/stylesheets/flujos.css`, limitado al contenedor `.ec-flujos`, y se actualizó únicamente el submenú Flujos y la referencia a ese CSS en `mkdocs.yml`. Las seis secciones superiores siguen presentes. Se conservaron los cambios que ya existían en Sistema y en la configuración.

## Recursos

- Once WebP locales de 1200 × 800: **cuatro imágenes generadas** (pagos, proveedor, producción y asistencia) y **siete reutilizadas** del proyecto. Ninguna descarga externa.
- Carpeta: `docs/assets/images/flujos/`.
- Una ilustración adicional original: `boleta.svg`.
- Procedencia y prompts: [FUENTES_IMAGENES.md](docs/assets/images/flujos/FUENTES_IMAGENES.md).
- Diez diagramas de flujo/actividades, incluido acopio con carriles y decisiones de calidad y pagos, más un diagrama de secuencia de integración.
- Once SVG y once fuentes Mermaid editables en `docs/assets/diagrams/flujos/`. Los diagramas se sirven localmente; cada página incluye ampliación, fuente descargable y fuente desplegable.

## Reglas comprobadas

Se revisaron rutas web/API, casos de uso PHP, entidades pertinentes y rutas, pantallas, repositorios y sincronización Kotlin. Las páginas identifican sus fuentes de código en un bloque desplegable.

- Acopio incorpora leche sin esperar análisis y sincroniza el pendiente cuando hay configuración suficiente.
- Calidad trabaja por muestras, evalúa valores registrados y da prioridad a RECHAZADA; falta de reglas impide confirmar.
- La sincronización semanal usa jueves a miércoles, peor resultado confirmado y NO_PAGABLE para rechazo. No modifica pagos realizados.
- Se documentó por separado la generación tradicional de liquidaciones: usa valorizaciones previas y precio al cierre, con reglas distintas. Se evitó reproducir como comportamiento actual un comentario antiguo sobre leche rechazada que no ingresaba a planta.
- Producción descuenta insumos al inicio y registra cantidades reales al cierre.
- OCR de compras requiere revisión y confirmación humana; la equivalencia viene del catálogo.
- Venta de planilla no es entrada de caja. Caja conserva referencia a venta, compra o pago.
- Asistencia cierra a las 07:00; la corrección solo se admite en la ventana del mismo día.
- Se distinguió API de pantallas móviles: QR en Inicio y liquidaciones presentadas como Pagos.
- Se documentaron cola local, cinco intentos transitorios, revisión de errores y alcance específico de idempotencia. Finalizar recorrido, sortear muestra y validar planilla necesitan conexión.

## Validación ejecutada

Comando: `python -m mkdocs build --clean` (equivalente al ejecutable `mkdocs build --clean`).

**Resultado final: código de salida 0, documentación construida en 3,54 segundos.** Material emitió su aviso general sobre MkDocs 2.0; MkDocs informó que el documento de procedencia de imágenes no figura en la navegación. No hubo errores de compilación ni advertencias de enlaces rotos del proyecto.

- Once páginas Markdown presentes.
- Once imágenes WebP verificadas, todas de 1200 × 800.
- Once diagramas parseados y renderizados correctamente con Mermaid 11.12.0.
- **726 referencias locales** del HTML de Flujos comprobadas: ninguna ruta inexistente.
- Navegador Edge mediante Playwright: las once páginas abiertas a **1440 × 1000**, **768 × 1024** y **390 × 844**.
- **33 capturas de Flujos**, más siete capturas de Inicio y Sistema.
- En las 33 vistas no se detectaron imágenes rotas, enlaces internos de Flujos fallidos, errores JavaScript ni desbordamiento horizontal de la página. Las tablas tienen desplazamiento interno en pantallas estrechas.
- Inicio y las seis páginas de Sistema respondieron 200 y sus imágenes cargaron correctamente.
- **40 comprobaciones, cero fallos**: [resultados.json](validacion-flujos/resultados.json).
- Se verificaron además las once páginas servidas en la raíz: [raiz.json](validacion-flujos/raiz.json). La pasada principal usa la subruta `/site/`, sin rutas absolutas a los recursos de Flujos.
- Se inspeccionaron visualmente las cabeceras y composición de las once páginas, las capturas completas de portada e insumos y una composición de vistas móviles. La verificación automatizada no sustituye una revisión humana exhaustiva de cada texto a cada nivel de ampliación.

Evidencia visual: [once páginas](validacion-flujos/revision-once-paginas.jpg), [móvil](validacion-flujos/revision-movil.jpg), [recursos](validacion-flujos/recursos.jpg). Las capturas completas están en `validacion-flujos/`.

## Problemas corregidos y límites

- Se corrigieron las rutas de imágenes para que MkDocs las transforme correctamente al construir páginas anidadas: se usó Markdown dentro de HTML en lugar de depender de `src` relativos sin transformación.
- La boleta ilustrativa quedaba sin cargar en capturas completas por carga diferida; se eliminó esa carga diferida. En las páginas existentes de Sistema, la prueba espera la carga y decodificación de imágenes sin editar sus fuentes.
- Los diagramas tienen alternativas textuales detalladas y SVG ampliable. En móviles, los diagramas complejos requieren abrir «Ampliar diagrama» y usar zoom para leer todos los rótulos con comodidad.
- **El mosaico de once mockups no estaba adjunto.** Se siguieron la paleta, los elementos solicitados y el estilo existente de Sistema. No se puede afirmar fidelidad exacta ni comparación con esa referencia ausente.
- La imagen móvil reutilizada es ilustrativa, no una captura funcional comprobada de la app. Las fotografías generadas tampoco son evidencia de una planta real.
- No se verificó el despliegue de Laravel, la base de datos, el programador de tareas ni la ejecución de las operaciones del negocio. La fidelidad funcional se basa en revisión estática del código local.
- No se modificó PHP, Kotlin, base de datos ni funcionalidades. No se realizaron commits ni pushes. `site/` se regeneró con MkDocs, conforme a la validación solicitada.

## Reproducción

`tools/generar_flujos.py` conserva las fuentes editoriales y genera Markdown y Mermaid. `tools/renderizar_flujos.cjs` renderiza los SVG (requiere Playwright, Edge y acceso al CDN de Mermaid solo en la fase de generación). `tools/validar_flujos.cjs` abre las páginas y guarda las capturas y resultados.

Los scripts de navegador esperan un servidor estático de esta carpeta en `http://127.0.0.1:8766/`. El sitio construido queda accesible bajo `/site/`. La reproducción de los scripts requiere configurar `NODE_PATH` con los paquetes disponibles de Playwright; no añade dependencias al sistema EcoLact.
