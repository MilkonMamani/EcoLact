"""Fuentes editoriales de Flujos. Ejecutar desde cualquier directorio con Python 3.
Los SVG se regeneran con renderizar_flujos.cjs después de editar los diagramas.
"""
from pathlib import Path
from html import escape
import json
import re

ROOT = Path(__file__).resolve().parents[1]
DOCS = ROOT / 'docs'
PAGES = []

def page(slug, title, subtitle, image, intro, actors, diagram, steps, body, result, sources, links):
    PAGES.append(dict(slug=slug,title=title,subtitle=subtitle,image=image,intro=intro,actors=actors,diagram=diagram,steps=steps,body=body,result=result,sources=sources,links=links))

def cards(items):
    return '<div class="ec-flow-grid" markdown="1">\n\n' + '\n\n'.join(f'<div class="ec-flow-card {color}" markdown="1">\n\n### {title}\n\n{text}\n\n</div>' for color,title,text in items) + '\n\n</div>\n'

page('index','Flujos de EcoLact','Visión completa del proceso','portada-flujos',
     'Desde la entrega de leche hasta la venta de productos: cada operación conecta personas, inventario e información de pago. Explora los diez procesos para entender qué ocurre, quién participa y qué se registra.',
     [('account-cog','Administración','Configura proveedores, rutas, precios y reglas.'),('truck-delivery','Operación','Acopio, calidad, planta y ventas registran su trabajo.'),('account-check','Proveedor','Entrega leche y consulta sus resultados y pagos.')],
'''flowchart TB
  A([Inicio · Administrador]) --> B[Proveedores, zonas y rutas]
  B --> C[Acopiador · Recorrido y entrega]
  C --> D[Ingreso de leche al inventario]
  C --> E[Pago semanal pendiente]
  C -. Muestreo opcional .-> F[Calidad · Análisis confirmado]
  F --> E
  D --> G[Planta · Producción con insumos]
  H[Compra de insumos] --> G
  G --> I[Ventas · Productos terminados]
  I --> J{Forma de pago}
  J -->|Contado| K[Caja · Entrada]
  J -->|Planilla| E
  E --> L[Administración · Pago al proveedor]
  L --> M[Caja · Salida]
  H -->|Con importe positivo| M
  N[Pago a trabajadores] --> M
  C --> O[Proveedor · Historial y consultas]
  F --> O
  L --> O
  O --> P([Fin · Información disponible])''',
     ['Preparar los datos del negocio: personas, zonas, rutas, recetas, precios y reglas vigentes.',
      'Registrar el acopio. Una entrega genera inventario y activa el cálculo del pendiente cuando existe configuración suficiente.',
      'Seleccionar muestras de calidad cuando corresponda. Su resultado confirmado puede recalcular el pago aún pendiente.',
      'Transformar leche e insumos, vender productos y registrar los movimientos de dinero asociados.',
      'Consultar el historial desde los portales web y las pantallas móviles implementadas.'],
     '<div class="ec-flow-info" markdown="1">\n\n**Tres recorridos relacionados.** El inventario sigue las cantidades físicas; el pago sigue la semana del proveedor; la caja sigue el dinero efectivamente registrado. No todos avanzan al mismo tiempo.\n\n</div>\n\n## Explorar los procesos\n\n{{NAV_CARDS}}',
     'Entregas, análisis, lotes, ventas y pagos conservan sus referencias. La calidad no es una barrera obligatoria entre el acopio y la producción.',
     ['Acopio/RegistrarEntrega.php','Calidad/FinalizarAnalisisCalidad.php','Pago/SincronizarPagosProveedor.php','Caja/RegistrarMovimientoCaja.php'], ['acopio','finanzas'])

page('acopio','Flujo de acopio de leche','De la ruta al registro de la entrega','acopio',
     'El acopiador atiende los recorridos asignados, identifica al proveedor y registra la entrega real. Elegir una parada no equivale a haber recolectado su leche.',
     [('account-cog','Administrador','Registra proveedores y organiza zonas, rutas y recorridos.'),('truck-delivery','Acopiador','Identifica, mide, registra y finaliza su recorrido.'),('account','Proveedor','Se identifica con QR o DNI y entrega la leche.')],
'''flowchart TB
  subgraph ADM[Administrador]
    A([Inicio]) --> B[Registrar proveedores y zonas]
    B --> C[Organizar ruta y asignar recorrido]
  end
  subgraph PRO[Proveedor]
    P[Presentar QR o DNI y entrega]
  end
  subgraph ACO[Acopiador]
    C --> D[Iniciar recorrido propio]
    D --> E[Identificar proveedor]
    P --> E
    E --> F{¿Entregó leche?}
    F -->|Sí| G[Registrar litros]
    F -->|No| H[Marcar NO_ENTREGO]
  end
  subgraph SIS[EcoLact]
    G --> I{¿Datos y permisos válidos?}
    I -->|No| X[Mostrar error y corregir]
    X --> G
    I -->|Sí| J[Guardar entrega e ingreso de leche]
    J --> K[Sincronizar pago pendiente]
    K --> L[Parada RECOLECTADO y notificación]
  end
  H --> M[Actualizar recorrido]
  L --> M
  M --> N{¿Continuar visitas?}
  N -->|Sí| E
  N -->|No| O[Acopiador · Finalizar recorrido]
  O --> Z([Fin · Historial consultable])''',
     ['El administrador registra proveedores, organiza zonas y rutas y asigna un recorrido con sus paradas al acopiador.',
      'El acopiador inicia el recorrido y usa QR o DNI para identificar al proveedor. Debe operar un recorrido propio y abierto.',
      'Si recibió leche, registra una cantidad válida de litros; si no recibió, marca «No entregó». No puede marcar así una parada que ya tenga acopio.',
      'EcoLact guarda la entrega, ingresa leche y actualiza el pago pendiente en la misma transacción. Solo entonces la parada queda RECOLECTADO.',
      'El acopiador revisa el avance y finaliza el recorrido. El caso de uso de cierre verifica propietario y estado abierto; no exige que todas las paradas estén resueltas.'],
     cards([('green','Con entrega','La entrega nace como **PENDIENTE_ANALISIS**, pero ese nombre no obliga a muestrearla. El movimiento **INGRESO_ACOPIO** incorpora la leche inmediatamente.'),('orange','Sin entrega','**NO_ENTREGO** describe la parada. No crea una entrega de cero litros, ingreso de leche ni pago.'),('purple','Reintentos','La clave de idempotencia reutiliza la entrega existente del mismo usuario. Protege contra duplicados al reenviar desde el teléfono.')]) + '''
## Validaciones y excepciones

El proveedor y el acopiador deben estar activos. En el registro directo se comprueba la misma zona; en un recorrido se verifica que la parada pertenezca al recorrido del acopiador. El proveedor necesita una zona para guardar la entrega. Las rutas operativas exigen asistencia válida.

Si falta precio o configuración de pago, el acopio no se bloquea: las entregas quedan sin asignar a un pago; las omisiones se reportan al calcular cuando corresponde. La ausencia total de configuraciones hace que el sincronizador termine sin generar pagos.
''',
     'Entrega e historial disponibles, leche en inventario, parada actualizada y notificación al proveedor. El pendiente se genera o recalcula si hay datos suficientes.',
     ['Acopio/RegistrarEntrega.php','Recorrido/RegistrarAcopioDeRecorrido.php','Recorrido/MarcarNoEntrego.php','Recorrido/FinalizarRecorrido.php'],['calidad','produccion','liquidacion-pagos'])

