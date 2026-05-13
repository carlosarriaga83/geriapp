<?php
/**
 * GeriApp — Servicio WaAPI
 *
 * Integración con waapi.app (WhatsApp Web JS vía API REST)
 * Docs: https://waapi.readme.io/reference
 *
 * Uso:
 *   $wa = WaAPI::fromConfig($instId);
 *   $wa->sendText('5215551234567', 'Hola mundo');
 */
class WaAPI
{
    private string $token;
    private string $instanceId;
    private string $base;

    public function __construct(string $token, string $instanceId)
    {
        $this->token      = $token;
        $this->instanceId = $instanceId;
        $this->base       = "https://waapi.app/api/v1/instances/{$instanceId}/client/action";
    }

    /**
     * Construye una instancia leyendo las credenciales de la DB.
     */
    public static function fromConfig(int $instId): static
    {
        $cfg = Configuracion::getOrCreate($instId);
        return new static(
            $cfg['wa_api_key']     ?? '',
            $cfg['wa_instance_id'] ?? ''
        );
    }

    // ── Formato de número ─────────────────────────────────────────────────────

    /**
     * Convierte un número de teléfono a chatId de WaAPI.
     * Acepta: "52 155 5123 4567", "+5215551234567", "5215551234567"
     */
    public function formatPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        return $digits . '@c.us';
    }

    // ── Endpoints ─────────────────────────────────────────────────────────────

    /**
     * Verifica si un número tiene cuenta de WhatsApp.
     * Retorna true/false o null si hay error de conexión.
     */
    public function isRegistered(string $phone): ?bool
    {
        $r = $this->post('is-registered-user', [
            'contactId' => $this->formatPhone($phone),
        ]);
        if (!$r['reachable']) return null;
        return (bool)($r['json']['data']['data']['isRegisteredUser'] ?? false);
    }

    /**
     * Envía un mensaje de texto plano.
     *
     * @return array ['ok'=>bool, 'message_id'=>string|null, 'error'=>string|null]
     */
    public function sendText(string $phone, string $message): array
    {
        $r = $this->post('send-message', [
            'chatId'  => $this->formatPhone($phone),
            'message' => $message,
        ]);
        return [
            'ok'         => $r['ok'],
            'message_id' => $r['json']['data']['data']['id']['_serialized'] ?? null,
            'error'      => $r['ok'] ? null : ($r['json']['data']['message'] ?? $r['curl_error'] ?? 'Error desconocido'),
        ];
    }

    /**
     * Envía un archivo multimedia (imagen, PDF, video, audio) por URL pública.
     * La URL debe ser accesible desde los servidores de WaAPI.
     *
     * @return array ['ok'=>bool, 'message_id'=>string|null, 'error'=>string|null]
     */
    public function sendMedia(string $phone, string $mediaUrl, string $caption = '', string $mediaName = ''): array
    {
        $payload = [
            'chatId'   => $this->formatPhone($phone),
            'mediaUrl' => $mediaUrl,
        ];
        if ($caption !== '')   $payload['caption']   = $caption;
        if ($mediaName !== '') $payload['mediaName']  = $mediaName;

        $r = $this->post('send-media', $payload);
        return [
            'ok'         => $r['ok'],
            'message_id' => $r['json']['data']['data']['id']['_serialized'] ?? null,
            'error'      => $r['ok'] ? null : ($r['json']['data']['message'] ?? $r['curl_error'] ?? 'Error desconocido'),
        ];
    }

    /**
     * Prueba de conectividad: envía un mensaje de prueba al número configurado
     * como remitente (wa_phone) o a un número de destino indicado.
     */
    public function testConnection(string $destPhone): array
    {
        $registered = $this->isRegistered($destPhone);
        if ($registered === null) {
            return ['ok' => false, 'error' => 'No se pudo conectar con waapi.app. Verifica el token y el ID de instancia.'];
        }
        if ($registered === false) {
            return ['ok' => false, 'error' => "El número {$destPhone} no tiene cuenta de WhatsApp."];
        }

        $result = $this->sendText($destPhone,
            "✅ GeriApp — Prueba de conexión exitosa\n" .
            "Instancia: {$this->instanceId}\n" .
            date('d/m/Y H:i:s')
        );
        return $result;
    }

    // ── Helpers internos ──────────────────────────────────────────────────────

    /**
     * POST genérico a cualquier action endpoint.
     *
     * @return array {
     *   ok: bool,          // HTTP 2xx + data.status=success + data.data.status=success
     *   reachable: bool,   // HTTP respondió (false = timeout / DNS)
     *   status: int,       // HTTP status code
     *   json: array|null,  // Decoded response
     *   curl_error: string // cURL error si aplica
     * }
     */
    private function post(string $action, array $payload): array
    {
        $url = "{$this->base}/{$action}";
        $ch  = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$this->token}",
                "Content-Type: application/json",
                "Accept: application/json",
            ],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body      = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $json      = $body ? json_decode($body, true) : null;
        $reachable = ($httpCode > 0 && !$curlError);

        // Éxito doble: HTTP 2xx + outer status=success + inner data.status=success
        $ok = $reachable
            && $httpCode >= 200 && $httpCode < 300
            && ($json['status'] ?? '') === 'success'
            && ($json['data']['status'] ?? '') === 'success';

        return [
            'ok'         => $ok,
            'reachable'  => $reachable,
            'status'     => $httpCode,
            'json'       => $json,
            'curl_error' => $curlError,
        ];
    }
}
