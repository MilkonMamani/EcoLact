@extends('layouts.planta')

@section('title', 'Lote '.$detalle->lote->codigo())

@section('content')
    @include('produccion._detalle', ['prefijo' => 'planta'])
@endsection
