<?php

namespace App\Http\Controllers\Web\Calidad;

use App\Application\Calidad\EscanearTicketAnalizador;
use App\Application\Calidad\TicketIlegibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calidad\EscanearTicketAnalizadorRequest;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "Escanear ticket" de Registrar análisis (portal CALIDAD y administrador):
 * guarda la foto original del ticket y devuelve los valores que el OCR
 * PROPONE para el formulario. No crea ni modifica el análisis: el analista
 * revisa, corrige y guarda con el flujo normal (store), enviando la ruta del
 * ticket para asociarla al análisis.
 *
 * El proveedor, la zona y el acopiador NO se leen del ticket: salen de la
 * entrega (entrega_id).
 */
final class TicketAnalizadorController extends Controller
{
    private const DISCO = 'public';

    /** Fotos originales de los tickets; StoreAnalisisCalidadRequest solo acepta rutas de aquí. */
    public const CARPETA_TICKETS = 'analisis-calidad/tickets';

    private const MENSAJE_ILEGIBLE = 'No se pudo leer el ticket automáticamente.';

    private const MENSAJE_INSUFICIENTE = 'No se pudieron identificar suficientes valores. Toma la foto de frente, con buena iluminación y sin sombras.';

    private const MENSAJE_DETECTADOS = 'Valores detectados automáticamente. Revisa la información antes de guardar.';

    public function __construct(
        private readonly EscanearTicketAnalizador $escanearTicketAnalizador,
    ) {}

    public function __invoke(EscanearTicketAnalizadorRequest $request): JsonResponse
    {
        Gate::authorize('create', AnalisisCalidadModel::class);

        $entrega = EntregaModel::findOrFail($request->integer('entrega_id'));
        Gate::authorize('view', $entrega);

        $imagen = $request->file('imagen');
        $ticketPath = $imagen->storeAs(
            self::CARPETA_TICKETS,
            'entrega-'.$entrega->id.'-'.Str::lower(Str::random(24)).'.'.$imagen->extension(),
            self::DISCO,
        );

        try {
            $lectura = $this->escanearTicketAnalizador->execute($entrega->id, Storage::disk(self::DISCO)->path($ticketPath));
        } catch (TicketIlegibleException $excepcion) {
            Log::warning('OCR del ticket del analizador falló.', ['entrega_id' => $entrega->id, 'error' => $excepcion->getMessage()]);

            // La foto se conserva: el analista puede ingresar los valores a
            // mano y el ticket igual queda como evidencia del análisis.
            return response()->json([
                'success' => false,
                'ticket_path' => $ticketPath,
                'ticket_url' => Storage::disk(self::DISCO)->url($ticketPath),
                'mensaje' => self::MENSAJE_ILEGIBLE,
            ], 422);
        } catch (DomainException $excepcion) {
            Storage::disk(self::DISCO)->delete($ticketPath);

            return response()->json(['success' => false, 'mensaje' => $excepcion->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'ticket_path' => $ticketPath,
            'ticket_url' => Storage::disk(self::DISCO)->url($ticketPath),
            'valores' => $lectura['valores'],
            'no_detectados' => $lectura['no_detectados'],
            'detectados_no_configurados' => $lectura['no_configurados'],
            'suficientes' => $lectura['suficientes'],
            'mensaje' => $lectura['suficientes'] ? self::MENSAJE_DETECTADOS : self::MENSAJE_INSUFICIENTE,
            // Solo en desarrollo: ayuda a ajustar el parser con fotos reales.
            'texto_ocr' => config('app.debug') ? $lectura['texto_ocr'] : null,
        ]);
    }
}
