<?php

use App\Http\Controllers\Api\Acopiador\ComunicadoController as AcopiadorComunicadoController;
use App\Http\Controllers\Api\Acopiador\DashboardController as AcopiadorDashboardController;
use App\Http\Controllers\Api\Acopiador\EntregaController as AcopiadorEntregaController;
use App\Http\Controllers\Api\Acopiador\ProveedorController as AcopiadorProveedorController;
use App\Http\Controllers\Api\Acopiador\RecorridoController as AcopiadorRecorridoController;
use App\Http\Controllers\Api\Acopiador\SeguimientoCalidadController as AcopiadorSeguimientoCalidadController;
use App\Http\Controllers\Api\Admin\AcopiadorController;
use App\Http\Controllers\Api\Admin\AnalisisCalidadController;
use App\Http\Controllers\Api\Admin\AnalistaCalidadController;
use App\Http\Controllers\Api\Admin\ComunicadoController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\EncargadoPlantaController;
use App\Http\Controllers\Api\Admin\EntregaController;
use App\Http\Controllers\Api\Admin\LiquidacionController;
use App\Http\Controllers\Api\Admin\PagoController;
use App\Http\Controllers\Api\Admin\PersonalVentasController;
use App\Http\Controllers\Api\Admin\ProduccionController;
use App\Http\Controllers\Api\Admin\ProveedorController;
use App\Http\Controllers\Api\Admin\RecetaController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\StockController;
use App\Http\Controllers\Api\Admin\UsuarioController;
use App\Http\Controllers\Api\Admin\VentaController;
use App\Http\Controllers\Api\Admin\ZonaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Calidad\AnalisisCalidadController as CalidadAnalisisCalidadController;
use App\Http\Controllers\Api\Calidad\ComunicadoController as CalidadComunicadoController;
use App\Http\Controllers\Api\Calidad\DashboardController as CalidadDashboardController;
use App\Http\Controllers\Api\Calidad\EntregaController as CalidadEntregaController;
use App\Http\Controllers\Api\Calidad\ProveedorController as CalidadProveedorController;
use App\Http\Controllers\Api\Comunicacion\NotificacionController;
use App\Http\Controllers\Api\Planta\DashboardController as PlantaDashboardController;
use App\Http\Controllers\Api\Planta\InsumoController as PlantaInsumoController;
use App\Http\Controllers\Api\Planta\ProduccionController as PlantaProduccionController;
use App\Http\Controllers\Api\Planta\ProductoController as PlantaProductoController;
use App\Http\Controllers\Api\Proveedor\AnalisisController as ProveedorAnalisisController;
use App\Http\Controllers\Api\Proveedor\CompraController as ProveedorCompraController;
use App\Http\Controllers\Api\Proveedor\ComunicadoController as ProveedorComunicadoController;
use App\Http\Controllers\Api\Proveedor\DashboardController as ProveedorDashboardController;
use App\Http\Controllers\Api\Proveedor\EntregaController as ProveedorEntregaController;
use App\Http\Controllers\Api\Proveedor\LiquidacionController as ProveedorLiquidacionController;
use App\Http\Controllers\Api\Proveedor\PagoController as ProveedorPagoController;
use App\Http\Controllers\Api\Proveedor\PerfilController as ProveedorPerfilController;
use App\Http\Controllers\Api\Proveedor\QrController as ProveedorQrController;
use App\Http\Controllers\Api\Ventas\CompradorController as VentasCompradorController;
use App\Http\Controllers\Api\Ventas\DashboardController as VentasDashboardController;
use App\Http\Controllers\Api\Ventas\ProductoController as VentasProductoController;
use App\Http\Controllers\Api\Ventas\VentaController as VentasVentaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| API v1 — App móvil (Kotlin Multiplatform)
|--------------------------------------------------------------------------
|
| Capa de serialización JSON delgada: reutiliza EXACTAMENTE los mismos
| Application/Domain/Query que ya usan los Controllers Web\*. No contiene
| lógica de negocio propia. La paginación de los listados (que las
| *ListadoQuery ya devuelven completos, sin paginar en SQL) se aplica solo
| aquí, sobre el array `filas` ya calculado.
|
*/
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::middleware(['active', 'role:ADMINISTRADOR'])->prefix('admin')->name('api.admin.')->group(function () {
            Route::get('dashboard', DashboardController::class)->name('dashboard');

            Route::get('zonas', [ZonaController::class, 'index'])->name('zonas.index');

            Route::get('acopiadores', [AcopiadorController::class, 'index'])->name('acopiadores.index');
            Route::get('acopiadores/{acopiador}', [AcopiadorController::class, 'show'])->name('acopiadores.show');

            Route::get('proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
            Route::get('proveedores/{proveedor}', [ProveedorController::class, 'show'])->name('proveedores.show');

            Route::get('entregas', [EntregaController::class, 'index'])->name('entregas.index');
            Route::get('entregas/{entrega}', [EntregaController::class, 'show'])->name('entregas.show');

            Route::get('liquidaciones', [LiquidacionController::class, 'index'])->name('liquidaciones.index');
            Route::get('liquidaciones/periodos/{periodo}/pdf', [LiquidacionController::class, 'pdfPeriodo'])->name('liquidaciones.pdf-periodo');
            Route::get('liquidaciones/{liquidacion}', [LiquidacionController::class, 'show'])->name('liquidaciones.show');
            Route::get('liquidaciones/{liquidacion}/pdf', [LiquidacionController::class, 'pdf'])->name('liquidaciones.pdf');

            Route::get('pagos', [PagoController::class, 'index'])->name('pagos.index');
            Route::get('pagos/{pago}', [PagoController::class, 'show'])->name('pagos.show');

            Route::get('analisis-calidad', [AnalisisCalidadController::class, 'index'])->name('analisis-calidad.index');
            Route::get('analisis-calidad/{analisis}', [AnalisisCalidadController::class, 'show'])->name('analisis-calidad.show');

            Route::get('produccion', [ProduccionController::class, 'index'])->name('produccion.index');
            Route::get('produccion/{lote}', [ProduccionController::class, 'show'])->name('produccion.show');

            Route::get('recetas', [RecetaController::class, 'index'])->name('recetas.index');

            Route::get('stock', [StockController::class, 'index'])->name('stock.index');
            Route::get('stock/{tipo}/{id}', [StockController::class, 'show'])->whereIn('tipo', ['producto', 'insumo'])->whereNumber('id')->name('stock.show');

            Route::get('ventas', [VentaController::class, 'index'])->name('ventas.index');
            Route::get('ventas/{venta}', [VentaController::class, 'show'])->name('ventas.show');

            Route::get('comunicados', [ComunicadoController::class, 'index'])->name('comunicados.index');
            Route::get('comunicados/{comunicado}', [ComunicadoController::class, 'show'])->name('comunicados.show');
            Route::post('comunicados', [ComunicadoController::class, 'store'])->name('comunicados.store');

            Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');

            Route::get('encargados-planta', [EncargadoPlantaController::class, 'index'])->name('encargados-planta.index');
            Route::get('encargados-planta/{encargado}', [EncargadoPlantaController::class, 'show'])->name('encargados-planta.show');

            Route::get('personal-ventas', [PersonalVentasController::class, 'index'])->name('personal-ventas.index');
            Route::get('personal-ventas/{personalVenta}', [PersonalVentasController::class, 'show'])->name('personal-ventas.show');

            Route::get('analistas-calidad', [AnalistaCalidadController::class, 'index'])->name('analistas-calidad.index');
            Route::get('analistas-calidad/{analista}', [AnalistaCalidadController::class, 'show'])->name('analistas-calidad.show');
        });

        Route::middleware(['active', 'role:ACOPIADOR'])->prefix('acopiador')->name('api.acopiador.')->group(function () {
            Route::get('dashboard', AcopiadorDashboardController::class)->name('dashboard');

            Route::get('proveedores', [AcopiadorProveedorController::class, 'index'])->name('proveedores.index');
            Route::get('proveedores/{proveedor}', [AcopiadorProveedorController::class, 'show'])->name('proveedores.show');

            Route::get('entregas', [AcopiadorEntregaController::class, 'index'])->name('entregas.index');
            Route::get('entregas/{entrega}', [AcopiadorEntregaController::class, 'show'])->name('entregas.show');
            Route::post('entregas', [AcopiadorEntregaController::class, 'store'])->name('entregas.store');

            Route::get('recorridos', [AcopiadorRecorridoController::class, 'index'])->name('recorridos.index');
            Route::get('recorridos/{recorrido}', [AcopiadorRecorridoController::class, 'show'])->name('recorridos.show');
            Route::patch('recorridos/{recorrido}/iniciar', [AcopiadorRecorridoController::class, 'iniciar'])->name('recorridos.iniciar');
            Route::patch('recorridos/{recorrido}/finalizar', [AcopiadorRecorridoController::class, 'finalizar'])->name('recorridos.finalizar');
            Route::post('recorridos/{recorrido}/identificar', [AcopiadorRecorridoController::class, 'identificar'])->name('recorridos.identificar');
            Route::post('recorridos/{recorrido}/paradas/{parada}/acopio', [AcopiadorRecorridoController::class, 'registrarAcopio'])->name('recorridos.paradas.acopio');
            Route::post('recorridos/{recorrido}/paradas/{parada}/no-entrego', [AcopiadorRecorridoController::class, 'noEntrego'])->name('recorridos.paradas.no-entrego');

            Route::get('seguimiento-calidad', [AcopiadorSeguimientoCalidadController::class, 'index'])->name('seguimiento-calidad.index');
            Route::get('seguimiento-calidad/{entrega}', [AcopiadorSeguimientoCalidadController::class, 'show'])->name('seguimiento-calidad.show');

            Route::get('comunicados', [AcopiadorComunicadoController::class, 'index'])->name('comunicados.index');
            Route::get('comunicados/{comunicado}', [AcopiadorComunicadoController::class, 'show'])->name('comunicados.show');
        });

        Route::middleware(['active', 'role:CALIDAD'])->prefix('calidad')->name('api.calidad.')->group(function () {
            Route::get('dashboard', CalidadDashboardController::class)->name('dashboard');

            Route::get('entregas', [CalidadEntregaController::class, 'index'])->name('entregas.index');
            Route::post('entregas', [CalidadEntregaController::class, 'store'])->name('entregas.store');
            Route::get('entregas/{entrega}', [CalidadEntregaController::class, 'show'])->name('entregas.show');
            Route::get('entregas/{entrega}/analisis-calidad', [CalidadAnalisisCalidadController::class, 'porEntrega'])->name('entregas.analisis-calidad.show');

            Route::get('analisis-calidad', [CalidadAnalisisCalidadController::class, 'index'])->name('analisis-calidad.index');
            Route::get('analisis-calidad/{analisis}', [CalidadAnalisisCalidadController::class, 'show'])->name('analisis-calidad.show');
            Route::post('analisis-calidad', [CalidadAnalisisCalidadController::class, 'store'])->name('analisis-calidad.store');
            Route::patch('analisis-calidad/{analisis}/finalizar', [CalidadAnalisisCalidadController::class, 'finalizar'])->name('analisis-calidad.finalizar');

            Route::get('proveedores', [CalidadProveedorController::class, 'index'])->name('proveedores.index');
            Route::get('proveedores/{proveedor}', [CalidadProveedorController::class, 'show'])->name('proveedores.show');

            Route::get('comunicados', [CalidadComunicadoController::class, 'index'])->name('comunicados.index');
            Route::get('comunicados/{comunicado}', [CalidadComunicadoController::class, 'show'])->name('comunicados.show');
            Route::post('comunicados', [CalidadComunicadoController::class, 'store'])->name('comunicados.store');
        });

        Route::middleware(['active', 'role:ENCARGADO_PLANTA'])->prefix('planta')->name('api.planta.')->group(function () {
            Route::get('dashboard', PlantaDashboardController::class)->name('dashboard');

            Route::get('produccion', [PlantaProduccionController::class, 'index'])->name('produccion.index');
            Route::get('produccion/leche-disponible', [PlantaProduccionController::class, 'lecheDisponible'])->name('produccion.leche-disponible');
            Route::get('produccion/{lote}', [PlantaProduccionController::class, 'show'])->name('produccion.show');
            Route::post('produccion/requerimientos', [PlantaProduccionController::class, 'requerimientos'])->name('produccion.requerimientos');
            Route::post('produccion', [PlantaProduccionController::class, 'store'])->name('produccion.store');
            Route::post('produccion/{lote}/finalizar', [PlantaProduccionController::class, 'finalizar'])->name('produccion.finalizar');

            Route::get('insumos', [PlantaInsumoController::class, 'index'])->name('insumos.index');
            Route::get('insumos/movimientos', [PlantaInsumoController::class, 'movimientos'])->name('insumos.movimientos');
            Route::get('insumos/{insumo}', [PlantaInsumoController::class, 'show'])->name('insumos.show');
            Route::post('insumos', [PlantaInsumoController::class, 'store'])->name('insumos.store');
            Route::post('insumos/{insumo}/movimiento', [PlantaInsumoController::class, 'registrarMovimiento'])->name('insumos.movimiento');

            Route::get('productos', [PlantaProductoController::class, 'index'])->name('productos.index');
            Route::get('productos/{producto}', [PlantaProductoController::class, 'show'])->name('productos.show');
        });

        Route::middleware(['active', 'role:VENTAS'])->prefix('ventas')->name('api.ventas.')->group(function () {
            Route::get('dashboard', VentasDashboardController::class)->name('dashboard');

            Route::get('ventas', [VentasVentaController::class, 'index'])->name('ventas.index');
            Route::get('ventas/crear', [VentasVentaController::class, 'crear'])->name('ventas.crear');
            Route::get('ventas/{venta}', [VentasVentaController::class, 'show'])->name('ventas.show');
            Route::post('ventas', [VentasVentaController::class, 'store'])->name('ventas.store');

            Route::get('productos', [VentasProductoController::class, 'index'])->name('productos.index');
            Route::get('productos/movimientos', [VentasProductoController::class, 'movimientos'])->name('productos.movimientos');
            Route::get('productos/{producto}', [VentasProductoController::class, 'show'])->name('productos.show');

            Route::get('compradores', [VentasCompradorController::class, 'index'])->name('compradores.index');
            Route::post('compradores', [VentasCompradorController::class, 'store'])->name('compradores.store');
        });

        Route::middleware(['active', 'role:PROVEEDOR'])->prefix('proveedor')->name('api.proveedor.')->group(function () {
            Route::get('dashboard', ProveedorDashboardController::class)->name('dashboard');

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

            Route::get('pagos', [ProveedorPagoController::class, 'index'])->name('pagos.index');
            Route::get('pagos/{pago}', [ProveedorPagoController::class, 'show'])->name('pagos.show');

            Route::get('comunicados', [ProveedorComunicadoController::class, 'index'])->name('comunicados.index');
            Route::get('comunicados/{comunicado}', [ProveedorComunicadoController::class, 'show'])->name('comunicados.show');
        });

        // Notificaciones: pertenecen al usuario autenticado, no a un área en
        // particular, compartidas por los 6 roles (igual que routes/web.php).
        Route::get('notificaciones', [NotificacionController::class, 'index'])->name('api.notificaciones.index');
        Route::patch('notificaciones/{notificacion}/leida', [NotificacionController::class, 'marcarComoLeida'])->name('api.notificaciones.marcar-leida');
    });
});
