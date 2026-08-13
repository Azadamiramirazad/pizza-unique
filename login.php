<?php
require_once 'config.php';
require_once 'db.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$step = $_SESSION['login_step'] ?? 1; // 1: phone, 2: password (or OTP option)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'check_phone') {
        $phone = trim($_POST['phone'] ?? '');
        
        if (!preg_match('/^09[0-9]{9}$/', $phone)) {
            $error = 'شماره موبایل معتبر نیست.';
        } else {
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $error = 'این شماره ثبت‌نام نکرده است. لطفاً ابتدا ثبت‌نام کنید.';
            } else {
                $_SESSION['login_phone'] = $phone;
                $_SESSION['login_name'] = $user['name'];
                $_SESSION['login_step'] = 2;
                $step = 2;
            }
        }
    }

    elseif ($action === 'login') {
        $password = $_POST['password'] ?? '';
        $phone = $_SESSION['login_phone'] ?? '';

        if (empty($phone)) {
            $error = 'جلسه منقضی شده. دوباره تلاش کنید.';
            $step = 1;
            unset($_SESSION['login_step'], $_SESSION['login_phone']);
        } else {
            $stmt = $pdo->prepare("SELECT id, password FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                unset($_SESSION['login_step'], $_SESSION['login_phone'], $_SESSION['login_name']);
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'رمز عبور اشتباه است.';
                $step = 2;
            }
        }
    }

    elseif ($action === 'restart') {
        unset($_SESSION['login_step'], $_SESSION['login_phone'], $_SESSION['login_name']);
        $step = 1;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود | پیتزا یونیک</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">
                🍕 <span>پیتزا یونیک</span>
            </a>
            <div class="nav-links">
                <a href="register.php" class="btn btn-primary">ثبت‌نام</a>
            </div>
        </nav>
    </header>

    <div class="auth-container">
        <div class="auth-card">
            <h1>ورود</h1>
            <p class="subtitle">به پیتزا یونیک خوش آمدید</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="check_phone">
                    <div class="form-group">
                        <label>شماره موبایل</label>
                        <input type="tel" name="phone" placeholder="09123456789" required 
                               pattern="09[0-9]{9}" maxlength="11"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">ادامه</button>
                </form>

            <?php elseif ($step === 2): ?>
                <div class="alert alert-info">
                    سلام <?= htmlspecialchars($_SESSION['login_name'] ?? '') ?> 👋<br>
                    شماره: <?= htmlspecialchars($_SESSION['login_phone'] ?? '') ?>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label>رمز عبور</label>
                        <input type="password" name="password" placeholder="رمز عبور خود را وارد کنید" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; margin-bottom:0.75rem;">ورود</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="restart">
                    <button type="submit" style="background:none; border:none; color:#adb5bd; cursor:pointer; width:100%; font-family:inherit;">تغییر شماره</button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                حساب کاربری ندارید؟ <a href="register.php">ثبت‌نام کنید</a>
            </div>
        </div>
    </div>
</body>
</html>
