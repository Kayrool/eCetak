<?php
/**
 * permohonan.php
 * - Terima no_pekerja dari carian_staf.php
 * - Query database untuk dapatkan maklumat staf
 * - Papar borang permohonan dengan data staf yang dipilih
 * - Peningkatan: CSRF, validasi klien, SweetAlert konsisten, PDO attr, kod diperkemas
 * - Pilihan untuk Anjuran Bahagian/Pusat Pengajian (YA/TIDAK) trigger input pilihan untuk Pilihan Anjuaran
 */

declare(strict_types=1);

// Mula sesi
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Header asas keselamatan
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Jana CSRF token jika tiada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'] ?? '';

// Helper: Escape HTML
function esc($s) {
  if ($s === null) {
    return '';
  }
  if (is_bool($s)) {
    $s = $s ? '1' : '0';
  } elseif (!is_scalar($s)) {
    $s = '';
  }
  return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Helper: Papar SweetAlert & keluar
function swal_exit(string $icon, string $title, string $text, string $redirectUrl): void {
    $icon_js = json_encode($icon, JSON_UNESCAPED_UNICODE);
    $title_js = json_encode($title, JSON_UNESCAPED_UNICODE);
    $text_js  = json_encode($text, JSON_UNESCAPED_UNICODE);
    $redir_js = json_encode($redirectUrl, JSON_UNESCAPED_SLASHES);
    ?>
    <!doctype html>
    <html lang="ms">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?= esc($title) ?> - eCetak</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4@5/bootstrap-4.min.css" rel="stylesheet">
    </head>
    <body>
      <noscript>
        <div class="container py-4">
          <div class="alert alert-warning" role="alert">
            <h4 class="alert-heading"><?= esc($title) ?></h4>
            <p class="mb-2"><?= esc($text) ?></p>
            <hr>
            <a class="btn btn-primary" href="<?= esc($redirectUrl) ?>">Teruskan</a>
          </div>
        </div>
      </noscript>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script>
        (function () {
          var redirectUrl = <?= $redir_js ?>;
          if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
              icon: <?= $icon_js ?>,
              title: <?= $title_js ?>,
              text: <?= $text_js ?>,
              confirmButtonText: 'Kembali',
              allowOutsideClick: false
            }).then(function () {
              window.location.href = redirectUrl;
            });
            return;
          }

          // Fallback jika CDN SweetAlert gagal dimuat.
          document.body.innerHTML =
            '<div class="container py-4">' +
              '<div class="alert alert-warning" role="alert">' +
                '<h4 class="alert-heading"><?= esc($title) ?></h4>' +
                '<p class="mb-2"><?= esc($text) ?></p>' +
                '<hr>' +
                '<a class="btn btn-primary" href="' + redirectUrl + '">Teruskan</a>' +
              '</div>' +
            '</div>';
        })();
      </script>
    </body>
    </html>
    <?php
    exit;
}


// Include database connection idata
require_once __DIR__ . '/config/db_idata.php';
// Semak sambungan pangkalan data idata dan hantar ralat ke sweetalert jika tidak dapat sambung.
try {
    if (isset($pdo_idata) && $pdo_idata instanceof PDO) {
        $pdo_idata->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo_idata->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } else {
        throw new RuntimeException('Sambungan pangkalan data tidak tersedia.');
    }
} catch (Throwable $e) {
    error_log('DB connection error permohonan.php: ' . $e->getMessage());
    swal_exit('error', 'Ralat Pangkalan Data', 'Tidak dapat menghubungi pangkalan data. Sila cuba semula.', 'index.php');
}

// Include database connection ecetak
require_once __DIR__ . '/config/db.php';
// Semak sambungan pangkalan data ecetak dan hantar ralat ke sweetalert jika tidak dapat sambung.
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } else {
        throw new RuntimeException('Sambungan pangkalan data ecetak tidak tersedia.');
    }
} catch (Throwable $e) {
    error_log('DB connection error permohonan.php (ecetak): ' . $e->getMessage());
    swal_exit('error', 'Ralat Pangkalan Data', 'Tidak dapat menghubungi pangkalan data. Sila cuba semula.', 'index.php');
}

