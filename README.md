# EcoLact

EcoLact:🐄✨ Tecnología que impulsa la calidad láctea. 🥛


## Problema que resuelve
El proceso de acopio de leche puede involucrar el registro manual de información relacionada con los proveedores, las cantidades entregadas y los resultados del análisis de calidad. Esto puede dificultar el seguimiento histórico de las entregas, la identificación de alteraciones y la generación de información para la toma de decisiones.

El proyecto propone una aplicación móvil que permita centralizar y organizar esta información, facilitando el registro de entregas, el seguimiento de los proveedores y el control de calidad de la leche recibida.


## Público objetivo
La aplicación estará dirigida principalmente a:

    •Personal de acopio: registra las entregas de leche y consulta información de los   proveedores.

    •Responsable de calidad: registra los análisis y determina el estado de la leche recibida. 

    •Administrador: gestiona proveedores, consulta información y supervisa el proceso. 

    •Proveedores/productores: consultan sus entregas, resultados de calidad y ver las notificaciones. 


## Funcionalidades previstas
    •	Gestión de proveedores
    •	Gestión de acopiador
    •	Registro de entregas
    •	Control de calidad
    •	Seguimiento de producción
    •	Reportes
    •	Identificación QR

## Entidad principal del CRUD
Proveedor
    La entidad principal del CRUD será Proveedor, debido a que representa a las personas que realizan las entregas de leche al centro de acopio.
    Atributos tentativos

    | Atributo             | Descripción                    |
    |----------------------|--------------------------------|
    | idProveedor          | Identificador único            |
    | codigoProveedor      | Código asignado al proveedor   |
    | nombreCompleto       | Nombre completo                |
    | dni                  | Documento de identidad         |
    | telefono             | Número de teléfono             |
    | comunidad            | Comunidad o localidad          |
    | direccion            | Dirección del proveedor        |
    | estado               | Activo / Inactivo              |
    | fechaIncorporacion   | Fecha de incorporación         |

El proveedor podrá estar relacionado con sus respectivas entregas de leche, permitiendo consultar su historial y realizar seguimiento de su producción.

## Capacidad nativa prevista
La aplicación incorporará lectura de códigos QR mediante la cámara del dispositivo para identificar rápidamente a los proveedores durante el registro de las entregas.
Ejemplo:
Escaneo QR
    → Proveedor: Juan Pérez
    → Código: PRV-001
    → Última entrega: 82 L
El personal de acopio podrá completar la cantidad de litros entregados y registrar la nueva entrega.

## Equipo : 
| Integrante                | Código    | Rol semana 1       |
|---------------------------|-----------|--------------------|
| Huanca Cruz Diana Vanessa | 202413543 | Coordinación       |
| Mamani Ccama Milkon Eddy  | 202411735 | Lógica y datos     |
| Perez Foraquita Yanil     | 202411734 | UI                 |
| Perez Foraquita Yanil     | 202411734 | QA y documentación |

## Tecnologías
Kotlin Multiplatform · Compose Multiplatform · targets Android y Desktop
(iOS preparado: requiere macOS para compilar)
