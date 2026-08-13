<?php
require_once 'config.php';
require_once 'db.php';
$user = getCurrentUser($pdo);
$products = getProducts($pdo);
$cartCount = getCartCount();
$shopOpen = isShopOpen($pdo);
$logoHtml = getLogoHtml($pdo);
$shopName = getSetting($pdo, 'shop_name', 'پیتزا یونیک');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($shopName) ?> | طعم بی‌نظیر پیتزا</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php renderClosedBanner($pdo); ?>

    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">
                <?= $logoHtml ?> <span><?= htmlspecialchars($shopName) ?></span>
            </a>
            <div class="nav-links">
                <a href="#menu">منو</a>
                <a href="#about">درباره ما</a>
                <a href="cart.php">سبد خرید <?php if ($cartCount > 0): ?><span style="background:var(--primary);padding:2px 8px;border-radius:20px;font-size:0.8rem;margin-right:4px;"><?= $cartCount ?></span><?php endif; ?></a>
                <?php if ($user): ?>
                    <a href="dashboard.php">پنل کاربری</a>
                    <a href="logout.php" class="btn btn-outline">خروج</a>
                <?php else: ?>
                    <a href="login.php">ورود</a>
                    <a href="register.php" class="btn btn-primary">ثبت‌نام</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1>طعم <span>یونیک</span><br>پیتزای واقعی</h1>
            <p>با بهترین مواد اولیه و دستورهای ایتالیایی اصیل، پیتزاهایی می‌پزییم که فراموش‌نشدنی‌اند.</p>
            <div class="hero-buttons">
                <a href="#menu" class="btn btn-primary">مشاهده منو</a>
                <?php if (!$user): ?>
                    <a href="register.php" class="btn btn-outline">عضویت رایگان</a>
                <?php else: ?>
                    <a href="cart.php" class="btn btn-secondary">سبد خرید</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-image">
            <div class="pizza-circle">🍕</div>
        </div>
    </section>

    <section class="section" id="menu">
        <div class="section-title">
            <h2>منوی ویژه</h2>
            <p>محبوب‌ترین پیتزاهای <?= htmlspecialchars($shopName) ?></p>
        </div>
        <div class="menu-grid">
            <?php if (empty($products)): ?>
                <p style="text-align:center;color:#adb5bd;grid-column:1/-1;">محصولی برای نمایش وجود ندارد</p>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <div class="menu-card">
                    <div class="menu-card-img"><?= htmlspecialchars($product['emoji'] ?: '🍕') ?></div>
                    <div class="menu-card-body">
                        <h3><?= htmlspecialchars($product['name']) ?></h3>
                        <p><?= htmlspecialchars($product['description'] ?? '') ?></p>
                        <div class="menu-card-footer">
                            <span class="price"><?= number_format($product['price']) ?> ت</span>
                            <?php if ($shopOpen): ?>
                                <form method="POST" action="cart.php" style="display:inline;">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">افزودن به سبد</button>
                                </form>
                            <?php else: ?>
                                <button class="btn" style="padding:0.5rem 1rem;font-size:0.85rem;background:#444;color:#aaa;cursor:not-allowed;" disabled>تعطیل</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="section" id="about">
        <div class="section-title">
            <h2>چرا <?= htmlspecialchars($shopName) ?>؟</h2>
            <p>کیفیت، سرعت و طعم واقعی ایتالیایی</p>
        </div>
        <div class="menu-grid">
            <div class="menu-card" style="text-align: center; padding: 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🔥</div>
                <h3>پخت در فر سنگی</h3>
                <p style="color:#adb5bd;">در دمای بالا برای پوسته ترد و طعم اصیل</p>
            </div>
            <div class="menu-card" style="text-align: center; padding: 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🚚</div>
                <h3>ارسال سریع</h3>
                <p style="color:#adb5bd;">کمتر از ۳۰ دقیقه تا درب منزل شما</p>
            </div>
            <div class="menu-card" style="text-align: center; padding: 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🥬</div>
                <h3>مواد تازه روزانه</h3>
                <p style="color:#adb5bd;">همه مواد اولیه هر روز تازه تهیه می‌شوند</p>
            </div>
        </div>
    </section>

    <footer class="footer">
        <p>© ۱۴۰۵ <?= htmlspecialchars($shopName) ?> — همه حقوق محفوظ است</p>
        <p style="margin-top: 0.5rem; font-size: 0.85rem;">ساعات کاری: ۱۰ صبح تا ۲ ظهر و ۵ عصر تا ۱۱ شب</p>
    </footer>
</body>
</html>