page('calidad','Flujo de control de calidad','Muestreo, revisión y confirmación','calidad',
     'El analista trabaja con muestras seleccionadas. La lectura del ticket propone valores; la decisión definitiva se obtiene al confirmar el análisis contra las reglas vigentes.',
     [('flask','Analista de calidad','Selecciona muestras, revisa valores y confirma.'),('account-cog','Administrador','Configura parámetros y reglas de calidad.'),('account','Proveedor','Recibe el resultado y las advertencias que correspondan.')],
'''flowchart TB
  A([Inicio · Analista]) --> B[Seleccionar entrega elegible]
  B --> C[Registrar muestra]
  C --> D[Analizar leche]
  D --> E{¿Origen de valores?}
  E -->|Manual| F[Ingresar mediciones]
  E -->|Ticket| G[OCR propone valores]
  F --> H[Revisión humana]
  G --> H
  H --> I[BORRADOR]
  I --> J{¿Valores y reglas disponibles?}
  J -->|No| K[Informar lo que falta]
  K --> H
  J -->|Sí| L{¿Incumplimiento de rechazo?}
  L -->|Sí| R[RECHAZADA]
  L -->|No| M{¿Incumplimiento de observación?}
  M -->|Sí| O[OBSERVADA]
  M -->|No| N[ACEPTADA]
  R --> S[CONFIRMADO · Inmutable]
  O --> S
  N --> S
  S --> T[Notificar y recalcular pago pendiente]
  T --> Z([Fin · Muestra analizada])''',
     ['Seleccionar una entrega existente, no anulada ni analizada, de proveedor activo. La selección aleatoria requiere un sorteo firmado por el servidor; la selección manual también está implementada.',
      'Registrar la muestra y realizar el análisis. El sistema evita seleccionar la misma entrega por distintos analistas; el reenvío equivalente del mismo analista devuelve la selección existente.',
      'Ingresar valores manualmente o escanear el ticket. Revisar las propuestas OCR y guardar el BORRADOR. La muestra pasa a EN_ANALISIS.',
      'Solicitar confirmación. EcoLact evalúa cada valor registrado con la regla vigente a la fecha del análisis y determina el peor incumplimiento.',
      'Guardar el análisis CONFIRMADO, marcar la entrega ANALIZADA, actualizar la muestra, notificar al proveedor y recalcular su pago pendiente. OBSERVADA y RECHAZADA generan advertencia.'],
     cards([('green','ACEPTADA','Ningún valor registrado incumple una regla que determine observación o rechazo.'),('orange','OBSERVADA','Hay incumplimiento de observación y no hay uno de rechazo.'),('red','RECHAZADA','Existe incumplimiento de rechazo. **Prevalece sobre OBSERVADA**, aunque otros parámetros sean conformes.')]) + '''
## Parámetros y datos incompletos

El catálogo es configurable. El lector de tickets reconoce grasa, SNG, densidad, proteína, lactosa, sales, sólidos totales, agua añadida, punto de congelación, temperatura y pH. Reconocer una etiqueta no sustituye tener el parámetro y su regla configurados.

- Un valor en blanco se omite; no se convierte en cero.
- Un código de parámetro inexistente produce error al guardar.
- Sin valores registrados, no se puede calcular el resultado.
- Si un valor registrado carece de regla vigente, la confirmación falla e indica el parámetro pendiente de configurar.
- No se exige completar automáticamente todo el catálogo: se evalúan los valores registrados.
- Un análisis confirmado no se edita ni se confirma por segunda vez.

<div class="ec-flow-info warning" markdown="1">

**La muestra no representa una etapa obligatoria de cada entrega.** No se inventan límites de grasa, densidad ni otros parámetros: el resultado depende de las reglas configuradas. La confirmación no vuelve a ingresar la leche al inventario.

</div>
''',
     'Resultado trazable e inmutable, consulta del proveedor y actualización del pendiente. La falta de configuración de pagos no bloquea la confirmación de calidad.',
     ['Calidad/SeleccionarMuestraCalidad.php','Calidad/RegistrarAnalisisCalidad.php','Calidad/CalcularResultadoCalidad.php','Calidad/FinalizarAnalisisCalidad.php','Calidad/TicketAnalizadorParser.php'],['acopio','liquidacion-pagos','consulta-proveedor'])

