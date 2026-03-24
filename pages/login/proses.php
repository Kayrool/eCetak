<?php
/**
 * proses.php - Login Processing Handler (Hardened)
 * Handles authentication with comprehensive security measures
 * Features: Strong session init, CSRF validation, per-session + per-IP rate limiting, input validation,
 *           security logging, strict security headers, and safe redirects by role.
 * Author: eCetak Development Team (Hardened by M365 Copilot)
 * Version: 3.0 (Security Hardened)
 */

declare(strict_types=1);

// ============================================================================
// RUNTIME & ERROR LOGGING (NO DISPLAY TO USER)
// ============================================================================
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ============================================================================
// SECURITY & CACHE HEADERS (SET EARLY)
// ============================================================================
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: Strict-Origin-When-Cross-Origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Conservative CSP. Adjust if you use external assets.
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'");

// ============================================================================
// SESSION SECURITY (SET PARAMS BEFORE session_start())
// ============================================================================
$secureTransport = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Force secure cookie in production. If you're in dev without HTTPS, set to false temporarily.
$FORCE_SECURE_COOKIE = true;

session_set_cookie_params([
    'lifetime' => 0,         // session cookie
    'path'     => '/',
    'domain'   => '',        // set your domain if you need cross-subdomain
    'secure'   => $FORCE_SECURE_COOKIE ? true : $secureTransport,
    'httponly' => true,
    'samesite' => 'Strict',  // change to 'Lax' only if you truly need cross-site POSTs
]);

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ============================================================================
// ERROR HANDLER
// ============================================================================
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    // On critical errors, fail closed and send user back to login.
    if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        $_SESSION['error_message'] = 'Ralat sistem: Sila hubungi pentadbir sistem.';
        header('Location: index.php', true, 302);
        exit();
    }
    return false; // allow normal PHP handling for non-critical
});

// ============================================================================
// CONSTANTS & PATHS
// ============================================================================
$LOG_DIR = realpath(__DIR__ . '/../../logs') ?: (__DIR__ . '/../../logs'); // fallback string if not exist yet
@mkdir($LOG_DIR, 0755, true);

// Rate limit parameters
const SESSION_MAX_ATTEMPTS  = 5;
const SESSION_LOCKOUT_SEC   = 15 * 60; // 15 min
const IP_MAX_ATTEMPTS       = 20;      // per IP window
const IP_WINDOW_SEC         = 15 * 60; // 15 min

// ============================================================================
// SECURITY UTILITIES
// ============================================================================
/**
 * Log security events to a dedicated log file.
 */
function logSecurityEvent(string $event_type, string $details = '', string $severity = 'INFO'): void {
    global $LOG_DIR;
    $log_file = $LOG_DIR . '/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $ua_sanitized = substr(preg_replace('/[^a-zA-Z0-9\s\-\.\(\);:\/,_]/', '', $ua), 0, 200);
    $entry = "[$timestamp] [$severity] Event: $event_type | IP: $ip | Details: $details | UA: $ua_sanitized\n";
    @file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
}

/**
 * Safer timing-attack resistant CSRF verification.
 */
function verifyCSRFToken(?string $token): bool {
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    if (empty($token)) {
        logSecurityEvent('CSRF_TOKEN_MISSING', 'No token provided', 'WARNING');
        return false;
    }
    if (!hash_equals((string)$_SESSION['csrf_token'], (string)$token)) {
        logSecurityEvent('CSRF_TOKEN_MISMATCH', 'Token validation failed', 'WARNING');
        return false;
    }
    // Token expiry check (1 hour)
    $issued = $_SESSION['csrf_token_time'] ?? 0;
    if ((time() - (int)$issued) > 3600) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        logSecurityEvent('CSRF_TOKEN_EXPIRED', 'Token exceeded 1 hour validity', 'WARNING');
        return false;
    }
    return true;
}

/**
 * Session-based rate limiting (per browser session).
 */
function checkRateLimitSession(): bool {
    $max = SESSION_MAX_ATTEMPTS;
    $lock = SESSION_LOCKOUT_SEC;

    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_first_attempt'] = time();
        return true;
    }
    $elapsed = time() - (int)$_SESSION['login_first_attempt'];
    if ($elapsed > $lock) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_first_attempt'] = time();
        return true;
    }
    if ((int)$_SESSION['login_attempts'] >= $max) {
        $remaining_time = max(1, ceil(($lock - $elapsed) / 60));
        logSecurityEvent('RATE_LIMIT_SESSION_EXCEEDED', "Attempts: {$_SESSION['login_attempts']}, Remain: {$remaining_time}m", 'WARNING');
        return false;
    }
    return true;
}

/**
 * Lightweight per-IP (+ optional username) rate limit using a JSON file.
 * Not bulletproof like Redis, but better than session-only.
 */
