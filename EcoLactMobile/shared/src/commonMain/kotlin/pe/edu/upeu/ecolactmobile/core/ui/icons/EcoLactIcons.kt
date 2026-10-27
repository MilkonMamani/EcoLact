package pe.edu.upeu.ecolactmobile.core.ui.icons

import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathFillType
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.graphics.vector.PathBuilder
import androidx.compose.ui.graphics.vector.addPathNodes
import androidx.compose.ui.graphics.vector.path
import androidx.compose.ui.unit.dp

/*
 * Íconos Material (variante Outlined) usados por la app. El path data se copió tal cual de
 * androidx.compose.material:material-icons-core/-extended 1.7.3 (Copyright The Android Open
 * Source Project, Apache License 2.0): se incluyen solo los que se usan, en vez de depender de
 * material-icons-extended (decenas de miles de íconos, congelado en 1.7.x).
 * Archivo generado; para agregar uno, copiar su bloque desde esa librería.
 */
object EcoLactIcons {
    val Menu: ImageVector by lazy {
        icon("Menu", autoMirror = false) {
            materialPath {
                moveTo(3.0f, 18.0f)
                horizontalLineToRelative(18.0f)
                verticalLineToRelative(-2.0f)
                lineTo(3.0f, 16.0f)
                verticalLineToRelative(2.0f)
                close()
                moveTo(3.0f, 13.0f)
                horizontalLineToRelative(18.0f)
                verticalLineToRelative(-2.0f)
                lineTo(3.0f, 11.0f)
                verticalLineToRelative(2.0f)
                close()
                moveTo(3.0f, 6.0f)
                verticalLineToRelative(2.0f)
                horizontalLineToRelative(18.0f)
                lineTo(21.0f, 6.0f)
                lineTo(3.0f, 6.0f)
                close()
            }
        }
    }

    val ArrowBack: ImageVector by lazy {
        icon("ArrowBack", autoMirror = true) {
            materialPath {
                moveTo(20.0f, 11.0f)
                horizontalLineTo(7.83f)
                lineToRelative(5.59f, -5.59f)
                lineTo(12.0f, 4.0f)
                lineToRelative(-8.0f, 8.0f)
                lineToRelative(8.0f, 8.0f)
                lineToRelative(1.41f, -1.41f)
                lineTo(7.83f, 13.0f)
                horizontalLineTo(20.0f)
                verticalLineToRelative(-2.0f)
                close()
            }
        }
    }

    val Home: ImageVector by lazy {
        icon("Home", autoMirror = false) {
            materialPath {
                moveTo(12.0f, 5.69f)
                lineToRelative(5.0f, 4.5f)
                verticalLineTo(18.0f)
                horizontalLineToRelative(-2.0f)
                verticalLineToRelative(-6.0f)
                horizontalLineTo(9.0f)
                verticalLineToRelative(6.0f)
                horizontalLineTo(7.0f)
                verticalLineToRelative(-7.81f)
                lineToRelative(5.0f, -4.5f)
                moveTo(12.0f, 3.0f)
                lineTo(2.0f, 12.0f)
                horizontalLineToRelative(3.0f)
                verticalLineToRelative(8.0f)
                horizontalLineToRelative(6.0f)
                verticalLineToRelative(-6.0f)
                horizontalLineToRelative(2.0f)
                verticalLineToRelative(6.0f)
                horizontalLineToRelative(6.0f)
                verticalLineToRelative(-8.0f)
                horizontalLineToRelative(3.0f)
                lineTo(12.0f, 3.0f)
                close()
            }
        }
    }

