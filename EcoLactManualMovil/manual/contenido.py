"""Contenido del Manual de Usuario de EcoLact Móvil — versión 3.0 (diseño visual de uso rápido).

Convenciones de los datos:
- Procedimiento: codigo, titulo, estado, frase (una oración), bloques, y opcionalmente:
  nota (una advertencia breve), lista (titulo, [items]), mensajes ([[situación, mensaje]] → Anexo C),
  pendiente (captura que falta → Anexo D).
- Bloque: dict(figs=[...], pasos=[(n, instrucción), ...]). Sin «figs» se muestra «Captura pendiente».
  El número de cada paso coincide con la marca roja de la captura.
- Figura: dict(img=<captura>, marcas=[(n, ancla), ...]).
  · img "pNN_xMMM": captura del manual anterior (28/10/2026), con datos personales difuminados.
  · Las capturas del Encargado de planta (p06–p17) traen sus marcas incrustadas: marcas=None;
    si el bloque tiene dos capturas, «nums» indica qué números lleva cada una.
  · ancla: ('fila', 'texto' [, alto]) control a todo el ancho centrado en ese texto;
           ('texto', 'texto' [, margen]) recuadro ajustado al texto;
           ('zona', 'texto inicial', 'texto final' [, margen]) franja a todo el ancho;
           ('boton', n) n-ésimo botón verde; ('caja', x1, y1, x2, y2) coordenadas en píxeles.
    Un sufijo '#2' en el texto elige la segunda aparición.
- {MOB-XXX-NNN} dentro de un texto se reemplaza por el número de sección del procedimiento.
- Estados (solo en el Anexo D): VER, PAR, COD.
"""

VER = "Verificado en interfaz"
PAR = "Verificado parcialmente"
COD = "Implementado en código; pendiente de verificación visual"

# ============================================================================ CAPÍTULO 1
INTRODUCCION = {
    "titulo": "Introducción",
    "bloques": [
        ("h2", "Para qué sirve este manual"),
        ("p", "Este manual muestra, paso a paso, cómo usar la aplicación móvil **EcoLact** de Ecolácteos Huata. "
              "Busca el capítulo de tu rol, mira la captura y sigue los números."),
        ("lista", [
            "**Capítulo 2.** Acceso y navegación (todos los roles).",
            "**Capítulo 3.** Encargado de planta.",
            "**Capítulo 4.** Personal de ventas.",
            "**Capítulo 5.** Analista de calidad.",
            "**Capítulo 6.** Proveedor.",
            "**Capítulo 7.** Acopiador.",
        ]),
        ("p", "El Administrador trabaja desde el sistema web EcoLactWeb, no desde la aplicación móvil."),
        ("h2", "Antes de empezar"),
        ("lista", [
            "Teléfono Android con la aplicación EcoLact instalada.",
            "Usuario y contraseña entregados por el administrador.",
            "Conexión a Internet para iniciar sesión.",
            "Trabajadores: asistencia del día registrada (ver {MOB-GEN-003}).",
        ]),
        ("h2", "Cómo leer las capturas"),
        ("lista", [
            "Cada **número rojo** de la captura corresponde al paso con el mismo número.",
            "El texto entre «comillas» es el nombre exacto de un botón, campo o mensaje.",
            "Menú › Opción: pulsa ☰ y elige la opción indicada.",
            "Los datos personales (DNI, teléfonos, usuarios y códigos QR) aparecen difuminados.",
        ]),
    ],
}

