<?php

namespace App\Application\Calidad;

use App\Application\Calidad\Contracts\TicketAnalizadorOcrService;
use App\Domain\Calidad\AnalisisCalidadRepository;
use App\Domain\Calidad\ParametroCalidadRepository;
use DomainException;
use Psr\Log\LoggerInterface;

/**
 * Lee el ticket del analizador de una entrega y PROPONE valores para el
 * formulario de análisis. No registra nada: el analista revisa/corrige y
 * recién entonces guarda (RegistrarAnalisisCalidad); el resultado lo deciden
 * las reglas de calidad al confirmar (FinalizarAnalisisCalidad).
 *
 * Cada valor leído se enlaza con su parámetro por CÓDIGO (nunca por id). Un
 * valor del ticket cuyo parámetro no existe o está inactivo NO se crea: se
 * informa como "no configurado" y queda registrado como advertencia.
 */
final class EscanearTicketAnalizador
{
    /** Con menos valores reconocidos se considera que la foto no sirve. */
    public const MINIMO_VALORES = 3;

    public function __construct(
        private readonly TicketAnalizadorOcrService $ocr,
        private readonly TicketAnalizadorParser $parser,
        private readonly ParametroCalidadRepository $parametros,
        private readonly AnalisisCalidadRepository $analisisCalidad,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array{
     *     texto_ocr: string,
     *     valores: list<array{parametro_id: int, codigo: string, nombre: string, valor: float, unidad: ?string}>,
     *     no_detectados: list<array{codigo: string, nombre: string}>,
     *     no_configurados: list<array{codigo: string, valor: float}>,
     *     suficientes: bool
     * }
     *
     * @throws TicketIlegibleException
     */
    public function execute(int $entregaId, string $rutaImagen): array
    {
        $analisis = $this->analisisCalidad->buscarPorEntregaId($entregaId);

        if ($analisis !== null && $analisis->estaConfirmado()) {
            throw new DomainException('El análisis de esta entrega ya fue confirmado y no puede modificarse.');
        }

        $lecturas = $this->ocr->leerLecturas($rutaImagen);
        $leidos = $this->parser->parsearLecturas($lecturas);

        $activos = [];
        foreach ($this->parametros->listarActivos() as $parametro) {
            $activos[$parametro->codigo()] = $parametro;
        }

        $valores = [];
        $noConfigurados = [];

        foreach ($leidos as $codigo => $valor) {
            $parametro = $activos[$codigo] ?? null;

            if ($parametro === null) {
                $noConfigurados[] = ['codigo' => $codigo, 'valor' => $valor];

                continue;
            }

            $valores[] = [
                'parametro_id' => (int) $parametro->id(),
                'codigo' => $codigo,
                'nombre' => $parametro->nombre(),
                'valor' => $valor,
                'unidad' => $parametro->unidad(),
            ];
        }

        if ($noConfigurados !== []) {
            $this->logger->warning('Ticket del analizador: valores sin parámetro de calidad configurado.', [
                'entrega_id' => $entregaId,
                'codigos' => array_column($noConfigurados, 'codigo'),
            ]);
        }

        $noDetectados = [];
        foreach ($activos as $codigo => $parametro) {
            if (! array_key_exists($codigo, $leidos)) {
                $noDetectados[] = ['codigo' => $codigo, 'nombre' => $parametro->nombre()];
            }
        }

        return [
            'texto_ocr' => implode("\n\n", $lecturas),
            'valores' => $valores,
            'no_detectados' => $noDetectados,
            'no_configurados' => $noConfigurados,
            'suficientes' => count($valores) >= self::MINIMO_VALORES,
        ];
    }
}