    val LocalShipping: ImageVector by lazy {
        icon("LocalShipping", autoMirror = false) {
            materialPath {
                moveTo(20.0f, 8.0f)
                horizontalLineToRelative(-3.0f)
                lineTo(17.0f, 4.0f)
                lineTo(3.0f, 4.0f)
                curveToRelative(-1.1f, 0.0f, -2.0f, 0.9f, -2.0f, 2.0f)
                verticalLineToRelative(11.0f)
                horizontalLineToRelative(2.0f)
                curveToRelative(0.0f, 1.66f, 1.34f, 3.0f, 3.0f, 3.0f)
                reflectiveCurveToRelative(3.0f, -1.34f, 3.0f, -3.0f)
                horizontalLineToRelative(6.0f)
                curveToRelative(0.0f, 1.66f, 1.34f, 3.0f, 3.0f, 3.0f)
                reflectiveCurveToRelative(3.0f, -1.34f, 3.0f, -3.0f)
                horizontalLineToRelative(2.0f)
                verticalLineToRelative(-5.0f)
                lineToRelative(-3.0f, -4.0f)
                close()
                moveTo(19.5f, 9.5f)
                lineToRelative(1.96f, 2.5f)
                lineTo(17.0f, 12.0f)
                lineTo(17.0f, 9.5f)
                horizontalLineToRelative(2.5f)
                close()
                moveTo(6.0f, 18.0f)
                curveToRelative(-0.55f, 0.0f, -1.0f, -0.45f, -1.0f, -1.0f)
                reflectiveCurveToRelative(0.45f, -1.0f, 1.0f, -1.0f)
                reflectiveCurveToRelative(1.0f, 0.45f, 1.0f, 1.0f)
                reflectiveCurveToRelative(-0.45f, 1.0f, -1.0f, 1.0f)
                close()
                moveTo(8.22f, 15.0f)
                curveToRelative(-0.55f, -0.61f, -1.33f, -1.0f, -2.22f, -1.0f)
                reflectiveCurveToRelative(-1.67f, 0.39f, -2.22f, 1.0f)
                lineTo(3.0f, 15.0f)
                lineTo(3.0f, 6.0f)
                horizontalLineToRelative(12.0f)
                verticalLineToRelative(9.0f)
                lineTo(8.22f, 15.0f)
                close()
                moveTo(18.0f, 18.0f)
                curveToRelative(-0.55f, 0.0f, -1.0f, -0.45f, -1.0f, -1.0f)
                reflectiveCurveToRelative(0.45f, -1.0f, 1.0f, -1.0f)
                reflectiveCurveToRelative(1.0f, 0.45f, 1.0f, 1.0f)
                reflectiveCurveToRelative(-0.45f, 1.0f, -1.0f, 1.0f)
                close()
            }
        }
    }

    val Payments: ImageVector by lazy {
        icon("Payments", autoMirror = false) {
            materialPath {
                moveTo(19.0f, 14.0f)
                verticalLineTo(6.0f)
                curveToRelative(0.0f, -1.1f, -0.9f, -2.0f, -2.0f, -2.0f)
                horizontalLineTo(3.0f)
                curveTo(1.9f, 4.0f, 1.0f, 4.9f, 1.0f, 6.0f)
                verticalLineToRelative(8.0f)
                curveToRelative(0.0f, 1.1f, 0.9f, 2.0f, 2.0f, 2.0f)
                horizontalLineToRelative(14.0f)
                curveTo(18.1f, 16.0f, 19.0f, 15.1f, 19.0f, 14.0f)
                close()
                moveTo(17.0f, 14.0f)
                horizontalLineTo(3.0f)
                verticalLineTo(6.0f)
                horizontalLineToRelative(14.0f)
                verticalLineTo(14.0f)
                close()
                moveTo(10.0f, 7.0f)
                curveToRelative(-1.66f, 0.0f, -3.0f, 1.34f, -3.0f, 3.0f)
                reflectiveCurveToRelative(1.34f, 3.0f, 3.0f, 3.0f)
                reflectiveCurveToRelative(3.0f, -1.34f, 3.0f, -3.0f)
                reflectiveCurveTo(11.66f, 7.0f, 10.0f, 7.0f)
                close()
                moveTo(23.0f, 7.0f)
                verticalLineToRelative(11.0f)
                curveToRelative(0.0f, 1.1f, -0.9f, 2.0f, -2.0f, 2.0f)
                horizontalLineTo(4.0f)
                curveToRelative(0.0f, -1.0f, 0.0f, -0.9f, 0.0f, -2.0f)
                horizontalLineToRelative(17.0f)
                verticalLineTo(7.0f)
                curveTo(22.1f, 7.0f, 22.0f, 7.0f, 23.0f, 7.0f)
                close()
            }
        }
    }

    val Science: ImageVector by lazy {
        icon("Science", autoMirror = false) {
            materialPath {
                moveTo(13.0f, 11.33f)
                lineTo(18.0f, 18.0f)
                horizontalLineTo(6.0f)
                lineToRelative(5.0f, -6.67f)
                verticalLineTo(6.0f)
                horizontalLineToRelative(2.0f)
                moveTo(15.96f, 4.0f)
                horizontalLineTo(8.04f)
                curveTo(7.62f, 4.0f, 7.39f, 4.48f, 7.65f, 4.81f)
                lineTo(9.0f, 6.5f)
                verticalLineToRelative(4.17f)
                lineTo(3.2f, 18.4f)
                curveTo(2.71f, 19.06f, 3.18f, 20.0f, 4.0f, 20.0f)
                horizontalLineToRelative(16.0f)
                curveToRelative(0.82f, 0.0f, 1.29f, -0.94f, 0.8f, -1.6f)
                lineTo(15.0f, 10.67f)
                verticalLineTo(6.5f)
                lineToRelative(1.35f, -1.69f)
                curveTo(16.61f, 4.48f, 16.38f, 4.0f, 15.96f, 4.0f)
                lineTo(15.96f, 4.0f)
                close()
            }
        }
    }

