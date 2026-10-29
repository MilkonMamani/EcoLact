"""Contenido del Manual de Usuario de EcoLact Móvil.

Convenciones de los datos:
- Figura: dict(img=<captura>, pie=<texto>, marcas=[(n, ancla), ...]).
  · img "pNN_xMMM": captura del manual anterior (28/10/2026), con datos personales difuminados.
  · Las capturas del Encargado de planta (p03–p17) traen sus marcas incrustadas: marcas=None.
  · ancla: ('fila', 'texto' [, alto]) control a todo el ancho centrado en ese texto;
           ('texto', 'texto' [, margen]) recuadro ajustado al texto;
           ('zona', 'texto inicial', 'texto final' [, margen]) franja a todo el ancho;
           ('caja', x1, y1, x2, y2) coordenadas en píxeles.
    Un sufijo '#2' en el texto elige la segunda aparición.
- Bloque: dict(figs=[...], pasos=[(n, titulo, instruccion), ...]) o dict(elementos=[(n, elemento, función)]).
- Estados: VER = verificado en interfaz y en código; PAR = verificado parcialmente; COD = solo en código.
"""

VER = "Verificado en interfaz"
PAR = "Verificado parcialmente"
COD = "Implementado en código; pendiente de verificación visual"

OBS = "Observado en las capturas del 28/10/2026."
PREV = "Previsto según la implementación actual; pendiente de validación funcional."

# ============================================================================ CAPÍTULO 1
INTRODUCCION = {
    "titulo": "Introducción",
    "bloques": [
        ("h2", "Propósito"),
        ("p", "Este manual explica, paso a paso, cómo utilizar la aplicación móvil **EcoLact** de Ecolácteos Huata. "
              "Cada capítulo está dedicado a un rol y describe únicamente las funciones disponibles en la aplicación "
              "para ese rol."),
        ("h2", "Alcance"),
        ("p", "El manual cubre los cinco roles con interfaz propia en la aplicación móvil:"),
        ("tabla", ["Capítulo", "Rol", "Funciones principales"], [
            ["3", "Encargado de planta", "Insumos, compras de insumos, producción, productos terminados y recetas."],
            ["4", "Personal de ventas", "Productos y precios, compradores, registro de ventas, historial y boleta."],
            ["5", "Analista de calidad", "Muestreo de entregas, análisis de calidad, proveedores y comunicados."],
            ["6", "Proveedor", "Código QR, entregas, resultados de calidad, pagos, compras y comunicados."],
            ["7", "Acopiador", "Registro de entregas, recorridos, proveedores asignados y seguimiento de calidad."],
        ], [2.0, 4.0, 10.6]),
        ("p", "El rol **Administrador** no opera desde la aplicación móvil: si inicia sesión, la aplicación muestra el "
              "mensaje «Este rol (administrador) se gestiona desde el sistema web, no desde la app móvil.» y solo permite "
              "cerrar sesión. Sus funciones se describen en el Manual de Usuario del Administrador de EcoLactWeb."),
        ("h2", "Requisitos"),
        ("lista", [
            "Teléfono Android con la aplicación EcoLact instalada.",
            "Una cuenta de usuario activa, creada por el administrador desde EcoLactWeb, con el rol correspondiente.",
            "Conexión a Internet para iniciar sesión. Después, varias operaciones pueden realizarse sin conexión "
            "(sección 2.4).",
            "Cámara del teléfono para escanear códigos QR, boletas de compra y tickets del analizador.",
            "Para los trabajadores (planta, ventas, calidad y acopio): asistencia del día registrada por el administrador "
            "(sección 2.3).",
        ]),
        ("h2", "Convenciones"),
        ("tabla", ["Elemento", "Significado"], [
            ["«Texto»", "Nombre exacto de un botón, campo, pestaña o mensaje tal como aparece en la pantalla."],
            ["Menú › Opción", "Ruta de navegación: abra el menú lateral (☰) y elija la opción indicada."],
            ["Marca numerada", "Recuadro rojo con un número en la captura. El número coincide con el paso o elemento del texto."],
            ["Código MOB-XXX-NNN", "Identificador del procedimiento: MOB = móvil; GEN, PLA, VEN, CAL, PRV y ACO = capítulo."],
            ["Datos difuminados", "DNI, teléfonos, usuarios y códigos QR se ocultan en las capturas para proteger datos personales."],
        ], [4.0, 12.6]),
        ("h2", "Estado de verificación"),
        ("p", "Cada procedimiento indica cómo se comprobó su contenido. La aplicación se contrastó con su código fuente "
              "actual (29/10/2026); las capturas proceden de la aplicación en ejecución (28/10/2026)."),
        ("tabla", ["Estado", "Significado"], [
            [VER, "Existe una captura auténtica de la pantalla y su contenido coincide con el código actual."],
            [PAR, "Hay capturas de una parte del procedimiento; el resto se describe según el código."],
            [COD, "Función nueva o sin captura. Se describe según el código actual y debe validarse en la aplicación."],
        ], [5.4, 11.2]),
        ("nota", "IMPORTANTE", "Durante la elaboración de este manual no se confirmaron operaciones que modifican datos "
                 "(ventas, compras, producciones, entregas, muestreos, análisis ni pagos). Cuando un resultado no aparece "
                 "en las capturas, se indica como «previsto» y queda pendiente de validación."),
    ],
}

# ============================================================================ CAPÍTULO 2
GENERAL = {
    "titulo": "Acceso y navegación general",
    "intro": "Este capítulo describe las funciones comunes a todos los roles: iniciar sesión, reconocer la estructura "
             "de la aplicación, registrar la asistencia del día y trabajar sin conexión.",
    "procedimientos": [
        {
            "codigo": "MOB-GEN-001", "titulo": "Iniciar sesión", "estado": VER,
            "proposito": "Ingresar a la aplicación con la cuenta asignada por el administrador.",
            "acceso": "Al abrir la aplicación sin una sesión activa se muestra la pantalla «Ecolácteos Huata».",
            "requisitos": "Usuario y contraseña activos. Conexión a Internet.",
            "bloques": [
                {"figs": [dict(img="p52_x213", pie="Pantalla de inicio de sesión.", marcas=[
                    (1, ("caja", 55, 880, 857, 1022)), (2, ("caja", 55, 1086, 857, 1226)), (3, ("boton", 1))])],
                 "pasos": [
                     (1, "Escribir el usuario", "Toque el campo «Usuario» y escriba su nombre de usuario."),
                     (2, "Escribir la contraseña", "Toque el campo «Contraseña» y escriba su contraseña. Los caracteres se muestran ocultos."),
                     (3, "Ingresar", "Pulse «Iniciar sesión». La aplicación abre la pantalla «Inicio» del rol de la cuenta."),
                 ]},
            ],
            "resultado": ("La aplicación identifica el rol de la cuenta y muestra su pantalla «Inicio». La sesión queda "
                          "guardada en el teléfono hasta que el usuario la cierre.", OBS),
            "validaciones": [
                ["Usuario o contraseña vacíos", "«Ingrese usuario y contraseña»"],
                ["Credenciales incorrectas", "«Usuario o contraseña incorrectos»"],
                ["Cuenta sin acceso a la aplicación", "«Esta cuenta no tiene acceso»"],
                ["Sin conexión con el servidor", "«No se pudo conectar. Verifique su conexión a internet.»"],
            ],
        },
        {
            "codigo": "MOB-GEN-002", "titulo": "Reconocer la estructura de la aplicación", "estado": VER,
            "proposito": "Identificar los elementos de navegación que comparten todos los roles.",
            "acceso": "Cualquier pantalla principal después de iniciar sesión.",
            "requisitos": "Sesión iniciada.",
            "bloques": [
                {"figs": [dict(img="p53_x210", pie="Pantalla «Inicio» (ejemplo: Acopiador).", marcas=[
                    (1, ("caja", 18, 160, 125, 285)), (2, ("caja", 760, 160, 900, 285)), (3, ("zona", "Inicio#2", "Proveedores", 40))]),
                          dict(img="p53_x215", pie="Menú lateral abierto (ejemplo: Acopiador).", marcas=[
                    (4, ("zona", "OPERACION", "Seguimiento", 30)), (5, ("fila", "Cerrar", 45))])],
                 "elementos": [
                     (1, "Botón de menú (☰)", "Abre el menú lateral. En pantallas secundarias se reemplaza por la flecha «Volver»."),
                     (2, "Indicador de conexión y campana", "Punto verde: con conexión; punto naranja: sin conexión. La campana abre «Notificaciones»."),
                     (3, "Barra inferior", "Tres accesos directos a las pantallas principales del rol."),
                     (4, "Menú lateral", "Agrupa las opciones del rol en secciones como Operación, Gestión, Comunicación y Sistema."),
                     (5, "«Cerrar sesión»", "Cierra la sesión del usuario en el teléfono."),
                 ]},
                {"tabla": (["Rol", "Barra inferior", "Secciones del menú lateral"], [
                    ["Encargado de planta", "Inicio · Producción · Inventario", "Operación, Inventario, Gestión, Sistema"],
                    ["Personal de ventas", "Inicio · Vender · Historial", "Operación, Gestión, Comunicación, Sistema"],
                    ["Analista de calidad", "Inicio · Muestreo · Historial", "Operación, Consulta, Comunicación, Sistema"],
                    ["Proveedor", "Inicio · Entregas · Pagos", "Operación, Gestión, Comunicación, Sistema"],
                    ["Acopiador", "Inicio · Entregas · Proveedores", "Operación, Gestión, Comunicación, Sistema"],
                ], [3.6, 5.2, 7.8])},
            ],
            "resultado": ("El usuario reconoce dónde abrir cada sección y el estado de conexión del teléfono.", OBS),
        },
        {
            "codigo": "MOB-GEN-003", "titulo": "Registrar la asistencia del día", "estado": VER,
            "proposito": "Habilitar las operaciones del día mostrando el código QR personal al administrador.",
            "acceso": "Aviso «Asistencia pendiente» en «Inicio», acción rápida «Mi QR» o Menú › Mi QR.",
            "requisitos": "Roles Encargado de planta, Personal de ventas, Analista de calidad y Acopiador. "
                          "El Proveedor no registra asistencia.",
            "bloques": [
                {"figs": [dict(img="p08_x95", pie="Aviso «Asistencia pendiente» en «Inicio».", marcas=None),
                          dict(img="p08_x94", pie="Pantalla «Mi QR / Asistencia».", marcas=None)],
                 "pasos": [
                     (1, "Revisar el aviso", "Al iniciar el día, «Inicio» muestra «Asistencia pendiente» mientras el servidor no registre su asistencia."),
                     (2, "Abrir el código", "Pulse «Ver mi QR». También puede usar la acción rápida «Mi QR» o el menú lateral."),
                     (3, "Mostrar el QR", "Muestre el código al administrador para que lo escanee desde EcoLactWeb."),
                     (4, "Esperar la habilitación", "El aviso de la pantalla recuerda que el administrador usa este QR para la asistencia diaria. Con conexión, la aplicación vuelve a consultar el estado cada minuto; cuando la asistencia queda registrada, el aviso de «Inicio» desaparece sin cerrar sesión."),
                 ]},
            ],
            "resultado": ("Sin asistencia válida del día el usuario puede consultar información, pero no operar. "
                          "Después del registro, el aviso deja de mostrarse y las operaciones quedan habilitadas.",
                          "Aviso observado en las capturas; habilitación según la implementación actual."),
            "validaciones": [
                ["Operación sin asistencia registrada", "«Necesitas registrar tu asistencia antes de realizar esta operación.»"],
                ["Sin conexión y sin asistencia confirmada hoy", "«Asistencia no verificada para hoy. Conéctate a Internet después de registrar tu asistencia para habilitar las operaciones.»"],
                ["Trabajador sin QR activo", "«Tu QR de asistencia no está activo. Consulta con el administrador.»"],
            ],
            "notas": ["El QR es personal: identifica al trabajador con su nombre, rol y DNI. No lo comparta ni lo use para cobrar ventas.",
                      "La confirmación de asistencia vale solo para el día en que se registró; al día siguiente debe registrarse de nuevo."],
        },
        {
            "codigo": "MOB-GEN-004", "titulo": "Trabajar sin conexión y sincronizar", "estado": PAR,
            "proposito": "Continuar operando cuando el teléfono pierde la conexión a Internet.",
            "acceso": "Automático. El indicador de la barra superior cambia a naranja sin conexión.",
            "requisitos": "Haber iniciado sesión con conexión y, para los trabajadores, tener la asistencia del día confirmada mientras había conexión.",
            "bloques": [
                {"figs": [dict(img="p56_x223", pie="Entrega guardada sin conexión (Acopiador).", marcas=[
                    (1, ("texto", "Pendiente", 14))])],
                 "pasos": [
                     (1, "Operar normalmente", "Registre la operación como de costumbre. La aplicación la guarda en el teléfono y la marca como «Pendiente» o «Pendiente de sincronización»."),
                     (2, "Recuperar la conexión", "No es necesario hacer nada más: al volver la conexión, la aplicación envía automáticamente lo pendiente."),
                     (3, "Comprobar el envío", "Revise el historial del módulo: el registro debe cambiar a «Sincronizada» o mostrar el resultado del servidor."),
                 ]},
                {"tabla": (["Rol", "Operaciones disponibles sin conexión", "Requieren conexión"], [
                    ["Encargado de planta", "Iniciar y finalizar producción; registrar compra manual; tomar la foto de una boleta (se lee al volver la conexión).", "Lectura automática (OCR) de la boleta."],
                    ["Personal de ventas", "Venta al contado con el precio guardado (se valida al enviarla).", "Pago con «Descuento del pago semanal»; registrar un comprador."],
                    ["Analista de calidad", "Muestreo directo; selección manual de muestras; análisis con valores ingresados a mano.", "Elegir una muestra al azar; lectura automática del ticket."],
                    ["Acopiador", "Registrar entregas; registrar paradas del recorrido.", "—"],
                    ["Proveedor", "Consultar la información guardada; marcar notificaciones como leídas.", "Actualizar datos desde el servidor."],
                ], [3.4, 7.6, 5.6])},
            ],
            "resultado": ("Las operaciones quedan guardadas en el teléfono y se envían solas al recuperar la conexión. "
                          "Si el servidor rechaza una operación (por ejemplo, porque el inventario cambió), la aplicación muestra el motivo.",
                          "Estado «Pendiente» observado (Acopiador); el envío automático es el comportamiento previsto."),
            "notas": ["Si cierra sesión sin conexión, la aplicación advierte: «Estás sin internet. Lo guardado en el teléfono se enviará al volver a ingresar con conexión. ¿Cerrar sesión de todas formas?». Para no demorar el envío, cierre sesión con conexión."],
        },
        {
            "codigo": "MOB-GEN-005", "titulo": "Consultar notificaciones y cerrar sesión", "estado": VER,
            "proposito": "Revisar los avisos recibidos y salir de la cuenta de forma segura.",
            "acceso": "Campana de la barra superior; Menú › Mi perfil › «Cerrar sesión».",
            "requisitos": "Sesión iniciada.",
            "bloques": [
                {"figs": [dict(img="p50_x205", pie="Pantalla «Notificaciones» (ejemplo: Proveedor).", marcas=[
                    (1, ("zona", "Entrega", "analisis", 22))]),
                          dict(img="p60_x235", pie="Pantalla «Mi perfil» (ejemplo: Acopiador).", marcas=[
                    (2, ("fila", "Cerrar", 45))])],
                 "pasos": [
                     (1, "Leer una notificación", "Pulse la campana y toque la notificación. El punto verde indica que no se ha leído; desaparece al tocarla."),
                     (2, "Cerrar sesión", "Abra «Mi perfil» desde el menú lateral y pulse «Cerrar sesión». También está al final del menú lateral."),
                 ]},
            ],
            "resultado": ("La notificación queda marcada como leída. Al cerrar sesión se muestra de nuevo la pantalla de acceso.", OBS),
            "notas": ["Si comparte el teléfono con otra persona, cierre sesión al terminar el turno."],
        },
    ],
}

