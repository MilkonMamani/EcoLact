package pe.edu.upeu.ecolactmobile.di

import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.SupervisorJob
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.database.AnalisisCalidadLocalStore
import pe.edu.upeu.ecolactmobile.core.database.ComunicadoLocalStore
import pe.edu.upeu.ecolactmobile.core.database.DatabaseDriverFactory
import pe.edu.upeu.ecolactmobile.core.database.EntregaLocalStore
import pe.edu.upeu.ecolactmobile.core.database.InsumoLocalStore
import pe.edu.upeu.ecolactmobile.core.database.InsumoMovimientoLocalStore
import pe.edu.upeu.ecolactmobile.core.database.LoteProduccionLocalStore
import pe.edu.upeu.ecolactmobile.core.database.NotificacionLocalStore
import pe.edu.upeu.ecolactmobile.core.database.ProductoLocalStore
import pe.edu.upeu.ecolactmobile.core.database.ProveedorLocalStore
import pe.edu.upeu.ecolactmobile.core.database.createDatabase
import pe.edu.upeu.ecolactmobile.core.network.HttpClientFactory
import pe.edu.upeu.ecolactmobile.core.session.SessionManager
import pe.edu.upeu.ecolactmobile.core.sync.ConnectivityObserver
import pe.edu.upeu.ecolactmobile.core.sync.SyncManager
import pe.edu.upeu.ecolactmobile.core.sync.SyncQueueRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorApi
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorAcopioParadaSyncHandler
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorEntregaSyncHandler
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorIniciarRecorridoSyncHandler
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorLeerComunicadoSyncHandler
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorNoEntregoSyncHandler
import pe.edu.upeu.ecolactmobile.feature.auth.AuthApi
import pe.edu.upeu.ecolactmobile.feature.auth.AuthRepository
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadAnalisisSyncHandler
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadApi
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadCapturaStore
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadEntregaMuestreadaSyncHandler
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadSeleccionarMuestraSyncHandler
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadSubirTicketSyncHandler
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionLeidaSyncHandler
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesApi
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaApi
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaCompraStore
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaFinalizarProduccionSyncHandler
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaIniciarProduccionSyncHandler
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRegistrarCompraSyncHandler
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaSubirBoletaSyncHandler
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorApi
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorLeerComunicadoSyncHandler

/**
 * Contenedor manual de dependencias (sin librería de DI): dado el tamaño del proyecto, un
 * contenedor único construido una vez al arrancar la app es suficiente y evita introducir una
 * dependencia adicional (Koin/Hilt) que el prompt inicial no pidió. `DatabaseDriverFactory` y
 * `ConnectivityObserver` los construye cada plataforma (necesitan `Context` en Android) y se
 * inyectan ya listos aquí.
 */
class AppContainer(
    databaseDriverFactory: DatabaseDriverFactory,
    connectivityObserver: ConnectivityObserver,
) {
    /** Vive tanto como la app: usado para procesos de fondo que no pertenecen a ninguna pantalla, como el auto-sync. */
    private val appScope = CoroutineScope(SupervisorJob())

    val database = createDatabase(databaseDriverFactory)
    val sessionManager = SessionManager(database)
    val httpClient = HttpClientFactory.create(sessionManager)

    val cacheRepository = CacheRepository(database)
    val syncQueueRepository = SyncQueueRepository(database)

    /** Etapa 3/4: reemplazan `cacheRepository` para las entidades que necesitan consulta/búsqueda offline real (ver los `*LocalStore.kt` en core/database). Se consumen desde cada feature Repository en las Etapas 6-8. */
    val proveedorLocalStore = ProveedorLocalStore(database)
    val entregaLocalStore = EntregaLocalStore(database)
    val analisisCalidadLocalStore = AnalisisCalidadLocalStore(database)
    val insumoLocalStore = InsumoLocalStore(database)
    val insumoMovimientoLocalStore = InsumoMovimientoLocalStore(database)
    val productoLocalStore = ProductoLocalStore(database)
    val loteProduccionLocalStore = LoteProduccionLocalStore(database)
    val comunicadoLocalStore = ComunicadoLocalStore(database)
    val notificacionLocalStore = NotificacionLocalStore(database)

    /** Foto del ticket + OCR propuesto del analista, hasta confirmar el análisis (ver CalidadCaptura.sq). */
    val calidadCapturaStore = CalidadCapturaStore(database)

    /** Compras de insumos del encargado de planta en preparación (foto de boleta, OCR, revisión). */
    val plantaCompraStore = PlantaCompraStore(database)
    val connectivityObserver = connectivityObserver
    val syncManager = SyncManager(syncQueueRepository, connectivityObserver)

    val authApi = AuthApi(httpClient)
    val authRepository = AuthRepository(authApi, sessionManager)

    /** Compartidos entre AppContainer (registro de handlers de sync) y las shells de cada rol (repositorios). */
    val acopiadorApi = AcopiadorApi(httpClient)
    val calidadApi = CalidadApi(httpClient)
    val plantaApi = PlantaApi(httpClient)
    val proveedorApi = ProveedorApi(httpClient)
    val notificacionesApi = NotificacionesApi(httpClient)
    val ventasApi = pe.edu.upeu.ecolactmobile.feature.ventas.VentasApi(httpClient)

    init {
        sessionManager.restore()
        // Recover an interrupted sale upload before starting any sync coroutine.
        syncQueueRepository.byOperationType(pe.edu.upeu.ecolactmobile.feature.ventas.VENTAS_REGISTRAR)
            .filter { it.status == "SYNCING" && it.server_id == null }
            .forEach { syncQueueRepository.markPending(it.id) }
        syncManager.register(pe.edu.upeu.ecolactmobile.feature.ventas.VentasSyncHandler(ventasApi, cacheRepository) { sessionManager.session.value?.userId })
        syncManager.register(AcopiadorEntregaSyncHandler(acopiadorApi))
        // Recorrido: la cola respeta el orden de creación (iniciar → acopios / "no entregó").
        syncManager.register(AcopiadorIniciarRecorridoSyncHandler(acopiadorApi))
        syncManager.register(AcopiadorAcopioParadaSyncHandler(acopiadorApi))
        syncManager.register(AcopiadorNoEntregoSyncHandler(acopiadorApi))
        syncManager.register(AcopiadorLeerComunicadoSyncHandler(acopiadorApi))
        // Orden de la cola = orden de creación: la selección de una muestra siempre se envía antes que su foto y su análisis.
        syncManager.register(CalidadSeleccionarMuestraSyncHandler(calidadApi))
        syncManager.register(CalidadSubirTicketSyncHandler(calidadApi, calidadCapturaStore))
        syncManager.register(CalidadAnalisisSyncHandler(calidadApi, calidadCapturaStore))
        syncManager.register(CalidadEntregaMuestreadaSyncHandler(calidadApi))
        syncManager.register(PlantaIniciarProduccionSyncHandler(plantaApi))
        syncManager.register(PlantaFinalizarProduccionSyncHandler(plantaApi))
        // La foto de una boleta se sube (OCR) antes que la compra que la usa: la cola respeta el orden.
        syncManager.register(PlantaSubirBoletaSyncHandler(plantaApi, plantaCompraStore))
        syncManager.register(PlantaRegistrarCompraSyncHandler(plantaApi, plantaCompraStore))
        syncManager.register(NotificacionLeidaSyncHandler(notificacionesApi))
        syncManager.register(ProveedorLeerComunicadoSyncHandler(proveedorApi))
        syncManager.startAutoSync(appScope)
    }
}
