
<?php
// config/db_idata.php
$ID_HOST = '';
$ID_NAME = '';
$ID_USER = '';
$ID_PASS = '';

try {
  $pdo_idata = new PDO("mysql:host=$ID_HOST;dbname=$ID_NAME;charset=latin1", $ID_USER, $ID_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);
  // Pastikan connection guna latin1 supaya data keluar betul
  $pdo_idata->exec("SET NAMES latin1");
} catch (PDOException $e) {
  die("IDATA DB error: " . htmlspecialchars($e->getMessage()));
}
