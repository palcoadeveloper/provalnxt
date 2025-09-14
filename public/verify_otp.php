<?php 
// Include configuration first
require_once 'core/config/config.php';

// Include session initialization to ensure consistent session config
require_once 'core/security/session_init.php';

// Include security utilities
require_once 'core/security/rate_limiting_utils.php';
require_once 'core/security/auth_utils.php';
require_once 'core/security/two_factor_auth.php';
require_once 'core/email/BasicOTPEmailService.php';

// Check rate limiting for OTP verification page access
$rateLimitResult = RateLimiter::checkRateLimit('otp_verification_attempts');
if (!$rateLimitResult['allowed']) {
    header('HTTP/1.1 429 Too Many Requests');
    header('Retry-After: ' . ($rateLimitResult['lockout_expires'] - time()));
    die('Too many verification attempts. Please try again in ' . 
        ceil(($rateLimitResult['lockout_expires'] - time()) / 60) . ' minutes.');
}

// Generate CSRF token
generateCSRFToken();

/**
 * Mask email address for privacy while keeping it recognizable
 * @param string $email The email address to mask
 * @return string Masked email address
 */
function maskEmailAddress($email) {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email; // Return as-is if invalid
    }

    list($localPart, $domain) = explode('@', $email, 2);

    // Mask local part (before @)
    $localLength = strlen($localPart);
    if ($localLength <= 2) {
        $maskedLocal = $localPart . '***';
    } else {
        $maskedLocal = substr($localPart, 0, 2) . str_repeat('*', max(1, $localLength - 2));
    }

    // Mask domain part (after @)
    $domainParts = explode('.', $domain);
    $domainName = $domainParts[0];
    $domainLength = strlen($domainName);

    if ($domainLength <= 2) {
        $maskedDomain = $domainName . '**';
    } else {
        $maskedDomain = substr($domainName, 0, 2) . str_repeat('*', max(1, $domainLength - 2));
    }

    // Reconstruct with TLD
    $tld = implode('.', array_slice($domainParts, 1));
    return $maskedLocal . '@' . $maskedDomain . '.' . $tld;
}

// Check if user has pending 2FA session
if (!isset($_SESSION['pending_2fa']) || !isset($_SESSION['otp_session_token'])) {
    // No pending 2FA session, redirect to login
    header('Location: ' . BASE_URL . 'login.php?msg=session_required');
    exit();
}

$pendingUser = $_SESSION['pending_2fa'];
$otpSessionToken = $_SESSION['otp_session_token'];
$ipAddress = getClientIP();

// Get OTP session details
$otpSession = TwoFactorAuth::getOTPSession($otpSessionToken, $ipAddress);
if (!$otpSession || $otpSession['is_used'] === 'Yes') {
    // Invalid or expired session
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php?msg=session_expired');
    exit();
}

// Check if session has expired
if ($otpSession['seconds_remaining'] <= 0) {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php?msg=otp_expired');
    exit();
}

$message = '';
$messageType = '';

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security token validation failed. Please try again.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'verify_otp') {
            $otpCode = trim($_POST['otp_code'] ?? '');
            
            if (empty($otpCode)) {
                $message = 'Please enter the verification code.';
                $messageType = 'warning';
            } else {
                // Verify OTP
                $verificationResult = TwoFactorAuth::verifyOTP($otpCode, $otpSessionToken, $ipAddress);
                
                if ($verificationResult['success']) {
                    // OTP verification successful - complete login using proper session setup
                    error_log("[2FA SUCCESS] OTP verification successful for user: " . $pendingUser['employee_id']);
                    
                    // Clear pending 2FA data before calling handleSuccessfulLogin
                    unset($_SESSION['pending_2fa']);
                    unset($_SESSION['otp_session_token']);
                    
                    // Use the standard login handler to set up complete session
                    handleSuccessfulLogin($pendingUser, $pendingUser['user_type']);
                } else {
                    $message = $verificationResult['error'];
                    $messageType = 'danger';
                    
                    // If maximum attempts exceeded, destroy session and redirect
                    if (strpos($verificationResult['error'], 'Maximum attempts') !== false) {
                        session_destroy();
                        header('Location: ' . BASE_URL . 'login.php?msg=max_attempts');
                        exit();
                    }
                }
            }
        } elseif ($action === 'resend_otp') {
            // Check if user can resend OTP
            $canResend = TwoFactorAuth::canResendOTP($otpSessionToken, $ipAddress);
            
            if ($canResend['can_resend']) {
                // Create new OTP session
                $newOtpSession = TwoFactorAuth::createOTPSession(
                    $pendingUser['user_id'],
                    $pendingUser['unit_id'],
                    $pendingUser['employee_id'],
                    $ipAddress,
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                );
                
                if ($newOtpSession) {
                    // Send new OTP via email using smart sender
                    require_once('core/email/SmartOTPEmailSender.php');
                    $smartEmailSender = new SmartOTPEmailSender();
                    $emailResult = $smartEmailSender->sendOTP(
                        $pendingUser['user_email'],
                        $pendingUser['user_name'],
                        $newOtpSession['otp_code'],
                        $newOtpSession['validity_minutes'],
                        $pendingUser['employee_id'],
                        $pendingUser['unit_id']
                    );
                    
                    if ($emailResult['success']) {
                        // Update session token
                        $_SESSION['otp_session_token'] = $newOtpSession['session_token'];
                        $otpSessionToken = $newOtpSession['session_token'];
                        
                        // Refresh session data
                        $otpSession = TwoFactorAuth::getOTPSession($otpSessionToken, $ipAddress);
                        
                        $message = 'A new verification code has been sent to your email.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to send verification code. Please try again.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Failed to generate new verification code. Please try again.';
                    $messageType = 'danger';
                }
            } else {
                $waitTime = $canResend['wait_time'];
                $message = "Please wait {$waitTime} seconds before requesting a new code.";
                $messageType = 'warning';
            }
        }
    }
}

