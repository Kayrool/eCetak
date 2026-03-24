<?php
// auth/security.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** CSRF token untuk form */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/** Normalisasi emel: trim + lowercase */
function norm_emel(string $emel): string {
    return strtolower(trim($emel));
}

/** Polisi kata laluan asas (ketatkan kalau perlu) */
function validate_password_policy(string $pwd, array &$errors): bool {
    $errors = [];
    if (strlen($pwd) < 12) $errors[] = 'Minimum 12 aksara.';
    if (!preg_match('/[A-Z]/', $pwd)) $errors[] = 'Perlu sekurang-kurangnya 1 huruf besar.';
    if (!preg_match('/[a-z]/', $pwd)) $errors[] = 'Perlu sekurang-kurangnya 1 huruf kecil.';
    if (!preg_match('/\d/', $pwd))    $errors[] = 'Perlu sekurang-kurangnya 1 nombor.';
    if (!preg_match('/[^A-Za-z0-9]/', $pwd)) $errors[] = 'Perlu sekurang-kurangnya 1 simbol.';
    return empty($errors);
}

/** Uniform response delay untuk kurangkan timing leak (±250–350ms) */
function uniform_delay(int $minMs = 250, int $maxMs = 350): void {
    $sleep = random_int($minMs, $maxMs) * 1000; // microseconds
    usleep($sleep);
}

/** Honeypot: field tersembunyi yang jika terisi → kemungkinan bot */
function is_bot_honeypot(): bool {
    return !empty($_POST['website'] ?? '');
}