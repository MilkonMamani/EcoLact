package pe.edu.upeu.ecolactmobile

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.session.Role
import pe.edu.upeu.ecolactmobile.core.session.UserSession
import pe.edu.upeu.ecolactmobile.core.ui.state.rememberScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactTheme
import pe.edu.upeu.ecolactmobile.di.AppContainer
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorApp
import pe.edu.upeu.ecolactmobile.feature.auth.LoginScreen
import pe.edu.upeu.ecolactmobile.feature.auth.LoginViewModel
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadApp
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaApp
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorApp

@Composable
fun App(container: AppContainer) {
    EcoLactTheme {
        val session by container.sessionManager.session.collectAsState()

        if (session == null) {
            val loginViewModel = rememberScreenModel { LoginViewModel(container.authRepository) }
            LoginScreen(
                viewModel = loginViewModel,
                onLoginSuccess = { /* session ya se actualizó en SessionManager; la UI reacciona sola */ },
            )
        } else {
            when (session!!.role) {
                Role.PROVEEDOR -> ProveedorApp(container)
                Role.ACOPIADOR -> AcopiadorApp(container, session!!)
                Role.CALIDAD -> CalidadApp(container, session!!)
                Role.ENCARGADO_PLANTA -> PlantaApp(container, session!!)
                // ADMINISTRADOR y VENTAS se gestionan desde el sistema web (decisión Etapa 2:
                // la app móvil se enfoca en Acopio, Calidad y Producción+Insumos — un shell
                // completo de Admin/Ventas replicaría el dashboard web, contra lo que pide el
                // enunciado de "app más ligera").
                Role.ADMINISTRADOR, Role.VENTAS -> RolNoDisponibleEnMovil(session!!, container)
            }
        }
    }
}

@Composable
private fun RolNoDisponibleEnMovil(session: UserSession, container: AppContainer) {
    val scope = rememberCoroutineScope()
    Column(
        modifier = Modifier.fillMaxSize().padding(24.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp, Alignment.CenterVertically),
    ) {
        Text("Este rol (${session.role.name.lowercase()}) se gestiona desde el sistema web, no desde la app móvil.", style = MaterialTheme.typography.bodyLarge)
        Button(onClick = { scope.launch { container.authRepository.logout() } }) {
            Text("Cerrar sesión")
        }
    }
}
