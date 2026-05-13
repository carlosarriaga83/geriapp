<?php
/**
 * GeriApp — Stripe API Client (lightweight cURL wrapper)
 *
 * Sin dependencias externas (no requiere stripe-php). Cubre las operaciones
 * necesarias para F3-F6: Products, Prices, Customers, Subscriptions, Checkout
 * Sessions, Billing Portal Sessions, y verificación de webhooks (HMAC-SHA256).
 *
 * Configuración vía v9/secretos/stripe.json:
 * {
 *   "mode": "test" | "live",
 *   "test": {
 *     "secret_key":      "sk_test_xxx",
 *     "publishable_key": "pk_test_xxx",
 *     "webhook_secret":  "whsec_xxx"
 *   },
 *   "live": { ... mismas claves ... }
 * }
 *
 * Política de seguridad:
 *  - El archivo `stripe.json` vive en `/secretos/` (Deny from all en .htaccess).
 *  - Nunca se serializa la `secret_key` en respuestas API.
 *  - Toda llamada usa `Idempotency-Key` cuando es POST de creación.
 *  - HTTP 4xx/5xx convierten en RuntimeException con el mensaje de Stripe.
 *  - Soporte multi-divisa (los Price se crean uno por moneda+intervalo).
 *
 * No persiste nada por sí mismo. Los IDs devueltos deben guardarse en BD por
 * el código que invoca esta clase (ver sync_stripe.php).
 */

declare(strict_types=1);

class StripeClient
{
    /** Endpoint base de la API REST. */
    private const API_BASE = 'https://api.stripe.com/v1';
    /** Versión de API fijada para evitar breaking changes silenciosos. */
    private const API_VERSION = '2024-04-10';

    private string $secretKey;
    private string $mode;
    private ?string $webhookSecret;

    public function __construct(?string $forcedMode = null)
    {
        $cfg = self::loadConfig();
        $mode = $forcedMode ?: ($cfg['mode'] ?? 'test');
        if (!in_array($mode, ['test','live'], true)) {
            throw new RuntimeException("Modo Stripe inválido: $mode");
        }
        $bucket = $cfg[$mode] ?? null;
        if (!is_array($bucket) || empty($bucket['secret_key'])) {
            throw new RuntimeException("Falta configuración Stripe para modo «{$mode}». Edita /secretos/stripe.json");
        }
        $this->mode = $mode;
        $this->secretKey = $bucket['secret_key'];
        $this->webhookSecret = $bucket['webhook_secret'] ?? null;
    }

