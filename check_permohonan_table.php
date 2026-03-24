<?php
/**
 * check_permohonan_table.php
 * Skrip diagnostik untuk menyemak struktur jadual permohonan
 * dan mengesan isu-isu yang mungkin menyebabkan ralat simpan permohonan
 */

require_once __DIR__ . '/config/db.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>
<html lang='ms'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Diagnostik Jadual Permohonan</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { background: #e7f3fe; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔍 Diagnostik Jadual Permohonan</h1>
";

try {
    // 1. Semak sambungan database
    echo "<h2>1. Status Sambungan Database</h2>";
    if (isset($pdo) && $pdo instanceof PDO) {
        echo "<p class='success'>✓ Sambungan database berjaya</p>";
        echo "<div class='info'><strong>Server:</strong> " . $pdo->getAttribute(PDO::ATTR_SERVER_INFO) . "</div>";
    } else {
        echo "<p class='error'>✗ Sambungan database gagal</p>";
        die("</div></body></html>");
    }

    // 2. Semak kewujudan jadual permohonan
    echo "<h2>2. Semakan Jadual 'permohonan'</h2>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'permohonan'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✓ Jadual 'permohonan' wujud</p>";
    } else {
        echo "<p class='error'>✗ Jadual 'permohonan' tidak wujud!</p>";
        echo "<div class='info'>Sila cipta jadual 'permohonan' terlebih dahulu.</div>";
        die("</div></body></html>");
    }

    // 3. Papar struktur jadual
    echo "<h2>3. Struktur Jadual</h2>";
    $stmt = $pdo->query("DESCRIBE permohonan");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Nama Medan</th><th>Jenis Data</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $columnNames = [];
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
        
        $columnNames[] = $col['Field'];
    }
    echo "</table>";

    // 4. Semak medan yang diperlukan
    echo "<h2>4. Semakan Medan Yang Diperlukan</h2>";
    $requiredColumns = [
        'id', 'no_pekerja', 'nama', 'bah_fak', 'nama_program', 'emel', 'no_tel', 'tarikh_cetakan',
        'letterhead_a4_berwarna', 'letterhead_a4_hitam_putih', 'letterhead_a3_berwarna', 'letterhead_a3_hitam_putih',
        'booklet_a4_berwarna', 'booklet_a4_hitam_putih', 'booklet_a3_berwarna', 'booklet_a3_hitam_putih',
        'poster_a4_berwarna', 'poster_a4_hitam_putih', 'poster_a3_berwarna', 'poster_a3_hitam_putih',
        'sijil_a4_berwarna', 'sijil_a4_hitam_putih', 'sijil_a3_berwarna', 'sijil_a3_hitam_putih',
        'lain_a4_berwarna', 'lain_a4_hitam_putih', 'lain_a3_berwarna', 'lain_a3_hitam_putih',
        'pengesahan_kb_no_pekerja', 'anjuran_bahagian', 'anjuran'
    ];

    echo "<table>";
    echo "<tr><th>Medan</th><th>Status</th></tr>";
    
    $missingColumns = [];
    foreach ($requiredColumns as $reqCol) {
        $exists = in_array($reqCol, $columnNames);
        echo "<tr>";
        echo "<td>" . htmlspecialchars($reqCol) . "</td>";
        if ($exists) {
            echo "<td class='success'>✓ Wujud</td>";
        } else {
            echo "<td class='error'>✗ Tidak wujud</td>";
            $missingColumns[] = $reqCol;
        }
        echo "</tr>";
    }
    echo "</table>";

    if (!empty($missingColumns)) {
        echo "<div class='info'>";
        echo "<strong>⚠️ Medan yang hilang:</strong> " . implode(', ', $missingColumns);
        echo "</div>";
    }

    // 5. Semak constraints
    echo "<h2>5. Constraints & Indexes</h2>";
    $stmt = $pdo->query("SHOW INDEX FROM permohonan");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($indexes)) {
        echo "<table>";
        echo "<tr><th>Nama Index</th><th>Medan</th><th>Unique</th></tr>";
        foreach ($indexes as $idx) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($idx['Key_name']) . "</td>";
            echo "<td>" . htmlspecialchars($idx['Column_name']) . "</td>";
            echo "<td>" . ($idx['Non_unique'] == 0 ? 'Ya' : 'Tidak') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Tiada index dijumpai.</p>";
    }

    // 6. Semak foreign keys
    echo "<h2>6. Foreign Keys</h2>";
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME, COLUMN_NAME, 
            REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_NAME = 'permohonan' 
        AND TABLE_SCHEMA = DATABASE()
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $fkeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($fkeys)) {
        echo "<table>";
        echo "<tr><th>Constraint</th><th>Medan</th><th>Jadual Rujukan</th><th>Medan Rujukan</th></tr>";
        foreach ($fkeys as $fk) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($fk['CONSTRAINT_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['COLUMN_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Tiada foreign key dijumpai.</p>";
    }

    // 7. Test INSERT
    echo "<h2>7. Ujian INSERT (Dry Run)</h2>";
    echo "<div class='info'>";
    echo "<p>Cuba untuk mencipta query INSERT dengan data sampel...</p>";
    
    $testData = [
        'no_pekerja' => 'TEST123',
        'nama' => 'Nama Ujian',
        'bah_fak' => 'BAHAGIAN UJIAN',
        'nama_program' => 'Program Ujian',
        'emel' => 'test@example.com',
        'no_tel' => '0123456789',
        'tarikh_cetakan' => date('Y-m-d'),
    ];
    
    $sql = "INSERT INTO permohonan (
        no_pekerja, nama, bah_fak, nama_program, emel, no_tel, tarikh_cetakan,
        letterhead_a4_berwarna, letterhead_a4_hitam_putih, letterhead_a3_berwarna, letterhead_a3_hitam_putih,
        booklet_a4_berwarna, booklet_a4_hitam_putih, booklet_a3_berwarna, booklet_a3_hitam_putih,
        poster_a4_berwarna, poster_a4_hitam_putih, poster_a3_berwarna, poster_a3_hitam_putih,
        sijil_a4_berwarna, sijil_a4_hitam_putih, sijil_a3_berwarna, sijil_a3_hitam_putih,
        lain_a4_berwarna, lain_a4_hitam_putih, lain_a3_berwarna, lain_a3_hitam_putih,
        pengesahan_kb_no_pekerja, anjuran_bahagian, anjuran
    ) VALUES (
        :no_pekerja, :nama, :bah_fak, :nama_program, :emel, :no_tel, :tarikh_cetakan,
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, ''
    )";
    
    echo "<pre>" . htmlspecialchars($sql) . "</pre>";
    echo "</div>";

    // 8. Cadangan
    echo "<h2>8. Cadangan & Langkah Seterusnya</h2>";
    echo "<div class='info'>";
    echo "<ul>";
    
    if (!empty($missingColumns)) {
        echo "<li class='error'><strong>Kritikal:</strong> Tambah medan yang hilang ke jadual permohonan</li>";
        echo "<li>Gunakan ALTER TABLE untuk menambah medan: <pre>";
        foreach ($missingColumns as $col) {
            echo "ALTER TABLE permohonan ADD COLUMN `$col` VARCHAR(255) NULL;\n";
        }
        echo "</pre></li>";
    } else {
        echo "<li class='success'>Semua medan diperlukan wujud dalam jadual</li>";
    }
    
    echo "<li>Semak log ralat PHP di <code>C:\\laragon\\logs\\php_errors.log</code></li>";
    echo "<li>Semak log ralat Apache di <code>C:\\laragon\\logs\\apache_errors.log</code></li>";
    echo "<li>Untuk debug ralat INSERT, tambah <code>?debug=1</code> pada URL semasa menghantar borang</li>";
    echo "<li>Pastikan data yang dihantar mematuhi constraint jadual (contoh: panjang maksimum, format tarikh, dll.)</li>";
    echo "</ul>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>Ralat berlaku:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</div></body></html>";
