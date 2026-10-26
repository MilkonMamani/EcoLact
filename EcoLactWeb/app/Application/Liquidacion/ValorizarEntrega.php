<?php

namespace App\Application\Liquidacion;

use App\Domain\Calidad\AnalisisCalidadRepository;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Pago\PrecioLecheRepository;
use App\Domain\Pago\ReglaPagoCalidadRepository;
use App\Domain\Pago\ValorizacionEntrega;
use App\Domain\Pago\ValorizacionEntregaRepository;
use App\Domain\Shared\Enums\ResultadoCalidad;
use DateTimeImmutable;
use DomainException;

/**
 * Calcula y persiste la valorización individual de una entrega ya
 * analizada: toma el resultado de calidad CONFIRMADO, el precio base
 * vigente y la regla de pago por calidad vigente, y calcula el precio final
 * e importe de ESA entrega. Ninguno de estos valores se hardcodea: todos
 * provienen de precios_leche y reglas_pago_calidad.
 */
final class ValorizarEntrega
{
    public function __construct(
        private readonly EntregaRepository $entregas,
        private readonly AnalisisCalidadRepository $analisisCalidad,
        private readonly PrecioLecheRepository $preciosLeche,
        private readonly ReglaPagoCalidadRepository $reglasPagoCalidad,
        private readonly ValorizacionEntregaRepository $valorizaciones,
    ) {}

    public function execute(int $entregaId): ValorizacionEntrega
    {
        $entrega = $this->entregas->buscarPorId($entregaId);

        if ($entrega === null) {
            throw new DomainException('La entrega indicada no existe.');
        }

        $existente = $this->valorizaciones->buscarPorEntregaId($entregaId);

        if ($existente !== null) {
            // Una valorización sin análisis (precio base) ya existe. Que el
            // análisis se confirme después no debe fallar ni cambiar lo ya calculado.
            if ($existente->analisisId() === null) {
                return $existente;
            }

            throw new DomainException('La entrega ya fue valorizada.');
        }

        $analisis = $this->analisisCalidad->buscarPorEntregaId($entregaId);

        if ($analisis === null || ! $analisis->estaConfirmado()) {
            throw new DomainException('La entrega no tiene un análisis de calidad confirmado.');
        }

        $resultado = $analisis->resultado() ?? throw new DomainException('El análisis confirmado no tiene un resultado de calidad.');

        $precioLeche = $this->preciosLeche->buscarVigente($entrega->fechaHora());

        if ($precioLeche === null) {
            throw new DomainException('No existe un precio base de leche vigente para la fecha de la entrega.');
        }

        $reglaPago = $this->reglasPagoCalidad->buscarVigentePorResultado($resultado, $entrega->fechaHora());

        if ($reglaPago === null) {
            throw new DomainException('No existe una regla de pago por calidad vigente para el resultado obtenido.');
        }

        // ACEPTADA es siempre el precio normal: nunca suma un ajuste al precio base.
        $ajustePorLitro = $resultado === ResultadoCalidad::ACEPTADA ? 0.0 : $reglaPago->calcularAjuste($precioLeche->precioLitro());
        $precioFinalLitro = max(0.0, round($precioLeche->precioLitro() + $ajustePorLitro, 4));
        // Leche RECHAZADA no ingresa físicamente a la planta: no se paga.
        // El precio final por litro de la SEMANA (no de este día) lo
        // determina GenerarLiquidacion en función de si hubo alguna
        // incidencia en el periodo; esta valorización individual solo deja
        // constancia informativa del análisis de ESTA entrega.
        $litrosPagables = $resultado === ResultadoCalidad::RECHAZADA ? 0.0 : $entrega->litros();
        $importeTotal = round($litrosPagables * $precioFinalLitro, 2);

        return $this->valorizaciones->guardar(new ValorizacionEntrega(
            id: null,
            entregaId: $entregaId,
            analisisId: $analisis->id() ?? throw new DomainException('El análisis confirmado no tiene identificador válido.'),
            precioLecheId: $precioLeche->id() ?? throw new DomainException('El precio de leche vigente no tiene identificador válido.'),
            reglaPagoId: $reglaPago->id() ?? throw new DomainException('La regla de pago vigente no tiene identificador válido.'),
            litrosEntregados: $entrega->litros(),
            litrosPagables: $litrosPagables,
            precioBaseLitro: $precioLeche->precioLitro(),
            ajustePorLitro: $ajustePorLitro,
            precioFinalLitro: $precioFinalLitro,
            importeTotal: $importeTotal,
            fechaCalculo: new DateTimeImmutable,
        ));
    }
}
