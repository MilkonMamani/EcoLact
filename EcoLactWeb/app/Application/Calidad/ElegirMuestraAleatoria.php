<?php

namespace App\Application\Calidad;

use App\Application\Calidad\Contracts\FirmaPropuestaMuestra;
use App\Application\Calidad\Contracts\MuestreosCalidadQuery;

/**
 * Propone al azar UNA entrega elegible para control de calidad. El azar
 * ocurre aquí (servidor), nunca en el teléfono ni en el navegador. No guarda
 * nada: el analista confirma la propuesta con SeleccionarMuestraCalidad
 * (método ALEATORIA, con la firma del sorteo) o pide otra, excluyendo las
 * que ya vio.
 */
final class ElegirMuestraAleatoria
{
    public function __construct(
        private readonly MuestreosCalidadQuery $muestreos,
        private readonly FirmaPropuestaMuestra $firma,
    ) {}

    /**
     * @param  list<int>  $excluir  Entregas ya propuestas en esta ronda ("Elegir otra").
     * @return array{entrega: array<string, mixed>, propuesta_token: string}|null null si no hay ninguna elegible.
     */
    public function execute(RangoMuestreo $rango, int $analistaUserId, ?int $zonaId = null, array $excluir = []): ?array
    {
        $desde = $rango->desde->toDateTimeImmutable();
        $hasta = $rango->hasta->toDateTimeImmutable();
        $ids = $this->muestreos->idsElegibles($zonaId, $desde, $hasta, $excluir);

        if ($ids === [] && $excluir !== []) {
            // Ya se vieron todas: se vuelve a sortear entre el universo completo.
            $ids = $this->muestreos->idsElegibles($zonaId, $desde, $hasta);
        }

        if ($ids === []) {
            return null;
        }

        $entregaId = $ids[random_int(0, count($ids) - 1)];

        return [
            'entrega' => $this->muestreos->entrega($entregaId),
            'propuesta_token' => $this->firma->firmar($entregaId, $analistaUserId),
        ];
    }
}
