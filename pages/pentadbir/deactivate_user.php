<?php
declare(strict_types=1);

session_start();
require_once 'auth_guard.php';
require_once __DIR__ . '/../../config/db.php';

$isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
if (!$isAjax) {
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $isAjax = strpos($accept, 'application/json') !== false;
}

function respondAndExit(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    global $isAjax;

    http_response_code($statusCode);

    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: text/html; charset=UTF-8');
    $title = $ok ? 'Berjaya' : 'Ralat';
    $icon = $ok ? 'success' : 'error';

    echo '<!doctype html><html lang="ms"><head><meta charset="utf-8"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js'></script>";
    echo '</head><body>';
    echo '<script>';
    echo 'Swal.fire({';
    echo 'title:' . json_encode($title, JSON_UNESCAPED_UNICODE) . ',';
    echo 'text:' . json_encode($message, JSON_UNESCAPED_UNICODE) . ',';
    echo 'icon:' . json_encode($icon, JSON_UNESCAPED_UNICODE) . ',';
    echo "confirmButtonText:'OK'";
    echo '}).then(function(){ window.location.href=' . json_encode('pengguna.php', JSON_UNESCAPED_SLASHES) . '; });';
    echo '</script>';
    echo '</body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondAndExit(false, 'Kaedah tidak dibenarkan.', 405);
}

if (!empty($_SESSION['csrf'])) {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)$_SESSION['csrf'], $csrf)) {
        respondAndExit(false, 'Token CSRF tidak sah.', 419);
    }
}

$userId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($userId <= 0) {
    respondAndExit(false, 'ID pengguna tidak sah.', 422);
}

if (!($pdo instanceof PDO)) {
    respondAndExit(false, 'Sambungan pangkalan data tidak tersedia.', 500);
}

try {
    $targetStmt = $pdo->prepare('SELECT id, no_pekerja, aktif FROM users WHERE id = :id LIMIT 1');
    $targetStmt->execute([':id' => $userId]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        respondAndExit(false, 'Pengguna tidak dijumpai.', 404);
    }

    $meNoPekerja = (string)($_SESSION['no_pekerja'] ?? '');
    if ($meNoPekerja !== '' && hash_equals($meNoPekerja, (string)($target['no_pekerja'] ?? ''))) {
        respondAndExit(false, 'Anda tidak boleh menukar status akaun sendiri.', 403);
    }

    $isActive = (int)($target['aktif'] ?? 0) === 1;
    $nextStatus = $isActive ? 0 : 1;

    $updateStmt = $pdo->prepare('UPDATE users SET aktif = :aktif WHERE id = :id');
    $updateStmt->execute([
        ':aktif' => $nextStatus,
        ':id' => $userId,
    ]);

    $message = $nextStatus === 1
        ? 'Pengguna berjaya diaktifkan.'
        : 'Pengguna berjaya dinyahaktifkan.';
    respondAndExit(true, $message, 200, ['aktif' => $nextStatus]);
} catch (Throwable $e) {
    respondAndExit(false, 'Ralat pelayan semasa mengemas kini status pengguna.', 500);
}
