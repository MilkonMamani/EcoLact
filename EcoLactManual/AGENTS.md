# Instrucciones permanentes para Antigravity: Manual EcoLact

Ámbito del proyecto: esta carpeta EcoLactManual es INDEPENDIENTE del proyecto de aplicación. Todas las creaciones o ediciones deben quedar aquí. Se permite consultar EcoLactWeb y EcoLactMobile como orígenes externos cuando Antigravity tenga acceso, pero no modificarlos ni ejecutar acciones peligrosas.

Objetivo: producir documentación de uso fiable para EcoLact (web Laravel y app móvil KMP) desde configuración/alta de usuarios hasta liquidaciones y pagos.

## Prioridades obligatorias
1. NO inventar rutas, interfaces, tablas, estados, roles, mensajes, cálculos ni permisos. Separar "observado en código", "verificado en interfaz", "esperado" y "pendiente".
2. Antes de redactar, levantar un catálogo de módulos, roles, flujos, validaciones, decisiones y dependencias. Verificar con rutas/controladores/FormRequests/Blade y pantallas reales; el código no reemplaza la verificación funcional.
3. Construir trazabilidad: cada paso -> rol -> precondición -> acción -> evidencia antes/después -> resultado -> excepción -> estado de verificación. ID reproducible `ROL-MODULO-OPERACION-NNN`.
4. No alterar lógica Laravel/KMP ni migraciones. Nunca ejecutar `migrate:fresh`, `db:wipe`, `truncate`, eliminación masiva, pagos reales ni sincronizaciones destructivas. Si una acción cambia datos, utilizar entorno de prueba y datos sintéticos, y pedir autorización antes.
5. No capturar contraseñas, claves API, números de cuenta, DNI identificables ni información personal. Ocultar o utilizar datos ficticios; revisar capturas antes de publicar.
6. Documentar un estado de UI por cada acción distinguible. Captura antes de clicar, al abrir modal, tras completar datos y después de confirmar. No sustituir la evidencia por una imagen inventada.
7. Para cada anotación, rectángulo exacto, flecha corta, número legible; resaltar solo 1 objeto/acción por imagen, conservar original sin modificaciones.
8. Redactar instrucciones cortas en segunda persona: verbo imperativo + elemento exacto + resultado comprobado, sin explicaciones excesivas.
9. Entrega: `.docx` editable y `.pdf`, con portada, alcance, prerrequisitos, matriz de flujo, procedimientos numerados, imágenes anotadas, mensajes de validación y última comprobación. Si falta evidencia, poner "Pendiente de comprobar".
10. Mantener ledger/registro de evidencias; reutilizar imágenes y componentes, nunca copiar credenciales dentro del repositorio.

## Forma de trabajo
- Etapa A: `descubrir-flujo-ecolact` para identificar el proceso y crear inventario.
- Etapa B: `capturar-pantallas` para evidencia con navegador o capturas manuales; para móvil buscar automatización/emulador antes de afirmar compatibilidad.
- Etapa C: `anotar-capturas` para anotaciones.
- Etapa D: `redactar-procedimientos`.
- Etapa E: `validar-evidencias` (puertas de calidad).
- Etapa F: `generar-manual-word-pdf`.

Primero elaborar un prototipo cerrado de Zonas > Nueva zona y comprobar un registro exitoso en un entorno de prueba, sin cambiar código. Luego extender desde el alta del acopiador/proveedor hasta el pago, sin asumir que todos los módulos existen o funcionan.

## ALCANCE DEFINITIVO: MANUAL WEB DEL ADMINISTRADOR

Esta sección prevalece sobre cualquier referencia anterior a la documentación móvil o de otros roles.

PROYECTO FUENTE:
EcoLactWeb (Laravel, Blade, PHP y MySQL).

PROYECTO DE SALIDA:
EcoLactManual.

ÚNICO ROL:
Administrador.

OBJETIVO:
Documentar todas las funciones reales a las que tiene acceso el Administrador, desde la configuración inicial y los registros hasta las liquidaciones y pagos.

REGLAS:
1. No documentar EcoLactMobile.
2. No documentar sesiones ni interfaces propias de Proveedor o Acopiador.
3. Sí documentar el registro y administración de proveedores, acopiadores y demás entidades desde el panel Administrador.
4. Inspeccionar únicamente rutas, controladores, vistas, permisos y operaciones del Administrador de EcoLactWeb.
5. Descubrir todo el flujo funcional real antes de establecer su orden definitivo.
6. No inventar botones, formularios, funciones ni pantallas.
7. Documentar todas las acciones significativas con capturas reales y anotaciones precisas.
8. Usar una imagen anotada por acción.
9. No modificar archivos fuente de EcoLactWeb.
10. No ejecutar operaciones que alteren datos reales.
11. Guardar todos los Word y PDF dentro de EcoLactManual/salida.
12. El producto final será un único manual profesional del Administrador, con capítulos y procedimientos separados.
13. Registrar claramente qué funcionalidades están verificadas y cuáles están pendientes.

El archivo flujos/mapa_objetivo.json es orientativo y debe adaptarse al alcance exclusivo del Administrador.

