<?php

namespace App\Application\Zona;

use DomainException;

/**
 * La ubicación marcada no cae en la delimitación de la zona de la ruta elegida. Se distingue de
 * otros DomainException para que el modal muestre el error junto al mapa y no bajo "Ruta".
 */
final class UbicacionFueraDeZona extends DomainException {}
