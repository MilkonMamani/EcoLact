package pe.edu.upeu.ecolactmobile.core.database

import app.cash.sqldelight.db.SqlDriver
import app.cash.sqldelight.driver.native.NativeSqliteDriver

actual class DatabaseDriverFactory {
    actual fun createDriver(): SqlDriver {
        return NativeSqliteDriver(EcoLactDatabase.Schema, "ecolact.db")
    }
}
