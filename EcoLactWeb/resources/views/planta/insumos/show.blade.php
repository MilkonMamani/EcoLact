@extends('layouts.planta')

@section('title', $insumo->nombre())

@section('content')
    @include('insumos._ficha', ['volver' => route('planta.insumos.index'), 'historialUrl' => route('planta.insumos.movimientos').'?insumo_id='.$insumo->id()])
@endsection
