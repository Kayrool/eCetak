<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();
require_once 'auth_guard.php';

// Fungsi untuk menampilkan SweetAlert dan redirect
function sweetAlertRedirect($title, $text, $icon, $redirectUrl) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js'></script>";
    echo "<script>
        Swal.fire({
            title: " . json_encode($title) . ",
            text: " . json_encode($text) . ",
            icon: " . json_encode($icon) . ",
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = " . json_encode($redirectUrl) . ";
            }
        });
    </script>";
    exit;
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

if ($pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT 1");
    } catch (PDOException $e) {
        die("Sambungan ke pangkalan data gagal: " . $e->getMessage());
    }
} else {
    die("Sambungan ke pangkalan data gagal: PDO tidak dimulai.");
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
        die("Sambungan ke pangkalan data idata gagal: " . $e->getMessage());
    }
} else {
    die("Sambungan ke pangkalan data idata gagal: PDO tidak terinitialize");
}
// Fetch no_pekerja dan nama, role dan emel dari session
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
            sweetAlertRedirect('Ralat', 'Gagal mengambil nama pengguna.', 'error', 'pengguna.php');
        }
    } catch (PDOException $e) {
        sweetAlertRedirect('Ralat', 'Gagal mengambil nama pengguna: ' . $e->getMessage(), 'error', 'pengguna.php');
    }
}

// Fetch users grouped by role
$users_by_role = [];
try {
    $stmt = $pdo->query("SELECT id, no_pekerja, nama, emel, bah_fak, role, aktif FROM users ORDER BY nama ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $r = $row['role'] ?? 'Tanpa Peranan';
        if (!isset($users_by_role[$r])) {
            $users_by_role[$r] = [];
        }
        $users_by_role[$r][] = $row;
    }
} catch (PDOException $e) {
    sweetAlertRedirect('Ralat', 'Gagal mengambil data pengguna: ' . $e->getMessage(), 'error', 'pengguna.php');
}

// Fungsi untuk mengubah peranan pengguna (akan dipanggil oleh AJAX)
function changeUserRole($userId, $newRole) {
    global $pdo;
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
            $stmt->execute([':role' => $newRole, ':id' => $userId]);
            if ($stmt) {
                sweetAlertRedirect('Berjaya', 'Peranan pengguna telah dikemaskini.', 'success', 'pengguna.php');
            } else {
                sweetAlertRedirect('Ralat', 'Gagal mengemaskini peranan pengguna.', 'error', 'pengguna.php');
            }
        } catch (PDOException $e) {
            sweetAlertRedirect('Ralat', 'Gagal mengemaskini peranan pengguna: ' . $e->getMessage(), 'error', 'pengguna.php');
        }
    }
}

