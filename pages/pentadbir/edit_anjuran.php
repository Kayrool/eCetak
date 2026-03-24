<?php
declare(strict_types=1);
/**
 * Borang untuk menambah penganjur program baru. Halaman ini akan memproses data yang dihantar dari borang tambah_anjuran.php dan menyimpan maklumat penganjur ke dalam pangkalan data.
 * UI menggunakan Bootsrap 5, AndminLTE, Datatables, SweetAlert2, FontAwesome, jQuery, dan AJAX diambil dari CDN.
 * Design: Topbar dan sidebar untuk navigasi, dengan konten utama menampilkan tabel senarai penganjur program.
 * Warna Tema: Mengikut fail 'anjuran.php' untuk konsistensi dengan halaman senarai penganjur.
 */
session_start();

// Set waktu tempatan ke Kuala Lumpur
date_default_timezone_set('Asia/Kuala_Lumpur');

// Semak jika pengguna sudah log masuk dan user role adalah pentadbir dari fail 'auth_guard.php'
require_once 'auth_guard.php';

// Sambungan ke pangkalan data
require_once '../../config/db.php';
require_once '../../config/db_idata.php';

// Function untuk SweetAlert2 dengan redirect
function sweetAlertRedirect($title, $text, $icon, $redirectUrl) {
    echo "<!DOCTYPE html>
    <html lang='ms'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>" . htmlspecialchars($title) . "</title>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js'></script>
    </head>
    <body>
        <script>
            Swal.fire({
                title: " . json_encode($title, JSON_UNESCAPED_UNICODE) . ",
                text: " . json_encode($text, JSON_UNESCAPED_UNICODE) . ",
                icon: " . json_encode($icon, JSON_UNESCAPED_UNICODE) . ",
                confirmButtonText: 'OK'
            }).then(function() {
                window.location.href = " . json_encode($redirectUrl, JSON_UNESCAPED_SLASHES) . ";
            });
        </script>
    </body>
    </html>";
    exit;
}

// Semak jika $pdo adalah objek PDO yang sah dan uji sambungan ke pangkalan data
if ($pdo instanceof PDO) {
    try {
        $pdo->query('SELECT 1'); // Uji sambungan dengan query sederhana
    } catch (PDOException $e) {
        sweetAlertRedirect('Ralat', 'Sambungan ke pangkalan data gagal: ' . $e->getMessage(), 'error', 'anjuran.php');
    }
} else {
    sweetAlertRedirect('Ralat', 'Sambungan ke pangkalan data tidak valid.', 'error', 'anjuran.php');
}

// Fetch no_pekerja, nama dan role pengguna dari session
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

// Fetch maklumat anjuran dari pangkalan data 'anjuran' berdasarkan 'id' yang dihantar melalui GET. Jika data tidak dijumpai, redirect kembali ke 'anjuran.php' dengan mesej ralat.

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        if (!$pdo instanceof PDO) {
            sweetAlertRedirect('Ralat', 'Sambungan ke pangkalan data tidak sah.', 'error', 'anjuran.php');
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM anjuran WHERE id = :id LIMIT 1");
        if ($stmt !== false) {
            $stmt->execute([':id' => $id]);
            $anjuran = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$anjuran) {
                sweetAlertRedirect('Ralat', 'Penganjur program tidak dijumpai.', 'error', 'anjuran.php');
                exit;
            }
        } else {
            sweetAlertRedirect('Ralat', 'Gagal mengambil maklumat penganjur program.', 'error', 'anjuran.php');
            exit;
        }
    } catch (PDOException $e) {
        sweetAlertRedirect('Ralat', 'Gagal mengambil maklumat penganjur program: ' . $e->getMessage(), 'error', 'anjuran.php');
        exit;
    }
}


