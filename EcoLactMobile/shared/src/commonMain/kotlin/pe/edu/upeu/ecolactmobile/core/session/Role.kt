package pe.edu.upeu.ecolactmobile.core.session

/** Los 6 roles exactos que soporta el backend. No hay sub-roles ni permisos configurables. */
enum class Role {
    ADMINISTRADOR,
    ACOPIADOR,
    PROVEEDOR,
    CALIDAD,
    ENCARGADO_PLANTA,
    VENTAS;

    companion object {
        fun fromApiValue(value: String): Role =
            entries.firstOrNull { it.name == value }
                ?: throw IllegalArgumentException("Rol desconocido recibido del backend: $value")
    }
}
