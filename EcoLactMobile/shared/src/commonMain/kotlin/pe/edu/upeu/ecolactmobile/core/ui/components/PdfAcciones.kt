package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.runtime.Composable

/** Las dos acciones sobre un PDF recibido del servidor: guardarlo en Descargas o enviarlo con el selector del sistema. */
interface PdfAcciones {
    fun descargar(bytes: ByteArray, nombre: String)
    /** Adjunta el PDF (no solo texto); [texto] acompaña al archivo en apps que lo admiten. */
    fun compartir(bytes: ByteArray, nombre: String, texto: String)
}

@Composable
expect fun rememberPdfAcciones(): PdfAcciones
