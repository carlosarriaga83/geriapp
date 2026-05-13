<?php
/**
 * Migration: Tablas para el asistente de chat IA.
 *
 *  chat_asistente_conv      → conversaciones (1 por hilo)
 *  chat_asistente_mensajes  → mensajes (rol = 'user' | 'assistant')
 *
 * Mensajes encriptados via EncryptionMap (campo: contenido).
 *
 * Multi-tenant: ambas tablas viven en la BD del tenant (Database::getTenant).
 */
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../includes/EncryptionMap.php';

function ensureChatTables(PDO $db, string $label): void {
    // Conversaciones
    $exists = $db->query("SHOW TABLES LIKE 'chat_asistente_conv'")->rowCount() > 0;
    if (!$exists) {
        $db->exec("
            CREATE TABLE chat_asistente_conv (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                institucion_id  INT UNSIGNED NOT NULL,
                usuario_id      INT UNSIGNED NOT NULL,
                titulo          VARCHAR(180) DEFAULT NULL,
                creado_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_chat_conv_user (usuario_id, actualizado_at),
                INDEX idx_chat_conv_inst (institucion_id, actualizado_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "[$label] Created table: chat_asistente_conv\n";
    } else {
        echo "[$label] Table already exists: chat_asistente_conv\n";
    }

    // Mensajes
    $exists = $db->query("SHOW TABLES LIKE 'chat_asistente_mensajes'")->rowCount() > 0;
    if (!$exists) {
        $db->exec("
            CREATE TABLE chat_asistente_mensajes (
                id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                conversacion_id  BIGINT UNSIGNED NOT NULL,
                rol              ENUM('user','assistant','system') NOT NULL,
                contenido        MEDIUMTEXT,
                contenido_enc    MEDIUMBLOB DEFAULT NULL,
                tokens_in        INT UNSIGNED DEFAULT NULL,
                tokens_out       INT UNSIGNED DEFAULT NULL,
                creado_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_chat_msg_conv (conversacion_id, creado_at),
                CONSTRAINT fk_chat_msg_conv FOREIGN KEY (conversacion_id)
                    REFERENCES chat_asistente_conv(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "[$label] Created table: chat_asistente_mensajes\n";
    } else {
        echo "[$label] Table already exists: chat_asistente_mensajes\n";
    }
}

// ── Detect mode ─────────────────────────────────────────────────────────
// Si hay una BD de tenant explícita (env TENANT_ID o argv[1]), corre solo allí.
// Si no, recorre todas las instituciones.
$db = Database::getInstance();

$specificInst = null;
if (!empty($argv[1]) && ctype_digit($argv[1])) {
    $specificInst = (int)$argv[1];
}

if ($specificInst) {
    $tdb = Database::getTenant($specificInst);
    ensureChatTables($tdb, "tenant#$specificInst");
} else {
    $rows = $db->query("SELECT id, nombre FROM instituciones")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        try {
            $tdb = Database::getTenant((int)$row['id']);
            ensureChatTables($tdb, "tenant#{$row['id']} ({$row['nombre']})");
        } catch (\Throwable $e) {
            echo "[tenant#{$row['id']}] ERROR: " . $e->getMessage() . "\n";
        }
    }
}

echo "Migration complete.\n";
