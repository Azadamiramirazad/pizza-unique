<?php
require_once 'config.php';
require_once 'db.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$step = $_SESSION['reg_step'] ?? 1; // 1: phone+name, 2: OTP, 3: password

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_otp') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Validate phone (Iranian format: 09xxxxxxxxx)
        if (empty($name) || empty($phone)) {
            $error = 'لطفاً نام و شماره موبایل را وارد کنید.';
        } elseif (!preg_match('/^09[0-9]{9}$/', $phone)) {
            $error = 'شماره موبایل معتبر نیست. مثال: ۰۹۱۲۳۴۵۶۷۸۹';
        } else {
            // Check if phone already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $error = 'این شماره قبلاً ثبت‌نام کرده است. لطفاً وارد شوید.';
            } else {
                require_once 'sms.php';
                $otp = generateOTP();
                
                $smsResult = sendOTP($phone, $otp);
                
                $_SESSION['reg_otp'] = $otp;
                $_SESSION['reg_otp_time'] = time();
                $_SESSION['reg_name'] = $name;
                $_SESSION['reg_phone'] = $phone;
                $_SESSION['reg_step'] = 2;
                $step = 2;
                
                if ($smsResult['success']) {
                    $success = 'کد تأیید به شماره شما ارسال شد.';
                } else {
                    $success = 'کد تأیید آماده شد. (خطا در ارسال پیامک: ' . ($smsResult['error'] ?? '') . ')';
                }
            }
        }
    }

    elseif ($action === 'verify_otp') {
        $otp_input = trim($_POST['otp'] ?? '');
        
        if (empty($_SESSION['reg_otp']) || empty($_SESSION['reg_phone'])) {
            $error = 'جلسه منقضی شده. لطفاً دوباره شروع کنید.';
            $step = 1;
            unset($_SESSION['reg_step'], $_SESSION['reg_otp']);
        } elseif (time() - ($_SESSION['reg_otp_time'] ?? 0) > 300) { // 5 minutes
            $error = 'کد منقضی شده است. لطفاً دوباره درخواست دهید.';
            $step = 1;
            unset($_SESSION['reg_step'], $_SESSION['reg_otp']);
        } elseif ($otp_input !== $_SESSION['reg_otp']) {
            $error = 'کد وارد شده صحیح نیست.';
            $step = 2;
        } else {
            $_SESSION['reg_step'] = 3;
            $step = 3;
            $success = 'کد تأیید شد! حالا یک رمز عبور انتخاب کنید.';
        }
    }

    elseif ($action === 'set_password') {
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if (empty($_SESSION['reg_phone']) || empty($_SESSION['reg_name'])) {
            $error = 'جلسه منقضی شده. لطفاً دوباره شروع کنید.';
            $step = 1;
        } elseif (strlen($password) < 6) {
            $error = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
            $step = 3;
        } elseif ($password !== $password2) {
            $error = 'رمزهای عبور یکسان نیستند.';
            $step = 3;
        } else {
            // Create user
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, phone, password) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['reg_name'], $_SESSION['reg_phone'], $hashed]);
                
                // Auto login
                $_SESSION['user_id'] = $pdo->lastInsertId();
                
                // Clear reg session
                unset($_SESSION['reg_step'], $_SESSION['reg_otp'], $_SESSION['reg_otp_time'], 
                      $_SESSION['reg_name'], $_SESSION['reg_phone']);
                
                header('Location: dashboard.php');
                exit;
            } catch (PDOException $e) {
                $error = 'خطا در ثبت‌نام. لطفاً دوباره تلاش کنید.';
                $step = 3;
            }
        }
    }

    elseif ($action === 'resend_otp') {
        if (!empty($_SESSION['reg_phone'])) {
            require_once 'sms.php';
            $otp = generateOTP();
            $smsResult = sendOTP($_SESSION['reg_phone'], $otp);
            $_SESSION['reg_otp'] = $otp;
            $_SESSION['reg_otp_time'] = time();
            $step = 2;
            $success = $smsResult['success'] ? 'کد جدید ارسال شد.' : 'خطا در ارسال مجدد: ' . ($smsResult['error'] ?? '');
        }
    }

    elseif ($action === 'restart') {
        unset($_SESSION['reg_step'], $_SESSION['reg_otp'], $_SESSION['reg_otp_time'], 
              $_SESSION['reg_name'], $_SESSION['reg_phone']);
        $step = 1;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌نام | پیتزا یونیک</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">
                🍕 <span>پیتزا یونیک</span>
            </a>
            <div class="nav-links">
                <a href="login.php">ورود</a>
            </div>
        </nav>
    </header>

    <div class="auth-container">
        <div class="auth-card">
            <h1>ثبت‌نام</h1>
            <p class="subtitle">عضویت در پیتزا یونیک</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <!-- Step 1: Name + Phone -->
                <form method="POST">
                    <input type="hidden" name="action" value="send_otp">
                    <div class="form-group">
                        <label>نام و نام خانوادگی</label>
                        <input type="text" name="name" placeholder="مثلاً: علی محمدی" required 
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>شماره موبایل</label>
                        <input type="tel" name="phone" placeholder="09123456789" required 
                               pattern="09[0-9]{9}" maxlength="11"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">ارسال کد تأیید</button>
                </form>

            <?php elseif ($step === 2): ?>
                <!-- Step 2: OTP -->
                <?php if (DEMO_MODE && !empty($_SESSION['reg_otp'])): ?>
                    <div class="alert alert-info">
                        📱 حالت دمو: کد تأیید شما
                    </div>
                    <div class="otp-display"><?= htmlspecialchars($_SESSION['reg_otp']) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="verify_otp">
                    <div class="form-group">
                        <label>کد ۶ رقمی ارسال‌شده</label>
                        <input type="text" name="otp" placeholder="------" required 
                               maxlength="6" pattern="[0-9]{6}" style="text-align:center; letter-spacing:6px; font-size:1.3rem;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; margin-bottom:0.75rem;">تأیید کد</button>
                </form>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="resend_otp">
                    <button type="submit" class="btn btn-outline" style="width:100%; margin-bottom:0.75rem;">ارسال مجدد کد</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="restart">
                    <button type="submit" style="background:none; border:none; color:#adb5bd; cursor:pointer; width:100%; font-family:inherit;">تغییر شماره موبایل</button>
                </form>

            <?php elseif ($step === 3): ?>
                <!-- Step 3: Set Password -->
                <form method="POST">
                    <input type="hidden" name="action" value="set_password">
                    <div class="form-group">
                        <label>رمز عبور</label>
                        <input type="password" name="password" placeholder="حداقل ۶ کاراکتر" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>تکرار رمز عبور</label>
                        <input type="password" name="password2" placeholder="رمز عبور را دوباره وارد کنید" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">تکمیل ثبت‌نام</button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                قبلاً ثبت‌نام کرده‌اید؟ <a href="login.php">وارد شوید</a>
            </div>
        </div>
    </div>
</body>
</html>
