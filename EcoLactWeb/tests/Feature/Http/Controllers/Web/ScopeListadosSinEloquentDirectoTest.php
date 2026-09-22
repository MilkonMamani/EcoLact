<?php

/**
 * Etapa 10.1.2 — Auditoría de scope: el filtrado de listados (index()) debe
 * resolverse siempre vía Application/Repository contracts, nunca con una
 * consulta Eloquent directa (Model::where(...), DB::table(...), etc.)
 * escrita dentro del Controller. Esta prueba es una verificación estática
 * del código fuente, no una prueba de comportamiento: si algún Controller
 * incorporara una consulta de este tipo en el futuro, este test fallaría
 * incluso si el resultado "parece" correcto.
 */
$controllersConIndex = [
    'app/Http/Controllers/Web/ProveedorController.php',
    'app/Http/Controllers/Web/AcopiadorController.php',
    'app/Http/Controllers/Web/EntregaController.php',
    'app/Http/Controllers/Web/Liquidacion/LiquidacionController.php',
    'app/Http/Controllers/Web/Pago/PagoController.php',
    'app/Http/Controllers/Web/Pago/ConfiguracionPagoController.php',
    'app/Http/Controllers/Web/Comunicacion/ComunicadoController.php',
    'app/Http/Controllers/Web/Comunicacion/NotificacionController.php',
];

$patronesProhibidos = [
    '::where(',
    '::whereHas(',
    '::whereIn(',
    '::query()',
    '::all()',
    'DB::table(',
    'DB::select(',
    '->newQuery(',
];

foreach ($controllersConIndex as $controllerPath) {
    it("no contiene consultas Eloquent directas para filtrar listados: {$controllerPath}", function () use ($controllerPath, $patronesProhibidos) {
        $rutaAbsoluta = base_path($controllerPath);
        expect(file_exists($rutaAbsoluta))->toBeTrue("No se encontró el Controller en {$controllerPath}");

        $codigo = file_get_contents($rutaAbsoluta);

        foreach ($patronesProhibidos as $patron) {
            expect($codigo)->not->toContain($patron, "El Controller {$controllerPath} contiene '{$patron}': el filtrado debe delegarse a Application/Repository, nunca hacerse con Eloquent directo en el Controller.");
        }
    });
}
