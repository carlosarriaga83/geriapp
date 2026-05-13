<?php
/**
 * GeriApp — API: Push Test
 *
 * POST /api/push_test.php → envía push de prueba al usuario autenticado
 */

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__) . '/api/push_send.php';

api_auth();
api_require_method('POST');

$userId = api_user_id();
$master = Database::getMaster();

// Obtener tokens del usuario
$stmt = $master->prepare("SELECT token, platform FROM push_tokens WHERE usuario_id = ?");
$stmt->execute([$userId]);
$tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tokens)) {
    api_ok([
        'total'   => 0,
        'success' => 0,
        'errors'  => [],
        'message' => 'No hay tokens registrados para tu usuario',
    ]);
}

$title = '🔔 GeriApp — Prueba';
$body  = 'Si ves esta notificación, las push notifications están funcionando correctamente.';

$results  = [];
$okCount  = 0;
$errors   = [];
$cleaned  = 0;

foreach ($tokens as $t) {
    $r = pushSendToToken($t['token'], $title, $body, ['type' => 'test']);
    $results[] = $r;
    if ($r['success']) {
        $okCount++;
    } else {
        $err = $r['error'] ?? '';
        if (!$err && isset($r['response']['error']['message'])) {
            $err = $r['response']['error']['message'];
        }
        $errors[] = ($t['platform'] ?? '?') . ': ' . ($err ?: 'Error desconocido');
        if (!empty($r['cleaned'])) {
            $cleaned++;
        }
    }
}

api_ok([
    'total'   => count($tokens),
    'success' => $okCount,
    'errors'  => $errors,
    'cleaned' => $cleaned,
]);