    val ShoppingBag: ImageVector by lazy {
        icon("ShoppingBag", autoMirror = false) {
            materialPath {
                moveTo(18.0f, 6.0f)
                horizontalLineToRelative(-2.0f)
                curveToRelative(0.0f, -2.21f, -1.79f, -4.0f, -4.0f, -4.0f)
                reflectiveCurveTo(8.0f, 3.79f, 8.0f, 6.0f)
                horizontalLineTo(6.0f)
                curveTo(4.9f, 6.0f, 4.0f, 6.9f, 4.0f, 8.0f)
                verticalLineToRelative(12.0f)
                curveToRelative(0.0f, 1.1f, 0.9f, 2.0f, 2.0f, 2.0f)
                horizontalLineToRelative(12.0f)
                curveToRelative(1.1f, 0.0f, 2.0f, -0.9f, 2.0f, -2.0f)
                verticalLineTo(8.0f)
                curveTo(20.0f, 6.9f, 19.1f, 6.0f, 18.0f, 6.0f)
                close()
                moveTo(12.0f, 4.0f)
                curveToRelative(1.1f, 0.0f, 2.0f, 0.9f, 2.0f, 2.0f)
                horizontalLineToRelative(-4.0f)
                curveTo(10.0f, 4.9f, 10.9f, 4.0f, 12.0f, 4.0f)
                close()
                moveTo(18.0f, 20.0f)
                horizontalLineTo(6.0f)
                verticalLineTo(8.0f)
                horizontalLineToRelative(2.0f)
                verticalLineToRelative(2.0f)
                curveToRelative(0.0f, 0.55f, 0.45f, 1.0f, 1.0f, 1.0f)
                reflectiveCurveToRelative(1.0f, -0.45f, 1.0f, -1.0f)
                verticalLineTo(8.0f)
                horizontalLineToRelative(4.0f)
                verticalLineToRelative(2.0f)
                curveToRelative(0.0f, 0.55f, 0.45f, 1.0f, 1.0f, 1.0f)
                reflectiveCurveToRelative(1.0f, -0.45f, 1.0f, -1.0f)
                verticalLineTo(8.0f)
                horizontalLineToRelative(2.0f)
                verticalLineTo(20.0f)
                close()
            }
        }
    }

    val Campaign: ImageVector by lazy {
        icon("Campaign", autoMirror = false) {
            materialPath {
                moveTo(18.0f, 11.0f)
                curveToRelative(0.0f, 0.67f, 0.0f, 1.33f, 0.0f, 2.0f)
                curveToRelative(1.2f, 0.0f, 2.76f, 0.0f, 4.0f, 0.0f)
                curveToRelative(0.0f, -0.67f, 0.0f, -1.33f, 0.0f, -2.0f)
                curveTo(20.76f, 11.0f, 19.2f, 11.0f, 18.0f, 11.0f)
                close()
            }
            materialPath {
                moveTo(16.0f, 17.61f)
                curveToRelative(0.96f, 0.71f, 2.21f, 1.65f, 3.2f, 2.39f)
                curveToRelative(0.4f, -0.53f, 0.8f, -1.07f, 1.2f, -1.6f)
                curveToRelative(-0.99f, -0.74f, -2.24f, -1.68f, -3.2f, -2.4f)
                curveTo(16.8f, 16.54f, 16.4f, 17.08f, 16.0f, 17.61f)
                close()
            }
            materialPath {
                moveTo(20.4f, 5.6f)
                curveTo(20.0f, 5.07f, 19.6f, 4.53f, 19.2f, 4.0f)
                curveToRelative(-0.99f, 0.74f, -2.24f, 1.68f, -3.2f, 2.4f)
                curveToRelative(0.4f, 0.53f, 0.8f, 1.07f, 1.2f, 1.6f)
                curveTo(18.16f, 7.28f, 19.41f, 6.35f, 20.4f, 5.6f)
                close()
            }
            materialPath {
                moveTo(4.0f, 9.0f)
                curveToRelative(-1.1f, 0.0f, -2.0f, 0.9f, -2.0f, 2.0f)
                verticalLineToRelative(2.0f)
                curveToRelative(0.0f, 1.1f, 0.9f, 2.0f, 2.0f, 2.0f)
                horizontalLineToRelative(1.0f)
                verticalLineToRelative(4.0f)
                horizontalLineToRelative(2.0f)
                verticalLineToRelative(-4.0f)
                horizontalLineToRelative(1.0f)
                lineToRelative(5.0f, 3.0f)
                verticalLineTo(6.0f)
                lineTo(8.0f, 9.0f)
                horizontalLineTo(4.0f)
                close()
                moveTo(9.03f, 10.71f)
                lineTo(11.0f, 9.53f)
                verticalLineToRelative(4.94f)
                lineToRelative(-1.97f, -1.18f)
                lineTo(8.55f, 13.0f)
                horizontalLineTo(8.0f)
                horizontalLineTo(4.0f)
                verticalLineToRelative(-2.0f)
                horizontalLineToRelative(4.0f)
                horizontalLineToRelative(0.55f)
                lineTo(9.03f, 10.71f)
                close()
            }
            materialPath {
                moveTo(15.5f, 12.0f)
                curveToRelative(0.0f, -1.33f, -0.58f, -2.53f, -1.5f, -3.35f)
                verticalLineToRelative(6.69f)
                curveTo(14.92f, 14.53f, 15.5f, 13.33f, 15.5f, 12.0f)
                close()
            }
        }
    }