// Endpoint carian staf secara masa nyata
if (isset($_GET['query'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $query = trim($_GET['query']);

    if ($query === '' || strlen($query) < 2) {
        echo json_encode([]);
        exit;
    }

    if (!($pdo_idata instanceof PDO)) {
        error_log('Sambungan pangkalan data idata tidak sah');
        echo json_encode([]);
        exit;
    }

    try {
        $likeQuery = '%' . $query . '%';
        $stmt = $pdo_idata->prepare("
            SELECT no_pekerja, nama
            FROM t_staf
            WHERE nama LIKE :query_nama
               OR no_pekerja LIKE :query_no_pekerja
            ORDER BY nama ASC
            LIMIT 10
        ");
        if ($stmt instanceof PDOStatement) {
            $stmt->execute([
                ':query_nama' => $likeQuery,
                ':query_no_pekerja' => $likeQuery
            ]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            echo json_encode([]);
        }
    } catch (PDOException $e) {
        error_log('Carian staf gagal: ' . $e->getMessage());
        echo json_encode([]);
    }
    exit;
}

// Proses data yang dihantar dari borang edit_anjuran.php. Field yang dihantar adalah 'id ', 'nama_penganjur', 'catatan', 'kb_no_pekerja', dan 'dikemaskini_pada'.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $nama_penganjur = $_POST['nama_penganjur'] ?? '';
    $catatan = $_POST['catatan'] ?? '';
    $kb_no_pekerja = $_POST['kb_no_pekerja'] ?? '';
    $dikemaskini_pada = date('Y-m-d H:i:s');

    // Validasi input (contoh: pastikan nama penganjur tidak kosong)
    if (empty($nama_penganjur)) {
        sweetAlertRedirect('Ralat', 'Nama penganjur tidak boleh kosong.', 'error', 'anjuran.php');
        exit;
    }

    // Simpan maklumat penganjur ke dalam pangkalan data
    try {
        if (!$pdo instanceof PDO) {
            sweetAlertRedirect('Ralat', 'Sambungan ke pangkalan data tidak sah.', 'error', 'anjuran.php');
            exit;
        }
        $stmt = $pdo->prepare("UPDATE anjuran SET nama_penganjur = :nama_penganjur, catatan = :catatan, kb_no_pekerja = :kb_no_pekerja, dikemaskini_pada = :dikemaskini_pada WHERE id = :id");
        $stmt->execute([
            ':nama_penganjur' => $nama_penganjur,
            ':catatan' => $catatan,
            ':kb_no_pekerja' => $kb_no_pekerja,
            ':dikemaskini_pada' => $dikemaskini_pada,
            ':id' => $id
        ]);

        sweetAlertRedirect('Berjaya', 'Maklumat penganjur berjaya dikemaskini.', 'success', 'anjuran.php');
    } catch (PDOException $e) {
        sweetAlertRedirect('Ralat', 'Gagal mengemaskini penganjur program: ' . $e->getMessage(), 'error', 'anjuran.php');
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
    <style>
        :root {
            /* === Lite Matte Blue Palette === */
            --lite-gray: #f7f7f7;              /* Latar belakang terang */
            --lite-matte-blue: #d5e6f8;             /* Latar keseluruhan – biru matte cerah */
            --lite-matte-blue-2: #c9d9ec;           /* Latar section/gradient */
            --sidebar-blue: #1f3553;                /* Sidebar matte (biru gelap lembut) */
            --sidebar-blue-2: #3e71b3;              /* Sidebar hover/active */
            --brand-blue: #1e7bff;                  /* Biru utama */
            --brand-blue-2: #4c97ff;                /* Biru hover/gradient */
            
            --text-main: #2d3c4e;                   /* Teks utama */
            --text-soft: #50627a;                   /* Teks lembut */
            --text-dim: #7488a3;                    /* Secondary text */
            --text-dim-1: #bcc1ca;                  /* Teks tajuk/label */
            --text-dark-blue: #0c3864;                   /* Teks gelap untuk kontras */
            --text-lite-blue: #599eff;              /* Teks terang untuk dark mode */
            
            --card-bg: #ffffff;                     /* Card putih matte */
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
        #anjuranTable thead th {
            background-color: #343a40; /* Warna latar untuk header tabel */
            color: #ffffff; /* Warna teks untuk header tabel */
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

        .kb-search-wrap {
            position: relative;
        }

        #kb_dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 1060;
            background: #fff;
            border: 1px solid var(--input-border);
            border-radius: 0.375rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            max-height: 220px;
            overflow-y: auto;
        }

        .kb-item {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eef2f7;
        }

        .kb-item:last-child {
            border-bottom: 0;
        }

        .kb-item:hover {
            background: #eaf3ff;
        }

        .kb-item small {
            color: #62748a;
        }

        .kb-helper {
            margin-top: 6px;
            font-size: 0.875rem;
            color: #62748a;
        }

        #kb_selected_info {
            display: none;
            margin-top: 8px;
            font-size: 0.9rem;
            color: #225c9e;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed lite-mode">
<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-dark">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="#" class="nav-link">Penganjur</a>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-bell"></i>
                    <span id="notif-count" class="badge badge-warning navbar-badge">0</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" id="notif-dropdown">
                    <span class="dropdown-item dropdown-header">Notifikasi</span>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-envelope mr-2"></i> Tidak ada notifikasi baru
                    </a>
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
                            <small><?=  htmlspecialchars($emel, ENT_QUOTES, 'UTF-8') ?></small>
                        </p>
                    </li>
                    <li class="user-footer">
                        <a href="../profile.php" class="btn btn-default btn-flat">Profil</a>
                        <a href="../login/logout.php" class="btn btn-default btn-flat float-right">Keluar</a>
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
                <li class="nav-item"><a href="#section-users" class="nav-link"><i class="nav-icon fas fa-users-cog"></i><p>Pengurusan Pengguna</p></a></li>
                <li class="nav-item"><a href="anjuran.php" class="nav-link active"><i class="nav-icon fas fa-calendar-alt"></i><p>Penganjur</p></a></li>
                <li class="nav-item"><a href="log_audit.php" class="nav-link"><i class="nav-icon fas fa-clipboard-list"></i><p>Log & Audit</p></a></li>
                <li class="nav-item"><a href="#section-settings" class="nav-link"><i class="nav-icon fas fa-cog"></i><p>Tetapan</p></a></li>
            </ul>
        </nav>
    </div>
    </aside>
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Penganjur Program</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Laman Utama</a></li>
                            <li class="breadcrumb-item breadcrumb-active">Penganjur Program</li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="col-8">
                    <div class="card shadow">
                        <div class="card-header">
                            <h3 class="card-title">Edit Penganjur Program</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <a href="anjuran.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left"></i> Kembali ke Senarai Penganjur</a>
                                <form action="edit_anjuran.php" method="POST">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                                    <div class="mb-3">
                                        <label for="nama_penganjur" class="form-label">Nama Penganjur</label>
                                        <input type="text" class="form-control" id="nama_penganjur" name="nama_penganjur" value="<?php echo htmlspecialchars($anjuran['nama_penganjur'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="catatan" class="form-label">Catatan</label>
                                        <textarea class="form-control" id="catatan" name="catatan" rows="3"><?php echo htmlspecialchars($anjuran['catatan'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="kb_no_pekerja" class="form-label">Ketua Bahagian</label>
                                        <div class="kb-search-wrap">
                                            <input type="text" class="form-control" id="kb_no_pekerja" name="kb_no_pekerja" value="<?php echo htmlspecialchars($anjuran['kb_no_pekerja'] ?? ''); ?>" autocomplete="off" required>
                                            <div id="kb_dropdown"></div>
                                        </div>
                                        <div class="kb-helper">Taip sekurang-kurangnya 2 aksara untuk cari no pekerja atau nama staf.</div>
                                        <div id="kb_selected_info"></div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Simpan</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->
    <!-- Footer -->
    <footer class="main-footer" style="background:#0d2236;border-top:1px solid #1b3c5e;">
        <strong>&copy; <?= date('Y') ?> eCetak</strong>
        <div class="float-right d-none d-sm-inline-block">Perpustakaan Sultan Badlishah</div>
    </footer>
</div>
<!-- ./wrapper -->
<!-- JS: jQuery, Bootstrap, AdminLTE, DataTables, SweetAlert2 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js" integrity="sha512-pumBsjNRGGqkPzKHndZMaAG+bir374sORyzM3uulLV14lN5LyykqNk8eEeUlUkB3U0M4FApyaHraT65ihJhDpQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/js/adminlte.min.js" integrity="sha512-KCgUnf/JiKrDFOqBnlGaw2KxEVr4WyCVw/G1EABDQv1mJKbXDphEnJG2IlTRKGVlW/LKymWk4GVSN2jN+A0E2A==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdn.datatables.net/1.13.10/js/jquery.dataTables.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.10/js/dataTables.bootstrap4.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
<script>
    (function () {
        const kbInput = $('#kb_no_pekerja');
        const kbDropdown = $('#kb_dropdown');
        const kbSelectedInfo = $('#kb_selected_info');
        let debounceTimer = null;

        function hideDropdown() {
            kbDropdown.hide().empty();
        }

        function clearSelectedInfo() {
            kbSelectedInfo.hide().text('');
        }

        function renderResults(data) {
            if (!Array.isArray(data) || data.length === 0) {
                kbDropdown.html('<div class="kb-item">Tiada staf dijumpai.</div>').show();
                return;
            }

            const html = data.map(function (item) {
                const noPekerja = String(item.no_pekerja || '');
                const nama = String(item.nama || '');
                return '<div class="kb-item" data-no-pekerja="' + $('<div>').text(noPekerja).html() + '" data-nama="' + $('<div>').text(nama).html() + '">' +
                    '<div><strong>' + $('<div>').text(noPekerja).html() + '</strong></div>' +
                    '<small>' + $('<div>').text(nama).html() + '</small>' +
                    '</div>';
            }).join('');

            kbDropdown.html(html).show();
        }

        kbInput.on('input', function () {
            const query = kbInput.val().trim();
            clearSelectedInfo();

            clearTimeout(debounceTimer);
            if (query.length < 2) {
                hideDropdown();
                return;
            }

            debounceTimer = setTimeout(function () {
                $.ajax({
                    url: 'edit_anjuran.php',
                    method: 'GET',
                    data: { query: query },
                    dataType: 'json',
                    success: renderResults,
                    error: function () {
                        hideDropdown();
                        Swal.fire('Ralat', 'Carian staf gagal. Sila cuba lagi.', 'error');
                    }
                });
            }, 250);
        });

        kbDropdown.on('click', '.kb-item', function () {
            const selectedNoPekerja = $(this).data('no-pekerja');
            const selectedNama = $(this).data('nama');
            if (selectedNoPekerja) {
                kbInput.val(selectedNoPekerja);
                kbSelectedInfo.text('Staf dipilih: ' + selectedNama + ' (' + selectedNoPekerja + ')').show();
            }
            hideDropdown();
            kbInput.trigger('blur');
        });

        $(document).on('click', function (event) {
            if (!$(event.target).closest('.kb-search-wrap').length) {
                hideDropdown();
            }
        });
    })();
</script>

<script>
    // Function to fetch notifications count and details
    function fetchNotifications() {
        $.ajax({
            url: 'fetch_notifications.php', // Endpoint untuk mengambil notifikasi
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#notif-count').text(response.count);
                var notifDropdown = $('#notif-dropdown');
                notifDropdown.find('.dropdown-item:not(.dropdown-header)').remove(); // Hapus notifikasi lama
                if (response.count > 0) {
                    response.notifications.forEach(function(notif) {
                        notifDropdown.append(
                            '<a href="#" class="dropdown-item">' +
                            '<i class="fas fa-envelope mr-2"></i> ' + notif.message +
                            '</a>'
                        );
                    });
                } else {
                    notifDropdown.append(
                        '<a href="#" class="dropdown-item">' +
                        '<i class="fas fa-envelope mr-2"></i> Tidak ada notifikasi baru' +
                        '</a>'
                    );
                }
            },
            error: function() {
                console.error('Gagal mengambil notifikasi');
            }
        });
    }

    // Fetch notifications on page load
    fetchNotifications();

    // Optionally, you can set an interval to fetch notifications periodically
    setInterval(fetchNotifications, 30000); // Fetch every 30 seconds
</script>
<script>
    // Handle sidebar toggle
    $('[data-widget="pushmenu"]').on('click', function() {
        $('.main-sidebar').toggleClass('open');
    });
</script>
</body>
</html>