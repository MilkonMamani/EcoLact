<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
            ParametroCalidadSeeder::class,
            PrecioLecheSeeder::class,
            ReglaPagoCalidadSeeder::class,
            // Las 4 zonas operativas y su delimitación operativa referencial inicial (idempotentes:
            // no duplican zonas ni pisan una delimitación ya editada por el administrador).
            ZonaSeeder::class,
            ZonaDelimitacionSeeder::class,
        ]);
    }
}
