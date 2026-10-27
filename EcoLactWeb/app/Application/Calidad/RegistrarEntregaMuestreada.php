<?php

namespace App\Application\Calidad;

use App\Application\Shared\TransactionManager;
use App\Domain\Calidad\MuestreoCalidad;
use App\Domain\Calidad\MuestreoCalidadRepository;
use App\Domain\Entrega\Entrega;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\EstadoMuestreo;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Shared\Enums\MetodoSeleccionMuestreo;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * MUESTREO DIRECTO: el analista llega al proveedor, lo identifica (QR o DNI)
 * y toma la muestra directamente, ANTES del acopio normal (inspección
 * sorpresa). Es el flujo secundario de Calidad; el principal es seleccionar
 * una entrega ya registrada por el acopiador (SeleccionarMuestraCalidad).
 *
 * Crea la entrega de la muestra (sin acopiador: acopiadorId queda null) y su
 * muestreo DIRECTA en estado SELECCIONADA, así aparece de inmediato en
 * "Muestras pendientes" para analizarla con el flujo de siempre. Esos litros
 * son solo la muestra: esta entrega NO genera ingreso de leche (ver
 * RegistrarEntrega).
 */
final class RegistrarEntregaMuestreada
{
    private const METODOS_SOPORTADOS = ['QR', 'DNI'];

    public function __construct(
        private readonly ProveedorRepository $proveedores,
        private readonly EntregaRepository $entregas,
        private readonly MuestreoCalidadRepository $muestreos,
        private readonly TransactionManager $transacciones,
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

        $ahora = new DateTimeImmutable;

        return $this->transacciones->run(function () use ($proveedor, $metodoIdentificacion, $analistaUserId, $litros, $observacion, $ahora): Entrega {
            $entrega = $this->entregas->guardar(new Entrega(
                id: null,
                proveedorId: $proveedor->id(),
                acopiadorId: null,
                zonaId: $proveedor->zonaId(),
                registradoPorUserId: $analistaUserId,
                fechaHora: $ahora,
                litros: $litros,
                metodoIdentificacion: $metodoIdentificacion,
                estado: EstadoEntrega::PENDIENTE_ANALISIS,
                observacion: $observacion,
            ));

            $this->muestreos->guardar(new MuestreoCalidad(
                id: null,
                entregaId: $entrega->id() ?? throw new DomainException('No se pudo registrar la entrega de la muestra.'),
                seleccionadoPorUserId: $analistaUserId,
                metodoSeleccion: MetodoSeleccionMuestreo::DIRECTA,
                tipoControl: null,
                fechaSeleccion: $ahora,
                estado: EstadoMuestreo::SELECCIONADA,
                observacion: $observacion,
            ));

            return $entrega;
        });
    }
}
