<?php

require_once __DIR__ . '/db.php';
header('Content-Type: application/json;charset=UTF-8');
header('Server: nslb');
header('Date: ' . gmdate('D, d M Y H:i:s T'));
header('Connection: keep-alive');
header('origconversationid: ' . generateUUID());
header('x-envoy-upstream-service-time: ' . rand(400, 600));
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);

$debug_file = 'pretransfer_debug.log';

function calculateFee($amount) {
    $amount = floatval($amount);
    if ($amount <= 100) return 1.00;
    elseif ($amount <= 500) return 3.00;
    elseif ($amount <= 1500) return 6.00;
    elseif ($amount <= 5000) return 9.00;
    else return 15.00;
}

$username = $_GET['username'] ?? '';

file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Username: $username\n", FILE_APPEND);

if (empty($username)) {
    echo json_encode([
        "responseCode" => "SYS00001",
        "responseDesc" => "Username parameter is required",
        "serverTimestamp" => round(microtime(true) * 1000)
    ]);
    exit;
}

$jsonData = file_get_contents('php://input');
$requestData = json_decode($jsonData, true);

file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Request: $jsonData\n", FILE_APPEND);

if ($conn->connect_error) {
    echo json_encode([
        "responseCode" => "SYS00002",
        "responseDesc" => "Database connection failed",
        "serverTimestamp" => round(microtime(true) * 1000)
    ]);
    exit;
}

if (!$requestData) {
    echo json_encode([
        "responseCode" => "SYS00001",
        "responseDesc" => "Invalid request data",
        "serverTimestamp" => round(microtime(true) * 1000)
    ]);
    exit;
}

$amount = floatval($requestData['amount'] ?? 0);
$holderName = $requestData['holderName'] ?? '';
$bankShortCode = $requestData['bankShortCode'] ?? '0003';
$bankAccountNo = $requestData['bankCardAccountNo'] ?? '';

$feeAmount = calculateFee($amount);
$originalAmount = $amount;
$totalAmount = bcadd($originalAmount, $feeAmount, 2);

$prepayId = generatePrepayId();
$timestamp = round(microtime(true) * 1000);

$conn->begin_transaction();

