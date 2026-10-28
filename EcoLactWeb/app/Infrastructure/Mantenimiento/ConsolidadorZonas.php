<?php

namespace App\Infrastructure\Mantenimiento;

use App\Domain\Zona\Zona;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolida las variantes de nombre de las cuatro zonas operativas oficiales ("Collana1" →
 * "Collana 1", "COLLANA 2" → "Collana 2", ...) en su fila canónica, sin perder nada: es la
 * misma zona lógica cargada dos veces (el UNIQUE de zonas.nombre no las distingue porque los
 * textos son distintos). Ver el comando `zonas:consolidar`.
 *
 * Alcance deliberadamente acotado: solo las zonas de ZONAS_OFICIALES y solo filas cuyo nombre
 * es equivalente según Zona::normalizarNombre (mayúsculas, espacios, letra/número). Nunca
 * crea zonas ni toca otras.
 *
 * Reglas:
 *  - Canónica = la fila cuyo nombre es EXACTAMENTE el oficial. Si no existe, es un conflicto.
 *  - Referencias: se descubren TODAS las llaves foráneas que apuntan a zonas.id en el esquema
 *    real (no una lista fija) y se reapuntan a la canónica. Incluye entregas e historiales:
 *    los dos ids son la misma zona, así que corregir el histórico no cambia su significado.
 *  - Rutas: se mueven a la canónica. Si la canónica ya tiene una ruta con el mismo nombre
 *    (UNIQUE zona_id + nombre), solo se fusionan si ambas son "Ruta principal" (la ruta por
 *    defecto que crea el sistema): sus ruta_detalles pasan a la ruta canónica DESPUÉS de los
 *    suyos (se conserva el orden relativo), y se reapuntan todas las FK hacia rutas.id
 *    (historial de ruta, recorridos, ...). Cualquier otra coincidencia de nombre es conflicto.
 *  - Datos de la zona: la canónica conserva lo que ya tiene. Solo si le falta poligono_geojson,
 *    color_mapa o descripcion se copia el de la duplicada; la descripción genérica
 *    "Zona <nombre>" de una carga masiva no se copia porque no aporta información real.
 *  - Con cualquier conflicto, aplicar() no modifica nada. Cada zona se consolida en su propia
 *    transacción y la duplicada solo se borra cuando ya no queda ninguna referencia a ella.
 */
final class ConsolidadorZonas
{
    public const ZONAS_OFICIALES = ['Collana 1', 'Collana 2', 'Faon', 'Yasin'];

    private const RUTA_FUSIONABLE = 'ruta principal';

    private const CAMPOS_ZONA = ['poligono_geojson', 'color_mapa', 'descripcion'];

    /**
     * Qué se haría, sin modificar nada.
     *
     * @return list<array{oficial: string, origen: array{id: int, nombre: string}, destino: ?array{id: int, nombre: string}, referencias: array<string, int>, rutas: list<array{id: int, nombre: string, accion: string, destino_id: ?int}>, detalles_fusionados: int, copiar: array<string, string>, conflictos: list<string>}>
     */
    public function planificar(): array
    {
        $zonas = DB::table('zonas')->orderBy('id')->get();
        $referencias = $this->referenciasA('zonas');
        $plan = [];

        foreach (self::ZONAS_OFICIALES as $oficial) {
            $canonica = $zonas->first(fn (object $zona): bool => $zona->nombre === $oficial);
            $variantes = $zonas->filter(fn (object $zona): bool => $zona->nombre !== $oficial
                && Zona::normalizarNombre($zona->nombre) === Zona::normalizarNombre($oficial));

            foreach ($variantes as $origen) {
                $plan[] = $this->planDe($oficial, $origen, $canonica, $referencias);
            }
        }

        return $plan;
    }

    /**
     * Ejecuta el plan. Si algún paso tiene conflictos no se toca nada.
     *
     * @return list<array<string, mixed>> el plan ejecutado
     */
    public function aplicar(): array
    {
        $plan = $this->planificar();
        $conflictos = array_merge([], ...array_column($plan, 'conflictos'));

        if ($conflictos !== []) {
            throw new DomainException('Hay conflictos sin resolver; no se modificó nada:'.PHP_EOL.'- '.implode(PHP_EOL.'- ', $conflictos));
        }

        $referenciasZona = $this->referenciasA('zonas');
        $referenciasRuta = $this->referenciasA('rutas');

        foreach ($plan as $paso) {
            DB::transaction(fn () => $this->consolidar($paso, $referenciasZona, $referenciasRuta));
        }

        return $plan;
    }

