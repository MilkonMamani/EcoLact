# Inventario de funcionalidades — EcoLact Móvil

> 29/10/2026. Estado: A = comprobado visualmente; B = solo código; C = parcial. «Sin conexión» según el código (`*Sync*.kt`, `LecturaPendiente`, `AsistenciaGate`).

| Procedimiento | Rol | Pantalla(s) | Endpoint | Sin conexión | Estado | Captura(s) |
|---|---|---|---|---|---|---|
| MOB-GEN-001 Iniciar sesión | Todos | Ecolácteos Huata | `POST login` | No | A | p52_x213 |
| MOB-GEN-002 Estructura | Todos | Inicio, menú | — | Sí | A | p53_x210, p53_x215 |
| MOB-GEN-003 Asistencia | Planta, Ventas, Calidad, Acopiador | Aviso, Mi QR / Asistencia | `GET me/asistencia` | Solo si se confirmó hoy | A/B | p08_x95, p08_x94 |
| MOB-GEN-004 Sin conexión | Todos | Indicador, «Pendiente» | cola `SyncManager` | Sí | C | p56_x223 |
| MOB-GEN-005 Notificaciones y salir | Todos | Notificaciones, Mi perfil | `GET notificaciones`, `PATCH …/leida`, `POST logout` | Lectura sí | A | p50_x205, p60_x235 |
| MOB-PLA-001 Inicio | Planta | Inicio | `planta/dashboard` | Datos guardados | A | p06_x90 |
| MOB-PLA-002 Menú | Planta | Menú lateral | — | Sí | A | p07_x92 |
| MOB-PLA-003 Insumos | Planta | Inventario › Insumos, Detalle de insumo | `planta/insumos` | Datos guardados | A | p09_x97, p09_x98 |
| MOB-PLA-004 Compras (lista) | Planta | Compras de insumos | `planta/compras-insumo` | Datos guardados | A | p10_x101 |
| MOB-PLA-005 Compra por boleta | Planta | Tomar foto de la boleta, Datos detectados, Compra registrada | `compras-insumo/boleta`, `compras-insumo` | Foto sí; OCR no | C | p10_x100 |
| MOB-PLA-006 Compra manual | Planta | Registrar compra | `compras-insumo` | Sí | A | p11_x103 |
| MOB-PLA-007 Nueva producción | Planta | Nueva producción | `produccion/requerimientos`, `produccion` | Sí (revalida al sincronizar) | A | p12_x105 |
| MOB-PLA-008 Finalizar producción | Planta | Detalle de lote | `produccion/{id}/finalizar` | Sí | A | p13_x107 |
| MOB-PLA-009 Producciones | Planta | Producción, Detalle de lote | `planta/produccion` | Datos guardados | A | p14_x110, p14_x109 |
| MOB-PLA-010 Productos | Planta | Productos, Detalle de producto | `planta/productos` | Datos guardados | A | p15_x112, p15_x113 |
| MOB-PLA-011 Recetas | Planta | Recetas, Detalle de receta | `planta/recetas` | Datos guardados | A | p16_x116, p16_x115 |
| MOB-PLA-012 Mi perfil | Planta | Mi perfil | `planta/perfil` | Datos guardados | A | p17_x118 |
| MOB-VEN-001 Inicio | Ventas | Inicio | `ventas/movil` | Datos guardados | A | p19_x125 |
| MOB-VEN-002 Productos | Ventas | Productos | `ventas/catalogo` | Datos guardados | A | p22_x137 |
| MOB-VEN-003 Compradores | Ventas | Compradores, Nuevo comprador | `ventas/compradores` | Registro no | C | p23_x139 |
| MOB-VEN-004 Registrar venta | Ventas | Nueva venta, Seleccionar cliente, Resumen, Venta | `ventas/cotizar`, `ventas/ventas` | Contado sí; descuento no | C | p24_x141, p25_x143, p26_x145 |
| MOB-VEN-005 Historial | Ventas | Historial, Detalle de venta | `ventas/movil` | Datos guardados | A | p27_x147 |
| MOB-VEN-006 Boleta | Ventas | Boleta de venta | `ventas/ventas/{id}/boleta.pdf` | No | B | — |
| MOB-VEN-007 Perfil y QR | Ventas | Mi perfil, Mi QR | `ventas/mi-asistencia` | Datos guardados | A | p28_x149, p29_x151 |
| MOB-VEN-008 Comunicados | Ventas | Comunicados | `ventas/comunicados` | Datos guardados | B | — |
| MOB-CAL-001 Inicio y pendientes | Calidad | Inicio, Muestras pendientes | `calidad/dashboard`, `calidad/muestreos` | Datos guardados | A | p30_x156 |
| MOB-CAL-002 Muestreo aleatorio/manual | Calidad | Muestreo | `muestreos/elegibles`, `muestreos/aleatorio`, `muestreos` | Manual sí; aleatorio no | B | — |
| MOB-CAL-003 Muestreo directo | Calidad | Muestreo directo | `calidad/entregas` | Sí | A | p31–p36 |
| MOB-CAL-004 Análisis | Calidad | Detalle de entrega, Tomar foto del ticket, Revisar datos | `analisis-calidad/escanear-ticket`, `analisis-calidad` | Manual sí; OCR no | C | p37_x175 |
| MOB-CAL-005 Historial | Calidad | Historial | `calidad/analisis-calidad` | Datos guardados | B | — |
| MOB-CAL-006 Proveedores | Calidad | Proveedores, Proveedor | `calidad/proveedores` | Datos guardados | B | — |
| MOB-CAL-007 Comunicado | Calidad | Nuevo comunicado | `POST calidad/comunicados` | No | A | p38_x177 |
| MOB-CAL-008 Notificaciones y perfil | Calidad | Notificaciones, Mi perfil | `notificaciones` | Lectura sí | A | p39_x180, p39_x179 |
| MOB-PRV-001 Inicio y QR | Proveedor | Inicio | `proveedor/dashboard`, `proveedor/qr` | Datos guardados | A | p43_x184 |
| MOB-PRV-002 Menú | Proveedor | Menú lateral | — | Sí | A | p42_x188 |
| MOB-PRV-003 Entregas | Proveedor | Mis entregas, Detalle de entrega | `proveedor/entregas` | Datos guardados | A | p44_x191, p45_x193 |
| MOB-PRV-004 Mi calidad | Proveedor | Mi calidad, Análisis de calidad | `proveedor/analisis` | Datos guardados | A | p46_x195, p48_x200 |
| MOB-PRV-005 Pagos y liquidaciones | Proveedor | Mis pagos, Detalle de pago | `proveedor/liquidaciones` | Datos guardados | C | p47_x197 |
| MOB-PRV-006 Compras | Proveedor | Mis compras, Detalle de compra | `proveedor/compras` | Datos guardados | C | p48_x199 |
| MOB-PRV-007 Comunicados | Proveedor | Comunicados, Comunicado | `proveedor/comunicados` | Datos guardados | C | p49_x203 |
| MOB-PRV-008 Notificaciones y perfil | Proveedor | Notificaciones, Mi perfil | `notificaciones`, `proveedor/perfil` | Sí | A | p50_x205, p50_x206 |
| MOB-PRV-009 Sincronización | Proveedor | Sincronización | — | Sí | B | — |
| MOB-ACO-001 Inicio | Acopiador | Inicio | `acopiador/dashboard` | Datos guardados | A | p51_x210 |
| MOB-ACO-002 Registrar entrega | Acopiador | Registrar entrega | `POST acopiador/entregas` | Sí | A | p54, p55 |
| MOB-ACO-003 Sin conexión | Acopiador | Entrega guardada | cola local | Sí | A | p56_x220, p56_x223 |
| MOB-ACO-004 Historial y corrección | Acopiador | Entregas, Detalle de entrega | `acopiador/entregas` | Sí | C | p57_x226, p57_x225 |
| MOB-ACO-005 Recorrido | Acopiador | Recorrido, Paradas | `acopiador/recorridos/*` | Paradas sí | B | — |
| MOB-ACO-006 Proveedores | Acopiador | Mis proveedores, Detalle | `acopiador/proveedores` | Datos guardados | A | p58_x229, p58_x228 |
| MOB-ACO-007 Calidad y comunicados | Acopiador | Seguimiento de calidad, Comunicados | `seguimiento-calidad`, `comunicados` | Datos guardados | A | p59_x231, p59_x232 |
| MOB-ACO-008 QR y perfil | Acopiador | Mi QR / Asistencia, Mi perfil | `acopiador/mi-asistencia` | Datos guardados | A | p60_x236, p60_x235 |

**Totales:** 50 procedimientos — 34 verificados en interfaz, 9 parcialmente, 7 solo en código.

## Exclusivas de EcoLactWeb (E)
Administración de zonas, rutas, personal, proveedores, parámetros de calidad, recetas, precios, presentaciones, liquidaciones, pagos, caja y registro de asistencia por escaneo. No se documentan en este manual.
