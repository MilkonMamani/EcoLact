Implementación móvil del Personal de ventas — EcoLact

Se revisó el flujo Web existente: PantallaVentas, controladores Web/API, casos de uso, consultas y políticas. Se conserva una venta por producto y RegistrarVentaProducto como responsable de precio, stock y entrada de Caja. No se añadió carrito ni un proceso financiero independiente.

1. **Archivos nuevos.** En `shared/src/commonMain/kotlin/pe/edu/upeu/ecolactmobile/feature/ventas/`: `VentasApp.kt`, `VentasRoutes.kt`, `VentasModels.kt`, `VentasApi.kt`, `VentasRepository.kt`, `VentasViewModel.kt` y `screens/VentasScreens.kt`. En androidMain: `feature/ventas/VentasPreviews.kt`. Tests: `commonTest/.../feature/ventas/VentasModelsTest.kt` y `androidHostTest/.../feature/ventas/VentasSyncTest.kt`. Backend: `app/Http/Controllers/Api/Ventas/MovilController.php` y `tests/Feature/Http/Api/VentasMovilApiTest.php`.

2. **Archivos modificados.** Mobile: `App.kt`, `di/AppContainer.kt`, `core/sync/SyncModels.kt`, `core/sync/SyncManager.kt`. Web: `routes/api.php`, `Application/Ventas/RegistrarVentaProducto.php`, `Application/Ventas/VentasFiltro.php`, `Application/PersonalVentas/ObtenerDashboardVentas.php`, `Http/Requests/Ventas/StoreVentaProductoRequest.php`, `Http/Controllers/Api/Ventas/VentaController.php`, `Http/Support/PantallaVentas.php`, `Http/Middleware/IdempotenciaApi.php`, `Infrastructure/Persistence/Queries/EloquentVentasListadoQuery.php` y `Policies/VentaProductoPolicy.php`. Pint también normalizó formato de archivos que ya estaban modificados en el repositorio; no se descartaron esos cambios previos.

3. **VentasApp.** `Role.VENTAS` abre el módulo. Administrador sigue fuera del móvil. Se mantiene Compose → ScreenModel/ViewModel → Repository → Ktor + SQLite, como los otros roles.

4. **Navegación.** Bottom: Inicio / Vender / Historial. Drawer: Operación, Gestión, Comunicación y Sistema, con las opciones solicitadas y cerrar sesión. Se reutilizan top bar, avatar, iconos, punto de conexión y navegación del proyecto. El indicador no tiene texto permanente ni botón de sincronización.

5. **Inicio.** Tres KPIs de ventas propias aceptadas del día: cantidad de ventas, efectivo cobrado y unidades vendidas. Acciones Nueva venta, Ver historial y Mi QR. Ventas recientes con estado y acceso al detalle. Los descuentos semanales no suman al efectivo cobrado.

6. **Nueva venta.** Catálogo con búsqueda, presentación, stock conocido y mínimo mayorista. Una venta contiene un producto. El formulario conserva cantidad y pago al navegar; cambiar cualquiera invalida la cotización.

7. **Clientes.** Público general sin registro ni documento persistido ficticio; comprador por nombre/documento; proveedor activo por nombre/DNI. El documento XXXXXXXX solo se representa en la boleta del público.

8. **Precios.** Laravel usa TarifasProducto::aplicable. Online se solicita cotización. Offline el repositorio interpreta rangos resueltos por Laravel y guardados en SQLite; no hay selección de tarifa ni regla mayorista en Compose/ViewModel. RegistrarVentaProducto vuelve a calcular y rechaza un precio mostrado que haya cambiado. El catálogo también permite consultar productos activos sin precio, pero no agregarlos.

9. **Efectivo.** Público y comprador usan automáticamente CONTADO, mostrado como Efectivo. No se añadieron Yape, Plin, tarjetas, crédito ni pago mixto.

10. **Proveedor/descuento semanal.** Efectivo o DESCUENTO_PLANILLA. El servidor comprueba saldo disponible. El descuento no crea entrada de Caja ni se ofrece como operación offline.

11. **Historial.** Filas completas pulsables, columnas ajustadas al ancho, búsqueda y filtros Todas / Hoy / Esta semana / Este mes. Combina datos remotos con pendientes y errores locales. No usa desplazamiento horizontal.

12. **Detalle.** Código real devuelto por Laravel, fecha/hora de Perú, cliente/documento, producto, cantidad, tarifa, precio, importe, pago, vendedor y estado. También muestra motivos de rechazo. El Web usa códigos con cinco dígitos; se conserva ese código del servidor, aunque los ejemplos visuales del pedido usaban cuatro.

13. **Boleta SIN IGV.** Pantalla blanca con EcoLact, código, fecha/hora, datos del cliente, tabla alineada, TOTAL y pago. No contiene impuestos, datos fiscales, QR, observaciones ni datos de EcoLact inventados. En descuento se muestra únicamente el aviso semanal solicitado. Se abre para ventas aceptadas. No se incorporó compartir/imprimir: no se encontró un flujo existente de exportación de esta boleta móvil.

