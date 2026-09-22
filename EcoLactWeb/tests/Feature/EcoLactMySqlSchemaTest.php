<?php

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

it('reproduces the authoritative SQL on MySQL and supports rollback and migration again', function () {
    if (getenv('ECOLACT_MYSQL_SCHEMA_TEST') !== '1') {
        $this->markTestSkipped('Set ECOLACT_MYSQL_SCHEMA_TEST=1 to validate in two disposable MySQL databases.');
    }

    $sql = file_get_contents(base_path('docs/database/EcoLact_BD_v1_MySQL.sql'));
    preg_match_all('/CREATE TABLE IF NOT EXISTS `([a-z_]+)` \(.*?\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;/s', $sql, $definitions, PREG_SET_ORDER);
    expect($definitions)->toHaveCount(40);

    $tables = array_column($definitions, 1);
    $mysql = config('database.connections.mysql');
    $mysql['url'] = null;
    $mysql['database'] = null;
    config(['database.connections.ecolact_test_admin' => $mysql]);
    $admin = DB::connection('ecolact_test_admin');
    $server = $admin->selectOne('SELECT VERSION() AS version, @@explicit_defaults_for_timestamp AS explicit_defaults');
    expect($server->version)->toStartWith('8.');
    expect((int) $server->explicit_defaults)->toBe(1);

    $suffix = bin2hex(random_bytes(6));
    $createdDatabases = [];
    $originalDefault = DB::getDefaultConnection();
    $connections = [
        'ecolact_reference' => 'ecolact_schema_test_'.$suffix.'_reference',
        'ecolact_migrations' => 'ecolact_schema_test_'.$suffix.'_migrations',
    ];

    $readStructure = function (Connection $connection, string $table): array {
        $queries = [
            'table' => 'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            'columns' => 'SELECT COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME, DATETIME_PRECISION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            'indexes' => 'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, INDEX_TYPE, IS_VISIBLE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            'foreign_keys' => 'SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.ORDINAL_POSITION, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? ORDER BY k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
        ];

        $structure = [];
        foreach ($queries as $part => $query) {
            $structure[$part] = array_map(fn (object $row): array => (array) $row, $connection->select($query, [$table]));
        }

        return $structure;
    };

    try {
        foreach ($connections as $connection => $database) {
            $admin->statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $createdDatabases[] = $database;
            config(['database.connections.'.$connection => array_replace($mysql, ['database' => $database])]);
        }

        $reference = DB::connection('ecolact_reference');
        foreach ($definitions as $definition) {
            $reference->unprepared($definition[0]);
        }

        $this->artisan('migrate', ['--database' => 'ecolact_migrations', '--force' => true])->assertSuccessful();
        $migrated = DB::connection('ecolact_migrations');
        $expectedTables = array_merge($tables, ['migrations', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'personal_access_tokens']);
        $actualTables = array_column($migrated->select('SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'), 'name');
        expect($actualTables)->toEqualCanonicalizing($expectedTables);

        foreach ($tables as $table) {
            expect($readStructure($migrated, $table))->toBe($readStructure($reference, $table), 'SQL equivalence: '.$table);
            expect($migrated->table($table)->count())->toBe(0, 'Migrations must not seed '.$table);
        }
        expect($migrated->table('migrations')->count())->toBe(50);

        $this->artisan('migrate:rollback', ['--database' => 'ecolact_migrations', '--force' => true])->assertSuccessful();
        expect(array_column($migrated->select('SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'), 'name'))->toBe(['migrations']);
        expect($migrated->table('migrations')->count())->toBe(0);

        $this->artisan('migrate', ['--database' => 'ecolact_migrations', '--force' => true])->assertSuccessful();
        foreach ($tables as $table) {
            expect($readStructure($migrated, $table))->toBe($readStructure($reference, $table), 'SQL equivalence after rollback: '.$table);
        }
    } finally {
        DB::setDefaultConnection($originalDefault);
        foreach ($connections as $connection => $database) {
            DB::purge($connection);
            if (in_array($database, $createdDatabases, true) && preg_match('/^ecolact_schema_test_[a-f0-9]{12}_(reference|migrations)$/', $database)) {
                $admin->statement("DROP DATABASE `{$database}`");
            }
        }
        DB::purge('ecolact_test_admin');
    }
});
