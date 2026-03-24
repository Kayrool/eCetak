<?php
/**
 * semak_permohonan.php
 * - Papar semakan masa nyata Permohonan Kebenaran Cetakan
 * - Data diambil dari database ecetak table 'permohonan'
 * - Merujuk kepada no_pekerja dari parameter GET
 */

// Include database connection (ecetak database)
require_once __DIR__ . '/config/db.php';

// Include database connection (idata database)
require_once __DIR__ . '/config/db_idata.php';

// Tangkap parameter no_pekerja dari URL
$no_pekerja = isset($_GET['no_pekerja']) ? trim((string)$_GET['no_pekerja']) : '';

// Check jika request dari AJAX
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// Validasi no_pekerja jika tidak kosong
if ($no_pekerja !== '') {
    // Ada no_pekerja, proses seperti biasa
    $has_no_pekerja = true;
} else {
    // Tidak ada no_pekerja, paparkan form input
    $has_no_pekerja = false;
}

// Ambil data permohonan dari database
$permohonan_list = [];
$query_error = null;

if ($has_no_pekerja) {
    try {
        $stmt = $pdo->prepare("SELECT 
            id, no_pekerja, nama_program, no_tel, tarikh_cetakan,
            letterhead_a4_berwarna, letterhead_a4_hitam_putih, letterhead_a3_berwarna, letterhead_a3_hitam_putih,
            booklet_a4_berwarna, booklet_a4_hitam_putih, booklet_a3_berwarna, booklet_a3_hitam_putih,
            poster_a4_berwarna, poster_a4_hitam_putih, poster_a3_berwarna, poster_a3_hitam_putih,
            sijil_a4_berwarna, sijil_a4_hitam_putih, sijil_a3_berwarna, sijil_a3_hitam_putih,
            lain_a4_berwarna, lain_a4_hitam_putih, lain_a3_berwarna, lain_a3_hitam_putih,
            pengesahan_kb_no_pekerja, kelulusan_kp_no_pekerja, status
            FROM permohonan 
            WHERE no_pekerja = :no_pekerja 
            ORDER BY id DESC");
        if (!$stmt) {
            $query_error = "Query Preparation Failed: " . implode(", ", $pdo->errorInfo());
        } else {
            $result = $stmt->execute([':no_pekerja' => $no_pekerja]);
            if (!$result) {
                $query_error = "Query Execution Failed: " . implode(", ", $stmt->errorInfo());
            } else {
                $permohonan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        $query_error = "Exception: " . $e->getMessage();
        $permohonan_list = [];
    }
}

// Fungsi untuk menentukan warna status
function getStatusColor($status) {
    switch($status) {
        case 'PENGESAHAN KB':
            return 'danger';
        case 'KELULUSAN KETUA PERPUSTAKAAN':
            return 'success';
        case 'MENUNGGU':
            return 'warning';
        default:
            return 'secondary';
    }
}

// Fungsi untuk menampilkan status bahasa Melayu
function getStatusLabel($status) {
    switch($status) {
        case 'PENGESAHAN KB':
            return 'Menunggu Pengesahan Ketua Bahagian';
        case 'KELULUSAN KETUA PERPUSTAKAAN':
            return 'Menunggu Kelulusan Ketua Perpustakaan';
        case 'MENUNGGU':
            return 'Menunggu Pengesahan';
        default:
            return 'Tidak Diketahui';
    }
}

// Fungsi untuk menghitung jumlah item
function countItems($permohonan) {
    $count = 0;
    $items = [
        'letterhead_a4_berwarna', 'letterhead_a4_hitam_putih', 'letterhead_a3_berwarna', 'letterhead_a3_hitam_putih',
        'booklet_a4_berwarna', 'booklet_a4_hitam_putih', 'booklet_a3_berwarna', 'booklet_a3_hitam_putih',
        'poster_a4_berwarna', 'poster_a4_hitam_putih', 'poster_a3_berwarna', 'poster_a3_hitam_putih',
        'sijil_a4_berwarna', 'sijil_a4_hitam_putih', 'sijil_a3_berwarna', 'sijil_a3_hitam_putih',
        'lain_a4_berwarna', 'lain_a4_hitam_putih', 'lain_a3_berwarna', 'lain_a3_hitam_putih'
    ];
    foreach($items as $item) {
        if(isset($permohonan[$item]) && (int)$permohonan[$item] > 0) {
            $count += (int)$permohonan[$item];
        }
    }
    return $count;
}

// Ambil data pengguna dari idata.t_staf untuk paparan nama, jawatan dan bahagian(bah_fak)
$staf_data = [];
if ($no_pekerja) {
    try {
        $stmt = $pdo_idata->prepare("SELECT nama, jawatan, bah_fak, emel FROM t_staf WHERE no_pekerja = :no_pekerja");
        $stmt->execute([':no_pekerja' => $no_pekerja]);
        $staf_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching staff data: " . $e->getMessage());
    }
}

// Ambil pengesahan_kb_no_pekerja dari $permohonan_list
$pengesahan_kb_no_pekerja = null;
if (!empty($permohonan_list)) {
    $pengesahan_kb_no_pekerja = $permohonan_list[0]['pengesahan_kb_no_pekerja'];
}

// Ambil data staf dengan role 'KETUA BAHAGIAN' untuk validasi pengesahan KB
$ketua_bahagian = [];
try {
    $stmt = $pdo->prepare("SELECT no_pekerja, nama FROM users WHERE role = 'KETUA BAHAGIAN' AND no_pekerja = :pengesahan_kb_no_pekerja AND aktif = 1");
    $stmt->execute([':pengesahan_kb_no_pekerja' => $pengesahan_kb_no_pekerja]);
    $ketua_bahagian = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
catch (Exception $e) {
    error_log("Error fetching ketua bahagian data: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="ms">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Semak Permohonan - eCetak</title>
  <link rel="icon" href="assets/favicon.ico">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4@5/bootstrap-4.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}

		body {
			background: linear-gradient(135deg, #001f3f 0%, #004080 50%, #0066cc 100%);
			min-height: 100vh;
			font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
		}

		.container-wrapper {
			min-height: 100vh;
			padding: 30px 15px;
			display: flex;
			flex-direction: column;
			justify-content: flex-start;
		}

		.card {
			border: none;
			border-radius: 15px;
			box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
			background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
			margin-bottom: 20px;
		}

		.card-header {
			background: linear-gradient(135deg, #64b5f6 0%, #42a5f5 100%);
			border-radius: 15px 15px 0 0;
			padding: 20px;
			border: none;
		}

		.card-header h4, .card-header h5 {
			color: white;
			margin: 0;
			font-weight: 600;
		}

		.card-header .text-muted {
			color: rgba(255, 255, 255, 0.9) !important;
		}

		.badge-status {
			padding: 8px 15px;
			border-radius: 20px;
			font-weight: 600;
			font-size: 14px;
		}

		.badge-status.bg-success {
			background-color: #4caf50 !important;
		}

		.badge-status.bg-danger {
			background-color: #f44336 !important;
		}

		.badge-status.bg-warning {
			background-color: #ff9800 !important;
			color: white !important;
		}

		.table-responsive {
			border-radius: 10px;
			overflow: hidden;
		}

		.table {
			margin: 0;
			background: transparent;
		}

		.table thead {
			background: linear-gradient(135deg, #81d4fa 0%, #4fc3f7 100%);
			color: white;
		}

		.table thead th {
			border: none;
			padding: 15px;
			font-weight: 600;
			vertical-align: middle;
		}

		.table tbody td {
			padding: 12px 15px;
			vertical-align: middle;
			border-color: #e0e0e0;
		}

		.table tbody tr:hover {
			background-color: #e3f2fd;
		}

		.btn-group-custom {
			display: flex;
			gap: 10px;
			flex-wrap: wrap;
		}

		.btn-custom {
			padding: 10px 20px;
			border-radius: 8px;
			font-weight: 500;
			border: none;
			transition: all 0.3s ease;
		}

		.btn-custom-primary {
			background: linear-gradient(135deg, #42a5f5 0%, #1e88e5 100%);
			color: white;
		}

		.btn-custom-primary:hover {
			transform: translateY(-2px);
			box-shadow: 0 5px 15px rgba(30, 136, 229, 0.4);
			color: white;
		}

		.btn-custom-secondary {
			background: linear-gradient(135deg, #90caf9 0%, #64b5f6 100%);
			color: white;
		}

		.btn-custom-secondary:hover {
			transform: translateY(-2px);
			box-shadow: 0 5px 15px rgba(100, 181, 246, 0.4);
			color: white;
		}

		.info-section {
			background: white;
			padding: 20px;
			border-radius: 10px;
			margin-bottom: 15px;
		}

		.info-label {
			font-weight: 600;
			color: #1565c0;
			font-size: 14px;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}

		.info-value {
			color: #333;
			font-size: 16px;
			margin-top: 5px;
		}

		.detail-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 15px;
		}

		.empty-state {
			text-align: center;
			padding: 60px 20px;
			color: white;
		}

		.empty-state-icon {
			font-size: 60px;
			margin-bottom: 20px;
			opacity: 0.8;
		}

		.empty-state-text {
			font-size: 18px;
			margin-bottom: 20px;
		}

		.page-title {
			color: white;
			text-align: center;
			margin-bottom: 30px;
			padding: 20px;
			text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
		}

		.page-title h1 {
			font-weight: 700;
			font-size: 2.5rem;
			margin-bottom: 10px;
		}

		.page-title p {
			font-size: 16px;
			opacity: 0.95;
		}

		.detail-item-value {
			padding: 10px;
			background: #f5f5f5;
			border-radius: 5px;
			border-left: 4px solid #42a5f5;
		}

		.modal-content {
			border-radius: 15px;
			border: none;
			background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
		}

		.modal-header {
			background: linear-gradient(135deg, #64b5f6 0%, #42a5f5 100%);
			border-radius: 15px 15px 0 0;
			border: none;
		}

		.modal-header .btn-close {
			filter: brightness(0) invert(1);
		}

		.modal-title {
			color: white;
			font-weight: 600;
		}

		.items-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
			gap: 15px;
			margin-top: 15px;
		}

		.item-card {
			background: white;
			padding: 15px;
			border-radius: 8px;
			text-align: center;
			border: 2px solid #e0e0e0;
			transition: all 0.3s ease;
		}

		.item-card:hover {
			border-color: #42a5f5;
			box-shadow: 0 5px 15px rgba(66, 165, 245, 0.2);
		}

		.item-name {
			font-weight: 600;
			color: #1565c0;
			font-size: 13px;
			margin-bottom: 8px;
		}

		.item-quantity {
			font-size: 24px;
			font-weight: 700;
			color: #f57c00;
		}

		.item-unit {
			font-size: 12px;
			color: #666;
			margin-top: 5px;
		}

		.filter-section {
			background: white;
			padding: 20px;
			border-radius: 10px;
			margin-bottom: 20px;
			box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
		}

		/* Transparent table styling */
		.alert .table,
		.alert .table tbody,
		.alert .table tbody tr,
		.alert .table tbody td {
			background-color: transparent !important;
		}

		/* Form card styling sesuai tema */
		.form-card,
		.staf-info {
			border: none;
			border-radius: 15px;
			box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
			background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
		}

		.form-card h5 {
			color: #1565c0;
			font-weight: 700;
		}

		.form-card strong {
			color: #1565c0;
			font-weight: 600;
		}

		.form-card .text-primary {
			color: #0d47a1 !important;
			font-weight: 500;
		}

		/* Styling untuk search form */
		#searchForm {
			flex-direction: column;
		}

		#searchForm .form-control {
			border-radius: 8px;
			border: 2px solid #bbdefb;
			padding: 12px 15px;
			font-size: 16px;
			transition: all 0.3s ease;
		}

		#searchForm .form-control:focus {
			border-color: #42a5f5;
			box-shadow: 0 0 0 0.2rem rgba(66, 165, 245, 0.25);
			background-color: #f0f8ff;
		}

		#searchForm .btn {
			width: 100%;
			padding: 12px 20px;
			font-size: 16px;
			font-weight: 600;
		}

		/* Responsive Design */
		@media (max-width: 768px) {
			.page-title h1 {
				font-size: 1.8rem;
			}
			
			.detail-grid {
				grid-template-columns: 1fr;
			}
			
			.items-grid {
				grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
			}
		}

		@media (max-width: 576px) {
			#searchForm {
				flex-direction: column;
			}
			
			#searchForm .form-control,
			#searchForm .btn {
				width: 100%;
			}
		}
	</style>
</head>
<body>
  <div class="container-wrapper">
    <!-- Page Title -->
    <div class="page-title">
      <h1><i class="bi bi-file-text"></i> Semakan Permohonan Kebenaran Cetakan</h1>
    </div>

    <div class="container">
      <?php if (!$has_no_pekerja): ?>
        <!-- Input Form Section -->
        <div class="card mb-4" style="max-width: 500px; margin: 30px auto;">
          <div class="card-header">
            <h5 style="margin: 0;"><i class="bi bi-search"></i> Masukkan Nombor Pekerja</h5>
          </div>
          <div class="card-body">
            <form id="searchForm" class="d-flex gap-2">
              <input type="text" 
                     id="no_pekerja_input" 
                     name="no_pekerja" 
                     class="form-control form-control-lg" 
                     placeholder="Contoh: 12345"
                     autocomplete="off"
                     required autofocus>
              <button type="submit" class="btn btn-custom btn-custom-primary">
                <i class="bi bi-search"></i> Cari
              </button>
            </form>
            <div id="searchError" class="alert alert-danger mt-3" style="display: none;"></div>
            <div id="searchLoading" class="alert alert-info mt-3" style="display: none;">
              <i class="bi bi-hourglass-split"></i> Mencari data...
            </div>
          </div>
        </div>
      <?php else: ?>
        <!-- Permohonan Content Section -->
        <?php if (empty($permohonan_list)): ?>
        <!-- Debug Information -->
        <!-- <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 20px; margin-bottom: 20px; color: #856404;">
          <h6 style="margin-bottom: 15px; font-weight: 600;">
            <i class="bi bi-exclamation-triangle"></i> Maklumat Debug
          </h6>
          <p><strong>Nombor Pekerja yang dicari:</strong> <code><?php echo htmlspecialchars($no_pekerja, ENT_QUOTES, 'UTF-8'); ?></code></p>
          <p><strong>Status Sambungan Database:</strong> 
            <?php 
            if ($pdo) {
                echo '<span style="color: #155724; font-weight: 600;">✓ Berjaya</span>';
            } else {
                echo '<span style="color: #721c24; font-weight: 600;">✗ Gagal</span>';
            }
            ?>
          </p>
          <p><strong>Bilangan Permohonan Ditemui:</strong> <code><?php echo count($permohonan_list); ?></code></p>
          <?php if ($query_error): ?>
          <p><strong>Ralat Query:</strong> <code style="background: #f8d7da; padding: 10px; border-radius: 4px; display: block; margin-top: 10px;"><?php echo htmlspecialchars($query_error, ENT_QUOTES, 'UTF-8'); ?></code></p>
          <?php endif; ?>
          <hr style="margin: 15px 0; border: none; border-top: 1px solid #ffc107;">
          <p style="font-size: 13px; margin: 0;">
            Jika masalah berterusan, sila hubungi administrator sistem dengan info di atas.
          </p>
        </div> -->
        
        <!-- Empty State -->
        <div class="empty-state" style="padding: 30px 20px;">
          <div class="empty-state-icon">
            <i class="bi bi-inbox"></i>
          </div>
          <div class="empty-state-text">Tiada Permohonan Ditemui</div>
          <p style="font-size: 14px; opacity: 0.8;">Anda belum membuat permohonan. Sila membuat permohonan baru untuk memulai.</p>
          <a href="carian_staf.php" class="btn btn-custom btn-custom-primary mt-3">
            <i class="bi bi-plus-circle"></i> Buat Permohonan Baru
          </a>
          <br>
          <a href="index.php" class="btn btn-custom btn-custom-secondary mt-3">
            <i class="bi bi-house"></i> Kembali ke Halaman Utama
          </a>
        </div>
        <?php else: ?>
        <!-- Filter Section -->
        <div class="filter-section">
          <div class="row align-items-center">
            <div class="col-md-8">
              <h6 class="mb-0">
                <i class="bi bi-funnel"></i> Jumlah Permohonan: <strong><?php echo count($permohonan_list); ?></strong>
              </h6>
            </div>
            <div class="col-md-4 text-end mt-3 mt-md-0">
              <a href="index.php" class="btn btn-custom btn-custom-secondary">
                <i class="bi bi-house"></i> Kembali ke Halaman Utama
              </a>
            </div>
          </div>
        </div>
        <!-- Papar maklumat permohonan -->
        <section class="form-card p-4 p-lg-5 mb-4 staf-info" data-aos="fade-up" data-aos-duration="700" data-aos-delay="100">
        <h5 class="mb-3 fw-bold">
            <i class="fas fa-user-circle me-2"></i>Maklumat Staf
        </h5>
        <div class="row g-3">
            <div class="col-md-6">
            <div class="mb-2">
                <strong>Nama:</strong> <span class="text-primary"><?php echo htmlspecialchars($staf_data['nama'] ?? 'Tidak Diketahui', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="mb-2">
                <strong>No. Pekerja:</strong> <span class="text-primary"><?php echo htmlspecialchars($no_pekerja, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="mb-2">
                <strong>Jawatan:</strong> <span class="text-primary"><?php echo htmlspecialchars($staf_data['jawatan'] ?? 'Tidak Diketahui', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            </div>
            <div class="col-md-6">
            <div class="mb-2">
                <strong>Bahagian / Fakulti:</strong> <span class="text-primary"><?php echo htmlspecialchars($staf_data['bah_fak'] ?? 'Tidak Diketahui', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="mb-2">
                <strong>Emel:</strong> <span class="text-primary"><?php echo htmlspecialchars($staf_data['emel'] ?? 'Tidak Diketahui', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            </div>
        </div>
        </section>

        <?php foreach($permohonan_list as $permohonan): ?>
          <div class="card mb-4">
            <!-- Card Header -->
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <h5 class="mb-1">
                  <i class="bi bi-file-earmark-pdf"></i><?php echo " ".htmlspecialchars($permohonan['nama_program']); ?>
                </h5>
              </div>
              <div>
                <span class="badge badge-status bg-<?php echo getStatusColor($permohonan['status'] ?? 'MENUNGGU'); ?>">
                  <i class="bi bi-check-circle"></i> <?php echo getStatusLabel($permohonan['status'] ?? 'MENUNGGU'); ?>
                </span>
              </div>
            </div>

            <!-- Card Body -->
            <div class="card-body">
              <!-- Basic Information -->
              <div class="row mb-4">
                <div class="col-md-6">
                  <div class="info-section">
                    <div class="info-label"><i class="bi bi-briefcase"></i> Program</div>
                    <div class="info-value"><?php echo htmlspecialchars($permohonan['nama_program']); ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-section">
                    <div class="info-label"><i class="bi bi-telephone"></i> Nombor Telefon</div>
                    <div class="info-value"><?php echo htmlspecialchars($permohonan['no_tel']); ?></div>
                  </div>
                </div>
              </div>

              <div class="row mb-4">
                <div class="col-md-6">
                  <div class="info-section">
                    <div class="info-label"><i class="bi bi-calendar-event"></i> Tarikh Cetakan</div>
                    <div class="info-value"><?php echo date('d/m/Y', strtotime($permohonan['tarikh_cetakan'])); ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-section">
                    <div class="info-label"><i class="bi bi-box"></i> Jumlah Item</div>
                    <div class="info-value"><?php echo countItems($permohonan); ?> Item</div>
                  </div>
                </div>
              </div>

              <!-- Items Breakdown -->
              <div>
                <h6 class="mb-3">
                  <i class="bi bi-list-check"></i> Butiran Item Cetakan
                </h6>
                <div class="items-grid">
                  <?php
                  $items_display = [
                    ['name' => 'Letterhead A4 Berwarna', 'key' => 'letterhead_a4_berwarna'],
                    ['name' => 'Letterhead A4 Hitam Putih', 'key' => 'letterhead_a4_hitam_putih'],
                    ['name' => 'Letterhead A3 Berwarna', 'key' => 'letterhead_a3_berwarna'],
                    ['name' => 'Letterhead A3 Hitam Putih', 'key' => 'letterhead_a3_hitam_putih'],
                    ['name' => 'Booklet A4 Berwarna', 'key' => 'booklet_a4_berwarna'],
                    ['name' => 'Booklet A4 Hitam Putih', 'key' => 'booklet_a4_hitam_putih'],
                    ['name' => 'Booklet A3 Berwarna', 'key' => 'booklet_a3_berwarna'],
                    ['name' => 'Booklet A3 Hitam Putih', 'key' => 'booklet_a3_hitam_putih'],
                    ['name' => 'Poster A4 Berwarna', 'key' => 'poster_a4_berwarna'],
                    ['name' => 'Poster A4 Hitam Putih', 'key' => 'poster_a4_hitam_putih'],
                    ['name' => 'Poster A3 Berwarna', 'key' => 'poster_a3_berwarna'],
                    ['name' => 'Poster A3 Hitam Putih', 'key' => 'poster_a3_hitam_putih'],
                    ['name' => 'Sijil A4 Berwarna', 'key' => 'sijil_a4_berwarna'],
                    ['name' => 'Sijil A4 Hitam Putih', 'key' => 'sijil_a4_hitam_putih'],
                    ['name' => 'Sijil A3 Berwarna', 'key' => 'sijil_a3_berwarna'],
                    ['name' => 'Sijil A3 Hitam Putih', 'key' => 'sijil_a3_hitam_putih'],
                    ['name' => 'Lain A4 Berwarna', 'key' => 'lain_a4_berwarna'],
                    ['name' => 'Lain A4 Hitam Putih', 'key' => 'lain_a4_hitam_putih'],
                    ['name' => 'Lain A3 Berwarna', 'key' => 'lain_a3_berwarna'],
                    ['name' => 'Lain A3 Hitam Putih', 'key' => 'lain_a3_hitam_putih']
                  ];
                  
                  foreach($items_display as $item):
                    $qty = isset($permohonan[$item['key']]) ? (int)$permohonan[$item['key']] : 0;
                    // $qty = (int)($permohonan[$item{'key'}] ?? 0);
                    if($qty > 0):
                  ?>
                    <div class="item-card">
                      <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                      <div class="item-quantity"><?php echo $qty; ?></div>
                      <div class="item-unit">Salinan</div>
                    </div>
                  <?php
                    endif;
                  endforeach;
                  ?>
                </div>
              </div>

              <!-- Pengesahan Section -->
              <?php if($permohonan['status'] === 'PENGESAHAN KETUA BAHAGIAN'): ?>
                <div class="row mt-4">
                  <div class="col-md-6">
                    <div class="info-section bg-success bg-opacity-10" style="border-left: 4px solid #4caf50;">
                      <div class="info-label" style="color: #2e7d32;">
                        <i class="bi bi-check-circle"></i> Ketua Bahagian
                      </div>
                      <div class="info-value" style="color: #2e7d32;">
                        <?php echo htmlspecialchars($ketua_bahagian['nama'], ENT_QUOTES, 'UTF-8'); ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
              <?php if($permohonan['status'] === 'KELULUSAN KETUA PERPUSTAKAAN'): ?>
                <div class="row mt-4">
                  <div class="col-md-6">
                    <div class="info-section bg-success bg-opacity-10" style="border-left: 4px solid #4caf50;">
                      <div class="info-label" style="color: #2e7d32;">
                        <i class="bi bi-check-circle"></i> Ketua Perpustakaan
                      </div>
                      <div class="info-value" style="color: #2e7d32;">
                        <?php echo htmlspecialchars($permohonan['kelulusan_kp_no_pekerja'], ENT_QUOTES, 'UTF-8'); ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- Action Buttons -->
        <div class="text-center mb-4">
          <a href="index.php" class="btn btn-custom btn-custom-secondary">
            <i class="bi bi-house"></i> Kembali ke Halaman Utama
          </a>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
  <script>
    // Handle form submission untuk input no_pekerja
    document.getElementById('searchForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const noPekerja = document.getElementById('no_pekerja_input').value.trim();
      const errorDiv = document.getElementById('searchError');
      const loadingDiv = document.getElementById('searchLoading');
      
      // Reset messages
      errorDiv.style.display = 'none';
      errorDiv.innerHTML = '';
      
      // Validasi input
      if (!noPekerja) {
        errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Sila masukkan nombor pekerja.';
        errorDiv.style.display = 'block';
        return;
      }
      
      // Tampilkan loading
      loadingDiv.style.display = 'block';
      
      // Redirect dengan parameter no_pekerja
      window.location.href = 'semak_permohonan.php?no_pekerja=' + encodeURIComponent(noPekerja);
    });
  </script>
</body>
</html>