<?php
/**
 * carian_staf.php
 * - UI carian staf (real-time, tanpa DataTables)
 * - Endpoint JSON:
 *     1) ?action=search  -> pulangkan {data:[{nama, jawatan, bah_fak, token}], filtered}
 *     2) ?action=resolve -> WAJIB POST + CSRF, terima token, sahkan, pulangkan {no_pekerja, nama}
 *
 * PRIVASI:
 * - No. pekerja TIDAK dihantar semasa senarai carian.
 * - Token kini disulitkan (AES-256-GCM) jika openssl tersedia; fallback HMAC sedia ada.
 * - No. pekerja hanya dipulangkan selepas "resolve" yang sah (POST + CSRF).
 *
 * PERLU: /config/db_idata.php -> $pdo_idata (PDO).
 * 
 * Serasi: PHP 7.4+ | MySQL 5.7+ | FreeBSD 12+
 */

declare(strict_types=1);

// =============================
// 0) Session & Header asas
// =============================
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// CSRF token untuk AJAX (diguna di action=resolve)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF_TOKEN = $_SESSION['csrf_token'];

// =============================
// 1) Konfigurasi Token
// =============================

// **Disarankan** set dari environment / secret manager
$TOKEN_SECRET = getenv('IDATA_TOKEN_SECRET') ?: 'TUKAR_KE_RAHSIA_PANJANG_DAN_UNIK_32+Bait_MINIMA';

// Base64 URL-safe helpers
function b64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Decode base64 URL-safe (PHP 7 compatible)
 * @param string $data
 * @return string|false
 */
function b64url_decode($data) {
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'), true);
}

function crypto_available() {
    return function_exists('openssl_encrypt') && in_array('aes-256-gcm', openssl_get_cipher_methods());
}

/**
 * Token selamat:
 *  - Jika openssl tersedia: token = b64url(nonce) . '.' . b64url(ciphertext) . '.' . b64url(tag)
 *  - Jika tidak: fallback token = b64url(no_pekerja) . '.' . b64url(HMAC_SHA256(no_pekerja, SECRET))
 */
function make_token($no_pekerja, $secret) {
    if (crypto_available()) {
        $key = hash('sha256', $secret, true);         // 32 bytes
        $nonce = random_bytes(12);                    // GCM 96-bit nonce
        $tag = '';
        $ciphertext = openssl_encrypt(
            $no_pekerja,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',                                       // aad
            16                                        // tag length
        );
        if ($ciphertext === false || $tag === '') {
            // fallback HMAC jika gagal
            $sig = hash_hmac('sha256', $no_pekerja, $secret, true);
            return b64url_encode($no_pekerja) . '.' . b64url_encode($sig);
        }
        return b64url_encode($nonce) . '.' . b64url_encode($ciphertext) . '.' . b64url_encode($tag);
    } else {
        $sig = hash_hmac('sha256', $no_pekerja, $secret, true);
        return b64url_encode($no_pekerja) . '.' . b64url_encode($sig);
    }
}

/**
 * Nyah-token:
 *  - Cuba format AES-GCM (3 segmen). Jika gagal dan token ada 2 segmen, cuba mod HMAC.
 */
function resolve_token($token, $secret) {
    if (!is_string($token) || $token === '') return null;

    $parts = explode('.', $token);
    if (count($parts) === 3 && crypto_available()) {
        $p1 = $parts[0];
        $p2 = $parts[1];
        $p3 = $parts[2];
        
        $nonce = b64url_decode($p1);
        $ciphertext = b64url_decode($p2);
        $tag = b64url_decode($p3);
        if ($nonce === false || $ciphertext === false || $tag === false) return null;

        $key = hash('sha256', $secret, true);
        $plain = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '' // aad
        );
        if ($plain === false) return null;
        return $plain;
    }

    if (count($parts) === 2) {
        $p1 = $parts[0];
        $p2 = $parts[1];
        
        $no = b64url_decode($p1);
        $sig = b64url_decode($p2);
        if ($no === false || $sig === false) return null;
        $calc = hash_hmac('sha256', $no, $secret, true);
        if (function_exists('hash_equals')) {
            if (!hash_equals($calc, $sig)) return null;
        } else {
            if ($calc !== $sig) return null;
        }
        return $no;
    }

    return null;
}

