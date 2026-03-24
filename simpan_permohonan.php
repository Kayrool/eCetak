<?php
/**
 * simpan_permohonan.php
 * - Terima data permohonan dari borang permohonan.php
 * - Simpan ke database ecetak (table permohonan)
 * - Papar notifikasi dan redirect ke semak_permohonan.php
 * 
 * Penambahbaikan:
 * - CSRF protection (wajib tambah token pada borang).
 * - Validasi & sanitasi input (XSS/SQL injection/format).
 * - Emel header lebih selamat, elak header injection.
 * - SweetAlert helper & pengendalian ralat lebih kemas.
 */

declare(strict_types=1);

// Mulakan sesi untuk CSRF
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Header asas
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Include database connection (ecetak database)
require_once __DIR__ . '/config/db.php';

// Include database connection (idata database)
require_once __DIR__ . '/config/db_idata.php';

// (Opsyenal) Pastikan PDO di set throw exception jika belum
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    if (isset($pdo_idata) && $pdo_idata instanceof PDO) {
        $pdo_idata->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo_idata->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
} catch (Throwable $t) {
    error_log('PDO attribute set error: ' . $t->getMessage());
}

/** ---------------------------
 *  Helper untuk SweetAlert
 *  ---------------------------
 */
function swal_and_redirect(string $icon, string $title, string $text, ?string $redirectUrl = null, bool $goBack = false): void {
    // Elak XSS: guna json_encode untuk string JS
    $icon_js = json_encode($icon, JSON_UNESCAPED_UNICODE);
    $title_js = json_encode($title, JSON_UNESCAPED_UNICODE);
    $text_js  = json_encode($text, JSON_UNESCAPED_UNICODE);
    $redirect_js = $redirectUrl !== null ? json_encode($redirectUrl, JSON_UNESCAPED_SLASHES) : 'null';

    ?>
    <!doctype html>
    <html lang="ms">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> - eCetak</title>
      <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4@5/bootstrap-4.min.css" rel="stylesheet">
    </head>
    <body>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script>
        Swal.fire({
          icon: <?= $icon_js ?>,
          title: <?= $title_js ?>,
          text: <?= $text_js ?>,
          confirmButtonText: <?= json_encode($goBack ? 'Kembali' : ($redirectUrl ? 'Teruskan' : 'OK')) ?>,
          allowOutsideClick: false
        }).then(() => {
          <?php if ($redirectUrl !== null): ?>
            window.location.href = <?= $redirect_js ?>;
          <?php elseif ($goBack): ?>
            window.history.back();
          <?php else: ?>
            // do nothing
          <?php endif; ?>
        });
      </script>
    </body>
    </html>
    <?php
    exit;
}

/** ---------------------------
 *  Validasi Method & CSRF
 *  ---------------------------
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    swal_and_redirect('error', 'Ralat', 'Kaedah permintaan tidak sah.', 'index.php');
}

// Semak CSRF token
$csrf_post = $_POST['csrf_token'] ?? '';
$csrf_sess = $_SESSION['csrf_token'] ?? '';
if (!$csrf_post || !$csrf_sess || !hash_equals($csrf_sess, $csrf_post)) {
    http_response_code(403);
    swal_and_redirect('error', 'Ralat Keselamatan', 'Sesi tidak sah atau telah luput. Sila cuba hantar semula borang.', 'permohonan.php');
}

/** ---------------------------
 *  Helper Validasi Input
 *  ---------------------------
 */