# ============================================================================ CAPÍTULO 3: PLANTA
PLANTA = {
    "titulo": "Manual del Encargado de planta",
    "intro": "El Encargado de planta controla los insumos, registra sus compras, inicia y finaliza la producción y "
             "consulta los productos terminados y las recetas. Las recetas, precios y presentaciones los configura el "
             "administrador desde EcoLactWeb; en la aplicación solo se consultan.",
    "ficha": [
        ["Barra inferior", "Inicio · Producción · Inventario"],
        ["Menú lateral", ["Operación: Inicio, Producción", "Inventario: Insumos, Productos, Recetas",
                          "Gestión: Compras de insumos", "Sistema: Mi QR, Notificaciones, Mi perfil"]],
        ["Requiere asistencia", "Sí, para registrar compras y producciones (sección 2.3)."],
    ],
    "flujo": [
        ["1", "Iniciar sesión y mostrar el QR al administrador", "2.1 y 2.3"],
        ["2", "Revisar en «Inicio» la leche disponible, los lotes en proceso y el stock bajo", "MOB-PLA-001"],
        ["3", "Revisar los insumos y registrar las compras recibidas", "MOB-PLA-003 a MOB-PLA-006"],
        ["4", "Iniciar la producción con la receta activa", "MOB-PLA-007"],
        ["5", "Finalizar cada lote con la cantidad realmente obtenida", "MOB-PLA-008"],
        ["6", "Consultar lotes, productos terminados y recetas", "MOB-PLA-009 a MOB-PLA-011"],
    ],
    "stock": [
        ["Compra de insumos confirmada", "Aumenta el stock de los insumos comprados."],
        ["Iniciar producción", "Descuenta los insumos según la receta activa."],
        ["Finalizar producción", "Aumenta el stock del producto con la cantidad realmente obtenida."],
        ["Acopio de leche", "Aumenta el stock de leche; la leche no se compra en este módulo."],
    ],
    "procedimientos": [
        {
            "codigo": "MOB-PLA-001", "titulo": "Consultar la pantalla Inicio", "estado": VER,
            "proposito": "Conocer el estado de la planta al comenzar el día.",
            "acceso": "Barra inferior › «Inicio».", "requisitos": "Sesión de Encargado de planta.",
            "bloques": [
                {"figs": [dict(img="p06_x90", pie="Pantalla «Inicio» del Encargado de planta.", marcas=None)],
                 "elementos": [
                     (1, "Resumen", "«Leche disponible», «Lotes en proceso» y «Stock bajo» (insumos por debajo de su stock mínimo)."),
                     (2, "Acciones rápidas", "«Nueva producción», «Escanear boleta» y «Mi QR»."),
                     (3, "Producción activa", "Lotes en proceso. Toque uno para abrir su detalle; «Ver todas» abre «Producción»."),
                     (4, "Barra inferior", "Cambia entre «Inicio», «Producción» e «Inventario»."),
                     (5, "Conexión y notificaciones", "Punto verde con conexión; la campana abre «Notificaciones»."),
                 ]},
            ],
            "resultado": ("Si «Stock bajo» es mayor que 0, revise Inventario › Insumos y registre la compra necesaria.", OBS),
        },
        {
            "codigo": "MOB-PLA-002", "titulo": "Usar el menú lateral", "estado": VER,
            "proposito": "Abrir las secciones que no están en la barra inferior.",
            "acceso": "Botón ☰ de cualquier pantalla principal.", "requisitos": "Sesión de Encargado de planta.",
            "bloques": [
                {"figs": [dict(img="p07_x92", pie="Menú lateral del Encargado de planta.", marcas=None)],
                 "elementos": [
                     (1, "Operación", "«Inicio» y «Producción»."),
                     (2, "Inventario", "«Insumos», «Productos» y «Recetas»."),
                     (3, "Gestión", "«Compras de insumos»: escanear boletas y registrar compras."),
                     (4, "Sistema", "«Mi QR», «Notificaciones» y «Mi perfil»."),
                     (5, "Cerrar sesión", "Sale de la cuenta al terminar el turno."),
                 ]},
            ],
            "resultado": ("La opción elegida se abre y el menú se cierra.", OBS),
        },
        {
            "codigo": "MOB-PLA-003", "titulo": "Consultar los insumos", "estado": VER,
            "proposito": "Revisar el stock de cada insumo, sus presentaciones de compra y sus movimientos.",
            "acceso": "Barra inferior › «Inventario» › pestaña «Insumos», o Menú › Insumos.",
            "requisitos": "Sesión de Encargado de planta.",
            "bloques": [
                {"figs": [dict(img="p09_x97", pie="Lista de insumos.", marcas=None),
                          dict(img="p09_x98", pie="«Detalle de insumo».", marcas=None)],
                 "elementos": [
                     (1, "Pestañas «Insumos» / «Productos»", "Cambian de lista; el número indica cuántos registros hay."),
                     (2, "Leche", "Etiqueta «Del sistema (acopio)»: su stock aumenta con cada acopio y se descuenta al iniciar una producción. No se compra ni se ajusta a mano."),
                     (3, "Otros insumos", "Muestran su stock y su estado. Toque uno para abrir el detalle."),
                     (4, "Stock", "«Stock actual», «Stock mínimo» y «Unidad base»."),
                     (5, "Presentaciones de compra", "Equivalencias configuradas por el administrador (por ejemplo, Balde 25 L = 25 000 ml)."),
                     (6, "Movimientos recientes", "Últimos ingresos y salidas con fecha y hora."),
                     (7, "Botones de compra", "«Escanear boleta» y «Registrar compra manual»."),
                 ]},
            ],
            "resultado": ("Se muestra el stock expresado en la unidad base del insumo (ml, g o L).", OBS),
        },
        {
            "codigo": "MOB-PLA-004", "titulo": "Consultar las compras de insumos", "estado": VER,
            "proposito": "Revisar las compras registradas y las que están guardadas en el teléfono.",
            "acceso": "Menú › Compras de insumos.", "requisitos": "Sesión de Encargado de planta.",
            "bloques": [
                {"figs": [dict(img="p10_x101", pie="Pantalla «Compras de insumos».", marcas=None)],
                 "elementos": [
                     (1, "«Escanear boleta»", "Inicia una compra a partir de la foto de la boleta (MOB-PLA-005)."),
                     (2, "«Registrar compra manual»", "Abre el formulario de compra (MOB-PLA-006)."),
                     (3, "«En el teléfono»", "Compras guardadas en el teléfono: «Pendiente OCR», «Por revisar» o «Pendiente de envío». Toque una para completarla."),
                     (4, "«Compras registradas»", "Historial con número, fecha, insumos, proveedor y total."),
                 ]},
            ],
            "resultado": ("Las compras se listan de la más reciente a la más antigua.", OBS),
        },
        {
            "codigo": "MOB-PLA-005", "titulo": "Registrar una compra escaneando la boleta", "estado": PAR,
            "proposito": "Registrar una compra a partir de la foto de la boleta, revisando los datos leídos automáticamente.",
            "acceso": "«Inicio» › «Escanear boleta», Menú › Compras de insumos › «Escanear boleta», o el detalle de un insumo.",
            "requisitos": "Asistencia del día registrada. Conexión a Internet para la lectura automática.",
            "bloques": [
                {"pasos": [
                    (1, "Tomar la foto", "En «Tomar foto de la boleta», centre la boleta dentro del marco, con buena luz, y pulse «Tomar foto». También puede elegir una imagen con «Galería»."),
                    (2, "Revisar los datos detectados", "La pantalla «Datos detectados» muestra lo leído en la boleta. Corrija cada línea: insumo, presentación y cantidad. Bajo cada línea aparece «Leído en la boleta» como referencia."),
                    (3, "Completar el comprobante", "Revise el proveedor comercial, el número de comprobante, la fecha y el «Total de la compra»."),
                    (4, "Confirmar", "Pulse «Confirmar compra». La compra no se registra hasta este paso."),
                ]},
                {"figs": [dict(img="p10_x100", pie="Confirmación «Compra registrada».", marcas=None)],
                 "pasos": [
                     (5, "Revisar la confirmación", "La aplicación muestra el número de boleta y el mensaje «El stock de los insumos ya fue actualizado.»."),
                     (6, "Continuar", "Pulse «Continuar» para volver a «Compras de insumos»."),
                 ]},
            ],
            "resultado": ("La compra queda registrada y el stock de los insumos aumenta. Sin conexión, la foto queda guardada "
                          "(«Foto guardada») y se leerá al volver la conexión; la compra no se registra hasta que la confirme.",
                          "Confirmación observada (28/10/2026); cámara y lectura automática pendientes de verificación visual."),
            "validaciones": [
                ["Líneas incompletas", "«Completa el insumo, la presentación y la cantidad de cada línea.»"],
                ["La boleta no se pudo leer", "«No se pudo procesar la boleta» con las opciones «Tomar otra foto» y «Cancelar»."],
            ],
            "notas": ["La lectura automática puede equivocarse. Verifique cada valor con la boleta física antes de confirmar."],
        },
        {
            "codigo": "MOB-PLA-006", "titulo": "Registrar una compra manual", "estado": VER,
            "proposito": "Registrar una compra de insumos escribiendo los datos.",
            "acceso": "Menú › Compras de insumos › «Registrar compra manual», o el detalle de un insumo.",
            "requisitos": "Asistencia del día registrada. Presentaciones de compra configuradas para el insumo.",
            "bloques": [
                {"figs": [dict(img="p11_x103", pie="Formulario «Registrar compra».", marcas=None)],
                 "pasos": [
                     (1, "Elegir insumo, cantidad y presentación", "Seleccione el insumo, escriba la cantidad y elija la presentación (por ejemplo, Saco 10 kg)."),
                     (2, "Comprobar el resultado", "«Resultado» muestra la conversión a la unidad base (1 saco de 10 kg = 10 000 g)."),
                     (3, "Agregar otro insumo", "Si la compra incluye más insumos, pulse «Agregar insumo»."),
                     (4, "Completar el comprobante", "«Proveedor comercial», «N° de comprobante» y «Fecha (AAAA-MM-DD)» son opcionales."),
                     (5, "Indicar el total", "Escriba el «Total de la compra» en soles."),
                     (6, "Confirmar", "Pulse «Confirmar compra» para registrar la compra."),
                     (7, "Descartar (opcional)", "Pulse «Descartar» para cancelar el registro sin guardar nada."),
                 ]},
            ],
            "resultado": ("Se muestra «Compra registrada» y el stock de los insumos aumenta. Si se indicó el total, el sistema "
                          "registra también el pago de la compra. Sin conexión aparece «Compra guardada en el teléfono» y "
                          "el stock se actualiza cuando el servidor registra la compra.", OBS),
            "validaciones": [
                ["Líneas incompletas", "«Completa el insumo, la presentación y la cantidad de cada línea.»"],
                ["Insumo sin presentaciones", "«Sin presentaciones configuradas» (las configura el administrador)."],
            ],
            "notas": ["Revise insumo, cantidad y presentación: el «Resultado» debe coincidir con lo que realmente ingresó a la planta."],
        },
        {
            "codigo": "MOB-PLA-007", "titulo": "Iniciar una nueva producción", "estado": VER,
            "proposito": "Crear un lote de producción con la receta activa del producto.",
            "acceso": "«Inicio» › «Nueva producción» o «Producción» › «Nueva producción».",
            "requisitos": "Asistencia del día registrada. Receta activa del producto. Stock suficiente de insumos.",
            "bloques": [
                {"figs": [dict(img="p12_x105", pie="Pantalla «Nueva producción».", marcas=None)],
                 "pasos": [
                     (1, "Elegir el producto", "En «Producto (receta)» seleccione qué va a producir. Siempre se usa la receta activa."),
                     (2, "Indicar la cantidad", "Escriba las unidades en «Cantidad a producir»."),
                     (3, "Revisar los requerimientos", "«Requerimientos de insumos» compara lo «Necesario» con lo «Disponible». La marca ✓ indica que el insumo alcanza."),
                     (4, "Iniciar", "Pulse «Iniciar producción»."),
                 ]},
            ],
            "resultado": ("Se crea el lote en estado «En proceso» (por ejemplo, LOT-00004) y los insumos se descuentan del "
                          "inventario. Sin conexión aparece «Producción guardada en el teléfono»; al sincronizar, el sistema "
                          "vuelve a revisar el inventario y, si cambió, avisa y no crea la producción.", OBS),
            "validaciones": [
                ["Un insumo no alcanza", "«<insumo>: stock insuficiente», con lo necesario, lo disponible y lo que falta."],
                ["Sin recetas activas", "«No hay recetas activas para producir. Las configura el administrador.»"],
                ["Inventario cambiado durante la desconexión", "«No se pudo iniciar la producción porque el inventario cambió mientras estabas sin conexión.»"],
            ],
            "notas": ["Ejemplo: 30 unidades de Yogurt lúcumo 1 L requieren 30 L de leche y 900 ml de saborizante (1 L y 30 ml por unidad). Si un insumo no alcanza, registre la compra y vuelva a esta pantalla."],
        },
        {
            "codigo": "MOB-PLA-008", "titulo": "Finalizar una producción", "estado": VER,
            "proposito": "Cerrar un lote registrando las unidades realmente obtenidas.",
            "acceso": "«Inicio» › lote en «Producción activa», o «Producción» › pestaña «En proceso» › lote.",
            "requisitos": "Asistencia del día registrada. Lote en estado «En proceso».",
            "bloques": [
                {"figs": [dict(img="p13_x107", pie="«Detalle de lote» en proceso.", marcas=None)],
                 "pasos": [
                     (1, "Identificar el lote", "Compruebe el producto, el código del lote y el estado «En proceso»."),
                     (2, "Revisar los datos", "Cantidad planificada, insumos utilizados y fecha de inicio."),
                     (3, "Indicar la cantidad obtenida", "En «Cantidad obtenida realmente» aparece lo planificado; corríjalo si se obtuvieron más o menos unidades (puede ser 0)."),
                     (4, "Revisar la actualización de stock", "La vista previa muestra «Stock anterior» + «Cantidad obtenida» = «Stock nuevo»."),
                     (5, "Finalizar", "Pulse «Finalizar producción»."),
                 ]},
            ],
            "resultado": ("El lote pasa a «Finalizado» y el stock del producto aumenta con la cantidad obtenida (en la captura: "
                          "3 + 19 = 22). Los insumos no se vuelven a descontar.", OBS),
            "validaciones": [["El servidor rechaza la finalización", "«No se pudo finalizar: <motivo>»"]],
        },
        {
            "codigo": "MOB-PLA-009", "titulo": "Consultar las producciones", "estado": VER,
            "proposito": "Revisar los lotes en proceso y los finalizados.",
            "acceso": "Barra inferior › «Producción».", "requisitos": "Sesión de Encargado de planta.",
            "bloques": [
                {"figs": [dict(img="p14_x110", pie="Pantalla «Producción», pestaña «Finalizados».", marcas=None),
                          dict(img="p14_x109", pie="«Detalle de lote» finalizado.", marcas=None)],
                 "elementos": [
                     (1, "«Nueva producción»", "Atajo para iniciar otro lote."),
                     (2, "«En proceso» / «Finalizados»", "Cambian entre lotes activos y terminados."),
                     (3, "Lista de lotes", "Producto, lote, unidades y litros de leche. Toque uno para abrirlo."),
                     (4, "Estado", "«Finalizado»: el lote ya ingresó al inventario."),
                     (5, "Detalle", "Cantidad planificada y obtenida, insumos usados, fechas de inicio y fin."),
                 ]},
            ],
            "resultado": ("Los lotes más recientes aparecen primero. Cada lote tiene un código único, como LOT-00005.", OBS),
        },
        {
            "codigo": "MOB-PLA-010", "titulo": "Consultar los productos terminados", "estado": VER,
            "proposito": "Revisar el stock y los precios vigentes de cada producto.",
            "acceso": "Barra inferior › «Inventario» › pestaña «Productos», o Menú › Productos.",
            "requisitos": "Sesión de Encargado de planta.",
            "bloques": [
                {"figs": [dict(img="p15_x112", pie="Pestaña «Productos».", marcas=None),
                          dict(img="p15_x113", pie="«Detalle de producto».", marcas=None)],
                 "elementos": [
                     (1, "Pestaña «Productos»", "Unidades en stock de cada producto."),
                     (2, "Producto", "Toque uno para abrir su detalle."),
                     (3, "Datos del producto", "Presentación, stock actual, estado, última producción (lote) y receta."),
                     (4, "«Precios vigentes»", "«Público», «Mayorista» (desde una cantidad mínima) y «Proveedor». Solo consulta."),
                 ]},
            ],
            "resultado": ("El stock de un producto aumenta cada vez que se finaliza un lote de ese producto.", OBS),
        },
        {
            "codigo": "MOB-PLA-011", "titulo": "Consultar las recetas", "estado": VER,
            "proposito": "Conocer los insumos que lleva cada unidad de producto.",
            "acceso": "Menú › Recetas.", "requisitos": "Sesión de Encargado de planta.",
            "bloques": [
                {"figs": [dict(img="p16_x116", pie="Pantalla «Recetas».", marcas=None),
                          dict(img="p16_x115", pie="«Detalle de receta».", marcas=None)],
                 "elementos": [
                     (1, "Lista de recetas", "Producto, presentación e insumos por unidad. Toque una para abrirla."),
                     (2, "«Activa»", "Es la receta que usa «Nueva producción»."),
                     (3, "«Por cada unidad»", "Insumos y cantidades por unidad producida."),
                     (4, "Estado, versión y actualización", "Datos de control de la receta."),
                     (5, "Aviso", "«Las recetas las configura el administrador. Nueva producción usa siempre la receta activa.»"),
                 ]},
            ],
            "resultado": ("Ejemplo: Mantequilla cremosa 500 g usa 2 L de leche y 10 g de sal por unidad; para 30 unidades "
                          "el sistema calcula 60 L de leche y 300 g de sal.", OBS),
        },
        {
            "codigo": "MOB-PLA-012", "titulo": "Consultar Mi perfil", "estado": VER,
            "proposito": "Revisar los datos de la cuenta y cerrar sesión.",
            "acceso": "Menú › Mi perfil.", "requisitos": "Sesión de Encargado de planta.",
            "bloques": [
                {"figs": [dict(img="p17_x118", pie="Pantalla «Mi perfil».", marcas=None)],
                 "elementos": [
                     (1, "Datos de la cuenta", "Usuario, DNI, teléfono y rol."),
                     (2, "«Cerrar sesión»", "Sale de la cuenta. También está al final del menú lateral."),
                     (3, "Campana", "Abre «Notificaciones» desde cualquier pantalla."),
                 ]},
            ],
            "resultado": ("Los datos son de solo lectura; para corregirlos, comuníquese con el administrador.", OBS),
        },
    ],
}

