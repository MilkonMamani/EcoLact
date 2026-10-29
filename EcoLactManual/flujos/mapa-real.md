# Mapa real — Flujo Administrativo del ADMINISTRADOR en EcoLactWeb

> **Estado:** Basado en inspección directa de `routes/web.php` y controladores bajo el prefijo `/admin`.  
> **Fecha de inspección:** 2026-10-28  
> **Fuente:** Código observado — ninguna afirmación está basada en suposiciones.  
> **Verificación UI:** Pendiente de capturas reales.

---

## Mecanismo de autorización

| Mecanismo | Descripción |
|-----------|-------------|
| `middleware('auth')` | Requiere sesión activa |
| `middleware('active')` | Requiere cuenta activa (no suspendida) |
| `middleware('role:ADMINISTRADOR')` | Solo el rol ADMINISTRADOR accede al prefijo `/admin` |
| `Gate::before()` | En `AppServiceProvider`, el ADMINISTRADOR tiene acceso `true` a todas las Policies salvo `AnalisisCalidadPolicy::update` (análisis CONFIRMADO es inmutable incluso para el Admin) |

---

## Flujo E2E del Administrador

```
[Inicio de sesión]
     |
     v
[1. CONFIGURACIÓN INICIAL]
  ├── 1.1 Parámetros de calidad (crear / editar)
  ├── 1.2 Reglas de calidad (crear / editar)
  ├── 1.3 Configuración de pagos → precio por litro (proveedor)
  ├── 1.4 Insumos → crear ficha + presentaciones
  └── 1.5 Recetas → crear / versionar / activar

[2. ESTRUCTURA TERRITORIAL]
  ├── 2.1 Crear Zonas (con polígono GeoJSON y color)
  ├── 2.2 Crear Rutas dentro de cada zona
  └── 2.3 Buscar proveedores disponibles y agregarlos a la ruta (con orden de visita)

[3. REGISTRO DE PERSONAL]
  ├── 3.1 Registrar Acopiadores (con zona asignada)
  ├── 3.2 Registrar Proveedores (con credenciales de acceso)
  ├── 3.3 Registrar Analistas de calidad
  ├── 3.4 Registrar Encargados de planta
  ├── 3.5 Registrar Personal de ventas
  └── 3.6 Configurar sueldos → Configuración de pagos (pestaña Trabajadores)

[4. ASIGNACIÓN DE PROVEEDORES A ZONAS/RUTAS]
  ├── 4.1 Asignar proveedor a zona + ruta + punto de recolección (modal desde Zonas)
  ├── 4.2 Mover pin de proveedor en el mapa general
  └── 4.3 Reactivar proveedor inactivo (requiere ruta destino)

[5. APERTURA DE RECORRIDO OPERATIVO]
  └── 5.1 Abrir recorrido: asignar acopiador a ruta del día (desde panel Rutas)
         ↳ El acopiador opera el recorrido desde su propio panel /acopiador

[6. ACOPIO / ENTREGAS]
  ├── 6.1 Listar entregas (todas las zonas, filtros: zona/acopiador/estado/fecha)
  ├── 6.2 Ver detalle de entrega con modal
  ├── 6.3 Registrar entrega administrativa (sin restricción de asistencia)
  ├── 6.4 Editar entrega (auditado: motivo obligatorio)
  ├── 6.5 Corregir litros inline desde modal (auditado)
  └── 6.6 Anular entrega (con reverso de inventario si ya estaba incorporada)

[7. CALIDAD]
  ├── 7.1 Listar análisis (todas las zonas, filtros: zona/resultado/estado/analista)
  ├── 7.2 Ver análisis de una entrega específica
  ├── 7.3 Registrar análisis de calidad (valores de parámetros)
  │        ↳ OCR escanear ticket del analizador (propone valores, NO guarda)
  └── 7.4 Finalizar/confirmar análisis → estado CONFIRMADO (inmutable)
           ↳ El sistema calcula automáticamente: ACEPTADA / OBSERVADA / RECHAZADA

[8. PRODUCCIÓN]
  ├── 8.1 Ver lotes de producción (historial, filtros: estado/periodo)
  ├── 8.2 Nueva producción (modal): elegir productos + cantidades
  │        ↳ 8.2a Previsualizar requerimientos de insumos (sin descontar)
  ├── 8.3 Iniciar producción → descuenta insumos → lote EN_PROCESO
  └── 8.4 Finalizar lote → registra cantidades obtenidas → ingresa a stock

[9. INSUMOS Y COMPRAS]
  ├── 9.1 Ver inventario de insumos (con niveles: sin stock / bajo mínimo / OK)
  ├── 9.2 Crear ficha de insumo (stock 0)
  ├── 9.3 Agregar/editar presentaciones de compra
  ├── 9.4 Registrar compra de insumo (manual o desde OCR de boleta)
  │        ↳ Sube stock + registra movimiento en Caja
  └── 9.5 Ajuste de stock (merma / pérdida / corrección / inventario)

[10. PRODUCTOS TERMINADOS Y VENTAS]
  ├── 10.1 Ver productos terminados con precios por tipo de cliente
  ├── 10.2 Configurar/eliminar precios de productos (exclusivo ADMINISTRADOR)
  ├── 10.3 Registrar comprador
  ├── 10.4 Registrar venta → descuenta stock → registra movimiento en Caja
  └── 10.5 Imprimir boleta PDF de la venta

[11. CONTROL DE ASISTENCIA Y JORNADAS]
  ├── 11.1 Ver control de asistencia diaria del personal
  ├── 11.2 Ver jornadas de un trabajador (por mes)
  ├── 11.3 Registrar jornada manualmente
  └── 11.4 Corregir jornada existente

[12. CONFIGURACIÓN Y PAGOS]
  ├── 12.1 Configurar precio por litro de leche + descuento por calidad OBSERVADA
  ├── 12.2 Ver/editar historial de configuraciones de pago (vigencias)
  ├── 12.3 Generar pagos pendientes desde Configuración
  │
  ├── [Pago a Proveedores]
  │    ├── 12.4 Listar proveedores con saldo pendiente (por semana/zona)
  │    ├── 12.5 Previsualizar comprobantes de pago
  │    ├── 12.6 Confirmar impresión → marcar como PAGADO en efectivo
  │    └── 12.7 Imprimir comprobante individual de proveedor
  │
  ├── [Pago a Trabajadores]
  │    ├── 12.8  Listar sueldos del mes del personal (con panel lateral)
  │    ├── 12.9  Ajustar bonificación/descuento (solo si PENDIENTE)
  │    ├── 12.10 Confirmar pago de sueldo → PAGADO
  │    └── 12.11 Imprimir comprobante de trabajador
  │
  └── [Liquidaciones]
       └── 12.12 Imprimir hoja de liquidación del proveedor (sobre de pago)

[13. FINANZAS / CAJA] ← SOLO LECTURA
  ├── 13.1 Ver saldo actual de caja
  ├── 13.2 Ver historial de movimientos (filtros: tipo/categoría/fechas)
  └── 13.3 Ver detalle de un movimiento (ventas / pagos / compras insumos)

[14. COMUNICADOS]
  ├── 14.1 Listar comunicados (borradores y publicados)
  ├── 14.2 Crear comunicado (estado inicial: BORRADOR)
  ├── 14.3 Editar comunicado (solo mientras está en BORRADOR)
  └── 14.4 Publicar comunicado → estado PUBLICADO (destinatarios reciben notificación)

[15. NOTIFICACIONES]
  ├── 15.1 Ver lista de notificaciones propias
  └── 15.2 Marcar notificación como leída

[16. MI PERFIL]
  └── 16.1 Ver datos propios del administrador autenticado
```

