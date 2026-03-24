<?php
declare(strict_types=1);

/**
 * Dashboard Pentadbir - eCetak (Fixed)
 * - Betulkan header newline (CSP single line)
 * - Betulkan tag CDN (Bootstrap, Font Awesome, AdminLTE, jQuery, Chart.js, DataTables)
 * - Kemas navbar/sidebar/link/img
 */

require_once __DIR__ . '/auth_guard.php';

// ------------------------------
// SECURITY HEADERS (single-line)
// ------------------------------
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// CSP: satu baris SAHAJA (tiada newline)
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; img-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

// Paparan pengguna
$no_pekerja  = $_SESSION['no_pekerja'] ?? 'PENTADBIR';
$emelPaparan = $_SESSION['user_emel'] ?? '';
$rolePaparan = $_SESSION['user_role'] ?? 'PENTADBIR';

include_once __DIR__ . '/../../config/db_idata.php';
if (!isset($pdo_idata) || !($pdo_idata instanceof PDO)) {
    die('Ralat sambungan IDATA. Sila hubungi pentadbir sistem.');
} else {
    $pdo_idata->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

// Query nama paparan dari idata.t_staf ikut no_pekerja
$namaPaparan = $no_pekerja;
if ($pdo_idata instanceof PDO) {
    try {
        $stmt = $pdo_idata->prepare("SELECT nama FROM t_staf WHERE no_pekerja = :no_pekerja LIMIT 1");
        $stmt->execute([':no_pekerja' => $no_pekerja]);
        $v = $stmt->fetchColumn();
        if (is_string($v) && $v !== '') {
            $namaPaparan = $v;
        }
    } catch (Throwable $e) {
        // fallback: $namaPaparan kekal $no_pekerja
    }
}

// ------------------------------
// DB & DATA (fallback selamat)
// ------------------------------
$pdo = null;
$DB_OK = false;
try {
    $db_config_path = __DIR__ . '/../../config/db.php';
    if (file_exists($db_config_path)) {
        require_once $db_config_path; // mesti set $pdo
        if (isset($pdo) && ($pdo instanceof PDO)) {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $DB_OK = true;
        }
    }
} catch (Throwable $e) { /* fallback kosong */ }

function safeScalar(PDO $pdo, string $sql, array $params = [], int $fallback = 0): int {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        return is_numeric($v) ? (int)$v : $fallback;
    } catch (Throwable $e) { return $fallback; }
}
function fetchRecentRequests(PDO $pdo, int $limit = 10): array {
    $sql = "SELECT id, no_pekerja, nama, bah_fak, status, dikemaskini_pada
            FROM permohonan
            ORDER BY dikemaskini_pada DESC
            LIMIT :limit";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) { return []; }
}

// Metrik
$kira_baru_24h = 0;
$kira_menunggu_kj = 0;
$kira_menunggu_kp = 0;
$kira_menunggu_rektor = 0;

if ($DB_OK && isset($pdo) && ($pdo instanceof PDO)) {
    $kira_baru_24h = safeScalar($pdo, "SELECT COUNT(*) FROM permohonan WHERE dicipta_pada >= (NOW() - INTERVAL 1 DAY)");
    $kira_menunggu_kj = safeScalar($pdo, "SELECT COUNT(*) FROM permohonan WHERE status = 'PENGESAHAN KB'");
    $kira_menunggu_kp = safeScalar($pdo, "SELECT COUNT(*) FROM permohonan WHERE status = 'KELULUSAN KP'");
    $kira_menunggu_rektor = safeScalar($pdo, "SELECT COUNT(*) FROM permohonan WHERE status = 'KEBENARAN REKTOR'");
}

$agih_status = [
    'MENUNGGU_KETUA_JABATAN' => 0,
    'MENUNGGU_KETUA_PERPUSTAKAAN' => 0,
    'MENUNGGU_REKTOR' => 0,
    'DILULUSKAN' => 0,
    'DITOLAK' => 0,
];
if ($DB_OK && isset($pdo) && ($pdo instanceof PDO)) {
    $agih_status['MENUNGGU_KETUA_JABATAN']      = safeScalar($pdo, "SELECT COUNT(*) FROM permohonan WHERE status='PENGESAHAN KB'");
    $agih_status['MENUNGGU_KETUA_PERPUSTAKAAN'] = safeScalar($pdo, "SELECT COUNT(*) FROM permohonan WHERE status='KELULUSAN KP'");
    $agih_status['MENUNGGU_REKTOR']             = safeScalar($pdo, "SELECT COUNT(*) FROM permohonan WHERE status='KEBENARAN REKTOR'");
    $agih_status['DILULUSKAN']                  = safeScalar($pdo, "SELECT COUNT(*) FROM permohonan WHERE status LIKE 'CETAK%'");
    $agih_status['DITOLAK']                     = safeScalar($pdo, "SELECT COUNT(*) FROM permohonan WHERE status='DITOLAK'");
}

