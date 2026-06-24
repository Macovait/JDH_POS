<?php
session_start();

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/src/paths.php';
require_once $rootPath . '/src/db.php';
require_once $rootPath . '/src/functions.php';
require_once $rootPath . '/src/OtpService.php';

$error = '';
$success = '';
$phone = $_GET['phone'] ?? $_SESSION['pending_otp_phone'] ?? '';

// Check if we have pending OTP login
if (empty($_SESSION['pending_otp_phone']) || empty($_SESSION['pending_otp_user'])) {
    header('Location: login.php');
    exit;
}

// Verify OTP submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedOtp = '';
    // Combine all 6 digit inputs
    for ($i = 1; $i <= 6; $i++) {
        $submittedOtp .= $_POST['otp_' . $i] ?? '';
    }
    
    if (strlen($submittedOtp) !== 6) {
        $error = 'Please enter all 6 digits';
    } else {
        $otpService = new OtpService(get_db_connection());
        $result = $otpService->verify($_SESSION['pending_otp_phone'], $submittedOtp);
        
        if ($result['success']) {
            // Complete login
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("
                SELECT u.*, t.id as tenant_id, t.name as tenant_name, t.code as tenant_code,
                       t.timezone, t.currency, t.currency_symbol, t.date_format,
                       r.name as role_name, r.permissions
                FROM users u
                JOIN tenants t ON u.tenant_id = t.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND u.active = 1
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['pending_otp_user']]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['tenant_id'] = $user['tenant_id'];
                $_SESSION['tenant_code'] = $user['tenant_code'];
                $_SESSION['role'] = strtolower($user['role_name'] ?? $user['role'] ?? 'user');
                $_SESSION['permissions'] = json_decode($user['permissions'] ?? '[]', true);
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), last_login_ip = ? WHERE id = ?");
                $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? null, $user['id']]);
                
                // Clear OTP session
                unset($_SESSION['pending_otp_phone']);
                unset($_SESSION['pending_otp_tenant']);
                unset($_SESSION['pending_otp_user']);
                unset($_SESSION['pending_otp_time']);
                
                // Redirect to dashboard
                header('Location: ' . base_url('dashboard/home.php'));
                exit;
            }
        } else {
            $error = $result['message'];
        }
    }
}

// Resend OTP
if (isset($_GET['resend'])) {
    $otpService = new OtpService(get_db_connection());
    $result = $otpService->generateAndSend($_SESSION['pending_otp_phone'], 'login');
    if ($result['success']) {
        $success = 'New code sent!';
    } else {
        $error = $result['message'];
        if (isset($result['otp'])) {
            $error .= ' (Test: ' . $result['otp'] . ')';
        }
    }
}

// Mask phone for display
$maskedPhone = substr($phone, 0, 4) . '***' . substr($phone, -4);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code - JDH POS</title>
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Enter Verification Code</h2>
                <p class="text-gray-500 mt-2">We sent a 6-digit code to<br><strong><?php echo htmlspecialchars($maskedPhone); ?></strong></p>
            </div>
            
            <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm text-center">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-600 text-sm text-center">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-4">
                <div class="flex gap-2 justify-center">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <input type="text" name="otp_<?php echo $i; ?>" id="otp_<?php echo $i; ?>" 
                           maxlength="1" 
                           class="w-12 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:outline-none"
                           onkeyup="moveToNext(this, <?php echo $i; ?>)" 
                           onkeydown="moveToPrev(event, <?php echo $i; ?>)"
                           inputmode="numeric"
                           pattern="[0-9]"
                           required>
                    <?php endfor; ?>
                </div>
                
                <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-3 rounded-lg transition">
                    Verify Code
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-500">
                    Didn't receive the code? 
                    <a href="?resend=1&phone=<?php echo urlencode($phone); ?>" class="text-primary-500 hover:underline">Resend</a>
                </p>
                <p class="text-sm text-gray-400 mt-2">
                    <a href="login.php" class="hover:underline">Back to login</a>
                </p>
            </div>
        </div>
    </div>
    
    <script>
    function moveToNext(current, index) {
        current.value = current.value.replace(/[^0-9]/g, '');
        if (current.value && index < 6) {
            document.getElementById('otp_' + (index + 1)).focus();
        }
        if (index === 6 && current.value) {
            current.form.submit();
        }
    }
    
    function moveToPrev(e, index) {
        if (e.key === 'Backspace' && !e.target.value && index > 1) {
            document.getElementById('otp_' + (index - 1)).focus();
        }
    }
    
    // Auto-focus first box
    document.getElementById('otp_1')?.focus();
    </script>
    
    <style>
    .bg-primary-500 { background-color: #f59e0b; }
    .text-primary-500 { color: #f59e0b; }
    .bg-primary-100 { background-color: #fef3c7; }
    .focus\:border-primary-500:focus { border-color: #f59e0b; }
    .hover\:bg-primary-600:hover { background-color: #d97706; }
    </style>
</body>
</html>
