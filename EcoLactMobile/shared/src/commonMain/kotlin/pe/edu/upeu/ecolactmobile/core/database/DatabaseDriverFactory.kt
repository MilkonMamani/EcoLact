package pe.edu.upeu.ecolactmobile.core.database

import app.cash.sqldelight.db.SqlDriver

expect class DatabaseDriverFactory {
    fun createDriver(): SqlDriver
}

fun createDatabase(driverFactory: DatabaseDriverFactory): EcoLactDatabase {
    return EcoLactDatabase(driverFactory.createDriver())
}
