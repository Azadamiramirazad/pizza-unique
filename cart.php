<?php
require_once 'config.php';
require_once 'db.php';

$user = getCurrentUser($pdo);
$productsList = getProducts($pdo);
$products = [];
foreach ($productsList as $p) { $products[$p['id']] = $p; }
$shopOpen = isShopOpen($pdo);
$message = '';

// مدیریت اکشن‌های سبد
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        if (!$shopOpen) {
            $message = 'مغازه تعطیل است و امکان سفارش وجود ندارد.';
        } else {
            $id = (int)($_POST['product_id'] ?? 0);
            if (addToCart($id)) {
                $message = 'به سبد خرید اضافه شد ✅';
            }
        }
    }
    
    elseif ($action === 'update') {
        $id = (int)($_POST['product_id'] ?? 0);
        $qty = (int)($_POST['qty'] ?? 1);
        updateCartQty($id, $qty);
        $message = 'سبد خرید به‌روزرسانی شد';
    }
    
    elseif ($action === 'remove') {
        $id = (int)($_POST['product_id'] ?? 0);
        removeFromCart($id);
        $message = 'محصول از سبد حذف شد';
    }
    
    elseif ($action === 'clear') {
        clearCart();
        $message = 'سبد خرید خالی شد';
    }
    
    elseif ($action === 'checkout') {
        if (!$shopOpen) {
            $message = 'مغازه تعطیل است و امکان ثبت سفارش وجود ندارد.';
        } elseif (getCartCount() === 0) {
            $message = 'سبد خرید خالی است';
        } elseif (!$user) {
            header('Location: login.php');
            exit;
        } else {
            require_once 'sms.php';
            
            $cart = getCart();
            $total = getCartTotal($pdo);
            $productsList = getProducts($pdo);
            $products = [];
            foreach ($productsList as $p) { $products[$p['id']] = $p; }
            
            // ذخیره سفارش در دیتابیس
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_phone, total_price, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$user['id'], $user['name'], $user['phone'], $total]);
            $orderId = $pdo->lastInsertId();
            
            // ذخیره آیتم‌ها
            $itemsText = '';
            $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, qty) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($cart as $pid => $item) {
                if (!isset($products[$pid])) continue;
                $p = $products[$pid];
                $itemStmt->execute([$orderId, $pid, $p['name'], $p['price'], $item['qty']]);
                $itemsText .= "• {$p['name']} × {$item['qty']}\n";
            }
            
            // ارسال پیامک به فروشنده
            notifySellerNewOrder($orderId, $user['name'], $user['phone'], $total, $itemsText);
            
            // ارسال پیامک تأیید به مشتری
            $customerMsg = "سفارش شما در پیتزا یونیک با موفقیت ثبت شد.\nشماره سفارش: #$orderId\nمبلغ: " . number_format($total) . " تومان\nبه زودی با شما تماس می‌گیریم.";
            sendSMS($user['phone'], $customerMsg);
            
            clearCart();
            header('Location: cart.php?success=1&order_id=' . $orderId);
            exit;
        }
    }
}

