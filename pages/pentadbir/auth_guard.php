<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function abort_403() {
    header('HTTP/1.1 403 Forbidden');
    exit('Akses dinafikan.');
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_role'])) {
    header('Location: ../../login/index.php', true, 302);
    exit();
}

if (empty($_SESSION['no_pekerja'])) {
    header('Location: ../../login/index.php', true, 302);
    exit();
}

// Tetapkan role yang dibenarkan untuk folder ini
$allowed_roles = ['PENTADBIR']; // ubah mengikut folder
if (!in_array($_SESSION['user_role'], $allowed_roles, true)) {
    abort_403();
}