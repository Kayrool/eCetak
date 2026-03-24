<?php
/**
 * Halaman lupa katalaluan
 * PHP version 7.4
 * MySQL version 5.7
 * Apache version 2.4
 * FreeBSD
 */
// Sambungan Pangkalan Data
require_once '../../config/db.php';
require_once '../../config/db_idata.php';

// Mulakan sesi
session_start();

// Cipta token untuk reset katalaluan
if (empty($_SESSION['reset_token'])) {
    $_SESSION['reset_token'] = bin2hex(random_bytes(32));
}
$reset_token = $_SESSION['reset_token'];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Katalaluan - eCetak</title>
    <!-- Perpustakaan CSS Bootstrap 5 luaran -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Ikon Bootstrap luaran -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Perpustakaan JavaScript Bootstrap 5 luaran -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Perpustakaan JavaScript jQuery luaran -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Perpustakaan JavaScript SweetAlert2 luaran -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Gaya Tersuai -->
    <style>
        :root {
            --primary-color: #8b5cf6;
            --bg-light-purple: #eedeff;
            --bg-light-blue: #cbe5fd;
            --text-muted: #c4c4c4;
        }

        body {
            /* background-color: var(--bg-light-blue); */
            background: linear-gradient(135deg, #001f3f 0%, #004080 50%, #0066cc 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .card-header {
            background-color: #f6fbff;
            border-bottom: 2px solid #e9ecef;
            border-radius: 15px 15px 0 0;
            padding: 2rem 1.25rem;
        }

        .card-header h3 {
            margin: 0;
            color: var(--primary-color);
        }

        .card-body {
            padding: 2rem 1.25rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(139, 92, 246, 0.25);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #7c4de0;
            border-color: #7c4de0;
        }

        .text-muted {
            color: var(--text-muted) !important;
        }

        .footer {
            margin-top: 2rem;
            text-align: center;
            color: var(--text-muted);
        }
        
        .form-label {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="container d-flex flex-column justify-content-center align-items-center min-vh-100">
        <div class="card" data-aos="fade-up">
            <div class="card-header text-center">
                <!-- Ikon untuk tajuk lupa katalaluan -->
                <i class="bi bi-key-fill" style="font-size: 2rem; color: var(--primary-color);"></i>
                <h3 class="mb-0">Lupa Katalaluan</h3>
            </div>
            <div class="card-body">
                <form id="resetPasswordForm" method="POST" action="proses_lupa_katalaluan.php">
                    <div class="mb-3">
                        <!-- Ikon emel untuk input emel -->
                        <i class="bi bi-envelope-fill" style="font-size: 1rem; color: var(--primary-color);"></i>
                        <label for="email" class="form-label">Alamat Emel</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="masukkan emel anda" required>
                    </div>
                    <input type="hidden" name="reset_token" value="<?php echo htmlspecialchars($reset_token); ?>">
                    <button type="submit" class="btn btn-primary w-100">Hantar Pautan Reset</button>
                </form>
            </div>
        </div>
        <footer class="text-center mt-4 text-muted">
            &copy; <?php echo date('Y'); ?> eCetak. Perpustakaan Sultan Badlishah.
        </footer>
    </div>

    <!-- Skrip Tersuai untuk Pengendalian Borang -->
    <script>
        $(document).ready(function() {
            $('#resetPasswordForm').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                $.ajax({
                    type: form.attr('method'),
                    url: form.attr('action'),
                    data: form.serialize(),
                    success: function(response) {
                        Swal.fire({
                            title: 'Berjaya!',
                            text: 'Pautan reset katalaluan telah dihantar ke emel anda.',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                        form[0].reset();
                    },
                    error: function() {
                        Swal.fire({
                            title: 'Ralat!',
                            text: 'Terdapat masalah semasa menghantar pautan reset. Sila cuba lagi.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            });
        });
    </script>

    <!-- JavaScript untuk tapisan input emel -->
    <script>
        document.getElementById('email').addEventListener('input', function (e) {
            var value = e.target.value;
            // Tapisan untuk membenarkan hanya aksara tertentu
            var filteredValue = value.replace(/[^a-zA-Z0-9@._-]/g, '');
            if (value !== filteredValue) {
                e.target.value = filteredValue;
            }
        });
        // Validasi emel sebelum penghantaran borang
        document.getElementById('resetPasswordForm').addEventListener('submit', function (e) {
            var emailInput = document.getElementById('email');
            var email = emailInput.value;
            var emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!emailPattern.test(email)) {
                e.preventDefault();
                Swal.fire({
                    title: 'Ralat!',
                    text: 'Sila masukkan alamat emel yang sah.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    </script>
</body>
</html>