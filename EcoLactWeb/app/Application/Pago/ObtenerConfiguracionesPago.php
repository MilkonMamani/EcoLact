<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\SueldosTrabajadoresQuery;
use App\Domain\Pago\ConfiguracionPago;
use App\Domain\Pago\ConfiguracionPagoRepository;
use App\Domain\Pago\PrecioLeche;
use App\Domain\Pago\PrecioLecheRepository;
use App\Domain\Pago\ReglaPagoCalidadRepository;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DateTimeImmutable;

/**
 * Datos de Configuración de pagos, en sus dos pestañas. Solo valores base: nada
 * de asistencia, entregas ni pagos (eso es Pagos).
 *
 * - trabajadores: cada trabajador real con su sueldo (SueldosTrabajadoresQuery).
 * - proveedor: la configuración GENERAL de pago a proveedores, vigente (o la
 *   próxima programada), con el precio base vigente, el descuento por litro de
 *   la calidad OBSERVADA y los precios anteriores (historial).
 */
final class ObtenerConfiguracionesPago
{
    public function __construct(
        private readonly SueldosTrabajadoresQuery $sueldos,
        private readonly ConfiguracionPagoRepository $configuraciones,
        private readonly PrecioLecheRepository $preciosLeche,
        private readonly ReglaPagoCalidadRepository $reglasPagoCalidad,
    ) {}

    /**
     * @return array{
     *     trabajadores: list<array<string, mixed>>,
     *     proveedor: array{configuracion: ConfiguracionPago|null, estado: string, precioBase: float|null, observada: array{tipo: string, valor: float}|null, preciosAnteriores: PrecioLeche[]}
     * }
     */
    public function execute(DateTimeImmutable $hoy): array
    {
        return [
            'trabajadores' => $this->sueldos->listar($hoy),
            'proveedor' => $this->proveedor($hoy),
        ];
    }

    /**
     * @return array{configuracion: ConfiguracionPago|null, estado: string, precioBase: float|null, observada: array{tipo: string, valor: float}|null, preciosAnteriores: PrecioLeche[]}
     */
    private function proveedor(DateTimeImmutable $hoy): array
    {
        $historial = array_values(array_filter(
            $this->configuraciones->listarHistorial(TipoBeneficiarioPago::PROVEEDOR, null),
            fn (ConfiguracionPago $configuracion): bool => $configuracion->estado() === 'ACTIVO',
        ));

        $vigente = null;
        foreach ($historial as $configuracion) {
            if ($configuracion->estaVigente($hoy)) {
                $vigente = $configuracion;
                break;
            }
        }

        $programadas = array_filter($historial, fn (ConfiguracionPago $c): bool => $c->fechaInicio() > $hoy);
        $mostrada = $vigente ?? ($programadas !== [] ? end($programadas) : null);
        $fechaReferencia = $mostrada !== null && $vigente === null ? $mostrada->fechaInicio() : $hoy;

        $precioVigente = $this->preciosLeche->buscarVigente($fechaReferencia);
        $observada = $this->reglasPagoCalidad->buscarVigentePorResultado(ResultadoCalidad::OBSERVADA, $fechaReferencia);

        return [
            'configuracion' => $mostrada,
            'estado' => match (true) {
                $vigente !== null => 'ACTIVO',
                $mostrada !== null => 'PROGRAMADO',
                default => 'SIN_CONFIGURAR',
            },
            'precioBase' => $precioVigente?->precioLitro(),
            // El descuento se muestra en positivo ("- S/ 0.20 / L"); en la regla se guarda como ajuste negativo.
            'observada' => $observada !== null && ($mostrada?->aplicaCalidad() ?? false)
                ? ['tipo' => $observada->tipoAjuste(), 'valor' => abs($observada->valorAjuste())]
                : null,
            'preciosAnteriores' => array_values(array_filter(
                $this->preciosLeche->listar(),
                fn (PrecioLeche $precio): bool => $precio->estado() === 'ACTIVO' && $precio->fechaFin() !== null && $precio->fechaFin() < $fechaReferencia,
            )),
        ];
    }
}
