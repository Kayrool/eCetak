<?php
// auth/rate_limiter.php
// Guna jadual rate_limits (rujuk SQL migrasi)
function rl_hit(string $key, int $max, int $windowSeconds): bool {
    global $pdo; // $pdo dari ../../config/db.php
    $now = time();
    $resetAt = date('Y-m-d H:i:s', $now + $windowSeconds);

    $stmt = $pdo->prepare("SELECT id, count, reset_at FROM rate_limits WHERE rl_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $ins = $pdo->prepare("INSERT INTO rate_limits (rl_key, count, reset_at) VALUES (?, 1, ?)");
        $ins->execute([$key, $resetAt]);
        return true;
    }

    if (strtotime($row['reset_at']) <= $now) {
        $upd = $pdo->prepare("UPDATE rate_limits SET count = 1, reset_at = ? WHERE id = ?");
        $upd->execute([$resetAt, $row['id']]);
        return true;
    }

    if ((int)$row['count'] >= $max) {
        return false;
    }

    $upd = $pdo->prepare("UPDATE rate_limits SET count = count + 1 WHERE id = ?");
    $upd->execute([$row['id']]);
    return true;
}