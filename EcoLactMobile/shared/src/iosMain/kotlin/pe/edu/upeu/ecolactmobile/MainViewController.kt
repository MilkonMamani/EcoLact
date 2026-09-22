package pe.edu.upeu.ecolactmobile

import androidx.compose.ui.window.ComposeUIViewController
import pe.edu.upeu.ecolactmobile.core.database.DatabaseDriverFactory
import pe.edu.upeu.ecolactmobile.core.sync.ConnectivityObserver
import pe.edu.upeu.ecolactmobile.di.AppContainer

private val appContainer by lazy {
    AppContainer(
        databaseDriverFactory = DatabaseDriverFactory(),
        connectivityObserver = ConnectivityObserver(),
    )
}

fun MainViewController() = ComposeUIViewController { App(appContainer) }
