<?php
// eCetak — Laman Hadapan (Landing) sahaja
// Perpustakaan Sultan Badlishah | Universiti Teknologi MARA
// Nota: Tiada borang pada halaman ini. Butang CTA akan bawa ke halaman tempahan sebenar (contoh: permohonan.php)
?>
<!doctype html>
<html lang="ms">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>eCetak · Perpustakaan Sultan Badlishah</title>
  <meta name="description" content="eCetak ialah portal permohonan kebenaran cetakan untuk komuniti Perpustakaan Sultan Badlishah.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <link rel="stylesheet" href="css/custom.css">
</head>
<body class="index-page">
  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg border-bottom shadow-sm sticky-top">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="#">
        <!-- Gantikan src di bawah dengan laluan logo UiTM sebenar anda -->
        <img src="images/Logo.png" alt="Logo UiTM" class="brand-logo me-2" loading="lazy">
        <span class="fw-semibold">eCetak</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav ms-auto align-items-lg-center">
          <li class="nav-item"><a class="nav-link" href="#pengenalan">Pengenalan</a></li>
          <li class="nav-item"><a class="nav-link" href="#ciri">Ciri</a></li>
          <li class="nav-item"><a class="nav-link" href="#langkah">Cara Memohon</a></li>
          <li class="nav-item"><a class="btn btn-primary ms-lg-2" href="pages/login/"><i class="bi bi-box-arrow-in-right me-1"></i>Log Masuk</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- HERO -->
  <header class="hero py-5 py-md-5">
    <div class="container">
      <div class="row align-items-center g-4">
        <div class="col-lg-7" data-aos="fade-right">
          <span class="badge badge-soft rounded-pill mb-3"><i class="bi bi-stars me-1"></i> Mudah • Pantas • Tersusun</span>
          <h1 class="display-5 fw-bold mb-3">Permohonan Kebenaran Cetakan</h1>
          <p class="lead" id="pengenalan">
            eCetak memudahkan komuniti UiTM membuat permohonan kebenaran cetakan di Perpustakaan Sultan Badlishah.
            Pilih tetapan cetakan, tetapkan tarikh ambil, dan serahkan di kaunter — semua dengan lebih teratur.
          </p>
          <div class="d-flex gap-2 mt-3">
            <a href="carian_staf.php" class="btn btn-primary btn-lg"><i class="bi bi-send-fill me-1"></i>Buat Permohonan</a>
            <a href="semak_permohonan.php" class="btn btn-outline-primary btn-lg"><i class="bi bi-info-circle me-1"></i>Semak Status Permohonan</a>
          </div>
        </div>
        <div class="col-lg-5" data-aos="zoom-in" data-aos-delay="100">
          <div class="card glass shadow-soft border-0">
            <div class="card-body p-4">
              <div class="d-flex align-items-center mb-2">
                <div class="display-6 text-primary me-3"><i class="bi bi-printer"></i></div>
                <div>
                  <h5 class="mb-0">Permohonan Kebenaran Cetakan</h5>
                  <small class="text-muted">Nota:</small>
                </div>
              </div>
              <hr>
              <ul class="list-unstyled mb-0 small">
                <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i>Semua urusan percetakan adalah untuk tujuan rasmi universiti sahaja.</li>
                <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i>Pemohon perlu membawa jenis kertas yang diperlukan dan percetakan bahan dilaksanakan oleh pemohon.</li>
                <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i>Setiap permohonan perlu mendapat kelulusan dari Ketua PTJ/Unit masing-masing. Manakala setiap permohonan bagi Program Pelajar memerlukan lampiran surat kelulusan program.</li>
                <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i>Permohonan hendaklah dibuat sekurang-kurangnya <b>2 (dua) minggu</b> sebelum tarikh yang diperlukan.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- CIRI -->
  <section id="ciri" class="py-5">
    <div class="container">
      <div class="row mb-4">
        <div class="col-lg-8" data-aos="fade-up">
          <h2 class="section-title">Ciri Utama</h2>
          <p class="text-secondary">Antara muka moden menggunakan <strong>Bootstrap 5</strong> dan animasi ringan <strong>(AOS)</strong> untuk pengalaman yang licin.</p>
        </div>
      </div>
      <div class="row g-4">
        <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
          <div class="card h-100 shadow-soft border-0">
            <div class="card-body">
              <i class="bi bi-ui-checks-grid feature-icon"></i>
              <h5 class="mt-3">Mesra Pengguna</h5>
              <p class="text-secondary small">Maklumat dipersembahkan dengan jelas, mudah difahami sebelum anda membuat tempahan.</p>
            </div>
          </div>
        </div>
        <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
          <div class="card h-100 shadow-soft border-0">
            <div class="card-body">
              <i class="bi bi-shield-check feature-icon"></i>
              <h5 class="mt-3">Tersusun & Selamat</h5>
              <p class="text-secondary small">Aliran kerja yang kemas untuk meminimumkan kesilapan dan memudahkan pengesanan tempahan.</p>
            </div>
          </div>
        </div>
        <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
          <div class="card h-100 shadow-soft border-0">
            <div class="card-body">
              <i class="bi bi-rocket-takeoff feature-icon"></i>
              <h5 class="mt-3">Cepat Bermula</h5>
              <p class="text-secondary small">Hanya klik <em>Buat Tempahan</em> untuk memulakan; tiada pendaftaran rumit diperlukan.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- LANGKAH -->
  <section id="langkah" class="py-5 bg-white">
    <div class="container">
      <div class="row mb-4">
        <div class="col-lg-8" data-aos="fade-up">
          <h2 class="section-title">Cara Buat Permohonan</h2>
          <p class="text-secondary">Tiga langkah mudah untuk mendapatkan cetakan anda.</p>
        </div>
      </div>
      <div class="row g-4">
        <div class="col-md-4" data-aos="fade-up">
          <div class="h-100 p-4 border rounded-3 step-box">
            <div class="step-num mb-3">1</div>
            <h6>Pilih Tetapan</h6>
            <p class="text-secondary small mb-0">Pilih saiz kertas, warna, dan keperluan binding.</p>
          </div>
        </div>
        <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
          <div class="h-100 p-4 border rounded-3 step-box">
            <div class="step-num mb-3">2</div>
            <h6>Tetapkan Slot Ambil</h6>
            <p class="text-secondary small mb-0">Pilih tarikh dan masa ambilan yang sesuai.</p>
          </div>
        </div>
        <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
          <div class="h-100 p-4 border rounded-3 step-box">
            <div class="step-num mb-3">3</div>
            <h6>Serahkan di Kaunter</h6>
            <p class="text-secondary small mb-0">Tunjukkan nombor rujukan dan ambil hasil cetakan anda.</p>
          </div>
        </div>
      </div>
      <div class="text-center mt-4" data-aos="fade-up" data-aos-delay="250">
        <a class="btn btn-primary btn-lg" href="carian_staf.php"><i class="bi bi-plus-circle me-1"></i>Buat Permohonan</a>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="py-4">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-md-6 small text-secondary">
          © <?php echo date('Y'); ?> eCetak · Perpustakaan Sultan Badlishah
        </div>
        <div class="col-md-6 small text-md-end text-secondary">
          Antara muka dengan Bootstrap 5 & AOS
        </div>
      </div>
    </div>
  </footer>

  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init({ once:true, duration:700, easing:'ease-out-cubic' });
  </script>
</body>
</html>
