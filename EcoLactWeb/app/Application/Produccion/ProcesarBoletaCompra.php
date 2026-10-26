<?php

namespace App\Application\Produccion;

use App\Application\Produccion\Contracts\BoletaCompraOcrService;

/**
 * Lee la foto de una boleta y PROPONE las líneas de compra para revisar. No
 * registra nada ni toca el stock: la persona revisa, corrige y confirma
 * (RegistrarCompraInsumos). Nunca crea insumos ni presentaciones.
 *
 * Por cada línea leída ("2 SACOS SAL"):
 *   - reconoce el INSUMO comparando el texto normalizado con los nombres de
 *     los insumos comprables (nunca por id ni con reglas escritas a mano); si
 *     no lo reconoce, la línea queda "No reconocido" para asociarla a mano,
 *   - resuelve la PRESENTACIÓN entre las configuradas de ese insumo:
 *       · la boleta nombra una ("SACO") y hay una sola con ese nombre -> se elige,
 *       · hay varias con ese nombre (Saco 25 kg / Saco 50 kg) -> NO se adivina:
 *         requiere_confirmacion_presentacion = true,
 *       · la boleta no nombra ninguna y el insumo tiene una sola -> se elige,
 *       · en cualquier otro caso la persona la elige.
 *
 * @phpstan-import-type InsumoCatalogo from ObtenerCatalogoCompraInsumos
 */
final class ProcesarBoletaCompra
{
    public const RECONOCIDA = 'RECONOCIDA';

    public const INSUMO_NO_RECONOCIDO = 'INSUMO_NO_RECONOCIDO';

    public const PRESENTACION_AMBIGUA = 'PRESENTACION_AMBIGUA';

    public const PRESENTACION_NO_CONFIGURADA = 'PRESENTACION_NO_CONFIGURADA';

    public function __construct(
        private readonly BoletaCompraOcrService $ocr,
        private readonly BoletaCompraInsumosParser $parser,
        private readonly ObtenerCatalogoCompraInsumos $catalogo,
    ) {}

    /**
     * @return array{
     *     lineas: list<array<string, mixed>>,
     *     cabecera: array{proveedor: ?string, ruc: ?string, numero_comprobante: ?string, fecha: ?string, total: ?float},
     *     texto_ocr: string
     * }
     *
     * @throws BoletaIlegibleException
     */
    public function execute(string $rutaImagen): array
    {
        $lecturas = $this->ocr->leerLecturas($rutaImagen);
        $catalogo = $this->catalogo->execute();

        $nombresPresentaciones = [];
        foreach ($catalogo as $insumo) {
            foreach ($insumo['presentaciones'] as $presentacion) {
                $nombresPresentaciones[] = $presentacion['nombre'];
            }
        }
        $nombresInsumos = array_column($catalogo, 'nombre');

        // Se queda con la lectura que reconoce más líneas; la cabecera toma el primer dato encontrado.
        $mejor = ['lineas' => [], 'cabecera' => []];
        $cabecera = ['proveedor' => null, 'ruc' => null, 'numero_comprobante' => null, 'fecha' => null, 'total' => null];

        foreach ($lecturas as $lectura) {
            $resultado = $this->parser->parsear($lectura, $nombresPresentaciones, $nombresInsumos);

            if (count($resultado['lineas']) > count($mejor['lineas'])) {
                $mejor = $resultado;
            }

            foreach ($resultado['cabecera'] as $campo => $valor) {
                $cabecera[$campo] ??= $valor;
            }
        }

        return [
            'lineas' => array_map(fn (array $linea): array => $this->resolver($linea, $catalogo), $mejor['lineas']),
            'cabecera' => $cabecera,
            'texto_ocr' => implode("\n\n", $lecturas),
        ];
    }

    /**
     * @param  array{texto: string, cantidad: float, presentacion: ?string, descripcion: string}  $linea
     * @param  list<InsumoCatalogo>  $catalogo
     * @return array<string, mixed>
     */
    private function resolver(array $linea, array $catalogo): array
    {
        $insumo = $this->reconocerInsumo($linea['descripcion'], $catalogo);

        $propuesta = [
            'texto' => $linea['texto'],
            'cantidad' => $linea['cantidad'],
            'presentacion_detectada' => $linea['presentacion'] !== null ? ucfirst($linea['presentacion']) : null,
            'descripcion' => mb_strtoupper($linea['descripcion']),
            'insumo_id' => $insumo['id'] ?? null,
            'insumo' => $insumo['nombre'] ?? null,
            'presentacion_id' => null,
            'opciones_presentacion' => [],
            'requiere_confirmacion_presentacion' => false,
            'estado' => self::INSUMO_NO_RECONOCIDO,
        ];

        if ($insumo === null) {
            return $propuesta;
        }

        $todas = $insumo['presentaciones'];
        $candidatas = $linea['presentacion'] === null
            ? $todas
            : array_values(array_filter($todas, static fn (array $p): bool => (TextoComparable::palabras($p['nombre'])[0] ?? null) === $linea['presentacion']));

        $propuesta['opciones_presentacion'] = array_column($candidatas === [] ? $todas : $candidatas, 'id');

        if (count($candidatas) === 1) {
            $propuesta['presentacion_id'] = $candidatas[0]['id'];
            $propuesta['estado'] = self::RECONOCIDA;
        } elseif (count($candidatas) > 1) {
            $propuesta['requiere_confirmacion_presentacion'] = true;
            $propuesta['estado'] = self::PRESENTACION_AMBIGUA;
        } else {
            $propuesta['estado'] = self::PRESENTACION_NO_CONFIGURADA;
        }

        return $propuesta;
    }

    /**
     * Insumo cuyo nombre está completo dentro del texto de la línea; si varios
     * coinciden ("Sal" y "Sal de cura"), el de nombre más largo.
     *
     * @param  list<InsumoCatalogo>  $catalogo
     * @return InsumoCatalogo|null
     */
    private function reconocerInsumo(string $descripcion, array $catalogo): ?array
    {
        $palabrasLinea = TextoComparable::palabras($descripcion);
        $elegido = null;
        $largoElegido = 0;

        foreach ($catalogo as $insumo) {
            $palabrasNombre = array_values(array_diff(TextoComparable::palabras($insumo['nombre']), ['de', 'del', 'la', 'el']));

            if ($palabrasNombre === [] || array_diff($palabrasNombre, $palabrasLinea) !== []) {
                continue;
            }

            if (count($palabrasNombre) > $largoElegido) {
                $elegido = $insumo;
                $largoElegido = count($palabrasNombre);
            }
        }

        return $elegido;
    }
}
