<?php

namespace App\Http\Requests\Recorrido;

use Illuminate\Foundation\Http\FormRequest;

class MarcarNoEntregoRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }
}
