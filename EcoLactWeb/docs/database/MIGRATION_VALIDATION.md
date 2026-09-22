# Resultado de migrations EcoLact

Fecha: 2026-09-09. Entorno verificado: PHP 8.3.16, Laravel 13.31.0 y MySQL 8.4.3.

## Autoridad utilizada

Por autorización expresa del usuario se utilizó `EcoLact_BD_v1_MySQL.sql`, conservado íntegramente en esta carpeta. La copia tiene el mismo SHA-256 que el archivo de Downloads: `d43e92ce6f5590d2f91c2465c057bc8363a7541ba10a9d9e43acd4723764bacb`.

El DBML mencionado en `docs/database/EcoLact_DB_v1.dbml` no existía. El DBML adjunto era una representación parcial: omitía nulabilidad explícita, UNSIGNED, defaults, nombres de índices y acciones referenciales. La equivalencia final se verificó contra el SQL autorizado.

Se conservó el nombre de la base configurada en el proyecto, `ecolactv1`. No se ejecutaron las sentencias CREATE DATABASE/USE ni el INSERT de roles del archivo fuente sobre la aplicación.

## Resultado comprobado

- 29 tablas funcionales y 268 columnas, en el orden exacto de cada definición SQL.
- Una sola migration crea `users`: `2026_09_09_154755_create_users_table.php`.
- `users`: id, role_id, username, password, estado, debe_cambiar_password, ultimo_acceso, remember_token, created_at y updated_at. Sin name, email ni email_verified_at.
- 47 foreign keys con sus nombres originales: 47 ON UPDATE CASCADE; 41 ON DELETE RESTRICT y 6 ON DELETE SET NULL.
- 28 índices explícitos: 19 UNIQUE y 9 no únicos. También se verificaron las 29 PK y los 29 índices implícitos de soporte de FK: 86 índices físicos en total.
- Todas las tablas usan InnoDB, utf8mb4 y utf8mb4_unicode_ci.
- Tipos, UNSIGNED, longitudes, precisión/escala, nulabilidad, defaults, nombres de índices, orden de columnas de índices y relaciones comparados contra MySQL ejecutando el SQL de referencia.
- `proveedores` implementada; ninguna tabla `productores`.
- `auditorias.created_at` es TIMESTAMP NOT NULL, sin default y sin updated_at.
- Cero registros insertados en las 29 tablas funcionales. Los tres roles quedan para un Seeder posterior.
- Se conservaron las 8 tablas técnicas existentes de Laravel/Sanctum, además de la tabla de control migrations.

## Ejecución y validación

1. Prueba `tests/Feature/EcoLactMySqlSchemaTest.php`: PASS, 1 prueba y 97 assertions. Crea dos bases temporales propias: una con los CREATE TABLE del SQL y otra con las migrations. Compara metadatos completos, ejecuta rollback y vuelve a migrar. Elimina únicamente las bases creadas por la prueba.
2. `php artisan migrate --no-interaction`: exit code 0, las 29 migrations EcoLact ejecutadas correctamente en batch 3.
3. `php artisan migrate:status --no-interaction`: 33 migrations Ran, cero Pending. Las 4 técnicas pertenecen a los batches 1 y 2.
4. Comparación independiente de la base real `ecolactv1` con otra base temporal de referencia: cero diferencias; 29 tablas, 268 columnas, 47 FK y 86 índices físicos coincidentes.
5. Orden de dependencias de todos los archivos validado; ninguna creación duplicada.
6. Laravel Pint aplicado a las migrations y la prueba: PASS. `--dirty` no está disponible porque esta carpeta no es un repositorio Git; se usaron rutas explícitas.

Para repetir la prueba de equivalencia en PowerShell, con MySQL disponible y un usuario con permisos para crear bases temporales:

```powershell
$env:ECOLACT_MYSQL_SCHEMA_TEST = '1'
php artisan test --compact tests/Feature/EcoLactMySqlSchemaTest.php
```

La prueba utiliza las opciones de conexión MySQL del proyecto, no escribe en la base de la aplicación y no necesita ejecutar migrate:fresh en ella.

## Diferencias con las migrations originales

- Las 28 migrations funcionales distintas de users solo declaraban id y timestamps; se completaron todas sus definiciones.
- La migration estándar de users seguía presente y ya había sido ejecutada; no existía otra migration personalizada de users.
- Se separaron password_reset_tokens y sessions en `0001_01_01_000000_create_session_tables.php`. Se reutilizó su registro histórico, manteniendo el batch original. Las tablas técnicas no se recrearon ni vaciaron.
- Tras comprobar que users tenía cero filas y ninguna FK entrante, se retiró únicamente esa tabla vacía. Se creó users con la nueva migration ubicada después de roles y antes de sus dependientes. La sesión existente se conservó.
- Se eliminó updated_at de la definición original de auditorias y se corrigió created_at a NOT NULL.
- Se añadieron todos los campos, tipos, defaults, índices y constraints ausentes. No se modificaron modelos, factories, seeders ni lógica de autenticación: el alcance fue la estructura.

