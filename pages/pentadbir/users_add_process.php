<?php
declare(strict_types=1);

session_start();
require_once 'auth_guard.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/db_idata.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Kaedah tidak dibenarkan.']);
    exit;
}

$noPekerja = trim((string)($_POST['no_pekerja'] ?? ''));
$requestedRole = trim((string)($_POST['role'] ?? ''));
if ($noPekerja === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'No. pekerja diperlukan.']);
    exit;
}

if (!($pdo instanceof PDO) || !($pdo_idata instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Sambungan pangkalan data tidak tersedia.']);
    exit;
}

try {
    $checkUser = $pdo->prepare('SELECT id FROM users WHERE no_pekerja = :no_pekerja LIMIT 1');
    $checkUser->execute([':no_pekerja' => $noPekerja]);
    $existingUser = $checkUser->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        echo json_encode(['ok' => false, 'message' => 'Pengguna sudah wujud dalam sistem.']);
        exit;
    }

    $staffStmt = $pdo_idata->prepare('SELECT no_pekerja, nama, jawatan, bah_fak, emel, no_kp FROM t_staf WHERE no_pekerja = :no_pekerja LIMIT 1');
    $staffStmt->execute([':no_pekerja' => $noPekerja]);
    $staff = $staffStmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Data staf tidak dijumpai di iData.']);
        exit;
    }

    $role = 'PENYELIA';
    $enumStmt = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'");
    $enumCol = $enumStmt ? $enumStmt->fetch(PDO::FETCH_ASSOC) : null;
    $enumType = isset($enumCol['Type']) ? (string)$enumCol['Type'] : '';

    if (preg_match('/^enum\\((.*)\\)$/i', $enumType, $m)) {
        $roles = str_getcsv($m[1], ',', "'");
        $normalized = [];
        foreach ($roles as $r) {
            $normalized[strtoupper(trim((string)$r))] = trim((string)$r);
        }

        if (isset($normalized['PENYELIA'])) {
            $role = $normalized['PENYELIA'];
        } elseif (isset($normalized['STAF'])) {
            $role = $normalized['STAF'];
        } elseif (isset($normalized['USER'])) {
            $role = $normalized['USER'];
        } elseif (!empty($roles)) {
            $role = trim((string)$roles[0]);
        }

        if ($requestedRole !== '') {
            $requestedKey = strtoupper($requestedRole);
            if (!isset($normalized[$requestedKey])) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => 'Role yang dipilih tidak sah.']);
                exit;
            }
            $role = $normalized[$requestedKey];
        }
    } elseif ($requestedRole !== '') {
        $role = $requestedRole;
    }

    $noKp = preg_replace('/\D/', '', (string)($staff['no_kp'] ?? ''));
    $passwordSeed = $noKp !== '' ? $noKp : $noPekerja;
    $passwordHash = password_hash(substr($passwordSeed, 0, 20), PASSWORD_BCRYPT);

    $insert = $pdo->prepare(
        'INSERT INTO users (no_pekerja, nama, jawatan, bah_fak, password_hash, role, aktif, emel)
         VALUES (:no_pekerja, :nama, :jawatan, :bah_fak, :password_hash, :role, 1, :emel)'
    );

    $insert->execute([
        ':no_pekerja' => (string)($staff['no_pekerja'] ?? $noPekerja),
        ':nama' => (string)($staff['nama'] ?? ''),
        ':jawatan' => (string)($staff['jawatan'] ?? ''),
        ':bah_fak' => (string)($staff['bah_fak'] ?? ''),
        ':password_hash' => $passwordHash,
        ':role' => $role,
        ':emel' => (string)($staff['emel'] ?? ''),
    ]);

    echo json_encode(['ok' => true, 'message' => 'Pengguna berjaya ditambah ke pangkalan data users.']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'Pengguna sudah wujud atau data bertindih.']);
        exit;
    }

    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ralat pangkalan data semasa simpan pengguna.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ralat pelayan semasa simpan pengguna.']);
}
