<?php
// reset/emails/reset_password_email.php
// Pemboleh ubah $resetUrl MESTI diset oleh pemanggil
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Arahan Set Semula Kata Laluan</title>
  <style>
    body { font-family: Arial, sans-serif; color:#222; }
    .btn { display:inline-block; padding:10px 16px; background:#0b5ed7; color:#fff; text-decoration:none; border-radius:4px; }
    .small { color:#666; font-size:12px; }
  </style>
</head>
<body>
  <p>Assalamu'alaikum dan Salam Sejahtera,</p>
  <p>Kami menerima permintaan untuk menukar kata laluan akaun anda.</p>
  <p>
    <a class="btn btn-primary" href="<?= htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') ?>">Tetapkan Kata Laluan Baharu</a>
  </p>
  <p>Atau salin pautan ini ke pelayar anda:<br>
     <span class="small"><?= htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') ?></span></p>
  <p class="small">Pautan ini akan tamat tempoh dalam 15 minit. Jika anda tidak meminta pertukaran ini, abaikan emel ini.</p>
  <p>Terima kasih,<br>Pasukan Sokongan</p>
</body>
</html>