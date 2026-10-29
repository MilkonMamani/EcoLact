---
name: descubrir-flujo-ecolact
description: Inspecciona y traza los procesos reales de EcoLact, roles, rutas, dependencias, estados y puntos de inicio/fin. Úsala antes de documentar un proceso.
---

# Descubrir flujo de EcoLact
1. Inspeccionar `routes/*.php`, `php artisan route:list` (sin modificar DB), controladores, FormRequests, vistas Blade, servicios/use cases, middleware, policies y menús; en móvil verificar módulos existentes, vistas y repositorios de sincronización.
2. Crear inventario `ID | rol | módulo | disparador | pantalla | entradas | acción | salida | estados | permisos | validación | fuente de evidencia | estado`.
3. Levantar **dos** modelos: diagrama de recorrido del usuario (quién pulsa qué), y diagrama de datos/negocio (qué registro causa qué dependencia). No confundir navegación con dependencia transaccional.
4. Establecer flujo E2E con disparadores, decisiones y excepciones, partiendo de `flujos/mapa_objetivo.json`: configuración -> zonas -> acopiador/proveedor -> entrega -> calidad -> valorización -> liquidación -> pago -> consulta. Orden definitivo solo después de inspección.
5. Confirmar el estado de cada componente: implementado/verificado, implementado/no probado, por desarrollar. Separar flujo de caja de la liquidación si no hay integración verificada.
6. Entregar `inventario.csv`, `mapa-real.md`, `matriz-trazabilidad.csv`, `lagunas.md`, y cada operación en JSON con evidencias trazables. No inferir a partir del nombre de una migración que la función está disponible.
