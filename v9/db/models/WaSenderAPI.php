<?php
/**
 * GeriApp — Servicio WaSender API
 *
 * Integración con wasenderapi.com (WhatsApp vía sesión propia)
 * Docs: https://wasenderapi.com/api-docs
 *
 * Notas clave:
 *   - Sin instance_id en la URL: el Bearer token ya identifica la sesión de forma única
 *   - Base URL: https://www.wasenderapi.com/api  (con www)
 *   - Envío:  POST {base}/send-message   { "to": "+521234567890", "text": "…" }
 *   - Check:  GET  {base}/on-whatsapp/{+phone}
 *   - El número debe estar en formato E.164 (con prefijo +)
 *   - Respuesta éxito: { "success": true, "data": { "msgId": 100000, … } }
 *   - Respuesta check:  { "success": true, "data": { "exists": true } }
 *
 * Uso:
 *   $wa = WaSenderAPI::fromConfig($instId);
 *   $wa->sendText('+5215551234567', 'Hola mundo');
 */
class WaSenderAPI
{
    private string $token;
    private string $base = 'https://www.wasenderapi.com/api';

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Construye una instancia leyendo las credenciales de la DB.
     */
    public static function fromConfig(int $instId): static
    {
        $cfg = Configuracion::getOrCreate($instId);
        return new static($cfg['wa_api_key'] ?? '');
    }

    // ── Formato de número ─────────────────────────────────────────────────────

    /**
     * Formatea el teléfono en E.164: asegura prefijo '+' y solo dígitos.
     * WaSender requiere E.164 estricto (ej. +14167682436, +5215551234567)
     */
    public function formatPhone(string $phone): string
    {
        // Conservar el + inicial si existe, luego solo dígitos
        $digits = preg_replace('/\D/', '', $phone);
        return '+' . ltrim($digits, '+');
    }

    // ── Endpoints ─────────────────────────────────────────────────────────────

    /**
     * Verifica si un número tiene cuenta de WhatsApp.
     * Retorna true/false o null si hay error de conexión.
     */
    public function isRegistered(string $phone): ?bool
    {
        if ($this->token === '') return null;
        $e164 = $this->formatPhone($phone);
        // El endpoint requiere encodear el '+' como %2B en la URL.
        $r = $this->get('on-whatsapp/' . rawurlencode($e164));
        if (!$r['reachable']) return null;
        // Respuesta: { "success": true, "data": { "exists": true } }
        if (!($r['json']['success'] ?? false)) return null;
        return (bool)($r['json']['data']['exists'] ?? false);
    }