page('liquidacion-pagos','Valorización, liquidación y pagos','De los litros al pago semanal','pagos',
     'El flujo operativo agrupa las entregas por proveedor en semanas de jueves a miércoles. El importe pendiente combina precio vigente, resultado semanal de calidad y compras a cuenta del pago.',
     [('account-cog','Administrador','Configura, revisa y registra el pago.'),('flask','Calidad','Aporta los resultados confirmados de la semana.'),('account-cash','Proveedor','Recibe el neto y consulta su comprobante.')],
'''flowchart TB
  A([Inicio · Entrega o análisis confirmado]) --> B[Agrupar proveedor y semana]
  B --> C{¿Configuración y precio?}
  C -->|No| D[Omitir entregas sin configuración suficiente]
  D --> Z([Fin · Completar configuración])
  C -->|Sí| E[Obtener peor resultado confirmado]
  E --> F{¿RECHAZADA?}
  F -->|Sí| G[NO_PAGABLE · Importe cero]
  G --> H[Trasladar compras a siguiente semana pagable]
  H --> Y([Fin · Visible, no se paga])
  F -->|No| I[Precio por entrega y ajuste de calidad]
  I --> J[Restar compras de planilla]
  J --> K{¿Neto positivo?}
  K -->|No| L[No generar pendiente pagable]
  K -->|Sí| M[PENDIENTE · Revisión administrativa]
  M --> N{¿Fecha habilitada y monto exacto?}
  N -->|No| M
  N -->|Sí| O[Registrar pago y comprobante]
  O --> P[PAGADO y salida de caja]
  P --> Q([Fin · Pago preservado])''',
     ['Registrar entregas y agruparlas por proveedor y semana de jueves a miércoles.',
      'Obtener el precio vigente en la fecha de cada entrega y el peor resultado confirmado de la semana: RECHAZADA, luego OBSERVADA, luego ACEPTADA.',
      'Sin análisis confirmado se usa el resultado de las otras entregas de la semana; sin resultados se conserva el precio base. OBSERVADA aplica el ajuste configurado si el pago aplica calidad.',
      'Restar las compras con DESCUENTO_PLANILLA disponibles. Una semana RECHAZADA queda NO_PAGABLE, incluso si no se aplican ajustes de calidad; sus compras se trasladan a la siguiente semana pagable.',
      'Revisar el pendiente. Registrar el pago desde el miércoles de cierre, por el importe exacto. El registro conserva datos del comprobante y permite su consulta o emisión por el flujo de pagos.',
      'Marcar PAGADO y registrar la salida de caja en la misma transacción. Un pago efectuado queda fuera del recálculo automático.'],
     cards([('orange','PENDIENTE','Importe calculado y revisable. Puede cambiar por nuevas entregas, calidad confirmada y descuentos.'),('green','PAGADO','Pago efectuado una sola vez. Las entregas posteriores de una semana ya pagada pasan a la siguiente semana disponible.'),('red','NO_PAGABLE','Semana rechazada con importe cero. Se conserva visible, pero no puede pagarse.')]) + '''
## Factores del importe

**Bruto = suma de litros pagables × precio ajustado de cada entrega.** El neto resta las compras de planilla disponibles. Los precios ajustados no bajan de cero. Si falta regla OBSERVADA en el sincronizador, su ajuste es cero; si no hay precio para una entrega, se omite. Si los descuentos igualan o superan el bruto, no se genera un pendiente pagable.

## Dos cálculos que no deben confundirse

<div class="ec-flow-table" markdown="1">

| Aspecto | Sincronización operativa | Generación de liquidación tradicional |
| --- | --- | --- |
| Caso de uso | `SincronizarPagosProveedor` | `GenerarLiquidacion` y `CalcularLiquidacion` |
| Base | Entregas por asignar y pendientes | Entregas con valorizaciones existentes |
| Precio | Vigente por fecha de entrega | Un precio vigente al final del periodo |
| Calidad | Peor resultado confirmado semanal | Incidencias OBSERVADA o RECHAZADA seleccionan regla OBSERVADA; usa litros pagables de las valorizaciones |
| Semana rechazada | Toda la semana NO_PAGABLE | No implementa por sí mismo esa decisión semanal |
| Configuración insuficiente | Omite sin bloquear acopio o calidad | Lanza error si faltan valorizaciones, precio o regla necesaria |
| Ajustes | Descuentos de planilla disponibles | Compras del periodo y ajustes recibidos, incluidos manuales |
| Repetición | Recalcula pendientes; preserva pagados | Rechaza una liquidación duplicada del mismo periodo |

</div>

La generación tradicional recibe fechas explícitas; no impone por sí sola que el rango sea jueves a miércoles. Su comentario sobre leche rechazada que no ingresó a planta no describe el flujo actual de acopio: `RegistrarEntrega` sí ingresa la leche sin esperar calidad.

<div class="ec-flow-info warning" markdown="1">

**Pago exacto y sin duplicados.** `RegistrarPagoObligacion` rechaza pagos parciales, fechas futuras o anteriores al periodo y métodos que requieran número de operación si este falta. No existe todavía un flujo de regularización automática de un pago ya efectuado.

</div>
''',
     'Pago, liquidación asociada y caja quedan relacionados. El proveedor puede consultar el estado; la contabilidad del dinero usa el importe neto realmente registrado.',
     ['Pago/SincronizarPagosProveedor.php','Pago/RegistrarPagoObligacion.php','Liquidacion/GenerarLiquidacion.php','Liquidacion/CalcularLiquidacion.php','Caja/RegistrarMovimientoCaja.php'],['calidad','ventas-caja','finanzas'])

