<?php
/**
 * GeriApp — Internationalization helper
 *
 * Usage:
 *   require_once __DIR__ . '/lang/i18n.php';
 *   echo t('btn_save');                       // "Guardar" or "Save"
 *   echo t('confirm_delete_user', [':name' => 'Juan']);  // interpolation
 *
 * The current language is resolved from (in order):
 *   1. Cookie `geriapp_lang`
 *   2. Defaults to 'es'
 *
 * Available languages are auto-discovered from .php files in this directory
 * (excluding i18n.php itself).
 */

// Resolve language
$_geriLang = $_COOKIE['geriapp_lang'] ?? 'es';
$_langFile = __DIR__ . '/' . basename($_geriLang) . '.php';
if (!file_exists($_langFile)) {
    $_geriLang = 'es';
    $_langFile = __DIR__ . '/es.php';
}
define('APP_LANG', $_geriLang);

// Load dictionary
$_T = require $_langFile;

/**
 * Translate a key, with optional placeholder replacement.
 * Placeholders use :key syntax:  t('hello', [':name' => 'World'])
 */
function t(string $key, array $params = []): string {
    global $_T;
    $str = $_T[$key] ?? $key;
    if ($params) {
        $str = strtr($str, $params);
    }
    return $str;
}

/**
 * Return the full dictionary as JSON for injection into JavaScript.
 */
function t_json(): string {
    global $_T;
    return json_encode($_T, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * List available languages with their native labels.
 */
function t_available(): array {
    $langs = [];
    foreach (glob(__DIR__ . '/*.php') as $f) {
        $code = basename($f, '.php');
        if ($code === 'i18n') continue;
        $meta = [];
        $lines = file($f, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            if (preg_match("/'_lang_label'\s*=>\s*'(.+?)'/", $line, $m)) {
                $meta['label'] = $m[1];
            }
            if (preg_match("/'_lang_flag'\s*=>\s*'(.+?)'/", $line, $m)) {
                $meta['flag'] = $m[1];
            }
        }
        $langs[$code] = [
            'code'  => $code,
            'label' => $meta['label'] ?? strtoupper($code),
            'flag'  => $meta['flag'] ?? '',
        ];
    }
    return $langs;
}
