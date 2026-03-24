<?php
/**
 * index.php - eCetak Login Interface
 * Security-Enhanced Login Page with CSRF Protection
 * Includes: Input validation, rate limiting, security headers, CSRF tokens
 * Author: eCetak Development Team
 * Version: 2.0 (Security Enhanced)
 * Last Updated: 2026
 */

// ============================================================================
// SECURITY CONFIGURATION & SESSION MANAGEMENT
// ============================================================================

// Set secure session options
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);

// Set SameSite attribute for PHP 7.3+
if (PHP_VERSION_ID >= 70300) {
    ini_set('session.cookie_samesite', 'Strict');
}

session_start();

// ============================================================================
// SECURITY HEADERS - Protect against common web vulnerabilities
// ============================================================================

// Prevent clickjacking attacks
header('X-Frame-Options: SAMEORIGIN');

// Prevent content-type sniffing
header('X-Content-Type-Options: nosniff');

// Enable XSS protection
header('X-XSS-Protection: 1; mode=block');

// Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://stackpath.bootstrapcdn.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com");

// Referrer Policy
header('Referrer-Policy: Strict-Origin-When-Cross-Origin');

// ============================================================================
// SECURITY FUNCTIONS
// ============================================================================

/**
 * Generate CSRF token for form protection
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token validity
 */
function isValidCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    // Token expiry: 1 hour
    if (time() - $_SESSION['csrf_token_time'] > 3600) {
        unset($_SESSION['csrf_token']);
        return false;
    }
    return true;
}

/**
 * Check rate limiting for login attempts
 */
function checkRateLimit() {
    $max_attempts = 5;
    $lockout_time = 900; // 15 minutes
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_first_attempt'] = time();
    }
    
    $elapsed = time() - $_SESSION['login_first_attempt'];
    
    // Reset counter after lockout period
    if ($elapsed > $lockout_time) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_first_attempt'] = time();
        return true;
    }
    
    // Check if limit exceeded
    if ($_SESSION['login_attempts'] >= $max_attempts) {
        return false;
    }
    
    return true;
}

/**
 * Sanitize input data
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Log security events
 * Note: This function is defined for compatibility. Actual logging is handled in proses.php
 * Signature matches proses.php for consistency
 */
