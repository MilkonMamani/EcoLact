<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardPeriodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'periodo' => ['sometimes', 'required', 'string', Rule::in(['hoy', 'semana', 'mes', 'personalizado'])],
            'desde' => ['exclude_unless:periodo,personalizado', 'bail', 'required', 'date_format:Y-m-d'],
            'hasta' => ['exclude_unless:periodo,personalizado', 'bail', 'required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'periodo.required' => 'Selecciona un período.',
            'periodo.in' => 'El período debe ser hoy, semana, mes o personalizado.',
            'desde.required' => 'Indica la fecha desde.',
            'hasta.required' => 'Indica la fecha hasta.',
            'desde.date_format' => 'La fecha desde debe ser válida y tener formato AAAA-MM-DD.',
            'hasta.date_format' => 'La fecha hasta debe ser válida y tener formato AAAA-MM-DD.',
            'hasta.after_or_equal' => 'La fecha hasta debe ser igual o posterior a desde.',
        ];
    }
}
