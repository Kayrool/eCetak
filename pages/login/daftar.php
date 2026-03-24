<?php
/**
 * daftar.php
 * Pendaftaran pentadbir sistem (default role = 'PENTADBIR')
 * - Carian staf dari idata.t_staf (latin1) berdasarkan no_pekerja / nama (LIKE, limit 10)
 * - Pendaftaran ke ecetak.users (utf8mb4) dengan role 'PENTADBIR' secara default
 * Serasi: PHP <= 7.4, MySQL 5.6, Apache 2.4 (FreeBSD)
 */

// -----------------------------
// (0) Session & cookies
// -----------------------------
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict'
    ]);
} else {
    session_set_cookie_params(0, '/; samesite=Strict', '', $secure, true);
}
session_start();

// -----------------------------
// (1) Security headers (CSP + nonce)
// -----------------------------
$cspNonce = base64_encode(random_bytes(16));
$scriptSrc  = ["'self'", "https://code.jquery.com", "https://cdn.jsdelivr.net", "https://cdnjs.cloudflare.com"];
$styleSrc   = ["'self'", "'unsafe-inline'", "https://cdn.jsdelivr.net", "https://cdnjs.cloudflare.com"];
$fontSrc    = ["'self'", "data:", "https://cdnjs.cloudflare.com"];
$imgSrc     = ["'self'", "data:"];
$connectSrc = ["'self'"]; // AJAX ke origin sendiri

$csp = "default-src 'self'; ".
       "script-src ".implode(' ', $scriptSrc)." 'nonce-{$cspNonce}'; ".
       "style-src ".implode(' ', $styleSrc)."; ".
       "img-src ".implode(' ', $imgSrc)."; ".
       "font-src ".implode(' ', $fontSrc)."; ".
       "connect-src ".implode(' ', $connectSrc)."; ".
       "frame-ancestors 'none'; base-uri 'self'; form-action 'self'";

