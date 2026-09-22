package pe.edu.upeu.ecolactmobile

interface Platform {
    val name: String
}

expect fun getPlatform(): Platform