package pe.edu.upeu.ecolactmobile.feature.auth

import io.ktor.client.HttpClient
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.http.ContentType
import io.ktor.http.contentType
import pe.edu.upeu.ecolactmobile.core.network.requireSuccess
import pe.edu.upeu.ecolactmobile.core.network.toEnvelopeData

class AuthApi(private val httpClient: HttpClient) {

    suspend fun login(username: String, password: String): LoginResponse {
        val response = httpClient.post("login") {
            contentType(ContentType.Application.Json)
            setBody(LoginRequestBody(username, password))
        }
        return response.toEnvelopeData()
    }

    suspend fun logout() {
        httpClient.post("logout").requireSuccess()
    }
}
