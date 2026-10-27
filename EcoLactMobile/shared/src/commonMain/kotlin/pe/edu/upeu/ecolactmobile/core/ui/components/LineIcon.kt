package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.size
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp

/** Íconos de trazo simple, monocromos. El proyecto no incluye material-icons, así que se dibujan aquí. */
enum class LineIconType { Menu, Back, Home, Entregas, Pagos, Calidad, Perfil, Logout }

@Composable
fun LineIcon(type: LineIconType, tint: Color, modifier: Modifier = Modifier, size: Dp = 22.dp) {
    Canvas(modifier = modifier.size(size)) {
        val stroke = Stroke(width = 1.8.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round)
        // Coordenadas en fracciones (0..1) del tamaño del ícono.
        fun p(x: Float, y: Float) = Offset(x * this.size.width, y * this.size.height)
        fun line(x1: Float, y1: Float, x2: Float, y2: Float) =
            drawLine(tint, p(x1, y1), p(x2, y2), strokeWidth = stroke.width, cap = StrokeCap.Round)
        fun poly(vararg pts: Pair<Float, Float>) {
            val path = Path().apply {
                moveTo(p(pts[0].first, pts[0].second).x, p(pts[0].first, pts[0].second).y)
                pts.drop(1).forEach { (x, y) -> lineTo(p(x, y).x, p(x, y).y) }
            }
            drawPath(path, tint, style = stroke)
        }

        when (type) {
            LineIconType.Menu -> {
                line(0.18f, 0.28f, 0.82f, 0.28f)
                line(0.18f, 0.50f, 0.82f, 0.50f)
                line(0.18f, 0.72f, 0.82f, 0.72f)
            }
            LineIconType.Back -> {
                line(0.80f, 0.50f, 0.22f, 0.50f)
                poly(0.45f to 0.25f, 0.20f to 0.50f, 0.45f to 0.75f)
            }
            LineIconType.Home -> {
                poly(0.14f to 0.48f, 0.50f to 0.18f, 0.86f to 0.48f)
                poly(0.25f to 0.40f, 0.25f to 0.82f, 0.75f to 0.82f, 0.75f to 0.40f)
                poly(0.43f to 0.82f, 0.43f to 0.62f, 0.57f to 0.62f, 0.57f to 0.82f)
            }
            LineIconType.Entregas -> { // porongo de leche
                roundRect(tint, 0.28f, 0.36f, 0.72f, 0.85f, stroke)
                poly(0.38f to 0.36f, 0.38f to 0.22f, 0.62f to 0.22f, 0.62f to 0.36f)
                line(0.33f, 0.18f, 0.67f, 0.18f)
            }
            LineIconType.Pagos -> { // billete
                roundRect(tint, 0.12f, 0.28f, 0.88f, 0.72f, stroke)
                drawCircle(tint, radius = 0.10f * this.size.width, center = p(0.5f, 0.5f), style = stroke)
            }
            LineIconType.Calidad -> { // matraz
                poly(0.41f to 0.16f, 0.41f to 0.42f, 0.20f to 0.84f, 0.80f to 0.84f, 0.59f to 0.42f, 0.59f to 0.16f)
                line(0.34f, 0.16f, 0.66f, 0.16f)
                line(0.30f, 0.64f, 0.70f, 0.64f)
            }
            LineIconType.Perfil -> {
                drawCircle(tint, radius = 0.15f * this.size.width, center = p(0.5f, 0.35f), style = stroke)
                drawArc(
                    tint, startAngle = 180f, sweepAngle = 180f, useCenter = false,
                    topLeft = p(0.20f, 0.60f), size = Size(0.60f * this.size.width, 0.50f * this.size.height),
                    style = stroke,
                )
            }
            LineIconType.Logout -> {
                poly(0.55f to 0.20f, 0.20f to 0.20f, 0.20f to 0.80f, 0.55f to 0.80f)
                line(0.40f, 0.50f, 0.86f, 0.50f)
                poly(0.72f to 0.36f, 0.86f to 0.50f, 0.72f to 0.64f)
            }
        }
    }
}

private fun DrawScope.roundRect(color: Color, l: Float, t: Float, r: Float, b: Float, stroke: Stroke) {
    drawRoundRect(
        color = color,
        topLeft = Offset(l * size.width, t * size.height),
        size = Size((r - l) * size.width, (b - t) * size.height),
        cornerRadius = CornerRadius(0.08f * size.width),
        style = stroke,
    )
}