// roles_enum.php — include di page yang render modal
$enum_roles = [];

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    $type = $col['Type'] ?? ''; // contoh: "enum('admin','staff','user')"

    if (preg_match("/^enum\((.*)\)$/i", $type, $m)) {
        // Parse nilai enum. str_getcsv jaga quote & koma.
        $enum_roles = str_getcsv($m[1], ',', "'");
    }
} catch (PDOException $e) {
    // fallback kalau gagal—jangan biar kosong
    $enum_roles = ['PENTADBIR','REKTOR','KETUA PERPUSTAKAAN','KETUA BAHAGIAN','PENYELIA'];
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css">

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
            background-color: var(--sidebar-blue-3) !important; /* Warna aktif untuk navigasi */
            color: #ffffff !important; /* Tetap putih saat aktif */
        }
        .nav-sidebar .nav-link:hover {
            background-color: var(--sidebar-blue-2) !important; /* Warna hover untuk navigasi */
            color: #ffffff !important; /* Tetap putih saat hover */
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
                <li class="nav-item"><a href="" class="nav-link active"><i class="nav-icon fas fa-users-cog"></i><p>Pengurusan Pengguna</p></a></li>
                <li class="nav-item"><a href="anjuran.php" class="nav-link"><i class="nav-icon fas fa-calendar-alt"></i><p>Penganjur</p></a></li>
                <li class="nav-item"><a href="log_audit.php" class="nav-link"><i class="nav-icon fas fa-clipboard-list"></i><p>Log & Audit</p></a></li>
                <li class="nav-item"><a href="#section-settings" class="nav-link"><i class="nav-icon fas fa-cog"></i><p>Tetapan</p></a></li>
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
                    <div class="col-sm-6"><h1 class="m-0">Pengurusan Pengguna</h1></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="#">Utama</a></li>
                            <li class="breadcrumb-item active">Pengurusan Pengguna</li>
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

        <!-- Pengurusan Pengguna & Peranan -->
        <div class="content" id="section-users">
            <div class="container-fluid">
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
                    </div>
                    </div>
                    <div class="card-body">
                    <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                        <?php $first=true; foreach ($users_by_role as $role => $list): ?>
                        <li class="nav-item">
                        <a class="nav-link <?= $first?'active':'' ?>" 
                            id="pills-<?= md5($role) ?>-tab" 
                            data-toggle="pill" 
                            href="#pills-<?= md5($role) ?>" 
                            role="tab"
                            data-role="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fas fa-user-shield mr-1"></i><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>
                            <span class="badge badge-info ml-1"><?= count($list) ?></span>
                        </a>
                        </li>
                        <?php $first=false; endforeach; ?>
                    </ul>
                    <div class="tab-content" id="pills-tabContent">
                        <?php $first=true; foreach ($users_by_role as $role => $list): ?>
                        <div class="tab-pane fade show <?= $first?'active':'' ?>" id="pills-<?= md5($role) ?>" role="tabpanel" data-role="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>">                           
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
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary btn-assign"
                                                    data-user-id="<?= (int)$u['id'] ?>"
                                                    data-user-name="<?= htmlspecialchars($u['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    data-user-role="<?= htmlspecialchars($u['role'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    title="Tukar Peranan">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm <?= $aktif ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-deactivate"
                                                    data-user-id="<?= (int)$u['id'] ?>"
                                                    data-user-name="<?= htmlspecialchars($u['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    data-aktif="<?= $aktif ? '1' : '0' ?>"
                                                    title="<?= $aktif ? 'Nyahaktifkan' : 'Aktifkan' ?>">
                                                <i class="fas <?= $aktif ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-dark btn-delete-user" data-user-id="<?= (int)$u['id'] ?>" data-user-name="<?= htmlspecialchars($u['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>" title="Padam Pengguna">
                                                <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center text-muted">Tiada pengguna dalam peranan ini.</td></tr>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>

<!-- Custom JS -->

<script>
$(function(){

  // Klik butang "Tetapkan Peranan" pada mana-mana row
  $(document).on('click', '.btn-assign', function(){
    const $btn  = $(this);
    const id    = $btn.data('user-id');
    const name  = $btn.data('user-name');
    const role  = $btn.data('user-role');

    // Prefill modal
    $('#m_id').val(id);
    $('#m_name').val(name);
    $('#m_role').val(role);

    // Simpan context row (supaya senang update UI lepas berjaya)
    $('#assignRoleForm').data('rowEl', $btn.closest('tr'));

    $('#modalAssignRole').modal('show');
  });

  // Submit AJAX
  $('#assignRoleForm').on('submit', function(e){
    e.preventDefault();

    const $form = $(this);
    const $btn  = $('#btnSaveRole');
    const $spin = $btn.find('.spin');
    const $fb   = $('#assignRoleFeedback');

    $fb.hide().text('');
    $btn.prop('disabled', true);
    $spin.removeClass('d-none');

    $.ajax({
      url: $form.attr('action'),
      method: 'POST',
      data: $form.serialize(), // hantar sebagai x-www-form-urlencoded
      dataType: 'json'
    })
    .done(function(res){
      if (!res || !res.ok) throw new Error(res?.message || 'Ralat tidak diketahui');

      // Update UI pada row semasa
      const $row = $form.data('rowEl');
      const newRole = res.role;

      // 1) Kemaskini paparan role pada cell "Role" jika ada (kau boleh cari cell spesifik)
      //   Dalam table kau, tiada kolum "Role" dipaparkan sebab grouping ikut tab.
      //   Jadi kita akan (pilihan):
      //   - Pindahkan row ke tab yang sepadan (disyorkan), atau
      //   - Reload (paling mudah).
      moveRowToRoleTab($row, newRole);

      // Tutup modal
      $('#modalAssignRole').modal('hide');

      // Optional: paparkan toast/alert ringkas
      // alert('Role dikemaskini kepada: ' + newRole);
    })
    .fail(function(xhr){
      let msg = 'Gagal mengemaskini peranan.';
      if (xhr.responseJSON?.message) msg = xhr.responseJSON.message;
      $fb.text(msg).show();
    })
    .always(function(){
      $btn.prop('disabled', false);
      $spin.addClass('d-none');
    });
  });

  // Pindah row ke tab role yang baru (supaya UI konsisten dgn grouping)
  function moveRowToRoleTab($row, newRole){
    // Cari tab-pane yg ada data-role = newRole
    const $targetPane = $('.tab-pane[data-role]').filter(function(){
      return $(this).data('role') == newRole;
    }).first();

    if ($targetPane.length) {
      const $tbody = $targetPane.find('table tbody');
      if ($tbody.length) {
        // Remove event context supaya fresh
        // (tak perlu detach handler sebab kita guna delegation)
        $row.fadeOut(150, function(){
          $row.appendTo($tbody).fadeIn(150);
        });

        // Update data-user-role pada butang supaya next open guna nilai baru
        const $btn = $row.find('.btn-assign').first();
        $btn.data('user-role', newRole);

        // Update badge kiraan pada tab nav (optional)
        updateRoleTabBadges();
      } else {
        // fallback: kalau tak jumpa table target
        location.reload();
      }
    } else {
      // fallback: kalau tab role tak wujud (contoh role baru muncul)
      location.reload();
    }
  }

  // Kira semula bilangan dalam setiap tab (badge)
  function updateRoleTabBadges(){
    // Data-role pada tab-pane, cari nav-link yang menyebut role sama.
    // Cara paling tepat: set data-role juga pada <a.nav-link>. (Tambahkan pada server-side)
    // Jika tak, skip fungsi ni atau implement mapping sendiri.
    // Contoh jika kau tambah data-role pada nav-link:
    $('[data-toggle="pill"][data-role]').each(function(){
      const role = $(this).data('role');
      const $pane = $('.tab-pane[data-role="'+ role +'"]');
      const count = $pane.find('tbody tr').length;
      $(this).find('.badge').text(count);
    });
  }

    function refreshRowNumbers($pane){
        $pane.find('tbody tr').each(function(index){
            $(this).find('td').first().text(index + 1);
        });
    }

    // Toggle aktif/nyahaktif pengguna
    $(document).on('click', '.btn-deactivate', function(){
        const $btn = $(this);
        const userId = Number($btn.data('user-id') || 0);
        const userName = String($btn.data('user-name') || '');
        const isActiveNow = String($btn.data('aktif') || '0') === '1';

        if (!userId) {
            Swal.fire('Ralat', 'ID pengguna tidak sah.', 'error');
            return;
        }

        const actionText = isActiveNow ? 'nyahaktifkan' : 'aktifkan';
        Swal.fire({
            title: 'Pengesahan',
            text: 'Anda pasti mahu ' + actionText + ' pengguna ' + (userName || ('ID ' + userId)) + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, teruskan',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if (!result.isConfirmed) return;

            $btn.prop('disabled', true);

            $.ajax({
                url: 'deactivate_user.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    id: userId,
                    csrf: $('input[name="csrf"]').first().val() || ''
                }
            })
            .done(function(res){
                if (!res || !res.ok) {
                    throw new Error((res && res.message) ? res.message : 'Gagal mengemas kini status pengguna.');
                }

                const $row = $btn.closest('tr');
                const nowActive = Number(res.aktif || 0) === 1;

                $row.find('span.badge')
                    .removeClass('badge-success badge-secondary')
                    .addClass(nowActive ? 'badge-success' : 'badge-secondary')
                    .text(nowActive ? 'AKTIF' : 'NYAHAKTIF');

                $btn
                    .data('aktif', nowActive ? '1' : '0')
                    .removeClass('btn-outline-danger btn-outline-success')
                    .addClass(nowActive ? 'btn-outline-danger' : 'btn-outline-success')
                    .attr('title', nowActive ? 'Nyahaktifkan' : 'Aktifkan');

                $btn.find('i')
                    .removeClass('fa-user-slash fa-user-check')
                    .addClass(nowActive ? 'fa-user-slash' : 'fa-user-check');

                Swal.fire('Berjaya', res.message || 'Status pengguna berjaya dikemaskini.', 'success');
            })
            .fail(function(xhr){
                let msg = 'Gagal mengemas kini status pengguna.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                Swal.fire('Ralat', msg, 'error');
            })
            .always(function(){
                $btn.prop('disabled', false);
            });
        });
    });

    // Padam pengguna
    $(document).on('click', '.btn-delete-user', function(){
        const $btn = $(this);
        const userId = Number($btn.data('user-id') || 0);
        const userName = String($btn.data('user-name') || '');

        if (!userId) {
            Swal.fire('Ralat', 'ID pengguna tidak sah.', 'error');
            return;
        }

        Swal.fire({
            title: 'Padam Pengguna?',
            text: 'Padam pengguna ' + (userName || ('ID ' + userId)) + '? Tindakan ini tidak boleh diundur.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, padam',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#d33'
        }).then(function(result){
            if (!result.isConfirmed) return;

            $btn.prop('disabled', true);

            $.ajax({
                url: 'delete_user.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    id: userId,
                    csrf: $('input[name="csrf"]').first().val() || ''
                }
            })
            .done(function(res){
                if (!res || !res.ok) {
                    throw new Error((res && res.message) ? res.message : 'Gagal memadam pengguna.');
                }

                const $row = $btn.closest('tr');
                const $pane = $btn.closest('.tab-pane');

                $row.fadeOut(120, function(){
                    $(this).remove();
                    refreshRowNumbers($pane);
                    updateRoleTabBadges();

                    const $tbody = $pane.find('tbody');
                    if ($tbody.find('tr').length === 0) {
                        $tbody.append('<tr><td colspan="7" class="text-center text-muted">Tiada pengguna dalam peranan ini.</td></tr>');
                    }
                });

                Swal.fire('Dipadam', res.message || 'Pengguna berjaya dipadam.', 'success');
            })
            .fail(function(xhr){
                let msg = 'Gagal memadam pengguna.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                Swal.fire('Ralat', msg, 'error');
                $btn.prop('disabled', false);
            });
        });
    });

});
</script>


<!-- Modal untuk tukar peranan pengguna dimana user id diambil dari button btnAssignRole -->
<div class="modal fade" id="modalAssignRole" tabindex="-1" role="dialog" aria-labelledby="assignRoleTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="formAssignRole" action="update_role.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignRoleTitle">Tetapkan Peranan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <!-- CSRF -->
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                    <input type="hidden" name="id" id="m_id">

                    <div class="form-group">
                    <label>Nama</label>
                    <input type="text" class="form-control" id="m_name" readonly>
                    </div>

                    <div class="form-group">
                    <label for="m_role">Peranan</label>
                    <select name="role" id="m_role" class="form-control" required>
                        <option value="">— pilih peranan —</option>
                        <?php foreach ($enum_roles as $opt): ?>
                        <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(ucfirst($opt), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    </div>

                    <div id="assignRoleFeedback" class="small text-danger" style="display:none;"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveRole">
                    <span class="spin d-none spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>
                    Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>