    val Notifications: ImageVector by lazy {
        icon("Notifications", autoMirror = false) {
            materialPath {
                moveTo(12.0f, 22.0f)
                curveToRelative(1.1f, 0.0f, 2.0f, -0.9f, 2.0f, -2.0f)
                horizontalLineToRelative(-4.0f)
                curveToRelative(0.0f, 1.1f, 0.9f, 2.0f, 2.0f, 2.0f)
                close()
                moveTo(18.0f, 16.0f)
                verticalLineToRelative(-5.0f)
                curveToRelative(0.0f, -3.07f, -1.63f, -5.64f, -4.5f, -6.32f)
                lineTo(13.5f, 4.0f)
                curveToRelative(0.0f, -0.83f, -0.67f, -1.5f, -1.5f, -1.5f)
                reflectiveCurveToRelative(-1.5f, 0.67f, -1.5f, 1.5f)
                verticalLineToRelative(0.68f)
                curveTo(7.64f, 5.36f, 6.0f, 7.92f, 6.0f, 11.0f)
                verticalLineToRelative(5.0f)
                lineToRelative(-2.0f, 2.0f)
                verticalLineToRelative(1.0f)
                horizontalLineToRelative(16.0f)
                verticalLineToRelative(-1.0f)
                lineToRelative(-2.0f, -2.0f)
                close()
                moveTo(16.0f, 17.0f)
                lineTo(8.0f, 17.0f)
                verticalLineToRelative(-6.0f)
                curveToRelative(0.0f, -2.48f, 1.51f, -4.5f, 4.0f, -4.5f)
                reflectiveCurveToRelative(4.0f, 2.02f, 4.0f, 4.5f)
                verticalLineToRelative(6.0f)
                close()
            }
        }
    }

    val Person: ImageVector by lazy {
        icon("Person", autoMirror = false) {
            materialPath {
                moveTo(12.0f, 6.0f)
                curveToRelative(1.1f, 0.0f, 2.0f, 0.9f, 2.0f, 2.0f)
                reflectiveCurveToRelative(-0.9f, 2.0f, -2.0f, 2.0f)
                reflectiveCurveToRelative(-2.0f, -0.9f, -2.0f, -2.0f)
                reflectiveCurveToRelative(0.9f, -2.0f, 2.0f, -2.0f)
                moveToRelative(0.0f, 10.0f)
                curveToRelative(2.7f, 0.0f, 5.8f, 1.29f, 6.0f, 2.0f)
                lineTo(6.0f, 18.0f)
                curveToRelative(0.23f, -0.72f, 3.31f, -2.0f, 6.0f, -2.0f)
                moveToRelative(0.0f, -12.0f)
                curveTo(9.79f, 4.0f, 8.0f, 5.79f, 8.0f, 8.0f)
                reflectiveCurveToRelative(1.79f, 4.0f, 4.0f, 4.0f)
                reflectiveCurveToRelative(4.0f, -1.79f, 4.0f, -4.0f)
                reflectiveCurveToRelative(-1.79f, -4.0f, -4.0f, -4.0f)
                close()
                moveTo(12.0f, 14.0f)
                curveToRelative(-2.67f, 0.0f, -8.0f, 1.34f, -8.0f, 4.0f)
                verticalLineToRelative(2.0f)
                horizontalLineToRelative(16.0f)
                verticalLineToRelative(-2.0f)
                curveToRelative(0.0f, -2.66f, -5.33f, -4.0f, -8.0f, -4.0f)
                close()
            }
        }
    }

    val Sync: ImageVector by lazy {
        icon("Sync", autoMirror = false) {
            materialPath {
                moveTo(12.0f, 4.0f)
                lineTo(12.0f, 1.0f)
                lineTo(8.0f, 5.0f)
                lineToRelative(4.0f, 4.0f)
                lineTo(12.0f, 6.0f)
                curveToRelative(3.31f, 0.0f, 6.0f, 2.69f, 6.0f, 6.0f)
                curveToRelative(0.0f, 1.01f, -0.25f, 1.97f, -0.7f, 2.8f)
                lineToRelative(1.46f, 1.46f)
                curveTo(19.54f, 15.03f, 20.0f, 13.57f, 20.0f, 12.0f)
                curveToRelative(0.0f, -4.42f, -3.58f, -8.0f, -8.0f, -8.0f)
                close()
                moveTo(12.0f, 18.0f)
                curveToRelative(-3.31f, 0.0f, -6.0f, -2.69f, -6.0f, -6.0f)
                curveToRelative(0.0f, -1.01f, 0.25f, -1.97f, 0.7f, -2.8f)
                lineTo(5.24f, 7.74f)
                curveTo(4.46f, 8.97f, 4.0f, 10.43f, 4.0f, 12.0f)
                curveToRelative(0.0f, 4.42f, 3.58f, 8.0f, 8.0f, 8.0f)
                verticalLineToRelative(3.0f)
                lineToRelative(4.0f, -4.0f)
                lineToRelative(-4.0f, -4.0f)
                verticalLineToRelative(3.0f)
                close()
            }
        }
    }

