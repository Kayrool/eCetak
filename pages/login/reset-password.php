<?php
// auth/reset-password.php
require_once __DIR__ . '/../../../config/db.php'; // menyediakan $pdo
require_once __DIR__ . '/security.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$view = 'enter'; // enter | error | success
$msg = '';
$token_plain = $_GET['token'] ?? '';

if ($method === 'GET') {
    if (!$token_plain || !preg_match('/^[a-f0-9]{64}$/', $token_plain)) {
        $view = 'error';
        $msg = 'Pautan tidak sah.';
    } else {
        $token_hash = hash('sha256', $token_plain);
        $stmt = $pdo->prepare(
            "SELECT prt.id, prt.user_id, prt.expires_at, prt.used
             FROM password_reset_tokens prt
             WHERE prt.token_hash = ?
             LIMIT 1"
        );
        $stmt->execute([$token_hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int)$row['used'] === 1 || strtotime($row['expires_at']) < time()) {
            $view = 'error';
            $msg = 'Pautan tidak sah atau telah tamat tempoh.';
        }
    }
}

if ($method === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $view = 'error';
        $msg = 'Sesi tidak sah. Sila cuba lagi.';
    } else {
        $token_plain = $_POST['token'] ?? '';
        if (!preg_match('/^[a-f0-9]{64}$/', $token_plain)) {
            $view = 'error';
            $msg = 'Pautan tidak sah.';
        } else {
            $token_hash = hash('sha256', $token_plain);
            $stmt = $pdo->prepare(
                "SELECT prt.id, prt.user_id, prt.expires_at, prt.used
                 FROM password_reset_tokens prt
                 WHERE prt.token_hash = ?
                 LIMIT 1"
            );
            $stmt->execute([$token_hash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || (int)$row['used'] === 1 || strtotime($row['expires_at']) < time()) {
                $view = 'error';
                $msg = 'Pautan tidak sah atau telah tamat tempoh.';
            } else {
                $pwd  = $_POST['password']  ?? '';
                $pwd2 = $_POST['password2'] ?? '';

                $errs = [];
                if (!validate_password_policy($pwd, $errs)) {
                    $view = 'enter';
                    $msg = implode(' ', $errs);
                } elseif ($pwd !== $pwd2) {
                    $view = 'enter';
                    $msg = 'Pengesahan kata laluan tidak sepadan.';
                } else {
                    $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);

                    try {
                        $pdo->beginTransaction();

                        // Update kata laluan
                        $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $upd->execute([$hash, $row['user_id']]);

                        // Tandakan token sudah digunakan
                        $mark = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?");
                        $mark->execute([$row['id']]);

                        // (Opsyenal) Buang token lain yang belum digunakan untuk user ini
                        $del = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND used = 0");
                        $del->execute([$row['user_id']]);

                        $pdo->commit();

                        $view = 'success';
                        $msg = 'Kata laluan berjaya ditetapkan semula.';
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $view = 'error';
                        $msg = 'Ralat pelayan. Sila cuba lagi.';
                    }
                }
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
  <title>Tetapkan Kata Laluan Baharu</title>
  <style>
    body { font-family: Arial, sans-serif; max-width:520px; margin:24px auto; padding:0 12px; }
    label { display:block; margin:12px 0 6px; }
    input[type=password] { width:100%; padding:10px; box-sizing:border-box; }
    .btn { margin-top:12px; padding:10px 14px; background:#0b5ed7; color:#fff; border:none; border-radius:4px; cursor:pointer; }
    .note { color:#666; font-size: 14px; margin-top: 8px; }
    .error { color:#a00; margin-top:8px; }
    .ok { color:#060; margin-top:8px; }
  </style>
</head>
<body>
  <h1>Tetapkan Kata Laluan Baharu</h1>

  <?php if ($view === 'error'): ?>
    <div class="error"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php elseif ($view === 'success'): ?>
    <div class="ok"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
    <p><a href="/">Kembali ke Laman Log Masuk</a></p>
  <?php else: ?>
    <?php if ($msg): ?><div class="error"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token_plain, ENT_QUOTES, 'UTF-8') ?>">

      <label for="password">Kata Laluan Baharu</label>
      <input type="password" id="password" name="password" required autocomplete="new-password" minlength="12">

      <label for="password2">Sahkan Kata Laluan Baharu</label>
      <input type="password" id="password2" name="password2" required autocomplete="new-password" minlength="12">

      <button type="submit" class="btn">Simpan Kata Laluan</button>
      <p class="note">Tip: Gunakan frasa panjang dengan gabungan huruf besar/kecil, nombor, dan simbol.</p>
    </form>
  <?php endif; ?>
</body>
</html>