<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();
require_once 'auth_guard.php';
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

if ($pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT 1");
    } catch (PDOException $e) {
        die("Koneksi database gagal: " . $e->getMessage());
    }
} else {
    die("Koneksi database gagal: PDO tidak terinitialize");
}
$pdo_idata = null;
$DB_OK_idata = false;
try {
    $db_idata_config_path = __DIR__ . '/../../config/db_idata.php';
    if (file_exists($db_idata_config_path)) {
        require_once $db_idata_config_path; // mesti set $pdo_idata
        if (isset($pdo_idata) && ($pdo_idata instanceof PDO)) {
            $pdo_idata->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $DB_OK_idata = true;
        }
    }
} catch (Throwable $e) {}
if ($pdo_idata !== null) {
    try {
        $stmt = $pdo_idata->query("SELECT 1");
    } catch (PDOException $e) {
        die("Sambungan database idata gagal: " . $e->getMessage());
    }
} else {
    die("Sambungan database idata gagal: PDO tidak terinitialize");
}
// Fetch no_pekerja dan nama dan role dari session
$no_pekerja = $_SESSION['no_pekerja'] ?? $_SESSION['no_pekerja'] ?? '';
$nama = $_SESSION['nama'] ?? $_SESSION['user_nama'] ?? '';
$role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
$emel = $_SESSION['emel'] ?? $_SESSION['user_emel'] ?? '';

if ($nama === '' && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT nama FROM users WHERE no_pekerja = :no_pekerja LIMIT 1");
        if ($stmt !== false) {
            $stmt->execute([':no_pekerja' => $no_pekerja]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result['nama'])) {
                $nama = $result['nama'];
            }
        } else {
            sweetAlertRedirect('Ralat', 'Gagal mengambil nama pengguna.', 'error', 'anjuran.php');
        }
    } catch (PDOException $e) {
        sweetAlertRedirect('Ralat', 'Gagal mengambil nama pengguna: ' . $e->getMessage(), 'error', 'anjuran.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
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

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" integrity="sha256-+lH8Xo9nqj1sQp5Zt0aLh6u+Vv7yYl3b2mLh5kP4w=" crossorigin="anonymous">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.10/css/dataTables.bootstrap4.min.css" crossorigin="anonymous" />
    <!-- Custom CSS -->
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
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <li class="user-header">
                        <img src="../../images/user.png" class="img-circle elevation-2" alt="User Image">
                        <p class="user-info">
                            <strong><?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?></strong><br>
                            <?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?><br>
                            <small><?= htmlspecialchars($emel, ENT_QUOTES, 'UTF-8') ?></small>
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
    <aside class="main-sidebar elevation-4">
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
            <div class="info">
                <a href="#" class="d-block"><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" role="menu" data-accordion="false">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">
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
                <li class="nav-item"><a href="log_audit.php" class="nav-link active"><i class="nav-icon fas fa-clipboard-list"></i><p>Log & Audit</p></a></li>
                <li class="nav-item"><a href="tetapan.php" class="nav-link"><i class="nav-icon fas fa-cog"></i><p>Tetapan</p></a></li>
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
                            <li class="breadcrumb-item"><a href="index.php">Utama</a></li>
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
                <?php if (!$DB_OK_idata): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Amaran:</strong> Sambungan ke database idata gagal. Data berkaitan idata tidak akan dipaparkan.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- /.content-header -->

        <!-- Main content -->

        <!-- Log & Audit -->
        <div class="content">
            <div class="container-fluid">
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
                                <pre class="p-3" style="background:#0b1e31;border:1px solid #1b3c5e; color:#a7c4e2; font-size: 11px; max-height:380px; overflow:auto;"><?php
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
                                    }?>
                                </pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>




        <!-- /.content -->
    </div>
    <!-- Footer -->
    <footer class="main-footer" style="background:#0d2236;border-top:1px solid #1b3c5e;">
        <strong>&copy; <?= date('Y') ?> eCetak</strong>
        <div class="float-right d-none d-sm-inline-block">Perpustakaan Sultan Badlishah</div>
    </footer>
</div>
<!-- ./wrapper -->

<!-- JS: jQuery, Bootstrap, AdminLTE, Chart.js, DataTables -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js" integrity="sha512-pumBsjNRGGqkPzKHndZMaAG+bir374sORyzM3uulLV14lN5LyykqNk8eEeUlUkB3U0M4FApyaHraT65ihJhDpQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/js/adminlte.min.js" integrity="sha512-KCgUnf/JiKrDFOqBnlGaw2KxEVr4WyCVw/G1EABDQv1mJKbXDphEnJG2IlTRKGVlW/LKymWk4GVSN2jN+A0E2A==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js" integrity="sha512-ElRFoEQdI5Ht6kZvyzXhYG9NqjtkmlkfYk0wr6wHxU9JEHakS7UJZNeml5ALk+8IKlU6jDgMabC3vkumRokgJA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdn.datatables.net/1.13.10/js/jquery.dataTables.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.10/js/dataTables.bootstrap4.min.js" crossorigin="anonymous"></script>

<!-- Custom JS -->
<script>
    $(document).ready(function() {
        $('#anjuranTable').DataTable();
    });
</script>
</body>
</html>