function checkRateLimitIP(?string $username = null): bool {
    global $LOG_DIR;
    $store = $LOG_DIR . '/rate_limit.json';
    $keyIP  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $keyUsr = $username ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username) : '';
    $compositeKey = $keyIP . ($keyUsr !== '' ? (':' . $keyUsr) : '');

    $now = time();
    $window = IP_WINDOW_SEC;

    $data = [];
    if (file_exists($store)) {
        $json = @file_get_contents($store);
        if ($json !== false) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }

    // prune old
    foreach ($data as $k => $row) {
        if (!isset($row['first']) || ($now - (int)$row['first']) > $window) {
            unset($data[$k]);
        }
    }

    if (!isset($data[$compositeKey])) {
        $data[$compositeKey] = ['first' => $now, 'count' => 0];
    }

    // Check
    if ((int)$data[$compositeKey]['count'] >= IP_MAX_ATTEMPTS) {
        logSecurityEvent('RATE_LIMIT_IP_EXCEEDED', "Key: $compositeKey, Count: {$data[$compositeKey]['count']}", 'WARNING');
        return false;
    }

    // Persist
    $ok = @file_put_contents($store, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
    if ($ok === false) {
        // If cannot write, log and continue (fallback to session-only limit)
        logSecurityEvent('RATE_LIMIT_STORE_WRITE_FAIL', "File: $store", 'WARNING');
    }

    return true;
}

/**
 * Increment counters after failed attempt.
 */
function markFailedAttempt(?string $username = null): void {
    global $LOG_DIR;
    // session-based
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 1;
        $_SESSION['login_first_attempt'] = time();
    } else {
        $_SESSION['login_attempts'] = (int)$_SESSION['login_attempts'] + 1;
    }

    // file-based per-IP(+user)
    $store = $LOG_DIR . '/rate_limit.json';
    $keyIP  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $keyUsr = $username ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username) : '';
    $compositeKey = $keyIP . ($keyUsr !== '' ? (':' . $keyUsr) : '');

    $now = time();
    $window = IP_WINDOW_SEC;

    $data = [];
    if (file_exists($store)) {
        $json = @file_get_contents($store);
        if ($json !== false) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }

    // prune old
    foreach ($data as $k => $row) {
        if (!isset($row['first']) || ($now - (int)$row['first']) > $window) {
            unset($data[$k]);
        }
    }

    if (!isset($data[$compositeKey])) {
        $data[$compositeKey] = ['first' => $now, 'count' => 0];
    }
    $data[$compositeKey]['count'] = (int)$data[$compositeKey]['count'] + 1;

    $ok = @file_put_contents($store, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
    if ($ok === false) {
        logSecurityEvent('RATE_LIMIT_STORE_WRITE_FAIL', "File: $store", 'WARNING');
    }
}

/**
 * Validate input formats strictly.
 */
function validateInputFormat(string $no_pekerja, string $no_kp): array {
    $errors = [];
    if (!preg_match('/^\d{6}$/', $no_pekerja)) {
        $errors[] = 'Nombor pekerja tidak valid';
    }
    if (!preg_match('/^\d{12}$/', $no_kp)) {
        $errors[] = 'Nombor kad pengenalan tidak valid';
    }
    return $errors;
}

/**
 * Authenticate against DB and return user info array on success.
 * Table: users(id, no_pekerja, password_hash, role, emel, bah_fak, aktif)
 */
function authenticateUser(string $no_pekerja, string $no_kp) {
    try {
        $db_config_path = __DIR__ . '/../../config/db.php';
        if (!file_exists($db_config_path)) {
            logSecurityEvent('AUTH_ERROR', 'DB config not found at: ' . $db_config_path, 'ERROR');
            return false;
        }
        require_once $db_config_path;

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            logSecurityEvent('AUTH_ERROR', 'PDO not initialized', 'ERROR');
            return false;
        }

        $sql = "SELECT id, no_pekerja, nama, password_hash, role, emel, bah_fak
                FROM users
                WHERE no_pekerja = :no_pekerja AND aktif = 1
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        if (!$stmt) {
            logSecurityEvent('AUTH_ERROR', 'Prepare failed', 'ERROR');
            return false;
        }

        $stmt->bindParam(':no_pekerja', $no_pekerja, PDO::PARAM_STR);
        if (!$stmt->execute()) {
            $err = $stmt->errorInfo();
            logSecurityEvent('AUTH_ERROR', 'Execute failed: ' . implode('|', $err), 'ERROR');
            return false;
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($no_kp, $user['password_hash'])) {
            // Return a lean profile; do NOT write to session here
            return [
                'id'       => $user['id'],
                'nama'     => $user['nama'],
                'role'     => $user['role'],
                'emel'     => $user['emel'],
                'bah_fak'  => $user['bah_fak'] ?? null,
            ];
        }

        logSecurityEvent('LOGIN_FAILED', "Invalid credentials - Employee: $no_pekerja");
        return false;

    } catch (PDOException $e) {
        logSecurityEvent('AUTH_ERROR', 'PDO: ' . $e->getMessage(), 'ERROR');
        return false;
    } catch (Throwable $e) {
        logSecurityEvent('AUTH_ERROR', 'Unexpected: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Determine redirect URL by role (whitelist mapping).
 */
function redirectByRole(?string $role): string {
    $role = $role ? trim($role) : '';
    switch ($role) {
        case 'PENTADBIR':          return '../pentadbir/';
        case 'PENYELIA':           return '../penyelia/';
        case 'KETUA BAHAGIAN':     return '../ketua_bahagian/';
        case 'KETUA PERPUSTAKAAN': return '../ketua_perpustakaan/';
        case 'REKTOR':             return '../rektor/';
        default:                   return '../../'; // safe default
    }
}

// ============================================================================
// REQUEST VALIDATION
// ============================================================================
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    logSecurityEvent('INVALID_REQUEST_METHOD', 'Expected POST, got ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'), 'WARNING');
    $_SESSION['error_message'] = 'Ralat: Kaedah permintaan tidak sah. Sila gunakan borang log masuk.';
    header('Location: index.php', true, 302);
    exit();
}

