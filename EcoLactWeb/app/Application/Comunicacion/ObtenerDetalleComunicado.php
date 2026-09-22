<?php

namespace App\Application\Comunicacion;

use App\Domain\Comunicacion\Repositories\ComunicadoRepository;
use App\Domain\Proveedor\ProveedorRepository;

final class ObtenerDetalleComunicado
{
    public function __construct(
        private readonly ComunicadoRepository $comunicados,
        private readonly ProveedorRepository $proveedores,
    ) {}

    public function execute(int $comunicadoId): ?ComunicadoDetalleData
    {
        $comunicado = $this->comunicados->buscarPorId($comunicadoId);

        if ($comunicado === null) {
            return null;
        }

        $destinatarios = [];
        $leidos = 0;

        foreach ($this->comunicados->listarDestinatarios($comunicadoId) as $destinatario) {
            $proveedor = $this->proveedores->buscarPorUserId($destinatario['userId']);

            $destinatarios[] = [
                'userId' => $destinatario['userId'],
                'nombre' => $proveedor?->nombreCompleto() ?? 'Usuario #'.$destinatario['userId'],
                'dni' => $proveedor?->dni(),
                'leido' => $destinatario['leido'],
                'fechaLectura' => $destinatario['fechaLectura']?->format('d/m/Y H:i'),
            ];

            if ($destinatario['leido']) {
                $leidos++;
            }
        }

        return new ComunicadoDetalleData(
            comunicado: $comunicado,
            destinatarios: $destinatarios,
            leidos: $leidos,
            noLeidos: count($destinatarios) - $leidos,
        );
    }
}