header("Content-Security-Policy: $csp");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");
if ($secure) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// -----------------------------
// (2) Import DB connections
// -----------------------------
require_once __DIR__ . '/../../config/db.php';       // $pdo (ecetak, utf8mb4)
require_once __DIR__ . '/../../config/db_idata.php'; // $pdo_idata (idata, latin1)
try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (\Throwable $e) {}
try { $pdo_idata->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (\Throwable $e) {}

// Semak samada terdapat pengguna PENTADBIR sedia ada
try {
    $chkAdmin = $pdo->prepare("SELECT id FROM users WHERE role = 'PENTADBIR' LIMIT 1");
    $chkAdmin->execute();
    if ($chkAdmin->fetch(PDO::FETCH_ASSOC)) {
        // Jika sudah ada, alihkan ke halaman login dengan notifikasi sweetalert2
        header('Location: index.php?msg=admin_exists'); exit;
    }
} catch (\Throwable $e) {
    // Teruskan jika ada ralat (anggap tiada pentadbir)
}

// -----------------------------
// (3) Utiliti (CSRF, rate, charset, json)
// -----------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_ok($t) { return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$t); }
function rate_limit($key, $limit=40, $window=60) {
    $now = time();
    if (empty($_SESSION['rate'][$key])) $_SESSION['rate'][$key] = [];
    $_SESSION['rate'][$key] = array_filter($_SESSION['rate'][$key], function($ts) use($now,$window){ return $ts > $now - $window; });
    if (count($_SESSION['rate'][$key]) >= $limit) return false;
    $_SESSION['rate'][$key][] = $now; return true;
}
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function str_len($s){ return function_exists('mb_strlen') ? mb_strlen((string)$s, 'UTF-8') : strlen((string)$s); }
function normalize_no_pekerja($s){ return preg_replace('/\s+/', '', trim((string)$s)); }
function is_valid_no_pekerja($s){ return (bool)preg_match('/^[A-Za-z0-9-]{3,20}$/', (string)$s); }
function normalize_email($s){ return strtolower(trim((string)$s)); }
function is_valid_email($s){ return (bool)filter_var((string)$s, FILTER_VALIDATE_EMAIL); }
// Tukar latin1 -> UTF-8 untuk output & simpan ke ecetak
function to_utf8($val) {
    if (is_array($val)) { foreach ($val as $k=>$v) $val[$k] = to_utf8($v); return $val; }
    if ($val === null) return null;
    $res = @iconv('ISO-8859-1', 'UTF-8//TRANSLIT', (string)$val);
    return $res === false ? (string)$val : $res;
}
function json_out($data, $code=200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  echo json_encode($data, JSON_UNESCAPED_UNICODE); exit;
}

// -----------------------------
// (4) Dapatkan role enum dari ecetak.users (untuk verifikasi sahaja)
// -----------------------------
function get_role_options(PDO $pdo) {
    $roles = [];
    try {
        $rs = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
        if ($rs && !empty($rs['Type']) && preg_match("/^enum\\((.*)\\)$/i", $rs['Type'], $m)) {
            foreach (preg_split("/\\s*,\\s*/", $m[1]) as $p) {
                $p = trim($p, " '\"");
                if ($p !== '') $roles[] = $p;
            }
        }
    } catch (\Throwable $e) {
        $roles = ['PENTADBIR','PENYELIA','KETUA BAHAGIAN','KETUA PERPUSTAKAAN','REKTOR']; // fallback
    }
    return $roles;
}

// -----------------------------
// (5) Endpoint AJAX: SEARCH (idata.t_staf)
// -----------------------------
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = $_REQUEST['action'] ?? null;

if ($action === 'search' && $method === 'GET') {
  if (!rate_limit('search-daftar', 60, 60)) json_out(['ok'=>false,'msg'=>'Terlalu banyak permintaan.'], 429);

  if (!isset($pdo_idata) || !($pdo_idata instanceof PDO)) {
    json_out(['ok'=>false,'msg'=>'Sambungan DB idata tidak tersedia.'], 500);
  }

  try { $pdo_idata->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (\Throwable $e) {}
  try { $pdo_idata->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); } catch (\Throwable $e) {}

  $q = trim((string)($_GET['q'] ?? ''));
  if ($q === '' || !preg_match('/^[A-Za-z0-9\-_\/]{6,12}$/', $q)) {
    json_out(['ok'=>true,'data'=>[]]);
  }

    try {
        // Padanan tepat pada no_pekerja (ikut carian_staf.php)
        $stmt = $pdo_idata->prepare("
          SELECT no_pekerja, nama, jawatan, emel, unit_jab, bah_fak, gred
          FROM t_staf
          WHERE no_pekerja = :kw
          LIMIT 1
        ");
        $stmt->bindParam(':kw', $q, PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Tukar ke UTF-8 untuk hantar ke UI
        $out = [];
        foreach ($rows as $r) {
            $out[] = to_utf8([
                'no_pekerja' => $r['no_pekerja'] ?? '',
                'nama'       => $r['nama'] ?? '',
                'jawatan'    => $r['jawatan'] ?? '',
                'emel'       => $r['emel'] ?? '',
                'unit_jab'   => $r['unit_jab'] ?? '',
                'bah_fak'    => $r['bah_fak'] ?? '',
                'gred'       => $r['gred'] ?? ''
            ]);
        }
        json_out(['ok'=>true,'data'=>$out]);
    } catch (\Throwable $e) {
        error_log('SEARCH ERR: '.$e->getMessage());
        json_out(['ok'=>false,'msg'=>'Ralat carian.'], 500);
    }
}

// -----------------------------
// (6) Endpoint AJAX: REGISTER (default role = 'PENTADBIR')
// -----------------------------
if ($method === 'POST' && $action === 'register') {
    if (!rate_limit('register-daftar', 20, 60)) json_out(['ok'=>false,'msg'=>'Terlalu banyak pendaftaran. Cuba lagi.'], 429);
    $token = $_POST['csrf'] ?? '';
    if (!csrf_ok($token)) json_out(['ok'=>false,'msg'=>'Token tidak sah.'], 400);

    $no_pekerja = normalize_no_pekerja($_POST['no_pekerja'] ?? '');
    $emel       = normalize_email($_POST['emel'] ?? '');

    if ($no_pekerja === '' || $emel === '') {
      json_out(['ok'=>false,'msg'=>'Data tidak lengkap.'], 400);
    }
    if (!is_valid_no_pekerja($no_pekerja)) {
      json_out(['ok'=>false,'msg'=>'No. pekerja tidak sah.'], 400);
    }
    if (!is_valid_email($emel)) {
      json_out(['ok'=>false,'msg'=>'Emel tidak sah.'], 400);
    }

    try {
        // Tetapkan DEFAULT role = 'PENTADBIR' di kod
        $role = 'PENTADBIR';

        // Sahkan role wujud dalam enum (jika tidak, tetap cuba insert dan/or bagi mesej jelas)
        $validRoles = get_role_options($pdo);
        if (!in_array($role, $validRoles, true)) {
            // Jika enum DB belum ada 'PENTADBIR', berikan mesej bantuan
            json_out(['ok'=>false,'msg'=>"Peranan 'PENTADBIR' tiada dalam enum jadual users. Sila kemas kini skema atau tambah nilai enum tersebut."], 400);
        }

        // Ambil semula dari idata.t_staf (latin1) berdasarkan no_pekerja
        $s = $pdo_idata->prepare("SELECT no_pekerja, nama, no_kp, jawatan, bah_fak, emel FROM t_staf WHERE no_pekerja = :no LIMIT 1");
        $s->execute([':no' => $no_pekerja]);
        $staf = $s->fetch(PDO::FETCH_ASSOC);
        if (!$staf) json_out(['ok'=>false,'msg'=>'Staf tidak ditemui.'], 404);

        // Semak duplikasi pada ecetak.users (ikut no_pekerja)
        $chk = $pdo->prepare("SELECT id FROM users WHERE no_pekerja = :no LIMIT 1");
        $chk->execute([':no' => $no_pekerja]);
        if ($chk->fetch(PDO::FETCH_ASSOC)) json_out(['ok'=>false,'msg'=>'Pengguna telah wujud.'], 409);

        // Jana password berdasarkan no_kp (12 aksara)
        $no_kp = preg_replace('/\D/', '', $staf['no_kp'] ?? '');
        if (strlen($no_kp) < 12) {
            // Jika no_kp kurang 12 aksara, tambah rawak di hujung
            $needed = 12 - strlen($no_kp);
            $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
            for ($i=0; $i<$needed; $i++) {
                $no_kp .= $chars[random_int(0, strlen($chars)-1)];
            }
        } else {
            $no_kp = substr($no_kp, 0, 12);
        }
        $tmpPass = $no_kp;
        $hash = password_hash($tmpPass, PASSWORD_BCRYPT);

        // Tukar nilai latin1 -> UTF-8 sebelum insert ke ecetak (utf8mb4)
        $nama    = to_utf8($staf['nama'] ?? '');
        $jawatan = to_utf8($staf['jawatan'] ?? null);
        $bah_fak = to_utf8($staf['bah_fak'] ?? '');
        $nopek   = to_utf8($staf['no_pekerja'] ?? '');
        // emel guna input (disahkan) tetapi fallback ke sumber jika kosong
        $emel_final = $emel !== '' ? $emel : normalize_email(to_utf8($staf['emel'] ?? ''));
        if (!is_valid_email($emel_final)) {
          json_out(['ok'=>false,'msg'=>'Emel tidak sah.'], 400);
        }

        // Insert
        $ins = $pdo->prepare("
            INSERT INTO users (no_pekerja, nama, jawatan, bah_fak, password_hash, role, aktif, emel)
            VALUES (:no_pekerja, :nama, :jawatan, :bah_fak, :password_hash, :role, 1, :emel)
        ");
        $ins->execute([
            ':no_pekerja'   => $nopek,
            ':nama'         => $nama,
            ':jawatan'      => $jawatan,
            ':bah_fak'      => $bah_fak,
            ':password_hash'=> $hash,
            ':role'         => $role,
            ':emel'         => $emel_final
        ]);

        // Pulangkan JSON sahaja; client akan urus alert + redirect
        json_out([
            'ok'            => true,
            'msg'           => 'Pentadbir berjaya didaftarkan.',
            'temp_password' => $tmpPass,
            'next'          => 'index.php'
        ], 201);
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            json_out(['ok'=>false,'msg'=>'Pendaftaran gagal: Kemungkinan konflik indeks unik (cth. pada nama). Pertimbang jadikan no_pekerja unik.'], 409);
        }
        error_log('REGISTER DB ERR: '.$ex->getMessage());
        json_out(['ok'=>false,'msg'=>'Ralat pendaftaran (DB).'], 500);
    } catch (\Throwable $e) {
        error_log('REGISTER ERR: '.$e->getMessage());
        json_out(['ok'=>false,'msg'=>'Ralat pendaftaran.'], 500);
    }
}

// -----------------------------
// (7) Render UI (GET) – Fokus untuk daftar PENTADBIR dahulu
// -----------------------------
?>
<!doctype html>
<html lang="ms">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pendaftaran Pentadbir</title>
  <meta name="csrf-token" content="<?php echo e($_SESSION['csrf_token']); ?>">

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- AOS CSS (opsyenal animasi ringan) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">

  <style>
    body { background:#f6f8fb; }
    .search-card { border:0; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,.06); }
    .table thead th { background:#eef2f7; }
    /* Fallback: pastikan kandungan dipaparkan jika AOS tidak berfungsi */
    .aos-fallback [data-aos] { opacity: 1 !important; transform: none !important; }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
      <span class="navbar-brand"><i class="fa-solid fa-user-shield me-2"></i> Daftar Pentadbir</span>
    </div>
  </nav>

  <main class="container py-4">
    <div class="row justify-content-center">
      <div class="col-lg-10">

        <div class="alert alert-info" data-aos="fade-down">
          Sistem baru: sila daftar sekurang-kurangnya <strong>seorang Pentadbir</strong> terlebih dahulu.
        </div>

        <div class="card search-card mb-4" data-aos="fade-up">
          <div class="card-body">
            <h5 class="card-title mb-2"><i class="fa-solid fa-magnifying-glass me-2"></i> Carian Staf</h5>
            <p class="text-muted mb-3">Taip <strong>No. Pekerja 6–12 aksara</strong>. Carian akan bermula automatik selepas 6 aksara.</p>
            <div class="input-group input-group-lg">
              <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
              <input type="text" id="q" class="form-control" placeholder="Contoh: 801234" autocomplete="off" maxlength="12" pattern="^[A-Za-z0-9\-_\/]{6,12}$">
            </div>
          </div>
        </div>

        <div class="card search-card" data-aos="fade-up" data-aos-delay="100">
          <div class="card-body">
            <div class="d-flex align-items-center mb-2">
              <h6 class="mb-0"><i class="fa-solid fa-list me-2"></i> Keputusan (maks 10)</h6>
              <div class="ms-auto"><span class="badge bg-secondary" id="result-count">0</span></div>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>No. Pekerja</th>
                    <th>Nama</th>
                    <th>Jawatan</th>
                    <th>Gred</th>
                    <th>Bah/Fak</th>
                    <th class="text-center">Tindakan</th>
                  </tr>
                </thead>
                <tbody id="result-body">
                  <tr><td colspan="8" class="text-center text-muted py-4">Tiada data.</td></tr>
                </tbody>
              </table>
            </div>
            <div class="small text-muted">* Pendaftaran akan menetapkan peranan <strong>PENTADBIR</strong> secara automatik.</div>
          </div>
        </div>

      </div>
    </div>
  </main>

  <footer class="py-4 text-center text-muted">
    <small>&copy; <?php echo date('Y'); ?> • Modul Pendaftaran Pentadbir</small>
  </footer>

  <!-- jQuery -->
  <script nonce="<?php echo $cspNonce; ?>" src="https://code.jquery.com/jquery-3.6.4.min.js" crossorigin="anonymous"></script>
  <!-- Bootstrap JS -->
  <script nonce="<?php echo $cspNonce; ?>" src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <!-- AOS JS -->
  <script nonce="<?php echo $cspNonce; ?>" src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.min.js" crossorigin="anonymous"></script>
  <!-- SweetAlert2 -->
  <script nonce="<?php echo $cspNonce; ?>" src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script nonce="<?php echo $cspNonce; ?>">
    if (window.AOS) {
      AOS.init({ once:true });
    } else {
      document.body.classList.add('aos-fallback');
    }

    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function debounce(fn, ms){ let t=null; return function(...a){ clearTimeout(t); t=setTimeout(()=>fn.apply(this,a), ms); }; }
    function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

    function renderRows(list){
      const tbody = document.getElementById('result-body');
      const count = document.getElementById('result-count');
      tbody.innerHTML=''; count.textContent = list.length;
      if (!list || list.length===0){
        tbody.innerHTML='<tr><td colspan="8" class="text-center text-muted py-4">Tiada data.</td></tr>'; return;
      }
      for (const r of list){
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${escapeHtml(r.no_pekerja||'')}</td>
          <td>${escapeHtml(r.nama||'')}</td>
          <td>${escapeHtml(r.jawatan||'')}</td>
          <td>${escapeHtml(r.gred||'')}</td>
          <td>${escapeHtml(r.bah_fak||'')}</td>
          <td class="text-center">
            <button class="btn btn-sm btn-primary btn-pilih"><i class="fa-solid fa-user-check me-1"></i> Daftar Pentadbir</button>
          </td>`;
        tr.querySelector('.btn-pilih').addEventListener('click', () => daftarPentadbir(r));
        tbody.appendChild(tr);
      }
    }

    var baseUrl = window.location.href.split('#')[0].split('?')[0];

    function formEncode(data){
      const parts = [];
      for (const k in data) {
        if (!Object.prototype.hasOwnProperty.call(data, k)) continue;
        parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(String(data[k])));
      }
      return parts.join('&');
    }

    function postForm(url, data, onSuccess, onError){
      var xhr = new XMLHttpRequest();
      xhr.open('POST', url, true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.timeout = 15000;
      xhr.onload = function(){
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            const resp = JSON.parse(xhr.responseText);
            onSuccess(resp);
          } catch (e) {
            onError();
          }
        } else {
          onError();
        }
      };
      xhr.onerror = onError;
      xhr.ontimeout = onError;
      xhr.send(formEncode(data));
    }

    function getJson(url, onSuccess, onError){
      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.timeout = 15000;
      xhr.onload = function(){
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            var resp = JSON.parse(xhr.responseText);
            onSuccess(resp);
          } catch (e) {
            onError();
          }
        } else {
          onError();
        }
      };
      xhr.onerror = onError;
      xhr.ontimeout = onError;
      xhr.send(null);
    }

    const doSearch = debounce(function(){
      const q = document.getElementById('q').value.trim();
      if (q.length < 6){ renderRows([]); return; }
      var url = baseUrl + '?action=search&q=' + encodeURIComponent(q) + '&_ts=' + Date.now();
      getJson(
        url,
        function(resp){
          if (resp && resp.ok) {
            renderRows(resp.data||[]);
          } else {
            renderRows([]);
            if (resp && resp.msg) {
              if (window.Swal) { Swal.fire({icon:'error', title:'Ralat', text:resp.msg}); }
              else { alert(resp.msg); }
            }
          }
        },
        function(){
          renderRows([]);
          if (window.Swal) { Swal.fire({icon:'error', title:'Ralat', text:'Gagal memproses carian.'}); }
          else { alert('Gagal memproses carian.'); }
        }
      );
    }, 350);
    document.getElementById('q').addEventListener('input', doSearch);
    document.getElementById('q').addEventListener('keyup', doSearch);

    function daftarPentadbir(row){
      Swal.fire({
        title: 'Sahkan Pendaftaran Pentadbir',
        html: `
          <div class="text-start">
            <div class="mb-2"><strong>No Pekerja:</strong> ${escapeHtml(row.no_pekerja||'')}</div>
            <div class="mb-2"><strong>Nama:</strong> ${escapeHtml(row.nama||'')}</div>
            <div class="mb-2"><strong>Jawatan:</strong> ${escapeHtml(row.jawatan||'-')}</div>
            <div class="mb-2"><strong>Bah/Fak:</strong> ${escapeHtml(row.bah_fak||'')}</div>
            <label class="form-label mt-2">Emel</label>
            <input id="sw-emel" type="email" class="form-control" value="${escapeHtml(row.emel||'')}" placeholder="nama@domain.com">
            <div class="form-text mt-1">Peranan akan ditetapkan sebagai <strong>PENTADBIR</strong> secara automatik.</div>
          </div>
        `,
        focusConfirm:false,
        showCancelButton:true,
        confirmButtonText:'Daftar sebagai Pentadbir',
        cancelButtonText:'Batal',
        preConfirm: ()=>{
          const emel = document.getElementById('sw-emel').value.trim();
          if (!emel || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emel)){
            Swal.showValidationMessage('Sila masukkan emel yang sah.');
            return false;
          }
          return { emel };
        }
      }).then(res=>{
        if (!res.isConfirmed) return;
        const { emel } = res.value;
        postForm(
          baseUrl,
          { action:'register', csrf:CSRF, no_pekerja:row.no_pekerja, emel:emel },
          function(resp){
            if (resp && resp.ok){
              const next = resp.next || 'index.php';
              const pwd  = resp.temp_password || '';
              Swal.fire({
                icon: 'success',
                title: 'Berjaya',
                html: `
                  Pentadbir telah didaftarkan.<br>
                  <small>Password sementara:</small>
                  <div class="mt-2"><code id="pwd">${escapeHtml(pwd)}</code></div>
                  <button id="copy-pwd" class="btn btn-sm btn-outline-secondary mt-2">Salin</button>
                  <div class="mt-2 text-muted"><small>Serahkan kepada pengguna & minta tukar pada login pertama.</small></div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Terus ke Login',
                cancelButtonText: 'Tutup',
                timer: 5000,
                timerProgressBar: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                  const btn = document.getElementById('copy-pwd');
                  if (btn) {
                    btn.addEventListener('click', () => {
                      if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(pwd).then(() => { btn.textContent = 'Disalin'; });
                      } else {
                        const tmp = document.createElement('textarea');
                        tmp.value = pwd; document.body.appendChild(tmp);
                        tmp.select(); document.execCommand('copy'); document.body.removeChild(tmp);
                        btn.textContent = 'Disalin';
                      }
                    });
                  }
                }
              }).then((r) => {
                if (r.isConfirmed || r.dismiss === Swal.DismissReason.timer) {
                  window.location.replace(next);
                }
              });
            } else {
              Swal.fire({icon:'warning', title:'Makluman', text:(resp&&resp.msg)?resp.msg:'Gagal mendaftar.'});
            }
          },
          function(){ Swal.fire({icon:'error', title:'Ralat', text:'Gagal memproses pendaftaran.'}); }
        );
      });
    }
  </script>
</body>
</html>