page('consulta-proveedor','Consulta del proveedor','Su información, en un solo recorrido','proveedor',
     'El proveedor accede a sus propios registros para conocer cuánto entregó, qué resultados obtuvo y cómo se compone su pago. Las consultas no equivalen a permisos para modificar las operaciones.',
     [('account','Proveedor','Inicia sesión y consulta su información personal.')],
'''flowchart TB
  A([Inicio · Proveedor]) --> B[Acceder al portal o app]
  B --> C{¿Sesión y acceso válidos?}
  C -->|No| D[Resolver acceso]
  D --> B
  C -->|Sí| E[Inicio y resumen personal]
  E --> F[Entregas y detalles]
  E --> G[Calidad y análisis]
  E --> H[Liquidaciones y pagos]
  E --> I[Compras de productos]
  E --> J[QR personal]
  E --> K[Comunicados y notificaciones]
  F --> L[Consultar detalle propio]
  G --> L
  H --> L
  I --> L
  J --> M([Fin · Identificación disponible])
  K --> N([Fin · Información consultada])
  L --> N''',
     ['Iniciar sesión con la cuenta de proveedor y acceder al resumen personal.',
      'Elegir entregas, calidad, pagos, compras o comunicaciones y abrir el detalle disponible.',
      'Mostrar el QR personal cuando se necesite identificar al proveedor durante el acopio.',
      'Actualizar las consultas con conexión. La app puede mostrar información previamente almacenada; eso no garantiza que el saldo o los estados sean los más recientes.'],
     cards([('','Entregas','Litros e historial de registros propios, con acceso al detalle.'),('','Mi calidad','Análisis disponibles y sus resultados; una entrega sin muestra no implica un análisis faltante obligatorio.'),('green','Pagos','Liquidaciones y sus detalles, importe y pago asociado según el registro existente.'),('','Mis compras','Productos comprados y detalles; permite reconocer descuentos de planilla.'),('purple','QR personal','Identificación del proveedor, visible en Inicio de la app.'),('orange','Comunicaciones','Comunicados y notificaciones dirigidos al usuario.')]) + '''
## Qué existe en cada canal

<div class="ec-flow-table" markdown="1">

| Información | Portal web | API | App implementada |
| --- | --- | --- | --- |
| Entregas | Lista y detalle | Lista y detalle propios | Entregas y EntregaDetalle |
| Calidad | Análisis y detalle | Análisis y detalle | MiCalidad y AnalisisDetalle |
| Liquidaciones | Mis liquidaciones | Liquidaciones | **Pagos** y detalle de pago |
| Pagos | Rutas conservadas; integrados en detalle de liquidación | Rutas de pagos | Se presentan mediante el recorrido de Pagos; no se supone otra pantalla independiente |
| Compras | Lista y detalle | Lista y detalle | MisCompras y CompraDetalle |
| QR | Página QR | Consulta QR | Dentro de Inicio |
| Comunicaciones | Comunicados y notificaciones | Consultas y lectura | Comunicados, detalles y Notificaciones |

</div>

La navegación móvil está comprobada en `ProveedorRoutes.kt` y `ProveedorApp.kt`. El nombre «Pagos» de la app presenta liquidaciones del backend; no son dos secciones móviles diferentes.
''',
     'El proveedor puede revisar sus registros sin conocer el código y distinguir una entrega, su análisis, una liquidación y un pago realizado.',
     ['Proveedor/ObtenerMiQr.php','Proveedor/ObtenerDashboardProveedor.php','Calidad/ObtenerAnalisisProveedor.php'],['acopio','calidad','liquidacion-pagos','integracion'])

page('produccion','Flujo de producción','De la materia prima al producto terminado','produccion',
     'La planta planifica productos con recetas vigentes, comprueba sus insumos y registra lo realmente obtenido. El inventario cambia en dos momentos distintos: al iniciar y al finalizar el lote.',
     [('account-cog','Administrador','Configura productos y recetas.'),('factory','Encargado de planta','Planifica, inicia y finaliza los lotes.'),('package-variant','Inventario','Conserva consumos y productos obtenidos.')],
'''flowchart TB
  A([Inicio · Planta]) --> B[Consultar leche e insumos disponibles]
  B --> C[Seleccionar productos y cantidades]
  C --> D[Obtener recetas y calcular requerimientos]
  D --> E{¿Recetas válidas y stock suficiente?}
  E -->|No| F[Impedir inicio y mostrar faltantes]
  F --> C
  E -->|Sí| G[Iniciar lote EN_PROCESO]
  G --> H[Descontar leche e insumos]
  H --> I[Elaborar productos]
  I --> J[Registrar cantidades reales y merma]
  J --> K{¿Datos de todos los productos válidos?}
  K -->|No| J
  K -->|Sí| L[Finalizar lote]
  L --> M[Ingresar productos realmente obtenidos]
  M --> N[Estado FINALIZADO]
  N --> Z([Fin · Stock disponible para ventas])''',
     ['Consultar la leche ingresada desde Acopio y el stock de los demás insumos.',
      'Seleccionar productos y cantidades previstas. EcoLact obtiene sus recetas y calcula los requerimientos.',
      'Verificar recetas y stock. Al iniciar vuelve a calcular y bloquea los insumos para comprobar sus existencias; si faltan, impide crear el lote.',
      'Crear el lote EN_PROCESO, guardar la receta y versión utilizadas y descontar insumos con CONSUMO_PRODUCCION.',
      'Registrar la cantidad real obtenida de cada producto, incluida cero si corresponde. La diferencia respecto de lo planificado permite registrar la merma.',
      'Finalizar el lote. Por cada cantidad obtenida positiva, registrar INGRESO_PRODUCCION y dejar el lote FINALIZADO.'],
     cards([('orange','EN_PROCESO','El lote ya consumió los insumos necesarios. Todavía no añade productos terminados al stock.'),('green','FINALIZADO','Registra el resultado real e incorpora únicamente las cantidades obtenidas mayores que cero.'),('purple','Trazabilidad','El lote conserva productos, cantidades previstas y obtenidas, consumos y versión de receta.')]) + '''
## Controles del cierre

Solo se finaliza un lote EN_PROCESO y con productos planificados. Hay que indicar cantidades numéricas no negativas para todos sus productos. El servidor bloquea el lote durante el cierre para impedir que dos confirmaciones ingresen dos veces los mismos productos.

Si la hora de cierre enviada por el teléfono precede al inicio, se utiliza la hora de inicio. El cierre no vuelve a descontar insumos. Una falla dentro de la transacción revierte los cambios de esa operación.

<div class="ec-flow-info" markdown="1">

**La leche ya está disponible desde Acopio.** La producción no espera obligatoriamente una aprobación de calidad por cada entrega. Las compras reponen los demás insumos; la leche no se incorpora por el formulario de compra de insumos.

</div>
''',
     'Lote finalizado, consumo trazable y productos terminados disponibles para la venta. El stock refleja lo obtenido, no una suposición basada en lo planificado.',
     ['Produccion/CalcularRequerimientosProduccion.php','Produccion/IniciarProduccion.php','Produccion/FinalizarProduccion.php','Produccion/RegistrarIngresoLecheDesdeEntrega.php'],['acopio','insumos','ventas-caja'])

