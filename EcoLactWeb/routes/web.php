<?php

use App\Http\Controllers\Web\Acopiador\ComunicadoController as AcopiadorComunicadoController;
use App\Http\Controllers\Web\Acopiador\DashboardController as AcopiadorDashboardController;
use App\Http\Controllers\Web\Acopiador\EntregaController as AcopiadorEntregaController;
use App\Http\Controllers\Web\Acopiador\ProveedorController as AcopiadorProveedorController;
use App\Http\Controllers\Web\Acopiador\RecorridoController as AcopiadorRecorridoController;
use App\Http\Controllers\Web\Acopiador\SeguimientoCalidadController as AcopiadorSeguimientoCalidadController;
use App\Http\Controllers\Web\AcopiadorController;
use App\Http\Controllers\Web\Admin\CajaController;
use App\Http\Controllers\Web\Admin\CompraInsumoController as AdminCompraInsumoController;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\InsumoController as AdminInsumoController;
use App\Http\Controllers\Web\Admin\PresentacionInsumoController as AdminPresentacionInsumoController;
use App\Http\Controllers\Web\AdministradorController;
use App\Http\Controllers\Web\Analista\AnalisisCalidadController as AnalistaAnalisisCalidadController;
use App\Http\Controllers\Web\Analista\ComunicadoController as AnalistaComunicadoController;
use App\Http\Controllers\Web\Analista\DashboardController as AnalistaDashboardController;
use App\Http\Controllers\Web\Analista\EntregaController as AnalistaEntregaController;
use App\Http\Controllers\Web\Analista\MuestreoController as AnalistaMuestreoController;
use App\Http\Controllers\Web\Analista\ProveedorController as AnalistaProveedorController;
use App\Http\Controllers\Web\AnalistaCalidadController;
use App\Http\Controllers\Web\Auth\HomeRedirectController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Calidad\AnalisisCalidadController;
use App\Http\Controllers\Web\Calidad\ParametroCalidadController;
use App\Http\Controllers\Web\Calidad\ReglaCalidadController;
use App\Http\Controllers\Web\Calidad\TicketAnalizadorController;
use App\Http\Controllers\Web\Comunicacion\ComunicadoController;
use App\Http\Controllers\Web\Comunicacion\NotificacionController;
use App\Http\Controllers\Web\EncargadoPlantaController;
use App\Http\Controllers\Web\EntregaController;
use App\Http\Controllers\Web\Liquidacion\LiquidacionController;
use App\Http\Controllers\Web\NuevoTrabajadorController;
use App\Http\Controllers\Web\Pago\ConfiguracionPagoController;
use App\Http\Controllers\Web\Pago\ControlAsistenciaController;
use App\Http\Controllers\Web\Pago\JornadaPersonalController;
use App\Http\Controllers\Web\Pago\PagoController;
use App\Http\Controllers\Web\Pago\PagoSemanalProveedorController;
use App\Http\Controllers\Web\Pago\PagoTrabajadorController;
use App\Http\Controllers\Web\PersonalVentas\CompradorController as PersonalVentasCompradorController;
use App\Http\Controllers\Web\PersonalVentas\DashboardController as PersonalVentasDashboardController;
use App\Http\Controllers\Web\PersonalVentas\ProductoController as PersonalVentasProductoController;
use App\Http\Controllers\Web\PersonalVentas\VentaController as PersonalVentasVentaController;
use App\Http\Controllers\Web\PersonalVentasController;
use App\Http\Controllers\Web\Planta\DashboardController as PlantaDashboardController;
use App\Http\Controllers\Web\Planta\InsumoController as PlantaInsumoController;
use App\Http\Controllers\Web\Planta\ProduccionController as PlantaProduccionController;
use App\Http\Controllers\Web\Planta\ProductoController as PlantaProductoController;
use App\Http\Controllers\Web\Planta\RecetaController as PlantaRecetaController;
use App\Http\Controllers\Web\Produccion\CategoriaProductoController;
use App\Http\Controllers\Web\Produccion\ProduccionController;
use App\Http\Controllers\Web\Produccion\ProductoController;
use App\Http\Controllers\Web\Produccion\RecetaController;
use App\Http\Controllers\Web\Produccion\StockController;
use App\Http\Controllers\Web\Proveedor\AnalisisController as ProveedorAnalisisController;
use App\Http\Controllers\Web\Proveedor\CompraController as ProveedorCompraController;
use App\Http\Controllers\Web\Proveedor\ComunicadoController as ProveedorComunicadoController;
use App\Http\Controllers\Web\Proveedor\DashboardController as ProveedorDashboardController;
use App\Http\Controllers\Web\Proveedor\EntregaController as ProveedorEntregaController;
use App\Http\Controllers\Web\Proveedor\LiquidacionController as ProveedorLiquidacionController;
use App\Http\Controllers\Web\Proveedor\PagoController as ProveedorPagoController;
use App\Http\Controllers\Web\Proveedor\PerfilController as ProveedorPerfilController;
use App\Http\Controllers\Web\Proveedor\QrController as ProveedorQrController;
use App\Http\Controllers\Web\ProveedorController;
use App\Http\Controllers\Web\ProveedorPagosHistorialController;
use App\Http\Controllers\Web\RutaController;
use App\Http\Controllers\Web\Ventas\CompradorController;
use App\Http\Controllers\Web\Ventas\ProductoPrecioController;
use App\Http\Controllers\Web\Ventas\VentaProductoController;
use App\Http\Controllers\Web\ZonaController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeRedirectController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.store');
});

Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Etapa 10.2.1 — Rutas Web de EcoLact por rol
|--------------------------------------------------------------------------
|
| Flujo: URL -> auth -> active -> role:X -> Controller -> Policy -> Use
| Case. Esta capa NO contiene lógica de negocio.
|
| Corrección de arquitectura de presentación respecto a la Etapa 10.2: en
| 10.2 varias rutas bajo /admin/* eran multi-rol (ACOPIADOR/PROVEEDOR las
| alcanzaban vía Policy, sin middleware de rol). Ahora cada rol navega bajo
| su propio prefijo (/admin, /acopiador, /proveedor) con Controllers de
| presentación delgados y específicos por rol (App\Http\Controllers\Web\
| Acopiador\*, App\Http\Controllers\Web\Proveedor\*) que reutilizan los
| MISMOS Use Cases que ya usaban las rutas admin.* (ningún Use Case ni
| Repository se duplicó). /admin/* vuelve a ser 100% exclusivo de
| ADMINISTRADOR. Las Policies siguen siendo la autorización real del
| recurso concreto; el middleware de rol es ahora, además, el filtro de
| navegación por área.
|
*/
Route::middleware(['auth', 'active'])->group(function () {

    // ------------------------------------------------------------------
    // ADMINISTRADOR — administración global
    // ------------------------------------------------------------------
    Route::prefix('admin')->as('admin.')->middleware('role:ADMINISTRADOR')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Proveedores
        Route::get('proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
        Route::get('proveedores/{proveedor}', [ProveedorController::class, 'show'])->name('proveedores.show');
        // Panel derecho de la pantalla de Proveedores (fragmento HTML pedido por fetch).
        Route::get('proveedores/{proveedor}/panel', [ProveedorController::class, 'panel'])->name('proveedores.panel');
        Route::post('proveedores', [ProveedorController::class, 'store'])->name('proveedores.store');
        Route::match(['put', 'patch'], 'proveedores/{proveedor}', [ProveedorController::class, 'update'])->name('proveedores.update');
        Route::patch('proveedores/{proveedor}/desactivar', [ProveedorController::class, 'desactivar'])->name('proveedores.desactivar');
        Route::patch('proveedores/{proveedor}/reactivar', [ProveedorController::class, 'reactivar'])->name('proveedores.reactivar');
        Route::get('proveedores/{proveedor}/entregas', [EntregaController::class, 'porProveedor'])->name('proveedores.entregas.index');
        // Recursos de datos del modal "Ver pagos" del detalle (JSON); no son pantallas.
        Route::get('proveedores/{proveedor}/pagos', [ProveedorPagosHistorialController::class, 'index'])->name('proveedores.pagos.index');
        Route::get('proveedores/{proveedor}/pagos/{obligacion}', [ProveedorPagosHistorialController::class, 'show'])->name('proveedores.pagos.show');
        // NO existe destroy: los proveedores no se eliminan físicamente.

        // Acopiadores
        Route::get('acopiadores', [AcopiadorController::class, 'index'])->name('acopiadores.index');
        Route::get('acopiadores/{acopiador}', [AcopiadorController::class, 'show'])->name('acopiadores.show');
        // Panel derecho de la pantalla de Acopiadores (fragmento HTML pedido por fetch).
        Route::get('acopiadores/{acopiador}/panel', [AcopiadorController::class, 'panel'])->name('acopiadores.panel');
        Route::post('acopiadores', [AcopiadorController::class, 'store'])->name('acopiadores.store');
        Route::patch('acopiadores/{acopiador}', [AcopiadorController::class, 'update'])->name('acopiadores.update');
        Route::patch('acopiadores/{acopiador}/desactivar', [AcopiadorController::class, 'desactivar'])->name('acopiadores.desactivar');
        Route::patch('acopiadores/{acopiador}/reactivar', [AcopiadorController::class, 'reactivar'])->name('acopiadores.reactivar');

        // Perfil del administrador autenticado ("Mi perfil"): sin ficha ni listado, autovista.
        Route::get('mi-perfil', [AdministradorController::class, 'show'])->name('administrador.show');

        // Zonas
        Route::get('zonas', [ZonaController::class, 'index'])->name('zonas.index');
        Route::get('zonas/{zona}', [ZonaController::class, 'show'])->name('zonas.show');
        Route::post('zonas', [ZonaController::class, 'store'])->name('zonas.store');
        Route::patch('zonas/{zona}', [ZonaController::class, 'update'])->name('zonas.update');
        Route::delete('zonas/{zona}', [ZonaController::class, 'destroy'])->name('zonas.destroy');

        // Zonas: panel derecho de la pantalla maestro-detalle (fragmento HTML)
        // y búsqueda JSON, que se consumen sin recargar la página y asignación de
        // un proveedor a zona + ruta + punto de recolección.
        Route::get('zonas/proveedores/buscar', [ZonaController::class, 'buscarProveedores'])->name('zonas.proveedores.buscar');
        Route::get('zonas/panel/sin-asignar', [ZonaController::class, 'panelSinAsignar'])->name('zonas.panel.sin-asignar');
        Route::get('zonas/{zona}/panel', [ZonaController::class, 'panel'])->name('zonas.panel');
        Route::put('zonas/proveedores/{proveedor}/asignacion', [ZonaController::class, 'guardarAsignacion'])->name('zonas.proveedores.asignacion');
        // Mapa general: "Mover ubicación" de un pin (misma zona o cambio de zona, verificado en el servidor).
        Route::patch('zonas/proveedores/{proveedor}/ubicacion', [ZonaController::class, 'moverProveedor'])->name('zonas.proveedores.ubicacion');

        // Rutas: configuración de proveedores y orden de visita dentro de
        // una zona, más la asignación del acopiador que abre el recorrido.
        Route::post('zonas/{zona}/rutas', [RutaController::class, 'store'])->name('zonas.rutas.store');
        Route::get('rutas/{ruta}', [RutaController::class, 'show'])->name('rutas.show');
        Route::get('rutas/{ruta}/panel', [RutaController::class, 'panel'])->name('rutas.panel');
        Route::patch('rutas/{ruta}', [RutaController::class, 'update'])->name('rutas.update');
        Route::get('rutas/{ruta}/proveedores-disponibles', [RutaController::class, 'buscarProveedores'])->name('rutas.proveedores-disponibles');
        Route::post('rutas/{ruta}/detalles', [RutaController::class, 'agregarProveedor'])->name('rutas.detalles.store');
        Route::delete('rutas/{ruta}/detalles/{detalle}', [RutaController::class, 'quitarProveedor'])->name('rutas.detalles.destroy');
        Route::patch('rutas/{ruta}/detalles/reordenar', [RutaController::class, 'reordenarDetalles'])->name('rutas.detalles.reordenar');
        Route::post('rutas/{ruta}/recorridos', [RutaController::class, 'abrirRecorrido'])->name('rutas.recorridos.store');

        // Entregas: lectura global + alta administrativa (Gate::before).
        Route::get('entregas', [EntregaController::class, 'index'])->name('entregas.index');
        Route::get('entregas/buscar-proveedor', [EntregaController::class, 'buscarProveedorPorDni'])->name('entregas.buscar-proveedor');
        Route::get('entregas/{entrega}', [EntregaController::class, 'show'])->name('entregas.show');
        // Fragmento del modal "Ver entrega" + edición inline de litros (auditada).
        Route::get('entregas/{entrega}/detalle', [EntregaController::class, 'detalle'])->name('entregas.detalle');
        Route::patch('entregas/{entrega}/litros', [EntregaController::class, 'actualizarLitros'])->name('entregas.litros.update');
        Route::post('entregas', [EntregaController::class, 'store'])->name('entregas.store');
        Route::patch('entregas/{entrega}', [EntregaController::class, 'update'])->name('entregas.update');
        Route::patch('entregas/{entrega}/anular', [EntregaController::class, 'anular'])->name('entregas.anular');

        // Análisis de calidad: lectura global + operación administrativa.
        Route::get('analisis-calidad', [AnalisisCalidadController::class, 'index'])->name('analisis-calidad.index');
        Route::get('entregas/{entrega}/analisis-calidad', [AnalisisCalidadController::class, 'porEntrega'])->name('entregas.analisis-calidad.index');
        // Fragmento del modal "Ver análisis" del listado de Calidad.
        Route::get('entregas/{entrega}/analisis-calidad/detalle', [AnalisisCalidadController::class, 'detalle'])->name('entregas.analisis-calidad.detalle');
        Route::get('analisis-calidad/{analisis}', [AnalisisCalidadController::class, 'show'])->name('analisis-calidad.show');
        Route::post('analisis-calidad', [AnalisisCalidadController::class, 'store'])->name('analisis-calidad.store');
        // OCR local del ticket del analizador: solo PROPONE valores, no guarda el análisis.
        Route::post('analisis-calidad/escanear-ticket', TicketAnalizadorController::class)->name('analisis-calidad.escanear-ticket');
        Route::patch('analisis-calidad/{analisis}/finalizar', [AnalisisCalidadController::class, 'finalizar'])->name('analisis-calidad.finalizar');

        // Parámetros de calidad
        Route::get('parametros-calidad', [ParametroCalidadController::class, 'index'])->name('parametros-calidad.index');
        Route::get('parametros-calidad/{parametro}', [ParametroCalidadController::class, 'show'])->name('parametros-calidad.show');
        Route::get('parametros-calidad/{parametro}/panel', [ParametroCalidadController::class, 'panel'])->name('parametros-calidad.panel');
        Route::post('parametros-calidad', [ParametroCalidadController::class, 'store'])->name('parametros-calidad.store');
        Route::patch('parametros-calidad/{parametro}', [ParametroCalidadController::class, 'update'])->name('parametros-calidad.update');
        // NO existe destroy.

        // Reglas de calidad
        Route::get('parametros-calidad/{parametro}/reglas-calidad', [ReglaCalidadController::class, 'porParametro'])->name('parametros-calidad.reglas-calidad.index');
        Route::get('reglas-calidad', [ReglaCalidadController::class, 'index'])->name('reglas-calidad.index');
        Route::post('reglas-calidad', [ReglaCalidadController::class, 'store'])->name('reglas-calidad.store');
        Route::patch('reglas-calidad/{regla}', [ReglaCalidadController::class, 'update'])->name('reglas-calidad.update');

        // Configuración de pagos: los valores base que usa Pagos. Trabajadores: sueldo
        // mensual de cada trabajador registrado. Proveedores: precio por litro (con
        // vigencia histórica en precios_leche) y descuento por la semana OBSERVADA.
        // Define reglas versionadas: NO registra dinero, asistencia ni pagos.
        Route::get('configuracion-pagos', [ConfiguracionPagoController::class, 'index'])->name('configuracion-pagos.index');
        Route::get('configuracion-pagos/trabajadores/{userId}/panel', [ConfiguracionPagoController::class, 'panelTrabajador'])->whereNumber('userId')->name('configuracion-pagos.trabajadores.panel');
        Route::post('configuracion-pagos', [ConfiguracionPagoController::class, 'store'])->name('configuracion-pagos.store');
        Route::patch('configuracion-pagos/{configuracion}', [ConfiguracionPagoController::class, 'update'])->name('configuracion-pagos.update');

        // Hoja imprimible de la liquidación de un proveedor (sobre de pago). Ya no hay
        // pantalla de Liquidaciones: el cálculo nace en Configuración -> Pagos.
        Route::get('liquidaciones/{liquidacion}/imprimir', [LiquidacionController::class, 'imprimir'])->name('liquidaciones.imprimir');

        // Finanzas > Caja: solo lectura. Los movimientos nacen en Ventas, Pagos e Insumos;
        // aquí no hay alta, edición ni borrado de movimientos.
        Route::get('caja', [CajaController::class, 'index'])->name('caja.index');
        Route::get('caja/{movimiento}/panel', [CajaController::class, 'panel'])->whereNumber('movimiento')->name('caja.panel');

        // Pagar a proveedores: pago semanal en efectivo. Elegir proveedores, revisar un
        // comprobante por cada uno y, al confirmar la impresión, marcarlos como pagados.
        Route::get('pagos/proveedores', [PagoSemanalProveedorController::class, 'index'])->name('pagos.proveedores.index');
        Route::get('pagos/proveedores/preview', [PagoSemanalProveedorController::class, 'preview'])->name('pagos.proveedores.preview');
        Route::post('pagos/proveedores/imprimir-y-marcar-pagados', [PagoSemanalProveedorController::class, 'imprimirYMarcarPagados'])->name('pagos.proveedores.print-and-pay');
        Route::get('pagos/proveedores/comprobante/{obligacion}', [PagoSemanalProveedorController::class, 'receipt'])->name('pagos.proveedores.receipt');

        // Pagar a trabajadores: sueldo mensual del personal, con bonificación/descuento
        // ajustables por trabajador mientras esté PENDIENTE (tabla + panel lateral).
        Route::get('pagos/trabajadores', [PagoTrabajadorController::class, 'index'])->name('pagos.trabajadores.index');
        Route::get('pagos/trabajadores/{obligacion}/panel', [PagoTrabajadorController::class, 'panel'])->name('pagos.trabajadores.panel');
        Route::get('pagos/trabajadores/sin-sueldo/{trabajador}/panel', [PagoTrabajadorController::class, 'panelSinSueldo'])->name('pagos.trabajadores.sin-sueldo');
        Route::post('pagos/trabajadores/{obligacion}/ajuste', [PagoTrabajadorController::class, 'ajustar'])->name('pagos.trabajadores.ajuste');
        Route::post('pagos/trabajadores/{obligacion}/pagar', [PagoTrabajadorController::class, 'registrarPago'])->name('pagos.trabajadores.pagar');
        Route::get('pagos/trabajadores/{obligacion}/comprobante', [PagoTrabajadorController::class, 'comprobante'])->name('pagos.trabajadores.comprobante');

        // Control de días trabajados: una sola ruta reutilizada por los 5 perfiles de
        // personal y por el panel de "Pagar a trabajadores".
        // Trabajadores → Nuevo trabajador: un formulario que envía al alta existente de cada rol.
        Route::get('trabajadores/nuevo', NuevoTrabajadorController::class)->name('trabajadores.create');
        Route::get('trabajadores/{trabajador}/jornadas', [JornadaPersonalController::class, 'index'])->name('trabajadores.jornadas.index');
        Route::post('trabajadores/{trabajador}/jornadas', [JornadaPersonalController::class, 'store'])->name('trabajadores.jornadas.store');
        Route::post('trabajadores/{trabajador}/jornadas/{fecha}/corregir', [JornadaPersonalController::class, 'corregir'])->name('trabajadores.jornadas.corregir');

        // Control de asistencia: registro y consulta diaria del personal (QR, manual, corrección).
        Route::get('asistencia', [ControlAsistenciaController::class, 'index'])->name('asistencia.index');

        // Pagos: pendientes y pagados generados desde Configuración de pagos. Un pago
        // NO se crea a mano; solo se confirma la entrega del dinero de un pendiente.
        Route::get('pagos', [PagoController::class, 'index'])->name('pagos.index');
        Route::post('pagos/generar', [PagoController::class, 'generar'])->name('pagos.generar');
        Route::post('pagos/{obligacion}/pagar', [PagoController::class, 'registrar'])->name('pagos.pagar');
        Route::get('pagos/{obligacion}/comprobante', [PagoController::class, 'comprobante'])->name('pagos.comprobante');

        // Comunicados: lectura global + crear + editar/publicar borradores.
        Route::get('comunicados', [ComunicadoController::class, 'index'])->name('comunicados.index');
        Route::get('comunicados/{comunicado}', [ComunicadoController::class, 'show'])->name('comunicados.show');
        Route::post('comunicados', [ComunicadoController::class, 'store'])->name('comunicados.store');
        Route::patch('comunicados/{comunicado}', [ComunicadoController::class, 'update'])->name('comunicados.update');
        Route::patch('comunicados/{comunicado}/publicar', [ComunicadoController::class, 'publicar'])->name('comunicados.publicar');

        // Producción: nueva producción (producto + cantidad, el sistema calcula
        // los insumos con la receta), previsualización de requerimientos (no
        // descuenta nada), iniciar (descuenta insumos) y finalizar (registra lo
        // obtenido e ingresa el producto terminado).
        Route::get('produccion', [ProduccionController::class, 'index'])->name('produccion.index');
        Route::get('produccion/crear', [ProduccionController::class, 'create'])->name('produccion.create');
        Route::post('produccion/requerimientos', [ProduccionController::class, 'requerimientos'])->name('produccion.requerimientos');
        Route::get('produccion/{lote}', [ProduccionController::class, 'show'])->name('produccion.show');
        Route::post('produccion', [ProduccionController::class, 'store'])->name('produccion.store');
        Route::post('produccion/{lote}/finalizar', [ProduccionController::class, 'finalizar'])->name('produccion.finalizar');

        // Productos: consulta de productos terminados (nacen desde su receta; no se
        // crean ni se editan aquí) + gestión de sus precios por tipo de cliente
        // en la fila desplegable. Precios: exclusivo de ADMINISTRADOR (ProductoPrecioPolicy).
        Route::get('productos', [ProductoController::class, 'index'])->name('productos.index');
        Route::put('productos/{producto}/precios', [ProductoPrecioController::class, 'update'])->whereNumber('producto')->name('producto-precios.update');
        Route::delete('productos/{producto}/precios', [ProductoPrecioController::class, 'destroy'])->whereNumber('producto')->name('producto-precios.destroy');

        // Recetas: configuración de qué insumos (leche incluida) y cuánto de
        // cada uno lleva un producto. NO descuenta inventario; se versiona.
        Route::get('recetas', [RecetaController::class, 'index'])->name('recetas.index');
        Route::post('recetas', [RecetaController::class, 'store'])->name('recetas.store');
        Route::post('recetas/categorias', [CategoriaProductoController::class, 'store'])->name('recetas.categorias.store');
        Route::patch('recetas/{receta}', [RecetaController::class, 'update'])->name('recetas.update');
        Route::post('recetas/{receta}/version', [RecetaController::class, 'nuevaVersion'])->name('recetas.version');
        Route::patch('recetas/{receta}/activar', [RecetaController::class, 'activar'])->name('recetas.activar');
        Route::patch('recetas/{receta}/desactivar', [RecetaController::class, 'desactivar'])->name('recetas.desactivar');

        // Insumos: vista administrativa propia (admin.insumos.*), reutiliza
        // los MISMOS Use Cases que Web\Planta\InsumoController (ese ya no se
        // comparte con ADMIN: tiene su propia vista operativa para
        // ENCARGADO_PLANTA). Stock de productos: reutiliza los mismos
        // Controllers que ya usa VENTAS (/ventas) — mismos Use Cases, mismas
        // Policies (Gate::before ya concede acceso a ADMINISTRADOR).
        Route::get('insumos', [AdminInsumoController::class, 'index'])->name('insumos.index');
        Route::get('insumos/{insumo}', [AdminInsumoController::class, 'show'])->name('insumos.show');
        Route::get('insumos/{insumo}/panel', [AdminInsumoController::class, 'panel'])->name('insumos.panel');
        Route::post('insumos/{insumo}/presentaciones', [AdminPresentacionInsumoController::class, 'store'])->name('insumos.presentaciones.store');
        Route::patch('insumos/{insumo}/presentaciones/{presentacion}', [AdminPresentacionInsumoController::class, 'update'])->whereNumber('presentacion')->name('insumos.presentaciones.update');
        // Compra de insumos: boleta escaneada (OCR propone, la persona revisa) o registro manual.
        Route::post('compras-insumo/boleta', [AdminCompraInsumoController::class, 'procesarBoleta'])->name('compras-insumo.boleta');
        Route::post('compras-insumo/previsualizar', [AdminCompraInsumoController::class, 'previsualizar'])->name('compras-insumo.previsualizar');
        Route::post('compras-insumo', [AdminCompraInsumoController::class, 'store'])->name('compras-insumo.store');
        Route::get('compras-insumo/{compra}', [AdminCompraInsumoController::class, 'show'])->whereNumber('compra')->name('compras-insumo.show');
        Route::post('insumos', [AdminInsumoController::class, 'store'])->name('insumos.store');
        Route::patch('insumos/{insumo}', [AdminInsumoController::class, 'update'])->name('insumos.update');
        Route::post('insumos/{insumo}/ajuste', [AdminInsumoController::class, 'registrarAjuste'])->name('insumos.ajuste');

        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('stock/{tipo}/{id}', [StockController::class, 'show'])->whereIn('tipo', ['producto', 'insumo'])->whereNumber('id')->name('stock.show');
        Route::post('stock/ajuste', [StockController::class, 'ajustar'])->name('stock.ajuste');

        Route::get('compradores', [CompradorController::class, 'index'])->name('compradores.index');
        Route::post('compradores', [CompradorController::class, 'store'])->name('compradores.store');

        Route::get('ventas', [VentaProductoController::class, 'index'])->name('ventas.index');
        Route::get('ventas/saldo-proveedor/{proveedor}', [VentaProductoController::class, 'saldoProveedor'])->whereNumber('proveedor')->name('ventas.saldo-proveedor');
        Route::get('ventas/{venta}', [VentaProductoController::class, 'show'])->name('ventas.show');
        Route::get('ventas/{venta}/boleta.pdf', [VentaProductoController::class, 'boleta'])->name('ventas.boleta');
        Route::get('proveedores/{proveedor}/ventas', [VentaProductoController::class, 'porProveedor'])->name('proveedores.ventas.index');
        Route::post('ventas', [VentaProductoController::class, 'store'])->name('ventas.store');

        // Encargados de planta, personal de ventas y analistas de calidad:
        // gestión de cuentas de acceso exclusiva de ADMINISTRADOR (el propio
        // ADMINISTRADOR sigue creándose solo por seeder, no por esta interfaz).
        Route::get('encargados-planta', [EncargadoPlantaController::class, 'index'])->name('encargados-planta.index');
        Route::get('encargados-planta/{encargado}', [EncargadoPlantaController::class, 'show'])->name('encargados-planta.show');
        Route::get('encargados-planta/{encargado}/panel', [EncargadoPlantaController::class, 'panel'])->name('encargados-planta.panel');
        Route::post('encargados-planta', [EncargadoPlantaController::class, 'store'])->name('encargados-planta.store');
        Route::patch('encargados-planta/{encargado}', [EncargadoPlantaController::class, 'update'])->name('encargados-planta.update');
        Route::patch('encargados-planta/{encargado}/desactivar', [EncargadoPlantaController::class, 'desactivar'])->name('encargados-planta.desactivar');
        Route::patch('encargados-planta/{encargado}/reactivar', [EncargadoPlantaController::class, 'reactivar'])->name('encargados-planta.reactivar');

        Route::get('personal-ventas', [PersonalVentasController::class, 'index'])->name('personal-ventas.index');
        Route::get('personal-ventas/{personalVenta}', [PersonalVentasController::class, 'show'])->name('personal-ventas.show');
        Route::get('personal-ventas/{personalVenta}/panel', [PersonalVentasController::class, 'panel'])->name('personal-ventas.panel');
        Route::post('personal-ventas', [PersonalVentasController::class, 'store'])->name('personal-ventas.store');
        Route::patch('personal-ventas/{personalVenta}', [PersonalVentasController::class, 'update'])->name('personal-ventas.update');
        Route::patch('personal-ventas/{personalVenta}/desactivar', [PersonalVentasController::class, 'desactivar'])->name('personal-ventas.desactivar');
        Route::patch('personal-ventas/{personalVenta}/reactivar', [PersonalVentasController::class, 'reactivar'])->name('personal-ventas.reactivar');

        Route::get('analistas-calidad', [AnalistaCalidadController::class, 'index'])->name('analistas-calidad.index');
        Route::get('analistas-calidad/{analista}', [AnalistaCalidadController::class, 'show'])->name('analistas-calidad.show');
        Route::get('analistas-calidad/{analista}/panel', [AnalistaCalidadController::class, 'panel'])->name('analistas-calidad.panel');
        Route::post('analistas-calidad', [AnalistaCalidadController::class, 'store'])->name('analistas-calidad.store');
        Route::patch('analistas-calidad/{analista}', [AnalistaCalidadController::class, 'update'])->name('analistas-calidad.update');
        Route::patch('analistas-calidad/{analista}/desactivar', [AnalistaCalidadController::class, 'desactivar'])->name('analistas-calidad.desactivar');
        Route::patch('analistas-calidad/{analista}/reactivar', [AnalistaCalidadController::class, 'reactivar'])->name('analistas-calidad.reactivar');
    });

    // ------------------------------------------------------------------
    // ACOPIADOR — operación de campo y calidad
    // ------------------------------------------------------------------
    Route::prefix('acopiador')->as('acopiador.')->middleware('role:ACOPIADOR')->group(function () {
        Route::get('dashboard', [AcopiadorDashboardController::class, 'index'])->name('dashboard');

        // Proveedores de su zona (solo lectura: crear/editar sigue siendo
        // exclusivo de ADMINISTRADOR).
        Route::get('proveedores', [AcopiadorProveedorController::class, 'index'])->name('proveedores.index');
        Route::get('proveedores/{proveedor}', [AcopiadorProveedorController::class, 'show'])->name('proveedores.show');
        Route::get('proveedores/{proveedor}/qr', [AcopiadorProveedorController::class, 'qr'])->name('proveedores.qr');

        // Entregas de su zona: historial + registrar (dos pantallas, no se
        // mezclan en una sola).
        Route::get('entregas', [AcopiadorEntregaController::class, 'index'])->name('entregas.index');
        Route::get('entregas/crear', [AcopiadorEntregaController::class, 'create'])->name('entregas.create');
        Route::get('entregas/{entrega}', [AcopiadorEntregaController::class, 'show'])->name('entregas.show');
        Route::post('entregas', [AcopiadorEntregaController::class, 'store'])->middleware('requiere.asistencia')->name('entregas.store');

        // Recorridos: mapa + lista ordenada de paradas, identificación por
        // DNI/QR y registro de acopio. El acopiador nunca crea sus propios
        // recorridos (eso es exclusivo de ADMINISTRADOR desde Zonas/Rutas):
        // solo abre, opera y consulta los que ya le fueron asignados.
        Route::get('recorridos', [AcopiadorRecorridoController::class, 'index'])->name('recorridos.index');
        Route::get('recorridos/{recorrido}', [AcopiadorRecorridoController::class, 'show'])->name('recorridos.show');
        Route::patch('recorridos/{recorrido}/iniciar', [AcopiadorRecorridoController::class, 'iniciar'])->middleware('requiere.asistencia')->name('recorridos.iniciar');
        Route::patch('recorridos/{recorrido}/finalizar', [AcopiadorRecorridoController::class, 'finalizar'])->middleware('requiere.asistencia')->name('recorridos.finalizar');
        Route::post('recorridos/{recorrido}/identificar', [AcopiadorRecorridoController::class, 'identificar'])->name('recorridos.identificar');
        Route::post('recorridos/{recorrido}/paradas/{parada}/acopio', [AcopiadorRecorridoController::class, 'registrarAcopio'])->middleware('requiere.asistencia')->name('recorridos.paradas.acopio');
        Route::post('recorridos/{recorrido}/paradas/{parada}/no-entrego', [AcopiadorRecorridoController::class, 'noEntrego'])->middleware('requiere.asistencia')->name('recorridos.paradas.no-entrego');

        // Seguimiento de calidad: SOLO LECTURA (ver el resultado de lo que
        // él recolectó). Registrar/finalizar es exclusivo de CALIDAD.
        Route::get('seguimiento-calidad', [AcopiadorSeguimientoCalidadController::class, 'index'])->name('seguimiento-calidad.index');
        Route::get('seguimiento-calidad/{entrega}', [AcopiadorSeguimientoCalidadController::class, 'show'])->name('seguimiento-calidad.show');

        // Comunicados dirigidos a él.
        Route::get('comunicados', [AcopiadorComunicadoController::class, 'index'])->name('comunicados.index');
        Route::get('comunicados/{comunicado}', [AcopiadorComunicadoController::class, 'show'])->name('comunicados.show');

        // NO se permite: análisis de calidad (registrar/finalizar), reglas
        // de calidad, precios, reglas de pago, pagos, zonas, usuarios,
        // producción administrativa.
    });

    // ------------------------------------------------------------------
    // CALIDAD — control de calidad técnico, sin zona asignada (puede
    // analizar y comunicarse con proveedores de cualquier zona).
    // ------------------------------------------------------------------
    Route::prefix('calidad')->as('calidad.')->middleware('role:CALIDAD')->group(function () {
        Route::get('dashboard', [AnalistaDashboardController::class, 'index'])->name('dashboard');

        // Muestreo: Calidad solo analiza las entregas que selecciona como
        // muestra (al azar —lo sortea el servidor— o manualmente).
        Route::get('muestreo', [AnalistaMuestreoController::class, 'index'])->name('muestreo.index');
        Route::post('muestreo', [AnalistaMuestreoController::class, 'store'])->middleware('requiere.asistencia')->name('muestreo.store');

        // Entregas: lectura de cualquier zona, para elegir qué muestrear;
        // store = muestreo directo en campo.
        Route::get('entregas', [AnalistaEntregaController::class, 'index'])->name('entregas.index');
        Route::post('entregas', [AnalistaEntregaController::class, 'store'])->middleware('requiere.asistencia')->name('entregas.store');
        Route::get('entregas/{entrega}', [AnalistaEntregaController::class, 'show'])->name('entregas.show');
        Route::get('entregas/{entrega}/analisis', [AnalistaAnalisisCalidadController::class, 'porEntrega'])->name('entregas.analisis.index');

        // Análisis de calidad: registrar y finalizar es exclusivo de este rol.
        Route::get('analisis-calidad', [AnalistaAnalisisCalidadController::class, 'index'])->name('analisis-calidad.index');
        Route::get('analisis-calidad/{analisis}', [AnalistaAnalisisCalidadController::class, 'show'])->name('analisis-calidad.show');
        Route::post('analisis-calidad', [AnalistaAnalisisCalidadController::class, 'store'])->middleware('requiere.asistencia')->name('analisis-calidad.store');
        Route::post('analisis-calidad/escanear-ticket', TicketAnalizadorController::class)->middleware('requiere.asistencia')->name('analisis-calidad.escanear-ticket');
        Route::patch('analisis-calidad/{analisis}/finalizar', [AnalistaAnalisisCalidadController::class, 'finalizar'])->middleware('requiere.asistencia')->name('analisis-calidad.finalizar');

        // Proveedores: lectura de cualquier zona, para elegir destinatarios
        // de comunicados y revisar su historial.
        Route::get('proveedores', [AnalistaProveedorController::class, 'index'])->name('proveedores.index');
        Route::get('proveedores/{proveedor}', [AnalistaProveedorController::class, 'show'])->name('proveedores.show');

        // Comunicados: lectura propia (autoservicio, como PROVEEDOR) +
        // redactar propuestas de capacitación a cualquier proveedor
        // (store reutiliza el mismo Controller/Use Case que ADMINISTRADOR,
        // ComunicadoPolicy::create ya admite CALIDAD).
        Route::get('comunicados', [AnalistaComunicadoController::class, 'index'])->name('comunicados.index');
        Route::get('comunicados/{comunicado}', [AnalistaComunicadoController::class, 'show'])->name('comunicados.show');
        Route::post('comunicados', [ComunicadoController::class, 'store'])->middleware('requiere.asistencia')->name('comunicados.store');
    });

    // ------------------------------------------------------------------
    // ENCARGADO_PLANTA — producción e insumos
    // ------------------------------------------------------------------
    Route::prefix('planta')->as('planta.')->middleware('role:ENCARGADO_PLANTA')->group(function () {
        Route::get('dashboard', [PlantaDashboardController::class, 'index'])->name('dashboard');

        // Producción: historial + registrar lote + finalizar (dos pantallas,
        // igual que Entregas del acopiador: no se mezclan en una sola).
        Route::get('produccion', [PlantaProduccionController::class, 'index'])->name('produccion.index');
        Route::get('produccion/crear', [PlantaProduccionController::class, 'create'])->name('produccion.create');
        Route::post('produccion/requerimientos', [PlantaProduccionController::class, 'requerimientos'])->name('produccion.requerimientos');
        Route::get('produccion/{lote}', [PlantaProduccionController::class, 'show'])->name('produccion.show');
        Route::post('produccion', [PlantaProduccionController::class, 'store'])->middleware('requiere.asistencia')->name('produccion.store');
        Route::post('produccion/{lote}/finalizar', [PlantaProduccionController::class, 'finalizar'])->middleware('requiere.asistencia')->name('produccion.finalizar');

        // Recetas: solo lectura para el encargado (las configura el ADMINISTRADOR).
        Route::get('recetas', [PlantaRecetaController::class, 'index'])->name('recetas.index');

        // Insumos: Inventario (catálogo + stock) + Movimientos (historial
        // completo de entradas/salidas), dos pantallas separadas.
        Route::get('insumos', [PlantaInsumoController::class, 'index'])->name('insumos.index');
        Route::get('insumos/movimientos', [PlantaInsumoController::class, 'movimientos'])->name('insumos.movimientos');
        Route::get('insumos/{insumo}', [PlantaInsumoController::class, 'show'])->name('insumos.show');
        Route::post('insumos', [PlantaInsumoController::class, 'store'])->middleware('requiere.asistencia')->name('insumos.store');
        Route::patch('insumos/{insumo}', [PlantaInsumoController::class, 'update'])->middleware('requiere.asistencia')->name('insumos.update');
        Route::post('insumos/{insumo}/movimiento', [PlantaInsumoController::class, 'registrarMovimiento'])->middleware('requiere.asistencia')->name('insumos.movimiento');

        // Productos: SOLO LECTURA del stock generado por producción (el
        // ajuste manual de inventario de productos terminados sigue siendo
        // exclusivo de VENTAS).
        Route::get('productos', [PlantaProductoController::class, 'index'])->name('productos.index');
        Route::get('productos/{producto}', [PlantaProductoController::class, 'show'])->name('productos.show');
    });

    // ------------------------------------------------------------------
    // VENTAS — inventario de productos terminados y venta al proveedor,
    // mayorista o público.
    // ------------------------------------------------------------------
    Route::prefix('ventas')->as('ventas.')->middleware('role:VENTAS')->group(function () {
        Route::get('dashboard', [PersonalVentasDashboardController::class, 'index'])->name('dashboard');

        // Ventas / POS: pestañas Nueva venta + Historial (ventas/crear redirige a la primera;
        // la misma pantalla que admin.ventas.*, ver PantallaVentas).
        Route::get('ventas', [PersonalVentasVentaController::class, 'index'])->name('ventas.index');
        Route::get('ventas/crear', [PersonalVentasVentaController::class, 'create'])->name('ventas.create');
        Route::get('ventas/saldo-proveedor/{proveedor}', [PersonalVentasVentaController::class, 'saldoProveedor'])->whereNumber('proveedor')->name('ventas.saldo-proveedor');
        Route::get('ventas/{venta}', [PersonalVentasVentaController::class, 'show'])->name('ventas.show');
        Route::get('ventas/{venta}/boleta.pdf', [PersonalVentasVentaController::class, 'boleta'])->name('ventas.boleta');
        Route::post('ventas', [PersonalVentasVentaController::class, 'store'])->middleware('requiere.asistencia')->name('ventas.store');

        // Productos: Stock de productos (con ingreso de inventario recibido
        // de planta) + Movimientos (historial completo de entradas/salidas).
        Route::get('productos', [PersonalVentasProductoController::class, 'index'])->name('productos.index');
        Route::get('productos/movimientos', [PersonalVentasProductoController::class, 'movimientos'])->name('productos.movimientos');
        Route::get('productos/{producto}', [PersonalVentasProductoController::class, 'show'])->name('productos.show');

        Route::get('compradores', [PersonalVentasCompradorController::class, 'index'])->name('compradores.index');
        Route::post('compradores', [PersonalVentasCompradorController::class, 'store'])->middleware('requiere.asistencia')->name('compradores.store');

        // NO se permite: configurar precios (ProductoPrecioPolicy es
        // exclusiva de ADMINISTRADOR), producción, insumos, proveedores,
        // liquidaciones, pagos, usuarios.
    });

    // ------------------------------------------------------------------
    // PROVEEDOR — portal de autoservicio
    // ------------------------------------------------------------------
    Route::prefix('proveedor')->as('proveedor.')->middleware('role:PROVEEDOR')->group(function () {
        Route::get('dashboard', [ProveedorDashboardController::class, 'index'])->name('dashboard');

        Route::get('perfil', [ProveedorPerfilController::class, 'show'])->name('perfil');
        Route::get('qr', [ProveedorQrController::class, 'show'])->name('qr');

        Route::get('entregas', [ProveedorEntregaController::class, 'index'])->name('entregas.index');
        Route::get('entregas/{entrega}', [ProveedorEntregaController::class, 'show'])->name('entregas.show');

        Route::get('analisis', [ProveedorAnalisisController::class, 'index'])->name('analisis.index');
        Route::get('analisis/{analisis}', [ProveedorAnalisisController::class, 'show'])->name('analisis.show');

        Route::get('liquidaciones', [ProveedorLiquidacionController::class, 'index'])->name('liquidaciones.index');
        Route::get('liquidaciones/{liquidacion}', [ProveedorLiquidacionController::class, 'show'])->name('liquidaciones.show');

        Route::get('compras', [ProveedorCompraController::class, 'index'])->name('compras.index');
        Route::get('compras/{venta}', [ProveedorCompraController::class, 'show'])->name('compras.show');

        // "Mis pagos" ya no es un módulo aparte en el sidebar: el pago se
        // muestra dentro del detalle de "Mis liquidaciones". Las rutas se
        // conservan (no se elimina funcionalidad ya probada), sin enlace
        // propio en el menú.
        Route::get('pagos', [ProveedorPagoController::class, 'index'])->name('pagos.index');
        Route::get('pagos/{pago}', [ProveedorPagoController::class, 'show'])->name('pagos.show');

        Route::get('comunicados', [ProveedorComunicadoController::class, 'index'])->name('comunicados.index');
        Route::get('comunicados/{comunicado}', [ProveedorComunicadoController::class, 'show'])->name('comunicados.show');
    });

    // ------------------------------------------------------------------
    // Notificaciones: pertenecen al usuario autenticado, no a un área en
    // particular (sección 15: se mantienen compartidas, sin duplicar).
    // ------------------------------------------------------------------
    Route::get('notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::patch('notificaciones/{notificacion}/leida', [NotificacionController::class, 'marcarComoLeida'])->name('notificaciones.marcar-leida');
});

/*
|--------------------------------------------------------------------------
| Pendiente (sin Controller/Use Case; no es un problema de routing)
|--------------------------------------------------------------------------
| Ninguno en esta etapa: "ver mi QR" y "listar mis análisis" (Etapa 10.2
| los reportaba pendientes) ya están resueltos vía
| App\Application\Proveedor\ObtenerMiQr y
| App\Application\Calidad\ObtenerAnalisisProveedor.
*/