try {
    // MAIN BALANCE
    $stmt = $conn->prepare("SELECT balance FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($main_balance);
    $user_exists = $stmt->fetch();
    $stmt->close();

    if (!$user_exists) {
        throw new Exception("User not found");
    }

    // EXTRA BALANCE (user_records)
    $stmt = $conn->prepare("SELECT balance FROM user_records WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($record_balance);
    $stmt->fetch();
    $stmt->close();

    if (!$record_balance) $record_balance = 0.00;

    // TOTAL BALANCE
    $total_balance = bcadd($main_balance, $record_balance, 2);

    // CHECK BALANCE
    if ($total_balance < $totalAmount) {
        file_put_contents($debug_file, "INSUFFICIENT: $total_balance < $totalAmount\n", FILE_APPEND);

        echo json_encode([
            "responseCode" => "app.insufficient_balance",
            "responseDesc" => "Your balance is insufficient.",
            "serverTimestamp" => round(microtime(true) * 1000)
        ]);

        $conn->rollback();
        $conn->close();
        exit;
    }

    // INSERT
    $stmt = $conn->prepare("INSERT INTO pretransfers 
        (prepay_id, username, amount, fee_amount, total_amount, holder_name, bank_short_code, bank_account_no, timestamp, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    $stmt->bind_param("ssddsssss",
        $prepayId,
        $username,
        $amount,
        $feeAmount,
        $totalAmount,
        $holderName,
        $bankShortCode,
        $bankAccountNo,
        $timestamp
    );

    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // FORMAT VALUES
    $balanceFormatted = number_format($total_balance, 2, '.', '');
    $amountFormatted = number_format($originalAmount, 2, '.', '');
    $feeFormatted = number_format($feeAmount, 2, '.', '');
    $totalFormatted = number_format($totalAmount, 2, '.', '');

    $response = [
        "responseCode" => "SYS00000",
        "responseDesc" => "Operation success.",
        "serverTimestamp" => (string)$timestamp,
        "balance" => $balanceFormatted,
        "balanceDisplay" => $balanceFormatted,
        "oppositeName" => getBankName($bankShortCode),
        "actualAmount" => $totalFormatted,
        "actualAmountDisplay" => $totalFormatted,
        "originalAmount" => $amountFormatted,
        "originalAmountDisplay" => $amountFormatted,
        "discountAmount" => "0.00",
        "discountAmountDisplay" => "0.00",
        "feeAmount" => $feeFormatted,
        "feeAmountDisplay" => $feeFormatted,
        "commissionAmount" => "0.00",
        "commissionAmountDisplay" => "0.00",
        "taxAmount" => "0.00",
        "taxAmountDisplay" => "0.00",
        "currency" => "ETB",
        "unit" => "ETB",
        "unitType" => "Suffix",
        "subTitle" => "Transfer To Bank",
        "displayItems" => ["Fee:$feeFormatted"],
        "prepayId" => $prepayId,
        "fundsSourceInfoDisplay" => [[
            "fundsSource" => "WALLET",
            "accountType" => "Customer E-Money Account",
            "available" => true,
            "icon" => "icon_wallet",
            "defaultAccount" => "true",
            "displayItems" => [[
                "label" => "Fee",
                "value" => $feeFormatted . "ETB",
                "key" => "feeAmount",
                "order" => "2"
            ]],
            "optimalDiscountAmount" => "0.00",
            "isDefault" => true,
            "payAmount" => "ETB",
            "displayContent" => "(Available Balance:" . $balanceFormatted . "ETB)",
            "payMethod" => "Balance",
            "fundsSourceDisplay" => "Balance",
            "order" => "0"
        ]],
        "confirm" => true,
        "freePin" => false,
        "confirmDisplayItems" => [
            [
                "label" => "Original Amount",
                "value" => $amountFormatted . "ETB",
                "key" => "OriginalAmount"
            ],
            [
                "label" => "Service fee",
                "value" => $feeFormatted . "ETB",
                "key" => "Fee"
            ]
        ],
        "needShowOd" => false,
        "openODMsg" => "Sorry, your telebirr balance is insufficient, do you want to activate telebirr endekise service?",
        "singleODMsg" => "Sorry, your telebirr balance is insufficient, do you want to complete the transaction using telebirr endekise?"
    ];

    echo json_encode($response);

} catch (Exception $e) {
    $conn->rollback();

    echo json_encode([
        "responseCode" => "SYS00001",
        "responseDesc" => $e->getMessage(),
        "serverTimestamp" => round(microtime(true) * 1000)
    ]);
}

$conn->close();

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function generatePrepayId() {
    return bin2hex(random_bytes(20)) . '03';
}

function getBankName($shortCode) {
    $banks = [
        '0003' => 'Commercial Bank of Ethiopia',
        '0004' => 'Awash Bank',
        '0005' => 'Dashen Bank',
        '0006' => 'Bank of Abyssinia',
        '0007' => 'Wegagen Bank',
        '0008' => 'United Bank',
        '0009' => 'NIB International Bank',
        '0010' => 'Cooperative Bank of Oromia',
        '0011' => 'Lion International Bank',
        '0012' => 'Zemen Bank',
        '0013' => 'Berhan Bank',
        '0014' => 'Abay Bank',
        '0015' => 'Addis International Bank',
        '0016' => 'Buna Bank',
        '0017' => 'Debub Global Bank',
        '0018' => 'Enat Bank',
        '0019' => 'Hijra Bank',
        '0020' => 'Oromia Bank',
        '0021' => 'Sino Ethiopia Bank',
        '0022' => 'Gadaa Bank',
        '0023' => 'Tsehay Bank'
    ];
    return $banks[$shortCode] ?? 'Commercial Bank of Ethiopia';
}
?>