# ============================================================================ CAPÍTULO 4: VENTAS
VENTAS = {
    "titulo": "Manual del Personal de ventas",
    "intro": "El Personal de ventas registra la venta de productos terminados al público general, a compradores "
             "registrados y a proveedores. El precio lo determina el sistema según el tipo de cliente y la cantidad.",
    "ficha": [
        ["Barra inferior", "Inicio · Vender · Historial"],
        ["Menú lateral", ["Operación: Inicio, Nueva venta, Historial de ventas", "Gestión: Compradores, Productos",
                          "Comunicación: Comunicados, Notificaciones", "Sistema: Mi QR, Mi perfil"]],
        ["Requiere asistencia", "Sí, para registrar ventas y compradores (sección 2.3)."],
    ],
    "procedimientos": [
        {
            "codigo": "MOB-VEN-001", "titulo": "Consultar la pantalla Inicio", "estado": VER,
            "proposito": "Revisar el resumen de ventas del día y acceder a las acciones principales.",
            "acceso": "Barra inferior › «Inicio».", "requisitos": "Sesión de Personal de ventas.",
            "bloques": [
                {"figs": [dict(img="p19_x125", pie="Pantalla «Inicio» del Personal de ventas.", marcas=[
                    (1, ("caja", 36, 480, 876, 830)), (2, ("zona", "Acciones", "historial#1", 50)),
                    (3, ("zona", "Ventas recientes", "Publico", 60)), (4, ("zona", "Inicio#2", "Historial#2", 30))])],
                 "elementos": [
                     (1, "Resumen del día", "«Cobrado hoy», «Ventas de hoy» y «Productos vendidos»."),
                     (2, "Acciones rápidas", "«Nueva venta», «Ver historial» y «Mi QR»."),
                     (3, "«Ventas recientes»", "Últimas ventas con código, cliente, estado y total. «Ver todas» abre el historial."),
                     (4, "Barra inferior", "«Inicio», «Vender» e «Historial»."),
                 ]},
            ],
            "resultado": ("Los importes del día se actualizan con cada venta registrada.", OBS),
        },
        {
            "codigo": "MOB-VEN-002", "titulo": "Consultar productos y precios", "estado": VER,
            "proposito": "Revisar el stock y los precios por tipo de cliente antes de vender.",
            "acceso": "Menú › Productos.", "requisitos": "Sesión de Personal de ventas.",
            "bloques": [
                {"figs": [dict(img="p22_x137", pie="Pantalla «Productos».", marcas=[
                    (1, ("fila", "Buscar", 45)), (2, ("zona", "Mantequilla", "Stock", 18)),
                    (3, ("zona", "Precio publico", "Precio proveedor", 22))])],
                 "pasos": [
                     (1, "Buscar el producto", "Escriba el nombre en «Buscar producto…»."),
                     (2, "Revisar el stock", "Bajo el nombre se muestra la presentación y el stock en unidades."),
                     (3, "Comparar precios", "«Precio público», «Precio mayorista», «Mínimo mayorista» y «Precio proveedor». Un guion indica precio no configurado."),
                 ]},
            ],
            "resultado": ("La pantalla es de consulta; los precios los configura el administrador.", OBS),
        },
        {
            "codigo": "MOB-VEN-003", "titulo": "Consultar y registrar compradores", "estado": PAR,
            "proposito": "Buscar un comprador registrado o registrar uno nuevo.",
            "acceso": "Menú › Compradores.", "requisitos": "Asistencia del día y conexión a Internet para registrar.",
            "bloques": [
                {"figs": [dict(img="p23_x139", pie="Pantalla «Compradores».", marcas=[
                    (1, ("fila", "Nombre", 45)), (2, ("boton", 1)), (3, ("caja", 36, 600, 876, 760))])],
                 "pasos": [
                     (1, "Buscar", "Escriba el nombre, DNI o RUC en el buscador."),
                     (2, "Registrar un comprador", "Pulse «+ Nuevo comprador»."),
                     (3, "Revisar el resultado", "Cada comprador muestra su documento y teléfono."),
                 ]},
                {"pasos": [
                    (4, "Completar el formulario", "En «Nuevo comprador» escriba «Nombre / razón social», «Documento (DNI o RUC)» y «Teléfono»."),
                    (5, "Registrar", "Pulse «Registrar comprador»."),
                ]},
            ],
            "resultado": ("El comprador queda disponible en la lista y al seleccionar el cliente de una venta.", PREV),
            "validaciones": [["Sin conexión", "«Necesitas conexión para registrar un comprador.»"]],
        },
        {
            "codigo": "MOB-VEN-004", "titulo": "Registrar una venta", "estado": PAR,
            "proposito": "Registrar la venta de un producto con el precio que corresponde al cliente.",
            "acceso": "Barra inferior › «Vender», o «Inicio» › «Nueva venta».",
            "requisitos": "Asistencia del día registrada. Producto con stock y precio vigente.",
            "bloques": [
                {"pasos": [
                    (1, "Elegir el producto", "En «Nueva venta» busque el producto y pulse «Agregar». Los productos sin stock o sin precio vigente no se pueden vender."),
                ]},
                {"figs": [dict(img="p24_x141", pie="Pantalla «Seleccionar cliente».", marcas=[
                    (2, ("zona", "Publico general", "Sin registro", 26)), (3, ("zona", "Comprador registrado", "Buscar por", 26)),
                    (4, ("zona", "Proveedor#1", "semanal", 26))])],
                 "pasos": [
                     (2, "Público general", "Venta sin cliente registrado; se paga en efectivo."),
                     (3, "Comprador registrado", "Busque por nombre, DNI o RUC y toque el comprador."),
                     (4, "Proveedor", "Busque por nombre o DNI. Aplica el precio proveedor y permite el descuento del pago semanal."),
                 ]},
                {"figs": [dict(img="p25_x143", pie="«Resumen de venta»: cantidad y forma de pago.", marcas=[
                    (5, ("caja", 36, 740, 876, 890)), (6, ("zona", "FORMA", "Descuento", 30)), (7, ("boton", 1))]),
                          dict(img="p26_x145", pie="«Resumen de venta»: importe calculado.", marcas=[
                    (8, ("caja", 36, 1215, 876, 1630)), (9, ("fila", "aplicado", 35)), (10, ("boton", 1))])],
                 "pasos": [
                     (5, "Indicar la cantidad", "Escriba las unidades a vender."),
                     (6, "Elegir la forma de pago", "«Efectivo» o, solo para proveedores, «Descuento del pago semanal»."),
                     (7, "Calcular el total", "Pulse «Calcular total»."),
                     (8, "Revisar el importe", "Cantidad, precio unitario, subtotal y «TOTAL»."),
                     (9, "Verificar el precio aplicado", "El mensaje indica la tarifa: público, mayorista o proveedor."),
                     (10, "Registrar", "Pulse «Registrar venta» (sin conexión, «Guardar venta»)."),
                 ]},
            ],
            "resultado": ("Se muestra la pantalla «Venta registrada» con el código, el total y las opciones «Ver boleta», "
                          "«Registrar otra venta» y «Ver historial». La venta de la captura (4 × S/ 4.00) aparece luego en el "
                          "historial como V-00005, S/ 16.00 (Figura de MOB-VEN-005).",
                          "Registro observado en el historial (28/10/2026); pantalla de confirmación pendiente de verificación visual."),
            "validaciones": [
                ["Tipo de precio", "Proveedor → precio proveedor; cantidad igual o mayor al mínimo mayorista → precio mayorista; otro caso → precio público."],
                ["Descuento del pago semanal", "Solo para proveedores; no puede superar el pago semanal disponible. «Este monto se descontará del próximo pago semanal del proveedor.»"],
                ["Descuento sin conexión", "«Necesitas conexión para validar el saldo disponible del proveedor.»"],
                ["Venta sin conexión", "«Venta guardada en el dispositivo» — «Precio guardado en el dispositivo. Se validará al enviar la venta.»"],
                ["Rechazo del servidor", "«No se pudo registrar la venta»"],
            ],
        },
        {
            "codigo": "MOB-VEN-005", "titulo": "Consultar el historial de ventas", "estado": VER,
            "proposito": "Buscar ventas registradas y abrir su detalle.",
            "acceso": "Barra inferior › «Historial».", "requisitos": "Sesión de Personal de ventas.",
            "bloques": [
                {"figs": [dict(img="p27_x147", pie="Pantalla «Historial».", marcas=[
                    (1, ("fila", "Buscar", 45)), (2, ("fila", "Todas", 40)), (3, ("zona", "V-00005", "Completada", 22))])],
                 "pasos": [
                     (1, "Buscar", "Escriba el código o el cliente en «Buscar por código o cliente…»."),
                     (2, "Filtrar el período", "Elija «Todas», «Hoy», «Esta semana» o «Este mes»."),
                     (3, "Abrir una venta", "Toque la fila para ver «Detalle de venta»: cliente, producto, cantidad, tipo de precio, total, forma de pago, vendedor y estado."),
                 ]},
            ],
            "resultado": ("Las ventas se muestran con su código, cliente, estado y total.", OBS),
        },
        {
            "codigo": "MOB-VEN-006", "titulo": "Ver y compartir la boleta de venta", "estado": COD,
            "proposito": "Mostrar, descargar o compartir la boleta de una venta registrada.",
            "acceso": "«Venta registrada» › «Ver boleta», o «Detalle de venta» › «Ver boleta».",
            "requisitos": "Venta registrada en el servidor.",
            "bloques": [
                {"pasos": [
                    (1, "Abrir la boleta", "Pulse «Ver boleta». La pantalla «Boleta de venta» muestra fecha, hora, datos del cliente, cantidad, descripción, precio unitario, total y forma de pago."),
                    (2, "Descargar", "Pulse «Descargar PDF» para guardar la boleta en el teléfono."),
                    (3, "Compartir", "Pulse «Compartir» para enviarla con otra aplicación del teléfono."),
                ]},
            ],
            "resultado": ("La boleta en PDF se descarga o se comparte. Para una venta pendiente de sincronización, la boleta "
                          "estará disponible después del envío.", PREV),
        },
        {
            "codigo": "MOB-VEN-007", "titulo": "Consultar Mi perfil y Mi QR", "estado": VER,
            "proposito": "Revisar los datos de la cuenta y mostrar el QR de asistencia.",
            "acceso": "Menú › Mi perfil; Menú › Mi QR.", "requisitos": "Sesión de Personal de ventas.",
            "bloques": [
                {"figs": [dict(img="p28_x149", pie="Pantalla «Mi perfil».", marcas=[
                    (1, ("zona", "Usuario", "Rol", 30)), (2, ("fila", "Cerrar", 45))]),
                          dict(img="p29_x151", pie="Pantalla «Mi QR».", marcas=[
                    (3, ("caja", 150, 600, 762, 1385)), (4, ("zona", "Este QR", "diaria", 30))])],
                 "elementos": [
                     (1, "Datos personales", "Usuario, DNI y rol."),
                     (2, "«Cerrar sesión»", "Sale de la cuenta."),
                     (3, "Código QR", "Se muestra al administrador para registrar la asistencia (sección 2.3)."),
                     (4, "Aviso", "El QR se usa solo para la asistencia diaria, no para cobrar ventas."),
                 ]},
            ],
            "resultado": ("Los datos son de solo lectura.", OBS),
        },
        {
            "codigo": "MOB-VEN-008", "titulo": "Consultar comunicados", "estado": COD,
            "proposito": "Leer los comunicados publicados.",
            "acceso": "Menú › Comunicados.", "requisitos": "Sesión de Personal de ventas.",
            "bloques": [{"pasos": [(1, "Abrir la lista", "La pantalla muestra los comunicados guardados en el teléfono. Si no hay ninguno: «No hay comunicados guardados.»")]}],
            "resultado": ("Pantalla de solo lectura; el Personal de ventas no publica comunicados.", PREV),
        },
    ],
}

