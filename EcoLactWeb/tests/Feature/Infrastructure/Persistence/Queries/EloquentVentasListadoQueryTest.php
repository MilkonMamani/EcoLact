<?php

use App\Application\Ventas\RegistrarComprador;
use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Application\Ventas\VentasFiltro;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Queries\EloquentVentasListadoQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

function vlq_crearProducto(string $nombre): ProductoModel
{
    return ProductoModel::create(['nombre' => $nombre, 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
}

it('calcula los KPIs (ventas, total vendido, productos vendidos, compradores) del periodo por defecto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $productoA = vlq_crearProducto('Queso Paria');
    $productoB = vlq_crearProducto('Yogur');

    app(RegistrarProductoPrecio::class)->execute($productoA->id, 'PROVEEDOR', 18.0, $admin->id);
    fixture_ingreso_producto($productoA->id, 10.0, $admin->id);
    app(RegistrarProductoPrecio::class)->execute($productoB->id, 'PUBLICO', 8.0, $admin->id);
    fixture_ingreso_producto($productoB->id, 10.0, $admin->id);

    app(RegistrarVentaProducto::class)->execute(
        productoId: $productoA->id,
        tipoCliente: 'PROVEEDOR',
        cantidad: 5.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
        proveedorId: $proveedor->id,
    );
    app(RegistrarVentaProducto::class)->execute(
        productoId: $productoB->id,
        tipoCliente: 'PUBLICO',
        cantidad: 3.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
    );

    $filtro = VentasFiltro::resolver(null, null, null, null, null, null, null);
    $datos = (new EloquentVentasListadoQuery)->obtener($filtro);

    expect($datos->kpis['ventas'])->toBe(2);
    expect($datos->kpis['totalVendido'])->toBe(90.0 + 24.0);
    expect($datos->kpis['productosVendidos'])->toBe(8.0);
    // Un comprador distinto: el proveedor. La venta PUBLICO no tiene comprador_id.
    expect($datos->kpis['compradores'])->toBe(1);
    expect($datos->filas)->toHaveCount(2);

    $filaProveedor = collect($datos->filas)->firstWhere('clienteTipo', 'PROVEEDOR');
    expect($filaProveedor['clienteNombre'])->toBe(trim($proveedor->nombres.' '.$proveedor->apellidos));
    expect($filaProveedor['productoNombre'])->toBe('Queso Paria');
});

it('cuenta un comprador registrado como cliente distinto y filtra por tipo de cliente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = vlq_crearProducto('Queso Madurado');
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'MAYORISTA', 19.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    $comprador = app(RegistrarComprador::class)->execute('Comercial X', 'MAYORISTA');

    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'MAYORISTA',
        cantidad: 4.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
        compradorId: $comprador->id(),
    );

    $porTipo = VentasFiltro::resolver(null, null, null, null, 'MAYORISTA', null, null);
    $datos = (new EloquentVentasListadoQuery)->obtener($porTipo);
    expect($datos->kpis['compradores'])->toBe(1);
    expect($datos->filas)->toHaveCount(1);
    expect($datos->filas[0]['clienteNombre'])->toBe('Comercial X');

    $porOtroTipo = VentasFiltro::resolver(null, null, null, null, 'PUBLICO', null, null);
    $datosVacio = (new EloquentVentasListadoQuery)->obtener($porOtroTipo);
    expect($datosVacio->filas)->toHaveCount(0);
});
