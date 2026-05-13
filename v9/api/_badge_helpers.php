<?php
/**
 * GeriApp — Shared badge calculation helpers
 *
 * Used by both /api/residentes.php (cuidados_estado list) and
 * /api/cuidados.php (dashboard batch) so that badge counts shown in the
 * residentes list and on the resident dashboard are guaranteed to match.
 *
 * Single source of truth: server-side, server timezone (date_default_timezone_set
 * in conf/config.php). The dashboard UI consumes these computed values directly
 * instead of re-deriving them in client-side JS (which previously could drift
 * from the API by ±1h when the institution timezone differs from the server
 * default — e.g. Etc/GMT+5 vs America/Mexico_City after Mexico abolished DST).
 */

if (!function_exists('_rx_parse_horarios')) {
    function _rx_parse_horarios(mixed $raw): array {
        if (is_array($raw)) return array_values(array_filter($raw, fn($v) => is_string($v) && strlen(trim($v)) >= 4));
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return array_values(array_filter($decoded, fn($v) => is_string($v) && strlen(trim($v)) >= 4));
        return [];
    }
}

if (!function_exists('_signos_out_count')) {
    function _signos_out_count(?array $d): int {
        if (!$d) return 0;
        $ranges = [
            'temperatura'             => ['normal' => [36, 37.5],  'warn' => [35.5, 38]],
            'frecuencia_respiratoria' => ['normal' => [12, 20],    'warn' => [10, 25]],
            'frecuencia_cardiaca'     => ['normal' => [60, 100],   'warn' => [50, 120]],
            'pa_sistolica'            => ['normal' => [90, 140],   'warn' => [80, 160]],
            'pa_diastolica'           => ['normal' => [60, 90],    'warn' => [50, 100]],
            'spo2'                    => ['normal' => [95, 100],   'warn' => [90, 100]],
            'glucosa'                 => ['normal' => [70, 140],   'warn' => [54, 180]],
        ];
        $out = 0;
        foreach ($ranges as $k => $r) {
            if (!isset($d[$k]) || $d[$k] === '' || $d[$k] === null) continue;
            $v = (float)$d[$k];
            if ($v < $r['normal'][0] || $v > $r['normal'][1]) $out++;
        }
        return $out;
    }
}

if (!function_exists('_med_overdue_count')) {
    function _med_overdue_count(array $rxList, array $todayRegs, string $today, string $nowDate, string $nowTime): int {
        $admin = [];   // nameLower => [time]
        $skip  = [];   // nameLower => ['all' => bool, 'times' => [time=>true]]

        foreach ($todayRegs as $r) {
            if (($r['categoria'] ?? '') !== 'medicacion') continue;
            $d = is_array($r['datos'] ?? null) ? $r['datos'] : [];
            $hora = substr((string)($r['hora'] ?? ''), 0, 5);

            $meds = array_merge($d['medicamentos_seleccionados'] ?? [], $d['medicamentos_extra'] ?? []);
            foreach ($meds as $name) {
                if (!is_string($name) || $name === '') continue;
                $key = mb_strtolower($name);
                if (!isset($admin[$key])) $admin[$key] = [];
                if ($hora) $admin[$key][] = $hora;
                $covered = $d['horarios_cubiertos'][$name] ?? null;
                if (is_array($covered)) {
                    foreach ($covered as $t) {
                        $t5 = substr((string)$t, 0, 5);
                        if ($t5 && !in_array($t5, $admin[$key], true)) $admin[$key][] = $t5;
                    }
                }
            }

            $noAdmin = $d['medicamentos_no_administrados'] ?? [];
            foreach ($noAdmin as $na) {
                $name = mb_strtolower((string)($na['nombre'] ?? ''));
                if (!$name) continue;
                if (!isset($skip[$name])) $skip[$name] = ['all' => false, 'times' => []];
                $times = $na['horarios'] ?? [];
                if (!is_array($times) || !count($times)) {
                    $skip[$name]['all'] = true;
                } else {
                    foreach ($times as $t) {
                        $t5 = substr((string)$t, 0, 5);
                        if ($t5) $skip[$name]['times'][$t5] = true;
                    }
                }
            }
        }

        $pending = 0;
        foreach ($rxList as $rx) {
            $name = mb_strtolower((string)($rx['nombre'] ?? ''));
            if (!$name) continue;
            // Mirror dashboard: skip Rx whose start date is in the future for `today`.
            $inicio = $rx['inicio'] ?? null;
            if ($inicio && $inicio > $today) continue;
            $horarios = _rx_parse_horarios($rx['horarios'] ?? null);

            // Mirror dashboard: Rx with no horarios counts as 1 pending slot
            // unless any administration has been recorded today (skipped/done).
            if (!count($horarios)) {
                $isSkipped = isset($skip[$name]) && $skip[$name]['all'];
                $isDone = !empty($admin[$name]);
                if (!$isSkipped && !$isDone && $today === $nowDate) $pending++;
                continue;
            }

            foreach ($horarios as $h) {
                $h5 = substr((string)$h, 0, 5);
                if (!$h5) continue;
                // Only overdue slots for today trigger warning badges.
                if ($today !== $nowDate || $h5 > $nowTime) continue;

                $isSkipped = isset($skip[$name]) && ($skip[$name]['all'] || !empty($skip[$name]['times'][$h5]));
                $isDone = false;
                if (!empty($admin[$name])) {
                    foreach ($admin[$name] as $at) {
                        if (substr((string)$at, 0, 5) === $h5) { $isDone = true; break; }
                    }
                }
                if (!$isSkipped && !$isDone) $pending++;
            }
        }
        return $pending;
    }
}

if (!function_exists('_heces_hours_since')) {
    /**
     * Hours elapsed since the given heces record (last bowel movement).
     * Returns null if no record. Uses server timezone.
     */
    function _heces_hours_since(?array $ultHeces): ?int {
        if (!$ultHeces || empty($ultHeces['fecha'])) return null;
        $fecha = (string)$ultHeces['fecha'];
        $hora  = (string)($ultHeces['hora'] ?? '00:00');
        if (strlen($hora) === 5) $hora .= ':00';
        $ts = strtotime($fecha . ' ' . $hora);
        if (!$ts) return null;
        return (int)floor((time() - $ts) / 3600);
    }
}
