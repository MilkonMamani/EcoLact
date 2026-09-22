<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Calidad\AnalisisCalidadRepository;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Proveedor\ProveedorRepository;

/**
 * Lista los análisis de calidad de las entregas del proveedor autenticado.
 * Recibe siempre el id del usuario autenticado (nunca un proveedor_id
 * manipulable desde el navegador): resuelve el proveedor propio, obtiene
 * sus entregas y, de cada una, su análisis (si existe). No hay un método de
 * repositorio "listar análisis por proveedor" porque analisis_calidad no
 * tiene proveedor_id propio (se relaciona vía entrega_id, igual que
 * pagos_proveedor se relaciona vía liquidacion_id).
 */
final class ObtenerAnalisisProveedor
{
    public function __construct(
        private readonly ProveedorRepository $proveedores,
        private readonly EntregaRepository $entregas,
        private readonly AnalisisCalidadRepository $analisisCalidad,
    ) {}

    /**
     * @return AnalisisCalidad[]
     */
    public function execute(int $userId): array
    {
        $proveedor = $this->proveedores->buscarPorUserId($userId);

        if ($proveedor === null) {
            return [];
        }

        $analisis = [];

        foreach ($this->entregas->listarPorProveedor($proveedor->id()) as $entrega) {
            $unAnalisis = $this->analisisCalidad->buscarPorEntregaId($entrega->id());

            if ($unAnalisis !== null) {
                $analisis[] = $unAnalisis;
            }
        }

        return $analisis;
    }
}
