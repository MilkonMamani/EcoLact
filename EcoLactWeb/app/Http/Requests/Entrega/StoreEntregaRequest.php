<?php

namespace App\Http\Requests\Entrega;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formato de los datos HTTP para registrar una entrega.
 *
 * NO valida que el proveedor y el acopiador pertenezcan a la misma zona:
 * esa regla de negocio pertenece al Use Case RegistrarEntrega.
 */
class StoreEntregaRequest extends FormRequest
{
    /**
     * Métodos de identificación que RegistrarEntrega sabe interpretar.
     */
    private const METODOS_SOPORTADOS = ['QR', 'DNI'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $metodo = $this->input('metodo_identificacion');

        return [
            'metodo_identificacion' => ['required', 'string', Rule::in(self::METODOS_SOPORTADOS)],
            'identificador' => [
                'required',
                'string',
                $metodo === 'DNI' ? 'regex:/^\d{8}$/' : 'max:36',
            ],
            // acopiador_user_id NO se valida aquí (Etapa 10.1.1): el actor
            // (acopiador que registra la entrega) es siempre
            // $request->user()->id en EntregaController, nunca un dato del
            // cliente — el campo se elimina en vez de dejarse opcional,
            // para no dejar una superficie de suplantación de identidad.
            'litros' => ['required', 'numeric', 'gt:0'],
            'fecha_hora' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'metodo_identificacion.in' => 'El método de identificación debe ser QR o DNI.',
            'identificador.regex' => 'El DNI debe contener exactamente 8 dígitos.',
            'litros.gt' => 'La cantidad de litros debe ser mayor que cero.',
        ];
    }
}
