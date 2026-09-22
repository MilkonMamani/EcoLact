<?php

namespace App\Application\Calidad;

use App\Domain\Entrega\Entrega;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\EstadoUsuario;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Registra la entrega de una muestra que el analista de calidad toma
 * directamente del proveedor, ANTES de que pase por el acopiador — a
 * diferencia de RegistrarEntrega (exclusivo del Acopiador), aquí no hay
 * acopiador involucrado todavía (acopiadorId queda null).
 *
 * Este es el caso de un proveedor elegido al azar para muestreo: si el
 * análisis resulta ACEPTADA u OBSERVADA, el analista avisa verbalmente al
 * acopiador y es este quien registra la entrega normalmente (flujo de
 * siempre, sin cambios). Este Use Case es solo para cuando el resultado va
 * a ser RECHAZADA y la leche nunca llega a acopiarse: aun así queda un
 * registro (entrega + análisis) para que el historial de calidad, las
 * advertencias y el conteo de sanciones del proveedor sigan funcionando
 * igual que con cualquier otra entrega. Esos litros nunca cuentan como
 * inventario de leche: esta entrega NO genera ingreso de leche (ver RegistrarEntrega).
 */
final class RegistrarEntregaMuestreada
{
    private const METODOS_SOPORTADOS = ['QR', 'DNI'];

    public function __construct(
        private readonly ProveedorRepository $proveedores,
        private readonly EntregaRepository $entregas,
    ) {}

    public function execute(
        string $metodoIdentificacion,
        string $identificador,
        int $analistaUserId,
        float $litros,
        ?string $observacion = null,
    ): Entrega {
        $proveedor = match ($metodoIdentificacion) {
            'QR' => $this->proveedores->buscarPorQrToken($identificador),
            'DNI' => $this->proveedores->buscarPorDni($identificador),
            default => throw new InvalidArgumentException(
                sprintf('Método de identificación no soportado: %s. Use %s.', $metodoIdentificacion, implode(' o ', self::METODOS_SOPORTADOS))
            ),
        };

        if ($proveedor === null) {
            throw new DomainException('No se encontró un proveedor con el identificador indicado.');
        }

        if ($proveedor->estado() !== EstadoUsuario::ACTIVO) {
            throw new DomainException('El proveedor no está habilitado para registrar entregas.');
        }

        return $this->entregas->guardar(new Entrega(
            id: null,
            proveedorId: $proveedor->id(),
            acopiadorId: null,
            zonaId: $proveedor->zonaId(),
            registradoPorUserId: $analistaUserId,
            fechaHora: new DateTimeImmutable,
            litros: $litros,
            metodoIdentificacion: $metodoIdentificacion,
            estado: EstadoEntrega::PENDIENTE_ANALISIS,
            observacion: $observacion,
        ));
    }
}
