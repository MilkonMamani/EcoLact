<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\UnidadMedida;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use App\Domain\Shared\Enums\TipoInsumo;
use DomainException;

/**
 * Da de alta un insumo en el catálogo (cuajo, sal, azúcar, envases...), con
 * su unidad base y el umbral mínimo que dispara la alerta de reposición. El
 * stock inicial, si lo hay, entra como un movimiento AJUSTE_ENTRADA para que
 * el historial de movimientos respalde siempre el saldo.
 *
 * Los insumos del sistema (LECHE) NO se crean por aquí: los crea la
 * migración y se identifican por su código.
 */
final class RegistrarInsumo
{
    public function __construct(
        private readonly InsumoRepository $insumos,
        private readonly RegistrarMovimientoInsumo $registrarMovimiento,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        string $nombre,
        string $unidadBase,
        float $stockMinimo,
        float $stockInicial = 0.0,
        TipoInsumo $tipo = TipoInsumo::INSUMO,
        ?string $descripcion = null,
        ?int $registradoPorUserId = null,
    ): Insumo {
        $nombre = trim($nombre);

        if (mb_strtolower($nombre) === 'leche') {
            throw new DomainException('La leche ya existe como insumo del sistema y no se puede crear otra.');
        }

        if ($this->insumos->buscarPorNombre($nombre) !== null) {
            throw new DomainException("Ya existe un insumo registrado con el nombre {$nombre}.");
        }

        if (! UnidadMedida::esValida($unidadBase)) {
            throw new DomainException('La unidad base no es válida. Use: '.implode(', ', UnidadMedida::codigos()).'.');
        }

        if ($stockInicial > 0.0 && $registradoPorUserId === null) {
            throw new DomainException('Para registrar stock inicial se necesita saber quién lo registra.');
        }

        return $this->transacciones->run(function () use ($nombre, $unidadBase, $stockMinimo, $stockInicial, $tipo, $descripcion, $registradoPorUserId): Insumo {
            $insumo = $this->insumos->guardar(new Insumo(
                id: null,
                nombre: $nombre,
                unidadBase: $unidadBase,
                stockActual: 0.0,
                stockMinimo: $stockMinimo,
                estado: 'ACTIVO',
                tipo: $tipo,
                codigo: null,
                descripcion: $descripcion,
            ));

            if ($stockInicial > 0.0) {
                $this->registrarMovimiento->execute(
                    insumoId: $insumo->id() ?? throw new DomainException('El insumo no tiene identificador válido.'),
                    motivo: MotivoMovimientoInsumo::AJUSTE_ENTRADA,
                    direccion: MotivoMovimientoInsumo::ENTRADA,
                    cantidad: $stockInicial,
                    registradoPorUserId: $registradoPorUserId,
                    observacion: 'Stock inicial al registrar el insumo.',
                );

                return $this->insumos->buscarPorId($insumo->id()) ?? $insumo;
            }

            return $insumo;
        });
    }
}