$cart = getCart();
$cartCount = getCartCount();
$cartTotal = getCartTotal($pdo);
$success = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سبد خرید | پیتزا یونیک</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        .cart-table th, .cart-table td {
            padding: 1rem;
            text-align: right;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .cart-table th {
            color: #adb5bd;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .cart-item-name {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .cart-item-emoji {
            font-size: 2rem;
        }
        .qty-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .qty-control input {
            width: 60px;
            text-align: center;
            padding: 0.4rem;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.3);
            color: #fff;
            font-family: inherit;
        }
        .qty-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.05);
            color: #fff;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qty-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
        }
        .cart-summary {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.5rem;
            max-width: 400px;
            margin-right: auto;
        }
        .cart-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        .cart-summary-total {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--secondary);
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 1rem;
            margin-top: 1rem;
        }
        .empty-cart {
            text-align: center;
            padding: 4rem 1rem;
            color: #adb5bd;
        }
        .empty-cart .emoji {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .btn-danger {
            background: transparent;
            border: 1px solid #ff6b6b;
            color: #ff6b6b;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.85rem;
        }
        .btn-danger:hover {
            background: #ff6b6b;
            color: #fff;
        }
        .success-box {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.4);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        .success-box h2 {
            color: #28a745;
            margin-bottom: 0.5rem;
        }
    </style>
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

    <div class="dashboard" style="max-width:1000px;">
        <h1 style="font-size:1.8rem; margin-bottom:1.5rem;">🛒 سبد خرید</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-box">
                <h2>✅ سفارش شما با موفقیت ثبت شد!</h2>
                <?php if (!empty($_GET['order_id'])): ?>
                    <p style="font-size:1.2rem; margin:0.75rem 0;">شماره سفارش: <strong>#<?= (int)$_GET['order_id'] ?></strong></p>
                <?php endif; ?>
                <p style="color:#adb5bd; margin-top:0.5rem;">پیامک تأیید براتون ارسال شد. به زودی با شما تماس می‌گیریم.</p>
                <a href="index.php" class="btn btn-primary" style="margin-top:1.5rem;">بازگشت به منو</a>
            </div>
        <?php elseif (empty($cart)): ?>
            <div class="empty-cart">
                <div class="emoji">🛒</div>
                <h2>سبد خرید شما خالی است</h2>
                <p style="margin:1rem 0 1.5rem;">هنوز هیچ پیتزایی اضافه نکردید</p>
                <a href="index.php#menu" class="btn btn-primary">مشاهده منو</a>
            </div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>محصول</th>
                        <th>قیمت واحد</th>
                        <th>تعداد</th>
                        <th>جمع</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart as $id => $item): 
                        if (!isset($products[$id])) continue;
                        $product = $products[$id];
                        $lineTotal = $product['price'] * $item['qty'];
                    ?>
                    <tr>
                        <td>
                            <div class="cart-item-name">
                                <span class="cart-item-emoji"><?= $product['emoji'] ?></span>
                                <div>
                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                    <div style="font-size:0.85rem;color:#adb5bd;"><?= htmlspecialchars($product['desc']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= formatPrice($product['price']) ?></td>
                        <td>
                            <form method="POST" class="qty-control">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="product_id" value="<?= $id ?>">
                                <button type="submit" name="qty" value="<?= $item['qty'] - 1 ?>" class="qty-btn">−</button>
                                <input type="number" name="qty" value="<?= $item['qty'] ?>" min="1" max="20" onchange="this.form.submit()">
                                <button type="submit" name="qty" value="<?= $item['qty'] + 1 ?>" class="qty-btn">+</button>
                            </form>
                        </td>
                        <td><strong><?= formatPrice($lineTotal) ?></strong></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= $id ?>">
                                <button type="submit" class="btn-danger">حذف</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="display:flex; flex-wrap:wrap; gap:1.5rem; justify-content:space-between; align-items:flex-start;">
                <form method="POST">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn btn-outline" onclick="return confirm('سبد خرید خالی شود؟')">خالی کردن سبد</button>
                </form>

                <div class="cart-summary">
                    <div class="cart-summary-row">
                        <span>تعداد اقلام:</span>
                        <span><?= $cartCount ?> عدد</span>
                    </div>
                    <div class="cart-summary-row cart-summary-total">
                        <span>مبلغ قابل پرداخت:</span>
                        <span><?= formatPrice($cartTotal) ?></span>
                    </div>
                    
                    <?php if ($user): ?>
                        <form method="POST" style="margin-top:1.25rem;">
                            <input type="hidden" name="action" value="checkout">
                            <button type="submit" class="btn btn-primary" style="width:100%;">ثبت سفارش</button>
                        </form>
                    <?php else: ?>
                        <p style="margin-top:1rem; font-size:0.9rem; color:#adb5bd; text-align:center;">
                            برای ثبت سفارش باید <a href="login.php" style="color:var(--primary);">وارد شوید</a>
                        </p>
                        <a href="login.php" class="btn btn-primary" style="width:100%; margin-top:0.75rem; display:block; text-align:center;">ورود / ثبت‌نام</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <p>© ۱۴۰۵ پیتزا یونیک</p>
    </footer>
</body>
</html>