function sanitize_text(string $v, int $maxLen = 255): string {
    $v = trim($v);
    // buang tag HTML & normalisasi whitespace
    $v = preg_replace('/\s+/u', ' ', strip_tags($v));
    if ($v === null) $v = '';
    // hadkan panjang
    if (mb_strlen($v, 'UTF-8') > $maxLen) {
        $v = mb_substr($v, 0, $maxLen, 'UTF-8');
    }
    return $v;
}
function validate_no_pekerja(string $v): ?string {
    $v = strtoupper(trim($v));
    // Benar: A-Z, 0-9, -, _, /
    if ($v === '' || !preg_match('/^[A-Z0-9\-_\/]+$/', $v)) {
        return null;
    }
    return $v;
}
function validate_phone(string $v): ?string {
    $v = trim($v);
    // Benarkan digit, ruang, +, -, dan kurungan
    if ($v === '' || !preg_match('/^[0-9\+\-\s\(\)]+$/', $v)) {
        return null;
    }
    // normalisasi ringkas (opsyenal): buang ruang berganda
    $v = preg_replace('/\s+/', ' ', $v);
    return $v;
}
function validate_date_ymd(string $v): ?string {
    $v = trim($v);
    $dt = DateTime::createFromFormat('Y-m-d', $v);
    $errors = DateTime::getLastErrors();
    if (!$dt || $errors['warning_count'] > 0 || $errors['error_count'] > 0) {
        return null;
    }
    // normalisasi ke Y-m-d
    return $dt->format('Y-m-d');
}
function int_or_zero(string $key): int {
    $val = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    return ($val === false || $val === null) ? 0 : $val;
}
// Emel: elak header injection
function safe_email(string $email): ?string {
    $email = trim($email);
    if ($email === '' || strpbrk($email, "\r\n") !== false) return null;
    return filter_var($email, FILTER_VALIDATE_EMAIL) ?: null;
}

/** ---------------------------
 *  Tangkap & Validasi Data
 *  ---------------------------
 */
$no_pekerja_raw   = (string)($_POST['no_pekerja'] ?? '');
$nama_raw         = (string)($_POST['nama'] ?? '');
$bah_fak_raw      = (string)($_POST['bah_fak'] ?? '');
$nama_program_raw = (string)($_POST['nama_program'] ?? '');
$emel_raw         = (string)($_POST['emel'] ?? '');
$no_tel_raw       = (string)($_POST['no_tel'] ?? '');
$tarikh_raw       = (string)($_POST['tarikh_cetakan'] ?? '');
$anjuran_bahagian_raw = (int)($_POST['anjuran_bahagian'] ?? 0);
$anjuran_raw      = (string)($_POST['pilihan_anjuran'] ?? '');

$no_pekerja     = validate_no_pekerja($no_pekerja_raw) ?? '';
$nama           = sanitize_text($nama_raw, 255);
$bah_fak        = sanitize_text($bah_fak_raw, 255);
$nama_program   = sanitize_text($nama_program_raw, 255);
$no_tel         = validate_phone($no_tel_raw) ?? '';
$tarikh_cetakan = validate_date_ymd($tarikh_raw) ?? '';
$emel           = safe_email($emel_raw) ?? '';
$anjuran_bahagian = $anjuran_bahagian_raw === 1 ? 1 : 0; // pastikan hanya 0 atau 1
$anjuran = sanitize_text($anjuran_raw, 255);

if ($no_pekerja === '' || $nama === '' || $bah_fak === '' || $nama_program === '' || $no_tel === '' || $tarikh_cetakan === '' || $emel === '') {
    swal_and_redirect('warning', 'Medan Tidak Lengkap', 'Sila pastikan semua medan wajib telah diisi dengan format yang betul.', null, true);
}

/** ---------------------------
 *  Ambil bah_fak & emel dari idata.t_staf
 *  ---------------------------
 */
// $bah_fak = '';
// $emel_pengguna = '';
// try {
//     $stmt = $pdo_idata->prepare("SELECT bah_fak, emel FROM t_staf WHERE no_pekerja = :no_pekerja LIMIT 1");
//     $stmt->execute([':no_pekerja' => $no_pekerja]);
//     $row = $stmt->fetch(PDO::FETCH_ASSOC);
//     if ($row) {
//         $bah_fak = (string)($row['bah_fak'] ?? '');
//         $emel_pengguna = safe_email((string)($row['emel'] ?? '')) ?? '';
//     }
// } catch (Throwable $e) {
//     error_log('idata.t_staf query error: ' . $e->getMessage());
// }

