package pe.edu.upeu.ecolactmobile.core.network

import io.ktor.client.HttpClient
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.client.plugins.defaultRequest
import io.ktor.client.plugins.logging.LogLevel
import io.ktor.client.plugins.logging.Logging
import io.ktor.client.request.header
import io.ktor.client.request.url
import io.ktor.serialization.kotlinx.json.json
import pe.edu.upeu.ecolactmobile.core.session.SessionManager

object HttpClientFactory {

    fun create(sessionManager: SessionManager): HttpClient {
        return HttpClient(platformHttpClientEngine()) {
            expectSuccess = false

            install(ContentNegotiation) {
                json(ApiJson)
            }

            install(Logging) {
                level = LogLevel.INFO
            }

            defaultRequest {
                url(ApiConfig.baseUrl)
                sessionManager.currentToken()?.let { token ->
                    header("Authorization", "Bearer $token")
                }
            }
        }
    }
}