# ============================================================================ CAPÍTULO 5: CALIDAD
CALIDAD = {
    "titulo": "Manual del Analista de calidad",
    "intro": "El Analista de calidad selecciona las entregas de leche que serán evaluadas (muestreo), registra los "
             "resultados del análisis y publica comunicados. Solo se analizan las entregas seleccionadas como muestra.",
    "ficha": [
        ["Barra inferior", "Inicio · Muestreo · Historial"],
        ["Menú lateral", ["Operación: Inicio, Muestreo, Muestras pendientes", "Consulta: Historial de análisis, Proveedores",
                          "Comunicación: Comunicados, Notificaciones", "Sistema: Mi QR, Mi perfil"]],
        ["Requiere asistencia", "Sí, para muestrear, analizar y publicar comunicados (sección 2.3)."],
    ],
    "flujo": [
        ["1", "Seleccionar entregas para el control: al azar, manualmente o por muestreo directo en campo", "MOB-CAL-002 y MOB-CAL-003"],
        ["2", "Abrir una muestra pendiente", "MOB-CAL-001"],
        ["3", "Registrar los valores del analizador y confirmar el análisis", "MOB-CAL-004"],
        ["4", "Consultar el historial y los proveedores", "MOB-CAL-005 y MOB-CAL-006"],
    ],
    "procedimientos": [
        {
            "codigo": "MOB-CAL-001", "titulo": "Consultar Inicio y las muestras pendientes", "estado": VER,
            "proposito": "Ver las muestras por analizar y abrir una de ellas.",
            "acceso": "Barra inferior › «Inicio»; Menú › Muestras pendientes.", "requisitos": "Sesión de Analista de calidad.",
            "bloques": [
                {"figs": [dict(img="p30_x156", pie="Pantalla «Inicio» del Analista de calidad.", marcas=[
                    (1, ("caja", 36, 450, 876, 600)), (2, ("zona", "Luis", "revisar", 24)),
                    (3, ("caja", 655, 868, 852, 982)), (4, ("zona", "Inicio#2", "Historial", 30))])],
                 "elementos": [
                     (1, "Resumen", "«Muestras pendientes» y «Analizadas hoy»."),
                     (2, "Muestra pendiente", "Proveedor, litros, zona y hora. «Datos por revisar» indica un ticket ya leído que falta confirmar."),
                     (3, "«Analizar»", "Abre el detalle de la muestra para registrar el análisis (MOB-CAL-004)."),
                     (4, "Barra inferior", "«Inicio», «Muestreo» e «Historial»."),
                 ]},
            ],
            "resultado": ("Si no hay muestras: «No tienes muestras pendientes. Selecciona una entrega en Muestreo.» con el botón «Ir a Muestreo».", OBS),
        },
        {
            "codigo": "MOB-CAL-002", "titulo": "Seleccionar entregas para el control de calidad", "estado": COD,
            "proposito": "Elegir, al azar o manualmente, las entregas de acopio que serán analizadas.",
            "acceso": "Barra inferior › «Muestreo».", "requisitos": "Asistencia del día registrada. Entregas de acopio disponibles.",
            "bloques": [
                {"pasos": [
                    (1, "Abrir «Muestreo»", "La pantalla indica «Selecciona las entregas que serán evaluadas por Calidad.»"),
                    (2, "Elegir al azar", "Pulse «Elegir muestra al azar». La aplicación propone una entrega con proveedor, litros, zona, acopiador, hora y método «Aleatoria». Pulse «Confirmar muestra» o «Elegir otra»."),
                    (3, "O seleccionar manualmente", "Pulse «Seleccionar manualmente», busque por proveedor o DNI y toque «Seleccionar» en la entrega. Agregue una «Observación (opcional)» y pulse «Confirmar muestra»."),
                    (4, "Continuar", "En «Muestra seleccionada» pulse «Analizar ahora» o «Ver pendientes»."),
                ]},
            ],
            "resultado": ("La entrega pasa a «Muestras pendientes». Sin conexión, la selección manual queda guardada y se envía al volver la conexión.", PREV),
            "validaciones": [
                ["Muestra al azar sin conexión", "«Se necesita conexión para elegir una muestra aleatoria. Puedes seleccionar manualmente.»"],
                ["Sin entregas", "«No hay entregas disponibles para muestrear.»"],
            ],
        },
        {
            "codigo": "MOB-CAL-003", "titulo": "Registrar un muestreo directo en campo", "estado": VER,
            "proposito": "Tomar una muestra al proveedor antes del acopio, identificándolo por QR o DNI.",
            "acceso": "«Muestreo» › «¿Estás en campo con el proveedor?» › «Muestreo directo».",
            "requisitos": "Asistencia del día registrada. Proveedor activo.",
            "bloques": [
                {"figs": [dict(img="p31_x162", pie="«Muestreo directo»: identificación.", marcas=[
                    (1, ("boton", 1)), (2, ("fila", "DNI del", 45))]),
                          dict(img="p33_x167", pie="Cámara de escaneo del QR.", marcas=[
                    (3, ("caja", 350, 1040, 590, 1255))])],
                 "pasos": [
                     (1, "Escanear el QR", "Pulse «Escanear QR» para abrir la cámara."),
                     (2, "O escribir el DNI", "Escriba los 8 dígitos en «DNI del proveedor»."),
                     (3, "Enfocar el QR", "Coloque el código del proveedor frente a la cámara; se lee automáticamente. «Cancelar» vuelve sin escanear."),
                 ]},
                {"figs": [dict(img="p32_x165", pie="Proveedor encontrado por DNI.", marcas=[
                    (4, ("fila", "Buscar", 45)), (5, ("zona", "encontrado", "Quispe", 50)), (6, ("boton", 2))]),
                          dict(img="p34_x169", pie="«Cantidad de muestra».", marcas=[
                    (7, ("caja", 36, 430, 876, 565)), (8, ("fila", "Observacion", 45)), (9, ("boton", 1))])],
                 "pasos": [
                     (4, "Buscar", "Si escribió el DNI, pulse «Buscar»."),
                     (5, "Comprobar la identidad", "Revise el nombre en «Proveedor encontrado». No continúe si no corresponde."),
                     (6, "Continuar", "Pulse «Continuar»."),
                     (7, "Indicar los litros", "Escriba la cantidad de muestra en «Litros»."),
                     (8, "Agregar una observación", "Opcional."),
                     (9, "Continuar", "Pulse «Continuar» para ver el resumen."),
                 ]},
                {"figs": [dict(img="p35_x171", pie="«Confirmar muestreo directo».", marcas=[
                    (10, ("zona", "Proveedor", "Metodo", 30)), (11, ("boton", 1)), (12, ("fila", "Corregir", 45))]),
                          dict(img="p36_x173", pie="«Muestra registrada».", marcas=[
                    (13, ("boton", 1)), (14, ("fila", "Listo", 45))])],
                 "pasos": [
                     (10, "Revisar el resumen", "Proveedor, cantidad y método."),
                     (11, "Confirmar", "Pulse «Confirmar» para registrar la muestra."),
                     (12, "Corregir (opcional)", "Pulse «Corregir» para volver y cambiar los datos."),
                     (13, "Analizar", "En «Muestra registrada» pulse «Continuar a análisis» para registrar el resultado (MOB-CAL-004)."),
                     (14, "Terminar", "O pulse «Listo»; la muestra queda en «Muestras pendientes»."),
                 ]},
            ],
            "resultado": ("Se muestra «Muestra registrada — Ya está en Muestras pendientes.». Sin conexión: «Muestreo guardado en el "
                          "dispositivo», que aparecerá en pendientes al sincronizarse.", OBS),
            "validaciones": [
                ["DNI con otra longitud", "«El DNI debe tener exactamente 8 dígitos»"],
                ["DNI no registrado", "«No se encontró ese DNI»"],
                ["Litros inválidos", "«Ingrese una cantidad de litros válida»"],
            ],
        },
        {
            "codigo": "MOB-CAL-004", "titulo": "Registrar el análisis de una muestra", "estado": PAR,
            "proposito": "Registrar los valores del analizador y confirmar el resultado de calidad.",
            "acceso": "«Inicio» › «Analizar», o Menú › Muestras pendientes › muestra.",
            "requisitos": "Asistencia del día registrada. Parámetros de calidad activos configurados por el administrador.",
            "bloques": [
                {"figs": [dict(img="p37_x175", pie="«Detalle de entrega» pendiente de análisis.", marcas=[
                    (1, ("zona", "Litros", "Fecha de", 30)), (2, ("boton", 1)), (3, ("fila", "Ingresar", 45))])],
                 "pasos": [
                     (1, "Revisar la muestra", "Litros entregados, zona, fecha, acopiador, estado «Pendiente de análisis» y método de selección."),
                     (2, "Escanear el ticket", "Pulse «Escanear ticket», centre el ticket del analizador en el recuadro y pulse «Tomar foto» (o «Galería»)."),
                     (3, "O ingresar manualmente", "Pulse «Ingresar manualmente» para escribir los valores."),
                 ]},
                {"pasos": [
                    (4, "Revisar los valores", "En «Datos detectados» o «Ingresar valores», revise cada parámetro. Deje vacío lo que no se midió."),
                    (5, "Revisar el resultado estimado", "«Resultado estimado» es una vista previa; el resultado final lo valida el servidor al confirmar."),
                    (6, "Confirmar", "Agregue una «Observación (opcional)» y pulse «Confirmar análisis»."),
                ]},
            ],
            "resultado": ("Se muestra «Análisis confirmado — La muestra salió de pendientes y ya está en el historial.». El "
                          "proveedor ve el resultado en «Mi calidad». Sin conexión, el análisis queda guardado y se envía después.",
                          "Detalle observado (28/10/2026); lectura del ticket y confirmación pendientes de verificación visual."),
            "validaciones": [
                ["Valores no numéricos", "«Revisa los valores marcados: deben ser números.»"],
                ["Ningún valor ingresado", "«Ingresa al menos un valor para confirmar el análisis.»"],
                ["Ticket ilegible", "«No se pudo leer el ticket» con «Tomar otra foto» e «Ingresar manualmente»."],
                ["Sin parámetros configurados", "«No hay parámetros de calidad activos configurados.»"],
            ],
            "notas": ["La notificación «Procesando OCR…» indica que el ticket se está leyendo (Figura de MOB-CAL-008)."],
        },
        {
            "codigo": "MOB-CAL-005", "titulo": "Consultar el historial de análisis", "estado": COD,
            "proposito": "Revisar los análisis realizados y sus parámetros.",
            "acceso": "Barra inferior › «Historial».", "requisitos": "Sesión de Analista de calidad.",
            "bloques": [{"pasos": [
                (1, "Filtrar", "Elija el período («Hoy», «Esta semana», «Todo») y el resultado («Todos los resultados», «Aceptadas», «Observadas», «Rechazadas»)."),
                (2, "Abrir un análisis", "Toque uno para ver proveedor, entrega, resultado, fecha, «PARÁMETROS DE CALIDAD» y observación."),
            ]}],
            "resultado": ("Los análisis guardados sin conexión aparecen como «Por enviar» hasta sincronizarse.", PREV),
        },
        {
            "codigo": "MOB-CAL-006", "titulo": "Consultar proveedores", "estado": COD,
            "proposito": "Revisar los proveedores, sus advertencias y sus últimos análisis.",
            "acceso": "Menú › Proveedores.", "requisitos": "Sesión de Analista de calidad.",
            "bloques": [{"pasos": [
                (1, "Buscar", "Escriba el nombre o DNI en «Buscar proveedor o DNI...»."),
                (2, "Abrir un proveedor", "Toque el proveedor para ver su DNI, el número de advertencias y «Últimos análisis»."),
            ]}],
            "resultado": ("Pantalla de consulta.", PREV),
        },
        {
            "codigo": "MOB-CAL-007", "titulo": "Publicar un comunicado", "estado": VER,
            "proposito": "Enviar un aviso a los proveedores.",
            "acceso": "Menú › Comunicados › «+ Nuevo comunicado».", "requisitos": "Asistencia del día registrada. Conexión a Internet.",
            "bloques": [
                {"figs": [dict(img="p38_x177", pie="Pantalla «Nuevo comunicado».", marcas=[
                    (1, ("caja", 36, 300, 760, 430)), (2, ("caja", 36, 480, 760, 730)), (3, ("caja", 36, 770, 876, 900)),
                    (4, ("fila", "GENERAL", 40)), (5, ("boton", 1))])],
                 "pasos": [
                     (1, "Título", "Escriba el asunto del aviso."),
                     (2, "Contenido", "Redacte el mensaje."),
                     (3, "Tipo", "Indique la categoría (por ejemplo, AVISO)."),
                     (4, "Destinatarios", "Elija «GENERAL», «ZONA» (pide el «ID de zona») o «INDIVIDUAL» (pide «IDs de usuario separados por coma»)."),
                     (5, "Publicar", "Pulse «Publicar comunicado»."),
                 ]},
            ],
            "resultado": ("Llega la notificación «Aviso creado» (Figura de MOB-CAL-008).", OBS),
            "validaciones": [
                ["Título o contenido vacío", "«Título y contenido son obligatorios»"],
                ["Error al publicar", "«No se pudo crear el comunicado»"],
            ],
            "notas": ["Para «ZONA» e «INDIVIDUAL» la aplicación solicita identificadores numéricos; consúltelos con el administrador."],
        },
        {
            "codigo": "MOB-CAL-008", "titulo": "Consultar notificaciones y Mi perfil", "estado": VER,
            "proposito": "Leer los avisos y revisar los datos de la cuenta.",
            "acceso": "Campana; Menú › Mi perfil.", "requisitos": "Sesión de Analista de calidad.",
            "bloques": [
                {"figs": [dict(img="p39_x180", pie="«Notificaciones» con aviso de lectura OCR.", marcas=[
                    (1, ("fila", "Procesando", 22)), (2, ("zona", "Aviso", "general", 26))]),
                          dict(img="p39_x179", pie="Pantalla «Mi perfil».", marcas=[
                    (3, ("zona", "Usuario", "Estado", 30)), (4, ("fila", "Cerrar", 45))])],
                 "elementos": [
                     (1, "«Procesando OCR…»", "Un ticket se está leyendo en el servidor."),
                     (2, "Notificación", "Ejemplo: «Aviso creado» tras publicar un comunicado."),
                     (3, "Datos de la cuenta", "Usuario y estado."),
                     (4, "«Cerrar sesión»", "Sale de la cuenta."),
                 ]},
            ],
            "resultado": ("Pantallas de consulta.", OBS),
        },
    ],
}

