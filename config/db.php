
<?php
// config/db.php
$DB_HOST = '10.0.23.197';
$DB_NAME = 'ecetak';
$DB_USER = 'ikdhusr';
$DB_PASS = 'dbk3d@h17';

try {
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);
} catch (PDOException $e) {
  die("DB error: " . htmlspecialchars($e->getMessage()));
}