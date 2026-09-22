<?php

namespace App\Http\Requests\Recorrido;

use Illuminate\Foundation\Http\FormRequest;

class IdentificarParadaRequest extends FormRequest
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
            'metodo' => ['required', 'string', 'in:DNI,QR'],
            'identificador' => ['required', 'string', 'max:64'],
        ];
    }
}
