<?php

use App\Application\Liquidacion\ValorizarEntrega;
use App\Application\Pago\CerrarNominaMensual;
use App\Application\Pago\RegistrarConfiguracionPago;
use App\Application\Pago\RegistrarJornadaTrabajador;
use App\Application\Trabajador\SincronizarTrabajadores;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\OrigenJornada;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use App\Infrastructure\Persistence\Models\EncargadoPlantaModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PersonalVentasModel;
use App\Infrastructure\Persistence\Models\PrecioLecheModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use App\Models\User;
use Carbon\CarbonImmutable;

require_once __DIR__.'/EcoLactFixtures.php';

if (! function_exists('pagos_configurar')) {
    /**
     * Configura el pago del proveedor ('PROVEEDOR': por litros; el precio base es el vigente en
     * precios_leche, o $precio si se indica) o el sueldo base de un rol (por nombre), que heredan
     * todos sus trabajadores, también los que pagos_personal() cree después.
     *
     * @param  array<string, array{tipo_ajuste?: string, valor_ajuste?: float|string}>|null  $ajustes
     */
    function pagos_configurar(string $destino, ?float $sueldo, string $desde, User $admin, bool $aplicaCalidad = true, ?float $precio = null, ?array $ajustes = null): void
    {
        if ($destino === 'PROVEEDOR') {
            app(RegistrarConfiguracionPago::class)->execute(
                tipo: TipoBeneficiarioPago::PROVEEDOR,
                rolId: null,
                montoBase: null,
                aplicaCalidad: $aplicaCalidad,
                fechaInicio: new DateTimeImmutable($desde),
                observacion: null,
                creadoPorUserId: $admin->id,
                precioBaseLitro: $precio ?? PrecioLecheModel::orderByDesc('fecha_inicio')->value('precio_litro') ?? 2.10,
                ajustes: $ajustes,
            );

            return;
        }

        app(RegistrarConfiguracionPago::class)->execute(
            tipo: TipoBeneficiarioPago::PERSONAL,
            rolId: fixture_rol($destino)->id,
            montoBase: $sueldo,
            aplicaCalidad: false,
            fechaInicio: new DateTimeImmutable($desde),
            observacion: null,
            creadoPorUserId: $admin->id,
        );
    }
}

if (! function_exists('pagos_sueldo_individual_anterior')) {
    /**
     * Fila individual de sueldo del modelo por trabajador (oct. 2026), que hoy solo es historial:
     * sirve para comprobar que ya no se usa para calcular sueldos.
     */
    function pagos_sueldo_individual_anterior(User $trabajador, float $sueldo, string $desde, User $admin): int
    {
        return ConfiguracionPagoModel::create([
            'tipo_beneficiario' => 'PERSONAL', 'rol_id' => $trabajador->role_id, 'user_id' => $trabajador->id,
            'tipo_calculo' => 'SUELDO_FIJO', 'monto_base' => $sueldo, 'periodicidad' => 'MENSUAL', 'aplica_calidad' => false,
            'fecha_inicio' => $desde, 'estado' => 'ACTIVO', 'creado_por_user_id' => $admin->id,
        ])->id;
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

if (! function_exists('pagos_trabajador')) {
    /** Sincroniza (SincronizarTrabajadores) y devuelve la identidad laboral (`trabajadores`) del usuario. */
    function pagos_trabajador(User $usuario): TrabajadorModel
    {
        app(SincronizarTrabajadores::class)->execute();

        return TrabajadorModel::where('user_id', $usuario->id)->firstOrFail();
    }
}

if (! function_exists('pagos_jornada')) {
    /**
     * Registra PRESENTE (pasando por el use case) para un trabajador en una fecha.
     * La asistencia solo se registra ese mismo día antes de las 07:00 (VentanaAsistencia):
     * viaja a las 06:00 hora del negocio de esa fecha, registra y vuelve al "ahora" del test.
     */
    function pagos_jornada(int $trabajadorId, string $fecha, int $registradoPorUserId, ?string $observacion = null): void
    {
        $antes = now()->toImmutable();
        test()->travelTo(CarbonImmutable::parse($fecha.' 06:00:00', config('app.zona_horaria_negocio')));

        try {
            app(RegistrarJornadaTrabajador::class)->execute(
                trabajadorId: $trabajadorId,
                fecha: new DateTimeImmutable($fecha),
                observacion: $observacion,
                registradoPorUserId: $registradoPorUserId,
            );
        } finally {
            test()->travelTo($antes);
        }
    }
}

if (! function_exists('pagos_falta')) {
    /** Inserta directamente una FALTA/AUTOMATICO (sin pasar por el scheduler) para un trabajador en una fecha. */
    function pagos_falta(int $trabajadorId, int $rolId, string $fecha): void
    {
        JornadaPersonalModel::create([
            'trabajador_id' => $trabajadorId,
            'rol_id' => $rolId,
            'fecha' => $fecha,
            'estado' => EstadoJornada::FALTA->value,
            'origen' => OrigenJornada::AUTOMATICO->value,
        ]);
    }
}

if (! function_exists('pagos_cerrar_mes')) {
    /**
     * Cierra de verdad (CerrarNominaMensual) el mes de una obligación de
     * personal EN_CURSO, con la fecha "hoy" un día después de su periodo_fin
     * (como lo haría el cron del día 1), y devuelve la obligación ya
     * PENDIENTE. A diferencia del modelo anterior, esto pasa por el cálculo
     * real (faltas/presentes/vigencia), no fija un acumulado a mano.
     */
    function pagos_cerrar_mes(ObligacionPagoModel $obligacion, ?int $actorUserId = null): ObligacionPagoModel
    {
        $hoyCierre = new DateTimeImmutable($obligacion->periodo_fin->format('Y-m-d'));
        app(CerrarNominaMensual::class)->execute($hoyCierre->modify('+1 day'), $actorUserId);

        return $obligacion->fresh();
    }
}
