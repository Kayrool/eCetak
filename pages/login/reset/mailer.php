<?php
// reset/mailer.php
// Pembalut ringkas untuk mail() (sendmail)
function send_mail(string $to, string $subject, string $html): bool {
    // Tukar pengirim ikut domain sebenar
    $fromEmail = 'no-reply@ikedah.uitm.edu.my';
    $fromName  = 'Sokongan Sistem';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: " . addslashes($fromName) . " <" . $fromEmail . ">\r\n";
    $headers .= "Reply-To: " . $fromEmail . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    // Gunakan -f untuk envelope sender (kurangkan isu SPF/DMARC)
    return mail(
        $to,
        '=?UTF-8?B?'.base64_encode($subject).'?=',
        $html,
        $headers,
        "-f" . $fromEmail
    );
}