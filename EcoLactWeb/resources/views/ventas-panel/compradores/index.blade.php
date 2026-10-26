@extends('layouts.ventas-panel')

@section('title', 'Compradores')

@section('content')
    @include('ventas.compradores._agenda', ['portal' => 'ventas', 'conBuscador' => true])
@endsection
