package pe.edu.upeu.ecolactmobile

import android.app.Application
import pe.edu.upeu.ecolactmobile.core.database.DatabaseDriverFactory
import pe.edu.upeu.ecolactmobile.core.sync.ConnectivityObserver
import pe.edu.upeu.ecolactmobile.di.AppContainer

class EcoLactApplication : Application() {
    val appContainer: AppContainer by lazy {
        AppContainer(
            databaseDriverFactory = DatabaseDriverFactory(this),
            connectivityObserver = ConnectivityObserver(this),
        )
    }
}