    /**
     * Las cuatro zonas oficiales tal como quedaron (o faltan) y si hay filas huérfanas.
     *
     * @return array{zonas: list<array{id: int, nombre: string, estado: string, tiene_poligono: bool}>, huerfanas: array<string, int>}
     */
    public function verificar(): array
    {
        $huerfanas = [];

        foreach ($this->referenciasA('zonas') as [$tabla, $columna]) {
            $huerfanas["{$tabla}.{$columna}"] = DB::table($tabla)
                ->whereNotNull($columna)
                ->whereNotIn($columna, DB::table('zonas')->select('id'))
                ->count();
        }

        return [
            'zonas' => DB::table('zonas')->orderBy('nombre')->get()->map(fn (object $zona): array => [
                'id' => (int) $zona->id,
                'nombre' => $zona->nombre,
                'estado' => $zona->estado,
                'tiene_poligono' => $zona->poligono_geojson !== null,
            ])->all(),
            'huerfanas' => $huerfanas,
        ];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $referencias
     * @return array<string, mixed>
     */
    private function planDe(string $oficial, object $origen, ?object $canonica, array $referencias): array
    {
        $conflictos = [];
        $conteos = [];

        foreach ($referencias as [$tabla, $columna]) {
            $conteos["{$tabla}.{$columna}"] = DB::table($tabla)->where($columna, $origen->id)->count();
        }

        if ($canonica === null) {
            $conflictos[] = "\"{$origen->nombre}\" (id {$origen->id}) equivale a \"{$oficial}\", pero no existe una zona llamada exactamente \"{$oficial}\" a la cual consolidarla.";

            return [
                'oficial' => $oficial,
                'origen' => ['id' => (int) $origen->id, 'nombre' => $origen->nombre],
                'destino' => null,
                'referencias' => $conteos,
                'rutas' => [],
                'detalles_fusionados' => 0,
                'copiar' => [],
                'conflictos' => $conflictos,
            ];
        }

        $rutasDestino = DB::table('rutas')->where('zona_id', $canonica->id)->get()
            ->keyBy(fn (object $ruta): string => mb_strtolower(trim($ruta->nombre)));
        $rutas = [];
        $detallesFusionados = 0;

        foreach (DB::table('rutas')->where('zona_id', $origen->id)->orderBy('id')->get() as $ruta) {
            $clave = mb_strtolower(trim($ruta->nombre));
            $homonima = $rutasDestino->get($clave);

            if ($homonima === null) {
                $rutas[] = ['id' => (int) $ruta->id, 'nombre' => $ruta->nombre, 'accion' => 'mover', 'destino_id' => null];

                continue;
            }

            if ($clave !== self::RUTA_FUSIONABLE) {
                $conflictos[] = "La ruta \"{$ruta->nombre}\" (id {$ruta->id}) de \"{$origen->nombre}\" tiene el mismo nombre que la ruta id {$homonima->id} de \"{$canonica->nombre}\"; no es seguro asumir que son la misma ruta. Renombra una de las dos y vuelve a ejecutar.";

                continue;
            }

            $rutas[] = ['id' => (int) $ruta->id, 'nombre' => $ruta->nombre, 'accion' => 'fusionar', 'destino_id' => (int) $homonima->id];
            $detallesFusionados += DB::table('ruta_detalles')->where('ruta_id', $ruta->id)->count();
        }

        return [
            'oficial' => $oficial,
            'origen' => ['id' => (int) $origen->id, 'nombre' => $origen->nombre],
            'destino' => ['id' => (int) $canonica->id, 'nombre' => $canonica->nombre],
            'referencias' => $conteos,
            'rutas' => $rutas,
            'detalles_fusionados' => $detallesFusionados,
            'copiar' => $this->camposACopiar($origen, $canonica),
            'conflictos' => $conflictos,
        ];
    }

    /** @return array<string, string> campo => valor que recibe la canónica */
    private function camposACopiar(object $origen, object $canonica): array
    {
        $copiar = [];

        foreach (self::CAMPOS_ZONA as $campo) {
            $valorOrigen = $origen->{$campo};

            if (! $this->vacio($canonica->{$campo}) || $this->vacio($valorOrigen)) {
                continue;
            }

            if ($campo === 'descripcion' && Zona::normalizarNombre($valorOrigen) === Zona::normalizarNombre("Zona {$origen->nombre}")) {
                continue;
            }

            $copiar[$campo] = $valorOrigen;
        }

        return $copiar;
    }

    /**
     * @param  array<string, mixed>  $paso
     * @param  list<array{0: string, 1: string}>  $referenciasZona
     * @param  list<array{0: string, 1: string}>  $referenciasRuta
     */
    private function consolidar(array $paso, array $referenciasZona, array $referenciasRuta): void
    {
        $origenId = $paso['origen']['id'];
        $destinoId = $paso['destino']['id'];

        foreach ($paso['rutas'] as $ruta) {
            if ($ruta['accion'] === 'fusionar') {
                $this->fusionarRuta($ruta['id'], $ruta['destino_id'], $referenciasRuta);
            }
        }

        foreach ($referenciasZona as [$tabla, $columna]) {
            DB::table($tabla)->where($columna, $origenId)->update([$columna => $destinoId]);
        }

        if ($paso['copiar'] !== []) {
            DB::table('zonas')->where('id', $destinoId)->update([...$paso['copiar'], 'updated_at' => now()]);
        }

        foreach ($referenciasZona as [$tabla, $columna]) {
            if (DB::table($tabla)->where($columna, $origenId)->exists()) {
                throw new DomainException("Todavía hay filas de {$tabla}.{$columna} apuntando a la zona {$origenId}; se revirtió la consolidación.");
            }
        }

        DB::table('zonas')->where('id', $origenId)->delete();
    }

    /**
     * Pasa los proveedores de la ruta origen a la ruta destino a continuación de los suyos y
     * reapunta toda referencia a la ruta origen antes de borrarla. El orden se mueve en dos
     * pasos para no chocar con UNIQUE(ruta_id, orden_visita) en ningún momento.
     *
     * @param  list<array{0: string, 1: string}>  $referenciasRuta
     */
    private function fusionarRuta(int $origenId, int $destinoId, array $referenciasRuta): void
    {
        $desfase = (int) DB::table('ruta_detalles')->where('ruta_id', $destinoId)->max('orden_visita');
        $temporal = 1_000_000;

        DB::table('ruta_detalles')->where('ruta_id', $origenId)->update(['orden_visita' => DB::raw("orden_visita + {$temporal}")]);
        DB::table('ruta_detalles')->where('ruta_id', $origenId)->update([
            'ruta_id' => $destinoId,
            'orden_visita' => DB::raw('orden_visita - '.($temporal - $desfase)),
        ]);

        foreach ($referenciasRuta as [$tabla, $columna]) {
            DB::table($tabla)->where($columna, $origenId)->update([$columna => $destinoId]);
        }

        DB::table('rutas')->where('id', $origenId)->delete();
    }

    /**
     * Columnas que tienen una llave foránea hacia $tablaReferida en el esquema real.
     *
     * @return list<array{0: string, 1: string}> [tabla, columna]
     */
    private function referenciasA(string $tablaReferida): array
    {
        $referencias = [];

        foreach (Schema::getTableListing(schema: Schema::getCurrentSchemaName(), schemaQualified: false) as $tabla) {
            foreach (Schema::getForeignKeys($tabla) as $llave) {
                if ($llave['foreign_table'] === $tablaReferida && count($llave['columns']) === 1) {
                    $referencias["{$tabla}.{$llave['columns'][0]}"] = [$tabla, $llave['columns'][0]];
                }
            }
        }

        ksort($referencias);

        return array_values($referencias);
    }

    private function vacio(mixed $valor): bool
    {
        return $valor === null || (is_string($valor) && trim($valor) === '');
    }
}
