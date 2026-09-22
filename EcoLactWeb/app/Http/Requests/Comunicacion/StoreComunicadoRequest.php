<?php

namespace App\Http\Requests\Comunicacion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formato de los datos HTTP para crear un comunicado.
 *
 * NO envía notificaciones aquí: eso lo coordina el Use Case CrearComunicado.
 * NO valida que tipo/alcance/estado pertenezcan al catálogo permitido: eso
 * lo hace CrearComunicado contra CatalogoComunicado (evita que este catálogo
 * viva duplicado en dos capas).
 */
class StoreComunicadoRequest extends FormRequest
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
            'titulo' => ['required', 'string', 'max:180'],
            'contenido' => ['required', 'string'],
            'tipo' => ['required', 'string', 'max:30'],
            'alcance' => ['required', 'string', 'max:20'],
            // creado_por_user_id NO se valida aquí (Etapa 10.1.1): el actor
            // es siempre $request->user()->id en ComunicadoController.
            // destinatarios SÍ se valida: no es el actor, son las personas
            // seleccionadas por el administrador como destinatarias.
            'zona_id' => ['nullable', 'integer', 'exists:zonas,id'],
            'fecha_evento' => ['nullable', 'date'],
            'destinatarios' => ['nullable', 'array'],
            'destinatarios.*' => ['integer', 'exists:users,id'],
            // Estado inicial: BORRADOR (Guardar borrador) o PUBLICADO
            // (Publicar ahora / Programar). fecha_publicacion solo se
            // recibe cuando el administrador elige "Programar"; si no se
            // envía, CrearComunicado usa now().
            'estado' => ['sometimes', 'string', 'in:BORRADOR,PUBLICADO'],
            'fecha_publicacion' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'zona_id.exists' => 'La zona seleccionada no existe.',
            'destinatarios.*.exists' => 'Uno de los destinatarios indicados no existe.',
        ];
    }
}
