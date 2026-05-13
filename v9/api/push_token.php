<?php
/**
 * GeriApp — API: Push Token Management
 *
 * POST   → Guardar/actualizar token FCM del dispositivo
 *           En iOS, el cliente envía un token APNs nativo (hex).
 *           El servidor lo convierte a token FCM vía Firebase Instance ID batchImport.
 * DELETE → Eliminar token (ej: al hacer logout)
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/push_send.php'; // para _fcmGetAccessToken()

api_auth();
api_require_method('POST', 'DELETE');

$userId = api_user_id();
$master = Database::getMaster();

if (api_method() === 'POST') {
    $body     = api_body();
    $token    = trim($body['token'] ?? '');
    $platform = $body['platform'] ?? 'android';

    if ($token === '') {
        api_error('Token requerido', 422);
    }
    if (!in_array($platform, ['android', 'ios', 'web'], true)) {
        $platform = 'android';
    }

    // iOS: el token es APNs nativo → convertir a FCM vía batchImport
    if ($platform === 'ios') {
        $fcmToken = _apnsToFcm($token);
        if ($fcmToken) {
            $token = $fcmToken;
        } else {
            // Si la conversión falla, guardamos el APNs token de todas formas
            // para reintentar después, pero informamos al cliente
            error_log("[GeriApp] APNs→FCM conversion failed for user $userId, saving APNs token as-is");
        }
    }

    // Upsert: si el token ya existe, actualizar usuario y timestamp
    $stmt = $master->prepare("
        INSERT INTO push_tokens (usuario_id, token, platform)
        VALUES (:uid, :token, :platform)
        ON DUPLICATE KEY UPDATE
            usuario_id = :uid2,
            platform   = :platform2,
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        ':uid'       => $userId,
        ':token'     => $token,
        ':platform'  => $platform,
        ':uid2'      => $userId,
        ':platform2' => $platform,
    ]);

    api_ok(null, 'Token guardado');
}

if (api_method() === 'DELETE') {
    $body  = api_body();
    $token = trim($body['token'] ?? '');

    if ($token === '') {
        api_error('Token requerido', 422);
    }

    $stmt = $master->prepare("DELETE FROM push_tokens WHERE token = :token AND usuario_id = :uid");
    $stmt->execute([':token' => $token, ':uid' => $userId]);

    api_ok(null, 'Token eliminado');
}

/**
 * Convertir un token APNs (hex) a token FCM usando Firebase Instance ID batchImport.
 * @see https://developers.google.com/instance-id/reference/server#create_registration_tokens_for_apns_tokens
 */
function _apnsToFcm(string $apnsToken): ?string
{
    $credsPath = defined('FCM_CREDENTIALS_PATH') ? FCM_CREDENTIALS_PATH : '';
    if ($credsPath === '' || !file_exists($credsPath)) {
        return null;
    }

    $accessToken = _fcmGetAccessToken($credsPath);
    if (!$accessToken) {
        return null;
    }

    // Leer el GOOGLE_APP_ID (gmp_app_id) desde el service account o definirlo
    // El bundle ID es com.geriapp.admin
    $appId = defined('FCM_IOS_APP_ID') ? FCM_IOS_APP_ID : '';
    $bundleId = 'com.geriapp.admin';

    $payload = [
        'application' => $bundleId,
        'sandbox'     => false,     // producción
        'apns_tokens' => [$apnsToken],
    ];

    $ch = curl_init('https://iid.googleapis.com/iid/v1:batchImport');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'access_token_auth: true',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode !== 200) {
        error_log("[GeriApp] APNs→FCM batchImport HTTP $httpCode, cURL: $curlErr, response: $response");
        return null;
    }

    $decoded = json_decode($response, true);
    $results = $decoded['results'] ?? [];

    if (!empty($results[0]) && ($results[0]['status'] ?? '') === 'OK' && !empty($results[0]['registration_token'])) {
        return $results[0]['registration_token'];
    }

    error_log("[GeriApp] APNs→FCM batchImport unexpected result: $response");
    return null;
}
