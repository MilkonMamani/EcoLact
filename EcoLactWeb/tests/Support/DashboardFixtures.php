<?php

use App\Infrastructure\Persistence\Models\ComunicadoModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\LoteProduccionInsumoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;

require_once __DIR__.'/EcoLactFixtures.php';

/** Hora del negocio (America/Lima) expresada como se guarda en las columnas DATETIME (zona de la app). */
function dashboard_utc(string $fechaLocal): string
{
    return (new DateTimeImmutable($fechaLocal, new DateTimeZone(config('app.zona_horaria_negocio'))))
        ->setTimezone(new DateTimeZone(config('app.timezone')))
        ->format('Y-m-d H:i:s');
}

function dashboard_entrega(string $fecha, float $litros = 30, ?string $resultado = null): EntregaModel
{
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega(fixture_proveedor($zona), $acopiador, $litros, new DateTimeImmutable(dashboard_utc($fecha)));

    if ($resultado !== null) {
        fixture_analisis_confirmado($entrega, $resultado, $acopiador->user_id);
        $entrega->update(['estado' => 'ANALIZADA']);
    }

    return $entrega;
}

function dashboard_lote(string $codigo, string $fecha, string $estado = 'EN_PROCESO'): LoteProduccionModel
{
    $lote = LoteProduccionModel::create([
        'codigo' => $codigo,
        'fecha_hora_inicio' => dashboard_utc($fecha),
        'estado' => $estado,
        'registrado_por_user_id' => fixture_usuario('ADMINISTRADOR')->id,
    ]);

    LoteProduccionInsumoModel::create([
        'lote_produccion_id' => $lote->id,
        'insumo_id' => fixture_leche()->id,
        'cantidad_requerida' => 150.25,
        'cantidad_usada' => 150.25,
        'unidad_base' => 'L',
    ]);

    return $lote;
}

function dashboard_comunicado(string $titulo, string $fecha, string $estado = 'PUBLICADO'): ComunicadoModel
{
    return ComunicadoModel::create([
        'titulo' => $titulo,
        'contenido' => 'Información de la operación.',
        'tipo' => 'AVISO',
        'alcance' => 'GENERAL',
        'fecha_publicacion' => dashboard_utc($fecha),
        'creado_por_user_id' => fixture_usuario('ADMINISTRADOR')->id,
        'estado' => $estado,
    ]);
}

function dashboard_venta(string $fecha, string $importe, string $formaPago = 'CONTADO', string $estado = 'REGISTRADA'): VentaProductoModel
{
    $proveedor = $formaPago === 'DESCUENTO_PLANILLA' ? fixture_proveedor(fixture_zona()) : null;

    return VentaProductoModel::create([
        'producto_id' => fixture_producto('Queso '.uniqid(), 'kg')->id,
        'proveedor_id' => $proveedor?->id,
        'tipo_cliente' => $proveedor !== null ? 'PROVEEDOR' : 'PUBLICO',
        'cantidad' => 1,
        'precio_unitario' => $importe,
        'importe_total' => $importe,
        'forma_pago' => $formaPago,
        'estado' => $estado,
        'fecha' => dashboard_utc($fecha),
        'registrado_por_user_id' => fixture_usuario('VENTAS')->id,
    ]);
}

/** Obligación semanal de proveedor (jueves a miércoles); su pago se programa el miércoles de cierre. */
function dashboard_obligacion_proveedor(string $inicioSemana, string $monto, string $estado = 'PENDIENTE'): ObligacionPagoModel
{
    $proveedor = fixture_proveedor(fixture_zona());

    return ObligacionPagoModel::create([
        'tipo_beneficiario' => 'PROVEEDOR',
        'proveedor_id' => $proveedor->id,
        'beneficiario_nombre' => 'Proveedor Test',
        'periodo_inicio' => $inicioSemana,
        'periodo_fin' => (new DateTimeImmutable($inicioSemana))->modify('+6 days')->format('Y-m-d'),
        'periodicidad' => 'SEMANAL',
        'concepto' => 'Pago semanal a proveedor',
        'monto_calculado' => $monto,
        'estado' => $estado,
        'clave_unicidad' => 'PROVEEDOR:'.$proveedor->id.':'.$inicioSemana,
    ]);
}

/** Sueldo mensual de un trabajador; null en $fechaPago = pendiente sin fecha programada. */
function dashboard_obligacion_trabajador(string $mes, ?string $fechaPago, string $monto, string $estado = 'PENDIENTE'): ObligacionPagoModel
{
    $usuario = fixture_usuario('ACOPIADOR');
    $inicio = new DateTimeImmutable($mes.'-01');

    return ObligacionPagoModel::create([
        'tipo_beneficiario' => 'PERSONAL',
        'user_id' => $usuario->id,
        'rol_id' => $usuario->role_id,
        'beneficiario_nombre' => 'Trabajador Test',
        'periodo_inicio' => $inicio->format('Y-m-d'),
        'periodo_fin' => $inicio->modify('last day of this month')->format('Y-m-d'),
        'fecha_pago_programada' => $fechaPago,
        'periodicidad' => 'MENSUAL',
        'concepto' => 'Sueldo mensual',
        'monto_calculado' => $monto,
        'estado' => $estado,
        'clave_unicidad' => 'PERSONAL:'.$usuario->id.':'.$inicio->format('Y-m-d'),
    ]);
}

function dashboard_trabajador(string $ingreso = '2026-01-01', ?string $salida = null, string $estado = 'ACTIVO'): TrabajadorModel
{
    return TrabajadorModel::create([
        'user_id' => fixture_usuario('ACOPIADOR')->id,
        'dni' => fixture_next_dni(),
        'nombres' => 'Trabajador',
        'apellidos' => 'Test',
        'estado' => $estado,
        'fecha_ingreso' => $ingreso,
        'fecha_salida' => $salida,
    ]);
}

function dashboard_jornada(int $trabajadorId, string $fecha, string $estado = 'PRESENTE'): void
{
    JornadaPersonalModel::create([
        'trabajador_id' => $trabajadorId,
        'rol_id' => fixture_rol('ACOPIADOR')->id,
        'fecha' => $fecha,
        'estado' => $estado,
        'origen' => 'MANUAL',
    ]);
}