# ============================================================================ CAPÍTULO 6: PROVEEDOR
PROVEEDOR = {
    "titulo": "Manual del Proveedor",
    "intro": "El Proveedor consulta sus entregas de leche, los resultados de calidad, sus pagos semanales, sus compras "
             "de productos y los comunicados. Todas sus pantallas son de consulta; el proveedor no registra operaciones.",
    "ficha": [
        ["Barra inferior", "Inicio · Entregas · Pagos"],
        ["Menú lateral", ["Operación: Inicio, Mis entregas, Mi calidad", "Gestión: Mis pagos, Mis compras",
                          "Comunicación: Comunicados, Notificaciones", "Sistema: Mi perfil, Sincronización"]],
        ["Requiere asistencia", "No."],
    ],
    "procedimientos": [
        {
            "codigo": "MOB-PRV-001", "titulo": "Mostrar el código QR y consultar la semana", "estado": VER,
            "proposito": "Identificarse ante el acopiador y revisar el resumen semanal.",
            "acceso": "Barra inferior › «Inicio».", "requisitos": "Sesión de Proveedor.",
            "bloques": [
                {"figs": [dict(img="p43_x184", pie="Pantalla «Inicio» del Proveedor.", marcas=[
                    (1, ("zona", "cedigo", "Muestralo", 30)), (2, ("zona", "Esta semana", "Pago", 34))])],
                 "pasos": [
                     (1, "Mostrar el QR", "Muestre «Mi código QR» al acopiador para que registre su entrega."),
                     (2, "Consultar la semana", "Revise «Litros entregados», «Entregas registradas» y «Pago pendiente»."),
                 ]},
            ],
            "resultado": ("El QR está visible en «Inicio». Sin conexión se muestran los datos guardados; si el QR aún no se "
                          "descargó: «Tu código QR se descargará la próxima vez que tengas conexión.»", OBS),
        },
        {
            "codigo": "MOB-PRV-002", "titulo": "Navegar por la aplicación", "estado": VER,
            "proposito": "Abrir las secciones del proveedor.",
            "acceso": "Botón ☰ o barra inferior.", "requisitos": "Sesión de Proveedor.",
            "bloques": [
                {"figs": [dict(img="p42_x188", pie="Menú lateral del Proveedor.", marcas=[
                    (1, ("zona", "OPERACION", "calidad", 22)), (2, ("zona", "GESTION", "compras", 22)),
                    (3, ("zona", "COMUNICACION", "Notificaciones", 22)), (4, ("zona", "SISTEMA", "Sincronizacion", 22))])],
                 "elementos": [
                     (1, "Operación", "«Inicio», «Mis entregas» y «Mi calidad»."),
                     (2, "Gestión", "«Mis pagos» y «Mis compras»."),
                     (3, "Comunicación", "«Comunicados» y «Notificaciones»."),
                     (4, "Sistema", "«Mi perfil» y «Sincronización»."),
                 ]},
            ],
            "resultado": ("La barra inferior lleva a «Inicio», «Entregas» y «Pagos».", OBS),
        },
        {
            "codigo": "MOB-PRV-003", "titulo": "Consultar mis entregas", "estado": VER,
            "proposito": "Revisar las entregas de leche y su estado.",
            "acceso": "Barra inferior › «Entregas», o Menú › Mis entregas.", "requisitos": "Sesión de Proveedor.",
            "bloques": [
                {"figs": [dict(img="p44_x191", pie="Pantalla «Mis entregas».", marcas=[
                    (1, ("texto", "Semana#2", 22)), (2, ("texto", "Estado", 22)), (3, ("zona", "11", "Rechazada", 30))]),
                          dict(img="p45_x193", pie="«Detalle de entrega».", marcas=[
                    (4, ("zona", "Fecha", "Estado", 30)), (5, ("fila", "Ver", 45))])],
                 "pasos": [
                     (1, "Filtrar por semana", "Pulse «Semana» y elija el período."),
                     (2, "Filtrar por estado", "Pulse «Estado»."),
                     (3, "Abrir una entrega", "Toque la fila para ver el detalle."),
                     (4, "Revisar los datos", "Fecha, hora, litros, zona, acopiador, estado y «Resultado de calidad»."),
                     (5, "Ver el análisis", "Pulse «Ver análisis» para abrir el resultado de laboratorio."),
                 ]},
            ],
            "resultado": ("El estado indica si la entrega fue «Aceptada» o «Rechazada». El resultado de calidad aparece solo "
                          "cuando la entrega fue analizada.", OBS),
        },
        {
            "codigo": "MOB-PRV-004", "titulo": "Consultar mis resultados de calidad", "estado": VER,
            "proposito": "Revisar los análisis de laboratorio de las entregas.",
            "acceso": "Menú › Mi calidad.", "requisitos": "Sesión de Proveedor.",
            "bloques": [
                {"figs": [dict(img="p46_x195", pie="Pantalla «Mi calidad».", marcas=[(1, ("zona", "76", "Aceptada", 34))]),
                          dict(img="p48_x200", pie="«Análisis de calidad».", marcas=[
                    (2, ("zona", "Resultado", "Zona", 30)), (3, ("zona", "Grasa", "pH#2", 30))])],
                 "pasos": [
                     (1, "Elegir un resultado", "Toque la entrega en la lista."),
                     (2, "Revisar el resultado", "Resultado, fecha, litros y zona."),
                     (3, "Revisar los parámetros", "Grasa, proteína, lactosa, densidad, agua añadida y pH. Los valores fuera de rango se señalan como «Fuera de rango»."),
                 ]},
            ],
            "resultado": ("Si corresponde, la pantalla muestra también la «Observación del analista» y la «Sanción».", OBS),
        },
        {
            "codigo": "MOB-PRV-005", "titulo": "Consultar mis pagos y liquidaciones", "estado": PAR,
            "proposito": "Revisar el importe por cobrar y el detalle de cada pago semanal.",
            "acceso": "Barra inferior › «Pagos», o Menú › Mis pagos.", "requisitos": "Sesión de Proveedor.",
            "bloques": [
                {"figs": [dict(img="p47_x197", pie="Pantalla «Mis pagos» sin pagos registrados.", marcas=[
                    (1, ("fila", "cobrar", 30))])],
                 "pasos": [
                     (1, "Consultar el saldo", "En la parte superior se muestra el importe «por cobrar»."),
                 ]},
                {"pasos": [
                    (2, "Abrir un pago", "Toque una semana de la lista para abrir «Detalle de pago»."),
                    (3, "Revisar la liquidación", "Semana y fechas, «Litros entregados», «Litros pagables», «Precio aplicado», «Calidad de la semana», «Monto por leche», «Ajuste de calidad», «Compras descontadas» y «Total a recibir»."),
                    (4, "Revisar el estado", "Estado del pago, «Fecha de pago» y, si corresponde, «Saldo pendiente»."),
                ]},
            ],
            "resultado": ("Pantalla de consulta; el proveedor no registra pagos. Sin pagos: «Aún no tienes pagos registrados.»",
                          "Lista vacía observada; detalle de liquidación pendiente de verificación visual."),
        },
        {
            "codigo": "MOB-PRV-006", "titulo": "Consultar mis compras", "estado": PAR,
            "proposito": "Revisar los productos comprados a la planta.",
            "acceso": "Menú › Mis compras.", "requisitos": "Sesión de Proveedor.",
            "bloques": [
                {"figs": [dict(img="p48_x199", pie="Pantalla «Mis compras».", marcas=[(1, ("zona", "Queso", "unidad", 30))])],
                 "pasos": [
                     (1, "Abrir una compra", "Toque la fila del producto."),
                     (2, "Revisar el detalle", "«Detalle de compra» muestra producto, cantidad, precio unitario, total, forma de pago y fecha. Si se pagó con descuento semanal, «Ver el pago donde se descontó» abre la liquidación."),
                 ]},
            ],
            "resultado": ("La lista es un registro de compras, no un botón para comprar.", "Lista observada; detalle pendiente de verificación visual."),
        },
        {
            "codigo": "MOB-PRV-007", "titulo": "Leer comunicados", "estado": PAR,
            "proposito": "Leer los avisos publicados para los proveedores.",
            "acceso": "Menú › Comunicados.", "requisitos": "Sesión de Proveedor.",
            "bloques": [
                {"pasos": [(1, "Abrir un comunicado", "Toque el comunicado en la lista. La etiqueta «Nuevo» identifica uno no leído.")]},
                {"figs": [dict(img="p49_x203", pie="Pantalla «Comunicado».", marcas=[(2, ("zona", "Buenos", "app", 26)), (3, ("fila", "Fecha del", 24))])],
                 "pasos": [
                     (2, "Leer el contenido", "El texto completo del aviso y su fecha de publicación."),
                     (3, "Revisar la fecha del evento", "Si el aviso anuncia una actividad, se muestra su fecha."),
                 ]},
            ],
            "resultado": ("El comunicado queda marcado como leído.", "Detalle observado; lista pendiente de verificación visual."),
        },
        {
            "codigo": "MOB-PRV-008", "titulo": "Consultar notificaciones y Mi perfil", "estado": VER,
            "proposito": "Revisar los avisos del sistema y los datos personales.",
            "acceso": "Campana; Menú › Mi perfil.", "requisitos": "Sesión de Proveedor.",
            "bloques": [
                {"figs": [dict(img="p50_x205", pie="Pantalla «Notificaciones».", marcas=[(1, ("zona", "Entrega", "analisis", 22))]),
                          dict(img="p50_x206", pie="Pantalla «Mi perfil».", marcas=[(2, ("zona", "Nombre", "Telefono", 30))])],
                 "pasos": [
                     (1, "Leer una notificación", "Toque la notificación; el punto verde desaparece al quedar leída. No se abre otra pantalla."),
                     (2, "Consultar Mi perfil", "Nombre, DNI, zona, estado y teléfono."),
                 ]},
            ],
            "resultado": ("El contador de la campana disminuye al leer las notificaciones.", OBS),
        },
        {
            "codigo": "MOB-PRV-009", "titulo": "Consultar la sincronización", "estado": COD,
            "proposito": "Comprobar si los datos del teléfono están al día.",
            "acceso": "Menú › Sincronización.", "requisitos": "Sesión de Proveedor.",
            "bloques": [{"pasos": [
                (1, "Revisar el estado", "«Estado actual» indica «Con conexión» o «Sin conexión», y «Al día», «Sincronizando…» o «En espera de conexión»."),
                (2, "Revisar los pendientes", "«Última sincronización», «Pendientes» y «Errores». Los errores se reintentan solos."),
            ]}],
            "resultado": ("«Los datos se actualizan solos cuando hay conexión. Sin conexión puedes seguir usando la app con lo guardado en el teléfono.»", PREV),
        },
    ],
}

