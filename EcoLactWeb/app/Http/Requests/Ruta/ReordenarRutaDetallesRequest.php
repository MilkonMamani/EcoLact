<?php

namespace App\Http\Requests\Ruta;

use Illuminate\Foundation\Http\FormRequest;

class ReordenarRutaDetallesRequest extends FormRequest
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
            'orden' => ['required', 'array', 'min:1'],
            'orden.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
