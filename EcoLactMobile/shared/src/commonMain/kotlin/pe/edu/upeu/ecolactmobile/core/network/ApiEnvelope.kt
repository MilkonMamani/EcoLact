package pe.edu.upeu.ecolactmobile.core.network

import kotlinx.serialization.Serializable

@Serializable
data class ApiEnvelope<T>(
    val data: T,
    val meta: ApiMeta? = null,
)

@Serializable
data class ApiMeta(
    val current_page: Int? = null,
    val per_page: Int? = null,
    val total: Int? = null,
    val last_page: Int? = null,
)

@Serializable
data class ApiMessageResponse(
    val message: String? = null,
)
