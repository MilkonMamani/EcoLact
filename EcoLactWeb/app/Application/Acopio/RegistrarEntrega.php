<?php

namespace App\Application\Acopio;

use App\Application\Pago\SincronizarPagosProveedor;
use App\Application\Produccion\RegistrarIngresoLecheDesdeEntrega;
use App\Application\Shared\TransactionManager;
use App\Domain\Acopiador\AcopiadorRepository;
use App\Domain\Comunicacion\Notificacion;
use App\Domain\Comunicacion\Repositories\NotificacionRepository;
use App\Domain\Entrega\Entrega;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\EstadoUsuario;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Registra una entrega de leche identificando al proveedor por QR estático o
 * DNI, verificando que el proveedor esté habilitado y que el acopiador que
 * la registra esté ACTIVO. La entrega nace en estado PENDIENTE_ANALISIS
 * (pendiente de análisis de calidad). Un acopiador INACTIVO nunca puede
 * registrar una entrega nueva.
 *
 * Al guardarse, la leche entra automáticamente al inventario de insumos
 * (movimiento INGRESO_ACOPIO sobre LECHE) en la misma transacción. La calidad
 * NO bloquea ese ingreso: el análisis es un monitoreo del proveedor, no un
 * paso obligatorio de cada entrega. Tampoco bloquea el pago: en la misma
 * transacción se genera o actualiza el pago pendiente del proveedor.
 *
 * Cuando la entrega se origina en una parada de un recorrido de acopio
 * ($recorridoDetalleId no nulo), la pertenencia del proveedor a ese
 * recorrido ya fue verificada por el llamador
 * (App\Application\Recorrido\RegistrarAcopioDeRecorrido, que además exige
 * que el recorrido pertenezca al propio acopiador) y NO se repite aquí la
 * comprobación de "misma zona": la autorización real es "el proveedor es
 * una parada de un recorrido que le pertenece", más precisa que comparar
 * zonas. Esa comparación de zona solo se conserva para el flujo manual sin
 * recorrido (compatibilidad con el registro directo ya existente).
 *
 * $tokenIdempotencia, si se indica, hace esta operación segura ante doble
 * envío/reintento/concurrencia: si ya existe una entrega con ese token, se
 * devuelve tal cual (sin duplicar ni volver a notificar). La defensa real
 * contra una carrera concurrente es el índice único de la columna en base
 * de datos (ver migración alter_entregas_add_recorrido_y_token); la
 * comprobación previa aquí solo evita una consulta de inserción innecesaria
 * en el caso común.
 */
final class RegistrarEntrega
{
    private const METODOS_SOPORTADOS = ['QR', 'DNI'];

    public function __construct(
        private readonly ProveedorRepository $proveedores,
        private readonly AcopiadorRepository $acopiadores,
        private readonly EntregaRepository $entregas,
        private readonly NotificacionRepository $notificaciones,
        private readonly RegistrarIngresoLecheDesdeEntrega $ingresoLeche,
        private readonly SincronizarPagosProveedor $pagosProveedor,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        string $metodoIdentificacion,
        string $identificador,
        int $acopiadorUserId,
        float $litros,
        ?DateTimeImmutable $fechaHora = null,
        ?string $observacion = null,
        bool $esAdministrador = false,
        ?int $recorridoDetalleId = null,
        ?string $tokenIdempotencia = null,
    ): Entrega {
        if ($tokenIdempotencia !== null) {
            $existente = $this->entregas->buscarPorTokenIdempotencia($tokenIdempotencia);

            if ($existente !== null) {
                // El token lo genera el teléfono: nunca devolver la entrega de otra persona.
                if ($existente->registradoPorUserId() !== $acopiadorUserId) {
                    throw new DomainException('La clave de idempotencia ya fue usada por otro usuario.');
                }

                return $existente;
            }
        }

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

        $acopiador = $esAdministrador
            ? null
            : $this->acopiadores->buscarPorUserId($acopiadorUserId);

        if (! $esAdministrador && $acopiador === null) {
            throw new DomainException('No se encontró un acopiador asociado al usuario indicado.');
        }

        if (! $esAdministrador && $acopiador->estado() !== EstadoUsuario::ACTIVO) {
            throw new DomainException('El acopiador no está activo y no puede registrar entregas.');
        }

        if ($recorridoDetalleId === null && ! $esAdministrador) {
            if ($proveedor->zonaId() === null) {
                throw new DomainException('El proveedor no tiene una ruta ni zona asignada; no se puede registrar la entrega.');
            }

            if ($acopiador->zonaId() !== $proveedor->zonaId()) {
                throw new DomainException('El acopiador no puede registrar entregas de un proveedor de otra zona.');
            }
        }

        return $this->transacciones->run(function () use (
            $proveedor, $acopiador, $acopiadorUserId, $fechaHora, $litros, $metodoIdentificacion,
            $observacion, $recorridoDetalleId, $tokenIdempotencia,
        ): Entrega {
            $entrega = $this->entregas->guardar(new Entrega(
                id: null,
                proveedorId: $proveedor->id(),
                acopiadorId: $acopiador?->id(),
                zonaId: $proveedor->zonaId() ?? throw new DomainException('El proveedor no tiene una ruta ni zona asignada; no se puede registrar la entrega.'),
                registradoPorUserId: $acopiadorUserId,
                // Hora real del acopio: la del evento si el cliente la envió (móvil, también
                // offline) o la del servidor si no (Web, clientes antiguos). created_at
                // queda como hora de llegada/sincronización.
                fechaHora: $fechaHora ?? DateTimeImmutable::createFromInterface(now()),
                litros: $litros,
                metodoIdentificacion: $metodoIdentificacion,
                estado: EstadoEntrega::PENDIENTE_ANALISIS,
                observacion: $observacion,
                recorridoDetalleId: $recorridoDetalleId,
                tokenIdempotencia: $tokenIdempotencia,
            ));

            // La leche entra al inventario en la misma transacción que la
            // entrega; si el ingreso falla, la entrega tampoco se guarda.
            $this->ingresoLeche->execute($entrega);

            // El pago pendiente del proveedor nace (o se actualiza) aquí mismo:
            // sin análisis de calidad se paga el precio base. Nunca lanza por
            // faltas de configuración, así que no bloquea el acopio.
            $this->pagosProveedor->execute($proveedor->id());

            $this->notificaciones->guardar(new Notificacion(
                id: null,
                userId: $proveedor->userId(),
                tipo: 'ENTREGA_REGISTRADA',
                titulo: 'Entrega registrada',
                mensaje: sprintf('Se registró una entrega de %s litros pendiente de análisis de calidad.', $litros),
                referenciaTipo: 'ENTREGA',
                referenciaId: $entrega->id(),
                leido: false,
            ));

            return $entrega;
        });
    }
}
