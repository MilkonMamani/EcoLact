package pe.edu.upeu.ecolactmobile.core.network

import io.ktor.client.engine.HttpClientEngineFactory

expect fun platformHttpClientEngine(): HttpClientEngineFactory<*>