// Get current remaining time
$secondsRemaining = max(0, $otpSession['seconds_remaining']);
$resendEligibility = TwoFactorAuth::canResendOTP($otpSessionToken, $ipAddress);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Two-Factor Authentication - ProVal HVAC</title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <!-- endinject -->
    <!-- inject:css -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- endinject -->
    <link rel="shortcut icon" href="assets/images/favicon.ico" />
    
    <style>
        .otp-input {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 5px;
            font-family: 'Courier New', monospace;
        }
        .countdown-timer {
            font-size: 18px;
            font-weight: bold;
            color: #dc3545;
        }
        .countdown-expired {
            color: #dc3545;
        }
        .countdown-warning {
            color: #fd7e14;
        }
        .countdown-normal {
            color: #28a745;
        }
        .security-info {
            background-color: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        .resend-section {
            text-align: center;
            margin-top: 20px;
        }
        .attempts-remaining {
            color: #fd7e14;
            font-weight: 500;
        }
    </style>
</head>

<body>
    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper full-page-wrapper">
            <div class="content-wrapper-login d-flex align-items-center auth">
                <div class="row flex-grow">
                    <div class="col-lg-6 mx-auto">
                        <!-- Message Alert -->
                        <?php if (!empty($message)): ?>
                        <div id="verificationnotifications" class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <div id="notify"><?php echo htmlspecialchars($message); ?></div>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php endif; ?>
                        
                        <div class="auth-form-light text-left p-5">
                            <div class="text-left brand-logo">
                                <h1 class="display-3 text-primary">ProVal</h1>
                            </div>
                            <h4>Two-Factor Authentication</h4>
                            <h6 class="font-weight-light">Enter the verification code sent to your email</h6>
                            
                            <!-- Security Information -->
                            <div class="security-info">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <small><strong>User:</strong> <?php echo htmlspecialchars($pendingUser['user_name']); ?></small><br>
                                        <small><strong>Email:</strong> <?php echo htmlspecialchars(maskEmailAddress($pendingUser['user_email'])); ?></small>
                                    </div>
                                    <div class="col-sm-6">
                                        <small><strong>Time Remaining:</strong></small><br>
                                        <span id="countdown-timer" class="countdown-timer">--:--</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- OTP Verification Form -->
                            <form id="otpform" class="needs-validation pt-3" method="post" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="verify_otp">
                                
                                <div class="form-group">
                                    <label for="otp_code">Verification Code</label>
                                    <input type="text" 
                                           class="form-control form-control-lg otp-input" 
                                           id="otp_code" 
                                           name="otp_code" 
                                           placeholder="Enter code"
                                           maxlength="8"
                                           pattern="[0-9]{4,8}"
                                           required 
                                           autocomplete="one-time-code"
                                           autofocus>
                                    <div class="invalid-feedback">
                                        Please enter a valid verification code.
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="submit" 
                                            id="btnVerify" 
                                            class="btn btn-block btn-gradient-primary btn-lg font-weight-medium auth-form-btn">
                                        Verify Code
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Resend OTP Section -->
                            <div class="resend-section">
                                <p class="text-muted small">Didn't receive the code?</p>
                                
                                <form id="resendform" method="post" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="resend_otp">
                                    
                                    <button type="submit" 
                                            id="btnResend" 
                                            class="btn btn-outline-primary btn-sm"
                                            <?php echo $resendEligibility['can_resend'] ? '' : 'disabled'; ?>>
                                        <span id="resend-text">
                                            <?php if ($resendEligibility['can_resend']): ?>
                                                Resend Code
                                            <?php else: ?>
                                                Wait <span id="resend-countdown"><?php echo $resendEligibility['wait_time']; ?></span>s
                                            <?php endif; ?>
                                        </span>
                                    </button>
                                </form>
                                
                                <div class="mt-3">
                                    <form method="post" action="<?php echo BASE_URL; ?>cancel_2fa.php" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="btn btn-link btn-sm" 
                                                onclick="return confirm('Are you sure you want to cancel the login process? You will need to log in again.');">
                                            ← Back to Login
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Security Notice -->
                            <div class="alert alert-info mt-3">
                                <small>
                                    <strong>Security Notice:</strong>
                                    <ul class="mb-0 pl-3">
                                        <li>Never share your verification code with anyone</li>
                                        <li>This code can only be used once</li>
                                        <li>Contact IT support if you didn't request this login</li>
                                    </ul>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- plugins:js -->
    <script src="assets/vendors/js/vendor.bundle.base.js"></script>
    <!-- endinject -->
    <!-- inject:js -->
    <script src="assets/js/off-canvas.js"></script>
    <script src="assets/js/hoverable-collapse.js"></script>
    <script src="assets/js/misc.js"></script>
    <!-- endinject -->
    
    <script>
        $(document).ready(function() {
            let secondsRemaining = <?php echo $secondsRemaining; ?>;
            let resendWaitTime = <?php echo $resendEligibility['wait_time']; ?>;
            
            // Update countdown timer every second
            function updateCountdown() {
                if (secondsRemaining <= 0) {
                    $('#countdown-timer').text('EXPIRED').removeClass().addClass('countdown-timer countdown-expired');
                    $('#btnVerify').prop('disabled', true).text('Code Expired');
                    $('#btnResend').prop('disabled', true);
                    
                    // Auto-redirect after 5 seconds
                    setTimeout(function() {
                        window.location.href = '<?php echo BASE_URL; ?>login.php?msg=otp_expired';
                    }, 5000);
                    
                    return;
                }
                
                const minutes = Math.floor(secondsRemaining / 60);
                const seconds = secondsRemaining % 60;
                const timeString = minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
                
                let timerClass = 'countdown-timer ';
                if (secondsRemaining < 60) {
                    timerClass += 'countdown-expired';
                } else if (secondsRemaining < 120) {
                    timerClass += 'countdown-warning';
                } else {
                    timerClass += 'countdown-normal';
                }
                
                $('#countdown-timer').text(timeString).removeClass().addClass(timerClass);
                secondsRemaining--;
            }
            
            // Update resend countdown
            function updateResendCountdown() {
                if (resendWaitTime <= 0) {
                    $('#btnResend').prop('disabled', false);
                    $('#resend-text').html('Resend Code');
                    return;
                }
                
                $('#resend-countdown').text(resendWaitTime);
                resendWaitTime--;
            }
            
            // Start timers
            updateCountdown();
            const countdownInterval = setInterval(updateCountdown, 1000);
            
            if (resendWaitTime > 0) {
                updateResendCountdown();
                const resendInterval = setInterval(function() {
                    updateResendCountdown();
                    if (resendWaitTime < 0) {
                        clearInterval(resendInterval);
                    }
                }, 1000);
            }
            
            // Form validation
            $('#otpform').on('submit', function(e) {
                const otpCode = $('#otp_code').val().trim();
                
                if (!/^[0-9]{4,8}$/.test(otpCode)) {
                    e.preventDefault();
                    $('#otp_code').addClass('is-invalid');
                    return false;
                } else {
                    $('#otp_code').removeClass('is-invalid').addClass('is-valid');
                }
                
                $('#btnVerify').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Verifying...');
            });
            
            // Auto-format OTP input
            $('#otp_code').on('input', function() {
                let value = $(this).val().replace(/[^0-9]/g, '');
                $(this).val(value);
                
                if (value.length >= 4 && value.length <= 8) {
                    $(this).removeClass('is-invalid');
                }
            });
            
            // Auto-submit when OTP is complete (assuming 6 digits)
            $('#otp_code').on('input', function() {
                if ($(this).val().length === 6) {
                    setTimeout(function() {
                        $('#otpform').submit();
                    }, 500);
                }
            });
            
            // Enhanced browser history protection
            history.pushState(null, null, location.href);
            window.addEventListener('popstate', function(e) {
                // Prevent going back to previous pages during 2FA
                history.pushState(null, null, location.href);
            });
            
            // Hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut();
            }, 5000);
        });
    </script>
</body>
</html>