<?php
/**
 * Tajuk: Senarai Penganjur Program
 * UI menggunakan Bootsrap 5, AndminLTE, Datatables, SweetAlert2, FontAwesome, jQuery, dan AJAX diambil dari CDN.
 * Design: Topbar dan sidebar untuk navigasi, dengan konten utama menampilkan tabel senarai penganjur program.
 * Warna Tema: Biru gelap untuk topbar, biru gelap kusam untuk sidebar, kelabu cerah untuk main content dan biru cerah malap untuk card.
 */
session_start();
require_once 'auth_guard.php';

// Require database connection with PDO and $pdo variable
require_once '../../config/db.php';
require_once '../../config/db_idata.php';

// Fungsi sweetAlertRedirect untuk menampilkan notifikasi dan redirect
function sweetAlertRedirect($title, $text, $icon, $redirectUrl) {
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>$title</title>
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css'>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body>
        <script>
            Swal.fire({
                title: '$title',
                text: '$text',
                icon: '$icon',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '$redirectUrl';
                }
            });
        </script>
    </body>
    </html>";
    exit();
}

// Check if $pdo is initialized and test query untuk memastikan sambungan database berjaya
if ($pdo === null) {
    // Hantar notifikasi error menggunakan SweetAlert2 dan redirect ke halaman utama
    sweetAlertRedirect('SambunganGagal', 'Sambungan ke database tidak tersedia. Sila periksa konfigurasi database.', 'error', '../index.php');
}

try {
    $stmt = $pdo->query("SELECT 1");
} catch (PDOException $e) {
    sweetAlertRedirect('SambunganGagal', 'Sambungan ke database gagal: ' . $e->getMessage(), 'error', '../index.php');
}

// Check if $pdo_idata is initialized and test query untuk memastikan sambungan database idata berjaya
if ($pdo_idata === null) {
    sweetAlertRedirect('SambunganGagal', 'Sambungan ke database idata tidak tersedia. Sila periksa konfigurasi database.', 'error', '../index.php');
}
try {
    $stmt = $pdo_idata->query("SELECT 1");
} catch (PDOException $e) {
    sweetAlertRedirect('SambunganGagal', 'Sambungan ke database idata gagal: ' . $e->getMessage(), 'error', '../index.php');
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

// Fetch senarai penganjur program dari database ke dalam array
try {
    $stmt = $pdo->query("SELECT * FROM anjuran");
    $anjuran_programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    sweetAlertRedirect('Gagal', 'Gagal mengambil data penganjur program: ' . $e->getMessage(), 'error', '../anjuran.php');
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM anjuran WHERE id = :id");
        $stmt->execute(['id' => $id]);
        header("Location: anjuran.php?message=deleted");
        exit();
    } catch (PDOException $e) {
        sweetAlertRedirect('Gagal', 'Gagal memadam penganjur program: ' . $e->getMessage(), 'error', 'anjuran.php');
    }
}

if (isset($_GET['message']) && $_GET['message'] === 'deleted') {
    // $showDeleteSuccess = true;
     sweetAlertRedirect('Berjaya', 'Penganjur program berjaya dipadam.', 'success', 'anjuran.php');
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

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
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-dark">
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
                <a class="nav-link" data-bs-toggle="dropdown" href="#">
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
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
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
                <li class="nav-item"><a href="pengguna.php" class="nav-link"><i class="nav-icon fas fa-users-cog"></i><p>Pengurusan Pengguna</p></a></li>
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
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h3 class="card-title">Senarai Penganjur Program</h3>
                            <!-- Button untuk tambah penganjur baru di kanan atas card header -->
                            <div class="card-tools">
                                <a href="tambah_anjuran.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus"></i> Tambah Penganjur
                                </a>
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="anjuranTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th width="10%">No</th>
                                        <th>Nama Penganjur</th>
                                        <th>Ketua/Pengerusi/Penyelaras</th>
                                        <th width="150px">Tindakan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; ?>
                                    <?php foreach ($anjuran_programs as $program): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($i); ?></td>
                                            <td><?php echo htmlspecialchars($program['nama_penganjur']); ?></td>
                                            <td><?php
                                                // Fetch ketua/pengerusi/penyelaras untuk penganjur ini
                                                try {
                                                    $stmt = $pdo_idata->prepare("SELECT nama FROM t_staf WHERE no_pekerja = :no_pekerja LIMIT 1");
                                                    $stmt->execute([':no_pekerja' => $program['kb_no_pekerja']]);
                                                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                    $userNames = array_map(function($user) {
                                                        return $user['nama'];
                                                    }, $users);
                                                    echo htmlspecialchars(implode(', ', $userNames));
                                                } catch (PDOException $e) {
                                                    sweetAlertRedirect('Ralat', 'Gagal mengambil data pengguna: ' . $e->getMessage(), 'error', 'anjuran.php');
                                                }
                                            ?></td>
                                            <td class="text-center">
                                                <a href="edit_anjuran.php?id=<?php echo $program['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="anjuran.php?action=delete&id=<?php echo $program['id']; ?>" class="btn btn-sm btn-danger js-delete-anjuran">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php $i++; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->
    <!-- Footer -->
    <footer class="main-footer">
        <div class="float-right d-none d-sm-block">
            <b>Version</b> 1.0.0
        </div>
        <strong>&copy; 2024 eCetak. Perpustakaan Sultan Badlishah.</strong>
    </footer>
</div>
<!-- ./wrapper -->
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE JS -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
<script>
    $(document).ready(function() {
        $('#anjuranTable').DataTable();

        $(document).on('click', '.js-delete-anjuran', function(event) {
            event.preventDefault();
            confirmDelete(this.href);
        });
    });

    window.confirmDelete = function(deleteUrl) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            Swal.fire({
                title: 'Pengesahan',
                text: 'Adakah anda pasti ingin memadam penganjur program ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, padam',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = deleteUrl;
                }
            });
            return false;
        }

        return confirm('Adakah anda pasti ingin memadam penganjur program ini?');
    };
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