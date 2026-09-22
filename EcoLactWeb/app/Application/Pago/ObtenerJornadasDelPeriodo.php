<?php

namespace App\Application\Pago;

use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Pago\ObligacionPagoRepository;

/** Datos del modal "Ver jornadas": las jornadas del periodo abierto (o el más reciente) de un trabajador. */
final class ObtenerJornadasDelPeriodo
{
    public function __construct(
        private readonly ObligacionPagoRepository $obligaciones,
        private readonly JornadaPersonalRepository $jornadas,
    ) {}

    /**
     * @return JornadaPersonalFila[]
     */
    public function execute(int $userId): array
    {
        $obligacion = $this->obligaciones->buscarPersonalAbiertaPorUsuario($userId)
            ?? $this->obligaciones->buscarPersonalMasRecientePorUsuario($userId);

        if ($obligacion === null) {
            return [];
        }

        return array_map(
            fn (JornadaPersonal $jornada): JornadaPersonalFila => new JornadaPersonalFila(
                fechaIso: $jornada->fecha()->format('Y-m-d'),
                fechaEtiqueta: $jornada->fecha()->format('d/m/Y'),
                observacion: $jornada->observacion(),
            ),
            $this->jornadas->listarEnPeriodo($userId, $obligacion->periodoInicio(), $obligacion->periodoFin()),
        );
    }
}