page('insumos','Flujo de insumos y compras','Revisar antes de ingresar al almacén','insumos',
     'Una compra física se convierte en inventario cuando una persona revisa y confirma sus datos. El OCR ayuda a leer la boleta, pero no identifica por sí solo equivalencias ni confirma la compra.',
     [('factory','Encargado de planta','Revisa la boleta y confirma la compra.'),('account-cog','Administrador','Mantiene el catálogo y las presentaciones.'),('store','Proveedor comercial','Entrega insumos y comprobante físico.')],
'''flowchart TB
  A([Inicio · Compra física]) --> B[Recibir boleta e insumos]
  B --> C{¿Cómo registrar?}
  C -->|Boleta| D[Escanear y extraer con OCR]
  C -->|Manual| E[Ingresar datos]
  D --> F[Planta · Revisar datos]
  E --> F
  F --> G[Seleccionar insumos y presentaciones]
  G --> H[Convertir a unidad base configurada]
  H --> I[Previsualizar movimiento]
  I --> J{¿Datos correctos?}
  J -->|No| F
  J -->|Sí| K[Persona confirma compra]
  K --> L[Servidor recalcula e incrementa stock]
  L --> M{¿Total conocido y positivo?}
  M -->|Sí| N[Salida de caja · INSUMOS]
  M -->|No| O[Sin movimiento de caja]
  N --> Z([Fin · Compra y detalle guardados])
  O --> Z''',
     ['Comprar físicamente los insumos y recibir la boleta; estas son actividades externas previas al registro.',
      'Escanear la boleta para obtener propuestas OCR o ingresar los datos manualmente.',
      'Revisar los datos, asociar cada línea al insumo correcto y elegir su presentación comercial activa.',
      'Convertir las presentaciones a la unidad base mediante la equivalencia configurada y previsualizar el stock resultante.',
      'Confirmar expresamente. El servidor recalcula, guarda la compra, sus detalles y los movimientos INGRESO_COMPRA.',
      'Si el total es conocido y positivo, registrar la salida de caja. Sin importe conocido, la compra sí puede ingresar al inventario, pero no se inventa una salida de dinero.'],
     '''<figure class="ec-flow-inline-image" markdown="1">

![Ilustración de una boleta: dos presentaciones de sal y revisión humana](../assets/images/flujos/boleta.svg){ width="480" height="400" }

<figcaption>Boleta ilustrativa: los datos se revisan antes de confirmar.</figcaption>
</figure>
''' + cards([('','Presentación','**2 sacos × 25 kg = 50 kg.** Si la unidad base del catálogo es gramo, el ingreso equivale a 50 000 g. Es un ejemplo, no una equivalencia inferida del texto OCR.'),('orange','Revisión obligatoria','La presentación debe pertenecer al insumo y estar activa. La cantidad debe ser positiva y el precio, si se indica, no negativo.'),('green','Importe de compra','Se usa el total indicado; si falta y todas las líneas tienen precio, se calcula cantidad × precio. Si falta un precio de línea, el total calculado queda desconocido.')]) + '''
## Excepciones que debe resolver la persona

No se admiten líneas vacías, insumos inactivos ni presentaciones inválidas. La leche se rechaza en este formulario: su stock aumenta mediante Acopio. Corregir una propuesta OCR o una vista previa no mueve existencias; solo la confirmación registra la compra.
''',
     'Compra, presentación utilizada y movimiento de inventario quedan vinculados. La salida de caja se registra únicamente si existe un total positivo conocido.',
     ['Produccion/ProcesarBoletaCompra.php','Produccion/CalcularCompraInsumos.php','Produccion/RegistrarCompraInsumos.php','Caja/RegistrarMovimientoCaja.php'],['produccion','finanzas'])

page('ventas-caja','Flujo de ventas y compradores','Vender con stock y tarifa vigentes','ventas',
     'Ventas identifica al cliente, consulta el producto y registra la operación con su tarifa aplicable. El servidor determina el precio y comprueba las existencias al confirmar.',
     [('cash-register','Personal de ventas','Selecciona cliente, producto, cantidad y forma de pago.'),('account-group','Comprador','Público general, comprador registrado o proveedor.'),('account-cog','Administrador','Configura las tarifas de los productos.')],
'''flowchart TB
  A([Inicio · Ventas]) --> B[Identificar comprador]
  B --> C[Seleccionar producto y cantidad]
  C --> D{¿Stock suficiente y producto activo?}
  D -->|No| E[Mostrar error y revisar]
  E --> C
  D -->|Sí| F[Determinar tarifa aplicable]
  F --> G{¿Forma de pago?}
  G -->|Contado| H[Registrar venta]
  G -->|Descuento de planilla| I{¿Proveedor con saldo suficiente?}
  I -->|No| E
  I -->|Sí| H
  H --> J[Descontar stock y habilitar comprobante]
  J --> K{¿Contado?}
  K -->|Sí| L[Entrada de caja · VENTA]
  K -->|No| M[Descuento en pago semanal]
  L --> Z([Fin · Venta REGISTRADA])
  M --> Z''',
     ['Identificar al cliente: público general, comprador registrado o proveedor activo. Una venta no puede asociarse a proveedor y comprador registrado simultáneamente.',
      'Seleccionar un producto activo y una cantidad válida. Comprobar stock; el servidor vuelve a validarlo con el producto bloqueado dentro de la transacción.',
      'Determinar la tarifa: PROVEEDOR para proveedor registrado, MAYORISTA si alcanza el mínimo configurado, o PUBLICO en los demás casos. Debe existir un precio aplicable.',
      'Seleccionar CONTADO o DESCUENTO_PLANILLA. La segunda opción requiere proveedor y no puede superar su pago semanal estimado disponible.',
      'Registrar la venta y descontar stock. Se guarda el precio aplicado para que futuros cambios de tarifa no alteren la operación pasada.',
      'Consultar o generar la boleta disponible. La venta al contado registra entrada de caja; la de planilla se cobra descontando del pago al proveedor.'],
     cards([('green','CONTADO','Genera ingreso real de caja por el importe total de la venta.'),('orange','DESCUENTO_PLANILLA','Solo para proveedor con saldo suficiente. **No genera entrada inmediata de efectivo.**'),('purple','Tarifa del servidor','No la decide el navegador. Si el precio esperado enviado por el cliente cambió, se solicita revisar la venta.')]) + '''
## Cuando se trabaja desde el teléfono

La app implementa ventas pendientes al contado con catálogo, precio y stock conocidos. El servidor puede rechazarlas al sincronizar si cambiaron las existencias o la tarifa. El descuento de planilla necesita conexión para validar saldo. El PDF de boleta requiere una venta sincronizada y conexión; registrar un comprador nuevo también necesita conexión.
''',
     'Venta REGISTRADA, salida de producto y comprobante consultable. La forma de pago decide si el dinero entra hoy a caja o se descuenta después de la liquidación.',
     ['Ventas/RegistrarVentaProducto.php','Ventas/ObtenerTarifasProducto.php','Ventas/EstimarSaldoDisponibleProveedor.php','Caja/RegistrarMovimientoCaja.php'],['produccion','liquidacion-pagos','finanzas','integracion'])