    val Logout: ImageVector by lazy {
        icon("Logout", autoMirror = true) {
            materialPath {
                moveTo(17.0f, 8.0f)
                lineToRelative(-1.41f, 1.41f)
                lineTo(17.17f, 11.0f)
                horizontalLineTo(9.0f)
                verticalLineToRelative(2.0f)
                horizontalLineToRelative(8.17f)
                lineToRelative(-1.58f, 1.58f)
                lineTo(17.0f, 16.0f)
                lineToRelative(4.0f, -4.0f)
                lineTo(17.0f, 8.0f)
                close()
                moveTo(5.0f, 5.0f)
                horizontalLineToRelative(7.0f)
                verticalLineTo(3.0f)
                horizontalLineTo(5.0f)
                curveTo(3.9f, 3.0f, 3.0f, 3.9f, 3.0f, 5.0f)
                verticalLineToRelative(14.0f)
                curveToRelative(0.0f, 1.1f, 0.9f, 2.0f, 2.0f, 2.0f)
                horizontalLineToRelative(7.0f)
                verticalLineToRelative(-2.0f)
                horizontalLineTo(5.0f)
                verticalLineTo(5.0f)
                close()
            }
        }
    }

    val ChevronRight: ImageVector by lazy {
        icon("ChevronRight", autoMirror = false) {
            materialPath {
                moveTo(10.0f, 6.0f)
                lineTo(8.59f, 7.41f)
                lineTo(13.17f, 12.0f)
                lineToRelative(-4.58f, 4.59f)
                lineTo(10.0f, 18.0f)
                lineToRelative(6.0f, -6.0f)
                lineToRelative(-6.0f, -6.0f)
                close()
            }
        }
    }

    val ArrowDropDown: ImageVector by lazy {
        icon("ArrowDropDown", autoMirror = false) {
            materialPath {
                moveTo(7.0f, 10.0f)
                lineToRelative(5.0f, 5.0f)
                lineToRelative(5.0f, -5.0f)
                horizontalLineTo(7.0f)
                close()
            }
        }
    }

    val CloudOff: ImageVector by lazy {
        icon("CloudOff", autoMirror = false) {
            materialPath {
                moveTo(24.0f, 15.0f)
                curveToRelative(0.0f, -2.64f, -2.05f, -4.78f, -4.65f, -4.96f)
                curveTo(18.67f, 6.59f, 15.64f, 4.0f, 12.0f, 4.0f)
                curveToRelative(-1.33f, 0.0f, -2.57f, 0.36f, -3.65f, 0.97f)
                lineToRelative(1.49f, 1.49f)
                curveTo(10.51f, 6.17f, 11.23f, 6.0f, 12.0f, 6.0f)
                curveToRelative(3.04f, 0.0f, 5.5f, 2.46f, 5.5f, 5.5f)
                verticalLineToRelative(0.5f)
                horizontalLineTo(19.0f)
                curveToRelative(1.66f, 0.0f, 3.0f, 1.34f, 3.0f, 3.0f)
                curveToRelative(0.0f, 0.99f, -0.48f, 1.85f, -1.21f, 2.4f)
                lineToRelative(1.41f, 1.41f)
                curveToRelative(1.09f, -0.92f, 1.8f, -2.27f, 1.8f, -3.81f)
                close()
                moveTo(4.41f, 3.86f)
                lineTo(3.0f, 5.27f)
                lineToRelative(2.77f, 2.77f)
                horizontalLineToRelative(-0.42f)
                curveTo(2.34f, 8.36f, 0.0f, 10.91f, 0.0f, 14.0f)
                curveToRelative(0.0f, 3.31f, 2.69f, 6.0f, 6.0f, 6.0f)
                horizontalLineToRelative(11.73f)
                lineToRelative(2.0f, 2.0f)
                lineToRelative(1.41f, -1.41f)
                lineTo(4.41f, 3.86f)
                close()
                moveTo(6.0f, 18.0f)
                curveToRelative(-2.21f, 0.0f, -4.0f, -1.79f, -4.0f, -4.0f)
                reflectiveCurveToRelative(1.79f, -4.0f, 4.0f, -4.0f)
                horizontalLineToRelative(1.73f)
                lineToRelative(8.0f, 8.0f)
                horizontalLineTo(6.0f)
                close()
            }
        }
    }

