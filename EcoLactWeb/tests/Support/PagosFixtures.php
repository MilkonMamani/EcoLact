<?php

use App\Application\Liquidacion\ValorizarEntrega;
use App\Application\Pago\RegistrarConfiguracionPago;
use App\Application\Pago\RegistrarJornadaTrabajador;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use App\Infrastructure\Persistence\Models\EncargadoPlantaModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PersonalVentasModel;
use App\Infrastructure\Persistence\Models\PrecioLecheModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Models\User;

require_once __DIR__.'/EcoLactFixtures.php';

if (! function_exists('pagos_configurar')) {
    /**
     * Configura el pago del personal (sueldo fijo mensual de un rol, por nombre) o del proveedor
     * ('PROVEEDOR': por litros; el precio base es el vigente en precios_leche, o $precio si se indica).
     *
     * @param  array<string, array{tipo_ajuste?: string, valor_ajuste?: float|string}>|null  $ajustes
     */
    function pagos_configurar(string $destino, ?float $sueldo, string $desde, User $admin, bool $aplicaCalidad = true, ?float $precio = null, ?array $ajustes = null): void
    {
        $esProveedor = $destino === 'PROVEEDOR';

        app(RegistrarConfiguracionPago::class)->execute(
            tipo: $esProveedor ? TipoBeneficiarioPago::PROVEEDOR : TipoBeneficiarioPago::PERSONAL,
            rolId: $esProveedor ? null : fixture_rol($destino)->id,
            montoBase: $sueldo,
            aplicaCalidad: $aplicaCalidad,
            fechaInicio: new DateTimeImmutable($desde),
            observacion: null,
            creadoPorUserId: $admin->id,
            precioBaseLitro: $esProveedor ? ($precio ?? PrecioLecheModel::orderByDesc('fecha_inicio')->value('precio_litro') ?? 2.10) : null,
            ajustes: $ajustes,
        );
    }
}

if (! function_exists('pagos_personal')) {
    /**
     * Crea una persona ACTIVA con el rol indicado (nombre de rol) y, si el rol tiene ficha, su ficha.
     */
    function pagos_personal(string $rol, string $nombres = 'Persona', string $alta = '2026-01-15', string $estado = 'ACTIVO'): User
    {
        $usuario = fixture_usuario($rol);
        $usuario->update(['estado' => $estado]);
        $usuario->forceFill(['created_at' => $alta])->save();

        $ficha = [
            'user_id' => $usuario->id,
            'dni' => fixture_next_dni(),
            'nombres' => $nombres,
            'apellidos' => 'Apellido',
            'telefono' => null,
            'estado' => $estado,
            'fecha_ingreso' => $alta,
        ];

        match ($rol) {
            'ACOPIADOR' => AcopiadorModel::create($ficha + ['zona_id' => fixture_zona()->id]),
            'CALIDAD' => AnalistaCalidadModel::create($ficha),
            'ENCARGADO_PLANTA' => EncargadoPlantaModel::create($ficha),
            'VENTAS' => PersonalVentasModel::create($ficha),
            default => null,
        };

        return $usuario;
    }
}

if (! function_exists('pagos_precios_y_reglas')) {
    /**
     * Precio de leche vigente y reglas de pago por calidad (ACEPTADA 0, OBSERVADA -0.30, RECHAZADA 0).
     */
    function pagos_precios_y_reglas(User $admin, float $precio = 2.10): void
    {
        fixture_precio_leche($precio, new DateTimeImmutable('2026-01-01'), $admin->id);
        fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
        fixture_regla_pago('OBSERVADA', 'MONTO', -0.30, new DateTimeImmutable('2026-01-01'));
        fixture_regla_pago('RECHAZADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
    }
}

if (! function_exists('pagos_entrega')) {
    /**
     * Entrega de un proveedor. $resultado null = sin análisis de calidad;
     * 'BORRADOR' = análisis sin confirmar; ACEPTADA/OBSERVADA/RECHAZADA = confirmado y valorizado.
     */
    function pagos_entrega(ProveedorModel $proveedor, float $litros, string $fecha, ?string $resultado = null): EntregaModel
    {
        $acopiador = AcopiadorModel::first() ?? fixture_acopiador(fixture_zona());
        $entrega = fixture_entrega($proveedor, $acopiador, $litros, new DateTimeImmutable($fecha));

        if ($resultado === 'BORRADOR') {
            AnalisisCalidadModel::create([
                'entrega_id' => $entrega->id,
                'imagen_path' => null,
                'estado' => 'BORRADOR',
                'resultado' => null,
                'fecha_analisis' => $entrega->fecha_hora,
                'confirmado_por_user_id' => null,
            ]);
        } elseif ($resultado !== null) {
            fixture_analisis_confirmado($entrega, $resultado, $acopiador->user_id);
            app(ValorizarEntrega::class)->execute($entrega->id);
        }

        return $entrega;
    }
}

if (! function_exists('pagos_forzar_pendiente')) {
    /**
     * Completa de golpe los 30 días de una ventana de personal EN_CURSO,
     * insertando jornadas reales (no solo tocando columnas), para que quede
     * PENDIENTE de forma consistente con RecalcularProgresoObligacionPersonal
     * incluso si una visita posterior a la pantalla vuelve a recalcularla.
     * Solo para tests que necesitan un trabajador ya listo para pagar y no
     * están probando el control de jornadas en sí mismo (para eso está
     * JornadaPersonalTest/RegistrarJornadaTrabajadorTest). Usa sueldos
     * múltiplos de 30 en los tests para que el valor por día sea exacto.
     */
    function pagos_forzar_pendiente(ObligacionPagoModel $obligacion, ?float $sueldo = null): ObligacionPagoModel
    {
        $sueldo ??= (float) ($obligacion->base_calculo['sueldo_base'] ?? 0.0);
        $inicio = new DateTimeImmutable($obligacion->periodo_inicio->format('Y-m-d'));

        for ($i = 0; $i < JornadaPersonal::DIAS_BASE_PERIODO; $i++) {
            JornadaPersonalModel::create([
                'user_id' => $obligacion->user_id,
                'rol_id' => $obligacion->rol_id,
                'fecha' => $inicio->modify("+{$i} days")->format('Y-m-d'),
            ]);
        }

        $valorDia = round($sueldo / JornadaPersonal::DIAS_BASE_PERIODO, 2);
        $acumulado = round($valorDia * JornadaPersonal::DIAS_BASE_PERIODO, 2);

        $obligacion->update([
            'dias_trabajados' => JornadaPersonal::DIAS_BASE_PERIODO,
            'valor_dia' => $valorDia,
            'acumulado' => $acumulado,
            'monto_calculado' => $acumulado,
            'estado' => 'PENDIENTE',
        ]);

        return $obligacion->fresh();
    }
}

if (! function_exists('pagos_jornada')) {
    /** Registra un día trabajado real (pasando por el use case) para un usuario. */
    function pagos_jornada(int $userId, int $rolId, string $fecha, int $registradoPorUserId, ?string $observacion = null): void
    {
        app(RegistrarJornadaTrabajador::class)->execute(
            userId: $userId,
            rolId: $rolId,
            fecha: new DateTimeImmutable($fecha),
            observacion: $observacion,
            registradoPorUserId: $registradoPorUserId,
        );
    }
}
