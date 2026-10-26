@extends('layouts.admin')

@section('title', 'Ventas')
@section('body-class', 'admin-fichas-page vpos-page')

{{-- Ventas / POS del ADMINISTRADOR: la misma pantalla que el PERSONAL DE VENTAS (ventas/pos). --}}
@section('content')
    @include('ventas.pos._pantalla', ['portal' => 'admin'])
@endsection
