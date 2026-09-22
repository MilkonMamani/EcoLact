@extends('layouts.admin')

@section('title', 'Insumos')

@section('content')
    @include('insumos._inventario', [
        'prefijo' => 'admin',
        'storeRoute' => 'admin.insumos.store',
        'updateRoute' => 'admin.insumos.update',
        'ajusteRoute' => 'admin.insumos.ajuste',
        'compraRoute' => 'admin.insumos.compra',
    ])
@endsection