14. **Compradores.** Consulta y búsqueda; alta online con nombre/razón social, documento y teléfono. No se pide Público/Mayorista ni un tipo de documento que el contrato actual no almacena.

15. **Productos.** Solo consulta de nombre/presentación, stock, precios vigentes y mínimo mayorista. Sin configuración ni movimientos manuales.

16. **Mi QR.** Reutiliza el componente de trabajador con identidad real, DNI, token QR guardado y texto para mostrarlo al administrador. También se integraron perfil, comunicados y notificaciones del usuario.

17. **Asistencia.** Se reutiliza AsistenciaGate; permite consultas sin jornada y bloquea ventas/alta de comprador. El repositorio vuelve a comprobar antes de guardar. Laravel valida la jornada de fecha_hora_evento; el trabajador no registra su propia asistencia.

18. **Offline.** Efectivo permitido con asistencia confirmada para el día y datos/rangos guardados suficientes. Se reserva stock conocido de otras ventas pendientes del usuario. Descuento semanal y alta de comprador requieren conexión. Una venta pendiente no se presenta como completada.

19. **SQLite.** Se reutiliza CacheRepository/CacheEntry para catálogo, precios, compradores, proveedores, historial, perfil/QR y comunicados, con claves por usuario. No se creó una base de datos paralela ni se alteraron las tablas de otros roles.

20. **sync_queue.** Nueva operación VENTAS_REGISTRAR con usuario propietario, request y fotografía comercial. El motor existente envía al reconectar; el ViewModel actualiza automáticamente mientras el módulo está activo. Se recuperan envíos interrumpidos por cierre del proceso. Operaciones de otra cuenta se omiten sin consumir reintentos.

21. **Idempotencia.** localId estable enviado como Idempotency-Key. La respuesta y las escrituras del backend se confirman en la misma transacción. La reconciliación usa server_id/localId, incluso después de recargar el historial; evita duplicación también durante el breve intervalo de confirmación local.

22. **Fecha real.** fecha_hora_evento se captura al confirmar y no cambia en reintentos. Se valida su rango/zona y se conserva en venta, movimiento de producto y entrada de Caja generada por Laravel. La UI usa America/Lima.

23. **Conflictos de stock.** Laravel conserva su bloqueo/validación transaccional. Un rechazo pasa a NEEDS_REVIEW, no a éxito ni a reintentos automáticos infinitos. El historial muestra el mensaje específico de stock cambiado. También se conservan rechazos por precio o asistencia.

24. **Caja y permisos.** No hay pantallas ni repositorio de Caja en el módulo. Caja, pagos y configuración de precios devuelven 403 al vendedor. La consulta móvil, listado API/Web, dashboard del vendedor y política de detalle se acotaron a sus ventas. El administrador conserva acceso global.

25. **Tests.** `:shared:testAndroidHostTest`: 82 tests aprobados, incluidos 10 nuevos de Ventas (precio por rangos, documentos de boleta, asistencia offline, reserva de stock, rechazo, usuario propietario, clave estable, fecha y reconciliación). Regresión Laravel seleccionada: 85 tests / 310 aserciones aprobados, incluidos 17 nuevos de API Ventas. Suite completa `php artisan test --compact`: 1.784 tests, 1.763 aprobados, 4 fallos, 16 errores y 1 omitido. No está completamente verde. Los cuatro fallos corresponden a vistas Acopio, Dashboard, Login y PagoSemanalTodosYPagados. Los errores incluyen fecha de pago en EntregaAlimentaLeche, contrato de ProductosListadoData, constructor de EloquentZonasListadoQuery y unicidad en ProveedorPagosModal. No se modificaron estos comportamientos ajenos a Ventas para forzar el resultado. Se ejecutó `php artisan optimize:clear` y Pint. No se ejecutó `migrate:fresh` manualmente.

26. **Build y revisión visual.** `:androidApp:assembleDebug` aprobado. APK: `androidApp/build/outputs/apk/debug/androidApp-debug.apk`. Boleta renderizada e inspeccionada en emulador a 360, 375, 390, 412, 430 y 480dp; también se inspeccionaron Inicio, Historial y Resumen a 360dp. Las capturas están en `build/ventas-qa/`. Esta revisión usa previews de los componentes reales con datos de ejemplo; no sustituye una aceptación completa con cuentas reales contra el servidor desplegado. No se probaron targets iOS en Windows.

Para usar la nueva versión, publicar los cambios de API junto al móvil y asegurar que esté aplicada la migración de idempotencia ya existente en el proyecto (`2026_10_30_100000_create_api_idempotencia_table.php`). No se ejecutaron migraciones sobre la base de datos de trabajo.
