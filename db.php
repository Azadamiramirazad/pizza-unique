<?php
$db_file = __DIR__ . '/pizza.db';

try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // کاربران
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        phone TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // سفارش‌ها
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        customer_name TEXT NOT NULL,
        customer_phone TEXT NOT NULL,
        total_price INTEGER NOT NULL,
        status TEXT DEFAULT 'pending',
        address TEXT,
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // آیتم‌های سفارش
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        product_name TEXT NOT NULL,
        price INTEGER NOT NULL,
        qty INTEGER NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id)
    )");

    // ادمین
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL
    )");

    // محصولات
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        price INTEGER NOT NULL,
        emoji TEXT DEFAULT '🍕',
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // تنظیمات فروشگاه
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");

    // ادمین پیش‌فرض
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    if ($stmt->fetchColumn() == 0) {
        $hashed = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)")
            ->execute(['admin', $hashed]);
    }

    // محصولات پیش‌فرض
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    if ($stmt->fetchColumn() == 0) {
        $defaults = [
            ['پیتزا مارگاریتا', 'سس گوجه، موزارلا، ریحان تازه و روغن زیتون', 185000, '🍕'],
            ['پیتزا پپرونی', 'پپرونی تند، پنیر موزارلا و سس مخصوص یونیک', 215000, '🌶️'],
            ['پیتزا قارچ و مرغ', 'مرغ گریل‌شده، قارچ تازه، پنیر و سس خامه‌ای', 245000, '🍄'],
            ['پیتزا چهار پنیر', 'موزارلا، گودا، پارمزان و پنیر آبی', 265000, '🧀'],
            ['پیتزا گوشت ویژه', 'گوشت چرخ‌کرده، سوسیس، بیکن و پنیر دوبل', 295000, '🥩'],
            ['پیتزا سبزیجات', 'فلفل دلمه‌ای، زیتون، ذرت، قارچ و پیاز', 195000, '🌿'],
        ];
        $ins = $pdo->prepare("INSERT INTO products (name, description, price, emoji) VALUES (?, ?, ?, ?)");
        foreach ($defaults as $p) {
            $ins->execute($p);
        }
    }

    // تنظیمات پیش‌فرض
    $defaults_settings = [
        'shop_mode' => 'auto',          // auto | open | closed
        'logo' => '',                   // مسیر لوگو
        'shop_name' => 'پیتزا یونیک',
    ];
    foreach ($defaults_settings as $k => $v) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
        $stmt->execute([$k, $v]);
    }
    
} catch (PDOException $e) {
    die("خطا در اتصال به دیتابیس: " . $e->getMessage());
}
?>
