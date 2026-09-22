@extends('layouts.planta')

@section('title', 'Insumos')

@section('content')
    <div class="flex justify-end">
        <x-ui.button :href="route('planta.insumos.movimientos')" variant="secondary" size="sm" as="a">Ver todos los movimientos</x-ui.button>
    </div>

    @include('insumos._inventario', [
        'prefijo' => 'planta',
        'storeRoute' => 'planta.insumos.store',
        'updateRoute' => 'planta.insumos.update',
        'ajusteRoute' => 'planta.insumos.movimiento',
        'compraRoute' => null,
    ])
@endsection
