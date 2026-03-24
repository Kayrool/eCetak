<?php
// update_role.php
declare(strict_types=1);

session_start();
require_once 'auth_guard.php'; // pastikan user logged in

$isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

if (!$isAjax) {
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $isAjax = strpos($accept, 'application/json') !== false;
}

/**
 * Hantar respons seragam:
 * - AJAX: JSON
 * - Non-AJAX: popup SweetAlert + redirect
 */
function respondAndExit(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    global $isAjax;

    http_response_code($statusCode);

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    $title = $ok ? 'Berjaya' : 'Ralat';
    $icon = $ok ? 'success' : 'error';
    $redirectUrl = 'pengguna.php';

    echo '<!doctype html><html lang="ms"><head><meta charset="utf-8"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js'></script>";
    echo '</head><body>';
    echo '<script>';
    echo 'Swal.fire({';
    echo 'title:' . json_encode($title, JSON_UNESCAPED_UNICODE) . ',';
    echo 'text:' . json_encode($message, JSON_UNESCAPED_UNICODE) . ',';
    echo 'icon:' . json_encode($icon, JSON_UNESCAPED_UNICODE) . ',';
    echo "confirmButtonText:'OK'";
    echo '}).then(function(){ window.location.href=' . json_encode($redirectUrl, JSON_UNESCAPED_SLASHES) . '; });';
    echo '</script>';
    echo '</body></html>';
    exit;
}

// Sambungan pangkalan data
$pdo = null;
$DB_OK = false;
try {
    $db_config_path = __DIR__ . '/../../config/db.php';
    if (file_exists($db_config_path)) {
        require_once $db_config_path;
        if(isset($pdo) && $pdo instanceof PDO) {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $DB_OK = true;
        }
    }
} catch (PDOException $e) { /* fallback kosong */ }

if ($pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT 1");
    } catch (PDOException $e) {
        respondAndExit(false, 'Koneksi database gagal: ' . $e->getMessage(), 500);
    }
} else {
    respondAndExit(false, 'Koneksi database gagal: PDO tidak tersedia.', 500);
}

// (Opsyenal tapi digalakkan) CSRF
if (!empty($_SESSION['csrf'])) {
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        respondAndExit(false, 'Token CSRF tidak sah.', 419);
    }
}

$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$role = $_POST['role'] ?? '';

if ($id <= 0 || $role === '') {
    respondAndExit(false, 'Input tidak lengkap.', 400);
}

// Ambil allowlist role dari ENUM
$enum_roles = [];
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        $type = $col['Type'] ?? '';
        if (preg_match("/^enum\((.*)\)$/i", $type, $m)) {
            $enum_roles = str_getcsv($m[1], ',', "'");
        }
    } catch (PDOException $e) {
        respondAndExit(false, 'Gagal ambil allowlist role: ' . $e->getMessage(), 500);
    }
}
if (!in_array($role, $enum_roles, true)) {
    respondAndExit(false, 'Peranan tidak dibenarkan.', 422);
}

// Cegah admin ubah role sendiri
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
if ($currentUserId > 0 && $currentUserId === $id) {
    respondAndExit(false, 'Anda tidak boleh mengubah peranan sendiri.', 403);
}

// Semak dan pastikan user role adalah PENTADBIR
if ($_SESSION['user_role'] !== 'PENTADBIR') {
    respondAndExit(false, 'Akses dinafikan.', 403);
}

// (Opsyenal) Semak autorisasi: hanya admin boleh ubah role
// if (!isCurrentUserAdmin()) { http_response_code(403); echo json_encode(['ok'=>false, 'message'=>'Akses dinafikan']); exit; }
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("UPDATE `users` SET `role` = ? WHERE `id` = ?");
        $stmt->execute([$role, $id]);
        respondAndExit(true, 'Peranan berjaya dikemaskini.', 200, ['role' => $role]);
    } catch (PDOException $e) {
        respondAndExit(false, 'Gagal kemaskini: ' . $e->getMessage(), 500);
    }
} else {
    respondAndExit(false, 'Sambungan DB tidak tersedia.', 500);
}