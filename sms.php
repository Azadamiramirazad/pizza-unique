<?php
require_once __DIR__ . '/sms_config.php';

/**
 * ارسال پیامک با کاوه نگار (بدون نیاز به Composer)
 */
function sendSMS($receptor, $message) {
    if (!SMS_ENABLED) {
        // حالت تست - فقط لاگ می‌کنیم
        error_log("SMS (DEMO) to $receptor: $message");
        return ['success' => true, 'demo' => true];
    }

    if (KAVENEGAR_API_KEY === 'API_KEY_اینجا_بذار' || empty(KAVENEGAR_API_KEY)) {
        return ['success' => false, 'error' => 'API Key تنظیم نشده'];
    }

    $url = "https://api.kavenegar.com/v1/" . KAVENEGAR_API_KEY . "/sms/send.json";

    $params = [
        'sender'   => KAVENEGAR_SENDER,
        'receptor' => $receptor,
        'message'  => $message
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'error' => 'خطای ارتباط: ' . $curlError];
    }

    $result = json_decode($response, true);

    if (isset($result['return']['status']) && $result['return']['status'] == 200) {
        return ['success' => true, 'data' => $result['entries'] ?? null];
    }

    $errorMsg = $result['return']['message'] ?? 'خطای نامشخص';
    return ['success' => false, 'error' => $errorMsg];
}

/**
 * ارسال کد تأیید (OTP)
 */
function sendOTP($phone, $code) {
    $message = "کد تأیید پیتزا یونیک:\n$code\nاین کد تا ۵ دقیقه معتبر است.";
    return sendSMS($phone, $message);
}

/**
 * اطلاع‌رسانی سفارش جدید به فروشنده
 */
function notifySellerNewOrder($orderId, $customerName, $customerPhone, $total, $itemsText) {
    $message = "🍕 سفارش جدید #$orderId\n";
    $message .= "مشتری: $customerName\n";
    $message .= "موبایل: $customerPhone\n";
    $message .= "مبلغ: " . number_format($total) . " تومان\n";
    $message .= "اقلام:\n$itemsText";

    return sendSMS(SELLER_PHONE, $message);
}
?>
