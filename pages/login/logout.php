<?php
// Declare a stricht type for better error handling
declare(strict_types=1);

// Logout the user  with full security and destroy all session data completely and then redirect to the login page
session_start();
session_unset(); // Hapus semua variabel sesi
session_destroy(); // Hancurkan sesi
header("Location: ../login/index.php"); // Alihkan ke halaman login
exit();
