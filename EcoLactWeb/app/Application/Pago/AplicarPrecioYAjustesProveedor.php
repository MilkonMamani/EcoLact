<?php

namespace App\Application\Pago;

use App\Domain\Pago\PrecioLeche;
use App\Domain\Pago\PrecioLecheRepository;
use App\Domain\Pago\ReglaPagoCalidad;
use App\Domain\Pago\ReglaPagoCalidadRepository;
use App\Domain\Shared\Enums\ResultadoCalidad;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Guarda, con vigencia histórica, el precio base por litro y los ajustes por
 * calidad que se configuran junto con el pago del proveedor. La fuente de
 * verdad sigue siendo precios_leche y reglas_pago_calidad (las mismas que lee
 * la valorización de entregas): aquí solo se abre una nueva vigencia cuando el
 * valor cambia, sin sobrescribir el pasado.
 *
 * RECHAZADA no es un ajuste editable: la leche rechazada nunca ingresa a la
 * planta y no se paga (la valorización ya la deja en 0 litros pagables). Solo
 * se asegura que exista su regla, que la valorización necesita.
 */
final class AplicarPrecioYAjustesProveedor
{
    /** Resultados cuyo ajuste se edita desde la configuración. */
    private const EDITABLES = [ResultadoCalidad::ACEPTADA, ResultadoCalidad::OBSERVADA];

    public function __construct(
        private readonly PrecioLecheRepository $preciosLeche,
        private readonly ReglaPagoCalidadRepository $reglasPagoCalidad,
    ) {}

    /**
     * @param  array<string, array{tipo_ajuste?: string|null, valor_ajuste?: float|string|null}>|null  $ajustes  por resultado (ACEPTADA, OBSERVADA)
     */
    public function execute(?float $precioBaseLitro, bool $aplicaCalidad, ?array $ajustes, DateTimeImmutable $inicio, int $actorUserId): void
    {
        $inicio = $inicio->setTime(0, 0);

        if ($precioBaseLitro !== null) {
            $this->aplicarPrecio($precioBaseLitro, $inicio, $actorUserId);
        }

        if ($aplicaCalidad) {
            $this->aplicarAjustes($ajustes ?? [], $inicio);
        }
    }

    private function aplicarPrecio(float $precio, DateTimeImmutable $inicio, int $actorUserId): void
    {
        $vigente = $this->preciosLeche->buscarVigente($inicio);

        if ($vigente !== null && round($vigente->precioLitro(), 4) === round($precio, 4)) {
            return;
        }

        foreach ($this->preciosLeche->listar() as $existente) {
            if ($existente->estado() === 'ACTIVO' && $existente->fechaInicio() > $inicio) {
                throw new DomainException('Ya existe un precio de leche con vigencia desde el '.$existente->fechaInicio()->format('d/m/Y').'. Elige una fecha de inicio posterior.');
            }
        }

        try {
            if ($vigente !== null && $vigente->fechaInicio() == $inicio) {
                $this->preciosLeche->actualizar(new PrecioLeche($vigente->id(), $precio, $vigente->fechaInicio(), $vigente->fechaFin(), $vigente->estado(), $vigente->creadoPorUserId()));

                return;
            }

            if ($vigente !== null) {
                $this->preciosLeche->actualizar(new PrecioLeche($vigente->id(), $vigente->precioLitro(), $vigente->fechaInicio(), $inicio->modify('-1 day'), $vigente->estado(), $vigente->creadoPorUserId()));
            }

            $this->preciosLeche->guardar(new PrecioLeche(null, $precio, $inicio, null, 'ACTIVO', $actorUserId));
        } catch (InvalidArgumentException $e) {
            throw new DomainException($e->getMessage());
        }
    }

    /**
     * @param  array<string, array{tipo_ajuste?: string|null, valor_ajuste?: float|string|null}>  $ajustes
     */
    private function aplicarAjustes(array $ajustes, DateTimeImmutable $inicio): void
    {
        $existentes = $this->reglasPagoCalidad->listar();

        foreach (self::EDITABLES as $resultado) {
            $vigente = $this->reglasPagoCalidad->buscarVigentePorResultado($resultado, $inicio);
            $enviado = $ajustes[$resultado->value] ?? null;
            $tipo = $enviado['tipo_ajuste'] ?? $vigente?->tipoAjuste() ?? 'MONTO';
            $valor = isset($enviado['valor_ajuste']) && $enviado['valor_ajuste'] !== '' ? (float) $enviado['valor_ajuste'] : ($vigente?->valorAjuste() ?? 0.0);

            if (! in_array($tipo, ['MONTO', 'PORCENTAJE'], true)) {
                throw new DomainException('El tipo de ajuste de calidad no es válido.');
            }

            $this->versionarRegla($resultado, $tipo, $valor, $vigente, $existentes, $inicio);
        }

        if ($this->reglasPagoCalidad->buscarVigentePorResultado(ResultadoCalidad::RECHAZADA, $inicio) === null) {
            $this->reglasPagoCalidad->guardar(new ReglaPagoCalidad(null, ResultadoCalidad::RECHAZADA, 'MONTO', 0.0, $inicio, null, 'ACTIVO'));
        }
    }

    /**
     * @param  ReglaPagoCalidad[]  $existentes
     */
    private function versionarRegla(ResultadoCalidad $resultado, string $tipo, float $valor, ?ReglaPagoCalidad $vigente, array $existentes, DateTimeImmutable $inicio): void
    {
        if ($vigente !== null && $vigente->tipoAjuste() === $tipo && round($vigente->valorAjuste(), 4) === round($valor, 4)) {
            return;
        }

        foreach ($existentes as $regla) {
            if ($regla->resultadoCalidad() === $resultado && $regla->estado() === 'ACTIVO' && $regla->fechaInicio() > $inicio) {
                throw new DomainException('Ya existe un ajuste por calidad con vigencia desde el '.$regla->fechaInicio()->format('d/m/Y').'. Elige una fecha de inicio posterior.');
            }
        }

        if ($vigente !== null && $vigente->fechaInicio() == $inicio) {
            $this->reglasPagoCalidad->actualizar(new ReglaPagoCalidad($vigente->id(), $resultado, $tipo, $valor, $vigente->fechaInicio(), $vigente->fechaFin(), $vigente->estado()));

            return;
        }

        if ($vigente !== null) {
            $this->reglasPagoCalidad->actualizar(new ReglaPagoCalidad($vigente->id(), $resultado, $vigente->tipoAjuste(), $vigente->valorAjuste(), $vigente->fechaInicio(), $inicio->modify('-1 day'), $vigente->estado()));
        }

        $this->reglasPagoCalidad->guardar(new ReglaPagoCalidad(null, $resultado, $tipo, $valor, $inicio, null, 'ACTIVO'));
    }
}