/** ---------------------------
 *  Ambil data Ketua Bahagian dari ecetak.users
 *  ---------------------------
 */
$staff_no_kb = null;
$email_kb = null;
try {
    $stmt = $pdo->prepare("SELECT staff_no, username, role, email FROM users WHERE bah_fak = :bah_fak AND role = 'KETUA BAHAGIAN' LIMIT 1");
    $stmt->execute([':bah_fak' => $bah_fak]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($userRow) {
        $staff_no_kb = (string)($userRow['staff_no'] ?? '');
        $email_kb = safe_email((string)($userRow['email'] ?? '')) ?? null;
    }
} catch (Throwable $e) {
    error_log('ecetak.users query error: ' . $e->getMessage());
}

/** ---------------------------
 *  Sediakan & Simpan ke permohonan
 *  ---------------------------
 */
$sql = "INSERT INTO permohonan (
    no_pekerja, nama, bah_fak, nama_program, emel, no_tel, tarikh_cetakan,
    letterhead_a4_berwarna, letterhead_a4_hitam_putih, letterhead_a3_berwarna, letterhead_a3_hitam_putih,
    booklet_a4_berwarna, booklet_a4_hitam_putih, booklet_a3_berwarna, booklet_a3_hitam_putih,
    poster_a4_berwarna, poster_a4_hitam_putih, poster_a3_berwarna, poster_a3_hitam_putih,
    sijil_a4_berwarna, sijil_a4_hitam_putih, sijil_a3_berwarna, sijil_a3_hitam_putih,
    lain_a4_berwarna, lain_a4_hitam_putih, lain_a3_berwarna, lain_a3_hitam_putih, pengesahan_kb_no_pekerja,
    anjuran_bahagian, anjuran
) VALUES (
    :no_pekerja, :nama, :bah_fak, :nama_program, :emel, :no_tel, :tarikh_cetakan,
    :letterhead_a4_berwarna, :letterhead_a4_hitam_putih, :letterhead_a3_berwarna, :letterhead_a3_hitam_putih,
    :booklet_a4_berwarna, :booklet_a4_hitam_putih, :booklet_a3_berwarna, :booklet_a3_hitam_putih,
    :poster_a4_berwarna, :poster_a4_hitam_putih, :poster_a3_berwarna, :poster_a3_hitam_putih,
    :sijil_a4_berwarna, :sijil_a4_hitam_putih, :sijil_a3_berwarna, :sijil_a3_hitam_putih,
    :lain_a4_berwarna, :lain_a4_hitam_putih, :lain_a3_berwarna, :lain_a3_hitam_putih, :pengesahan_kb_no_pekerja,
    :anjuran_bahagian, :anjuran
)";