    // ---- Muestreo / análisis de calidad (Material Filled, path data en notación SVG) ----

    val History: ImageVector by lazy {
        svgIcon("History", "M13,3c-4.97,0 -9,4.03 -9,9L1,12l3.89,3.89 0.07,0.14L9,12L6,12c0,-3.87 3.13,-7 7,-7s7,3.13 7,7 -3.13,7 -7,7c-1.93,0 -3.68,-0.79 -4.94,-2.06l-1.42,1.42C8.27,19.99 10.51,21 13,21c4.97,0 9,-4.03 9,-9s-4.03,-9 -9,-9zM12,8v5l4.28,2.54 0.72,-1.21 -3.5,-2.08L13.5,8L12,8z")
    }

    val Assignment: ImageVector by lazy {
        svgIcon("Assignment", "M19,3h-4.18C14.4,1.84 13.3,1 12,1c-1.3,0 -2.4,0.84 -2.82,2L5,3c-1.1,0 -2,0.9 -2,2v14c0,1.1 0.9,2 2,2h14c1.1,0 2,-0.9 2,-2L21,5c0,-1.1 -0.9,-2 -2,-2zM12,3c0.55,0 1,0.45 1,1s-0.45,1 -1,1 -1,-0.45 -1,-1 0.45,-1 1,-1zM14,17L7,17v-2h7v2zM17,13L7,13v-2h10v2zM17,9L7,9L7,7h10v2z")
    }

    val People: ImageVector by lazy {
        svgIcon("People", "M16,11c1.66,0 2.99,-1.34 2.99,-3S17.66,5 16,5c-1.66,0 -3,1.34 -3,3s1.34,3 3,3zM8,11c1.66,0 2.99,-1.34 2.99,-3S9.66,5 8,5C6.34,5 5,6.34 5,8s1.34,3 3,3zM8,13c-2.33,0 -7,1.17 -7,3.5L1,19h14v-2.5c0,-2.33 -4.67,-3.5 -7,-3.5zM16,13c-0.29,0 -0.62,0.02 -0.97,0.05 1.16,0.84 1.97,1.97 1.97,3.45L17,19h6v-2.5c0,-2.33 -4.67,-3.5 -7,-3.5z")
    }

    val PhotoCamera: ImageVector by lazy {
        svgIcon("PhotoCamera", "M12,12m-3.2,0a3.2,3.2 0,1 1,6.4 0a3.2,3.2 0,1 1,-6.4 0M9,2L7.17,4L4,4c-1.1,0 -2,0.9 -2,2v12c0,1.1 0.9,2 2,2h16c1.1,0 2,-0.9 2,-2L22,6c0,-1.1 -0.9,-2 -2,-2h-3.17L15,2L9,2zM12,17c-2.76,0 -5,-2.24 -5,-5s2.24,-5 5,-5 5,2.24 5,5 -2.24,5 -5,5z")
    }

    val Edit: ImageVector by lazy {
        svgIcon("Edit", "M3,17.25L3,21h3.75L17.81,9.94l-3.75,-3.75L3,17.25zM20.71,7.04c0.39,-0.39 0.39,-1.02 0,-1.41l-2.34,-2.34c-0.39,-0.39 -1.02,-0.39 -1.41,0l-1.83,1.83 3.75,3.75 1.83,-1.83z")
    }

    val Search: ImageVector by lazy {
        svgIcon("Search", "M15.5,14h-0.79l-0.28,-0.27C15.41,12.59 16,11.11 16,9.5 16,5.91 13.09,3 9.5,3S3,5.91 3,9.5 5.91,16 9.5,16c1.61,0 3.09,-0.59 4.23,-1.57l0.27,0.28v0.79l5,4.99L20.49,19l-4.99,-5zM9.5,14C7.01,14 5,11.99 5,9.5S7.01,5 9.5,5 14,7.01 14,9.5 11.99,14 9.5,14z")
    }

    val Shuffle: ImageVector by lazy {
        svgIcon("Shuffle", "M10.59,9.17L5.41,4 4,5.41l5.17,5.17 1.42,-1.41zM14.5,4l2.04,2.04L4,18.59 5.41,20 17.96,7.46 20,9.5L20,4h-5.5zM14.83,13.41l-1.41,1.41 3.13,3.13L14.5,20L20,20v-5.5l-2.04,2.04 -3.13,-3.13z")
    }

    val QrCode: ImageVector by lazy {
        svgIcon("QrCode", "M3,11h8L11,3L3,3v8zM5,5h4v4L5,9L5,5zM3,21h8v-8L3,13v8zM5,15h4v4L5,19v-4zM13,3v8h8L21,3h-8zM19,9h-4L15,5h4v4zM19,19h2v2h-2zM13,13h2v2h-2zM15,15h2v2h-2zM13,17h2v2h-2zM15,19h2v2h-2zM17,17h2v2h-2zM17,13h2v2h-2zM19,15h2v2h-2z")
    }

