<?php
/**
 * §5 PHIPA/NOM — Cifrado AES-256-GCM para campos PHI
 * (Protected Health Information / Información de Salud Protegida)
 *
 * Usa DATA_ENCRYPTION_KEY de conf/.env (64 hex chars = 32 bytes = 256 bits).
 * Formato almacenado: base64( IV‖TAG‖ciphertext )
 *
 * AES-256-GCM provee confidencialidad + integridad (autenticado).
 */

class Cipher
{
    private const METHOD     = 'aes-256-gcm';
    private const TAG_LENGTH = 16;   // 128-bit auth tag

    private static ?string $key = null;

    // ── Key management ───────────────────────────────────────────────────

    private static function getKey(): string
    {
        if (self::$key !== null) return self::$key;

        $hex = getenv('DATA_ENCRYPTION_KEY');
        if (!$hex || strlen(trim($hex)) < 64) {
            throw new RuntimeException(
                'DATA_ENCRYPTION_KEY no configurada o inválida en conf/.env (se requieren 64 caracteres hex)'
            );
        }
        self::$key = hex2bin(substr(trim($hex), 0, 64));
        return self::$key;
    }

    // ── Encrypt ──────────────────────────────────────────────────────────

    /**
     * Cifra un valor de texto plano.
     * Retorna null/empty sin modificar; de lo contrario base64(iv‖tag‖ct).
     */
    public static function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') return $plaintext;

        $key = self::getKey();
        $iv  = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::METHOD));
        $tag = '';

        $ct = openssl_encrypt(
            $plaintext, self::METHOD, $key,
            OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH
        );
        if ($ct === false) {
            throw new RuntimeException('Cipher::encrypt() — openssl_encrypt falló');
        }

        return base64_encode($iv . $tag . $ct);
    }

    // ── Decrypt ──────────────────────────────────────────────────────────

    /**
     * Descifra un valor previamente cifrado con encrypt().
     * Si el valor no parece cifrado (base64 inválido o demasiado corto),
     * lo retorna tal cual (permite transición gradual).
     */
    public static function decrypt(?string $encoded): ?string
    {
        if ($encoded === null || $encoded === '') return $encoded;

        $raw = base64_decode($encoded, true);
        if ($raw === false) return $encoded;   // no es base64 → plaintext

        $ivLen = openssl_cipher_iv_length(self::METHOD);
        if (strlen($raw) < $ivLen + self::TAG_LENGTH + 1) {
            return $encoded;                   // demasiado corto → plaintext
        }

        $iv  = substr($raw, 0, $ivLen);
        $tag = substr($raw, $ivLen, self::TAG_LENGTH);
        $ct  = substr($raw, $ivLen + self::TAG_LENGTH);

        $key = self::getKey();
        $pt  = openssl_decrypt($ct, self::METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($pt === false) {
            // openssl_decrypt falló: o bien la clave es incorrecta / datos
            // corruptos, o bien el valor nunca estuvo cifrado y solo PARECE
            // base64 (transición gradual desde texto plano). En ambos casos
            // devolvemos el valor original para que la app siga funcionando.
            //
            // El log se silencia por defecto para no inundar php_errors.log
            // con falsos positivos en producción. Para depurar, define
            // CIPHER_DEBUG=1 en conf/.env y se registrará 1 vez por valor
            // único por request (con backtrace abreviado).
            if (getenv('CIPHER_DEBUG') === '1') {
                static $logged = [];
                $key = md5($encoded);
                if (!isset($logged[$key])) {
                    $logged[$key] = true;
                    $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
                    $caller = isset($bt[1]) ? (($bt[1]['class'] ?? '') . ($bt[1]['type'] ?? '') . ($bt[1]['function'] ?? '?')) : '?';
                    error_log('[Cipher] decrypt fallback (len=' . strlen($encoded) . ', caller=' . $caller . ')');
                }
            }
            return $encoded;
        }

        return $pt;
    }

    // ── Utilities ────────────────────────────────────────────────────────

    /** ¿El motor OpenSSL soporta AES-256-GCM? */
    public static function isAvailable(): bool
    {
        return function_exists('openssl_encrypt')
            && in_array(self::METHOD, openssl_get_cipher_methods(), true);
    }

    /** ¿Existe DATA_ENCRYPTION_KEY válida en el entorno? */
    public static function hasKey(): bool
    {
        $hex = getenv('DATA_ENCRYPTION_KEY');
        return $hex && strlen(trim($hex)) >= 64;
    }

    /** Genera una clave aleatoria de 256 bits (64 hex). */
    public static function generateKey(): string
    {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }
}
