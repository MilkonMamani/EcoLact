<?php

namespace App\Application\Calidad\Contracts;

use App\Application\Calidad\TicketIlegibleException;

/**
 * Puerto del OCR local del ticket del analizador de leche. Solo LEE: recibe
 * la ruta absoluta de una imagen ya guardada por el servidor y devuelve el
 * texto bruto. Interpretar ese texto es trabajo de TicketAnalizadorParser.
 */
interface TicketAnalizadorOcrService
{
    /**
     * Varias lecturas independientes del mismo ticket (distintas variantes de
     * la imagen), en orden de preferencia. Con fotos de celular una sola
     * lectura falla en valores sueltos; el parser las combina por votación.
     *
     * @return list<string>
     *
     * @throws TicketIlegibleException Si el motor OCR no está disponible o falla.
     */
    public function leerLecturas(string $rutaImagen): array;
}
