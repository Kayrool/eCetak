<?php
// reset/forgot-password.php
require_once __DIR__ . '/../../../config/db.php';   // menyediakan $pdo (PDO)
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/rate_limiter.php';
require_once __DIR__ . '/mailer.php';

// Konfigurasi asas
$appUrl = 'https://ikedah.uitm.edu.my/iTempah/eCetak/pages/login'; // WAJIB HTTPS (tukar ikut domain sebenar)
$ttlSeconds = 15 * 60;               // 15 minit

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$genericMsg = "Jika emel ini berdaftar, pautan set semula telah dihantar.";
$done = false;
$error = null;

if ($method === 'POST') {
    // Rejek bot awal
    if (is_bot_honeypot()) {
        uniform_delay();
        $done = true;
    } elseif (!csrf_validate($_POST['csrf'] ?? '')) {
        uniform_delay();
        $error = "Sesi tidak sah. Sila cuba lagi.";
    } else {
        $emelRaw = $_POST['emel'] ?? '';
        $emel = norm_emel($emelRaw);

        // Validasi format emel
        if (!filter_var($emel, FILTER_VALIDATE_EMAIL)) {
            uniform_delay();
            $done = true; // balas generik
        } else {
            // Rate limit: 3/min per IP, 5/jam per emel
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $okIp   = rl_hit("fp:ip:$ip", 3, 60);
            $okMail = rl_hit("fp:mail:$emel", 5, 3600);

            if (!$okIp || !$okMail) {
                uniform_delay();
                $done = true;
            } else {
                // Cari user
                $stmt = $pdo->prepare("SELECT id, emel FROM users WHERE emel = ? LIMIT 1");
                $stmt->execute([$emel]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Sediakan token & hash
                $token_plain = bin2hex(random_bytes(32)); // 64 char hex
                $token_hash  = hash('sha256', $token_plain);
                $expires_at  = date('Y-m-d H:i:s', time() + $ttlSeconds);

                if ($user) {
                    // Simpan token
                    $ins = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, used, ip_address)
                                          VALUES (?, ?, ?, 0, ?)");
                    $ins->execute([$user['id'], $token_hash, $expires_at, $ip]);

                    // Bina pautan reset
                    $resetUrl = rtrim($appUrl, '/') . "/reset/reset-password.php?token=" . urlencode($token_plain);

                    // Render template emel
                    ob_start();
                    include __DIR__ . '/emails/reset_password_email.php';
                    $html = ob_get_clean();

                    // Hantar emel
                    send_mail($user['emel'], "Arahan Set Semula Kata Laluan", $html);
                } else {
                    // Samakan masa untuk elak enumeration via timing
                    $fake = $pdo->query("SELECT SLEEP(0.05)");
                    $fake->fetchColumn();
                }

                uniform_delay();
                $done = true;
            }
        }
    }
}
?>
<!doctype html>
<html lang="ms">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Lupa Kata Laluan</title>
  <style>
    body { font-family: Arial, sans-serif; max-width:520px; margin:24px auto; padding:0 12px; }
    label { display:block; margin:12px 0 6px; }
    input[type=email], input[type=text] { width:100%; padding:10px; box-sizing:border-box; }
    .btn { margin-top:12px; padding:10px 14px; background:#0b5ed7; color:#fff; border:none; border-radius:4px; cursor:pointer; }
    .note { color:#666; font-size: 14px; margin-top: 8px; }
    .error { color:#a00; margin-top:8px; }
  </style>
</head>
<body>
  <h1>Lupa Kata Laluan</h1>

  <?php if ($done): ?>
    <p class="note"><?= htmlspecialchars($genericMsg, ENT_QUOTES, 'UTF-8') ?></p>
  <?php else: ?>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <!-- Honeypot -->
      <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off">

      <label for="emel">Alamat emel berdaftar</label>
      <input type="email" id="emel" name="emel" placeholder="nama@contoh.com" required autocomplete="email">

      <!-- (Disyorkan) CAPTCHA di sini -->

      <button type="submit" class="btn">Hantar Pautan Reset</button>
      <p class="note">Kami akan menghantar pautan reset jika emel ini berdaftar.</p>
    </form>
  <?php endif; ?>
</body>
</html>