---

## Módulos y su pantalla de entrada

| Módulo | URL de entrada | Ruta Laravel |
|--------|---------------|--------------|
| Dashboard | `/admin/dashboard` | `admin.dashboard` |
| Zonas | `/admin/zonas` | `admin.zonas.index` |
| Rutas | Panel de zona | `admin.rutas.show` |
| Proveedores | `/admin/proveedores` | `admin.proveedores.index` |
| Acopiadores | `/admin/acopiadores` | `admin.acopiadores.index` |
| Nuevo trabajador | `/admin/trabajadores/nuevo` | `admin.trabajadores.create` |
| Encargados de planta | `/admin/encargados-planta` | `admin.encargados-planta.index` |
| Personal de ventas | `/admin/personal-ventas` | `admin.personal-ventas.index` |
| Analistas de calidad | `/admin/analistas-calidad` | `admin.analistas-calidad.index` |
| Entregas | `/admin/entregas` | `admin.entregas.index` |
| Análisis de calidad | `/admin/analisis-calidad` | `admin.analisis-calidad.index` |
| Parámetros de calidad | `/admin/parametros-calidad` | `admin.parametros-calidad.index` |
| Reglas de calidad | `/admin/reglas-calidad` | `admin.reglas-calidad.index` |
| Insumos | `/admin/insumos` | `admin.insumos.index` |
| Producción | `/admin/produccion` | `admin.produccion.index` |
| Recetas | `/admin/recetas` | `admin.recetas.index` |
| Productos terminados | `/admin/productos` | `admin.productos.index` |
| Stock general | `/admin/stock` | `admin.stock.index` |
| Compradores | `/admin/compradores` | `admin.compradores.index` |
| Ventas | `/admin/ventas` | `admin.ventas.index` |
| Config. de pagos | `/admin/configuracion-pagos` | `admin.configuracion-pagos.index` |
| Pagos generales | `/admin/pagos` | `admin.pagos.index` |
| Pagos a proveedores | `/admin/pagos/proveedores` | `admin.pagos.proveedores.index` |
| Pagos a trabajadores | `/admin/pagos/trabajadores` | `admin.pagos.trabajadores.index` |
| Liquidaciones | `/admin/liquidaciones/{id}/imprimir` | `admin.liquidaciones.imprimir` |
| Caja | `/admin/caja` | `admin.caja.index` |
| Comunicados | `/admin/comunicados` | `admin.comunicados.index` |
| Notificaciones | `/notificaciones` | `notificaciones.index` |
| Mi perfil | `/admin/mi-perfil` | `admin.administrador.show` |
| Control de asistencia | `/admin/asistencia` | `admin.asistencia.index` |
| Jornadas de trabajador | `/admin/trabajadores/{id}/jornadas` | `admin.trabajadores.jornadas.index` |

