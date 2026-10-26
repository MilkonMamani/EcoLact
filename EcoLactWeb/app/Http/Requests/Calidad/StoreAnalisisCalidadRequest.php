<?php

namespace App\Http\Requests\Calidad;

use App\Http\Controllers\Web\Calidad\TicketAnalizadorController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator;

/**
 * Valida el formato de los datos HTTP para registrar (o revisar mientras
 * esté BORRADOR) un análisis de calidad a partir de valores ya extraídos.
 *
 * NO implementa OCR ni decide ACEPTADA/OBSERVADA/RECHAZADA: solo valida que
 * los valores recibidos sean numéricos. La clasificación pertenece a
 * CalcularResultadoCalidad (Application/Domain).
 *
 * `ticket_path` es la foto del ticket que ya guardó "Escanear ticket"
 * (TicketAnalizadorController): solo se acepta si pertenece a ESTA entrega
 * y existe, así el request nunca apunta a un archivo arbitrario.
 * `origenes` indica por parámetro si el valor lo propuso el OCR y el
 * analista lo dejó tal cual, o lo escribió/corrigió él (MANUAL).
 */
class StoreAnalisisCalidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entrega_id' => ['required', 'integer', 'exists:entregas,id'],
            'valores' => ['required', 'array', 'min:1'],
            'valores.*' => ['nullable', 'numeric'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'ticket_path' => ['nullable', 'string', 'max:255'],
            'origenes' => ['nullable', 'array'],
            'origenes.*' => ['in:OCR,MANUAL'],
            'observacion' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $ticketPath = $this->input('ticket_path');

                if ($ticketPath === null || $validator->errors()->has('entrega_id')) {
                    return;
                }

                $patron = '#^'.preg_quote(TicketAnalizadorController::CARPETA_TICKETS, '#').'/entrega-'.(int) $this->input('entrega_id').'-[a-z0-9]{24}\.(jpg|jpeg|png|webp)$#';

                if (! is_string($ticketPath) || ! preg_match($patron, $ticketPath) || ! Storage::disk('public')->exists($ticketPath)) {
                    $validator->errors()->add('ticket_path', 'La foto del ticket no corresponde a esta entrega. Vuelve a escanearla.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entrega_id.exists' => 'La entrega indicada no existe.',
            'valores.required' => 'Debe registrar al menos un valor de parámetro de calidad.',
            'valores.*.numeric' => 'Cada valor de parámetro debe ser numérico.',
            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.mimes' => 'La imagen debe tener formato jpg, jpeg o png.',
            'imagen.max' => 'La imagen no debe superar los 5 MB.',
        ];
    }
}
