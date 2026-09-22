@extends('layouts.admin')

@section('title', 'Lote '.$detalle->lote->codigo())

@section('content')
    @include('produccion._detalle', ['prefijo' => 'admin'])
@endsection