---

## Dependencias entre módulos

```
Zonas ──────────────┐
                    ├──► Rutas ──────────────► Recorridos (operados por ACOPIADOR)
Proveedores ────────┘         └─► Detalles de ruta (orden de visita)
     │
     └──► Entregas ──────────────► Análisis de calidad ──► Liquidaciones
               │                                              │
               └──────────────────────────────────────────► Configuración de pagos
                                                              │
                                                             ▼
Insumos ────────► Compras ──────────────────────────────► CAJA ◄─── Ventas
     └──────────► Producción ─────► Stock de productos ──► Ventas
```

---

## Operaciones exclusivas del ADMINISTRADOR (no disponibles en otros roles)

| Operación | Razón de exclusividad |
|-----------|----------------------|
| Gestión de Zonas (CRUD completo) | `ZonaPolicy` solo admite ADMINISTRADOR |
| Gestión de Rutas (CRUD + asignación de acopiador) | `RutaPolicy` solo ADMINISTRADOR |
| Asignación de proveedor a zona/ruta | `Gate::before` ADMINISTRADOR |
| Registro de usuarios de cualquier tipo | Cada `*Policy::create` solo ADMINISTRADOR |
| Desactivar/Reactivar cualquier usuario | Policies respectivas |
| Configuración de precios de productos | `ProductoPrecioPolicy` exclusiva ADMINISTRADOR |
| Configuración de pagos y sueldos | `ConfiguracionPagoPolicy` exclusiva |
| Pagos a proveedores y trabajadores | `ObligacionPagoPolicy::pay` |
| Caja (lectura global) | `MovimientoCajaPolicy` |
| Liquidaciones (imprimir) | `LiquidacionPolicy` |
| Análisis de calidad (registrar + finalizar) | Compartido con CALIDAD; ambos pueden |
| Parámetros y reglas de calidad | Compartido con CALIDAD (lectura); escritura solo ADMIN |

---

## Operaciones que el ADMINISTRADOR NO ejecuta directamente

| Operación | Rol responsable |
|-----------|----------------|
| Iniciar/finalizar recorrido del día | ACOPIADOR |
| Registrar acopio desde recorrido (parada a parada) | ACOPIADOR |
| Registrar entrega con QR/DNI en campo | ACOPIADOR |
| Registrar muestreo de calidad | CALIDAD (ANALISTA) |
| Gestionar producción e insumos en planta | ENCARGADO_PLANTA |
| Registrar ventas en el punto de venta | VENTAS |
| Ver historial de pagos propios | PROVEEDOR (su panel) |

> **Nota:** El ADMINISTRADOR sí puede registrar entregas y análisis desde su panel  
> (vía `EntregaPolicy::create` con `Gate::before` y `AnalisisCalidadPolicy::create`),  
> pero esto es una acción administrativa de corrección, no el flujo operativo normal.
