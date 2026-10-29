{{-- Estado de existencias de un insumo (Insumo::nivelStock): Disponible, Stock bajo o Sin stock. $nivel --}}
@php
    $textos = ['DISPONIBLE' => 'Disponible', 'BAJO' => 'Stock bajo', 'SIN_STOCK' => 'Sin stock'];
@endphp
<span class="insumos-nivel is-{{ strtolower($nivel) }}">{{ $textos[$nivel] }}</span>
