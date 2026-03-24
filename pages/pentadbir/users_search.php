<?php
declare(strict_types=1);

session_start();
require_once 'auth_guard.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([]);
    exit;
}

$pdo_idata = null;
try {
    $dbConfigPath = __DIR__ . '/../../config/db_idata.php';
    if (file_exists($dbConfigPath)) {
        require_once $dbConfigPath;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([]);
    exit;
}

if (!($pdo_idata instanceof PDO)) {
    http_response_code(500);
    echo json_encode([]);
    exit;
}

$query = trim((string)($_POST['query'] ?? ''));
if ($query === '' || strlen($query) < 3) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo_idata->prepare(
        "SELECT no_pekerja, nama, jawatan, bah_fak, emel
         FROM t_staf
         WHERE no_pekerja LIKE :query_no OR nama LIKE :query_nama
         ORDER BY nama ASC
         LIMIT 20"
    );

    $like = '%' . $query . '%';
    $stmt->execute([
        ':query_no' => $like,
        ':query_nama' => $like,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results = [];

    foreach ($rows as $row) {
        $results[] = [
            'id' => (string)($row['no_pekerja'] ?? ''),
            'no_pekerja' => (string)($row['no_pekerja'] ?? ''),
            'nama' => (string)($row['nama'] ?? ''),
            'jawatan' => (string)($row['jawatan'] ?? ''),
            'jabatan' => (string)($row['bah_fak'] ?? ''),
            'emel' => (string)($row['emel'] ?? ''),
            'role' => 'staf',
        ];
    }

    echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([]);
}