<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Auditoria\AuditoriaRepository;
use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\UnidadMedida;
use App\Domain\Shared\Enums\TipoInsumo;
use DomainException;

/**
 * Edita los datos de la ficha de un insumo (nombre, descripción, stock
 * mínimo, estado y tipo). Nunca toca el stock (eso solo lo cambian los
 * movimientos).
 *
 * La unidad base solo se puede CORREGIR mientras nada guardado esté
 * expresado en ella (EvaluarCambioUnidadBase): una "Sal en ml" recién creada
 * pasa a "kg" sin convertir nada, porque no hay nada que convertir. Con
 * historial se rechaza: cambiarla reinterpretaría todas sus cantidades. El
 * cambio queda en `auditorias`.
 *
 * Protección del insumo del sistema (LECHE): no se puede desactivar ni
 * cambiar de tipo ni de unidad, porque las entregas de proveedores necesitan
 * poder ingresar leche y las recetas la usan.
 */
final class ActualizarInsumo
{
    public const ACCION_CAMBIO_UNIDAD = 'CAMBIO_UNIDAD_BASE_INSUMO';

    public function __construct(
        private readonly InsumoRepository $insumos,
        private readonly EvaluarCambioUnidadBase $evaluarCambioUnidad,
        private readonly AuditoriaRepository $auditorias,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $insumoId,
        string $nombre,
        ?string $descripcion,
        float $stockMinimo,
        string $estado,
        ?TipoInsumo $tipo = null,
        ?string $unidadBase = null,
        ?int $userId = null,
    ): Insumo {
        return $this->transacciones->run(function () use ($insumoId, $nombre, $descripcion, $stockMinimo, $estado, $tipo, $unidadBase, $userId): Insumo {
            $insumo = $this->insumos->bloquearPorId($insumoId)
                ?? throw new DomainException('El insumo indicado no existe.');

            $nombre = trim($nombre);

            $otro = $this->insumos->buscarPorNombre($nombre);

            if ($otro !== null && $otro->id() !== $insumo->id()) {
                throw new DomainException("Ya existe un insumo registrado con el nombre {$nombre}.");
            }

            if ($insumo->esDelSistema()) {
                if ($estado !== 'ACTIVO') {
                    throw new DomainException("{$insumo->nombre()} es un insumo del sistema y no se puede desactivar.");
                }

                if ($tipo !== null && $tipo !== $insumo->tipo()) {
                    throw new DomainException("{$insumo->nombre()} es un insumo del sistema y no se puede cambiar de tipo.");
                }
            }

            if (! in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
                throw new DomainException('El estado del insumo debe ser ACTIVO o INACTIVO.');
            }

            $cambiaUnidad = $unidadBase !== null && $unidadBase !== $insumo->unidadBase();

            if ($cambiaUnidad) {
                if (! UnidadMedida::esValida($unidadBase)) {
                    throw new DomainException('La unidad base no es válida. Use: '.implode(', ', UnidadMedida::codigos()).'.');
                }

                $motivos = $this->evaluarCambioUnidad->motivos($insumo);

                if ($motivos !== []) {
                    throw new DomainException("No se puede cambiar la unidad base de {$insumo->nombre()} porque ".implode(', ', $motivos).'. Sus cantidades guardadas están en '.$insumo->unidadBase().'.');
                }
            }

            $actualizado = $this->insumos->actualizar(new Insumo(
                id: $insumo->id(),
                nombre: $nombre,
                unidadBase: $cambiaUnidad ? $unidadBase : $insumo->unidadBase(),
                stockActual: $insumo->stockActual(),
                stockMinimo: $stockMinimo,
                estado: $estado,
                tipo: $tipo ?? $insumo->tipo(),
                codigo: $insumo->codigo(),
                descripcion: $descripcion,
            ));

            if ($cambiaUnidad) {
                $this->auditorias->registrar(
                    userId: $userId,
                    accion: self::ACCION_CAMBIO_UNIDAD,
                    tablaAfectada: 'insumos',
                    registroId: $insumo->id(),
                    datosAnteriores: ['unidad_base' => $insumo->unidadBase(), 'stock_minimo' => $insumo->stockMinimo()],
                    datosNuevos: ['unidad_base' => $actualizado->unidadBase(), 'stock_minimo' => $actualizado->stockMinimo()],
                    motivo: 'Corrección de la unidad base de un insumo sin historial.',
                );
            }

            return $actualizado;
        });
    }
}
