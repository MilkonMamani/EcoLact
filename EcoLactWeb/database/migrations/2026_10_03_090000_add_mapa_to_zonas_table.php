<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Colores del mapa general para las zonas operativas conocidas. Solo el
     * color se completa: la delimitación (poligono_geojson) queda en null
     * hasta que el administrador la dibuje desde "Editar zona", porque no
     * existe geometría oficial y no se inventan polígonos.
     *
     * @var array<string, string>
     */
    private const COLORES = [
        'Collana 1' => '#16a34a',
        'Collana 2' => '#d99a00',
        'Faon' => '#2563eb',
        'Faón' => '#2563eb',
        'Yasin' => '#dc3f4f',
    ];

    public function up(): void
    {
        Schema::table('zonas', function (Blueprint $table) {
            $table->string('color_mapa', 7)->nullable()->after('estado');
            $table->json('poligono_geojson')->nullable()->after('color_mapa');
        });

        foreach (self::COLORES as $nombre => $color) {
            DB::table('zonas')->where('nombre', $nombre)->whereNull('color_mapa')->update(['color_mapa' => $color]);
        }
    }

    public function down(): void
    {
        Schema::table('zonas', function (Blueprint $table) {
            $table->dropColumn(['color_mapa', 'poligono_geojson']);
        });
    }
};
