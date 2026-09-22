@extends('layouts.admin')

@section('title', $insumo->nombre())

@section('content')
    @include('insumos._ficha', ['volver' => route('admin.insumos.index'), 'historialUrl' => null])
@endsection