function logSecurityEvent($event_type, $details = '', $severity = 'INFO') {
    $log_dir = '../../logs';
    @mkdir($log_dir, 0755, true);
    
    $log_file = $log_dir . '/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    $user_agent = substr(preg_replace('/[^a-zA-Z0-9\s\-\.]/', '', $ua), 0, 100);
    
    $log_entry = "[$timestamp] [$severity] Event: $event_type | IP: $ip | Details: $details | UA: $user_agent\n";
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// ============================================================================
// SESSION & ERROR HANDLING
// ============================================================================

// Regenerate session ID for added security
if (!isset($_SESSION['session_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated'] = true;
}!

// Process error message from previous request
$errorMessage = '';
if (isset($_SESSION['error_message'])) {
    $errorMessage = sanitizeInput($_SESSION['error_message']);
    unset($_SESSION['error_message']);
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Tangkap ralat dari url dengan kod "header('Location: login.php?msg=admin_exists')" dalam fail daftar.php dan paparkan notifikasi dengan SweetAlert2
if (isset($_GET['msg']) && $_GET['msg'] === 'admin_exists') {
    $errorMessage = 'Admin sudah wujud. Sila log masuk dengan akaun admin yang sedia ada.';
    logSecurityEvent('Admin Registration Attempt', 'Attempt to register admin when one already exists', 'WARNING');
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="description" content="eCetak - Sistem Log Masuk">
    <meta name="robots" content="noindex, nofollow">
    <title>eCetak - Log Masuk</title>
    
    <!-- External CSS Libraries (with cache busting) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" integrity="sha512-iBBXm8fW90+nuLcSKlbmrPcLa0OT92xO1BIsZ+ywDWZCvqsWgccV3gFoRBv0z+8dLJgyAHIhR35VZc2oM/gI1w==" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@10/dist/sweetalert2.min.css" integrity="sha256-BEpyHrMkwbhMryUcwPGlgdVs0EMFOH89uqHLd6aAEg=" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" integrity="sha512-1cK78a1o+ht2JcaW6g8OXYwqviYAHEBRM1FREE//P+8GzNdfcddVPXWZrEMh50SNdeOfZlqoqknyV0DK8LZnSQ==" crossorigin="anonymous" />
    
    <!-- Custom Styles -->
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
            align-items: center;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: #f6fbff;
            border-bottom: 2px solid #e9ecef;
            border-radius: 15px 15px 0 0;
            padding: 2rem 1.25rem;
        }

        .card-header h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-top: 1rem;
        }

        .card-header img {
            max-width: 150px;
            height: auto;
            transition: transform 0.3s ease;
        }

        .card-header img:hover {
            transform: scale(1.05);
        }

        .card-body {
            padding: 2rem;
        }

        .form-group label {
            font-style: italic;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 0.75rem;
        }

        .form-control {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(139, 92, 246, 0.25);
        }

        .form-control.is-invalid {
            border-color: #dc3545;
        }

        .invalid-feedback {
            display: block;
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #7c3aed;
            border-color: #7c3aed;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.4);
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #5a6268;
            transform: translateY(-2px);
            color: #fff;
        }

        .form-group.text-center {
            margin-top: 2rem;
        }

        .security-badge {
            font-size: 0.75rem;
            color: #28a745;
            margin-top: 0.5rem;
        }

        .password-toggle {
            cursor: pointer;
            user-select: none;
        }

        .loading-spinner {
            display: none;
            margin-left: 0.5rem;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        footer {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        footer p {
            margin: 0;
        }
    </style>

    <!-- Error Handler Script -->
    <?php if (!empty($errorMessage)): ?>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10/dist/sweetalert2.all.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Ralat',
                text: <?php echo json_encode($errorMessage); ?>,
                confirmButtonColor: '#8b5cf6'
            });
        });
    </script>
    <?php endif; ?>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="card">
                    <div class="card-header text-center">
                        <img src="../../images/Logo.png" alt="Logo UiTM" class="mb-3">
                        <h3>Log Masuk</h3>
                    </div>
                    <div class="card-body">
                        <form id="loginForm" method="POST" action="proses.php" novalidate>
                            <!-- CSRF Token -->
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            
                            <div class="form-group">
                                <label for="no_pekerja">Nombor Pekerja</label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="no_pekerja" 
                                    name="no_pekerja" 
                                    maxlength="6" 
                                    required 
                                    autofocus 
                                    placeholder="Contoh: 123456"
                                    inputmode="numeric"
                                    pattern="[0-9]{6}">
                                <div class="invalid-feedback" id="pekerjaError"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="no_kp" class="d-flex justify-content-between align-items-center">
                                    <span>Nombor Kad Pengenalan</span>
                                    <small class="password-toggle" onclick="togglePasswordVisibility()" title="Tunjuk/Sembunyikan">
                                        <i class="fas fa-eye" id="toggleIcon"></i>
                                    </small>
                                </label>
                                <input 
                                    type="password" 
                                    class="form-control" 
                                    id="no_kp" 
                                    name="no_kp" 
                                    maxlength="12" 
                                    required 
                                    placeholder="Contoh: 123456789012"
                                    inputmode="numeric"
                                    pattern="[0-9]{12}">
                                <div class="invalid-feedback" id="kpError"></div>
                            </div>
                            
                            <div class="form-group text-center">
                                <button type="submit" class="btn btn-primary mr-2" id="submitBtn" data-aos="fade-right" data-aos-delay="100">
                                    <span id="btnText">Log Masuk</span>
                                    <span class="spinner-border spinner-border-sm loading-spinner" id="loadingSpinner" role="status" aria-hidden="true"></span>
                                </button>
                                <a href="../../index.php" class="btn btn-secondary" data-aos="fade-left" data-aos-delay="100">Batal</a>
                            </div>

                            <div class="form-group text-center">
                                <a href="daftar.php" class="btn btn-link">Daftar Pengguna Baru</a><br>
                                <!-- Sambungan Reeset Katalaluan -->
                                <a href="reset/forgot-password.php" class="btn btn-link">Lupa Katalaluan?</a>
                            </div>

                            <div class="security-badge text-center">
                                <i class="fas fa-lock"></i> Dilindungi dengan enkripsi HTTPS & CSRF Token
                            </div>
                        </form>
                    </div>
                </div>
                <footer class="text-center mt-4" data-aos="fade-up" data-aos-delay="200">
                    <p>&copy; <?php echo date("Y"); ?> eCetak - Perpustakaan Sultan Badlishah</p>
                </footer>
            </div>
        </div>
    </div>
    <!-- External JavaScript Libraries (with integrity checks) -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10/dist/sweetalert2.all.min.js" integrity="sha256-OjMcmZ/7FhnnPqw6PHhqVZk7VFrNhz+hJRKDGn9Z7I=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js" integrity="sha512-A7AYk1fXKgxPsOg6mpyl04o5V+swuLjJiFlJopY7h62fVeN4FeIME+6MwD4b56yD0D058bCnKJ+3rdw32dXDQg==" crossorigin="anonymous"></script>
    
    <!-- Application JavaScript with Enhanced Security -->
    <script>
        'use strict';

        // ===================== SECURITY & UTILITY VARIABLES =======================
        const DOMReady = (callback) => {
            if (document.readyState !== 'loading') {
                callback();
            } else {
                document.addEventListener('DOMContentLoaded', callback);
            }
        };

        // ===================== FORM VALIDATION FUNCTIONS =======================

        const validateEmployeeNumber = (pekerja) => /^\d{6}$/.test(pekerja.trim());
        const validateICNumber = (kp) => /^\d{12}$/.test(kp.trim());

        const clearErrors = () => {
            $('#pekerjaError').text('');
            $('#kpError').text('');
            $('#no_pekerja').removeClass('is-invalid');
            $('#no_kp').removeClass('is-invalid');
        };

        const showError = (fieldId, errorMsg) => {
            $(`#${fieldId}Error`).text(errorMsg);
            $(`#${fieldId}`).addClass('is-invalid');
        };

        const validateForm = () => {
            let isValid = true;
            const pekerja = $('#no_pekerja').val().trim();
            const kp = $('#no_kp').val().trim();

            clearErrors();

            if (!pekerja) {
                showError('no_pekerja', 'Sila masukkan nombor pekerja.');
                isValid = false;
            } else if (!validateEmployeeNumber(pekerja)) {
                showError('no_pekerja', 'Nombor pekerja mesti 6 digit sahaja.');
                isValid = false;
            }

            if (!kp) {
                showError('no_kp', 'Sila masukkan nombor kad pengenalan.');
                isValid = false;
            } else if (!validateICNumber(kp)) {
                showError('no_kp', 'Nombor kad pengenalan mesti 12 digit sahaja.');
                isValid = false;
            }

            return isValid;
        };

        // ===================== PASSWORD VISIBILITY TOGGLE =======================
        const togglePasswordVisibility = () => {
            const passwordField = document.getElementById('no_kp');
            const toggleIcon = document.getElementById('toggleIcon');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        };

        // ===================== SUBMIT BUTTON LOADING STATE =======================
        const setSubmitLoading = (isLoading) => {
            const submitBtn = document.getElementById('submitBtn');
            const spinner = document.getElementById('loadingSpinner');

            if (isLoading) {
                submitBtn.disabled = true;
                spinner.style.display = 'inline-block';
                document.getElementById('btnText').textContent = 'Memproses...';
            } else {
                submitBtn.disabled = false;
                spinner.style.display = 'none';
                document.getElementById('btnText').textContent = 'Log Masuk';
            }
        };

        // ===================== INITIALIZATION & EVENT HANDLERS =======================
        DOMReady(() => {
            // Initialize AOS
            AOS.init({ duration: 800, easing: 'ease-in-out', once: true });

            // Employee number real-time validation
            $('#no_pekerja').on('input', function() {
                const value = $(this).val().trim();
                $('#pekerjaError').text('');
                $(this).removeClass('is-invalid');
                if (value && !validateEmployeeNumber(value)) {
                    $(this).addClass('is-invalid');
                }
            });

            // IC number real-time validation
            $('#no_kp').on('input', function() {
                const value = $(this).val().trim();
                $('#kpError').text('');
                $(this).removeClass('is-invalid');
                if (value && !validateICNumber(value)) {
                    $(this).addClass('is-invalid');
                }
            });

            // Prevent non-numeric input
            $('#no_pekerja, #no_kp').on('input', function() {
                $(this).val($(this).val().replace(/[^0-9]/g, ''));
            });

            // Form submission handler
            $('#loginForm').on('submit', function(e) {
                e.preventDefault();

                if (!validateForm()) {
                    console.warn('Form validation failed');
                    return false;
                }

                setSubmitLoading(true);
                setTimeout(() => { this.submit(); }, 300);
                return false;
            });

            // Security logging
            console.log('%c eCetak Security Mode Active', 'color: green; font-weight: bold; font-size: 14px;');

            if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
                console.warn('%c Warning: Page loaded over insecure connection!', 'color: red; font-weight: bold;');
            }

            // Error message display
            <?php if (!empty($errorMessage)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Ralat Log Masuk',
                html: '<?php echo addslashes($errorMessage); ?>',
                confirmButtonColor: '#8b5cf6',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#no_pekerja').focus();
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>