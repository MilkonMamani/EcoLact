<?php

namespace App\Application\Zona;

use App\Domain\Zona\DelimitacionZona;

/**
 * Panel lateral de una zona en la pantalla de Zonas (admin): cabecera,
 * Resumen (datos, métricas de la semana EcoLact en curso, rutas y mapa) y
 * las pestañas Proveedores y Acopiadores.
 */
final readonly class ZonaPanelData
{
    /**
     * @param  list<array{id: int, nombre: string, estado: string}>  $rutas
     * @param  list<array{fila: ProveedorAsignadoFila, telefono: ?string, ultimaEntrega: ?string, litrosSemana: float}>  $proveedores
     * @param  list<array{id: int, nombre: string, dni: string, telefono: ?string, estado: string, entregasSemana: int, litrosSemana: float}>  $acopiadores
     */
    public function __construct(
        public int $id,
        public string $nombre,
        public ?string $descripcion,
        public string $estado,
        public ?string $creadaEl,
        public float $litrosSemana,
        public array $rutas,
        public array $proveedores,
        public array $acopiadores,
        public string $colorMapa = '#16a34a',
        public ?DelimitacionZona $delimitacion = null,
    ) {}

    public function estaActiva(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    /** @return list<ProveedorAsignadoFila> */
    public function proveedoresConUbicacion(): array
    {
        return array_values(array_filter(
            array_column($this->proveedores, 'fila'),
            fn (ProveedorAsignadoFila $fila): bool => $fila->tieneUbicacion(),
        ));
    }

    public function proveedoresSinUbicacion(): int
    {
        return count($this->proveedores) - count($this->proveedoresConUbicacion());
    }

    /**
     * Proveedores cuya ubicación registrada cae fuera de la delimitación
     * operativa de la zona. Solo es un aviso: no bloquea la asignación.
     *
     * @return list<ProveedorAsignadoFila>
     */
    public function proveedoresFueraDeDelimitacion(): array
    {
        if ($this->delimitacion === null) {
            return [];
        }

        return array_values(array_filter(
            $this->proveedoresConUbicacion(),
            fn (ProveedorAsignadoFila $fila): bool => ! $this->delimitacion->contiene($fila->latitud, $fila->longitud),
        ));
    }

    /** @return list<array{id: int, nombre: string, estado: string}> */
    public function rutasActivas(): array
    {
        return array_values(array_filter($this->rutas, fn (array $ruta): bool => $ruta['estado'] === 'ACTIVO'));
    }
}
