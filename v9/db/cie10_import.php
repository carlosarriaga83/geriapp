<?php
/**
 * GeriApp — Importador del Catálogo CIE-10 Completo [DESACTIVADO]
 * 
 * ⚠ El módulo de Expediente Médico (incluido CIE-10) fue migrado a MediApp.
 *   Este archivo se conserva solo como referencia histórica.
 *
 * Modo original:
 *   1. CLI:  php db/cie10_import.php
 *   2. Web:  /db/cie10_import.php (protegido por clave)
 *   3. CSV:  php db/cie10_import.php --file=ruta/al/archivo.csv
 *
 * El archivo CSV esperado debe tener columnas: codigo, descripcion
 * (opcionalmente: capitulo, grupo)
 *
 * Fuentes soportadas:
 *   - CSV/TSV local (--file=)
 *   - Descarga automática desde API pública de la OMS (ICD API)
 *   - Seed mínimo integrado (~170 códigos geriátricos frecuentes)
 */

echo "⚠ DESACTIVADO: CIE-10 fue migrado a MediApp.\n";
return;

// ── Seguridad ─────────────────────────────────────────────────────────────
$isCLI = (php_sapi_name() === 'cli');

// Allow being required from superadmin API (trusted context)
$_cie10_trusted = !empty($GLOBALS['_cie10_trusted']) || defined('CIE10_IMPORT_TRUSTED');

if (!$isCLI && !$_cie10_trusted) {
    // Proteger acceso web con clave en query string
    $key = $_GET['key'] ?? '';
    $expectedKey = 'geriapp_cie10_' . date('Ymd');
    if ($key !== $expectedKey) {
        http_response_code(403);
        echo "Acceso denegado. Usa: ?key=geriapp_cie10_YYYYMMDD\n";
        exit(1);
    }
    header('Content-Type: text/plain; charset=utf-8');
}

// Don't re-require config if already loaded (e.g. from superadmin API)
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../conf/config.php';
}
if (!class_exists('Database')) {
    require_once __DIR__ . '/Database.php';
}

// ── Parsear argumentos CLI o simulados ────────────────────────────────────
$csvFile = null;
$mode = 'auto'; // auto | file | seed
$forceReplace = false;
$_cie10_exit = function($code = 0) use ($isCLI, $_cie10_trusted) {
    if ($isCLI) exit($code);
    if ($_cie10_trusted) return; // let output be captured
    exit($code);
};

$argList = $isCLI ? ($argv ?? []) : ($_SERVER['argv'] ?? []);
foreach ($argList as $arg) {
    if (str_starts_with($arg, '--file=')) {
        $csvFile = substr($arg, 7);
        $mode = 'file';
    }
    if ($arg === '--replace') $forceReplace = true;
    if ($arg === '--seed-only') $mode = 'seed';
    if ($arg === '--who') $mode = 'who';
    if ($arg === '--help' && $isCLI) {
            echo <<<HELP
Uso: php db/cie10_import.php [opciones]

Opciones:
  --file=RUTA     Importar desde un archivo CSV/TSV local
  --replace       Reemplazar registros existentes (REPLACE INTO)
  --seed-only     Solo insertar los ~400 códigos geriátricos frecuentes (español)
  --who           Descargar catálogo completo CIE-10 desde la OMS (inglés, ~2000 códigos)
  --help          Mostrar esta ayuda

Formato CSV esperado (con o sin encabezados):
  codigo,descripcion[,capitulo][,grupo]

Ejemplos:
  php db/cie10_import.php --file=cie10_completo.csv
  php db/cie10_import.php --file=cie10.tsv --replace
  php db/cie10_import.php --seed-only

HELP;
            exit(0);
        }
    }

echo "=== GeriApp — Importador CIE-10 ===\n\n";

$db = Database::getInstance();

// ── Verificar que la tabla existe ─────────────────────────────────────────
try {
    $db->query("SELECT 1 FROM cie10_catalogo LIMIT 1");
    $count = (int)$db->query("SELECT COUNT(*) FROM cie10_catalogo")->fetchColumn();
    echo "✓ Tabla cie10_catalogo existe. Registros actuales: {$count}\n\n";
} catch (PDOException $e) {
    echo "✗ La tabla cie10_catalogo no existe. Ejecuta primero migrate_expediente_medico.php\n";
    $_cie10_exit(1);
    return;
}

