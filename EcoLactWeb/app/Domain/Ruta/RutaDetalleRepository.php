<?php

namespace App\Domain\Ruta;

interface RutaDetalleRepository
{
    public function guardar(RutaDetalle $detalle): RutaDetalle;

    /**
     * Actualiza la ubicación (latitud/longitud/referencia) de una
     * asignación existente. No modifica ruta, proveedor ni orden.
     */
    public function actualizarUbicacion(RutaDetalle $detalle): RutaDetalle;

    public function buscarPorId(int $id): ?RutaDetalle;

    public function buscarPorProveedor(int $proveedorId): ?RutaDetalle;

    /**
     * Elimina la asignación vigente de un proveedor (si existe), como parte
     * de un traslado o de su retiro de la ruta. No toca el historial: quien
     * llama debe cerrar el registro vigente de ProveedorRutaHistorial antes
     * o después, según corresponda.
     */
    public function eliminarPorProveedor(int $proveedorId): void;

    /**
     * @return RutaDetalle[] ordenados por orden_visita ascendente
     */
    public function listarPorRuta(int $rutaId): array;

    /**
     * Siguiente orden_visita disponible al final de la ruta (MAX + 1, o 1 si
     * la ruta no tiene proveedores todavía).
     */
    public function siguienteOrden(int $rutaId): int;

    /**
     * Renumera los órdenes vigentes de la ruta como 1..N sin huecos,
     * conservando el orden relativo actual. Se invoca cada vez que sale un
     * proveedor de la ruta (retiro o traslado) para que nunca queden
     * secuencias como 1, 3, 4.
     */
    public function compactarOrden(int $rutaId): void;

    /**
     * Reordena en una sola transacción, evitando el conflicto temporal del
     * índice único (ruta_id, orden_visita): primero desplaza las filas
     * afectadas a valores negativos únicos (basados en su propio id), luego
     * aplica el orden final.
     *
     * @param  array<int, int>  $ordenPorDetalleId  ruta_detalle.id => nuevo orden_visita
     */
    public function reordenar(int $rutaId, array $ordenPorDetalleId): void;
}