## Archivos funcionales modificados o reorganizados

El archivo original `database/migrations/0001_01_01_000000_create_users_table.php` se dividió en la migration técnica de sesiones y la migration EcoLact de users, sin duplicar creaciones.

| Tabla | Archivo en database/migrations | Columnas | Índices explícitos | FK |
| --- | --- | ---: | ---: | ---: |
| roles | `2026_09_09_154750_create_roles_table.php` | 5 | 1 | 0 |
| zonas | `2026_09_09_154751_create_zonas_table.php` | 6 | 1 | 0 |
| parametros_calidad | `2026_09_09_154752_create_parametros_calidad_table.php` | 7 | 1 | 0 |
| reglas_pago_calidad | `2026_09_09_154753_create_reglas_pago_calidad_table.php` | 9 | 0 | 0 |
| periodos_liquidacion | `2026_09_09_154754_create_periodos_liquidacion_table.php` | 6 | 0 | 0 |
| users | `2026_09_09_154755_create_users_table.php` | 10 | 1 | 1 |
| productos | `2026_09_09_154913_create_productos_table.php` | 6 | 1 | 0 |
| proveedores | `2026_09_09_154926_create_proveedores_table.php` | 12 | 4 | 2 |
| acopiadores | `2026_09_09_154930_create_acopiadores_table.php` | 11 | 3 | 2 |
| proveedor_zona_historial | `2026_09_09_154938_create_proveedor_zona_historial_table.php` | 8 | 0 | 2 |
| acopiador_zona_historial | `2026_09_09_154939_create_acopiador_zona_historial_table.php` | 8 | 0 | 2 |
| reglas_calidad | `2026_09_09_154946_create_reglas_calidad_table.php` | 11 | 0 | 1 |
| precios_leche | `2026_09_09_154948_create_precios_leche_table.php` | 8 | 0 | 1 |
| entregas | `2026_09_09_154954_create_entregas_table.php` | 12 | 3 | 4 |
| analisis_calidad | `2026_09_09_155006_create_analisis_calidad_table.php` | 12 | 2 | 2 |
| analisis_calidad_detalles | `2026_09_09_155007_create_analisis_calidad_detalles_table.php` | 9 | 1 | 2 |
| sanciones | `2026_09_09_155013_create_sanciones_table.php` | 12 | 0 | 3 |
| comunicados | `2026_09_09_155020_create_comunicados_table.php` | 12 | 0 | 2 |
| comunicado_destinatarios | `2026_09_09_155021_create_comunicado_destinatarios_table.php` | 7 | 2 | 2 |
| notificaciones | `2026_09_09_155022_create_notificaciones_table.php` | 11 | 1 | 1 |
| valorizaciones_entrega | `2026_09_09_155028_create_valorizaciones_entrega_table.php` | 14 | 1 | 4 |
| liquidaciones | `2026_09_09_155035_create_liquidaciones_table.php` | 13 | 2 | 3 |
| liquidacion_detalles | `2026_09_09_155036_create_liquidacion_detalles_table.php` | 6 | 1 | 2 |
| ajustes_liquidacion | `2026_09_09_155037_create_ajustes_liquidacion_table.php` | 9 | 0 | 3 |
| pagos_proveedor | `2026_09_09_155038_create_pagos_proveedor_table.php` | 11 | 0 | 2 |
| lotes_produccion | `2026_09_09_155044_create_lotes_produccion_table.php` | 10 | 1 | 1 |
| lote_produccion_entregas | `2026_09_09_155045_create_lote_produccion_entregas_table.php` | 6 | 1 | 2 |
| produccion_detalles | `2026_09_09_155046_create_produccion_detalles_table.php` | 6 | 1 | 2 |
| auditorias | `2026_09_09_155052_create_auditorias_table.php` | 11 | 0 | 1 |

Archivos adicionales del trabajo:

- `database/migrations/0001_01_01_000000_create_session_tables.php`: separación de tablas técnicas existentes.
- `docs/database/EcoLact_BD_v1_MySQL.sql`: copia exacta de la autoridad aprobada.
- `tests/Feature/EcoLactMySqlSchemaTest.php`: prueba de equivalencia y reversibilidad.
- `docs/database/MIGRATION_VALIDATION.md`: este informe.
- `composer.json` y `composer.lock`: Laravel Boost 2.8.0 y sus dependencias de desarrollo, exigidos por AGENTS.md. La versión instalada de Laravel no se cambió.
- `boost.json`, `CLAUDE.md`, `.mcp.json` y `.claude/skills/`: archivos generados por `php artisan boost:install` según la detección del instalador. AGENTS.md se volvió a leer y permanece sin cambios.
- `storage/app/schema-work/`: respaldo de la migration original, manifiesto de las 29 tablas, estado previo sin datos de usuarios ni sesiones y resultado JSON de validación local. Los scripts de preparación de uso único se retiraron al terminar.

Las migrations de cache, jobs y personal_access_tokens se conservaron.
