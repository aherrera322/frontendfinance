<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Reuse DB connection from the auth system
require_once __DIR__ . '/../auth/config.php';

// -----------------------------
// Configuration
// -----------------------------
// Default CSV path (you can override with ?file=)
$defaultCsvPath = 'C:\\Users\\alexh\\OneDrive - Zimple Rentals Inc\\2024\\2024-Reports\\ZB Reservations by App Date.csv';

// Database and table configuration
$databaseName = DB_NAME; // reuse the same database created by auth/config.php
$tableName = 'zb_reservations';

// Optional: set to true to attempt basic type inference (INTEGER/DECIMAL/DATETIME/TEXT)
$enableTypeInference = true;

// Use a unique row hash to avoid duplicates on repeated imports
$rowHashColumn = 'row_hash';

// -----------------------------
// Helpers
// -----------------------------
function respond($statusCode, $payload)
{
	http_response_code($statusCode);
	echo json_encode($payload);
	exit;
}

function sanitizeColumnName($name)
{
	// Convert to snake_case, remove non-alphanumeric/underscore, trim length to 60 chars
	$lower = strtolower(trim($name));
	$underscored = preg_replace('/[^a-z0-9]+/i', '_', $lower);
	$underscored = trim($underscored, '_');
	if ($underscored === '') {
		$underscored = 'col';
	}
	return substr($underscored, 0, 60);
}

function inferType($sampleValues)
{
	$hasFloat = false;
	$hasInt = true;
	$hasDateTime = true;
	$checked = 0;
	foreach ($sampleValues as $value) {
		$checked++;
		$value = trim((string)$value);
		if ($value === '') { continue; }
		// Check datetime (very permissive)
		if (strtotime($value) === false) {
			$hasDateTime = false;
		}
		// Check numeric
		if (!is_numeric($value)) {
			$hasInt = false;
			$hasFloat = false;
		} else {
			if ((string)(int)$value === (string)$value) {
				// Looks like integer
			} else {
				$hasFloat = true;
				$hasInt = false;
			}
		}
		// Limit checks for performance
		if ($checked > 50) { break; }
	}
	if ($hasDateTime && $checked > 0) return 'DATETIME NULL';
	if ($hasInt) return 'INT NULL';
	if ($hasFloat) return 'DECIMAL(18,6) NULL';
	return 'TEXT NULL';
}

