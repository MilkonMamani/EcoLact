package pe.edu.upeu.ecolactmobile.feature.calidad

import kotlin.io.encoding.Base64
import kotlin.io.encoding.ExperimentalEncodingApi

@OptIn(ExperimentalEncodingApi::class)
object Base64Util {
    fun encode(bytes: ByteArray): String = Base64.Default.encode(bytes)
    fun decode(text: String): ByteArray = Base64.Default.decode(text)
}