# ============================================================================ CAPÍTULO 2
GENERAL = {
    "titulo": "Acceso y navegación",
    "intro": "Funciones comunes a todos los roles.",
    "procedimientos": [
        {
            "codigo": "MOB-GEN-001", "titulo": "Iniciar sesión", "estado": VER,
            "frase": "Ingresa con el usuario y la contraseña que te entregó el administrador.",
            "bloques": [
                {"figs": [dict(img="p52_x213", marcas=[
                    (1, ("caja", 55, 880, 857, 1022)), (2, ("caja", 55, 1086, 857, 1226)), (3, ("boton", 1))])],
                 "pasos": [
                     (1, "Escribe tu usuario en «Usuario»."),
                     (2, "Escribe tu contraseña en «Contraseña»."),
                     (3, "Pulsa «Iniciar sesión». Se abre tu pantalla «Inicio»."),
                 ]},
            ],
            "nota": "Necesitas conexión a Internet para iniciar sesión.",
            "mensajes": [
                ["Usuario o contraseña vacíos", "«Ingrese usuario y contraseña»"],
                ["Credenciales incorrectas", "«Usuario o contraseña incorrectos»"],
                ["Cuenta sin acceso a la aplicación", "«Esta cuenta no tiene acceso»"],
                ["Sin conexión con el servidor", "«No se pudo conectar. Verifique su conexión a internet.»"],
            ],
        },
        {
            "codigo": "MOB-GEN-002", "titulo": "Conocer la pantalla principal", "estado": VER,
            "frase": "Todos los roles usan la misma barra superior, la barra inferior y el menú lateral.",
            "bloques": [
                {"figs": [dict(img="p53_x210", marcas=[
                    (1, ("caja", 18, 160, 125, 285)), (2, ("caja", 760, 160, 900, 285)), (3, ("zona", "Inicio#2", "Proveedores", 40))]),
                          dict(img="p53_x215", marcas=[
                    (4, ("zona", "OPERACION", "Seguimiento", 30)), (5, ("fila", "Cerrar", 45))])],
                 "pasos": [
                     (1, "Pulsa ☰ para abrir el menú lateral."),
                     (2, "Mira el punto: verde con Internet, naranja sin Internet. La campana abre «Notificaciones»."),
                     (3, "Usa la barra inferior para cambiar de pantalla."),
                     (4, "Pulsa una opción del menú lateral."),
                     (5, "Pulsa «Cerrar sesión» para salir."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-GEN-003", "titulo": "Registrar la asistencia del día", "estado": VER,
            "frase": "Muestra tu código QR al administrador para habilitar tus operaciones del día.",
            "bloques": [
                {"figs": [dict(img="p08_x95", marcas=None, nums=[1, 2]), dict(img="p08_x94", marcas=None, nums=[3, 4])],
                 "pasos": [
                     (1, "Revisa el aviso «Asistencia pendiente» en «Inicio»."),
                     (2, "Pulsa «Ver mi QR»."),
                     (3, "Muestra el código QR al administrador."),
                     (4, "Espera a que lo escanee. El aviso de «Inicio» desaparece solo."),
                 ]},
            ],
            "nota": "Importante: sin asistencia puedes consultar, pero no operar. La asistencia vale solo para el día. "
                    "El Proveedor no registra asistencia.",
            "mensajes": [
                ["Operación sin asistencia registrada", "«Necesitas registrar tu asistencia antes de realizar esta operación.»"],
                ["Sin conexión y sin asistencia confirmada hoy", "«Asistencia no verificada para hoy. Conéctate a Internet después de registrar tu asistencia para habilitar las operaciones.»"],
                ["Trabajador sin QR activo", "«Tu QR de asistencia no está activo. Consulta con el administrador.»"],
            ],
        },
        {
            "codigo": "MOB-GEN-004", "titulo": "Trabajar sin conexión", "estado": PAR,
            "frase": "La aplicación guarda tus operaciones en el teléfono y las envía sola al volver la conexión.",
            "bloques": [
                {"figs": [dict(img="p56_x223", marcas=[(1, ("texto", "Pendiente", 14))])],
                 "pasos": [
                     (1, "Registra la operación como siempre. Queda como «Pendiente»."),
                     (2, "Recupera la conexión. El envío es automático."),
                     (3, "Revisa el historial: el registro cambia a «Sincronizada»."),
                 ]},
            ],
            "lista": ("Qué puedes hacer sin conexión", [
                "**Planta:** iniciar y finalizar producción; compra manual; foto de boleta (se lee al volver la conexión).",
                "**Ventas:** venta en efectivo con el precio guardado.",
                "**Calidad:** muestreo directo, selección manual y análisis con valores escritos a mano.",
                "**Acopio:** registrar entregas y paradas del recorrido.",
                "**Proveedor:** consultar la información guardada.",
            ]),
            "nota": "Importante: confirma tu asistencia del día con Internet antes de quedarte sin conexión. "
                    "Evita cerrar sesión sin conexión: lo pendiente se enviará recién al volver a ingresar.",
            "mensajes": [
                ["Cerrar sesión sin conexión", "«Estás sin internet. Lo guardado en el teléfono se enviará al volver a ingresar con conexión. ¿Cerrar sesión de todas formas?»"],
            ],
            "pendiente": "Envío automático tras recuperar la conexión (prueba funcional).",
        },
        {
            "codigo": "MOB-GEN-005", "titulo": "Ver notificaciones y cerrar sesión", "estado": VER,
            "frase": "Revisa tus avisos y sal de la cuenta al terminar.",
            "bloques": [
                {"figs": [dict(img="p50_x205", marcas=[(1, ("zona", "Entrega", "analisis", 22))]),
                          dict(img="p60_x235", marcas=[(2, ("fila", "Cerrar", 45))])],
                 "pasos": [
                     (1, "Pulsa la campana y luego la notificación. El punto verde indica que no la leíste."),
                     (2, "En «Mi perfil», pulsa «Cerrar sesión»."),
                 ]},
            ],
            "nota": "Si compartes el teléfono, cierra sesión al terminar tu turno.",
        },
    ],
}

# ============================================================================ CAPÍTULO 3: PLANTA
PLANTA = {
    "titulo": "Encargado de planta",
    "intro": "Controla los insumos, registra sus compras e inicia y finaliza la producción. Las recetas y los precios "
             "los configura el administrador en EcoLactWeb.",
    "datos": [
        ("Barra inferior", "Inicio · Producción · Inventario"),
        ("Asistencia", "necesaria para registrar compras y producciones."),
    ],
    "flujo": [
        "Muestra tu QR al administrador ({MOB-GEN-003}).",
        "Revisa el resumen de «Inicio» ({MOB-PLA-001}).",
        "Registra las compras de insumos ({MOB-PLA-005} o {MOB-PLA-006}).",
        "Inicia la producción ({MOB-PLA-007}).",
        "Finaliza cada lote con lo obtenido ({MOB-PLA-008}).",
    ],
    "stock": [
        "**Compra confirmada:** aumenta el stock de los insumos.",
        "**Iniciar producción:** descuenta los insumos de la receta.",
        "**Finalizar producción:** aumenta el stock del producto.",
        "**Acopio de leche:** aumenta el stock de leche (no se compra en este módulo).",
    ],
    "procedimientos": [
        {
            "codigo": "MOB-PLA-001", "titulo": "Consultar la pantalla Inicio", "estado": VER,
            "frase": "Revisa el estado de la planta al empezar el día.",
            "bloques": [
                {"figs": [dict(img="p06_x90", marcas=None)],
                 "pasos": [
                     (1, "Revisa «Leche disponible», «Lotes en proceso» y «Stock bajo»."),
                     (2, "Usa «Nueva producción», «Escanear boleta» o «Mi QR»."),
                     (3, "Pulsa un lote en proceso para abrir su detalle."),
                     (4, "Cambia de pantalla con la barra inferior."),
                     (5, "Pulsa la campana para ver tus notificaciones."),
                 ]},
            ],
            "nota": "Si «Stock bajo» es mayor que 0, registra la compra que falta.",
        },
        {
            "codigo": "MOB-PLA-002", "titulo": "Usar el menú lateral", "estado": VER,
            "frase": "Pulsa ☰ para abrir las secciones que no están en la barra inferior.",
            "bloques": [
                {"figs": [dict(img="p07_x92", marcas=None)],
                 "pasos": [
                     (1, "«Operación»: abre «Inicio» o «Producción»."),
                     (2, "«Inventario»: abre «Insumos», «Productos» o «Recetas»."),
                     (3, "«Gestión»: abre «Compras de insumos»."),
                     (4, "«Sistema»: abre «Mi QR», «Notificaciones» o «Mi perfil»."),
                     (5, "Pulsa «Cerrar sesión» al terminar tu turno."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-PLA-003", "titulo": "Consultar los insumos", "estado": VER,
            "frase": "Revisa el stock, las presentaciones y los movimientos de cada insumo.",
            "bloques": [
                {"figs": [dict(img="p09_x97", marcas=None, nums=[1, 2, 3]), dict(img="p09_x98", marcas=None, nums=[4, 5, 6, 7])],
                 "pasos": [
                     (1, "Pulsa la pestaña «Insumos»."),
                     (2, "La leche viene del acopio: no se compra aquí."),
                     (3, "Pulsa un insumo para abrir su detalle."),
                     (4, "Revisa «Stock actual», «Stock mínimo» y «Unidad base»."),
                     (5, "Revisa las presentaciones de compra."),
                     (6, "Revisa los movimientos recientes."),
                     (7, "Pulsa «Escanear boleta» o «Registrar compra manual»."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-PLA-004", "titulo": "Consultar las compras de insumos", "estado": VER,
            "frase": "Revisa las compras registradas y las guardadas en el teléfono.",
            "bloques": [
                {"figs": [dict(img="p10_x101", marcas=None)],
                 "pasos": [
                     (1, "Pulsa «Escanear boleta» para comprar con foto (ver {MOB-PLA-005})."),
                     (2, "Pulsa «Registrar compra manual» para escribir los datos (ver {MOB-PLA-006})."),
                     (3, "En «En el teléfono», pulsa una compra para completarla."),
                     (4, "Revisa «Compras registradas»: número, fecha, insumos y total."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-PLA-005", "titulo": "Registrar una compra con boleta", "estado": PAR,
            "frase": "Escanea la boleta y revisa los datos antes de guardar la compra.",
            "bloques": [
                {"pasos": [
                    (1, "Pulsa «Escanear boleta» y toma una foto clara de la boleta."),
                    (2, "En «Datos detectados», revisa insumo, presentación y cantidad."),
                    (3, "Completa proveedor, comprobante, fecha y total."),
                    (4, "Pulsa «Confirmar compra»."),
                ]},
                {"figs": [dict(img="p10_x100", marcas=None)],
                 "pasos": [
                     (5, "Revisa el mensaje «Compra registrada»."),
                     (6, "Pulsa «Continuar»."),
                 ]},
            ],
            "nota": "Importante: verifica las cantidades con la boleta física antes de confirmar. La lectura automática necesita conexión.",
            "mensajes": [
                ["Líneas incompletas", "«Completa el insumo, la presentación y la cantidad de cada línea.»"],
                ["La boleta no se pudo leer", "«No se pudo procesar la boleta» («Tomar otra foto» o «Cancelar»)."],
            ],
            "pendiente": "Pantallas «Tomar foto de la boleta» y «Datos detectados».",
        },
        {
            "codigo": "MOB-PLA-006", "titulo": "Registrar una compra manual", "estado": VER,
            "frase": "Escribe los insumos comprados y confirma la compra.",
            "bloques": [
                {"figs": [dict(img="p11_x103", marcas=None)],
                 "pasos": [
                     (1, "Elige el insumo, escribe la cantidad y elige la presentación."),
                     (2, "Revisa el «Resultado» (1 saco de 10 kg = 10 000 g)."),
                     (3, "Pulsa «Agregar insumo» si compraste otro insumo."),
                     (4, "Completa proveedor, comprobante y fecha (opcionales)."),
                     (5, "Escribe el «Total de la compra»."),
                     (6, "Pulsa «Confirmar compra»."),
                     (7, "Pulsa «Descartar» para cancelar sin guardar."),
                 ]},
            ],
            "nota": "Importante: el «Resultado» debe coincidir con lo que realmente ingresó a la planta.",
            "mensajes": [
                ["Líneas incompletas", "«Completa el insumo, la presentación y la cantidad de cada línea.»"],
                ["Insumo sin presentaciones", "«Sin presentaciones configuradas» (las configura el administrador)."],
            ],
        },
        {
            "codigo": "MOB-PLA-007", "titulo": "Iniciar una producción", "estado": VER,
            "frase": "Elige el producto, indica la cantidad y comprueba que alcancen los insumos.",
            "bloques": [
                {"figs": [dict(img="p12_x105", marcas=None)],
                 "pasos": [
                     (1, "Elige el producto en «Producto (receta)»."),
                     (2, "Escribe las unidades en «Cantidad a producir»."),
                     (3, "Revisa los requerimientos: ✓ indica que el insumo alcanza."),
                     (4, "Pulsa «Iniciar producción». Los insumos se descuentan del stock."),
                 ]},
            ],
            "nota": "Si un insumo no alcanza, registra la compra y vuelve a esta pantalla.",
            "mensajes": [
                ["Un insumo no alcanza", "«<insumo>: stock insuficiente», con lo necesario, lo disponible y lo que falta."],
                ["Sin recetas activas", "«No hay recetas activas para producir. Las configura el administrador.»"],
                ["Inventario cambiado durante la desconexión", "«No se pudo iniciar la producción porque el inventario cambió mientras estabas sin conexión.»"],
            ],
        },
        {
            "codigo": "MOB-PLA-008", "titulo": "Finalizar una producción", "estado": VER,
            "frase": "Cierra el lote con las unidades que realmente obtuviste.",
            "bloques": [
                {"figs": [dict(img="p13_x107", marcas=None)],
                 "pasos": [
                     (1, "Comprueba el producto, el lote y el estado «En proceso»."),
                     (2, "Revisa la cantidad planificada y los insumos usados."),
                     (3, "Escribe la «Cantidad obtenida realmente»."),
                     (4, "Revisa el stock nuevo del producto."),
                     (5, "Pulsa «Finalizar producción»."),
                 ]},
            ],
            "mensajes": [["El servidor rechaza la finalización", "«No se pudo finalizar: <motivo>»"]],
        },
        {
            "codigo": "MOB-PLA-009", "titulo": "Consultar las producciones", "estado": VER,
            "frase": "Revisa los lotes en proceso y los finalizados.",
            "bloques": [
                {"figs": [dict(img="p14_x110", marcas=None, nums=[1, 2, 3]), dict(img="p14_x109", marcas=None, nums=[4, 5])],
                 "pasos": [
                     (1, "Pulsa «Nueva producción» para iniciar otro lote."),
                     (2, "Elige «En proceso» o «Finalizados»."),
                     (3, "Pulsa un lote para abrir su detalle."),
                     (4, "«Finalizado»: el lote ya ingresó al inventario."),
                     (5, "Revisa cantidades, insumos y fechas."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-PLA-010", "titulo": "Consultar los productos terminados", "estado": VER,
            "frase": "Revisa el stock y los precios de cada producto.",
            "bloques": [
                {"figs": [dict(img="p15_x112", marcas=None, nums=[1, 2]), dict(img="p15_x113", marcas=None, nums=[3, 4])],
                 "pasos": [
                     (1, "Pulsa la pestaña «Productos»."),
                     (2, "Pulsa un producto para abrir su detalle."),
                     (3, "Revisa presentación, stock, estado y última producción."),
                     (4, "Revisa los precios público, mayorista y proveedor."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-PLA-011", "titulo": "Consultar las recetas", "estado": VER,
            "frase": "Consulta qué insumos lleva cada unidad de producto.",
            "bloques": [
                {"figs": [dict(img="p16_x116", marcas=None, nums=[1, 2]), dict(img="p16_x115", marcas=None, nums=[3, 4, 5])],
                 "pasos": [
                     (1, "Pulsa una receta de la lista."),
                     (2, "«Activa»: es la receta que usa la producción."),
                     (3, "Revisa los insumos «Por cada unidad»."),
                     (4, "Revisa el estado, la versión y la fecha."),
                     (5, "Las recetas solo las cambia el administrador."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-PLA-012", "titulo": "Consultar Mi perfil", "estado": VER,
            "frase": "Revisa los datos de tu cuenta.",
            "bloques": [
                {"figs": [dict(img="p17_x118", marcas=None)],
                 "pasos": [
                     (1, "Revisa usuario, DNI, teléfono y rol."),
                     (2, "Pulsa «Cerrar sesión» para salir."),
                     (3, "Pulsa la campana para ver tus notificaciones."),
                 ]},
            ],
            "nota": "Para corregir tus datos, comunícate con el administrador.",
        },
    ],
}

# ============================================================================ CAPÍTULO 4: VENTAS
VENTAS = {
    "titulo": "Personal de ventas",
    "intro": "Registra ventas al público, a compradores registrados y a proveedores. El sistema aplica el precio "
             "según el tipo de cliente y la cantidad.",
    "datos": [
        ("Barra inferior", "Inicio · Vender · Historial"),
        ("Asistencia", "necesaria para registrar ventas y compradores."),
    ],
    "procedimientos": [
        {
            "codigo": "MOB-VEN-001", "titulo": "Consultar la pantalla Inicio", "estado": VER,
            "frase": "Revisa las ventas del día.",
            "bloques": [
                {"figs": [dict(img="p19_x125", marcas=[
                    (1, ("caja", 36, 480, 876, 830)), (2, ("zona", "Acciones", "historial#1", 50)),
                    (3, ("zona", "Ventas recientes", "Publico", 60)), (4, ("zona", "Inicio#2", "Historial#2", 30))])],
                 "pasos": [
                     (1, "Revisa «Cobrado hoy», «Ventas de hoy» y «Productos vendidos»."),
                     (2, "Usa «Nueva venta», «Ver historial» o «Mi QR»."),
                     (3, "Revisa las ventas recientes. «Ver todas» abre el historial."),
                     (4, "Cambia de pantalla con la barra inferior."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-VEN-002", "titulo": "Consultar productos y precios", "estado": VER,
            "frase": "Consulta el stock y los precios antes de vender.",
            "bloques": [
                {"figs": [dict(img="p22_x137", marcas=[
                    (1, ("fila", "Buscar", 45)), (2, ("zona", "Mantequilla", "Stock", 18)),
                    (3, ("zona", "Precio publico", "Precio proveedor", 22))])],
                 "pasos": [
                     (1, "Escribe el nombre en «Buscar producto…»."),
                     (2, "Revisa la presentación y el stock."),
                     (3, "Compara los precios. Un guion indica precio no configurado."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-VEN-003", "titulo": "Buscar y registrar compradores", "estado": PAR,
            "frase": "Busca un comprador o registra uno nuevo.",
            "bloques": [
                {"figs": [dict(img="p23_x139", marcas=[
                    (1, ("fila", "Nombre", 45)), (2, ("caja", 36, 600, 876, 760)), (3, ("boton", 1))])],
                 "pasos": [
                     (1, "Escribe el nombre, DNI o RUC en el buscador."),
                     (2, "Revisa el documento y el teléfono del comprador."),
                     (3, "Si no existe, pulsa «+ Nuevo comprador»."),
                 ]},
                {"pasos": [
                    (4, "Escribe nombre o razón social, DNI o RUC y teléfono."),
                    (5, "Pulsa «Registrar comprador»."),
                ]},
            ],
            "nota": "Necesitas conexión a Internet para registrar un comprador.",
            "mensajes": [["Sin conexión", "«Necesitas conexión para registrar un comprador.»"]],
            "pendiente": "Formulario «Nuevo comprador».",
        },
        {
            "codigo": "MOB-VEN-004", "titulo": "Registrar una venta", "estado": PAR,
            "frase": "Elige el producto y el cliente; el sistema calcula el precio.",
            "bloques": [
                {"pasos": [
                    (1, "Pulsa «Vender», busca el producto y pulsa «Agregar»."),
                ]},
                {"figs": [dict(img="p24_x141", marcas=[
                    (2, ("zona", "Publico general", "Sin registro", 26)), (3, ("zona", "Comprador registrado", "Buscar por", 26)),
                    (4, ("zona", "Proveedor#1", "semanal", 26))])],
                 "pasos": [
                     (2, "Elige «Público general» para una venta sin registro."),
                     (3, "O busca y elige un «Comprador registrado»."),
                     (4, "O busca y elige un «Proveedor»."),
                 ]},
                {"figs": [dict(img="p25_x143", marcas=[
                    (5, ("caja", 36, 740, 876, 890)), (6, ("zona", "FORMA", "Descuento", 30)), (7, ("boton", 1))]),
                          dict(img="p26_x145", marcas=[
                    (8, ("caja", 36, 1215, 876, 1630)), (9, ("fila", "aplicado", 35)), (10, ("boton", 1))])],
                 "pasos": [
                     (5, "Escribe la cantidad."),
                     (6, "Elige «Efectivo» o «Descuento del pago semanal»."),
                     (7, "Pulsa «Calcular total»."),
                     (8, "Revisa precio unitario, subtotal y «TOTAL»."),
                     (9, "Revisa el tipo de precio aplicado."),
                     (10, "Pulsa «Registrar venta»."),
                 ]},
            ],
            "nota": "Importante: verifica el total antes de registrar. El «Descuento del pago semanal» es solo para "
                    "proveedores y necesita conexión.",
            "mensajes": [
                ["Tipo de precio", "Proveedor → precio proveedor; cantidad igual o mayor al mínimo mayorista → precio mayorista; otro caso → precio público."],
                ["Descuento del pago semanal", "No puede superar el pago semanal disponible: «Este monto se descontará del próximo pago semanal del proveedor.»"],
                ["Descuento sin conexión", "«Necesitas conexión para validar el saldo disponible del proveedor.»"],
                ["Venta sin conexión", "«Venta guardada en el dispositivo» — «Precio guardado en el dispositivo. Se validará al enviar la venta.»"],
                ["Rechazo del servidor", "«No se pudo registrar la venta»"],
            ],
            "pendiente": "Pantalla «Nueva venta» (elegir producto) y confirmación «Venta registrada».",
        },
        {
            "codigo": "MOB-VEN-005", "titulo": "Consultar el historial de ventas", "estado": VER,
            "frase": "Busca una venta y abre su detalle.",
            "bloques": [
                {"figs": [dict(img="p27_x147", marcas=[
                    (1, ("fila", "Buscar", 45)), (2, ("fila", "Todas", 40)), (3, ("zona", "V-00005", "Completada", 22))])],
                 "pasos": [
                     (1, "Escribe el código o el cliente en el buscador."),
                     (2, "Elige «Todas», «Hoy», «Esta semana» o «Este mes»."),
                     (3, "Pulsa una venta para ver su detalle."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-VEN-006", "titulo": "Ver y compartir la boleta", "estado": COD,
            "frase": "Muestra, descarga o comparte la boleta de una venta.",
            "bloques": [
                {"pasos": [
                    (1, "Pulsa «Ver boleta» en la venta registrada o en su detalle."),
                    (2, "Pulsa «Descargar PDF» para guardarla en el teléfono."),
                    (3, "Pulsa «Compartir» para enviarla."),
                ]},
            ],
            "nota": "La boleta de una venta pendiente estará disponible después de enviarla.",
            "pendiente": "Pantalla «Boleta de venta», «Descargar PDF» y «Compartir».",
        },
        {
            "codigo": "MOB-VEN-007", "titulo": "Consultar Mi perfil y Mi QR", "estado": VER,
            "frase": "Revisa tu cuenta y muestra tu QR de asistencia.",
            "bloques": [
                {"figs": [dict(img="p28_x149", marcas=[
                    (1, ("zona", "Usuario", "Rol", 30)), (2, ("fila", "Cerrar", 45))]),
                          dict(img="p29_x151", marcas=[
                    (3, ("caja", 150, 600, 762, 1385)), (4, ("zona", "Este QR", "diaria", 30))])],
                 "pasos": [
                     (1, "Revisa usuario, DNI y rol."),
                     (2, "Pulsa «Cerrar sesión» para salir."),
                     (3, "Muestra el QR al administrador para tu asistencia."),
                     (4, "El QR sirve solo para la asistencia, no para cobrar."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-VEN-008", "titulo": "Consultar comunicados", "estado": COD,
            "frase": "Lee los comunicados publicados.",
            "bloques": [{"pasos": [
                (1, "Abre Menú › «Comunicados»."),
                (2, "Revisa la lista de comunicados."),
            ]}],
            "pendiente": "Lista de «Comunicados» del Personal de ventas.",
        },
    ],
}

# ============================================================================ CAPÍTULO 5: CALIDAD
CALIDAD = {
    "titulo": "Analista de calidad",
    "intro": "Elige las entregas que se analizan, registra los resultados y publica comunicados. Solo se analizan "
             "las entregas seleccionadas como muestra.",
    "datos": [
        ("Barra inferior", "Inicio · Muestreo · Historial"),
        ("Asistencia", "necesaria para muestrear, analizar y publicar comunicados."),
    ],
    "flujo": [
        "Selecciona entregas al azar, a mano o en campo ({MOB-CAL-002} y {MOB-CAL-003}).",
        "Abre una muestra pendiente ({MOB-CAL-001}).",
        "Registra el análisis ({MOB-CAL-004}).",
        "Consulta el historial ({MOB-CAL-005}).",
    ],
    "procedimientos": [
        {
            "codigo": "MOB-CAL-001", "titulo": "Consultar las muestras pendientes", "estado": VER,
            "frase": "Revisa en «Inicio» las muestras por analizar.",
            "bloques": [
                {"figs": [dict(img="p30_x156", marcas=[
                    (1, ("caja", 36, 450, 876, 600)), (2, ("zona", "Luis", "revisar", 24)),
                    (3, ("caja", 655, 868, 852, 982)), (4, ("zona", "Inicio#2", "Historial", 30))])],
                 "pasos": [
                     (1, "Revisa «Muestras pendientes» y «Analizadas hoy»."),
                     (2, "Revisa la muestra: proveedor, litros, zona y hora."),
                     (3, "Pulsa «Analizar» (ver {MOB-CAL-004})."),
                     (4, "Cambia de pantalla con la barra inferior."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-CAL-002", "titulo": "Seleccionar entregas para analizar", "estado": COD,
            "frase": "Elige al azar o a mano las entregas que se analizarán.",
            "bloques": [
                {"pasos": [
                    (1, "Pulsa «Muestreo» en la barra inferior."),
                    (2, "Pulsa «Elegir muestra al azar». «Elegir otra» propone otra entrega."),
                    (3, "O pulsa «Seleccionar manualmente», busca la entrega y pulsa «Seleccionar»."),
                    (4, "Pulsa «Confirmar muestra»."),
                    (5, "Pulsa «Analizar ahora» o «Ver pendientes»."),
                ]},
            ],
            "nota": "La muestra al azar necesita conexión. Sin conexión, usa la selección manual.",
            "mensajes": [
                ["Muestra al azar sin conexión", "«Se necesita conexión para elegir una muestra aleatoria. Puedes seleccionar manualmente.»"],
                ["Sin entregas", "«No hay entregas disponibles para muestrear.»"],
            ],
            "pendiente": "Pantallas «Muestreo», «Elegir muestra al azar» y «Seleccionar manualmente».",
        },
        {
            "codigo": "MOB-CAL-003", "titulo": "Registrar un muestreo directo en campo", "estado": VER,
            "frase": "Toma una muestra en campo identificando al proveedor por QR o DNI.",
            "bloques": [
                {"figs": [dict(img="p31_x162", marcas=[
                    (1, ("boton", 1)), (2, ("fila", "DNI del", 45))]),
                          dict(img="p33_x167", marcas=[
                    (3, ("caja", 350, 1040, 590, 1255))])],
                 "pasos": [
                     (1, "Pulsa «Escanear QR»."),
                     (2, "O escribe el DNI en «DNI del proveedor»."),
                     (3, "Enfoca el QR del proveedor; se lee solo."),
                 ]},
                {"figs": [dict(img="p32_x165", marcas=[
                    (4, ("fila", "Buscar", 45)), (5, ("zona", "encontrado", "Quispe", 50)), (6, ("boton", 2))]),
                          dict(img="p34_x169", marcas=[
                    (7, ("caja", 36, 430, 876, 565)), (8, ("fila", "Observacion", 45)), (9, ("boton", 1))])],
                 "pasos": [
                     (4, "Si escribiste el DNI, pulsa «Buscar»."),
                     (5, "Comprueba el nombre. No continúes si no corresponde."),
                     (6, "Pulsa «Continuar»."),
                     (7, "Escribe los litros de la muestra."),
                     (8, "Agrega una observación (opcional)."),
                     (9, "Pulsa «Continuar»."),
                 ]},
                {"figs": [dict(img="p35_x171", marcas=[
                    (10, ("zona", "Proveedor", "Metodo", 30)), (11, ("boton", 1)), (12, ("fila", "Corregir", 45))]),
                          dict(img="p36_x173", marcas=[
                    (13, ("boton", 1)), (14, ("fila", "Listo", 45))])],
                 "pasos": [
                     (10, "Revisa proveedor, cantidad y método."),
                     (11, "Pulsa «Confirmar»."),
                     (12, "Pulsa «Corregir» si debes cambiar algo."),
                     (13, "Pulsa «Continuar a análisis» para analizar ahora."),
                     (14, "O pulsa «Listo»: la muestra queda pendiente."),
                 ]},
            ],
            "mensajes": [
                ["DNI con otra longitud", "«El DNI debe tener exactamente 8 dígitos»"],
                ["DNI no registrado", "«No se encontró ese DNI»"],
                ["Litros inválidos", "«Ingrese una cantidad de litros válida»"],
            ],
        },
        {
            "codigo": "MOB-CAL-004", "titulo": "Registrar el análisis de una muestra", "estado": PAR,
            "frase": "Registra los valores del analizador y confirma el resultado.",
            "bloques": [
                {"figs": [dict(img="p37_x175", marcas=[
                    (1, ("zona", "Litros", "Fecha de", 30)), (2, ("boton", 1)), (3, ("fila", "Ingresar", 45))])],
                 "pasos": [
                     (1, "Revisa los datos de la muestra."),
                     (2, "Pulsa «Escanear ticket» y toma la foto del ticket."),
                     (3, "O pulsa «Ingresar manualmente»."),
                 ]},
                {"pasos": [
                    (4, "Revisa cada valor. Deja vacío lo que no se midió."),
                    (5, "Revisa el «Resultado estimado»."),
                    (6, "Pulsa «Confirmar análisis»."),
                ]},
            ],
            "nota": "Importante: verifica los valores leídos del ticket antes de confirmar.",
            "mensajes": [
                ["Valores no numéricos", "«Revisa los valores marcados: deben ser números.»"],
                ["Ningún valor ingresado", "«Ingresa al menos un valor para confirmar el análisis.»"],
                ["Ticket ilegible", "«No se pudo leer el ticket» («Tomar otra foto» o «Ingresar manualmente»)."],
                ["Sin parámetros configurados", "«No hay parámetros de calidad activos configurados.»"],
            ],
            "pendiente": "Pantallas «Tomar foto del ticket», «Datos detectados», «Resultado estimado» y «Análisis confirmado».",
        },
        {
            "codigo": "MOB-CAL-005", "titulo": "Consultar el historial de análisis", "estado": COD,
            "frase": "Revisa los análisis realizados y sus parámetros.",
            "bloques": [{"pasos": [
                (1, "Pulsa «Historial» en la barra inferior."),
                (2, "Elige el período y el resultado."),
                (3, "Pulsa un análisis para ver sus parámetros."),
            ]}],
            "pendiente": "Pantalla «Historial» de análisis y su detalle.",
        },
        {
            "codigo": "MOB-CAL-006", "titulo": "Consultar proveedores", "estado": COD,
            "frase": "Revisa las advertencias y los últimos análisis de un proveedor.",
            "bloques": [{"pasos": [
                (1, "Abre Menú › «Proveedores»."),
                (2, "Escribe el nombre o DNI en el buscador."),
                (3, "Pulsa un proveedor para ver su detalle."),
            ]}],
            "pendiente": "Pantalla «Proveedores» del Analista de calidad y su detalle.",
        },
        {
            "codigo": "MOB-CAL-007", "titulo": "Publicar un comunicado", "estado": VER,
            "frase": "Publica un aviso para los proveedores desde Menú › «Comunicados» › «+ Nuevo comunicado».",
            "bloques": [
                {"figs": [dict(img="p38_x177", marcas=[
                    (1, ("caja", 36, 300, 760, 430)), (2, ("caja", 36, 480, 760, 730)), (3, ("caja", 36, 770, 876, 900)),
                    (4, ("fila", "GENERAL", 40)), (5, ("boton", 1))])],
                 "pasos": [
                     (1, "Escribe el «Título»."),
                     (2, "Escribe el «Contenido»."),
                     (3, "Escribe el «Tipo» (por ejemplo, AVISO)."),
                     (4, "Elige «GENERAL», «ZONA» o «INDIVIDUAL»."),
                     (5, "Pulsa «Publicar comunicado»."),
                 ]},
            ],
            "nota": "Necesitas conexión. Para «ZONA» o «INDIVIDUAL», pide los identificadores al administrador.",
            "mensajes": [
                ["Título o contenido vacío", "«Título y contenido son obligatorios»"],
                ["Error al publicar", "«No se pudo crear el comunicado»"],
            ],
        },
        {
            "codigo": "MOB-CAL-008", "titulo": "Ver notificaciones y Mi perfil", "estado": VER,
            "frase": "Lee tus avisos y revisa los datos de tu cuenta.",
            "bloques": [
                {"figs": [dict(img="p39_x180", marcas=[
                    (1, ("fila", "Procesando", 22)), (2, ("zona", "Aviso", "general", 26))]),
                          dict(img="p39_x179", marcas=[
                    (3, ("zona", "Usuario", "Estado", 30)), (4, ("fila", "Cerrar", 45))])],
                 "pasos": [
                     (1, "«Procesando OCR…»: se está leyendo un ticket."),
                     (2, "Pulsa una notificación para leerla."),
                     (3, "Revisa el usuario y el estado de tu cuenta."),
                     (4, "Pulsa «Cerrar sesión» para salir."),
                 ]},
            ],
        },
    ],
}

# ============================================================================ CAPÍTULO 6: PROVEEDOR
PROVEEDOR = {
    "titulo": "Proveedor",
    "intro": "Consulta tus entregas, resultados de calidad, pagos, compras y comunicados. Todas tus pantallas son de consulta.",
    "datos": [
        ("Barra inferior", "Inicio · Entregas · Pagos"),
        ("Asistencia", "no la necesitas."),
    ],
    "procedimientos": [
        {
            "codigo": "MOB-PRV-001", "titulo": "Mostrar tu QR y consultar la semana", "estado": VER,
            "frase": "Muestra tu código al acopiador y revisa el resumen de la semana.",
            "bloques": [
                {"figs": [dict(img="p43_x184", marcas=[
                    (1, ("zona", "cedigo", "Muestralo", 30)), (2, ("zona", "Esta semana", "Pago", 34))])],
                 "pasos": [
                     (1, "Muestra «Mi código QR» al acopiador al entregar la leche."),
                     (2, "Revisa litros, entregas y pago pendiente de la semana."),
                 ]},
            ],
            "nota": "Sin conexión se muestran los datos guardados. El QR se descarga la próxima vez que tengas conexión.",
        },
        {
            "codigo": "MOB-PRV-002", "titulo": "Usar el menú lateral", "estado": VER,
            "frase": "Pulsa ☰ para abrir tus secciones.",
            "bloques": [
                {"figs": [dict(img="p42_x188", marcas=[
                    (1, ("zona", "OPERACION", "calidad", 22)), (2, ("zona", "GESTION", "compras", 22)),
                    (3, ("zona", "COMUNICACION", "Notificaciones", 22)), (4, ("zona", "SISTEMA", "Sincronizacion", 22))])],
                 "pasos": [
                     (1, "«Operación»: «Inicio», «Mis entregas» y «Mi calidad»."),
                     (2, "«Gestión»: «Mis pagos» y «Mis compras»."),
                     (3, "«Comunicación»: «Comunicados» y «Notificaciones»."),
                     (4, "«Sistema»: «Mi perfil» y «Sincronización»."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-PRV-003", "titulo": "Consultar mis entregas", "estado": VER,
            "frase": "Revisa tus entregas de leche y su estado.",
            "bloques": [
                {"figs": [dict(img="p44_x191", marcas=[
                    (1, ("texto", "Semana#2", 22)), (2, ("texto", "Estado", 22)), (3, ("zona", "11", "Rechazada", 30))]),
                          dict(img="p45_x193", marcas=[
                    (4, ("zona", "Fecha", "Estado", 30)), (5, ("fila", "Ver", 45))])],
                 "pasos": [
                     (1, "Pulsa «Semana» y elige el período."),
                     (2, "Pulsa «Estado» para filtrar."),
                     (3, "Pulsa una entrega para ver su detalle."),
                     (4, "Revisa fecha, litros, zona, estado y calidad."),
                     (5, "Pulsa «Ver análisis» para ver el resultado."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-PRV-004", "titulo": "Consultar mis resultados de calidad", "estado": VER,
            "frase": "Revisa el análisis de laboratorio de cada entrega.",
            "bloques": [
                {"figs": [dict(img="p46_x195", marcas=[(1, ("zona", "76", "Aceptada", 34))]),
                          dict(img="p48_x200", marcas=[
                    (2, ("zona", "Resultado", "Zona", 30)), (3, ("zona", "Grasa", "pH#2", 30))])],
                 "pasos": [
                     (1, "Pulsa una entrega de la lista."),
                     (2, "Revisa resultado, fecha, litros y zona."),
                     (3, "Revisa los parámetros. «Fuera de rango» señala un valor fuera del límite."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-PRV-005", "titulo": "Consultar mis pagos", "estado": PAR,
            "frase": "Revisa cuánto tienes por cobrar y el detalle de cada pago semanal.",
            "bloques": [
                {"figs": [dict(img="p47_x197", marcas=[(1, ("fila", "cobrar", 30))])],
                 "pasos": [
                     (1, "Revisa el importe «por cobrar»."),
                 ]},
                {"pasos": [
                    (2, "Pulsa una semana para abrir «Detalle de pago»."),
                    (3, "Revisa litros, precio, calidad, descuentos y «Total a recibir»."),
                    (4, "Revisa el estado y la «Fecha de pago»."),
                ]},
            ],
            "pendiente": "«Detalle de pago» (liquidación); no había liquidaciones en los datos de prueba.",
        },
        {
            "codigo": "MOB-PRV-006", "titulo": "Consultar mis compras", "estado": PAR,
            "frase": "Revisa los productos que compraste a la planta.",
            "bloques": [
                {"figs": [dict(img="p48_x199", marcas=[(1, ("zona", "Queso", "unidad", 30))])],
                 "pasos": [
                     (1, "Pulsa una compra de la lista."),
                 ]},
                {"pasos": [
                    (2, "Revisa producto, cantidad, precio, total y forma de pago."),
                    (3, "Si se descontó de tu pago, pulsa «Ver el pago donde se descontó»."),
                ]},
            ],
            "pendiente": "«Detalle de compra» del Proveedor.",
        },
        {
            "codigo": "MOB-PRV-007", "titulo": "Leer comunicados", "estado": PAR,
            "frase": "Lee los avisos publicados para los proveedores.",
            "bloques": [
                {"pasos": [(1, "Pulsa un comunicado de la lista. «Nuevo» indica que no lo leíste.")]},
                {"figs": [dict(img="p49_x203", marcas=[(2, ("zona", "Buenos", "app", 26)), (3, ("fila", "Fecha del", 24))])],
                 "pasos": [
                     (2, "Lee el contenido del aviso."),
                     (3, "Revisa la fecha del evento, si la hay."),
                 ]},
            ],
            "pendiente": "Lista de «Comunicados» del Proveedor.",
        },
        {
            "codigo": "MOB-PRV-008", "titulo": "Ver notificaciones y Mi perfil", "estado": VER,
            "frase": "Lee tus avisos y revisa tus datos personales.",
            "bloques": [
                {"figs": [dict(img="p50_x205", marcas=[(1, ("zona", "Entrega", "analisis", 22))]),
                          dict(img="p50_x206", marcas=[(2, ("zona", "Nombre", "Telefono", 30))])],
                 "pasos": [
                     (1, "Pulsa una notificación para marcarla como leída."),
                     (2, "Revisa nombre, DNI, zona, estado y teléfono."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-PRV-009", "titulo": "Consultar la sincronización", "estado": COD,
            "frase": "Comprueba si los datos del teléfono están al día.",
            "bloques": [{"pasos": [
                (1, "Abre Menú › «Sincronización»."),
                (2, "Revisa el «Estado actual»: conexión y sincronización."),
                (3, "Revisa «Pendientes» y «Errores». Los errores se reintentan solos."),
            ]}],
            "pendiente": "Pantalla «Sincronización».",
        },
    ],
}

# ============================================================================ CAPÍTULO 7: ACOPIADOR
ACOPIADOR = {
    "titulo": "Acopiador",
    "intro": "Registra las entregas de leche de los proveedores de tu zona, con o sin conexión, y sigue tu recorrido del día.",
    "datos": [
        ("Barra inferior", "Inicio · Entregas · Proveedores"),
        ("Asistencia", "necesaria para registrar entregas y operar el recorrido."),
    ],
    "procedimientos": [
        {
            "codigo": "MOB-ACO-001", "titulo": "Consultar la pantalla Inicio", "estado": VER,
            "frase": "Revisa el acopio del día y empieza a registrar entregas.",
            "bloques": [
                {"figs": [dict(img="p51_x210", marcas=[
                    (1, ("zona", "Leche", "368", 40)), (2, ("boton", 1)), (3, ("zona", "Ultimas", "Luis", 50))])],
                 "pasos": [
                     (1, "Revisa «Leche registrada» y «Entregas realizadas»."),
                     (2, "Pulsa «Registrar entrega» para recibir leche."),
                     (3, "Revisa las últimas entregas y su estado."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-ACO-002", "titulo": "Registrar una entrega de leche", "estado": VER,
            "frase": "Identifica al proveedor, escribe los litros y guarda la entrega.",
            "bloques": [
                {"figs": [dict(img="p54_x218", marcas=[
                    (1, ("caja", 250, 640, 670, 1200)), (2, ("fila", "Ingresar", 50))]),
                          dict(img="p54_x217", marcas=[
                    (3, ("caja", 36, 1690, 876, 1815)), (4, ("caja", 455, 1830, 880, 1965))])],
                 "pasos": [
                     (1, "Enfoca el QR del proveedor dentro del recuadro."),
                     (2, "Si no puedes escanear, pulsa «Ingresar DNI»."),
                     (3, "Escribe el «Número de DNI»."),
                     (4, "Pulsa «Buscar proveedor»."),
                 ]},
                {"figs": [dict(img="p55_x221", marcas=[
                    (5, ("texto", "Cambiar", 28)), (6, ("zona", "Cantidad", "0", 30)), (7, ("caja", 36, 845, 876, 1040)), (8, ("boton", 1))]),
                          dict(img="p55_x220", marcas=[
                    (9, ("boton", 1)), (10, ("fila", "Ver historial", 45))])],
                 "pasos": [
                     (5, "Comprueba el proveedor. Pulsa «Cambiar» si no es correcto."),
                     (6, "Escribe la «Cantidad de leche»."),
                     (7, "Agrega una observación (opcional)."),
                     (8, "Pulsa «Guardar entrega»."),
                     (9, "Pulsa «Registrar otra entrega» para seguir."),
                     (10, "O pulsa «Ver historial»."),
                 ]},
            ],
            "nota": "Importante: verifica los litros antes de guardar.",
            "mensajes": [
                ["DNI incompleto", "«El DNI debe tener 8 dígitos.»"],
                ["Litros en cero", "«Ingresa una cantidad mayor a 0.»"],
                ["Proveedor distinto al de la parada del recorrido", "«<proveedor> no es el proveedor de esta parada.»"],
            ],
        },
        {
            "codigo": "MOB-ACO-003", "titulo": "Registrar entregas sin conexión", "estado": VER,
            "frase": "Sin Internet, registra igual: la entrega se envía sola después.",
            "bloques": [
                {"figs": [dict(img="p56_x220", marcas=[(1, ("texto", "Sincronizada", 14))]),
                          dict(img="p56_x223", marcas=[(2, ("texto", "Pendiente", 14))])],
                 "pasos": [
                     (1, "«Sincronizada»: la entrega llegó al servidor."),
                     (2, "«Pendiente de sincronización»: se enviará al volver la conexión."),
                 ]},
            ],
            "nota": "No repitas una entrega pendiente; el envío es automático.",
        },
        {
            "codigo": "MOB-ACO-004", "titulo": "Consultar y corregir entregas", "estado": PAR,
            "frase": "Busca tus entregas, revisa su estado y corrige las que no se enviaron.",
            "bloques": [
                {"figs": [dict(img="p57_x226", marcas=[
                    (1, ("fila", "Buscar", 45)), (2, ("fila", "Todas", 40)), (3, ("zona", "Sofia", "05:59", 24))]),
                          dict(img="p57_x225", marcas=[
                    (4, ("zona", "Estado de", "conexion", 26)), (5, ("zona", "Control", "servidor", 26))])],
                 "pasos": [
                     (1, "Escribe el proveedor o DNI en el buscador."),
                     (2, "Elige el período."),
                     (3, "Pulsa una entrega para abrir su detalle."),
                     (4, "Revisa el estado de registro."),
                     (5, "Revisa el control de calidad."),
                 ]},
                {"pasos": [
                    (6, "Si ves «Error de sincronización», pulsa «Corregir y reintentar»."),
                    (7, "Ajusta los litros u observación y pulsa «Guardar y reintentar»."),
                    (8, "«Descartar registro» borra una entrega que no llegó al servidor."),
                ]},
            ],
            "nota": "Solo puedes corregir dentro de los 10 minutos siguientes a la hora de acopio.",
            "mensajes": [["Corrección fuera de plazo", "«Pasaron más de 10 minutos desde la hora de acopio.»"]],
            "pendiente": "Pantalla «Corregir entrega» ante un error de sincronización.",
        },
        {
            "codigo": "MOB-ACO-005", "titulo": "Realizar el recorrido del día", "estado": COD,
            "frase": "Visita a los proveedores de tu ruta y registra cada parada.",
            "bloques": [{"pasos": [
                (1, "Abre Menú › «Recorrido»."),
                (2, "Pulsa «Iniciar recorrido» o «Continuar recorrido»."),
                (3, "Pulsa «Registrar» en cada proveedor y registra su entrega."),
                (4, "Si no entregó, pulsa «No entregó», elige el motivo y pulsa «Guardar»."),
                (5, "Pulsa «Finalizar recorrido» y confirma con «Finalizar»."),
            ]}],
            "nota": "Después de finalizar ya no puedes registrar acopios en ese recorrido.",
            "mensajes": [["Sin recorrido asignado", "«No tienes un recorrido asignado para hoy.»"]],
            "pendiente": "Pantallas «Recorrido de hoy», paradas, «No entregó» y «Finalizar recorrido».",
        },
        {
            "codigo": "MOB-ACO-006", "titulo": "Consultar mis proveedores", "estado": VER,
            "frase": "Busca un proveedor de tu zona y consulta sus entregas.",
            "bloques": [
                {"figs": [dict(img="p58_x229", marcas=[
                    (1, ("fila", "Buscar", 45)), (2, ("zona", "Juan Mamani", "Zona", 22))]),
                          dict(img="p58_x228", marcas=[
                    (3, ("boton", 1)), (4, ("fila", "historial", 45))])],
                 "pasos": [
                     (1, "Escribe el nombre o DNI en el buscador."),
                     (2, "Pulsa un proveedor para ver su detalle."),
                     (3, "Pulsa «Registrar entrega» para recibir su leche."),
                     (4, "Pulsa «Ver historial» para ver sus entregas."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-ACO-007", "titulo": "Consultar calidad y comunicados", "estado": VER,
            "frase": "Revisa qué entregas fueron muestreadas y lee los avisos.",
            "bloques": [
                {"figs": [dict(img="p59_x231", marcas=[
                    (1, ("fila", "Todas", 40)), (2, ("zona", "Carlos Cahuana", "89 L", 22))]),
                          dict(img="p59_x232", marcas=[
                    (3, ("fila", "Todos", 40)), (4, ("zona", "Aviso", "Hoy", 26))])],
                 "pasos": [
                     (1, "Filtra: «Todas», «En control», «Con resultado» o «Sin muestreo»."),
                     (2, "Pulsa una entrega para ver su calidad."),
                     (3, "Filtra: «Todos», «No leídos» o «Importantes»."),
                     (4, "Pulsa un comunicado para leerlo."),
                 ]},
            ],
        },
        {
            "codigo": "MOB-ACO-008", "titulo": "Consultar Mi QR y Mi perfil", "estado": VER,
            "frase": "Muestra tu QR de asistencia y revisa tu cuenta.",
            "bloques": [
                {"figs": [dict(img="p60_x236", marcas=[(1, ("caja", 115, 740, 797, 1390))]),
                          dict(img="p60_x235", marcas=[(2, ("zona", "Usuario", "Estado", 30)), (3, ("fila", "Cerrar", 45))])],
                 "pasos": [
                     (1, "Muestra el QR al administrador para tu asistencia."),
                     (2, "Revisa usuario, DNI, teléfono, zona y estado."),
                     (3, "Pulsa «Cerrar sesión» para salir."),
                 ]},
            ],
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
    ["Aparece «Asistencia pendiente» y no puedo operar.", "Muestra tu QR al administrador ({MOB-GEN-003}) y espera a que el aviso desaparezca."],
    ["«Asistencia no verificada para hoy» sin conexión.", "Conéctate a Internet después de registrar la asistencia; luego podrás operar sin conexión."],
    ["«Usuario o contraseña incorrectos».", "Revisa mayúsculas y minúsculas. Si persiste, pide al administrador restablecer la contraseña."],
    ["Una operación sigue como «Pendiente».", "Comprueba la conexión (punto verde). El envío es automático; no repitas la operación."],
    ["Un insumo no alcanza para producir.", "Registra la compra ({MOB-PLA-005} o {MOB-PLA-006}) y vuelve a «Nueva producción»."],
    ["No aparece «Descuento del pago semanal».", "Solo está disponible para clientes de tipo Proveedor y con conexión."],
    ["No se puede leer el QR del proveedor.", "Usa «Ingresar DNI»."],
    ["Quiero cambiar una receta o un precio.", "Solo el administrador puede hacerlo desde EcoLactWeb."],
]

LIMITACIONES = [
    "Durante la revisión solo se navegó por la aplicación: no se confirmaron ventas, compras, producciones, entregas, "
    "muestreos, análisis ni comunicados nuevos.",
    "Las capturas proceden de la aplicación en ejecución del 28/10/2026 y se contrastaron con el código fuente del 29/10/2026.",
    "Las capturas del Encargado de planta conservan las marcas numeradas de su edición original.",
    "En algunas capturas de Calidad se ve la barra flotante del teclado del emulador; no se retocó.",
    "La lectura automática (OCR) de boletas y tickets depende del servidor y de la foto; revisa siempre los valores.",
    "Para comunicados por «ZONA» o «INDIVIDUAL», la aplicación pide identificadores numéricos en lugar de una lista.",
]