$recent = ($DB_OK && isset($pdo) && ($pdo instanceof PDO)) ? fetchRecentRequests($pdo, 10) : [];

// --- Util: ambil ENUM values daripada skema MySQL ---
function get_enum_values(PDO $pdo, string $table, string $column): array {
    // Lindungi identifier minimal (bukan untuk input pengguna)
    $tableSafe  = str_replace('`', '``', $table);
    $columnSafe = str_replace("'", "''", $column);

    $stmt = $pdo->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnSafe}'");
    $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if (!$row || empty($row['Type'])) return [];

    // Contoh $row['Type']: enum('PENTADBIR','PENYELIA','KETUA BAHAGIAN','KETUA PERPUSTAKAAN','REKTOR')
    if (preg_match("/^enum\\((.*)\\)$/i", $row['Type'], $m)) {
        // Guna str_getcsv dgn enclosure apostrophe untuk parse selamat
        $vals = str_getcsv($m[1], ',', "'", "\\");
        return array_map('trim', $vals);
    }
    return [];
}

// --- Inisialisasi $users_by_role ikut ENUM sebenar ---
$users_by_role = [];
// $role_order    = []; // untuk ORDER BY FIELD(role,...)
if (!empty($DB_OK) && isset($pdo) && ($pdo instanceof PDO )) {
    try {
        // Ambil senarai role dari ENUM users.role
        $enum_roles = get_enum_values($pdo, 'users', 'role');

        // Jika gagal dapat ENUM, fallback minimal (elak pecah UI)
        if (empty($enum_roles)) {
            $enum_roles = ['PENTADBIR','PENYELIA','KETUA BAHAGIAN','KETUA PERPUSTAKAAN','REKTOR']; // fallback
        }

        // Inisialisasi struktur kosong mengikut ENUM
        foreach ($enum_roles as $r) {
            $users_by_role[$r] = [];
        }

        // Bina klausa FIELD(role, ...) untuk mengekalkan turutan ENUM
        // Contoh: FIELD(role,'PENTADBIR','PENYELIA',...)
        $fieldList = implode("','", array_map(function($x) { return str_replace("'", "''", $x); }, $enum_roles));
        $orderRole = "FIELD(role,'{$fieldList}')";
        $sql = "SELECT id, no_pekerja, nama, emel, role, bah_fak, aktif
                FROM users
                ORDER BY {$orderRole}, no_pekerja";

        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $r = $row['role'] ?? '';
            // Jika ada role pelik yang tak wujud dalam ENUM (data legasi), letak bucket khas
            if (!array_key_exists($r, $users_by_role)) {
                if (!isset($users_by_role['__ROLE_TIDAK_DI_KENAL__'])) {
                    $users_by_role['__ROLE_TIDAK_DI_KENAL__'] = [];
                }
                $users_by_role['__ROLE_TIDAK_DI_KENAL__'][] = $row;
            } else {
                $users_by_role[$r][] = $row;
            }
        }
    } catch (Throwable $e) {
        // Pada produksi, log ke error_log. Di sini, jangan rosakkan halaman.
        // error_log('Users by role error: '.$e->getMessage());
        if (empty($users_by_role)) {
            // Minimum fallback agar pembolehubah wujud
            $users_by_role = ['PENTADBIR'=>[], 'PENYELIA'=>[], 'KETUA BAHAGIAN'=>[], 'KETUA PERPUSTAKAAN'=>[], 'REKTOR'=>[]];
        }
    }
} else {
    // Fallback jika DB tidak OK
    $users_by_role = ['PENTADBIR'=>[], 'PENYELIA'=>[], 'KETUA BAHAGIAN'=>[], 'KETUA PERPUSTAKAAN'=>[], 'REKTOR'=>[]];
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title>eCetak | Dashboard Pentadbir</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- Bootstrap 4.6 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">

  <!-- AdminLTE 3.2 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css" integrity="sha512-IuO+tczf4J43RzbCMEFggCWW5JuX78IrCJRFFBoQEXNvGI6gkUw4OjuwMidiS4Lm9Q2lILzpJwZuMWuSEeT9UQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.10/css/dataTables.bootstrap4.min.css" crossorigin="anonymous" />

  <style>
      :root {
        /* === Lite Matte Blue Palette === */
        --lite-gray: #f7f7f7;              /* Latar belakang terang */
        --lite-matte-blue: #d5e6f8;             /* Latar keseluruhan – biru matte cerah */
        --lite-matte-blue-2: #c9d9ec;           /* Latar section/gradient */
        --sidebar-blue: #1f3553;                /* Sidebar matte (biru gelap lembut) */
        --sidebar-blue-2: #3e71b3;              /* Sidebar hover */
        --sidebar-blue-3: #2c4a7a;              /* Sidebar active */
        --brand-blue: #1e7bff;                  /* Biru utama */
        --brand-blue-2: #4c97ff;                /* Biru hover/gradient */
            
        --text-main: #2d3c4e;                   /* Teks utama */
        --text-soft: #50627a;                   /* Teks lembut */
        --text-dim: #7488a3;                    /* Secondary text */
        --text-dim-1: #bcc1ca;                  /* Teks tajuk/label */
        --text-dark-blue: #0c3864;                   /* Teks gelap untuk kontras */
        --text-lite-blue: #599eff;              /* Teks terang untuk dark mode */
            
        --card-bg: #ecf5ff;                     /* Card putih matte */
        --card-border: #d0d9e6;                 /* Border halus */
            
        --input-bg: #f1f6fc;                    /* Input matte blue */
        --input-border: #c7d4e5;
      }

      .text-lite {
        color: var(--text-lite-blue) !important;
      }

      body,
      .content-wrapper,
      .main-footer,
      .main-sidebar {
        font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif;
      }

      /* Body background */
      body .dark-mode {
        background: gray;
        color: var(--text-dim);
      }

      body .lite-mode {
        /* background: var(--lite-matte-blue); */
        color: var(--text-main);
      }

      .main-header {
        background:#0d2236;
        border-bottom:1px solid #1b3c5e;
      }
      .user-info {
        font-size: .8rem !important;
      }
      .main-sidebar {
        background-color: #495057; /* Biru gelap kusam untuk sidebar */
      }
      .content-wrapper {
        background-color: #f8f9fa; /* Kelabu cerah untuk main content */
      }
      .card {
        background-color: #e7f3ff; /* Biru cerah malap untuk card */
      }
      .card-header {
        background-color: #cfe2ff; /* Biru cerah untuk header card */
        color: #343a40; /* Warna teks untuk header card */
      }

      .nav-link {
        color: #ffffff; /* Putih untuk teks navigasi */
        font-size: .8rem; /* Sedikit lebih kecil untuk navigasi */
      }
      .nav-sidebar .nav-link.active {
        background-color: var(--sidebar-blue-3); /* Warna aktif untuk navigasi */
        color: #ffffff; /* Tetap putih saat aktif */
      }
      .nav-sidebar .nav-link:hover {
        background-color: var(--sidebar-blue-2); /* Warna hover untuk navigasi */
        color: #ffffff; /* Tetap putih saat hover */
      }

      .nav-header {
        color: #adb5bd; /* Warna abu-abu untuk header navigasi */
      }

      .dataTables_wrapper .dataTables_paginate .paginate_button {
        color: #343a40; /* Warna teks untuk pagination */
      }
      .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background-color: #343a40; /* Warna latar untuk halaman aktif */
        color: #ffffff; /* Warna teks untuk halaman aktif */
      }

      /* Table header styling */
      table thead th {
        background-color: #5178a1; /* Warna latar untuk header tabel */
        color: #ffffff !important; /* Warna teks untuk header tabel */
      }
      table tbody tr {
        background-color: #dfefff !important; /* Warna latar saat hover pada baris tabel */
      }

      /* Sidebar */
      .main-sidebar {
        background: var(--sidebar-blue) !important;
        color: #fff;
      }

      .nav-sidebar .nav-link {
        color: #cdd7e5 !important;
      }
      .nav-sidebar .nav-link.active {
        background-color: var(--sidebar-blue-3) !important;
        color: #fff !important;
      }
      .nav-sidebar .nav-link:hover {
        background-color: var(--sidebar-blue-2) !important;
        color: #fff !important;
      }
      .nav-header {
        color: #e4eaf2 !important;
      }
        

      /* Brand Link */
      .brand-link {
        background: var(--sidebar-blue);
        color: #fff !important;
        border-bottom: 1px solid #1f2c3b;
      }

      /* Page content background */
      .content-wrapper {
        /* background: linear-gradient(180deg, var(--lite-matte-blue) 0%, var(--lite-matte-blue-2) 100%); */
        background: var(--lite-gray);
      }

      /* Card style */
      .card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        color: var(--text-main);
      }
      .card .card-title {
        color: var(--text-dark-blue);
        font-weight: 600;
      }

      .card .lite-blue {
        background: var(--lite-matte-blue);
        color: var(--text-dark-blue);
      }

      /* Small Box */
      .small-box {
        border: 1px solid var(--card-border);
        color: #fff !important;
      }
      .small-box.bg-info {
        background: linear-gradient(45deg, var(--brand-blue), var(--brand-blue-2));
      }
      .small-box.bg-warning {
        background: linear-gradient(45deg, #f3b316, #ffce4e);
      }
      .small-box.bg-success {
        background: linear-gradient(45deg, #2ecc71, #52e08a);
      }
      .small-box.bg-danger {
        background: linear-gradient(45deg, #e74c3c, #ff6654);
      }

      /* Table */
      .table thead th {
        color: var(--text-main);
        border-bottom: 1px solid var(--card-border);
      }
      .table tbody td {
        color: var(--text-soft);
      }

      /* Buttons */
      .btn-primary {
        background-color: var(--brand-blue);
        border-color: var(--brand-blue-2);
      }
      .btn-outline-primary {
        border-color: var(--brand-blue);
        color: var(--brand-blue);
      }
      .btn-outline-primary:hover {
        background-color: var(--brand-blue);
        color: #fff;
      }

      /* Form controls */
      .form-control,
      .custom-select {
        background: var(--input-bg);
        border-color: var(--input-border);
        color: var(--text-main);
      }
      .form-control::placeholder {
        color: var(--text-dim);
      }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed lite-mode">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-dark">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="#" class="nav-link">Dashboard</a>
      </li>
    </ul>

    <ul class="navbar-nav ml-auto">
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-bell"></i>
          <span id="notif-count" class="badge badge-warning navbar-badge">0</span>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <span class="dropdown-item dropdown-header">Notifikasi</span>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">Tiada notifikasi baharu</a>
        </div>
      </li>

      <li class="nav-item dropdown user-menu">
        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
          <img src="../../images/user.png" class="user-image img-circle elevation-2" alt="User Image">
          <!-- <span class="d-none d-md-inline"><?= htmlspecialchars($namaPaparan, ENT_QUOTES, 'UTF-8') ?></span> -->
        </a>
        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <li class="user-header">
            <img src="../../images/user.png" class="img-circle elevation-2" alt="User Image">
            <p class="user-info">
              <strong><?= htmlspecialchars($namaPaparan, ENT_QUOTES, 'UTF-8') ?></strong><br>
              <?= htmlspecialchars($rolePaparan, ENT_QUOTES, 'UTF-8') ?><br>
              <small><?= htmlspecialchars($emelPaparan, ENT_QUOTES, 'UTF-8') ?></small>
            </p>
          </li>
          <li class="user-footer">
            <a href="#" class="btn btn-default btn-flat">Profil</a>
            <a href="../login/logout.php" class="btn btn-default btn-flat float-right">Log Keluar</a>
          </li>
        </ul>
      </li>
    </ul>
  </nav>
  <!-- /.navbar -->

  <!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="#" class="brand-link">
      <i class="fas fa-print mr-2"></i>
      <span class="brand-text">eCetak</span>
    </a>

    <div class="sidebar">
      <!-- Sidebar user panel-->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="../../images/user.png" class="img-circle elevation-2" alt="User Image">
        </div>
      </div>

      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" role="menu" data-accordion="false">
          <li class="nav-item">
            <a href="index.php" class="nav-link active">
              <i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p>
            </a>
          </li>
          <li class="nav-header">PERMOHONAN</li>
          <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon fas fa-inbox"></i><p>Semua Permohonan</p></a></li>
          <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon fas fa-user-check"></i><p>Menunggu Ketua Jabatan</p></a></li>
          <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon fas fa-university"></i><p>Menunggu Ketua Perpustakaan</p></a></li>
          <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon fas fa-gavel"></i><p>Menunggu Rektor</p></a></li>

          <li class="nav-header">PENGGUNA & PERANAN</li>
          <li class="nav-item"><a href="pengguna.php" class="nav-link"><i class="nav-icon fas fa-users-cog"></i><p>Pengurusan Pengguna</p></a></li>
          <li class="nav-item"><a href="anjuran.php" class="nav-link"><i class="nav-icon fas fa-calendar-alt"></i><p>Penganjur</p></a></li>
          <li class="nav-item"><a href="log_audit.php" class="nav-link"><i class="nav-icon fas fa-clipboard-list"></i><p>Log & Audit</p></a></li>
          <li class="nav-item"><a href="#section-settings" class="nav-link"><i class="nav-icon fas fa-cog"></i><p>Tetapan</p></a></li>
          <li class="nav-item"><a href="../login/logout.php" class="nav-link"><i class="nav-icon fas fa-sign-out-alt"></i><p>Log Keluar</p></a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <!-- Content Wrapper -->
  <div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">Dashboard Pentadbir</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Utama</a></li>
              <li class="breadcrumb-item active">Dashboard</li>
            </ol>
          </div>
        </div>
        <?php if (!$DB_OK): ?>
          <div class="alert alert-warning mb-0">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <strong>Amaran:</strong> Sambungan pangkalan data tidak tersedia. Paparan statistik menggunakan nilai kosong.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <!-- Stat cards -->
        <div class="row">
          <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
              <div class="inner">
                <h3><?= number_format($kira_baru_24h) ?></h3>
                <p>Permohonan Baharu (24 jam)</p>
              </div>
              <div class="icon"><i class="fas fa-bolt"></i></div>
              <a href="#" class="small-box-footer">Butiran <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
              <div class="inner">
                <h3><?= number_format($kira_menunggu_kj) ?></h3>
                <p>Menunggu Ketua Jabatan</p>
              </div>
              <div class="icon"><i class="fas fa-user-check"></i></div>
              <a href="#" class="small-box-footer">Butiran <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
              <div class="inner">
                <h3><?= number_format($kira_menunggu_kp) ?></h3>
                <p>Menunggu Ketua Perpustakaan</p>
              </div>
              <div class="icon"><i class="fas fa-university"></i></div>
              <a href="#" class="small-box-footer">Butiran <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
              <div class="inner">
                <h3><?= number_format($kira_menunggu_rektor) ?></h3>
                <p>Menunggu Rektor</p>
              </div>
              <div class="icon"><i class="fas fa-gavel"></i></div>
              <a href="#" class="small-box-footer">Butiran <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
        </div>

        <!-- Graf & Tugas -->
        <div class="row">
          <div class="col-lg-6">
            <div class="card dark-mode">
              <div class="card-header border-0">
                <h3 class="card-title text-lite"><i class="fas fa-chart-pie mr-2"></i> Agihan Status Permohonan</h3>
              </div>
              <div class="card-body">
                <canvas id="pieStatus" height="220" aria-label="Carta Status Permohonan"></canvas>
                <p class="text-muted mt-2 mb-0"><small>Agihan status semasa.</small></p>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="card lite-blue">
              <div class="card-header border-0">
                <h3 class="card-title"><i class="fas fa-tasks mr-2"></i> Tugas Terkini</h3>
                <div class="card-tools">
                  <button class="btn btn-sm btn-outline-primary" id="btnRefreshTasks"><i class="fas fa-sync-alt"></i> Segar Semula</button>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped mb-0" id="tblRecent">
                    <thead>
                      <tr>
                        <th>#</th>
                        <!-- <th>Rujukan</th> -->
                        <th>Staf</th>
                        <th>Bahagian / Fakulti</th>
                        <th>Status</th>
                        <th>Dikemaskini</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($recent)): ?>
                      <?php foreach ($recent as $i => $r): ?>
                        <tr>
                          <td><?= $i+1 ?></td>
                          <!-- <td><?= htmlspecialchars($r['no_rujukan'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td> -->
                          <td><?= htmlspecialchars(($r['no_pekerja'] ?? '—').' ('.($r['nama'] ?? '—').')', ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars($r['bah_fak'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                          <td>
                            <?php
                              $s = (string)($r['status'] ?? '—');
                              $badge = 'secondary';
                              if (strpos($s,'MENUNGGU')===0) $badge='warning';
                              elseif (strpos($s,'DILULUSKAN')===0) $badge='success';
                              elseif ($s==='DITOLAK') $badge='danger';
                            ?>
                            <span class="badge badge-<?= $badge ?>"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></span>
                          </td>
                          <td><?= htmlspecialchars($r['dikemaskini_pada'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="5" class="text-center text-muted">Tiada data untuk dipaparkan.</td></tr>
                    <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="card-footer text-right">
                <a href="#" class="btn btn-sm btn-outline-primary">Lihat Semua Permohonan</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Pengurusan Pengguna & Peranan -->
        <div class="row" id="section-users">
          <div class="col-12">
            <div class="card">
              <div class="card-header border-0 d-flex align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-users-cog mr-2"></i> Pengurusan Pengguna & Peranan</h3>
                <div class="ml-auto">
                  <!-- <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalAddUser"><i class="fas fa-user-plus"></i> Tambah Pengguna</button> -->
                   <?php if (file_exists(__DIR__ . '/users_add.php')): ?>
                     <a href="users_add.php" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Tambah Pengguna</a>
                   <?php else: ?>
                     <button class="btn btn-primary btn-sm" disabled><i class="fas fa-user-plus"></i> Tambah Pengguna</button>
                   <?php endif; ?>
                  <button class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#modalAssignRole"><i class="fas fa-id-badge"></i> Tetapkan Peranan</button>
                </div>
              </div>
              <div class="card-body">
                <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                  <?php $first=true; foreach ($users_by_role as $role => $list): ?>
                  <li class="nav-item">
                    <a class="nav-link <?= $first?'active':'' ?>" id="pills-<?= md5($role) ?>-tab" data-toggle="pill" href="#pills-<?= md5($role) ?>" role="tab">
                      <i class="fas fa-user-shield mr-1"></i><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>
                      <span class="badge badge-info ml-1"><?= count($list) ?></span>
                    </a>
                  </li>
                  <?php $first=false; endforeach; ?>
                </ul>
                <div class="tab-content" id="pills-tabContent">
                  <?php $first=true; foreach ($users_by_role as $role => $list): ?>
                  <div class="tab-pane fade show <?= $first?'active':'' ?>" id="pills-<?= md5($role) ?>" role="tabpanel">
                    <div class="table-responsive">
                      <table class="table table-hover table-striped dt-role" style="width:100%">
                        <thead>
                          <tr>
                            <th>#</th>
                            <th>No. Pekerja</th>
                            <th>Nama</th>
                            <th>Emel</th>
                            <th>Bah/Fak</th>
                            <th>Status</th>
                            <th>Tindakan</th>
                          </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($list)): ?>
                          <?php foreach ($list as $i => $u): ?>
                          <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($u['no_pekerja'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($u['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($u['emel'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($u['bah_fak'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                              <?php $aktif = (int)($u['aktif'] ?? 0) === 1; ?>
                              <span class="badge badge-<?= $aktif?'success':'secondary' ?>"><?= $aktif?'AKTIF':'NYAHAKTIF' ?></span>
                            </td>
                            <td>
                              <button class="btn btn-sm btn-outline-primary btn-assign" data-user-id="<?= (int)$u['id'] ?>" data-role="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>" title="Tukar Peranan">
                                <i class="fas fa-exchange-alt"></i>
                              </button>
                              <button class="btn btn-sm btn-outline-danger btn-deactivate" data-user-id="<?= (int)$u['id'] ?>" title="Nyahaktifkan">
                                <i class="fas fa-user-slash"></i>
                              </button>
                            </td>
                          </tr>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <tr><td colspan="6" class="text-center text-muted">Tiada pengguna dalam peranan ini.</td></tr>
                        <?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                  <?php $first=false; endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Log & Audit -->
        <div class="row" id="section-logs">
          <div class="col-12">
            <div class="card">
              <div class="card-header border-0 d-flex align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-clipboard-list mr-2"></i> Log & Audit (Ringkas)</h3>
                <div class="ml-auto">
                  <a href="#" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i> Eksport</a>
                </div>
              </div>
              <div class="card-body">
                <p class="text-muted mb-2"><small>Log keselamatan terkini (30 baris terakhir).</small></p>
                <pre class="p-3" style="background:#0b1e31;border:1px solid #1b3c5e; color:#a7c4e2; max-height:220px; overflow:auto;"><?php
                  $log_file = __DIR__ . '/../../logs/security.log';
                  if (file_exists($log_file) && is_readable($log_file)) {
                      try {
                          // Baca fail dan ambil 30 baris terakhir
                          $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                          if ($lines !== false && count($lines) > 0) {
                              $last_lines = array_slice($lines, -100);
                              echo htmlspecialchars(implode("\n", $last_lines), ENT_QUOTES, 'UTF-8');
                          } else {
                              echo "Log kosong atau tiada data.";
                          }
                      } catch (Throwable $e) {
                          echo "Ralat membaca fail log: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                      }
                  } else {
                      echo "Fail log tidak wujud atau tidak boleh dibaca.\nLokasi: " . htmlspecialchars($log_file, ENT_QUOTES, 'UTF-8');
                  }
                ?></pre>
              </div>
            </div>
          </div>
        </div>

        <!-- Tetapan -->
        <div class="row" id="section-settings">
          <div class="col-lg-6">
            <div class="card">
              <div class="card-header border-0">
                <h3 class="card-title"><i class="fas fa-cog mr-2"></i> Tetapan Umum</h3>
              </div>
              <div class="card-body">
                <form id="formSettings" action="#" method="post" novalidate>
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                  <div class="form-group">
                    <label>Mod Gelap</label>
                    <select class="custom-select" id="optDarkMode">
                      <option value="on" selected>Hidup</option>
                      <option value="off">Mati</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Auto Refresh Dashboard</label>
                    <select class="custom-select" id="optAutoRefresh">
                      <option value="0">Mati</option>
                      <option value="30">30 saat</option>
                      <option value="60" selected>60 saat</option>
                      <option value="120">120 saat</option>
                    </select>
                  </div>
                  <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </form>
                <p class="text-muted mt-2 mb-0"><small>Nota: Simpan ke stor klien dahulu (localStorage). Sambung ke API tetapan kemudian.</small></p>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /.container-fluid -->
    </section>
  </div>

  <!-- Footer -->
  <footer class="main-footer" style="background:#0d2236;border-top:1px solid #1b3c5e;">
    <strong>&copy; <?= date('Y') ?> eCetak</strong>
    <div class="float-right d-none d-sm-inline-block">Perpustakaan Sultan Badlishah</div>
  </footer>
</div>

<!-- Modals -->

<!-- Tambah Pengguna -->
<div class="modal fade" id="modalAddUser" tabindex="-1" role="dialog" aria-labelledby="modalAddUserLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form action="actions/manage_user.php" method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
      <div class="modal-header">
        <h5 class="modal-title" id="modalAddUserLabel"><i class="fas fa-user-plus mr-2"></i> Tambah Pengguna</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>No. Pekerja</label>
            <input type="text" class="form-control" name="no_pekerja" pattern="\d{6}" required placeholder="6 digit">
          </div>
          <div class="form-group col-md-4">
            <label>Emel</label>
            <input type="email" class="form-control" name="emel" required placeholder="nama@domain">
          </div>
          <div class="form-group col-md-4">
            <label>Bah/Fak</label>
            <input type="text" class="form-control" name="bah_fak" placeholder="Bahagian/Fakulti">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Peranan</label>
            <select class="custom-select" name="role" required>
              <option value="PENYELIA">PENYELIA</option>
              <option value="KETUA JABATAN">KETUA JABATAN</option>
              <option value="KETUA PERPUSTAKAAN">KETUA PERPUSTAKAAN</option>
              <option value="REKTOR">REKTOR</option>
              <option value="PENTADBIR">PENTADBIR</option>
            </select>
          </div>
          <div class="form-group col-md-6">
            <label>Status</label>
            <select class="custom-select" name="aktif">
              <option value="1" selected>AKTIF</option>
              <option value="0">NYAHAKTIF</option>
            </select>
          </div>
        </div>
        <p class="text-muted mb-0"><small>Katalaluan awal ikut polisi (contoh: sementara & paksa reset).</small></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Tetapkan Peranan -->
<div class="modal fade" id="modalAssignRole" tabindex="-1" role="dialog" aria-labelledby="modalAssignRoleLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form action="actions/roles.php" method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="user_id" id="assignUserId" value="">
      <div class="modal-header">
        <h5 class="modal-title" id="modalAssignRoleLabel"><i class="fas fa-id-badge mr-2"></i> Tetapkan Peranan</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Peranan</label>
          <select class="custom-select" name="role" required>
            <option value="PENYELIA">PENYELIA</option>
            <option value="KETUA JABATAN">KETUA JABATAN</option>
            <option value="KETUA PERPUSTAKAAN">KETUA PERPUSTAKAAN</option>
            <option value="REKTOR">REKTOR</option>
            <option value="PENTADBIR">PENTADBIR</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- JS: jQuery, Bootstrap, AdminLTE, Chart.js, DataTables -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js" integrity="sha512-pumBsjNRGGqkPzKHndZMaAG+bir374sORyzM3uulLV14lN5LyykqNk8eEeUlUkB3U0M4FApyaHraT65ihJhDpQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/js/adminlte.min.js" integrity="sha512-KCgUnf/JiKrDFOqBnlGaw2KxEVr4WyCVw/G1EABDQv1mJKbXDphEnJG2IlTRKGVlW/LKymWk4GVSN2jN+A0E2A==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js" integrity="sha512-ElRFoEQdI5Ht6kZvyzXhYG9NqjtkmlkfYk0wr6wHxU9JEHakS7UJZNeml5ALk+8IKlU6jDgMabC3vkumRokgJA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdn.datatables.net/1.13.10/js/jquery.dataTables.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.10/js/dataTables.bootstrap4.min.js" crossorigin="anonymous"></script>

<script>
(function(){
  'use strict';

  // Data carta dari PHP
  const statusData = {
    MENUNGGU_KETUA_JABATAN: <?= (int)$agih_status['MENUNGGU_KETUA_JABATAN'] ?>,
    MENUNGGU_KETUA_PERPUSTAKAAN: <?= (int)$agih_status['MENUNGGU_KETUA_PERPUSTAKAAN'] ?>,
    MENUNGGU_REKTOR: <?= (int)$agih_status['MENUNGGU_REKTOR'] ?>,
    DILULUSKAN: <?= (int)$agih_status['DILULUSKAN'] ?>,
    DITOLAK: <?= (int)$agih_status['DITOLAK'] ?>
  };

  // DataTables
  $(function(){
    $('.dt-role').DataTable({
      paging: true,
      pageLength: 10,
      lengthChange: false,
      ordering: true,
      order: [[1, 'asc']],
      autoWidth: false,
      language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ms.json' }
    });
  });

  // Carta pai
  const ctx = document.getElementById('pieStatus').getContext('2d');
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: [
        'Menunggu Ketua Jabatan',
        'Menunggu Ketua Perpustakaan',
        'Menunggu Rektor',
        'Diluluskan',
        'Ditolak'
      ],
      datasets: [{
        data: [
          statusData.MENUNGGU_KETUA_JABATAN,
          statusData.MENUNGGU_KETUA_PERPUSTAKAAN,
          statusData.MENUNGGU_REKTOR,
          statusData.DILULUSKAN,
          statusData.DITOLAK
        ],
        backgroundColor: ['#f1c40f','#3498db','#9b59b6','#2ecc71','#e74c3c'],
        borderWidth: 1,
        borderColor: '#0b1e31'
      }]
    },
    options: { plugins: { legend: { labels: { color: '#eaf2ff' } } } }
  });

  // Tetapan klien
  const REFRESH_KEY = 'ecetak_refresh';
  const saved = localStorage.getItem(REFRESH_KEY) || '60';
  if (saved !== '0') {
    setInterval(function(){
      $('#btnRefreshTasks').addClass('disabled');
      setTimeout(()=>$('#btnRefreshTasks').removeClass('disabled'), 700);
    }, parseInt(saved, 10) * 1000);
  }

  $('#formSettings').on('submit', function(e){
    e.preventDefault();
    const dm = $('#optDarkMode').val();
    const ar = $('#optAutoRefresh').val();
    localStorage.setItem(REFRESH_KEY, ar);
    if (dm === 'on') $('body').addClass('dark-mode'); else $('body').removeClass('dark-mode');
    showToast('Tetapan disimpan (klien). Sambungkan ke API pelayan untuk simpan kekal.');
  });

  $('.btn-assign').on('click', function(){
    const uid = $(this).data('user-id');
    $('#assignUserId').val(uid);
    $('#modalAssignRole').modal('show');
  });

  $('.btn-deactivate').on('click', function(){
    const uid = $(this).data('user-id');
    if (!confirm('Nyahaktifkan pengguna ID '+uid+'?')) return;
    showToast('Integrasi API belum disambung. Sila sambungkan ke actions/manage_user.php.');
  });

  function showToast(msg){
    const t = $('<div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="2500" style="position: fixed; top: 10px; right: 10px; z-index: 2000;"><div class="toast-body">'+msg+'</div></div>');
    $('body').append(t);
    t.toast('show');
    t.on('hidden.bs.toast', function(){ $(this).remove(); });
  }

})();
</script>
</body>
</html>