    /**
     * Envía un mensaje de texto plano.
     *
     * @return array ['ok'=>bool, 'message_id'=>string|null, 'error'=>string|null]
     */
    public function sendText(string $phone, string $message): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'message_id' => null, 'error' => 'Token de WaSender no configurado.'];
        }
        $message = self::formatClickableLinks($message);
        $r = $this->post('send-message', [
            'to'   => $this->formatPhone($phone),
            'text' => $message,
        ]);
        // Respuesta exitosa: { "success": true, "data": { "msgId": 100000, ... } }
        $ok = $r['ok'] && ($r['json']['success'] ?? false);
        return [
            'ok'         => $ok,
            'message_id' => $r['json']['data']['msgId'] ?? null,
            'error'      => $ok ? null : self::extractError($r),
        ];
    }

    /**
     * Envía un archivo multimedia (imagen) por URL pública.
     *
     * @return array ['ok'=>bool, 'message_id'=>string|null, 'error'=>string|null]
     */
    public function sendMedia(string $phone, string $mediaUrl, string $caption = '', string $mediaName = ''): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'message_id' => null, 'error' => 'Token de WaSender no configurado.'];
        }
        // WaSender API espera 'imageUrl' (string) y 'text' como caption.
        $payload = [
            'to'       => $this->formatPhone($phone),
            'imageUrl' => $mediaUrl,
        ];
        if ($caption !== '') $payload['text'] = self::formatClickableLinks($caption);

        $r = $this->post('send-message', $payload);
        $ok = $r['ok'] && ($r['json']['success'] ?? false);
        return [
            'ok'         => $ok,
            'message_id' => $r['json']['data']['msgId'] ?? null,
            'error'      => $ok ? null : self::extractError($r),
        ];
    }

    /**
     * Envía un documento (PDF, etc.) por URL pública.
     *
     * @return array ['ok'=>bool, 'message_id'=>string|null, 'error'=>string|null]
     */
    public function sendDocument(string $phone, string $documentUrl, string $caption = '', string $filename = ''): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'message_id' => null, 'error' => 'Token de WaSender no configurado.'];
        }
        // WaSender API espera 'documentUrl' (string), 'fileName' y 'text' como caption.
        $payload = [
            'to'          => $this->formatPhone($phone),
            'documentUrl' => $documentUrl,
        ];
        if ($filename !== '') $payload['fileName'] = $filename;
        if ($caption !== '')  $payload['text']     = self::formatClickableLinks($caption);

        $r = $this->post('send-message', $payload);
        $ok = $r['ok'] && ($r['json']['success'] ?? false);
        return [
            'ok'         => $ok,
            'message_id' => $r['json']['data']['msgId'] ?? null,
            'error'      => $ok ? null : self::extractError($r),
        ];
    }

    /**
     * Prueba de conectividad: envía un mensaje de prueba al número indicado.
     *
     * NOTA: NO usamos isRegistered() aquí porque consume la cuota diaria del
     * endpoint /on-whatsapp (1000 req/día). Mejor intentamos enviar directo
     * y mapeamos los errores t\u00edpicos a mensajes claros.
     */
    public function testConnection(string $destPhone): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'error' => 'Token de WaSender no configurado. Ve a Configuración → Integraciones → WhatsApp.'];
        }

        $result = $this->sendText(
            $destPhone,
            "✅ GeriApp — Prueba de conexión exitosa\n" .
            "Proveedor: WaSender API\n" .
            date('d/m/Y H:i:s')
        );
        if (empty($result['ok'])) {
            $err = (string)($result['error'] ?? '');
            // Mensajes amigables para errores comunes de WaSender.
            if (stripos($err, 'JID does not exist') !== false || stripos($err, 'not exist on whatsapp') !== false) {
                $result['error'] = "El número {$destPhone} no tiene cuenta de WhatsApp activa.";
            } elseif (stripos($err, 'unauthorized') !== false || stripos($err, '401') !== false) {
                $result['error'] = 'Token inválido o sesión desconectada en wasenderapi.com. Reescanea el QR.';
            } elseif (stripos($err, 'session') !== false && stripos($err, 'disconnect') !== false) {
                $result['error'] = 'Sesión de WhatsApp desconectada. Vuelve a escanear el QR en wasenderapi.com.';
            }
        }
        return $result;
    }

    // ── Grupos ────────────────────────────────────────────────────────────────

    /**
     * Convierte un teléfono E.164 a JID de WhatsApp.
     *
     * Aplica normalización para México (lada 52): WhatsApp exige el dígito
     * '1' después del '52' para números móviles (LADA histórica
     * `+52 1 NN XXXXXXXX`). Si el número llega como `+52` + 10 dígitos lo
     * convertimos a `+521` + 10 dígitos. Números que ya tengan el `1` o
     * cualquier otra lada quedan intactos. Para fijos México WhatsApp
     * también acepta el `1`, así que es seguro.
     *
     * Ejemplos:
     *   +5215551234567 → 5215551234567@s.whatsapp.net  (ya tiene 1, sin cambios)
     *   +525551234567  → 5215551234567@s.whatsapp.net  (se inserta el 1)
     *   +14167682436   → 14167682436@s.whatsapp.net    (no es México)
     */
    public function phoneToJid(string $phone): string
    {
        $digits = self::normalizePhone($phone);
        return $digits . '@s.whatsapp.net';
    }

    /**
     * Devuelve el número solo en dígitos, aplicando la normalización México
     * (insertar `1` después del `52` para números de 12 dígitos).
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        // México móvil: 52 + 10 dígitos → 521 + 10 dígitos.
        if (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            $digits = '521' . substr($digits, 2);
        }
        return $digits;
    }

    /**
     * Devuelve el teléfono (solo dígitos) de la sesión conectada al token.
     * GET /api/user → { success:true, data:{ jid|phone|... } }
     * Retorna '' si no se puede determinar.
     *
     * Cacheado en memoria por instancia.
     */
    private ?string $sessionPhoneCache = null;
    public function getSessionPhone(): string
    {
        if ($this->sessionPhoneCache !== null) return $this->sessionPhoneCache;
        if ($this->token === '') { return $this->sessionPhoneCache = ''; }
        $r = $this->get('user');
        $phone = '';
        if ($r['ok'] && is_array($r['json'])) {
            $d = $r['json']['data'] ?? $r['json'];
            // Recorrido recursivo: cualquier valor string que parezca un número
            // largo o un JID @s.whatsapp.net se considera el teléfono propio.
            $found = '';
            $walk = function ($node) use (&$walk, &$found) {
                if ($found !== '') return;
                if (is_array($node)) { foreach ($node as $v) $walk($v); return; }
                if (!is_string($node) || $node === '') return;
                if (str_contains($node, '@s.whatsapp.net') || str_contains($node, '@c.us')) {
                    // Quedarse con la parte local del JID (antes de '@'),
                    // y descartar el sufijo de device id (':NN') si existe.
                    $local = explode('@', $node, 2)[0];
                    $local = explode(':', $local, 2)[0];
                    $digits = preg_replace('/\D/', '', $local);
                    if (strlen($digits) >= 8) { $found = $digits; return; }
                }
            };
            $walk($d);
            if ($found === '') {
                // Fallback: buscar campos típicos directos
                foreach (['phone','phone_number','number','msisdn','jid'] as $k) {
                    if (!empty($d[$k]) && is_string($d[$k])) {
                        $digits = preg_replace('/\D/', '', $d[$k]);
                        if (strlen($digits) >= 8) { $found = $digits; break; }
                    }
                }
            }
            $phone = $found;
        }
        error_log('[WaSender][getSessionPhone] status=' . (int)($r['status'] ?? 0)
            . " phone='" . $phone . "' body=" . substr((string)($r['body'] ?? ''), 0, 300));
        return $this->sessionPhoneCache = $phone;
    }

    /**
     * Crea un grupo de WhatsApp con el nombre y participantes dados.
     * El propio número conectado al token (chatbot) queda automáticamente como
     * superadmin del grupo (creador).
     *
     * @param string $name           Nombre/asunto del grupo (≤25 char preferiblemente)
     * @param array  $participants   Lista de teléfonos en E.164 (con o sin '+').
     * @return array ['ok'=>bool, 'group_jid'=>string|null, 'data'=>array|null, 'error'=>string|null]
     */
    public function createGroup(string $name, array $participants): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'group_jid' => null, 'data' => null, 'error' => 'Token de WaSender no configurado.'];
        }

        // Filtrar el propio número de la sesión (el bot ya queda como creador,
        // añadirlo como participante provoca "bad-request" en WaSender).
        $ownDigits = $this->getSessionPhone();
        $ownJid    = $ownDigits !== '' ? $ownDigits . '@s.whatsapp.net' : '';

        $jids = [];
        $skippedOwn = 0;
        foreach ($participants as $p) {
            $p = trim((string)$p);
            if ($p === '') continue;
            $jid = $this->phoneToJid($p);
            if ($ownJid !== '' && $jid === $ownJid) { $skippedOwn++; continue; }
            $jids[] = $jid;
        }
        $jids = array_values(array_unique($jids));
        if (!$jids) {
            $msg = 'No hay participantes válidos para crear el grupo'
                . ($skippedOwn > 0 ? ' (todos los números coinciden con el número de la sesión del bot, no se puede agregar a sí mismo).' : '.');
            return ['ok' => false, 'group_jid' => null, 'data' => null, 'error' => $msg];
        }

        $payload = [
            'name'         => self::sanitizeGroupName($name),
            'participants' => $jids,
        ];
        $r = $this->post('groups', $payload);

        // Log diagnóstico — incluye el cuerpo crudo de WaSender para depurar
        // errores ambiguos como "bad-request".
        error_log('[WaSender][createGroup] payload=' . json_encode($payload, JSON_UNESCAPED_UNICODE)
            . ' status=' . (int)($r['status'] ?? 0)
            . ' body=' . substr((string)($r['body'] ?? ''), 0, 500));

        $ok   = $r['ok'] && ($r['json']['success'] ?? false);
        $data = $r['json']['data'] ?? null;
        $jid  = is_array($data) ? ($data['id'] ?? null) : null;
        return [
            'ok'        => $ok && $jid,
            'group_jid' => $jid,
            'data'      => $data,
            'error'     => $ok ? null : self::extractError($r),
        ];
    }

    /**
     * Promueve participantes a admin del grupo (el bot debe ser admin).
     */
    public function promoteParticipants(string $groupJid, array $participantsPhones): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'error' => 'Token de WaSender no configurado.'];
        }
        $jids = array_values(array_unique(array_filter(array_map(
            fn($p) => $this->phoneToJid((string)$p),
            $participantsPhones
        ))));
        if (!$jids) return ['ok' => false, 'error' => 'No hay participantes a promover.'];
        $r = $this->put("groups/{$groupJid}/participants/update", [
            'action'       => 'promote',
            'participants' => $jids,
        ]);
        $ok = $r['ok'] && ($r['json']['success'] ?? false);
        return ['ok' => $ok, 'error' => $ok ? null : self::extractError($r), 'data' => $r['json']['data'] ?? null];
    }

    /**
     * Obtiene el invite-link del grupo (para compartir con quienes no se hayan agregado).
     */
    public function getGroupInviteLink(string $groupJid): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'invite_link' => null, 'error' => 'Token de WaSender no configurado.'];
        }
        $r = $this->get("groups/{$groupJid}/invite-link");
        $ok   = $r['ok'] && ($r['json']['success'] ?? false);
        $data = $r['json']['data'] ?? [];
        $link = $data['inviteLink']
            ?? $data['invite_link']
            ?? (isset($data['inviteCode']) ? 'https://chat.whatsapp.com/' . $data['inviteCode'] : null);
        return [
            'ok'          => $ok && $link,
            'invite_link' => $link,
            'error'       => $ok ? null : self::extractError($r),
        ];
    }

    /**
     * Envía un mensaje de texto al grupo (usa el groupJid como destinatario).
     */
    public function sendGroupText(string $groupJid, string $message): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'message_id' => null, 'error' => 'Token de WaSender no configurado.'];
        }
        $message = self::formatClickableLinks($message);
        $r = $this->post('send-message', [
            'to'   => $groupJid,
            'text' => $message,
        ]);
        $ok = $r['ok'] && ($r['json']['success'] ?? false);
        return [
            'ok'         => $ok,
            'message_id' => $r['json']['data']['msgId'] ?? null,
            'error'      => $ok ? null : self::extractError($r),
        ];
    }

    // ── Helpers internos ──────────────────────────────────────────────────────

    /**
     * GET genérico a cualquier endpoint.
     */
    private function get(string $endpoint): array
    {
        $url = "{$this->base}/{$endpoint}";
        $ch  = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$this->token}",
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

        return [
            'ok'         => $reachable && $httpCode >= 200 && $httpCode < 300,
            'reachable'  => $reachable,
            'status'     => $httpCode,
            'json'       => $json,
            'body'       => is_string($body) ? $body : '',
            'curl_error' => $curlError,
        ];
    }

    /**
     * POST genérico a cualquier endpoint.
     */
    private function post(string $endpoint, array $payload): array
    {
        $url = "{$this->base}/{$endpoint}";
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

        return [
            'ok'         => $reachable && $httpCode >= 200 && $httpCode < 300,
            'reachable'  => $reachable,
            'status'     => $httpCode,
            'json'       => $json,
            'body'       => is_string($body) ? $body : '',
            'curl_error' => $curlError,
        ];
    }

    /**
     * PUT genérico a cualquier endpoint (para update participants/settings).
     */
    private function put(string $endpoint, array $payload): array
    {
        $url = "{$this->base}/{$endpoint}";
        $ch  = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
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

        return [
            'ok'         => $reachable && $httpCode >= 200 && $httpCode < 300,
            'reachable'  => $reachable,
            'status'     => $httpCode,
            'json'       => $json,
            'body'       => is_string($body) ? $body : '',
            'curl_error' => $curlError,
        ];
    }

    /**
     * WhatsApp detecta enlaces en el cliente. Mantenerlos en texto plano,
     * absolutos y aislados evita que puntuacion/formato rompan el linkify.
     */
    private static function formatClickableLinks(string $message): string
    {
        $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $message = preg_replace("/\r\n?/", "\n", $message) ?? $message;
        $message = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $message) ?? $message;

        $message = preg_replace_callback(
            '~(?<![A-Za-z0-9@])((?:(?:https?://|www\.)[^\s<>"\'`*_\~]+)|(?:(?:[A-Za-z0-9-]+\.)+[A-Za-z]{2,}(?:/[^\s<>"\'`*_\~]+)?))~iu',
            static function (array $match): string {
                $url = $match[1];
                $url = rtrim($url, " \\t\\n\\r\\0\\x0B.,;:!?)\]}");
                if (!preg_match('~^https?://~i', $url)) {
                    $url = 'https://' . $url;
                }
                return "\n\n" . $url . "\n\n";
            },
            $message
        ) ?? $message;

        $message = preg_replace("/[ \t]+\n/", "\n", $message) ?? $message;
        $message = preg_replace("/\n{3,}/", "\n\n", $message) ?? $message;
        return trim($message);
    }

    /**
     * Sanitiza el subject de un grupo de WhatsApp.
     *  - Quita formato Markdown / asteriscos / underscores que WhatsApp
     *    interpreta como negrita/cursiva y suele rechazar en el subject.
     *  - Reemplaza em/en dash por guion normal.
     *  - Colapsa espacios.
     *  - Trunca a 25 caracteres (límite histórico de WhatsApp para subjects).
     */
    private static function sanitizeGroupName(string $name): string
    {
        $name = str_replace(["—", "–"], '-', $name);
        // Quitar caracteres de formato/control problemáticos
        $name = preg_replace('/[\*_~`\x00-\x1F\x7F]+/u', '', $name) ?? $name;
        // Colapsar espacios
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if ($name === '') $name = 'Soporte';
        return mb_substr($name, 0, 25);
    }

    /**
     * Extrae un mensaje de error legible a partir de la respuesta cruda.
     * WaSender suele devolver { success:false, message:"…" } o { error:"…" }.
     * Si no hay JSON, regresa status + curl_error o un fragmento del body.
     */
    private static function extractError(array $r): string
    {
        $j = $r['json'] ?? null;
        if (is_array($j)) {
            // Validation errors style: { errors: { campo: ["msg"] } }
            if (!empty($j['errors']) && is_array($j['errors'])) {
                $first = reset($j['errors']);
                if (is_array($first)) $first = reset($first);
                if (is_string($first) && $first !== '') return $first;
            }
            foreach (['message','error','msg','detail'] as $k) {
                if (!empty($j[$k]) && is_string($j[$k])) return $j[$k];
            }
        }
        if (!empty($r['curl_error'])) return 'Conexión: ' . $r['curl_error'];
        $status = (int)($r['status'] ?? 0);
        $bodySnip = isset($r['body']) ? trim(substr($r['body'], 0, 240)) : '';
        if ($status === 401 || $status === 403) {
            return 'Token rechazado por WaSender (HTTP ' . $status . '). Verifica que el Personal Access Token sea correcto y que la sesión esté conectada.';
        }
        if ($status === 422) return 'Datos inválidos (HTTP 422)' . ($bodySnip !== '' ? ': ' . $bodySnip : '');
        if ($status === 429) return 'Límite de peticiones alcanzado (HTTP 429). Espera unos minutos.';
        if ($status >= 500)  return 'Error del servidor WaSender (HTTP ' . $status . ').';
        if ($status === 0)   return 'Sin respuesta del servidor (verifica conectividad y SSL).';
        return 'Error HTTP ' . $status . ($bodySnip !== '' ? ': ' . $bodySnip : '');
    }
}
