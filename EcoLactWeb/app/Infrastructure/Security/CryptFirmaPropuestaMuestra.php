<?php

namespace App\Infrastructure\Security;

use App\Application\Calidad\Contracts\FirmaPropuestaMuestra;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/** Firma cifrada con la APP_KEY (no se puede fabricar ni alterar desde el cliente). */
final class CryptFirmaPropuestaMuestra implements FirmaPropuestaMuestra
{
    private const VIGENCIA_SEGUNDOS = 12 * 60 * 60;

    public function firmar(int $entregaId, int $analistaUserId): string
    {
        return Crypt::encryptString((string) json_encode([
            'entrega_id' => $entregaId,
            'user_id' => $analistaUserId,
            'expira' => time() + self::VIGENCIA_SEGUNDOS,
        ]));
    }

    public function esValida(?string $token, int $entregaId, int $analistaUserId): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        try {
            $propuesta = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return false;
        }

        return is_array($propuesta)
            && ($propuesta['entrega_id'] ?? null) === $entregaId
            && ($propuesta['user_id'] ?? null) === $analistaUserId
            && ($propuesta['expira'] ?? 0) >= time();
    }
}