// Tangkap no_pekerja dari POST
$no_pekerja = isset($_POST['no_pekerja']) ? trim((string)$_POST['no_pekerja']) : '';
$nama = isset($_POST['nama']) ? trim((string)$_POST['nama']) : '';
$bah_fak = isset($_POST['bah_fak']) ? trim((string)$_POST['bah_fak']) : '';
$emel = isset($_POST['emel']) ? trim((string)$_POST['emel']) : '';

// Validasi ringkas no_pekerja (selari server-side: A-Z0-9 - _ /)
$no_pekerja_valid = '';
if ($no_pekerja !== '' && preg_match('/^[A-Za-z0-9\-_\/]+$/', $no_pekerja)) {
    $no_pekerja_valid = strtoupper($no_pekerja);
}

if ($no_pekerja_valid === '') {
    swal_exit('error', 'Ralat', 'No. Pekerja tidak diterima atau tidak sah. Sila cuba semula.', 'index.php');
}

// Query untuk dapatkan maklumat staf
$stafData = null;
try {
    if (isset($pdo_idata) && $pdo_idata instanceof PDO) {
        $pdo_idata->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo_idata->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } else {
        throw new RuntimeException('Sambungan pangkalan data tidak tersedia.');
    }

    $stmt = $pdo_idata->prepare('
        SELECT 
            no_pekerja,
            nama,
            jawatan,
            bah_fak,
            emel
        FROM t_staf 
        WHERE no_pekerja = :no_pekerja 
        LIMIT 1
    ');
    $stmt->bindValue(':no_pekerja', $no_pekerja_valid, PDO::PARAM_STR);
    $stmt->execute();
    $stafData = $stmt->fetch();

    // Penukaran encoding jika data sumber bukan UTF-8
    if ($stafData) {
        $stafData = array_map(function($value) {
            if (is_string($value)) {
                if (function_exists('mb_convert_encoding')) {
                    return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                } elseif (function_exists('utf8_encode')) {
                    return utf8_encode($value);
                }
            }
            return $value;
        }, $stafData);
    }
} catch (Throwable $e) {
    error_log('DB error permohonan.php: ' . $e->getMessage());
    swal_exit('error', 'Ralat Pangkalan Data', 'Tidak dapat menghubungi pangkalan data. Sila cuba semula.', 'index.php');
}

// Jika staf tidak ditemui
if (!$stafData) {
    swal_exit('warning', 'Staf Tidak Ditemui', 'Staf dengan No. Pekerja ' . $no_pekerja_valid . ' tidak ditemui dalam sistem.', 'carian_staf.php');
}

// Urai data staf
$nama    = $stafData['nama'] ?? '';
$no_pk   = $stafData['no_pekerja'] ?? '';
$jawatan = $stafData['jawatan'] ?? '';
$bah_fak = $stafData['bah_fak'] ?? '';
$emel    = $stafData['emel'] ?? '';

// Query data anjuran dari database ecetak
$dataAnjuran = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } else {
        throw new RuntimeException('Sambungan pangkalan data ecetak tidak tersedia.');
    }

    $stmtAnjuran = $pdo->prepare('SELECT id, nama_penganjur FROM anjuran ORDER BY nama_penganjur ASC');
    $stmtAnjuran->execute();
    $dataAnjuran = $stmtAnjuran->fetchAll();

    // Penukaran encoding jika diperlukan
    if ($dataAnjuran) {
        $dataAnjuran = array_map(function($row) {
            if (is_array($row)) {
                return array_map(function($value) {
                    if (is_string($value)) {
                        if (function_exists('mb_convert_encoding')) {
                            return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                        } elseif (function_exists('utf8_encode')) {
                            return utf8_encode($value);
                        }
                    }
                    return $value;
                }, $row);
            }
            return $row;
        }, $dataAnjuran);
    }
} catch (Throwable $e) {
    error_log('DB error anjuran permohonan.php: ' . $e->getMessage());
    $dataAnjuran = [];
}

// Sediakan option HTML secara defensif supaya render halaman tidak terhenti jika data rekod tidak normal.
$anjuranOptionsHtml = '';
if (is_array($dataAnjuran)) {
  foreach ($dataAnjuran as $row) {
    if (!is_array($row)) {
      continue;
    }

    $id = $row['id'] ?? '';
    $namaPenganjur = $row['nama_penganjur'] ?? '';

    if ($id === '' && $namaPenganjur === '') {
      continue;
    }

    $anjuranOptionsHtml .= '<option value="' . esc($id) . '">' . esc($namaPenganjur) . '</option>';
  }
}