    val CheckCircle: ImageVector by lazy {
        svgIcon("CheckCircle", "M12,2C6.48,2 2,6.48 2,12s4.48,10 10,10 10,-4.48 10,-10S17.52,2 12,2zM10,17l-5,-5 1.41,-1.41L10,14.17l7.59,-7.59L19,8l-9,9z")
    }

    val Close: ImageVector by lazy {
        svgIcon("Close", "M19,6.41L17.59,5 12,10.59 6.41,5 5,6.41 10.59,12 5,17.59 6.41,19 12,13.41 17.59,19 19,17.59 13.41,12z")
    }

    // ---- Acopiador (Material Filled, path data en notación SVG) ----

    val Add: ImageVector by lazy {
        svgIcon("Add", "M19,13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z")
    }

    val Route: ImageVector by lazy {
        svgIcon("Route", "M19,15.18V7c0,-2.21 -1.79,-4 -4,-4s-4,1.79 -4,4v10c0,1.1 -0.9,2 -2,2s-2,-0.9 -2,-2V8.82C8.16,8.4 9,7.3 9,6c0,-1.66 -1.34,-3 -3,-3S3,4.34 3,6c0,1.3 0.84,2.4 2,2.82V17c0,2.21 1.79,4 4,4s4,-1.79 4,-4V7c0,-1.1 0.9,-2 2,-2s2,0.9 2,2v8.18c-1.16,0.41 -2,1.51 -2,2.82c0,1.66 1.34,3 3,3s3,-1.34 3,-3C21,16.7 20.16,15.6 19,15.18z")
    }

    val Badge: ImageVector by lazy {
        svgIcon("Badge", "M20,7h-5V4c0,-1.1 -0.9,-2 -2,-2h-2C9.9,2 9,2.9 9,4v3H4C2.9,7 2,7.9 2,9v11c0,1.1 0.9,2 2,2h16c1.1,0 2,-0.9 2,-2V9C22,7.9 21.1,7 20,7zM9,12c0.83,0 1.5,0.67 1.5,1.5S9.83,15 9,15s-1.5,-0.67 -1.5,-1.5S8.17,12 9,12zM12,18H6v-0.43c0,-0.6 0.36,-1.15 0.92,-1.39C7.56,15.9 8.26,15.75 9,15.75s1.44,0.15 2.08,0.43c0.55,0.24 0.92,0.78 0.92,1.39V18zM13,9h-2V4h2V9zM17.25,16.5h-2.5c-0.41,0 -0.75,-0.34 -0.75,-0.75s0.34,-0.75 0.75,-0.75h2.5c0.41,0 0.75,0.34 0.75,0.75S17.66,16.5 17.25,16.5zM17.25,13.5h-2.5c-0.41,0 -0.75,-0.34 -0.75,-0.75s0.34,-0.75 0.75,-0.75h2.5c0.41,0 0.75,0.34 0.75,0.75S17.66,13.5 17.25,13.5z")
    }

    val EventAvailable: ImageVector by lazy {
        svgIcon("EventAvailable", "M16.53,11.06L15.47,10l-4.88,4.88 -2.12,-2.12 -1.06,1.06L10.59,17l5.94,-5.94zM19,3h-1L18,1h-2v2L8,3L8,1L6,1v2L5,3c-1.11,0 -1.99,0.9 -1.99,2L3,19c0,1.1 0.89,2 2,2h14c1.1,0 2,-0.9 2,-2L21,5c0,-1.1 -0.9,-2 -2,-2zM19,19L5,19L5,8h14v11z")
    }

    val Schedule: ImageVector by lazy {
        svgIcon("Schedule", "M11.99,2C6.47,2 2,6.48 2,12s4.47,10 9.99,10C17.52,22 22,17.52 22,12S17.52,2 11.99,2zM12,20c-4.42,0 -8,-3.58 -8,-8s3.58,-8 8,-8 8,3.58 8,8 -3.58,8 -8,8zM12.5,7H11v6l5.25,3.15 0.75,-1.23 -4.5,-2.67z")
    }

    val Error: ImageVector by lazy {
        svgIcon("Error", "M12,2C6.48,2 2,6.48 2,12s4.48,10 10,10 10,-4.48 10,-10S17.52,2 12,2zM13,17h-2v-2h2v2zM13,13h-2L11,7h2v6z")
    }

    val Factory: ImageVector by lazy {
        svgIcon("Factory", "M22,22H2V10l7,-3v2l5,-2l0,3h3l1,-8h3l1,8V22zM12,9.95l-5,2V10l-3,1.32V20h16v-8h-8L12,9.95zM11,18h2v-4h-2V18zM7,18h2v-4H7V18zM17,14h-2v4h2V14z")
    }