try {
    $stmt = $pdo->prepare($sql);

    $params = [
        ':no_pekerja'  => $no_pekerja,
        ':nama'        => $nama,
        ':bah_fak'     => $bah_fak,
        ':nama_program'=> $nama_program,
        ':emel'        => $emel,
        ':no_tel'      => $no_tel,
        ':tarikh_cetakan' => $tarikh_cetakan,

        ':letterhead_a4_berwarna'    => int_or_zero('letterhead_a4_berwarna'),
        ':letterhead_a4_hitam_putih' => int_or_zero('letterhead_a4_hitam_putih'),
        ':letterhead_a3_berwarna'    => int_or_zero('letterhead_a3_berwarna'),
        ':letterhead_a3_hitam_putih' => int_or_zero('letterhead_a3_hitam_putih'),

        ':booklet_a4_berwarna'       => int_or_zero('booklet_a4_berwarna'),
        ':booklet_a4_hitam_putih'    => int_or_zero('booklet_a4_hitam_putih'),
        ':booklet_a3_berwarna'       => int_or_zero('booklet_a3_berwarna'),
        ':booklet_a3_hitam_putih'    => int_or_zero('booklet_a3_hitam_putih'),

        ':poster_a4_berwarna'        => int_or_zero('poster_a4_berwarna'),
        ':poster_a4_hitam_putih'     => int_or_zero('poster_a4_hitam_putih'),
        ':poster_a3_berwarna'        => int_or_zero('poster_a3_berwarna'),
        ':poster_a3_hitam_putih'     => int_or_zero('poster_a3_hitam_putih'),

        ':sijil_a4_berwarna'         => int_or_zero('sijil_a4_berwarna'),
        ':sijil_a4_hitam_putih'      => int_or_zero('sijil_a4_hitam_putih'),
        ':sijil_a3_berwarna'         => int_or_zero('sijil_a3_berwarna'),
        ':sijil_a3_hitam_putih'      => int_or_zero('sijil_a3_hitam_putih'),

        ':lain_a4_berwarna'          => int_or_zero('lain_a4_berwarna'),
        ':lain_a4_hitam_putih'       => int_or_zero('lain_a4_hitam_putih'),
        ':lain_a3_berwarna'          => int_or_zero('lain_a3_berwarna'),
        ':lain_a3_hitam_putih'       => int_or_zero('lain_a3_hitam_putih'),

        ':pengesahan_kb_no_pekerja'  => $staff_no_kb ?? null, // boleh null jika tiada KB
        ':anjuran_bahagian'          => $anjuran_bahagian,
        ':anjuran'                   => $anjuran,
    ];

    $stmt->execute($params);
    $lastId = $pdo->lastInsertId();

    /** ---------------------------
     *  Emel notifikasi (asas)
     *  ---------------------------
     */
    // Helper ringkas hantar emel (mail()) dengan header selamat.
    $from = 'no-reply@ecetak.uitm.edu.my';
    $commonHeaders = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
        'From: eCetak <' . $from . '>',
    ];
    $headers_str = implode("\r\n", $commonHeaders);

    // Kepada pengguna (jika emel wujud & sah)
    if ($emel) {
        $subject_user = 'Permohonan Cetakan Dihantar';
        $message_user = "Assalamualaikum dan Salam sejahtera,\n\n" .
                        "Permohonan cetakan anda (ID #$lastId) telah berjaya dihantar.\n" .
                        "Sila tunggu untuk proses pengesahan.\n\n" .
                        "Terima kasih.\n-eCetak";
        @mail($emel, '=?UTF-8?B?'.base64_encode($subject_user).'?=', $message_user, $headers_str);
    }

    // Kepada Ketua Bahagian (jika wujud & sah)
    if ($email_kb) {
        $subject_kb = 'Notifikasi Permohonan Cetakan Baru';
        $message_kb = "Tuan/Puan,\n\n" .
                      "Terdapat permohonan cetakan baru (ID #$lastId) daripada no_pekerja: $no_pekerja yang memerlukan pengesahan.\n\n" .
                      "Terima kasih.\n-eCetak";
        @mail($email_kb, '=?UTF-8?B?'.base64_encode($subject_kb).'?=', $message_kb, $headers_str);
    }

    // Berjaya - papar notifikasi dan redirect
    $redirectNoPekerja = rawurlencode($no_pekerja);
    $redirectUrl = "semak_permohonan.php?no_pekerja={$redirectNoPekerja}";
    swal_and_redirect('success', 'Permohonan Dihantar', 'Permohonan anda telah berjaya dihantar. Sila tunggu untuk pengesahan.', $redirectUrl);

} catch (Throwable $e) {
    error_log('DB insert or email error: ' . $e->getMessage());
    swal_and_redirect('error', 'Ralat Penyimpanan', 'Terjadi ralat semasa menyimpan permohonan. Sila cuba semula.', null, true);
}