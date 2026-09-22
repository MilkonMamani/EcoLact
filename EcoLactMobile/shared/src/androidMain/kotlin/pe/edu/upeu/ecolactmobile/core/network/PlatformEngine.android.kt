package pe.edu.upeu.ecolactmobile.core.network

import io.ktor.client.engine.HttpClientEngineFactory
import io.ktor.client.engine.okhttp.OkHttp

actual fun platformHttpClientEngine(): HttpClientEngineFactory<*> = OkHttp
