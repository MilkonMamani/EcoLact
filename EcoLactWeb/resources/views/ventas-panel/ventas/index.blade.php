@extends('layouts.ventas-panel')

@section('title', 'Ventas')
@section('body-class', 'admin-fichas-page vpos-page')

{{-- Ventas / POS del PERSONAL DE VENTAS: la misma pantalla que el ADMINISTRADOR (ventas/pos). --}}
@section('content')
    @include('ventas.pos._pantalla', ['portal' => 'ventas'])
@endsection
