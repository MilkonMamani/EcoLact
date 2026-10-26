@extends('layouts.admin')

@section('title', 'Compradores')

@section('content')
    @include('ventas.compradores._agenda', ['portal' => 'admin'])
@endsection