// ── Función de importación ────────────────────────────────────────────────
function importCodes(PDO $db, array $codes, bool $replace = false): array {
    $inserted = 0;
    $skipped  = 0;
    $errors   = 0;

    $verb = $replace ? 'REPLACE' : 'INSERT IGNORE';
    $stmt = $db->prepare("{$verb} INTO cie10_catalogo (codigo, descripcion, capitulo, grupo) VALUES (?, ?, ?, ?)");

    $db->beginTransaction();
    try {
        foreach ($codes as $row) {
            $codigo = trim($row['codigo'] ?? '');
            $desc   = trim($row['descripcion'] ?? '');
            if (!$codigo || !$desc) { $skipped++; continue; }

            // Normalizar código: quitar puntos y espacios extra
            $codigo = strtoupper(preg_replace('/\s+/', '', $codigo));
            // Longitud máxima
            if (strlen($codigo) > 10 || strlen($desc) > 500) { $skipped++; continue; }

            try {
                $stmt->execute([
                    $codigo,
                    $desc,
                    $row['capitulo'] ?? getCapitulo($codigo),
                    $row['grupo'] ?? null,
                ]);
                if ($stmt->rowCount() > 0) $inserted++;
                else $skipped++;
            } catch (PDOException $e) {
                $errors++;
            }
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    return compact('inserted', 'skipped', 'errors');
}

/**
 * Determina el capítulo CIE-10 a partir del código.
 */
function getCapitulo(string $codigo): ?string {
    $letter = strtoupper(substr($codigo, 0, 1));
    $num    = (int)substr($codigo, 1, 2);

    $map = [
        'I'    => ['A' => [0,9], 'B' => [0,99]],
        'II'   => ['C' => [0,97], 'D' => [0,48]],
        'III'  => ['D' => [50,89]],
        'IV'   => ['E' => [0,90]],
        'V'    => ['F' => [0,99]],
        'VI'   => ['G' => [0,99]],
        'VII'  => ['H' => [0,59]],
        'VIII' => ['H' => [60,95]],
        'IX'   => ['I' => [0,99]],
        'X'    => ['J' => [0,99]],
        'XI'   => ['K' => [0,93]],
        'XII'  => ['L' => [0,99]],
        'XIII' => ['M' => [0,99]],
        'XIV'  => ['N' => [0,99]],
        'XV'   => ['O' => [0,99]],
        'XVI'  => ['P' => [0,96]],
        'XVII' => ['Q' => [0,99]],
        'XVIII'=> ['R' => [0,99]],
        'XIX'  => ['S' => [0,99], 'T' => [0,98]],
        'XX'   => ['V' => [1,99], 'W' => [0,99], 'X' => [0,99], 'Y' => [0,98]],
        'XXI'  => ['Z' => [0,99]],
        'XXII' => ['U' => [0,99]],
    ];

    foreach ($map as $cap => $ranges) {
        if (isset($ranges[$letter])) {
            $r = $ranges[$letter];
            if ($num >= $r[0] && $num <= $r[1]) return $cap;
        }
    }
    return null;
}

// ═════════════════════════════════════════════════════════════════════════════
// MODO 1: Importar desde archivo CSV/TSV
// ═════════════════════════════════════════════════════════════════════════════
if ($mode === 'file' && $csvFile) {
    if (!file_exists($csvFile)) {
        echo "✗ Archivo no encontrado: {$csvFile}\n";
        $_cie10_exit(1);
        return;
    }

    echo "→ Importando desde: {$csvFile}\n";
    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        echo "✗ No se pudo abrir el archivo.\n";
        $_cie10_exit(1);
        return;
    }

    // Detectar delimitador
    $firstLine = fgets($handle);
    rewind($handle);
    $delimiter = (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) ? "\t" : ',';
    echo "  Delimitador detectado: " . ($delimiter === "\t" ? 'TAB' : 'COMA') . "\n";

    // Detectar si tiene encabezados
    $headers = fgetcsv($handle, 0, $delimiter);
    $headerLower = array_map('strtolower', array_map('trim', $headers));
    $hasHeaders = in_array('codigo', $headerLower) || in_array('code', $headerLower) || in_array('cod', $headerLower);

    // Mapear columnas
    $colMap = ['codigo' => 0, 'descripcion' => 1, 'capitulo' => null, 'grupo' => null];
    if ($hasHeaders) {
        foreach ($headerLower as $i => $h) {
            if (in_array($h, ['codigo', 'code', 'cod', 'código'])) $colMap['codigo'] = $i;
            if (in_array($h, ['descripcion', 'description', 'desc', 'descripción', 'nombre'])) $colMap['descripcion'] = $i;
            if (in_array($h, ['capitulo', 'chapter', 'capítulo'])) $colMap['capitulo'] = $i;
            if (in_array($h, ['grupo', 'group', 'categoria', 'categoría'])) $colMap['grupo'] = $i;
        }
        echo "  Encabezados detectados: " . implode(', ', $headers) . "\n";
    } else {
        rewind($handle); // No headers, re-read from start
        echo "  Sin encabezados. Asumiendo: columna 1=codigo, columna 2=descripcion\n";
    }

    $codes = [];
    $lineNum = $hasHeaders ? 1 : 0;
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $lineNum++;
        $codigo = $row[$colMap['codigo']] ?? '';
        $desc   = $row[$colMap['descripcion']] ?? '';
        if (!$codigo || !$desc) continue;

        $entry = ['codigo' => $codigo, 'descripcion' => $desc];
        if ($colMap['capitulo'] !== null && isset($row[$colMap['capitulo']])) {
            $entry['capitulo'] = $row[$colMap['capitulo']];
        }
        if ($colMap['grupo'] !== null && isset($row[$colMap['grupo']])) {
            $entry['grupo'] = $row[$colMap['grupo']];
        }
        $codes[] = $entry;
    }
    fclose($handle);

    echo "  Códigos leídos: " . count($codes) . "\n";

    if (!count($codes)) {
        echo "✗ No se encontraron códigos válidos en el archivo.\n";
        $_cie10_exit(1);
        return;
    }

    echo "  Importando...\n";
    $result = importCodes($db, $codes, $forceReplace);
    echo "\n✓ Importación completada:\n";
    echo "  Insertados: {$result['inserted']}\n";
    echo "  Omitidos (duplicados): {$result['skipped']}\n";
    echo "  Errores: {$result['errors']}\n";

    $total = (int)$db->query("SELECT COUNT(*) FROM cie10_catalogo")->fetchColumn();
    echo "  Total en catálogo: {$total}\n";
    $_cie10_exit(0);
    return;
}