page('finanzas','Flujo de caja y finanzas','Cada movimiento tiene una operación de origen','finanzas',
     'Caja reúne las entradas y salidas registradas por las operaciones del negocio. El administrador consulta el saldo y puede seguir cada movimiento hasta la venta, compra o pago que lo originó.',
     [('account-cog','Administrador','Consulta saldo, historial y detalle de los movimientos.'),('cash-register','Ventas','Origina entradas al registrar ventas al contado.'),('factory','Planta y pagos','Originan salidas por compras y pagos registrados.')],
'''flowchart TB
  A([Inicio · Confirmar operación]) --> B{¿Qué operación mueve dinero?}
  B --> C[Venta al contado]
  B --> D[Compra de insumos con total positivo]
  B --> E[Pago al proveedor]
  B --> F[Pago al trabajador]
  C -->|ENTRADA · VENTA| G[Caja EcoLact]
  D -->|SALIDA · INSUMOS| G
  E -->|SALIDA · PROVEEDOR| G
  F -->|SALIDA · TRABAJADOR| G
  G --> H[Concepto, fecha, importe y referencia]
  H --> I[Administrador · Historial y detalle]
  I --> J[Saldo: entradas menos salidas]
  J --> Z([Fin · Seguimiento financiero])
  X[Venta de planilla] -. Sin entrada inmediata .-> Y[Descuento del pago semanal]
  W[Compra sin total conocido] -. Sin salida inventada .-> V[Solo inventario]''',
     ['Confirmar la operación que mueve dinero: una venta al contado, una compra con total positivo o un pago realizado.',
      'Registrar el movimiento de caja dentro de la misma transacción. Si falla el registro de caja, la operación tampoco queda confirmada.',
      'Guardar categoría, concepto, descripción, importe, fecha, referencia de origen y usuario que registró la operación.',
      'Consultar el historial y abrir el detalle para reconocer comprador, proveedor o trabajador y su operación asociada.',
      'Seguir el saldo acumulado y los indicadores de ingresos, salidas y número de movimientos de hoy.'],
     cards([('green','Entradas','**VENTA:** importe de las ventas registradas al contado.'),('red','Salidas de compras','**INSUMOS:** compra con total conocido mayor que cero.'),('orange','Salidas de pagos','**PROVEEDOR** y **TRABAJADOR:** importe neto del pago realizado.')]) + '''
## Cómo leer un movimiento

<div class="ec-flow-table" markdown="1">

| Dato | Para qué sirve |
| --- | --- |
| Origen y categoría | Distinguir una venta, compra o pago |
| Concepto y descripción | Saber qué se registró y a quién corresponde |
| Importe y dirección | Reconocer cuánto entró o salió |
| Fecha | Ubicar cuándo se registró el movimiento |
| Referencia y detalle | Relacionar el movimiento con la operación concreta |
| Usuario de registro | Identificar quién confirmó la operación |

</div>

Las ventas conservan la fecha de su operación. Las compras usan la hora de registro en caja y conservan la fecha de compra en el detalle. Los pagos usan el momento efectivo de registro (`pagadoAt`) cuando está disponible.

<div class="ec-flow-result" markdown="1">

**Saldo = suma de entradas − suma de salidas.** Es el saldo de los movimientos registrados. Una compra sin total no genera salida y, por tanto, su desembolso desconocido no aparece en ese saldo.

</div>

## Evitar duplicar el dinero

Los movimientos se guardan una sola vez por tipo e identificador de referencia. Una venta a cuenta del pago no entra a caja: reduce el neto que luego sale al pagar al proveedor. Tampoco se registra dinero al guardar un borrador OCR, planificar producción o calcular un pendiente.

Las rutas de Caja revisadas ofrecen consulta y panel de detalle al administrador. No se documenta un formulario de movimientos manuales: no aparece en ese flujo implementado.
''',
     'Historial financiero relacionado con operaciones reales. El administrador puede explicar cada entrada, cada salida y el saldo registrado.',
     ['Caja/RegistrarMovimientoCaja.php','Caja/CajaResumen.php','Caja/Contracts/CajaQuery.php','Pago/RegistrarPagoObligacion.php'],['ventas-caja','insumos','liquidacion-pagos','personal-asistencia'])

