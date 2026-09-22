<?php

use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\ComunicadoModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\LoteProduccionInsumoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\ValorizacionEntregaModel;

require_once __DIR__.'/EcoLactFixtures.php';

function dashboard_entrega(string $fecha, float $litros = 30, ?string $resultado = null): EntregaModel
{
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega(fixture_proveedor($zona), $acopiador, $litros, new DateTimeImmutable($fecha));

    if ($resultado !== null) {
        fixture_analisis_confirmado($entrega, $resultado, $acopiador->user_id);
        $entrega->update(['estado' => 'ANALIZADA']);
    }

    return $entrega;
}

function dashboard_valorizacion(EntregaModel $entrega, string $importe, string $fecha): ValorizacionEntregaModel
{
    $analisis = AnalisisCalidadModel::where('entrega_id', $entrega->id)->firstOrFail();
    $precio = fixture_precio_leche(2, null, $entrega->registrado_por_user_id);
    $regla = fixture_regla_pago($analisis->resultado, 'SIN_AJUSTE', 0);

    return ValorizacionEntregaModel::create([
        'entrega_id' => $entrega->id,
        'analisis_id' => $analisis->id,
        'precio_leche_id' => $precio->id,
        'regla_pago_id' => $regla->id,
        'litros_entregados' => $entrega->litros,
        'litros_pagables' => $entrega->litros,
        'precio_base_litro' => 2,
        'ajuste_por_litro' => 0,
        'precio_final_litro' => 2,
        'importe_total' => $importe,
        'fecha_calculo' => $fecha,
    ]);
}

function dashboard_lote(string $codigo, string $fecha, string $estado = 'EN_PROCESO'): LoteProduccionModel
{
    $lote = LoteProduccionModel::create([
        'codigo' => $codigo,
        'fecha_hora_inicio' => $fecha,
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
        'fecha_publicacion' => $fecha,
        'creado_por_user_id' => fixture_usuario('ADMINISTRADOR')->id,
        'estado' => $estado,
    ]);
}
