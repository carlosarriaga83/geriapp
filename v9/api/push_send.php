<?php
/**
 * GeriApp — API: Enviar Push Notification vía Firebase Cloud Messaging (HTTP v1)
 *
 * Uso desde cualquier parte del backend:
 *   require_once __DIR__ . '/api/push_send.php';
 *   pushSendToUser($userId, 'Título', 'Mensaje', ['key' => 'value']);
 *   pushSendToToken($fcmToken, 'Título', 'Mensaje', ['key' => 'value']);
 *
 * Requisitos:
 *   1. Archivo de credenciales de Service Account (JSON) de Firebase
 *   2. Definir en config.php o .env:
 *        define('FCM_CREDENTIALS_PATH', '/ruta/a/firebase-service-account.json');
 *        define('FCM_PROJECT_ID', 'tu-proyecto-firebase');
 */

if (!defined('BASE_URL')) {
    require_once dirname(__DIR__) . '/conf/config.php';
    require_once dirname(__DIR__) . '/db/models.php';
}

/**
 * Enviar push notification a un usuario específico (todos sus dispositivos).
 */
function pushSendToUser(int $userId, string $title, string $body, array $data = []): array
{
    $master = Database::getMaster();
    $stmt   = $master->prepare("SELECT token FROM push_tokens WHERE usuario_id = :uid");
    $stmt->execute([':uid' => $userId]);
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $results = [];
    foreach ($tokens as $token) {
        $results[] = pushSendToToken($token, $title, $body, $data);
    }
    return $results;
}

/**
 * Enviar push notification a un token FCM específico.
 */
function pushSendToToken(string $fcmToken, string $title, string $body, array $data = []): array
{
    $projectId = defined('FCM_PROJECT_ID') ? FCM_PROJECT_ID : '';
    $credsPath = defined('FCM_CREDENTIALS_PATH') ? FCM_CREDENTIALS_PATH : '';

    if ($projectId === '' || $credsPath === '' || !file_exists($credsPath)) {
        return ['success' => false, 'error' => 'FCM no configurado'];
    }

    $accessToken = _fcmGetAccessToken($credsPath);
    if (!$accessToken) {
        return ['success' => false, 'error' => 'No se pudo obtener access token de FCM'];
    }

    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    $message = [
        'message' => [
            'token'        => $fcmToken,
            'notification' => [
                'title' => $title,
                'body'  => $body,
            ],
        ],
    ];

    if (!empty($data)) {
        // FCM data values must be strings
        $message['message']['data'] = array_map('strval', $data);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($message, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'error' => "cURL: $curlErr"];
    }

    $decoded = json_decode($response, true);

    // Si el token es inválido, limpiarlo de la BD
    $cleaned = false;
    if ($httpCode === 404 || ($httpCode >= 400 && isset($decoded['error']))) {
        $errorCode  = $decoded['error']['details'][0]['errorCode'] ?? '';
        $errorStatus = $decoded['error']['status'] ?? '';
        $invalidCodes = ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'];
        if (in_array($errorCode, $invalidCodes, true) || in_array($errorStatus, $invalidCodes, true)) {
            $master = Database::getMaster();
            $stmt   = $master->prepare("DELETE FROM push_tokens WHERE token = :token");
            $stmt->execute([':token' => $fcmToken]);
            $cleaned = true;
        }
    }

    return [
        'success'   => $httpCode === 200,
        'http_code' => $httpCode,
        'response'  => $decoded,
        'cleaned'   => $cleaned,
    ];
}

/**
 * Obtener un access token OAuth2 desde las credenciales de Service Account.
 * Usa JWT firmado con RS256 para solicitar un token de acceso a Google.
 */
function _fcmGetAccessToken(string $credentialsPath): ?string
{
    // Cache en memoria para evitar generar JWT en cada llamada dentro del mismo request
    static $cached = null;
    static $cachedExpiry = 0;
    if ($cached && time() < $cachedExpiry) {
        return $cached;
    }

    $creds = json_decode(file_get_contents($credentialsPath), true);
    if (!$creds || empty($creds['private_key']) || empty($creds['client_email'])) {
        return null;
    }

    $now    = time();
    $expiry = $now + 3600;
    $scope  = 'https://www.googleapis.com/auth/firebase.messaging';

    // JWT Header
    $header = _fcmBase64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));

    // JWT Claims
    $claims = _fcmBase64url(json_encode([
        'iss'   => $creds['client_email'],
        'scope' => $scope,
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $expiry,
    ]));

    // JWT Signature
    $toSign = "$header.$claims";
    $key    = openssl_pkey_get_private($creds['private_key']);
    if (!$key) {
        return null;
    }
    openssl_sign($toSign, $signature, $key, OPENSSL_ALGO_SHA256);
    $jwt = "$toSign." . _fcmBase64url($signature);

    // Exchange JWT for access token
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($resp, true);
    if (!empty($tokenData['access_token'])) {
        $cached      = $tokenData['access_token'];
        $cachedExpiry = $now + ($tokenData['expires_in'] ?? 3500) - 60;
        return $cached;
    }

    return null;
}

/**
 * Base64url encode (JWT-safe).
 */
function _fcmBase64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
