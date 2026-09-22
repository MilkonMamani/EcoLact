<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\Sancion;
use App\Domain\Calidad\SancionRepository;
use App\Domain\Comunicacion\Notificacion;
use App\Domain\Comunicacion\Repositories\NotificacionRepository;
use App\Domain\Proveedor\Proveedor;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Shared\Enums\ResultadoCalidad;
use DateTimeImmutable;
use DomainException;

/**
 * Registra la advertencia de calidad de un proveedor cuando un análisis se
 * confirma como OBSERVADA o RECHAZADA (ambas cuentan igual para el conteo).
 * Progresión: 1ª advertencia -> notificación; 2ª -> notificación de última
 * advertencia; 3ª -> el proveedor queda INACTIVO automáticamente. El
 * contador solo se reinicia cuando un ADMINISTRADOR reactiva al proveedor
 * (ReactivarProveedor), nunca automáticamente por el paso del tiempo.
 */
final class RegistrarAdvertenciaCalidad
{
    private const NIVEL_INACTIVACION = 3;

    public function __construct(
        private readonly ProveedorRepository $proveedores,
        private readonly SancionRepository $sanciones,
        private readonly NotificacionRepository $notificaciones,
    ) {}

    public function execute(
        int $proveedorId,
        int $analisisId,
        ResultadoCalidad $resultado,
        int $creadoPorUserId,
    ): Sancion {
        if ($resultado === ResultadoCalidad::ACEPTADA) {
            throw new DomainException('Un análisis ACEPTADA no genera advertencias de calidad.');
        }

        $proveedor = $this->proveedores->buscarPorId($proveedorId);

        if ($proveedor === null) {
            throw new DomainException('El proveedor indicado no existe.');
        }

        $nivel = min($proveedor->advertenciasCount() + 1, self::NIVEL_INACTIVACION);
        $seInactiva = $nivel >= self::NIVEL_INACTIVACION;

        $sancion = $this->sanciones->guardar(new Sancion(
            id: null,
            proveedorId: $proveedorId,
            analisisId: $analisisId,
            tipo: 'ADVERTENCIA_CALIDAD',
            nivelAdvertencia: $nivel,
            descripcion: sprintf('Análisis de calidad #%d confirmado como %s (advertencia %d de %d).', $analisisId, $resultado->value, $nivel, self::NIVEL_INACTIVACION),
            tipoPenalizacion: $seInactiva ? 'INACTIVACION' : 'ADVERTENCIA',
            valorPenalizacion: null,
            fecha: new DateTimeImmutable,
            estado: 'ACTIVA',
            creadoPorUserId: $creadoPorUserId,
        ));

        $this->proveedores->actualizar(new Proveedor(
            id: $proveedor->id(),
            userId: $proveedor->userId(),
            zonaId: $proveedor->zonaId(),
            dni: $proveedor->dni(),
            nombres: $proveedor->nombres(),
            apellidos: $proveedor->apellidos(),
            telefono: $proveedor->telefono(),
            qrToken: $proveedor->qrToken(),
            estado: $seInactiva ? EstadoUsuario::INACTIVO : $proveedor->estado(),
            fechaRegistro: $proveedor->fechaRegistro(),
            advertenciasCount: $nivel,
        ));

        $this->notificaciones->guardar(new Notificacion(
            id: null,
            userId: $proveedor->userId(),
            tipo: $seInactiva ? 'PROVEEDOR_INACTIVADO' : ($nivel === 2 ? 'ULTIMA_ADVERTENCIA_CALIDAD' : 'ADVERTENCIA_CALIDAD'),
            titulo: $seInactiva ? 'Cuenta desactivada por calidad' : 'Advertencia de calidad',
            mensaje: $this->mensajeNotificacion($nivel, $seInactiva),
            referenciaTipo: 'SANCION',
            referenciaId: $sancion->id(),
            leido: false,
        ));

        return $sancion;
    }

    private function mensajeNotificacion(int $nivel, bool $seInactiva): string
    {
        if ($seInactiva) {
            return 'Tu cuenta de proveedor fue desactivada tras la tercera advertencia de calidad. Contacta al administrador para más información.';
        }

        if ($nivel === 2) {
            return 'Esta es tu última advertencia de calidad: una nueva observación o rechazo desactivará tu cuenta de proveedor.';
        }

        return 'Tu entrega recibió una advertencia de calidad. Evita que se repita para no acumular más advertencias.';
    }
}
