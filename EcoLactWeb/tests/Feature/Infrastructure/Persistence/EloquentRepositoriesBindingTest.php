<?php

use App\Domain\Acopiador\AcopiadorRepository;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Calidad\AnalisisCalidadRepository;
use App\Domain\Comunicacion\Repositories\ComunicadoRepository;
use App\Domain\Comunicacion\Repositories\NotificacionRepository;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Pago\LiquidacionRepository;
use App\Domain\Pago\PagoProveedorRepository;
use App\Domain\Produccion\LoteProduccionRepository;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Zona\ZonaRepository;
use App\Infrastructure\Persistence\Repositories\EloquentAcopiadorRepository;
use App\Infrastructure\Persistence\Repositories\EloquentAnalisisCalidadRepository;
use App\Infrastructure\Persistence\Repositories\EloquentComunicadoRepository;
use App\Infrastructure\Persistence\Repositories\EloquentEntregaRepository;
use App\Infrastructure\Persistence\Repositories\EloquentLiquidacionRepository;
use App\Infrastructure\Persistence\Repositories\EloquentLoteProduccionRepository;
use App\Infrastructure\Persistence\Repositories\EloquentNotificacionRepository;
use App\Infrastructure\Persistence\Repositories\EloquentPagoProveedorRepository;
use App\Infrastructure\Persistence\Repositories\EloquentProductoRepository;
use App\Infrastructure\Persistence\Repositories\EloquentProveedorRepository;
use App\Infrastructure\Persistence\Repositories\EloquentUsuarioRepository;
use App\Infrastructure\Persistence\Repositories\EloquentZonaRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('resuelve cada contrato de repository a su implementación Eloquent desde el contenedor', function () {
    $bindings = [
        ZonaRepository::class => EloquentZonaRepository::class,
        ProveedorRepository::class => EloquentProveedorRepository::class,
        AcopiadorRepository::class => EloquentAcopiadorRepository::class,
        EntregaRepository::class => EloquentEntregaRepository::class,
        AnalisisCalidadRepository::class => EloquentAnalisisCalidadRepository::class,
        LiquidacionRepository::class => EloquentLiquidacionRepository::class,
        PagoProveedorRepository::class => EloquentPagoProveedorRepository::class,
        ProductoRepository::class => EloquentProductoRepository::class,
        LoteProduccionRepository::class => EloquentLoteProduccionRepository::class,
        UsuarioRepository::class => EloquentUsuarioRepository::class,
        ComunicadoRepository::class => EloquentComunicadoRepository::class,
        NotificacionRepository::class => EloquentNotificacionRepository::class,
    ];

    foreach ($bindings as $contract => $implementation) {
        expect(app($contract))->toBeInstanceOf($implementation);
    }
});