# ============================================================================ CAPÍTULO 7: ACOPIADOR
ACOPIADOR = {
    "titulo": "Manual del Acopiador",
    "intro": "El Acopiador registra las entregas de leche de los proveedores de su zona, con o sin conexión, sigue su "
             "recorrido del día y consulta sus proveedores y el seguimiento de calidad.",
    "ficha": [
        ["Barra inferior", "Inicio · Entregas · Proveedores"],
        ["Menú lateral", ["Operación: Inicio, Registrar entrega, Mis entregas, Recorrido",
                          "Gestión: Mis proveedores, Seguimiento de calidad",
                          "Comunicación: Comunicados, Notificaciones", "Sistema: Mi QR, Mi perfil"]],
        ["Requiere asistencia", "Sí, para registrar entregas y operar el recorrido (sección 2.3)."],
    ],
    "procedimientos": [
        {
            "codigo": "MOB-ACO-001", "titulo": "Consultar la pantalla Inicio", "estado": VER,
            "proposito": "Revisar el acopio del día y acceder al registro de entregas.",
            "acceso": "Barra inferior › «Inicio».", "requisitos": "Sesión de Acopiador.",
            "bloques": [
                {"figs": [dict(img="p51_x210", pie="Pantalla «Inicio» del Acopiador.", marcas=[
                    (1, ("zona", "Leche", "368", 40)), (2, ("boton", 1)), (3, ("zona", "Ultimas", "Luis", 50))])],
                 "elementos": [
                     (1, "Resumen de hoy", "«Leche registrada» y «Entregas realizadas»."),
                     (2, "«Registrar entrega»", "Inicia una nueva recepción (MOB-ACO-002)."),
                     (3, "«Últimas entregas»", "Entregas recientes con su estado. «Ver todas» abre el historial."),
                 ]},
            ],
            "resultado": ("Cada entrega muestra «Sincronizada» o «Pendiente».", OBS),
        },
        {
            "codigo": "MOB-ACO-002", "titulo": "Registrar una entrega de leche", "estado": VER,
            "proposito": "Registrar los litros entregados por un proveedor.",
            "acceso": "«Inicio» › «Registrar entrega», Menú › Registrar entrega, o el detalle de un proveedor.",
            "requisitos": "Asistencia del día registrada. Proveedor activo de la zona del acopiador.",
            "bloques": [
                {"figs": [dict(img="p54_x218", pie="Escaneo del QR del proveedor.", marcas=[
                    (1, ("caja", 250, 640, 670, 1200)), (2, ("fila", "Ingresar", 50))]),
                          dict(img="p54_x217", pie="Búsqueda por DNI.", marcas=[
                    (3, ("caja", 36, 1690, 876, 1815)), (4, ("caja", 455, 1830, 880, 1965))])],
                 "pasos": [
                     (1, "Escanear el QR", "Enfoque el código QR del proveedor dentro del recuadro; se lee automáticamente."),
                     (2, "O usar el DNI", "Si no puede escanear, pulse «Ingresar DNI»."),
                     (3, "Escribir el DNI", "Escriba el número en «Número de DNI»."),
                     (4, "Buscar", "Pulse «Buscar proveedor»."),
                 ]},
                {"figs": [dict(img="p55_x221", pie="Formulario «Registrar entrega».", marcas=[
                    (5, ("texto", "Cambiar", 28)), (6, ("zona", "Cantidad", "0", 30)), (7, ("caja", 36, 845, 876, 1040)), (8, ("boton", 1))]),
                          dict(img="p55_x220", pie="Confirmación «Entrega guardada».", marcas=[
                    (9, ("boton", 1)), (10, ("fila", "Ver historial", 45))])],
                 "pasos": [
                     (5, "Comprobar el proveedor", "Revise el nombre y la zona. Pulse «Cambiar» si no es el proveedor correcto."),
                     (6, "Indicar los litros", "Escriba la «Cantidad de leche»."),
                     (7, "Agregar una observación", "Opcional."),
                     (8, "Guardar", "Pulse «Guardar entrega»."),
                     (9, "Registrar otra", "Pulse «Registrar otra entrega» para continuar con otro proveedor."),
                     (10, "Ver el historial", "O pulse «Ver historial»."),
                 ]},
            ],
            "resultado": ("Se muestra «Entrega guardada — Se registró correctamente en el servidor.» con el estado «Sincronizada». "
                          "La leche registrada aumenta el stock de leche de la planta.", OBS),
            "validaciones": [
                ["DNI incompleto", "«El DNI debe tener 8 dígitos.»"],
                ["Litros en cero", "«Ingresa una cantidad mayor a 0.»"],
                ["Proveedor distinto al de la parada del recorrido", "«<proveedor> no es el proveedor de esta parada.»"],
            ],
        },
        {
            "codigo": "MOB-ACO-003", "titulo": "Registrar entregas sin conexión", "estado": VER,
            "proposito": "Continuar el acopio cuando no hay Internet.",
            "acceso": "Igual que MOB-ACO-002.", "requisitos": "Asistencia del día confirmada mientras había conexión.",
            "bloques": [
                {"figs": [dict(img="p56_x220", pie="Entrega guardada con conexión.", marcas=[(1, ("texto", "Sincronizada", 14))]),
                          dict(img="p56_x223", pie="Entrega guardada sin conexión.", marcas=[(2, ("texto", "Pendiente", 14))])],
                 "elementos": [
                     (1, "«Sincronizada»", "La entrega se registró en el servidor."),
                     (2, "«Pendiente de sincronización»", "La entrega quedó guardada en el teléfono: «Se enviará sola cuando vuelva la conexión.»"),
                 ]},
            ],
            "resultado": ("Al volver la conexión, la aplicación envía las entregas pendientes automáticamente. Compruebe en el "
                          "historial que cambien a «Sincronizada».", "Estados observados; envío automático según la implementación."),
        },
        {
            "codigo": "MOB-ACO-004", "titulo": "Consultar el historial y corregir entregas", "estado": PAR,
            "proposito": "Buscar entregas, revisar su estado y corregir las que no se pudieron enviar.",
            "acceso": "Barra inferior › «Entregas», o «Inicio» › «Ver todas».", "requisitos": "Sesión de Acopiador.",
            "bloques": [
                {"figs": [dict(img="p57_x226", pie="«Historial de entregas».", marcas=[
                    (1, ("fila", "Buscar", 45)), (2, ("fila", "Todas", 40)), (3, ("zona", "Sofia", "05:59", 24))]),
                          dict(img="p57_x225", pie="«Detalle de entrega».", marcas=[
                    (4, ("zona", "Estado de", "conexion", 26)), (5, ("zona", "Control", "servidor", 26))])],
                 "pasos": [
                     (1, "Buscar", "Escriba el proveedor o DNI en «Buscar proveedor o DNI»."),
                     (2, "Filtrar", "«Todas», «Hoy», «Esta semana» o «Este mes»."),
                     (3, "Abrir una entrega", "Toque la entrega."),
                     (4, "Revisar el estado de registro", "«Pendiente», «Registrada en el servidor» o error de sincronización."),
                     (5, "Revisar el control de calidad", "«Sin muestreo», «Seleccionada para análisis», «En análisis» o «Analizada»."),
                 ]},
                {"pasos": [
                    (6, "Corregir una entrega con error", "Si aparece «Error de sincronización», pulse «Corregir y reintentar», ajuste los litros u observación y pulse «Guardar y reintentar»."),
                    (7, "Descartar (solo no enviadas)", "«Descartar registro» borra del teléfono una entrega que no llegó al servidor."),
                ]},
            ],
            "resultado": ("El estado de la entrega se actualiza al sincronizar.", "Historial y detalle observados; corrección pendiente de verificación visual."),
            "validaciones": [["Corrección fuera de plazo", "«Pasaron más de 10 minutos desde la hora de acopio.»"]],
        },
        {
            "codigo": "MOB-ACO-005", "titulo": "Realizar el recorrido del día", "estado": COD,
            "proposito": "Visitar a los proveedores de la ruta asignada y registrar cada parada.",
            "acceso": "Menú › Recorrido.", "requisitos": "Asistencia del día registrada. Recorrido asignado por el administrador.",
            "bloques": [{"pasos": [
                (1, "Abrir el recorrido de hoy", "«Recorrido de hoy» muestra la ruta, la zona, el estado, los proveedores y los visitados. Sin asignación: «No tienes un recorrido asignado para hoy.»"),
                (2, "Iniciar", "Pulse «Iniciar recorrido» (o «Continuar recorrido» si ya está en curso)."),
                (3, "Registrar una parada", "En cada proveedor de la lista pulse «Registrar» para abrir el registro de entrega (MOB-ACO-002)."),
                (4, "Registrar una ausencia", "Si el proveedor no entregó, pulse «No entregó», elija el «Motivo» («No tenía leche», «No estaba» u «Otro») y pulse «Guardar»."),
                (5, "Finalizar", "Al terminar pulse «Finalizar recorrido» y confirme con «Finalizar». Después ya no podrá registrar acopios en ese recorrido."),
            ]}],
            "resultado": ("Cada parada queda como registrada o «No entregó», y el recorrido pasa a «Finalizado». Los registros "
                          "sin conexión se envían solos.", PREV),
        },
        {
            "codigo": "MOB-ACO-006", "titulo": "Consultar mis proveedores", "estado": VER,
            "proposito": "Buscar un proveedor de la zona y consultar sus entregas.",
            "acceso": "Barra inferior › «Proveedores», o Menú › Mis proveedores.", "requisitos": "Sesión de Acopiador.",
            "bloques": [
                {"figs": [dict(img="p58_x229", pie="«Mis proveedores».", marcas=[
                    (1, ("fila", "Buscar", 45)), (2, ("zona", "Juan Mamani", "Zona", 22))]),
                          dict(img="p58_x228", pie="«Detalle de proveedor».", marcas=[
                    (3, ("boton", 1)), (4, ("fila", "historial", 45))])],
                 "pasos": [
                     (1, "Buscar", "Escriba el nombre o DNI."),
                     (2, "Abrir el proveedor", "Toque el proveedor para ver su DNI, zona, teléfono y totales."),
                     (3, "Registrar una entrega", "«Registrar entrega» abre el formulario con ese proveedor ya seleccionado."),
                     (4, "Ver su historial", "«Ver historial» lista sus entregas; puede filtrarlas por fecha."),
                 ]},
            ],
            "resultado": ("Solo se listan los proveedores asignados a la zona del acopiador.", OBS),
        },
        {
            "codigo": "MOB-ACO-007", "titulo": "Consultar el seguimiento de calidad y los comunicados", "estado": VER,
            "proposito": "Saber qué entregas fueron muestreadas y leer los avisos.",
            "acceso": "Menú › Seguimiento de calidad; Menú › Comunicados.", "requisitos": "Sesión de Acopiador.",
            "bloques": [
                {"figs": [dict(img="p59_x231", pie="«Seguimiento de calidad».", marcas=[
                    (1, ("fila", "Todas", 40)), (2, ("zona", "Carlos Cahuana", "89 L", 22))]),
                          dict(img="p59_x232", pie="«Comunicados».", marcas=[
                    (3, ("fila", "Todos", 40)), (4, ("zona", "Aviso", "Hoy", 26))])],
                 "pasos": [
                     (1, "Filtrar resultados", "«Todas», «En control», «Con resultado» o «Sin muestreo»."),
                     (2, "Abrir una entrega", "Toque la entrega para ver su detalle de calidad."),
                     (3, "Filtrar comunicados", "«Todos», «No leídos» o «Importantes»."),
                     (4, "Leer un aviso", "Toque el comunicado. El punto verde indica que no se ha leído."),
                 ]},
            ],
            "resultado": ("«Calidad analiza solo las entregas seleccionadas como muestra.»: la mayoría de entregas aparecen como «Sin muestreo».", OBS),
        },
        {
            "codigo": "MOB-ACO-008", "titulo": "Consultar Mi QR y Mi perfil", "estado": VER,
            "proposito": "Mostrar el QR de asistencia y revisar los datos de la cuenta.",
            "acceso": "Menú › Mi QR; Menú › Mi perfil.", "requisitos": "Sesión de Acopiador.",
            "bloques": [
                {"figs": [dict(img="p60_x236", pie="«Mi QR / Asistencia».", marcas=[(1, ("caja", 115, 740, 797, 1390))]),
                          dict(img="p60_x235", pie="«Mi perfil».", marcas=[(2, ("zona", "Usuario", "Estado", 30)), (3, ("fila", "Cerrar", 45))])],
                 "elementos": [
                     (1, "Código QR", "Se muestra al administrador para registrar la asistencia diaria."),
                     (2, "Datos de la cuenta", "Usuario, DNI, teléfono, zona asignada y estado."),
                     (3, "«Cerrar sesión»", "Sale de la cuenta."),
                 ]},
            ],
            "resultado": ("Pantallas de consulta.", OBS),
        },
    ],
}

