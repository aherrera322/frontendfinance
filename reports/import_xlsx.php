<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../auth/config.php';

// Configuration
$defaultXlsxPath = 'C:\\Users\\alexh\\OneDrive - Zimple Rentals Inc\\2024\\2024-Reports\\ZB Reservations by App Date.xlsx';
$tableName = 'zb_reservations';
$rowHashColumn = 'row_hash';
$enableTypeInference = true;

function respond($status, $data) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function sanitizeColumnName($name) {
    $lower = strtolower(trim($name));
    $underscored = preg_replace('/[^a-z0-9]+/i', '_', $lower);
    $underscored = trim($underscored, '_');
    if ($underscored === '') { $underscored = 'col'; }
    return substr($underscored, 0, 60);
}

function inferType($samples) {
    $hasFloat = false;
    $hasInt = true;
    $hasDateTime = true;
    $checked = 0;
    foreach ($samples as $value) {
        $checked++;
        $value = trim((string)$value);
        if ($value === '') { continue; }
        if (strtotime($value) === false) { $hasDateTime = false; }
        if (!is_numeric($value)) { $hasInt = false; $hasFloat = false; }
        else {
            if ((string)(int)$value !== (string)$value) { $hasFloat = true; $hasInt = false; }
        }
        if ($checked > 50) { break; }
    }
    if ($hasDateTime && $checked > 0) return 'DATETIME NULL';
    if ($hasInt) return 'INT NULL';
    if ($hasFloat) return 'DECIMAL(18,6) NULL';
    return 'TEXT NULL';
}

function buildCreateTableSql(PDO $pdo, $tableName, $columns, $enableTypeInference, $rowHashColumn) {
    $parts = [];
    foreach ($columns as $meta) {
        $type = 'TEXT NULL';
        if ($enableTypeInference && isset($meta['samples'])) {
            $type = inferType($meta['samples']);
        }
        $parts[] = "`{$meta['name']}` {$type}";
    }
    $colsSql = implode(",\n\t", $parts);
    $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        {$colsSql},
        `{$rowHashColumn}` CHAR(40) NOT NULL UNIQUE,
        `source_file` TEXT NULL,
        `imported_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
}

function ensureColumnsExist(PDO $pdo, $tableName, $columns) {
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$tableName}`");
    $existing = [];
    foreach ($stmt->fetchAll() as $col) { $existing[$col['Field']] = true; }
    foreach ($columns as $meta) {
        if (!isset($existing[$meta['name']])) {
            $pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `{$meta['name']}` TEXT NULL");
        }
    }
}

function colLettersToIndex($letters) {
    $letters = strtoupper($letters);
    $len = strlen($letters);
    $index = 0;
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $index - 1; // zero-based
}

function xmlToSimple($xml) {
    libxml_use_internal_errors(true);
    $sxe = simplexml_load_string($xml);
    if ($sxe === false) { return null; }
    return $sxe;
}

function loadSharedStrings(ZipArchive $zip) {
    $idx = $zip->locateName('xl/sharedStrings.xml');
    if ($idx === false) { return []; }
    $xml = $zip->getFromIndex($idx);
    $sxe = xmlToSimple($xml);
    if (!$sxe) { return []; }
    $strings = [];
    foreach ($sxe->si as $si) {
        // Concatenate t nodes (may be rich text)
        $text = '';
        if (isset($si->t)) {
            $text = (string)$si->t;
        } elseif (isset($si->r)) {
            foreach ($si->r as $r) {
                $text .= (string)$r->t;
            }
        }
        $strings[] = $text;
    }
    return $strings;
}

function readSheetRows(ZipArchive $zip, $sheetIndex = 1, $sharedStrings = []) {
    $path = 'xl/worksheets/sheet' . (int)$sheetIndex . '.xml';
    $idx = $zip->locateName($path);
    if ($idx === false) { return [[], []]; }
    $xml = $zip->getFromIndex($idx);
    $sxe = xmlToSimple($xml);
    if (!$sxe) { return [[], []]; }

    $rows = [];
    foreach ($sxe->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $r = (string)$c['r'];
            // Extract column letters
            $letters = preg_replace('/\d+/', '', $r);
            $colIndex = colLettersToIndex($letters);

            $type = (string)$c['t'];
            $v = isset($c->v) ? (string)$c->v : '';
            $value = '';
            if ($type === 's') {
                // shared string
                $si = (int)$v;
                $value = $sharedStrings[$si] ?? '';
            } elseif ($type === 'b') {
                $value = ($v === '1') ? 'TRUE' : 'FALSE';
            } else {
                $value = $v; // numeric or inline string
            }
            $cells[$colIndex] = $value;
        }
        if (!empty($cells)) {
            // Normalize to a zero-based continuous array
            $maxIdx = max(array_keys($cells));
            $rowValues = array_fill(0, $maxIdx + 1, '');
            foreach ($cells as $i => $val) { $rowValues[$i] = $val; }
            $rows[] = $rowValues;
        }
    }
    return $rows;
}