page('personal-asistencia','Asistencia y personal','De la marcación al pago del trabajador','asistencia',
     'La asistencia se registra en una ventana diaria y alimenta el control del personal. Las obligaciones se acumulan por mes calendario y pasan a pago después del cierre correspondiente.',
     [('badge-account','Trabajador','Presenta su QR y consulta su asistencia.'),('account-cog','Administrador','Registra, corrige dentro del horario y paga.'),('calendar-check','EcoLact','Aplica cierre, faltas y cálculo del periodo.')],
'''flowchart TB
  A([Inicio · Trabajador presenta QR]) --> B[Administrador identifica trabajador]
  B --> C{¿Activo y vigente hoy?}
  C -->|No| X[Rechazar marcación]
  C -->|Sí| D{¿Hoy antes de las 07:00?}
  D -->|No| E[Solo consulta del día cerrado]
  D -->|Sí| F{¿Ya existe jornada?}
  F -->|No| G[Registrar PRESENTE]
  F -->|Presente| H[Confirmar registro existente]
  F -->|Falta| I[Corrección administrativa con motivo]
  I --> G
  G --> J[Recalcular acumulado EN_CURSO]
  H --> J
  E --> K[Control de faltas de días cerrados]
  K --> J
  J --> L[Cerrar mes · PENDIENTE]
  L --> M[Administrador registra pago exacto]
  M --> N[PAGADO y salida de caja]
  N --> Z([Fin · Comprobante e historial])''',
     ['Identificar por QR al trabajador. Debe estar activo, haber ingresado y estar vigente en la fecha. El administrador no marca asistencia: cobra sueldo fijo mensual.',
      'Registrar o corregir únicamente la asistencia de hoy antes de las 07:00, según la zona horaria configurada del negocio. Desde las 07:00:00 el día queda cerrado.',
      'Registrar PRESENTE. Un segundo escaneo del mismo día no duplica la jornada; una FALTA existente requiere corrección administrativa expresa con motivo.',
      'Controlar los días cerrados: MarcarFaltasAutomaticas registra faltas aplicables donde no hay jornada. No se corrigen días cerrados ni jornadas de periodos ya pagados.',
      'Con sueldo configurado, abrir o recalcular la obligación EN_CURSO del mes calendario. El cierre de nómina transforma las obligaciones del mes terminado en PENDIENTE y abre el nuevo mes.',
      'Registrar el pago por el monto calculado, conservar el comprobante y generar la salida de caja de TRABAJADOR.'],
     cards([('orange','Antes de las 07:00','Marcación y corrección de hoy. La restricción se comprueba en el servidor.'),('purple','Durante el mes','La obligación **EN_CURSO** acumula el progreso con la configuración salarial del trabajador.'),('green','Después del cierre','La obligación pasa a **PENDIENTE**; tras registrar el pago queda **PAGADO**.')]) + '''
## Permisos y excepciones

La corrección requiere motivo, un estado distinto y una jornada todavía abierta. No se sustituye silenciosamente una falta por un escaneo. Si no existe sueldo activo configurado, no se abre ni recalcula una obligación de personal mediante el generador.

El personal sin asistencia válida puede consultar, pero las rutas protegidas impiden operar. En peticiones móviles diferidas se comprueba la asistencia de la fecha del evento aceptada por el servidor; guardar algo localmente no garantiza que se autorice al sincronizar.

La programación de cierre mensual está declarada para el día 1 a las 00:05 en `routes/console.php`. Su ejecución efectiva depende de que el programador de tareas de Laravel esté funcionando; la documentación no verifica el despliegue del servidor.
''',
     'Jornadas trazables, faltas y correcciones controladas, obligación mensual y pago ligado a su salida de caja.',
     ['Pago/RegistrarAsistenciaQr.php','Pago/VentanaAsistencia.php','Pago/CorregirJornada.php','Pago/MarcarFaltasAutomaticas.php','Pago/GenerarObligacionesPersonal.php','Pago/CerrarNominaMensual.php','Pago/RegistrarPagoObligacion.php'],['finanzas','integracion'])

page('integracion','Integración móvil y web','Una operación local no es aún una confirmación','integracion',
     'La app combina consultas almacenadas y operaciones pendientes con solicitudes al backend Laravel. Los permisos, el stock y las reglas definitivas se comprueban en el servidor.',
     [('account','Usuario','Ejecuta la operación y atiende errores.'),('cellphone','App móvil','Consulta conectividad, guarda datos y sincroniza.'),('server','Backend Laravel','Autoriza, valida, registra y devuelve resultados.')],
'''sequenceDiagram
  actor U as Usuario
  participant A as App móvil
  participant Q as Cola local
  participant B as Laravel
  U->>A: Ejecutar operación
  A->>A: Comprobar conectividad y soporte local
  alt Operación requiere conexión y no hay red
    A-->>U: Solicitar conexión
  else Operación admite cola local
    A->>Q: Guardar datos, hora y clave local
    A-->>U: Mostrar pendiente
    Note over A,Q: Sincronizar al recuperar red o solicitarlo
    Q->>B: Enviar operación pendiente
    B->>B: Validar sesión, permisos y reglas
    alt Operación aceptada
      B->>B: Registrar datos y efectos relacionados
      B-->>A: Resultado e identificador
      A->>Q: Marcar SYNCED
      A-->>U: Actualizar consultas
    else Rechazo que requiere revisión
      B-->>A: Error de permisos o validación
      A->>Q: Marcar NEEDS_REVIEW
      A-->>U: Mostrar motivo
    else Fallo transitorio
      A->>Q: PENDING o revisión al agotar intentos
    end
  else Operación en línea
    A->>B: Solicitud autenticada
    B->>B: Autorizar, validar y registrar
    B-->>A: Resultado o error
    A-->>U: Mostrar resultado
  end''',
     ['El usuario inicia una consulta u operación. La app comprueba conectividad y los datos locales necesarios para esa funcionalidad.',
      'Las operaciones que admiten cola guardan datos, hora del evento e identificador local. Otras exigen conexión y muestran el motivo.',
      'Al sincronizar, el motor recorre pendientes en orden de creación y despacha cada tipo a su manejador. Un bloqueo evita barridos concurrentes.',
      'Laravel valida sesión, rol, asistencia cuando corresponde y reglas de negocio. Los registros locales todavía pueden ser rechazados por stock, precio o permisos.',
      'Una respuesta válida marca SYNCED y conserva el identificador del servidor. Los rechazos pasan a NEEDS_REVIEW; los fallos transitorios vuelven a PENDING hasta el límite.',
      'La app actualiza los datos disponibles y presenta el resultado. Los portales web consultan los registros ya aceptados por el backend.'],
     cards([('orange','PENDING → SYNCING','Operación guardada que aún debe enviarse. No equivale a un registro confirmado en el servidor.'),('green','SYNCED','El servidor respondió correctamente y se conserva su identificador.'),('red','NEEDS_REVIEW','Error de validación, permisos o agotamiento de los cinco intentos transitorios. Requiere atención.')]) + '''
## Alcance real del trabajo sin conexión

<div class="ec-flow-table" markdown="1">

| Funcionalidad | Comportamiento comprobado |
| --- | --- |
| Acopio | Entregas, inicio y «No entregó» cuentan con operaciones de cola; la identificación puede usar datos locales disponibles |
| Finalizar recorrido | Requiere conexión en `AcopiadorRepository` |
| Calidad | Selección manual, captura de ticket y análisis tienen almacenamiento o cola; el OCR del servidor se procesa al enviar |
| Sorteo de muestra | Requiere conexión y firma del servidor |
| Planta | Inicio y cierre de producción y compra revisada tienen manejadores de sincronización; el servidor vuelve a validar |
| Ventas | Contado con precio y stock conocidos puede quedar pendiente; descuento de planilla requiere conexión |
| Boleta PDF y comprador nuevo | Requieren conexión en la app de ventas |
| Proveedor | Consultas con datos almacenados y actualización al conectar; no se promete información reciente sin sincronizar |

</div>

## Reintentos e idempotencia

`SyncManager` limita a **cinco intentos transitorios** antes de exigir revisión. Un rechazo de validación o permiso no se reintenta indefinidamente. Una operación sin manejador disponible permanece pendiente.

Las entregas reutilizan su token de idempotencia. Las rutas de inicio/cierre de producción, confirmación de compra y venta usan el middleware `idempotente`; sus clientes envían la clave correspondiente. No se extiende esa garantía a todas las rutas: el manejador de análisis de calidad no usa esa cabecera para guardar y finalizar. La selección de muestra tiene además su propia protección de reenvío en el dominio.

<div class="ec-flow-info warning" markdown="1">

**Dato local y dato confirmado son distintos.** Recuperar conectividad no implica aceptación automática: el usuario debe revisar las operaciones rechazadas. Tampoco se promete sincronización en segundo plano cuando la aplicación está cerrada.

</div>
''',
     'Operaciones aceptadas reflejadas en consultas web y móviles; pendientes y errores identificables sin presentar una garantía universal de funcionamiento offline.',
     ['Acopio/RegistrarEntrega.php','Calidad/SeleccionarMuestraCalidad.php'],['acopio','consulta-proveedor','ventas-caja','personal-asistencia'])