    /** Lee config desde /secretos/stripe.json. Devuelve estructura cruda. */
    public static function loadConfig(): array
    {
        $path = dirname(__DIR__, 2) . '/secretos/stripe.json';
        if (!is_file($path)) {
            throw new RuntimeException('No existe /secretos/stripe.json. Crea el archivo con tus claves de Stripe (test/live).');
        }
        $raw = file_get_contents($path);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('stripe.json no es JSON válido.');
        }
        return $data;
    }

    /** Devuelve la publishable_key (segura para el frontend). */
    public static function publishableKey(?string $forcedMode = null): string
    {
        $cfg  = self::loadConfig();
        $mode = $forcedMode ?: ($cfg['mode'] ?? 'test');
        return (string)($cfg[$mode]['publishable_key'] ?? '');
    }

    public function mode(): string { return $this->mode; }

    // ─────────────────────────────────────────────────────────────────────
    // HTTP core
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Llamada genérica a la API. $method = GET|POST|DELETE.
     * $params se serializa como x-www-form-urlencoded (formato nativo Stripe).
     * $idempotencyKey: clave para reintento seguro de POSTs de creación.
     */
    public function request(string $method, string $path, array $params = [], ?string $idempotencyKey = null): array
    {
        $url = self::API_BASE . '/' . ltrim($path, '/');
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Stripe-Version: ' . self::API_VERSION,
        ];
        if ($idempotencyKey !== null && $method === 'POST') {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        $ch = curl_init();
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'GeriApp/9 PHP-cURL StripeClient',
        ];
        if ($method === 'GET') {
            if ($params) $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
            $opts[CURLOPT_URL] = $url;
        } elseif ($method === 'POST') {
            $opts[CURLOPT_URL] = $url;
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
        } elseif ($method === 'DELETE') {
            $opts[CURLOPT_URL] = $url;
            $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
            if ($params) $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
        } else {
            throw new RuntimeException("Método HTTP no soportado: $method");
        }

        curl_setopt_array($ch, $opts);
        $body  = curl_exec($ch);
        $errNo = curl_errno($ch);
        $err   = curl_error($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo !== 0) {
            throw new RuntimeException("Error de red Stripe ($errNo): $err");
        }
        $json = json_decode((string)$body, true);
        if (!is_array($json)) {
            throw new RuntimeException("Respuesta Stripe no JSON (HTTP $code): " . substr((string)$body, 0, 200));
        }
        if ($code >= 400 || isset($json['error'])) {
            $msg = $json['error']['message'] ?? "HTTP $code";
            throw new RuntimeException("Stripe error: $msg");
        }
        return $json;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Products & Prices
    // ─────────────────────────────────────────────────────────────────────

    public function createProduct(string $name, ?string $description = null, array $metadata = [], ?string $idemKey = null): array
    {
        $params = ['name' => $name];
        if ($description !== null && $description !== '') $params['description'] = $description;
        foreach ($metadata as $k => $v) $params['metadata[' . $k . ']'] = (string)$v;
        return $this->request('POST', 'products', $params, $idemKey);
    }

    public function retrieveProduct(string $productId): array
    {
        return $this->request('GET', "products/$productId");
    }

    /**
     * Crea un Price recurrente.
     * @param string $productId  ID del Product padre.
     * @param int    $unitAmount Monto en la unidad mínima (centavos para MXN/USD/CAD; pesos enteros para COP).
     * @param string $currency   ISO 4217 lowercase (mxn, usd, cop, cad).
     * @param string $interval   month | year
     */
    public function createRecurringPrice(string $productId, int $unitAmount, string $currency, string $interval, array $metadata = [], ?string $idemKey = null): array
    {
        $params = [
            'product'          => $productId,
            'unit_amount'      => $unitAmount,
            'currency'         => strtolower($currency),
            'recurring[interval]' => $interval,
        ];
        foreach ($metadata as $k => $v) $params['metadata[' . $k . ']'] = (string)$v;
        return $this->request('POST', 'prices', $params, $idemKey);
    }

    /** Convierte un monto decimal (ej. 199.50) a la unidad mínima Stripe según moneda. */
    public static function toStripeAmount(float $amount, string $currency): int
    {
        // Monedas de cero decimales (Stripe docs). COP en realidad acepta 2
        // decimales en algunos contextos pero el estándar es entero.
        $zeroDecimal = ['BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF'];
        if (in_array(strtoupper($currency), $zeroDecimal, true)) {
            return (int)round($amount);
        }
        return (int)round($amount * 100);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Customers
    // ─────────────────────────────────────────────────────────────────────

    public function createCustomer(string $email, ?string $name = null, array $metadata = [], ?string $idemKey = null): array
    {
        $params = ['email' => $email];
        if ($name) $params['name'] = $name;
        foreach ($metadata as $k => $v) $params['metadata[' . $k . ']'] = (string)$v;
        return $this->request('POST', 'customers', $params, $idemKey);
    }

    public function retrieveCustomer(string $customerId): array
    {
        return $this->request('GET', "customers/$customerId");
    }

    // ─────────────────────────────────────────────────────────────────────
    // Checkout & Billing Portal (para F5)
    // ─────────────────────────────────────────────────────────────────────

    public function createCheckoutSession(array $params, ?string $idemKey = null): array
    {
        return $this->request('POST', 'checkout/sessions', $params, $idemKey);
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        return $this->request('GET', "checkout/sessions/$sessionId");
    }

    public function createBillingPortalSession(string $customerId, string $returnUrl): array
    {
        return $this->request('POST', 'billing_portal/sessions', [
            'customer'   => $customerId,
            'return_url' => $returnUrl,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Subscriptions (para F4 webhooks principalmente)
    // ─────────────────────────────────────────────────────────────────────

    public function retrieveSubscription(string $subId): array
    {
        return $this->request('GET', "subscriptions/$subId");
    }

    public function cancelSubscription(string $subId, bool $atPeriodEnd = true): array
    {
        if ($atPeriodEnd) {
            return $this->request('POST', "subscriptions/$subId", ['cancel_at_period_end' => 'true']);
        }
        return $this->request('DELETE', "subscriptions/$subId");
    }

    // ─────────────────────────────────────────────────────────────────────
    // Webhook signature verification (para F4)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Verifica la firma de un webhook entrante. Lanza RuntimeException si falla.
     * @param string $payload         Cuerpo crudo del request (file_get_contents('php://input'))
     * @param string $signatureHeader Header Stripe-Signature
     * @param int    $tolerance       Segundos de tolerancia para evitar replay (default 5min)
     * @return array Evento decodificado.
     */
    public function verifyWebhook(string $payload, string $signatureHeader, int $tolerance = 300): array
    {
        if (!$this->webhookSecret) {
            throw new RuntimeException('webhook_secret no configurado en stripe.json');
        }
        // Header formato: t=timestamp,v1=signature[,v1=signature2]
        $parts = [];
        foreach (explode(',', $signatureHeader) as $kv) {
            $kv = trim($kv);
            if (strpos($kv, '=') === false) continue;
            [$k, $v] = explode('=', $kv, 2);
            $parts[$k][] = $v;
        }
        $timestamp = (int)($parts['t'][0] ?? 0);
        $sigs      = $parts['v1'] ?? [];
        if ($timestamp <= 0 || empty($sigs)) {
            throw new RuntimeException('Stripe-Signature mal formado');
        }
        if (abs(time() - $timestamp) > $tolerance) {
            throw new RuntimeException('Webhook timestamp fuera de tolerancia (replay attack?)');
        }
        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $this->webhookSecret);
        $valid = false;
        foreach ($sigs as $sig) {
            if (hash_equals($expected, $sig)) { $valid = true; break; }
        }
        if (!$valid) throw new RuntimeException('Firma de webhook inválida');
        $event = json_decode($payload, true);
        if (!is_array($event)) throw new RuntimeException('Payload de webhook no es JSON');
        return $event;
    }
}