// =============================
// 2) Utiliti & Rate limiting
// =============================
function json_headers_no_cache() {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function rate_limit($bucket, $limit, $windowSec = 60) {
    $now = time();
    if (!isset($_SESSION['rate'][$bucket])) {
        $_SESSION['rate'][$bucket] = ['start' => $now, 'count' => 0];
    }
    $entry = &$_SESSION['rate'][$bucket];
    if ($now - $entry['start'] >= $windowSec) {
        $entry = ['start' => $now, 'count' => 0];
    }
    $entry['count']++;
    if ($entry['count'] > $limit) {
        http_response_code(429);
        json_headers_no_cache();
        echo json_encode(['error' => 'Terlalu banyak permintaan. Sila cuba lagi sebentar.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function to_utf8($value) {
    if ($value === null) return null;
    if (is_string($value)) {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        }
    }
    return $value;
}

// =============================
// 3) Endpoint: RESOLVE (POST + CSRF)
// =============================
if (isset($_GET['action']) && $_GET['action'] === 'resolve') {
    json_headers_no_cache();
    // Rate-limit
    rate_limit('resolve', 30, 60);

    // Method enforcement
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Kaedah tidak dibenarkan. Gunakan POST.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // CSRF check
    $csrf_in = $_POST['csrf_token'] ?? '';
    if (!$csrf_in || !hash_equals($CSRF_TOKEN, $csrf_in)) {
        http_response_code(403);
        echo json_encode(['error' => 'Sesi tidak sah. Sila muat semula halaman.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $token = isset($_POST['token']) ? (string)$_POST['token'] : '';
    if ($token === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Token tidak diterima.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $no = resolve_token($token, $TOKEN_SECRET);
    if ($no === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Token tidak sah.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Sahkan kewujudan minimum dalam DB
    try {
        require_once __DIR__ . '/config/db_idata.php';
        if (isset($pdo_idata) && $pdo_idata instanceof PDO) {
            $pdo_idata->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo_idata->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } else {
            throw new RuntimeException('Sambungan pangkalan data tidak tersedia.');
        }

        $stmt = $pdo_idata->prepare('SELECT nama FROM t_staf WHERE no_pekerja = :no LIMIT 1');
        $stmt->bindValue(':no', $no, PDO::PARAM_STR);
        $stmt->execute();
        $nama = $stmt->fetchColumn();
        if ($nama !== false) $nama = to_utf8($nama);

        echo json_encode([
            'no_pekerja' => $no,
            'nama'       => $nama !== false ? $nama : null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        error_log('resolve error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Ralat pelayan. Sila cuba lagi.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// =============================
// 4) Endpoint: SEARCH (GET)
// =============================
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    json_headers_no_cache();
    // Rate-limit
    rate_limit('search', 60, 60);

    require_once __DIR__ . '/config/db_idata.php';
    try {
        if (isset($pdo_idata) && $pdo_idata instanceof PDO) {
            $pdo_idata->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo_idata->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } else {
            throw new RuntimeException('Sambungan pangkalan data tidak tersedia.');
        }

        $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
        // Validasi: benarkan A-Z a-z 0-9 - _ / , panjang 6-12
        if ($q === '' || !preg_match('/^[A-Za-z0-9\-_\/]{6,12}$/', $q)) {
            echo json_encode(['filtered' => 0, 'data' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Carian padanan tepat (guna indeks no_pekerja), limit 1
        $stmt = $pdo_idata->prepare('
            SELECT no_pekerja, nama, jawatan, bah_fak
            FROM t_staf
            WHERE no_pekerja = :kw
            LIMIT 1
        ');
        $stmt->bindValue(':kw', $q, PDO::PARAM_STR);
        $stmt->execute();
        $rows = [];
        while ($row = $stmt->fetch()) {
            $no  = isset($row['no_pekerja']) ? (string)$row['no_pekerja'] : '';
            $tok = $no !== '' ? make_token($no, $TOKEN_SECRET) : null;

            $rows[] = [
                'nama'    => to_utf8($row['nama']   ?? null),
                'jawatan' => to_utf8($row['jawatan']?? null),
                'bah_fak' => to_utf8($row['bah_fak']?? null),
                'token'   => $tok,
            ];
        }

        echo json_encode([
            'filtered' => count($rows),
            'data'     => $rows
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        error_log('search error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Ralat pelayan. Sila cuba lagi.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// =============================
// 5) UI Halaman (HTML)
// =============================
?>
<!doctype html>
<html lang="ms">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Carian Data Staf</title>

  <!-- CSRF untuk AJAX -->
  <meta name="csrf-token" content="<?= htmlspecialchars($CSRF_TOKEN, ENT_QUOTES, 'UTF-8') ?>">

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- AOS CSS -->
  <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

  <!-- SweetAlert2 Theme -->
  <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4@5/bootstrap-4.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="css/custom.css">
</head>
<body class="carian-staf-page">
  <div class="container py-4 py-lg-5">
    <header class="brand-hero p-4 p-lg-5 mb-4" data-aos="fade-up" data-aos-duration="700">
      <div class="row align-items-center g-4">
        <div class="col-lg-8">
          <h1 class="h2 h1-lg fw-bold mb-2">Carian Data Staf</h1>
          <p class="mb-0">Masukkan <strong>No. Pekerja (≥ 6 aksara)</strong> untuk carian. <em>Carian akan bermula automatik apabila 6 aksara diisi.</em></p>
        </div>
        <div class="col-lg-4 text-lg-end">
          <!-- ruang tambahan -->
        </div>
      </div>
    </header>

    <!-- Kotak carian -->
    <section class="search-card p-3 p-lg-4 mb-4" data-aos="fade-up" data-aos-duration="700" data-aos-delay="100">
      <div class="row g-3 align-items-center">
        <div class="col-md-6">
          <label for="q" class="form-label mb-1">No. Pekerja (6–12 aksara)</label>
          <input type="text" id="q" class="form-control form-control-lg" placeholder="Contoh: 801234" autocomplete="off" autofocus maxlength="12" pattern="^[A-Za-z0-9\-_\/]{6,12}$">
        </div>
        <div class="col-md-2 d-flex gap-2 mt-1 pt-4">
          <button id="btnClear" class="btn btn-outline-secondary btn-lg flex-fill" type="button">Kosongkan</button>
          <!-- <button id="btnRefresh" class="btn btn-gradient btn-lg flex-fill" type="button">Segar Semula</button> -->
        </div>
      </div>
    </section>

    <!-- Info ringkas hasil -->
    <section id="summaryWrap" class="mb-2 hide" data-aos="fade-up" data-aos-duration="600" data-aos-delay="120">
      <div class="alert alert-light border" role="alert">
        <span id="summaryText">Menemui 0 rekod.</span>
      </div>
    </section>

    <!-- Hasil carian -->
    <section id="resultWrap" class="search-card p-2 p-lg-3 hide" data-aos="fade-up" data-aos-duration="700" data-aos-delay="150">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="tblHasil">
          <thead>
            <tr>
              <th style="min-width:220px;">Nama</th>
              <th style="min-width:180px;">Jawatan</th>
              <th style="min-width:220px;">Bahagian / Fakulti</th>
              <th style="min-width:120px;">Tindakan</th>
            </tr>
          </thead>
          <tbody id="tblBody"></tbody>
        </table>
      </div>
      <div id="noData" class="text-center text-muted py-3 hide">Tiada rekod ditemui.</div>
    </section>

    <footer class="mt-4 text-center">
      <small class="footer-note">© 2026 eCetak · Perpustakaan Sultan Badlishah</small>
    </footer>
  </div>

  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <!-- AOS -->
  <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.min.js"></script>
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    AOS.init();

    // Debounce helper
    function debounce(fn, wait) {
      var t;
      return function(){
        clearTimeout(t);
        t = setTimeout(function(){ fn.apply(this, arguments); }, wait);
      };
    }

    // Escape HTML (guna jQuery)
    function esc(s){ return $('<div>').text(s == null ? '' : s).html(); }

    // Render jadual
    function renderTable(rows){
      var $tbody = $('#tblBody');
      $tbody.empty();

      if(!rows || rows.length === 0){
        $('#noData').removeClass('hide');
        return;
      }
      $('#noData').addClass('hide');

      for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        var nama    = esc(row.nama || '');
        var jawatan = esc(row.jawatan || '-');
        var bah     = esc(row.bah_fak || '-');
        var token   = row.token ? esc(row.token) : '';

        var tr = '<tr>' +
          '<td>' + nama + '</td>' +
          '<td>' + (jawatan || '-') + '</td>' +
          '<td>' + (bah || '-') + '</td>' +
          '<td class="text-nowrap">' +
          '<button class="btn btn-gradient btn-sm btnPilih" ' +
          'data-token="' + token + '" ' +
          'data-nama="' + nama + '" ' +
          'data-jawatan="' + jawatan + '" ' +
          'data-bah="' + bah + '">Pilih</button>' +
          '</td>' +
          '</tr>';
        $tbody.append(tr);
      }
    }

    // Update ringkasan
    function updateSummary(filtered, q){
      var summary = (q && q.trim() !== '')
        ? 'Menemui <strong>' + filtered + '</strong> rekod untuk No. Pekerja: <em>"' + esc(q) + '"</em>.'
        : 'Menemui <strong>' + filtered + '</strong> rekod.';
      $('#summaryText').html(summary);
    }

    // Panggil endpoint - carian masa nyata (≥ 6 aksara)
    var doSearch = debounce(function(){
      var q = $('#q').val().trim();

      if(q.length < 6){
        $('#resultWrap').addClass('hide');
        $('#summaryWrap').addClass('hide');
        $('#tblBody').empty();
        $('#noData').addClass('hide');
        return;
      }

      $.ajax({
        url: 'carian_staf.php',
        method: 'GET',
        dataType: 'json',
        data: { action: 'search', q: q },
        success: function(res){
          if(res && res.error){
            Swal.fire({ icon: 'error', title: 'Ralat', text: res.error });
            return;
          }
          var data = Array.isArray(res.data) ? res.data : [];
          var filtered = parseInt(res.filtered || data.length, 10) || data.length;

          renderTable(data);
          updateSummary(filtered, q);
          $('#summaryWrap').removeClass('hide');
          $('#resultWrap').removeClass('hide');
        },
        error: function(xhr){
          var msg = 'Ralat tidak diketahui.';
          try{
            var r = JSON.parse(xhr.responseText);
            if(r && r.error) msg = r.error;
          }catch(e){}
          Swal.fire({ icon: 'error', title: 'Ralat', text: msg });
        }
      });
    }, 300);

    // Event taip
    $('#q').on('keyup', doSearch);

    // Butang kosongkan
    $('#btnClear').on('click', function(){
      $('#q').val('');
      $('#tblBody').empty();
      $('#resultWrap').addClass('hide');
      $('#summaryWrap').addClass('hide');
      $('#noData').addClass('hide');
      $('#q').trigger('focus');
    });

    // Butang segar semula
    $('#btnRefresh').on('click', function(){ doSearch(); });

    // Tindakan "Pilih" -> resolve token (POST + CSRF) -> redirect ke permohonan.php (POST)
    $(document).on('click', '.btnPilih', function(){
      var token = $(this).data('token') || '';
      if(!token){
        Swal.fire({ icon: 'error', title: 'Ralat', text: 'Token tidak dijumpai.' });
        return;
      }

      var csrf = $('meta[name="csrf-token"]').attr('content') || '';

      $.ajax({
        url: 'carian_staf.php?action=resolve',
        method: 'POST',
        dataType: 'json',
        data: { token: token, csrf_token: csrf },
        success: function(res){
          if(res && res.error){
            Swal.fire({ icon: 'error', title: 'Ralat', text: res.error });
            return;
          }
          var no = res.no_pekerja || '';
          if(!no){
            Swal.fire({ icon: 'error', title: 'Ralat', text: 'No. Pekerja tidak ditemui.' });
            return;
          }

          // Buat form tersembunyi dan submit ke permohonan.php
          var form = $('<form method="POST" action="permohonan.php" style="display:none;"></form>');
          form.append($('<input type="hidden" name="no_pekerja">').val(no));
          $('body').append(form);
          form.submit();
        },
        error: function(xhr){
          var msg = 'Ralat tidak diketahui.';
          try{
            var r = JSON.parse(xhr.responseText);
            if(r && r.error) msg = r.error;
          }catch(e){}
          Swal.fire({ icon: 'error', title: 'Ralat', text: msg });
        }
      });
    });
  </script>
</body>
</html>