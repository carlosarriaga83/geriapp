<?php
/**
 * Migration: Dedupe contactos_json across all residents.
 * Removes duplicates by signature (_usuario_id || email || nombre+telefono).
 *
 * Usage:
 *   php v9/db/migrate_dedupe_contactos_json.php
 *   or open in browser:  /v9/db/migrate_dedupe_contactos_json.php
 */

require_once __DIR__ . '/Database.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== Dedupe contactos_json ===\n";

$pdo = Database::getMaster();
$st  = $pdo->query("SELECT id, contactos_json FROM residentes WHERE contactos_json IS NOT NULL AND contactos_json != ''");
$updated = 0;
$scanned = 0;
$upd     = $pdo->prepare("UPDATE residentes SET contactos_json = ? WHERE id = ?");

while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $scanned++;
    $arr = json_decode($row['contactos_json'], true);
    if (!is_array($arr) || !$arr) continue;

    $seen = [];
    $clean = [];
    foreach ($arr as $c) {
        if (!is_array($c)) continue;
        $uid   = isset($c['_usuario_id']) ? (int)$c['_usuario_id'] : 0;
        $email = strtolower(trim((string)($c['email'] ?? '')));
        $name  = strtolower(trim((string)($c['nombre'] ?? '')));
        $tel   = trim((string)($c['telefono'] ?? ''));
        $sig   = $uid ? "u:$uid" : ($email !== '' ? $email : ($name !== '' ? "$name|$tel" : ''));
        if ($sig === '') { $clean[] = $c; continue; }
        if (isset($seen[$sig])) continue;
        $seen[$sig] = true;
        $clean[] = $c;
    }

    if (count($clean) !== count($arr)) {
        $upd->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), (int)$row['id']]);
        $updated++;
        echo "  - residente_id={$row['id']}: " . count($arr) . " → " . count($clean) . " contactos\n";
    }
}

echo "\nScanned: $scanned residentes\n";
echo "Updated: $updated residentes\n";
echo "Done.\n";
