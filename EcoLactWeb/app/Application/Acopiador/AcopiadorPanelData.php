<?php

namespace App\Application\Acopiador;

/**
 * Panel lateral de un acopiador en la pantalla de Acopiadores (admin):
 * cabecera, Resumen (QR, datos personales y resumen de zona/entregas) y
 * los historiales de las pestañas Entregas y Zona.
 *
 * El QR es el de su identidad laboral (trabajadores.qr_token, el mismo que
 * usa el control de asistencia): el acopiador no tiene otro token. Es null
 * si todavía no se le generó.
 */
final readonly class AcopiadorPanelData
{
    /**
     * @param  list<array{id: int, fecha: string, proveedor: string, zona: string, litros: float, identificacion: string, estado: string}>  $entregas
     * @param  list<array{zona: string, desde: string, hasta: ?string, motivo: ?string}>  $historialZonas
     */
    public function __construct(
        public int $id,
        public int $userId,
        public string $nombres,
        public string $apellidos,
        public string $dni,
        public ?string $telefono,
        public string $estado,
        public ?string $fechaIngreso,
        public int $zonaId,
        public string $zona,
        public ?string $qrToken,
        public array $entregas,
        public int $entregasTotal,
        public float $litrosTotales,
        public array $historialZonas,
    ) {}

    public function nombreCompleto(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    public function iniciales(): string
    {
        $primera = fn (string $texto): string => mb_strtoupper(mb_substr(trim($texto), 0, 1));

        return $primera($this->nombres).$primera($this->apellidos);
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }
}
