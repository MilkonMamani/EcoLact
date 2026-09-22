<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\Receta;
use App\Domain\Produccion\RecetaRepository;
use DomainException;

/**
 * Edita una receta que NUNCA se usó en una producción (ingredientes,
 * cantidades, observaciones). Una receta ya usada no se modifica (dejaría
 * mentiras en el historial): para eso está GuardarCambiosReceta, que crea una
 * nueva versión. Editar tampoco toca el inventario ni la imagen del producto.
 */
final class ActualizarReceta
{
    public function __construct(
        private readonly RecetaRepository $recetas,
        private readonly ConstructorIngredientesReceta $ingredientes,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @param  list<array{insumo_id: int|string, cantidad: float|int|string, unidad: string, observacion?: string|null}>  $ingredientes
     */
    public function execute(int $recetaId, ?string $descripcion, array $ingredientes): Receta
    {
        $receta = $this->recetas->buscarPorId($recetaId)
            ?? throw new DomainException('La receta indicada no existe.');

        if ($this->recetas->estaEnUso($recetaId)) {
            throw new DomainException('Esta receta ya fue usada en una producción y no se puede modificar. Cree una nueva versión.');
        }

        $detalles = $this->ingredientes->construir($ingredientes);

        return $this->transacciones->run(fn (): Receta => $this->recetas->actualizar($receta->con(
            descripcion: $descripcion,
            detalles: $detalles,
        )));
    }
}