// ═════════════════════════════════════════════════════════════════════════════
// MODO 2: Descarga automática (API pública)
// ═════════════════════════════════════════════════════════════════════════════
if ($mode === 'auto') {
    echo "→ Intentando descarga desde fuentes públicas...\n\n";

    // Fuente 1: GitHub — CIE-10 en español (repositorio público conocido)
    $sources = [
        [
            'name' => 'GitHub CIE-10 ES (CSV)',
            'url'  => 'https://raw.githubusercontent.com/HL7/UTG/master/input/sourceOfTruth/external/v3-ietf3066/v3-ietf3066.csv',
            'type' => 'csv',
        ],
    ];

    // Intentar usar la API local para obtener datos que ya existen
    $existingCount = (int)$db->query("SELECT COUNT(*) FROM cie10_catalogo")->fetchColumn();

    if ($existingCount >= 10000) {
        echo "✓ El catálogo ya contiene {$existingCount} códigos. No se requiere descarga.\n";
        echo "  Use --replace para forzar actualización, o --file= para importar otro archivo.\n";
        $_cie10_exit(0);
        return;
    }

    echo "! No se encontró una fuente de descarga automática confiable.\n\n";
    echo "Para importar el catálogo completo CIE-10, sigue estos pasos:\n\n";
    echo "  1. Descarga el catálogo CIE-10 en CSV desde una de estas fuentes:\n\n";
    echo "     a) DGIS México (recomendado para NOM):\n";
    echo "        https://www.dgis.salud.gob.mx/\n\n";
    echo "     b) OPS/OMS:\n";
    echo "        https://icd.who.int/browse10/2019/en\n\n";
    echo "     c) eCIE-Maps (Ministerio de Sanidad España):\n";
    echo "        https://eciemaps.mscbs.gob.es/\n\n";
    echo "  2. El archivo CSV debe tener al menos 2 columnas:\n";
    echo "     codigo,descripcion\n\n";
    echo "  3. Ejecuta la importación:\n";
    echo "     php db/cie10_import.php --file=ruta/al/cie10.csv\n\n";
    echo "  4. Para reemplazar registros existentes:\n";
    echo "     php db/cie10_import.php --file=cie10.csv --replace\n\n";

    // Si hay pocos registros, ofrecer el seed integrado
    if ($existingCount < 200) {
        echo "? El catálogo solo tiene {$existingCount} registros.\n";
        echo "  ¿Quieres insertar los códigos geriátricos frecuentes? Ejecuta:\n";
        echo "  php db/cie10_import.php --seed-only\n\n";
    }
    $_cie10_exit(0);
    return;
}