?>
<!doctype html>
<html lang="ms">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Borang Permohonan - eCetak</title>

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <!-- AOS CSS -->
  <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

  <!-- SweetAlert2 Theme -->
  <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4@5/bootstrap-4.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="css/custom.css">
  <style>
    /* Fallback: elak elemen data-aos kekal tersembunyi jika skrip AOS gagal dijalankan. */
    [data-aos] {
      opacity: 1 !important;
      transform: none !important;
    }
  </style>
</head>
<body class="permohonan-page">
  <div class="container py-4 py-lg-5">
    <!-- Header -->
    <header class="form-header p-4 p-lg-5 mb-4" data-aos="fade-up" data-aos-duration="700">
      <div class="row align-items-center g-4">
        <div class="col-lg-8">
          <h1 class="h2 h1-lg fw-bold mb-2">Borang Permohonan Kebenaran Cetakan</h1>
          <p class="mb-0">Lengkapkan maklumat di bawah untuk proses permohonan.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
          <a href="carian_staf.php" class="btn btn-light btn-sm">
            <i class="fas fa-arrow-left"></i> Carian Semula
          </a>
        </div>
      </div>
    </header>

    <!-- Info Staf yang Dipilih -->
    <section class="form-card p-4 p-lg-5 mb-4 staf-info" data-aos="fade-up" data-aos-duration="700" data-aos-delay="100">
      <h5 class="mb-3 fw-bold">
        <i class="fas fa-user-circle me-2"></i>Maklumat Staf
      </h5>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="mb-2">
            <strong>Nama:</strong> <span class="text-primary"><?= esc($nama); ?></span>
          </div>
          <div class="mb-2">
            <strong>No. Pekerja:</strong> <span class="text-primary"><?= esc($no_pk); ?></span>
          </div>
          <div class="mb-2">
            <strong>Jawatan:</strong> <span class="text-primary"><?= esc($jawatan) ?: '-'; ?></span>
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-2">
            <strong>Bahagian / Fakulti:</strong> <span class="text-primary"><?= esc($bah_fak) ?: '-'; ?></span>
          </div>
          <div class="mb-2">
            <strong>Emel:</strong> <span class="text-primary"><?= esc($emel) ?: '-'; ?></span>
          </div>
        </div>
      </div>
    </section>

    <!-- Borang Permohonan -->
    <section class="form-card p-4 p-lg-5 mb-4" data-aos="fade-up" data-aos-duration="700" data-aos-delay="150">
      <h4 class="mb-4 fw-bold">Butiran Permohonan</h4>
      <form method="POST" action="simpan_permohonan.php" id="formPermohonan" novalidate>
        <!-- CSRF token -->
        <input type="hidden" name="csrf_token" value="<?= esc($csrf_token); ?>">
        <!-- Simpan no_pekerja sebagai input hidden -->
        <input type="hidden" name="no_pekerja" value="<?= esc($no_pk); ?>">
        <!-- Maklumat staf disimpan untuk rujukan -->
        <input type="hidden" name="nama" value="<?= esc($nama); ?>">
        <input type="hidden" name="bah_fak" value="<?= esc($bah_fak); ?>">
        <input type="hidden" name="emel" value="<?= esc($emel); ?>">

        <div class="row g-3">
          <!-- Anjuran Bahagian/Pusat Pengajian atau Bukan -->
          <div class="col-md-6 mb-3">
            <label class="form-label d-block mb-2">
              <i class="fa-solid fa-building me-1"></i>Anjuran Bahagian/Pusat Pengajian?
            </label>
            <div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="anjuran_bahagian" id="anjuran_ada" value="1" checked required>
                <label class="form-check-label" for="anjuran_ada">Ya</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="anjuran_bahagian" id="anjuran_tidak" value="0" required>
                <label class="form-check-label" for="anjuran_tidak">Tidak</label>
              </div>
            </div>
          </div>

          <!-- Pilihan Anjuran (Papar apabila "Tidak" dipilih) -->
          <div class="col-md-6 mb-3" id="containerPilihanAnjuran" style="display: none;">
            <label for="pilihan_anjuran" class="form-label">
              <i class="fa-solid fa-building me-1"></i>Pilihan Anjuran
            </label>
            <select id="pilihan_anjuran" name="pilihan_anjuran" class="form-select">
              <option value="">-- Sila Pilih Anjuran --</option>
              <?php if ($anjuranOptionsHtml !== ''): ?>
                <?= $anjuranOptionsHtml; ?>
              <?php else: ?>
                <option value="" disabled>Tiada data</option>
              <?php endif; ?>
            </select>
            <div class="invalid-feedback">Sila pilih anjuran.</div>
          </div>
        </div>

        <div class="row g-3">
          <!-- Nama Program -->
          <div class="mb-3">
            <label for="nama_program" class="form-label">
              <i class="fa-solid fa-calendar-days me-1"></i>Nama Program
            </label>
            <input
              id="nama_program"
              type="text"
              name="nama_program"
              class="form-control"
              maxlength="255"
              autocomplete="off"
              required
            >
            <div class="invalid-feedback">Sila masukkan nama program (maks 255 aksara).</div>
          </div>
        </div>

        <div class="row g-3">
          <!-- Nombor Telefon/HP -->
          <div class="col-md-6 mb-3">
            <label for="no_tel" class="form-label">
              <i class="fa-solid fa-phone me-1"></i>No Telefon
            </label>
            <input
              id="no_tel"
              type="text"
              name="no_tel"
              class="form-control"
              pattern="^[0-9\+\-\s\(\)]+$"
              maxlength="30"
              inputmode="tel"
              autocomplete="tel"
              required
            >
            <div class="invalid-feedback">Sila masukkan no telefon yang sah (angka, +, -, ruang, kurungan).</div>
          </div>

          <!-- Tarikh Diperlukan -->
          <div class="col-md-6 mb-3">
            <label for="tarikh_cetakan" class="form-label">
              <i class="fas fa-calendar-alt me-1"></i>Tarikh Cetakan
            </label>
            <input type="date" id="tarikh_cetakan" name="tarikh_cetakan" class="form-control" required>
            <div class="invalid-feedback">Sila pilih tarikh diperlukan (tidak boleh tarikh lampau).</div>
          </div>
        </div>

        <div class="row g-3">
          <!-- Bahan Cetakan -->
          <div class="mb-3">
            <label class="form-label mb-3">
              <i class="fa-solid fa-print me-1"></i>Bahan Cetakan
            </label>
            <div class="table-responsive">
              <table class="table table-bordered text-center align-middle">
                <thead class="table-light">
                  <tr>
                    <th scope="col" rowspan="2" class="align-middle">Bil</th>
                    <th scope="col" rowspan="2" class="align-middle">Jenis Bahan</th>
                    <th scope="col" colspan="4" class="text-center">Kuantiti</th>
                  </tr>
                  <tr>
                    <th scope="col">A4 (Berwarna)</th>
                    <th scope="col">A4 (Hitam Putih)</th>
                    <th scope="col">A3 (Berwarna)</th>
                    <th scope="col">A3 (Hitam Putih)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="fw-bold">1.</td>
                    <td class="text-start">Letterhead</td>
                    <td><input type="number" name="letterhead_a4_berwarna" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="letterhead_a4_hitam_putih" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="letterhead_a3_berwarna" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="letterhead_a3_hitam_putih" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                  </tr>
                  <tr>
                    <td class="fw-bold">2.</td>
                    <td class="text-start">Booklet</td>
                    <td><input type="number" name="booklet_a4_berwarna" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="booklet_a4_hitam_putih" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="booklet_a3_berwarna" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="booklet_a3_hitam_putih" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                  </tr>
                  <tr>
                    <td class="fw-bold">3.</td>
                    <td class="text-start">Poster</td>
                    <td><input type="number" name="poster_a4_berwarna" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="poster_a4_hitam_putih" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="poster_a3_berwarna" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="poster_a3_hitam_putih" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                  </tr>
                  <tr>
                    <td class="fw-bold">4.</td>
                    <td class="text-start">Sijil</td>
                    <td><input type="number" name="sijil_a4_berwarna" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="sijil_a4_hitam_putih" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="sijil_a3_berwarna" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="sijil_a3_hitam_putih" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                  </tr>
                  <tr>
                    <td class="fw-bold">5.</td>
                    <td class="text-start">Lain-lain</td>
                    <td><input type="number" name="lain_a4_berwarna" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="lain_a4_hitam_putih" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="lain_a3_berwarna" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                    <td><input type="number" name="lain_a3_hitam_putih" class="form-control" min="0" step="1" inputmode="numeric" value="0"></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Butang Aksi -->
        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-gradient btn-lg">
            <i class="fas fa-paper-plane me-2"></i>Hantar Permohonan
          </button>
          <button type="button" class="btn btn-outline-secondary btn-lg" id="btnBatal">
            <i class="fas fa-times me-2"></i>Batal
          </button>
        </div>
      </form>
    </section>

    <footer class="mt-4 text-center">
      <small class="footer-note">© 2026 eCetak · Perpustakaan Sultan Badlishah</small>
    </footer>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <!-- AOS -->
  <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.min.js"></script>
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    if (window.AOS && typeof window.AOS.init === 'function') {
      window.AOS.init();
    } else {
      // Fallback: pastikan elemen ber-data-aos tetap dipaparkan jika CDN AOS gagal dimuat.
      document.querySelectorAll('[data-aos]').forEach(function(el) {
        el.style.opacity = '1';
        el.style.transform = 'none';
      });
    }

    // Validasi Form Bootstrap
    var formPermohonan = document.getElementById('formPermohonan');
    var inputTarikh = document.getElementById('tarikh_cetakan');
    var radioButtons = document.querySelectorAll('input[name="anjuran_bahagian"]');
    var containerPilihanAnjuran = document.getElementById('containerPilihanAnjuran');
    var pilihanAnjuranSelect = document.getElementById('pilihan_anjuran');

    // Tetapkan tarikh minimum ke hari ini
    if (inputTarikh) {
      var today = new Date().toISOString().split('T')[0];
      inputTarikh.setAttribute('min', today);
    }

    // Fungsi untuk menampilkan/menyembunyikan pilihan anjuran
    function updateAnjuranVisibility() {
      var anjuranTidak = document.getElementById('anjuran_tidak');
      var isAnjuranTidak = !!(anjuranTidak && anjuranTidak.checked);
      
      if (containerPilihanAnjuran && pilihanAnjuranSelect && isAnjuranTidak) {
        containerPilihanAnjuran.style.display = 'block';
        pilihanAnjuranSelect.required = true;
      } else if (containerPilihanAnjuran && pilihanAnjuranSelect) {
        containerPilihanAnjuran.style.display = 'none';
        pilihanAnjuranSelect.required = false;
        pilihanAnjuranSelect.value = '';
      }
    }

    // Event listener untuk radio buttons
    Array.prototype.forEach.call(radioButtons, function(radio) {
      radio.addEventListener('change', updateAnjuranVisibility);
    });

    // Tetapkan keadaan awal medan pilihan anjuran semasa halaman dimuat.
    updateAnjuranVisibility();

    if (formPermohonan) {
      formPermohonan.addEventListener('submit', function(e) {
      if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();

        if (window.Swal && typeof window.Swal.fire === 'function') {
          window.Swal.fire({
            icon: 'warning',
            title: 'Sila Lengkapkan Borang',
            text: 'Pastikan semua medan yang diperlukan telah diisi dengan betul.',
            confirmButtonText: 'Faham'
          });
        } else {
          alert('Sila lengkapkan borang dengan betul.');
        }
      }
      this.classList.add('was-validated');
      });
    }

    // Butang Batal dengan konfirmasi
    var btnBatal = document.getElementById('btnBatal');
    if (btnBatal) {
      btnBatal.addEventListener('click', function() {
        if (window.Swal && typeof window.Swal.fire === 'function') {
          window.Swal.fire({
            icon: 'question',
            title: 'Pembatalan Borang',
            text: 'Adakah anda pasti ingin membatalkan permohonan ini?',
            showCancelButton: true,
            confirmButtonText: 'Ya, Batalkan',
            cancelButtonText: 'Tidak, Lanjutkan',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d'
          }).then(function(result) {
            if (result.isConfirmed) {
              window.location.href = 'index.php';
            }
          });
        } else if (confirm('Adakah anda pasti ingin membatalkan permohonan ini?')) {
          window.location.href = 'index.php';
        }
      });
    }
  </script>
</body>
</html>