function buildCreateTableSql(PDO $pdo, $tableName, $columns, $enableTypeInference, $rowHashColumn)
{
	// Determine column SQL types (basic inference from preview rows stored in $columns[$col]['samples'])
	$columnSqlParts = [];
	foreach ($columns as $colKey => $meta) {
		$colName = $meta['name'];
		$type = 'TEXT NULL';
		if ($enableTypeInference && isset($meta['samples'])) {
			$type = inferType($meta['samples']);
		}
		$columnSqlParts[] = "`{$colName}` {$type}";
	}

	$columnSql = implode(",\n\t", $columnSqlParts);
	$sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
		id INT AUTO_INCREMENT PRIMARY KEY,
		{$columnSql},
		`{$rowHashColumn}` CHAR(40) NOT NULL UNIQUE,
		`source_file` TEXT NULL,
		`imported_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

	$pdo->exec($sql);
}

function ensureColumnsExist(PDO $pdo, $tableName, $columns)
{
	// Add any missing columns as TEXT
	$stmt = $pdo->query("SHOW COLUMNS FROM `{$tableName}`");
	$existing = [];
	foreach ($stmt->fetchAll() as $col) {
		$existing[$col['Field']] = true;
	}
	foreach ($columns as $meta) {
		$colName = $meta['name'];
		if (!isset($existing[$colName])) {
			$pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `{$colName}` TEXT NULL");
		}
	}
}

try {
	// Determine source: uploaded file or local path
	$uploadedTmp = null;
	if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
		$uploadsDir = __DIR__ . '/uploads';
		if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0775, true); }
		$basename = basename($_FILES['file']['name']);
		$target = $uploadsDir . '/' . uniqid('csv_', true) . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $basename);
		if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
			respond(400, ['success' => false, 'message' => 'Failed to move uploaded file']);
		}
		$csvPath = $target;
		$uploadedTmp = $target; // mark for cleanup
	} else {
		$csvPath = isset($_GET['file']) && $_GET['file'] !== '' ? $_GET['file'] : $defaultCsvPath;
	}
	$dryRun = isset($_GET['dry_run']) && ($_GET['dry_run'] === '1' || strtolower($_GET['dry_run']) === 'true');
	$previewRows = isset($_GET['preview_rows']) ? max(0, (int)$_GET['preview_rows']) : 50;

	if (!file_exists($csvPath)) {
		respond(400, [
			'success' => false,
			'message' => 'CSV file not found. Please ensure the Excel file is saved as CSV at the same path or pass ?file= path.',
			'expected_path' => $csvPath
		]);
	}

	$pdo = getDBConnection();
	if (!$pdo) {
		respond(500, [ 'success' => false, 'message' => 'Database connection failed' ]);
	}

	// Open CSV
	$handle = fopen($csvPath, 'r');
	if ($handle === false) {
		respond(400, [ 'success' => false, 'message' => 'Failed to open CSV file for reading' ]);
	}

	// Detect delimiter (simple heuristic)
	$firstLine = fgets($handle);
	if ($firstLine === false) {
		respond(400, [ 'success' => false, 'message' => 'CSV file appears to be empty' ]);
	}
	$delimiters = [',', ';', '\t', '|'];
	$bestDelimiter = ',';
	$maxFields = 0;
	foreach ($delimiters as $delim) {
		$fields = str_getcsv($firstLine, $delim);
		if (count($fields) > $maxFields) {
			$maxFields = count($fields);
			$bestDelimiter = $delim;
		}
	}

	// Reset pointer and read headers with detected delimiter
	rewind($handle);
	$headers = fgetcsv($handle, 0, $bestDelimiter);
	if ($headers === false || count($headers) === 0) {
		respond(400, [ 'success' => false, 'message' => 'Unable to read header row from CSV' ]);
	}

	// Sanitize and ensure unique column names
	$columns = [];
	$used = [];
	foreach ($headers as $i => $header) {
		$name = sanitizeColumnName($header);
		if (isset($used[$name])) {
			$counter = 2;
			while (isset($used[$name . '_' . $counter])) { $counter++; }
			$name = $name . '_' . $counter;
		}
		$used[$name] = true;
		$columns[$i] = [ 'name' => $name, 'samples' => [] ];
	}

	// Collect some sample values for type inference
	$sampled = 0;
	while (($row = fgetcsv($handle, 0, $bestDelimiter)) !== false && $sampled < $previewRows) {
		foreach ($columns as $idx => &$meta) {
			$meta['samples'][] = $row[$idx] ?? '';
		}
		$sampled++;
	}

	// Create table if needed, then ensure columns exist
	buildCreateTableSql($pdo, $tableName, $columns, $enableTypeInference, $rowHashColumn);
	ensureColumnsExist($pdo, $tableName, $columns);

	// Prepare insert statement (dynamic)
	$columnNames = array_map(function($meta) { return $meta['name']; }, $columns);
	$insertColumnsSql = '`' . implode('`, `', $columnNames) . '`, `' . $rowHashColumn . '`, `source_file`';
	$placeholders = rtrim(str_repeat('?,', count($columnNames)), ',') . ', ?, ?';
	$sql = "INSERT IGNORE INTO `{$tableName}` ({$insertColumnsSql}) VALUES ({$placeholders})";
	$insertStmt = $pdo->prepare($sql);

	// Rewind to second line (first data row)
	rewind($handle);
	// consume header
	fgetcsv($handle, 0, $bestDelimiter);

	$inserted = 0;
	$skipped = 0;
	$errors = 0;
	$line = 1; // counting from 1 for header

	while (($row = fgetcsv($handle, 0, $bestDelimiter)) !== false) {
		$line++;
		// Normalize row length
		$row = array_pad($row, count($columns), '');
		$values = [];
		foreach ($columns as $idx => $meta) {
			$values[] = $row[$idx];
		}

		// Compute row hash to dedupe
		$normalizedForHash = array_map(function($v) { return trim(strtolower((string)$v)); }, $values);
		$rowHash = sha1(json_encode($normalizedForHash));

		$params = array_merge($values, [$rowHash, $csvPath]);

		if ($dryRun) { $skipped++; continue; }

		try {
			$insertStmt->execute($params);
			if ($insertStmt->rowCount() > 0) {
				$inserted++;
			} else {
				$skipped++; // likely duplicate due to IGNORE on unique row_hash
			}
		} catch (Throwable $e) {
			$errors++;
		}
	}

	fclose($handle);

	$response = [
		'success' => true,
		'message' => 'Import completed',
		'file' => $csvPath,
		'table' => $tableName,
		'inserted' => $inserted,
		'skipped' => $skipped,
		'errors' => $errors
	];

	// Cleanup uploaded temp file
	if ($uploadedTmp && file_exists($uploadedTmp)) { @unlink($uploadedTmp); }

	respond(200, $response);

} catch (Throwable $e) {
	respond(500, [ 'success' => false, 'message' => 'Unexpected error: ' . $e->getMessage() ]);
}
?>