    val Inventory: ImageVector by lazy {
        svgIcon("Inventory", "M20,2H4C3,2 2,2.9 2,4v3.01c0,0.72 0.43,1.34 1,1.69V20c0,1.1 1.1,2 2,2h14c0.9,0 2,-0.9 2,-2V8.7c0.57,-0.35 1,-0.97 1,-1.69V4C22,2.9 21,2 20,2zM19,20H5V9h14V20zM20,7H4V4h16V7zM9,12h6v2H9V12z")
    }

    /** Recetas (Article). */
    val Article: ImageVector by lazy {
        svgIcon("Article", "M19,5v14H5V5H19M19,3H5C3.9,3 3,3.9 3,5v14c0,1.1 0.9,2 2,2h14c1.1,0 2,-0.9 2,-2V5C21,3.9 20.1,3 19,3L19,3zM14,17H7v-2h7V17zM17,13H7v-2h10V13zM17,9H7V7h10V9z")
    }

    /** Boleta de compra (ReceiptLong). */
    val Receipt: ImageVector by lazy {
        svgIcon("Receipt", "M19.5,3.5L18,2l-1.5,1.5L15,2l-1.5,1.5L12,2l-1.5,1.5L9,2L7.5,3.5L6,2v14H3v3c0,1.66 1.34,3 3,3h12c1.66,0 3,-1.34 3,-3V2L19.5,3.5zM15,20H6c-0.55,0 -1,-0.45 -1,-1v-1h10V20zM19,19c0,0.55 -0.45,1 -1,1s-1,-0.45 -1,-1v-3H8V5h11V19zM9,7h6v2H9V7zM16,7h2v2h-2V7zM9,10h6v2H9V10zM16,10h2v2h-2V10z")
    }

    val ShoppingCart: ImageVector by lazy {
        svgIcon("ShoppingCart", "M15.55,13c0.75,0 1.41,-0.41 1.75,-1.03l3.58,-6.49C21.25,4.82 20.77,4 20.01,4H5.21l-0.94,-2H1v2h2l3.6,7.59 -1.35,2.44C4.52,15.37 5.48,17 7,17h12v-2H7l1.1,-2H15.55zM6.16,6h12.15l-2.76,5H8.53L6.16,6zM7,18c-1.1,0 -1.99,0.9 -1.99,2S5.9,22 7,22s2,-0.9 2,-2S8.1,18 7,18zM17,18c-1.1,0 -1.99,0.9 -1.99,2s0.89,2 1.99,2 2,-0.9 2,-2S18.1,18 17,18z")
    }

    val Warning: ImageVector by lazy {
        svgIcon("Warning", "M12,5.99L19.53,19H4.47L12,5.99M12,2L1,21h22L12,2L12,2zM13,16h-2v2h2V16zM13,10h-2v4h2V10z")
    }

    val Info: ImageVector by lazy {
        svgIcon("Info", "M11,7h2v2h-2zM11,11h2v6h-2zM12,2C6.48,2 2,6.48 2,12s4.48,10 10,10 10,-4.48 10,-10S17.52,2 12,2zM12,20c-4.41,0 -8,-3.59 -8,-8s3.59,-8 8,-8 8,3.59 8,8 -3.59,8 -8,8z")
    }

    val Check: ImageVector by lazy {
        svgIcon("Check", "M9,16.17L4.83,12l-1.42,1.41L9,19 21,7l-1.41,-1.41z")
    }
}

/** Ícono a partir de path data SVG (24×24), igual que los de material-icons. */
private fun svgIcon(name: String, pathData: String): ImageVector =
    ImageVector.Builder(
        name = "EcoLact.$name",
        defaultWidth = 24.dp,
        defaultHeight = 24.dp,
        viewportWidth = 24f,
        viewportHeight = 24f,
    ).addPath(pathData = addPathNodes(pathData), fill = SolidColor(Color.Black)).build()

private inline fun icon(name: String, autoMirror: Boolean, block: ImageVector.Builder.() -> ImageVector.Builder): ImageVector =
    ImageVector.Builder(
        name = "EcoLact.$name",
        defaultWidth = 24.dp,
        defaultHeight = 24.dp,
        viewportWidth = 24f,
        viewportHeight = 24f,
        autoMirror = autoMirror,
    ).block().build()

private inline fun ImageVector.Builder.materialPath(
    fillAlpha: Float = 1f,
    strokeAlpha: Float = 1f,
    pathFillType: PathFillType = PathFillType.NonZero,
    pathBuilder: PathBuilder.() -> Unit,
) = path(
    fill = SolidColor(Color.Black),
    fillAlpha = fillAlpha,
    stroke = null,
    strokeAlpha = strokeAlpha,
    strokeLineWidth = 1f,
    strokeLineCap = StrokeCap.Butt,
    strokeLineJoin = StrokeJoin.Bevel,
    strokeLineMiter = 1f,
    pathFillType = pathFillType,
    pathBuilder = pathBuilder,
)