// ═════════════════════════════════════════════════════════════════════════════
// MODO 3: Seed — Códigos geriátricos frecuentes (~400 códigos)
// ═════════════════════════════════════════════════════════════════════════════
if ($mode === 'seed') {
    echo "→ Insertando catálogo geriátrico frecuente...\n\n";

    $codes = [
        // ── Enfermedades infecciosas (Cap. I) ─────────────────────
        ['codigo'=>'A09',  'descripcion'=>'Diarrea y gastroenteritis de presunto origen infeccioso'],
        ['codigo'=>'A15',  'descripcion'=>'Tuberculosis respiratoria, confirmada bacteriológica e histológicamente'],
        ['codigo'=>'A41',  'descripcion'=>'Otras septicemias'],
        ['codigo'=>'A419', 'descripcion'=>'Septicemia, no especificada'],
        ['codigo'=>'A49',  'descripcion'=>'Infección bacteriana, sitio no especificado'],
        ['codigo'=>'B182', 'descripcion'=>'Hepatitis C crónica'],
        ['codigo'=>'B20',  'descripcion'=>'Enfermedad por virus de la inmunodeficiencia humana (VIH)'],
        ['codigo'=>'B37',  'descripcion'=>'Candidiasis'],
        ['codigo'=>'B86',  'descripcion'=>'Escabiosis'],

        // ── Neoplasias (Cap. II) ──────────────────────────────────
        ['codigo'=>'C18',  'descripcion'=>'Tumor maligno del colon'],
        ['codigo'=>'C34',  'descripcion'=>'Tumor maligno de los bronquios y del pulmón'],
        ['codigo'=>'C50',  'descripcion'=>'Tumor maligno de la mama'],
        ['codigo'=>'C61',  'descripcion'=>'Tumor maligno de la próstata'],
        ['codigo'=>'C64',  'descripcion'=>'Tumor maligno del riñón, excepto pelvis renal'],
        ['codigo'=>'C67',  'descripcion'=>'Tumor maligno de la vejiga urinaria'],
        ['codigo'=>'C71',  'descripcion'=>'Tumor maligno del encéfalo'],
        ['codigo'=>'C73',  'descripcion'=>'Tumor maligno de la glándula tiroides'],
        ['codigo'=>'C85',  'descripcion'=>'Linfoma no Hodgkin de otro tipo y tipo no especificado'],
        ['codigo'=>'C90',  'descripcion'=>'Mieloma múltiple y neoplasias malignas de células plasmáticas'],
        ['codigo'=>'D25',  'descripcion'=>'Leiomioma del útero'],
        ['codigo'=>'D46',  'descripcion'=>'Síndromes mielodisplásicos'],

        // ── Sangre e inmunidad (Cap. III) ─────────────────────────
        ['codigo'=>'D50',  'descripcion'=>'Anemia por deficiencia de hierro'],
        ['codigo'=>'D509', 'descripcion'=>'Anemia por deficiencia de hierro, no especificada'],
        ['codigo'=>'D64',  'descripcion'=>'Otras anemias'],
        ['codigo'=>'D649', 'descripcion'=>'Anemia, no especificada'],
        ['codigo'=>'D69',  'descripcion'=>'Púrpura y otras afecciones hemorrágicas'],

        // ── Endocrinas, nutricionales y metabólicas (Cap. IV) ─────
        ['codigo'=>'E039', 'descripcion'=>'Hipotiroidismo, no especificado'],
        ['codigo'=>'E05',  'descripcion'=>'Tirotoxicosis (hipertiroidismo)'],
        ['codigo'=>'E10',  'descripcion'=>'Diabetes mellitus tipo 1'],
        ['codigo'=>'E11',  'descripcion'=>'Diabetes mellitus tipo 2'],
        ['codigo'=>'E110', 'descripcion'=>'Diabetes mellitus tipo 2, con coma'],
        ['codigo'=>'E112', 'descripcion'=>'Diabetes mellitus tipo 2, con complicaciones renales'],
        ['codigo'=>'E113', 'descripcion'=>'Diabetes mellitus tipo 2, con complicaciones oftálmicas'],
        ['codigo'=>'E114', 'descripcion'=>'Diabetes mellitus tipo 2, con complicaciones neurológicas'],
        ['codigo'=>'E115', 'descripcion'=>'Diabetes mellitus tipo 2, con complicaciones circulatorias periféricas'],
        ['codigo'=>'E116', 'descripcion'=>'Diabetes mellitus tipo 2, con otras complicaciones especificadas'],
        ['codigo'=>'E119', 'descripcion'=>'Diabetes mellitus tipo 2, sin complicaciones'],
        ['codigo'=>'E13',  'descripcion'=>'Otros tipos especificados de diabetes mellitus'],
        ['codigo'=>'E14',  'descripcion'=>'Diabetes mellitus, no especificada'],
        ['codigo'=>'E440', 'descripcion'=>'Desnutrición proteicocalórica moderada'],
        ['codigo'=>'E441', 'descripcion'=>'Desnutrición proteicocalórica leve'],
        ['codigo'=>'E46',  'descripcion'=>'Desnutrición proteicocalórica, no especificada'],
        ['codigo'=>'E55',  'descripcion'=>'Deficiencia de vitamina D'],
        ['codigo'=>'E559', 'descripcion'=>'Deficiencia de vitamina D, no especificada'],
        ['codigo'=>'E61',  'descripcion'=>'Deficiencia de otros elementos nutritivos'],
        ['codigo'=>'E66',  'descripcion'=>'Obesidad'],
        ['codigo'=>'E669', 'descripcion'=>'Obesidad, no especificada'],
        ['codigo'=>'E780', 'descripcion'=>'Hipercolesterolemia pura'],
        ['codigo'=>'E785', 'descripcion'=>'Hiperlipidemia no especificada'],
        ['codigo'=>'E789', 'descripcion'=>'Trastorno del metabolismo de las lipoproteínas, no especificado'],
        ['codigo'=>'E83',  'descripcion'=>'Trastornos del metabolismo de los minerales'],
        ['codigo'=>'E86',  'descripcion'=>'Depleción del volumen (deshidratación)'],
        ['codigo'=>'E87',  'descripcion'=>'Otros trastornos de los líquidos, de los electrólitos y del equilibrio ácido-básico'],

        // ── Trastornos mentales (Cap. V) ──────────────────────────
        ['codigo'=>'F00',  'descripcion'=>'Demencia en la enfermedad de Alzheimer'],
        ['codigo'=>'F01',  'descripcion'=>'Demencia vascular'],
        ['codigo'=>'F019', 'descripcion'=>'Demencia vascular, no especificada'],
        ['codigo'=>'F02',  'descripcion'=>'Demencia en otras enfermedades clasificadas en otra parte'],
        ['codigo'=>'F03',  'descripcion'=>'Demencia, no especificada'],
        ['codigo'=>'F05',  'descripcion'=>'Delirium no inducido por alcohol ni por otras sustancias psicoactivas'],
        ['codigo'=>'F051', 'descripcion'=>'Delirium superpuesto a demencia'],
        ['codigo'=>'F059', 'descripcion'=>'Delirium, no especificado'],
        ['codigo'=>'F10',  'descripcion'=>'Trastornos mentales y del comportamiento debidos al uso de alcohol'],
        ['codigo'=>'F20',  'descripcion'=>'Esquizofrenia'],
        ['codigo'=>'F25',  'descripcion'=>'Trastornos esquizoafectivos'],
        ['codigo'=>'F31',  'descripcion'=>'Trastorno afectivo bipolar'],
        ['codigo'=>'F32',  'descripcion'=>'Episodio depresivo'],
        ['codigo'=>'F329', 'descripcion'=>'Episodio depresivo, no especificado'],
        ['codigo'=>'F33',  'descripcion'=>'Trastorno depresivo recurrente'],
        ['codigo'=>'F41',  'descripcion'=>'Otros trastornos de ansiedad'],
        ['codigo'=>'F410', 'descripcion'=>'Trastorno de pánico'],
        ['codigo'=>'F411', 'descripcion'=>'Trastorno de ansiedad generalizada'],
        ['codigo'=>'F419', 'descripcion'=>'Trastorno de ansiedad, no especificado'],
        ['codigo'=>'F43',  'descripcion'=>'Reacciones a estrés grave y trastornos de adaptación'],
        ['codigo'=>'F51',  'descripcion'=>'Trastornos no orgánicos del sueño'],

        // ── Sistema nervioso (Cap. VI) ────────────────────────────
        ['codigo'=>'G20',  'descripcion'=>'Enfermedad de Parkinson'],
        ['codigo'=>'G25',  'descripcion'=>'Otros trastornos extrapiramidales y del movimiento'],
        ['codigo'=>'G258', 'descripcion'=>'Otros trastornos extrapiramidales y del movimiento especificados'],
        ['codigo'=>'G30',  'descripcion'=>'Enfermedad de Alzheimer'],
        ['codigo'=>'G300', 'descripcion'=>'Enfermedad de Alzheimer de comienzo temprano'],
        ['codigo'=>'G301', 'descripcion'=>'Enfermedad de Alzheimer de comienzo tardío'],
        ['codigo'=>'G309', 'descripcion'=>'Enfermedad de Alzheimer, no especificada'],
        ['codigo'=>'G31',  'descripcion'=>'Otras enfermedades degenerativas del sistema nervioso'],
        ['codigo'=>'G318', 'descripcion'=>'Otras enfermedades degenerativas del sistema nervioso especificadas'],
        ['codigo'=>'G35',  'descripcion'=>'Esclerosis múltiple'],
        ['codigo'=>'G40',  'descripcion'=>'Epilepsia'],
        ['codigo'=>'G43',  'descripcion'=>'Migraña'],
        ['codigo'=>'G45',  'descripcion'=>'Ataques de isquemia cerebral transitoria y síndromes afines'],
        ['codigo'=>'G47',  'descripcion'=>'Trastornos del sueño'],
        ['codigo'=>'G470', 'descripcion'=>'Trastornos del inicio y del mantenimiento del sueño (insomnio)'],
        ['codigo'=>'G473', 'descripcion'=>'Apnea del sueño'],
        ['codigo'=>'G62',  'descripcion'=>'Otras polineuropatías'],
        ['codigo'=>'G629', 'descripcion'=>'Polineuropatía, no especificada'],
        ['codigo'=>'G81',  'descripcion'=>'Hemiplejía'],
        ['codigo'=>'G82',  'descripcion'=>'Paraplejía y tetraplejía'],

        // ── Ojo y anexos (Cap. VII) ───────────────────────────────
        ['codigo'=>'H25',  'descripcion'=>'Catarata senil'],
        ['codigo'=>'H26',  'descripcion'=>'Otras cataratas'],
        ['codigo'=>'H35',  'descripcion'=>'Otros trastornos de la retina'],
        ['codigo'=>'H353', 'descripcion'=>'Degeneración de la mácula y del polo posterior'],
        ['codigo'=>'H40',  'descripcion'=>'Glaucoma'],
        ['codigo'=>'H54',  'descripcion'=>'Ceguera y disminución de la agudeza visual'],

        // ── Oído (Cap. VIII) ──────────────────────────────────────
        ['codigo'=>'H65',  'descripcion'=>'Otitis media no supurada'],
        ['codigo'=>'H81',  'descripcion'=>'Trastornos de la función vestibular'],
        ['codigo'=>'H810', 'descripcion'=>'Enfermedad de Ménière'],
        ['codigo'=>'H811', 'descripcion'=>'Vértigo paroxístico benigno'],
        ['codigo'=>'H90',  'descripcion'=>'Hipoacusia conductiva y neurosensorial'],
        ['codigo'=>'H91',  'descripcion'=>'Otras hipoacusias'],
        ['codigo'=>'H919', 'descripcion'=>'Hipoacusia, no especificada'],

        // ── Sistema circulatorio (Cap. IX) ────────────────────────
        ['codigo'=>'I10',  'descripcion'=>'Hipertensión esencial (primaria)'],
        ['codigo'=>'I11',  'descripcion'=>'Enfermedad cardíaca hipertensiva'],
        ['codigo'=>'I13',  'descripcion'=>'Enfermedad cardíaca y renal hipertensiva'],
        ['codigo'=>'I20',  'descripcion'=>'Angina de pecho'],
        ['codigo'=>'I21',  'descripcion'=>'Infarto agudo de miocardio'],
        ['codigo'=>'I25',  'descripcion'=>'Enfermedad isquémica crónica del corazón'],
        ['codigo'=>'I259', 'descripcion'=>'Enfermedad isquémica crónica del corazón, no especificada'],
        ['codigo'=>'I42',  'descripcion'=>'Miocardiopatía'],
        ['codigo'=>'I48',  'descripcion'=>'Fibrilación y aleteo auricular'],
        ['codigo'=>'I480', 'descripcion'=>'Fibrilación auricular paroxística'],
        ['codigo'=>'I481', 'descripcion'=>'Fibrilación auricular persistente'],
        ['codigo'=>'I489', 'descripcion'=>'Fibrilación auricular, no especificada'],
        ['codigo'=>'I50',  'descripcion'=>'Insuficiencia cardíaca'],
        ['codigo'=>'I500', 'descripcion'=>'Insuficiencia cardíaca congestiva'],
        ['codigo'=>'I509', 'descripcion'=>'Insuficiencia cardíaca, no especificada'],
        ['codigo'=>'I61',  'descripcion'=>'Hemorragia intraencefálica'],
        ['codigo'=>'I63',  'descripcion'=>'Infarto cerebral'],
        ['codigo'=>'I64',  'descripcion'=>'Accidente vascular encefálico agudo, no especificado como hemorrágico o isquémico'],
        ['codigo'=>'I67',  'descripcion'=>'Otras enfermedades cerebrovasculares'],
        ['codigo'=>'I69',  'descripcion'=>'Secuelas de enfermedad cerebrovascular'],
        ['codigo'=>'I70',  'descripcion'=>'Aterosclerosis'],
        ['codigo'=>'I73',  'descripcion'=>'Otras enfermedades vasculares periféricas'],
        ['codigo'=>'I80',  'descripcion'=>'Flebitis y tromboflebitis'],
        ['codigo'=>'I83',  'descripcion'=>'Venas varicosas de los miembros inferiores'],
        ['codigo'=>'I87',  'descripcion'=>'Otros trastornos de las venas'],

        // ── Sistema respiratorio (Cap. X) ─────────────────────────
        ['codigo'=>'J06',  'descripcion'=>'Infecciones agudas de las vías respiratorias superiores'],
        ['codigo'=>'J15',  'descripcion'=>'Neumonía bacteriana, no clasificada en otra parte'],
        ['codigo'=>'J18',  'descripcion'=>'Neumonía, organismo no especificado'],
        ['codigo'=>'J189', 'descripcion'=>'Neumonía, no especificada'],
        ['codigo'=>'J20',  'descripcion'=>'Bronquitis aguda'],
        ['codigo'=>'J40',  'descripcion'=>'Bronquitis, no especificada como aguda o crónica'],
        ['codigo'=>'J42',  'descripcion'=>'Bronquitis crónica no especificada'],
        ['codigo'=>'J43',  'descripcion'=>'Enfisema'],
        ['codigo'=>'J44',  'descripcion'=>'Otras enfermedades pulmonares obstructivas crónicas'],
        ['codigo'=>'J449', 'descripcion'=>'Enfermedad pulmonar obstructiva crónica, no especificada'],
        ['codigo'=>'J45',  'descripcion'=>'Asma'],
        ['codigo'=>'J69',  'descripcion'=>'Neumonitis debida a sólidos y líquidos (aspiración)'],
        ['codigo'=>'J690', 'descripcion'=>'Neumonía por aspiración de alimento y vómito'],
        ['codigo'=>'J84',  'descripcion'=>'Otras enfermedades pulmonares intersticiales'],
        ['codigo'=>'J96',  'descripcion'=>'Insuficiencia respiratoria, no clasificada en otra parte'],

        // ── Sistema digestivo (Cap. XI) ───────────────────────────
        ['codigo'=>'K21',  'descripcion'=>'Enfermedad del reflujo gastroesofágico'],
        ['codigo'=>'K25',  'descripcion'=>'Úlcera gástrica'],
        ['codigo'=>'K26',  'descripcion'=>'Úlcera duodenal'],
        ['codigo'=>'K29',  'descripcion'=>'Gastritis y duodenitis'],
        ['codigo'=>'K40',  'descripcion'=>'Hernia inguinal'],
        ['codigo'=>'K56',  'descripcion'=>'Íleo paralítico y obstrucción intestinal sin hernia'],
        ['codigo'=>'K57',  'descripcion'=>'Enfermedad diverticular del intestino'],
        ['codigo'=>'K590', 'descripcion'=>'Estreñimiento'],
        ['codigo'=>'K70',  'descripcion'=>'Enfermedad alcohólica del hígado'],
        ['codigo'=>'K74',  'descripcion'=>'Fibrosis y cirrosis del hígado'],
        ['codigo'=>'K80',  'descripcion'=>'Colelitiasis'],
        ['codigo'=>'K85',  'descripcion'=>'Pancreatitis aguda'],
        ['codigo'=>'K922', 'descripcion'=>'Hemorragia gastrointestinal, no especificada'],

        // ── Piel y tejido subcutáneo (Cap. XII) ───────────────────
        ['codigo'=>'L02',  'descripcion'=>'Absceso cutáneo, furúnculo y carbunco'],
        ['codigo'=>'L03',  'descripcion'=>'Celulitis'],
        ['codigo'=>'L08',  'descripcion'=>'Otras infecciones locales de la piel y del tejido subcutáneo'],
        ['codigo'=>'L20',  'descripcion'=>'Dermatitis atópica'],
        ['codigo'=>'L30',  'descripcion'=>'Otras dermatitis'],
        ['codigo'=>'L40',  'descripcion'=>'Psoriasis'],
        ['codigo'=>'L89',  'descripcion'=>'Úlcera por presión (decúbito)'],
        ['codigo'=>'L890', 'descripcion'=>'Úlcera por presión, estadio I'],
        ['codigo'=>'L891', 'descripcion'=>'Úlcera por presión, estadio II'],
        ['codigo'=>'L892', 'descripcion'=>'Úlcera por presión, estadio III'],
        ['codigo'=>'L893', 'descripcion'=>'Úlcera por presión, estadio IV'],
        ['codigo'=>'L97',  'descripcion'=>'Úlcera de miembro inferior, no clasificada en otra parte'],

        // ── Sistema musculoesquelético (Cap. XIII) ────────────────
        ['codigo'=>'M05',  'descripcion'=>'Artritis reumatoide seropositiva'],
        ['codigo'=>'M06',  'descripcion'=>'Otras artritis reumatoides'],
        ['codigo'=>'M10',  'descripcion'=>'Gota'],
        ['codigo'=>'M15',  'descripcion'=>'Poliartrosis'],
        ['codigo'=>'M16',  'descripcion'=>'Coxartrosis (artrosis de la cadera)'],
        ['codigo'=>'M17',  'descripcion'=>'Gonartrosis (artrosis de la rodilla)'],
        ['codigo'=>'M19',  'descripcion'=>'Otras artrosis'],
        ['codigo'=>'M199', 'descripcion'=>'Artrosis, no especificada'],
        ['codigo'=>'M41',  'descripcion'=>'Escoliosis'],
        ['codigo'=>'M47',  'descripcion'=>'Espondilosis'],
        ['codigo'=>'M479', 'descripcion'=>'Espondilosis, no especificada'],
        ['codigo'=>'M51',  'descripcion'=>'Otros trastornos de los discos intervertebrales'],
        ['codigo'=>'M54',  'descripcion'=>'Dorsalgia'],
        ['codigo'=>'M545', 'descripcion'=>'Lumbago no especificado'],
        ['codigo'=>'M62',  'descripcion'=>'Otros trastornos de los músculos'],
        ['codigo'=>'M625', 'descripcion'=>'Desgaste y atrofia muscular, no clasificados en otra parte'],
        ['codigo'=>'M79',  'descripcion'=>'Otros trastornos de los tejidos blandos, no clasificados en otra parte'],
        ['codigo'=>'M80',  'descripcion'=>'Osteoporosis con fractura patológica'],
        ['codigo'=>'M81',  'descripcion'=>'Osteoporosis sin fractura patológica'],
        ['codigo'=>'M819', 'descripcion'=>'Osteoporosis, no especificada'],
        ['codigo'=>'M84',  'descripcion'=>'Trastornos de la continuidad del hueso'],

        // ── Sistema genitourinario (Cap. XIV) ─────────────────────
        ['codigo'=>'N10',  'descripcion'=>'Nefritis tubulointersticial aguda (pielonefritis aguda)'],
        ['codigo'=>'N17',  'descripcion'=>'Insuficiencia renal aguda'],
        ['codigo'=>'N18',  'descripcion'=>'Insuficiencia renal crónica'],
        ['codigo'=>'N185', 'descripcion'=>'Enfermedad renal crónica, estadio 5'],
        ['codigo'=>'N189', 'descripcion'=>'Insuficiencia renal crónica, no especificada'],
        ['codigo'=>'N19',  'descripcion'=>'Insuficiencia renal, no especificada'],
        ['codigo'=>'N20',  'descripcion'=>'Cálculo del riñón y del uréter'],
        ['codigo'=>'N30',  'descripcion'=>'Cistitis'],
        ['codigo'=>'N39',  'descripcion'=>'Otros trastornos del sistema urinario'],
        ['codigo'=>'N390', 'descripcion'=>'Infección de vías urinarias, sitio no especificado'],
        ['codigo'=>'N40',  'descripcion'=>'Hiperplasia de la próstata'],
        ['codigo'=>'N81',  'descripcion'=>'Prolapso genital femenino'],

        // ── Síntomas y signos (Cap. XVIII) ────────────────────────
        ['codigo'=>'R00',  'descripcion'=>'Anormalidades del latido cardíaco'],
        ['codigo'=>'R02',  'descripcion'=>'Gangrena, no clasificada en otra parte'],
        ['codigo'=>'R04',  'descripcion'=>'Hemorragia de las vías respiratorias'],
        ['codigo'=>'R05',  'descripcion'=>'Tos'],
        ['codigo'=>'R06',  'descripcion'=>'Anormalidades de la respiración'],
        ['codigo'=>'R10',  'descripcion'=>'Dolor abdominal y pélvico'],
        ['codigo'=>'R11',  'descripcion'=>'Náusea y vómito'],
        ['codigo'=>'R13',  'descripcion'=>'Disfagia'],
        ['codigo'=>'R15',  'descripcion'=>'Incontinencia fecal'],
        ['codigo'=>'R26',  'descripcion'=>'Anormalidades de la marcha y de la movilidad'],
        ['codigo'=>'R260', 'descripcion'=>'Marcha atáxica'],
        ['codigo'=>'R268', 'descripcion'=>'Otras anormalidades de la marcha y de la movilidad'],
        ['codigo'=>'R29',  'descripcion'=>'Otros síntomas y signos que involucran los sistemas nervioso y musculoesquelético'],
        ['codigo'=>'R296', 'descripcion'=>'Tendencia a caer, no clasificada en otra parte'],
        ['codigo'=>'R32',  'descripcion'=>'Incontinencia urinaria, no especificada'],
        ['codigo'=>'R33',  'descripcion'=>'Retención de orina'],
        ['codigo'=>'R40',  'descripcion'=>'Somnolencia, estupor y coma'],
        ['codigo'=>'R41',  'descripcion'=>'Otros síntomas y signos que involucran la función cognoscitiva y la conciencia'],
        ['codigo'=>'R410', 'descripcion'=>'Desorientación, no especificada'],
        ['codigo'=>'R413', 'descripcion'=>'Otra amnesia'],
        ['codigo'=>'R418', 'descripcion'=>'Otros síntomas y signos con afección de la función cognoscitiva'],
        ['codigo'=>'R42',  'descripcion'=>'Mareo y desvanecimiento'],
        ['codigo'=>'R44',  'descripcion'=>'Otros síntomas y signos que involucran las sensaciones generales y las percepciones'],
        ['codigo'=>'R50',  'descripcion'=>'Fiebre de otro origen y de origen desconocido'],
        ['codigo'=>'R52',  'descripcion'=>'Dolor, no clasificado en otra parte'],
        ['codigo'=>'R54',  'descripcion'=>'Senilidad (envejecimiento/fragilidad por la edad)'],
        ['codigo'=>'R55',  'descripcion'=>'Síncope y colapso'],
        ['codigo'=>'R56',  'descripcion'=>'Convulsiones, no clasificadas en otra parte'],
        ['codigo'=>'R60',  'descripcion'=>'Edema, no clasificado en otra parte'],
        ['codigo'=>'R63',  'descripcion'=>'Síntomas y signos concernientes a la alimentación y a la ingestión de líquidos'],
        ['codigo'=>'R630', 'descripcion'=>'Anorexia'],
        ['codigo'=>'R634', 'descripcion'=>'Pérdida anormal de peso'],
        ['codigo'=>'R64',  'descripcion'=>'Caquexia'],
        ['codigo'=>'R68',  'descripcion'=>'Otros síntomas y signos generales'],
        ['codigo'=>'R69',  'descripcion'=>'Causas de morbilidad desconocidas y no especificadas'],

        // ── Traumatismos / Caídas (Cap. XIX y XX) ─────────────────
        ['codigo'=>'S00',  'descripcion'=>'Traumatismo superficial de la cabeza'],
        ['codigo'=>'S32',  'descripcion'=>'Fractura de la columna lumbar y de la pelvis'],
        ['codigo'=>'S42',  'descripcion'=>'Fractura del hombro y del brazo'],
        ['codigo'=>'S52',  'descripcion'=>'Fractura del antebrazo'],
        ['codigo'=>'S62',  'descripcion'=>'Fractura a nivel de la muñeca y de la mano'],
        ['codigo'=>'S72',  'descripcion'=>'Fractura del fémur'],
        ['codigo'=>'S720', 'descripcion'=>'Fractura del cuello del fémur (fractura de cadera)'],
        ['codigo'=>'S82',  'descripcion'=>'Fractura de la pierna, incluso el tobillo'],
        ['codigo'=>'T14',  'descripcion'=>'Traumatismo de regiones del cuerpo no especificadas'],
        ['codigo'=>'T78',  'descripcion'=>'Efectos adversos, no clasificados en otra parte'],
        ['codigo'=>'T81',  'descripcion'=>'Complicaciones de procedimientos, no clasificadas en otra parte'],
        ['codigo'=>'T88',  'descripcion'=>'Otras complicaciones de la atención médica y quirúrgica'],
        ['codigo'=>'W01',  'descripcion'=>'Caída en el mismo nivel por resbalón, tropezón o traspié'],
        ['codigo'=>'W06',  'descripcion'=>'Caída que implica cama'],
        ['codigo'=>'W10',  'descripcion'=>'Caída en y desde escalones y escaleras'],
        ['codigo'=>'W18',  'descripcion'=>'Otras caídas en el mismo nivel'],
        ['codigo'=>'W19',  'descripcion'=>'Caída no especificada'],

        // ── Factores que influyen en el estado de salud (Cap. XXI) ─
        ['codigo'=>'Z22',  'descripcion'=>'Portador de enfermedad infecciosa'],
        ['codigo'=>'Z46',  'descripcion'=>'Adaptación y ajuste de otros dispositivos'],
        ['codigo'=>'Z49',  'descripcion'=>'Cuidados que implican diálisis'],
        ['codigo'=>'Z50',  'descripcion'=>'Atención que implica el uso de procedimientos de rehabilitación'],
        ['codigo'=>'Z73',  'descripcion'=>'Problemas relacionados con dificultades para la vida'],
        ['codigo'=>'Z74',  'descripcion'=>'Problemas relacionados con la necesidad de asistencia para el cuidado personal'],
        ['codigo'=>'Z75',  'descripcion'=>'Problemas relacionados con instalaciones médicas y otra atención de salud'],
        ['codigo'=>'Z87',  'descripcion'=>'Historia personal de otras enfermedades y afecciones'],
        ['codigo'=>'Z91',  'descripcion'=>'Historia personal de factores de riesgo, no clasificados en otra parte'],
        ['codigo'=>'Z93',  'descripcion'=>'Aberturas artificiales (ostomías)'],
        ['codigo'=>'Z96',  'descripcion'=>'Presencia de otros implantes funcionales'],
        ['codigo'=>'Z99',  'descripcion'=>'Dependencia de máquinas y dispositivos capacitantes, no clasificados en otra parte'],

        // ── Incontinencia urinaria (detalle) ──────────────────────
        ['codigo'=>'N393', 'descripcion'=>'Incontinencia urinaria de esfuerzo'],
        ['codigo'=>'N394', 'descripcion'=>'Otras incontinencias urinarias especificadas'],

        // ── Fragilidad / Sarcopenia ───────────────────────────────
        ['codigo'=>'M6250','descripcion'=>'Atrofia muscular, no especificada'],
        ['codigo'=>'R540', 'descripcion'=>'Fragilidad del anciano'],
    ];

    // Marcar como favoritos los geriátricos frecuentes
    $geriFrequent = [
        'E039','E11','E119','E46','E66','E780','E86',
        'F00','F01','F03','F05','F32','F329','F41','F419',
        'G20','G30','G309','G470',
        'H25','H353','H919',
        'I10','I25','I48','I489','I50','I500','I509','I63','I64','I69',
        'J18','J189','J44','J449','J690',
        'K21','K590',
        'L89','L890','L891','L892','L893','L97',
        'M16','M17','M199','M545','M625','M80','M81','M819',
        'N18','N189','N390','N40',
        'R13','R15','R260','R268','R296','R32','R42','R52','R54','R55','R630','R634','R64',
        'S720','W19',
        'Z74','Z75',
    ];

    echo "  Códigos a insertar: " . count($codes) . "\n";

    $result = importCodes($db, $codes, $forceReplace);
    echo "  Insertados: {$result['inserted']}\n";
    echo "  Omitidos (ya existentes): {$result['skipped']}\n";
    echo "  Errores: {$result['errors']}\n\n";

    // Marcar favoritos
    if (!empty($geriFrequent)) {
        $placeholders = implode(',', array_fill(0, count($geriFrequent), '?'));
        $stmt = $db->prepare("UPDATE cie10_catalogo SET favorito = 1 WHERE codigo IN ({$placeholders})");
        $stmt->execute($geriFrequent);
        echo "✓ Marcados como favoritos: {$stmt->rowCount()} códigos geriátricos frecuentes\n";
    }

    $total = (int)$db->query("SELECT COUNT(*) FROM cie10_catalogo")->fetchColumn();
    echo "\n✓ Total en catálogo: {$total}\n";
    $_cie10_exit(0);
    return;
}

echo "No se especificó un modo válido. Usa --help para ver opciones.\n";
