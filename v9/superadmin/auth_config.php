<?php
/**
 * GeriApp — Superadmin Auth Config
 *
 * Credenciales dedicadas para el portal de superadmin.
 * Independiente de la tabla usuarios de la BD principal.
 */

define('SA_CREDENTIALS', [
    [
        'user'  => 'superadmin',
        'pass'  => '$2y$10$6.4trmHCbiFzeCkBv3ri0.iW9qmklWhXoIBbPa/dwOPbE6Oo7KYZe', // GeriAdmin2026!
        'name'  => 'Super Admin',
    ],
    [
        'user'  => 'emergency',
        'pass'  => '$2y$10$BbXgj/F1BxecDr59hsJe.evxLvWRLx2RiYIZfnbnJZApZ8ZQIQNhe', // GeriEmergency2026!
        'name'  => 'Emergency Admin',
    ],
]);

// To generate a new password hash, run:
// php -r "echo password_hash('YourNewPassword', PASSWORD_DEFAULT);"
