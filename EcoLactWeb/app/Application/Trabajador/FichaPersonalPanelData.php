<?php

namespace App\Application\Trabajador;

/**
 * Panel lateral de un analista de calidad, encargado de planta o personal de
 * ventas (admin). Mismos datos personales y QR que el panel de Acopiadores;
 * lo único propio de cada rol son sus métricas y su actividad.
 *
 * El QR es el de su identidad laboral (trabajadores.qr_token, el mismo del
 * control de asistencia); es null si todavía no se le generó.
 *
 * Forma de cada fila de $actividad según el tipo:
 * - ANALISTA_CALIDAD: array{id: int, fecha: string, entregaId: int, proveedor: string, resultado: ?string, estado: string}
 * - ENCARGADO_PLANTA: array{id: int, fecha: string, codigo: string, productos: string, litros: float, estado: string}
 * - PERSONAL_VENTAS: array{id: int, fecha: string, cliente: string, tipoCliente: string, total: float, formaPago: string, estado: string}
 */
final readonly class FichaPersonalPanelData
{
    /**
     * @param  list<array{etiqueta: string, valor: string}>  $metricas
     * @param  list<array<string, mixed>>  $actividad  las más recientes (hasta el límite de la query)
     * @param  int  $actividadTotal  total de la actividad con el filtro aplicado
     */
    public function __construct(
        public TipoFichaPersonal $tipo,
        public int $id,
        public int $userId,
        public string $nombres,
        public string $apellidos,
        public string $dni,
        public ?string $telefono,
        public string $estado,
        public ?string $fechaIngreso,
        public ?string $qrToken,
        public array $metricas,
        public array $actividad,
        public int $actividadTotal,
        public ?string $tipoCliente = null,
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
