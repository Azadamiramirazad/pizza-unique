<?php
require_once 'config.php';
require_once 'db.php';
requireLogin();

$user = getCurrentUser($pdo);

if (!$user) {
    header('Location: login.php');
    exit;
}

$products = getProducts($pdo);
$cartCount = getCartCount();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل کاربری | پیتزا یونیک</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php renderClosedBanner($pdo); ?>
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">
                🍕 <span>پیتزا یونیک</span>
            </a>
            <div class="nav-links">
                <a href="index.php">صفحه اصلی</a>
                <a href="cart.php">سبد خرید <?php if ($cartCount > 0): ?><span style="background:var(--primary);padding:2px 8px;border-radius:20px;font-size:0.8rem;margin-right:4px;"><?= $cartCount ?></span><?php endif; ?></a>
                <a href="logout.php" class="btn btn-outline">خروج</a>
            </div>
        </nav>
    </header>

    <div class="dashboard">
        <div class="welcome-card">
            <h1 style="font-size:1.8rem; margin-bottom:0.5rem;">سلام <?= htmlspecialchars($user['name']) ?> 👋</h1>
            <p style="color:#adb5bd;">به پنل کاربری پیتزا یونیک خوش آمدید</p>
            <p style="margin-top:1rem; font-size:0.95rem;">
                شماره موبایل: <strong dir="ltr"><?= htmlspecialchars($user['phone']) ?></strong>
            </p>
        </div>

        <div class="section-title" style="text-align:right; margin-bottom:1.5rem;">
            <h2 style="font-size:1.5rem;">سفارش سریع</h2>
        </div>

        <div class="menu-grid">
            <?php foreach ($products as $product): ?>
            <div class="menu-card">
                <div class="menu-card-img"><?= $product['emoji'] ?></div>
                <div class="menu-card-body">
                    <h3><?= htmlspecialchars($product['name']) ?></h3>
                    <p><?= formatPrice($product['price']) ?></p>
                    <form method="POST" action="cart.php">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:0.75rem;">افزودن به سبد</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer class="footer">
        <p>© ۱۴۰۵ پیتزا یونیک</p>
    </footer>
</body>
</html>