CAPITULOS = [GENERAL, PLANTA, VENTAS, CALIDAD, PROVEEDOR, ACOPIADOR]

# ============================================================================ ANEXOS
GLOSARIO = [
    ["Acopio", "Recepción de la leche entregada por un proveedor."],
    ["Análisis de calidad", "Registro de los parámetros de una muestra (grasa, proteína, densidad, etc.) y su resultado."],
    ["Asistencia", "Registro diario del trabajador, hecho por el administrador al escanear su QR. Habilita las operaciones."],
    ["Descuento del pago semanal", "Forma de pago de un proveedor: el importe se descuenta de su próximo pago."],
    ["Insumo", "Materia prima para producir: leche, cuajo, sal, saborizantes."],
    ["Liquidación", "Cálculo del pago semanal al proveedor por la leche entregada."],
    ["Lote", "Tanda de producción identificada con un código (LOT-00001)."],
    ["Muestreo", "Selección de entregas que serán analizadas por Calidad."],
    ["OCR", "Lectura automática del texto de una foto (boleta o ticket del analizador)."],
    ["Presentación", "Formato de compra de un insumo (balde 25 L, saco 10 kg)."],
    ["Receta", "Cantidad de cada insumo que lleva una unidad de producto."],
    ["Sincronización", "Envío automático al servidor de lo guardado en el teléfono."],
    ["Stock", "Cantidad disponible en inventario."],
    ["Unidad base", "Unidad en la que se guarda el stock de un insumo (ml, g o L)."],
]

PROBLEMAS = [
    ["No puedo realizar operaciones; aparece «Asistencia pendiente».", "Muestre su QR al administrador (MOB-GEN-003) y espere a que el aviso desaparezca."],
    ["«Asistencia no verificada para hoy» sin conexión.", "Conéctese a Internet después de registrar la asistencia; luego podrá operar sin conexión."],
    ["«Usuario o contraseña incorrectos».", "Verifique mayúsculas y minúsculas. Si persiste, solicite al administrador restablecer la contraseña."],
    ["Una operación sigue como «Pendiente».", "Compruebe la conexión (punto verde). El envío es automático; no repita la operación."],
    ["Un insumo no alcanza para producir.", "Registre la compra (MOB-PLA-005 o MOB-PLA-006) y vuelva a «Nueva producción»."],
    ["No aparece «Descuento del pago semanal».", "Solo está disponible para clientes de tipo Proveedor y con conexión."],
    ["No se puede leer el QR del proveedor.", "Use «Ingresar DNI»."],
    ["Quiero cambiar una receta o un precio.", "Solo el administrador puede hacerlo desde EcoLactWeb."],
]
