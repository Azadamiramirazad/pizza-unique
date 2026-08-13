<?php
session_start();

define('DEMO_MODE', true);

// منطقه زمانی تهران
date_default_timezone_set('Asia/Tehran');

function generateOTP() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT id, name, phone FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }
    
    return $user;
}

// ==================== تنظیمات ====================
function getSetting($pdo, $key, $default = '') {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['value'] : $default;
}

function setSetting($pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
    $stmt->execute([$key, $value]);
}

// ==================== وضعیت فروشگاه ====================
/**
 * آیا فروشگاه الان باز است؟
 * حالت‌ها:
 * - open   → همیشه باز (دستی)
 * - closed → همیشه بسته (دستی)
 * - auto   → طبق ساعت تهران (۱۰-۱۴ و ۱۷-۲۳)
 */
function isShopOpen($pdo) {
    $mode = getSetting($pdo, 'shop_mode', 'auto');
    
    if ($mode === 'open') return true;
    if ($mode === 'closed') return false;
    
    // حالت خودکار بر اساس ساعت تهران
    $hour = (int) date('G');
    $minute = (int) date('i');
    $time = $hour * 60 + $minute;
    
    // ۱۰:۰۰ تا ۱۴:۰۰  و  ۱۷:۰۰ تا ۲۳:۰۰
    $morningOpen  = 10 * 60;
    $morningClose = 14 * 60;
    $eveningOpen  = 17 * 60;
    $eveningClose = 23 * 60;
    
    return ($time >= $morningOpen && $time < $morningClose) 
        || ($time >= $eveningOpen && $time < $eveningClose);
}

function getShopStatusText($pdo) {
    $mode = getSetting($pdo, 'shop_mode', 'auto');
    $open = isShopOpen($pdo);
    
    if ($mode === 'closed') return 'مغازه تعطیل است (دستی)';
    if ($mode === 'open') return 'مغازه باز است (دستی)';
    
    return $open ? 'مغازه باز است' : 'مغازه تعطیل است';
}

// ==================== محصولات ====================
function getProducts($pdo, $onlyActive = true) {
    $sql = "SELECT * FROM products";
    if ($onlyActive) $sql .= " WHERE is_active = 1";
    $sql .= " ORDER BY id ASC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getProduct($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function formatPrice($price) {
    return number_format($price) . ' تومان';
}

// ==================== سبد خرید ====================
function getCart() {
    return $_SESSION['cart'] ?? [];
}

function getCartCount() {
    $count = 0;
    foreach (getCart() as $item) {
        $count += $item['qty'];
    }
    return $count;
}

function getCartTotal($pdo) {
    $total = 0;
    foreach (getCart() as $id => $item) {
        $product = getProduct($pdo, $id);
        if ($product) {
            $total += $product['price'] * $item['qty'];
        }
    }
    return $total;
}

function addToCart($productId, $qty = 1) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['qty'] += $qty;
    } else {
        $_SESSION['cart'][$productId] = ['qty' => $qty];
    }
    return true;
}

function updateCartQty($productId, $qty) {
    if ($qty <= 0) {
        removeFromCart($productId);
        return;
    }
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['qty'] = $qty;
    }
}

function removeFromCart($productId) {
    unset($_SESSION['cart'][$productId]);
}

function clearCart() {
    unset($_SESSION['cart']);
}

// ==================== بنر تعطیلی ====================
function renderClosedBanner($pdo) {
    if (isShopOpen($pdo)) return;
    
    $mode = getSetting($pdo, 'shop_mode', 'auto');
    $text = $mode === 'closed' 
        ? '🔴 مغازه موقتاً تعطیل است' 
        : '🔴 مغازه در حال حاضر تعطیل است — ساعات کاری: ۱۰ صبح تا ۲ ظهر و ۵ عصر تا ۱۱ شب';
    
    echo '<div style="background:linear-gradient(90deg,#9b2226,#c1121f);color:#fff;text-align:center;padding:0.85rem 1rem;font-weight:600;font-size:0.95rem;letter-spacing:0.3px;">'
        . htmlspecialchars($text) .
        '</div>';
}

// لوگو
function getLogoHtml($pdo, $size = 40) {
    $logo = getSetting($pdo, 'logo', '');
    $name = getSetting($pdo, 'shop_name', 'پیتزا یونیک');
    
    if ($logo && file_exists(__DIR__ . '/' . $logo)) {
        return '<img src="' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars($name) . '" style="height:' . $size . 'px;width:auto;border-radius:8px;object-fit:contain;">';
    }
    return '🍕';
}
?>