try {
    // Determine source: uploaded file or local path
    $uploadedTmp = null;
    if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $uploadsDir = __DIR__ . '/uploads';
        if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0775, true); }
        $basename = basename($_FILES['file']['name']);
        $target = $uploadsDir . '/' . uniqid('xlsx_', true) . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $basename);
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            respond(400, ['success' => false, 'message' => 'Failed to move uploaded file']);
        }
        $xlsxPath = $target;
        $uploadedTmp = $target; // mark for cleanup
    } else {
        $xlsxPath = isset($_GET['file']) && $_GET['file'] !== '' ? $_GET['file'] : $defaultXlsxPath;
    }
    $sheetIndex = isset($_GET['sheet_index']) ? max(1, (int)$_GET['sheet_index']) : 1;
    $dryRun = isset($_GET['dry_run']) && ($_GET['dry_run'] === '1' || strtolower($_GET['dry_run']) === 'true');
    $previewRows = isset($_GET['preview_rows']) ? max(0, (int)$_GET['preview_rows']) : 50;

    if (!file_exists($xlsxPath)) {
        respond(400, [
            'success' => false,
            'message' => 'XLSX file not found. Pass ?file= with a valid path.',
            'expected_path' => $xlsxPath
        ]);
    }

    $pdo = getDBConnection();
    if (!$pdo) { respond(500, ['success' => false, 'message' => 'Database connection failed']); }

    $zip = new ZipArchive();
    if ($zip->open($xlsxPath) !== true) {
        respond(400, ['success' => false, 'message' => 'Unable to open XLSX (zip)']);
    }

    $shared = loadSharedStrings($zip);
    $rows = readSheetRows($zip, $sheetIndex, $shared);
    $zip->close();

    if (count($rows) === 0) {
        respond(400, ['success' => false, 'message' => 'No rows found in the specified sheet']);
    }

    // First non-empty row is header
    $headers = [];
    foreach ($rows as $row) { if (count(array_filter($row, fn($v)=>$v!=='')) > 0) { $headers = $row; break; } }
    if (count($headers) === 0) {
        respond(400, ['success' => false, 'message' => 'Header row not found']);
    }

    // Sanitize and uniquify column names
    $columns = [];
    $used = [];
    foreach ($headers as $i => $header) {
        $name = sanitizeColumnName($header);
        if (isset($used[$name])) { $n=2; while (isset($used[$name.'_'.$n])) { $n++; } $name = $name.'_'.$n; }
        $used[$name] = true;
        $columns[$i] = ['name' => $name, 'samples' => []];
    }

    // Collect samples
    $sampled = 0; $started = false;
    foreach ($rows as $row) {
        if (!$started) { $started = true; continue; } // skip header
        if ($sampled >= $previewRows) { break; }
        foreach ($columns as $idx => &$meta) { $meta['samples'][] = $row[$idx] ?? ''; }
        $sampled++;
    }

    buildCreateTableSql($pdo, $tableName, $columns, $enableTypeInference, $rowHashColumn);
    ensureColumnsExist($pdo, $tableName, $columns);

    // Prepare insert
    $columnNames = array_map(function($m){ return $m['name']; }, $columns);
    $insertColsSql = '`' . implode('`, `', $columnNames) . '`, `'.$rowHashColumn.'`, `source_file`';
    $placeholders = rtrim(str_repeat('?,', count($columnNames)), ',') . ', ?, ?';
    $stmt = $pdo->prepare("INSERT IGNORE INTO `{$tableName}` ({$insertColsSql}) VALUES ({$placeholders})");

    $inserted = 0; $skipped = 0; $errors = 0; $total = 0; $started = false;
    foreach ($rows as $row) {
        if (!$started) { $started = true; continue; }
        $total++;
        // Normalize row length
        $row = array_pad($row, count($columns), '');
        $values = [];
        foreach ($columns as $idx => $meta) { $values[] = $row[$idx]; }
        // Row hash
        $normalized = array_map(function($v){ return trim(strtolower((string)$v)); }, $values);
        $rowHash = sha1(json_encode($normalized));

        if ($dryRun) { $skipped++; continue; }

        try {
            $stmt->execute(array_merge($values, [$rowHash, $xlsxPath]));
            if ($stmt->rowCount() > 0) { $inserted++; } else { $skipped++; }
        } catch (Throwable $e) {
            $errors++;
        }
    }

    $response = [
        'success' => true,
        'message' => 'XLSX import completed',
        'file' => $xlsxPath,
        'table' => $tableName,
        'sheet_index' => $sheetIndex,
        'inserted' => $inserted,
        'skipped' => $skipped,
        'errors' => $errors,
        'total_processed' => $total
    ];

    // Cleanup uploaded temp file
    if ($uploadedTmp && file_exists($uploadedTmp)) { @unlink($uploadedTmp); }

    respond(200, $response);

} catch (Throwable $e) {
    respond(500, ['success' => false, 'message' => 'Unexpected error: '.$e->getMessage()]);
}
?>