TITLES = {p['slug']: p['title'] for p in PAGES}
for number,p in enumerate(PAGES,1):
    slug=p['slug']
    body=p['body']
    if slug=='index':
        nav='<div class="ec-flow-grid" markdown="1">\n\n'
        for n,q in enumerate(PAGES[1:],2):
            nav+=f'[**{n:02d} · {q["title"]}**<span>{q["subtitle"]} →</span>]({q["slug"]}.md){{ .ec-flow-card }}\n\n'
        body=body.replace('{{NAV_CARDS}}',nav+'</div>')
    actor_html='<div class="ec-flow-actors" markdown="1">\n\n'
    for icon,title,desc in p['actors']:
        actor_html+=f'<div class="ec-flow-card" markdown="1">\n\n:material-{icon}:\n\n**{title}**\n\n{desc}\n\n</div>\n\n'
    actor_html+='</div>'
    label='Vista general de flujos' if slug=='index' else p['title']
    diagram=p['diagram']+'\n'
    if diagram.startswith('flowchart'):
        diagram+='  classDef default fill:#eaf3ff,stroke:#7ca5df,color:#123d91,rx:7,ry:7;\n'
        diagram+='  classDef decision fill:#fff3df,stroke:#dca34d,color:#754c0c;\n'
        diagram+='  classDef terminal fill:#e6f7ed,stroke:#72b692,color:#145d3b;\n'
        decisions=list(dict.fromkeys(re.findall(r'\b(\w+)\{',p['diagram'])))
        terminals=list(dict.fromkeys(re.findall(r'\b(\w+)\(\[',p['diagram'])))
        if decisions: diagram+='  class '+','.join(decisions)+' decision;\n'
        if terminals: diagram+='  class '+','.join(terminals)+' terminal;\n'
    (DOCS/'assets/diagrams/flujos'/f'{slug}.mmd').write_text(diagram,encoding='utf-8')
    steps='\n'.join(f'{i}. {s}' for i,s in enumerate(p['steps'],1))
    evidence='\n'.join(f'- `EcoLactWeb/app/Application/{s}`' for s in p['sources'])
    evidence+='\n- `EcoLactWeb/routes/web.php` y `EcoLactWeb/routes/api.php`'
    if slug in ['consulta-proveedor','integracion','ventas-caja']:
        evidence+='\n- `EcoLactMobile/shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/feature/` (rutas, repositorios y pantallas del rol correspondiente).'
    if slug=='integracion':
        evidence+='\n- `core/sync/SyncManager.kt`, `SyncQueueRepository.kt` y manejadores de sincronización de cada funcionalidad.'
    links=([('index','← Todos los flujos')] if slug!='index' else [])+[(s,TITLES[s]+' →') for s in p['links']]
    navlinks='\n\n'.join(f'[{title}]({s}.md)' for s,title in links)
    caption='Fotografía ilustrativa generada para esta documentación.' if p['image'] in ['pagos','proveedor','asistencia','produccion'] else 'Recurso visual del proyecto. Ilustración del proceso.'
    text=f'''---
title: "{p['title']}"
description: "{p['subtitle']} en EcoLact: actores, decisiones y resultados verificados en el repositorio."
---

<div class="ec-flujos" markdown="1">
<div class="ec-flow-banner">{number:02d} · {label}</div>
<div class="ec-flow-content" markdown="1">

<div class="ec-flow-hero" markdown="1">
<div markdown="1">

<span class="ec-flow-kicker">EcoLact / Flujos de negocio</span>

# {p['title']}

<p class="ec-flow-subtitle">{p['subtitle']}</p>

{p['intro']}

</div>
<figure markdown="1">

![{p['subtitle']}: {p['title']}](../assets/images/flujos/{p['image']}.webp){{ width="1200" height="800" }}

<figcaption>{caption}</figcaption>
</figure>
</div>

{actor_html}

## Así funciona el proceso

<div class="ec-flow-diagram" markdown="1">

![Diagrama de {p['title']}: decisiones, actividades y resultados](../assets/diagrams/flujos/{slug}.svg)

[Ampliar diagrama](../assets/diagrams/flujos/{slug}.svg) · [Descargar fuente Mermaid](../assets/diagrams/flujos/{slug}.mmd)

<details markdown="1">
<summary>Ver la fuente editable del diagrama</summary>

```text
{diagram.rstrip()}
```

</details>
</div>

## Paso a paso

<div class="ec-flow-steps" markdown="1">

{steps}

</div>

{body}

<div class="ec-flow-result" markdown="1">

**Resultado del proceso**

{p['result']}

</div>

<details class="ec-flow-evidence" markdown="1">
<summary>Trazabilidad con el código revisado</summary>

Revisión documental: 28 de octubre de 2026. Las rutas siguientes identifican las fuentes locales; no implican haber ejecutado el sistema de negocio.

{evidence}

</details>

<div class="ec-flow-links" markdown="1">

{navlinks}

</div>
</div>
</div>
'''
    (DOCS/'flujos'/f'{slug}.md').write_text(text,encoding='utf-8')

(ROOT/'tools/flujos-manifest.json').write_text(json.dumps([{'slug':p['slug'],'title':p['title']} for p in PAGES],ensure_ascii=False,indent=2),encoding='utf-8')
print('11 páginas y 11 fuentes Mermaid creadas.')