// Enhanced rate limiting (IP + Session). We check before touching DB.
$hint_user = isset($_POST['no_pekerja']) ? trim((string)$_POST['no_pekerja']) : null;
if (!checkRateLimitSession() || !checkRateLimitIP($hint_user)) {
    $_SESSION['error_message'] = 'Terlalu banyak percubaan log masuk. Sila cuba semula selepas 15 minit.';
    logSecurityEvent('LOGIN_BLOCKED', 'Rate limit exceeded (IP or Session)', 'WARNING');
    header('Location: index.php', true, 302);
    exit();
}

// CSRF verification
$csrf_token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : null;
if (!verifyCSRFToken($csrf_token)) {
    $_SESSION['error_message'] = 'Ralat keselamatan: Pengesahan gagal. Sila cuba semula.';
    logSecurityEvent('CSRF_VALIDATION_FAILED', 'Invalid or missing CSRF token', 'WARNING');
    header('Location: index.php', true, 302);
    exit();
}

// ============================================================================
// INPUT EXTRACTION & VALIDATION
// ============================================================================
// We avoid HTML encoding here. Use prepared statements for DB, and encode on OUTPUT in views.
$no_pekerja = isset($_POST['no_pekerja']) ? trim((string)$_POST['no_pekerja']) : '';
$no_kp      = isset($_POST['no_kp'])      ? trim((string)$_POST['no_kp'])      : '';

if ($no_pekerja === '' || $no_kp === '') {
    $_SESSION['error_message'] = 'Ralat: Sila isi semua medan yang diperlukan.';
    logSecurityEvent('LOGIN_FAILED', 'Missing required fields');
    header('Location: index.php', true, 302);
    exit();
}

$validation_errors = validateInputFormat($no_pekerja, $no_kp);
if (!empty($validation_errors)) {
    $_SESSION['error_message'] = 'Ralat: ' . implode(', ', $validation_errors);
    logSecurityEvent('LOGIN_FAILED', 'Format validation failed: ' . json_encode($validation_errors, JSON_UNESCAPED_UNICODE));
    header('Location: index.php', true, 302);
    exit();
}

// ============================================================================
// AUTHENTICATION
// ============================================================================
$userInfo = authenticateUser($no_pekerja, $no_kp);

if ($userInfo !== false) {
    // ===== AUTHENTICATION SUCCESS =====
    // Clear rate limit session counters
    unset($_SESSION['login_attempts'], $_SESSION['login_first_attempt']);

    // Regenerate session ID AFTER authentication and BEFORE setting auth flags
    session_regenerate_id(true);

    // Store user profile in session (server-side only)
    $_SESSION['logged_in']     = true;
    $_SESSION['login_time']    = time();
    $_SESSION['no_pekerja']    = $no_pekerja;

    $_SESSION['user_id']       = $userInfo['id'];
    $_SESSION['user_nama']     = $userInfo['nama'];
    $_SESSION['user_role']     = $userInfo['role'];
    $_SESSION['user_emel']     = $userInfo['emel'];
    $_SESSION['user_bah_fak']  = $userInfo['bah_fak'];

    logSecurityEvent('LOGIN_SUCCESS', "Employee: $no_pekerja (ID: {$userInfo['id']})");

    // Compute redirect safely
    $redirect_url = redirectByRole($userInfo['role']);

    logSecurityEvent('LOGIN_REDIRECT', "Role: {$userInfo['role']}, Redirect: $redirect_url");

    // Close session write and redirect
    session_write_close();
    header('Location: ' . $redirect_url, true, 302);
    exit();

} else {
    // ===== AUTHENTICATION FAILED =====
    markFailedAttempt($no_pekerja);

    $_SESSION['error_message'] = 'Ralat: Nombor pekerja atau nombor kad pengenalan tidak sah.';
    header('Location: index.php', true, 302);
    exit();
}