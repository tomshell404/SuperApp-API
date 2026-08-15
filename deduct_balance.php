<?php
// deduct_balance.php
// This endpoint handles actual transfer to bank requests
// ALWAYS returns success after attempting deduction

// Set headers to match the expected format
header('Server: nslb');
header('Date: ' . gmdate('D, d M Y H:i:s T'));
header('Content-Type: application/json;charset=UTF-8');
header('Connection: keep-alive');
header('x-envoy-upstream-service-time: ' . rand(200, 500));


// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);

// Log all incoming requests for debugging
$debug_file = 'deduction_debug.log';
file_put_contents($debug_file, date('Y-m-d H:i:s') . " - GET params: " . print_r($_GET, true) . "\n", FILE_APPEND);

// Function to calculate Telebirr fee based on amount
function calculateFee($amount) {
    $amount = floatval($amount);
    
    if ($amount < 100) {
        return 1.00;
    } elseif ($amount >= 101 && $amount <= 500) {
        return 3.00;
    } elseif ($amount >= 501 && $amount <= 1500) {
        return 6.00;
    } elseif ($amount >= 1501 && $amount <= 5000) {
        return 9.00;
    } elseif ($amount >= 5001 && $amount <= 75000) {
        return 15.00;
    } else {
// For amounts above 75,000, fee maxes out at 15 ETB
        return 15.00;
    }
}

// Default values in case parameters are missing
$username = isset($_GET['username']) ? $_GET['username'] : 'unknown';
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

// Calculate fee based on amount
$feeAmount = calculateFee($amount);
$totalAmount = $amount + $feeAmount; // Total including fee

file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Processing: username=$username, amount=$amount, fee=$feeAmount, total=$totalAmount\n", FILE_APPEND);

// ALWAYS attempt deduction, but even if it fails, return success
$deduction_successful = false;

// Create connection
$db = mysqli_init();
if (!$db) {
    file_put_contents($debug_file, date('Y-m-d H:i:s') . " - MySQLi initialization failed\n", FILE_APPEND);
}

// 2. Enforce Aiven SSL parameters
$ssl_cert = __DIR__ . '/ca.pem';
$db->ssl_set(NULL, NULL, $ssl_cert, NULL, NULL);

// 3. Define Connection Credentials with Fallbacks
$db_host = getenv('DB_HOST') ?: 'telebirr-mysql-tomshell404-6264.c.aivencloud.com';
$db_user = getenv('DB_USER') ?: 'avnadmin'; 
$db_pass = getenv('DB_PASS') ?: 'AVNS_55Fv7fJr2wfxEf34fhF';
$db_name = getenv('DB_NAME') ?: 'custom_users';
$db_port = getenv('DB_PORT') ?: 11426; // Replace with your exact Aiven port number

// 4. Establish Connection
$connection_success = @$db->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

if (!$connection_success) {
    file_put_contents($debug_file, date('Y-m-d H:i:s') . " - DB connection failed\n", FILE_APPEND);
    $db->close();
} else {
    // Start transaction
    $db->begin_transaction();
    
    try {
        // Get the most recent pretransfer for this user to get holder name and bank details
        $full_name = "ZUFAN SORSA DANA";  // Default fallback
        $bank_account = "1000337793189";  // Default fallback
        $bank_code = "0003";
        
        // Check if pretransfers table exists and get data
        $table_check = $db->query("SHOW TABLES LIKE 'pretransfers'");
        if ($table_check && $table_check->num_rows > 0) {
            $pretransfer_stmt = $db->prepare("SELECT holder_name, bank_short_code, bank_account_no FROM pretransfers WHERE username = ? ORDER BY created_at DESC LIMIT 1");
            if ($pretransfer_stmt) {
                $pretransfer_stmt->bind_param("s", $username);
                $pretransfer_stmt->execute();
                $pretransfer_stmt->bind_result($pt_holder_name, $pt_bank_code, $pt_bank_account);
                if ($pretransfer_stmt->fetch()) {
                    // Use values from pretransfer if they exist
                    if (!empty($pt_holder_name)) $full_name = $pt_holder_name;
                    if (!empty($pt_bank_account)) $bank_account = $pt_bank_account;
                    if (!empty($pt_bank_code)) $bank_code = $pt_bank_code;
                    
                    file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Found pretransfer: holder=$pt_holder_name, account=$pt_bank_account\n", FILE_APPEND);
                }
                $pretransfer_stmt->close();
            }
        }
        
        // Check if user exists in users table
        $user_stmt = $db->prepare("SELECT balance FROM users WHERE username = ?");
        $user_stmt->bind_param("s", $username);
        $user_stmt->execute();
        $user_stmt->bind_result($db_balance);
        $user_exists = $user_stmt->fetch();
        $user_stmt->close();
        
        if ($user_exists) {
            $current_balance = $db_balance;
            
            // Check if sufficient balance
            if ($current_balance >= $totalAmount) {
                // Update user's balance (deduct total including fee)
                $new_balance = $current_balance - $totalAmount;
                $update_stmt = $db->prepare("UPDATE users SET balance = ? WHERE username = ?");
                $update_stmt->bind_param("ds", $new_balance, $username);
                if ($update_stmt->execute()) {
                    $deduction_successful = true;
                    
                    // Update user's info with pretransfer values
                    $update_info = $db->prepare("UPDATE users SET full_name = ?, bank_account = ?, bank_code = ? WHERE username = ?");
                    $update_info->bind_param("ssss", $full_name, $bank_account, $bank_code, $username);
                    $update_info->execute();
                    $update_info->close();
                    
                    file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Deduction successful: $totalAmount deducted from $username\n", FILE_APPEND);
                    
                    // Log transaction
                    try {
                        $log_stmt = $db->prepare("INSERT INTO transactions (username, amount, old_balance, new_balance, timestamp) VALUES (?, ?, ?, ?, NOW())");
                        $log_stmt->bind_param("sddd", $username, $totalAmount, $current_balance, $new_balance);
                        $log_stmt->execute();
                        $log_stmt->close();
                    } catch (Exception $e) {
                        // Ignore logging errors
                    }
                }
                $update_stmt->close();
            } else {
                file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Insufficient balance: has $current_balance, needs $totalAmount\n", FILE_APPEND);
            }
        } else {
            file_put_contents($debug_file, date('Y-m-d H:i:s') . " - User $username not found\n", FILE_APPEND);
        }
        
        // Commit transaction regardless of deduction success
        $db->commit();
        
    } catch (Exception $e) {
        // Rollback on error
        $db->rollback();
        file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    
    $db->close();
}

// ALWAYS return success response, regardless of what happened above
file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Returning success for $username, deduction successful: " . ($deduction_successful ? 'YES' : 'NO') . "\n", FILE_APPEND);

// Prepare success response with correct amount display
$response = [
    "responseCode" => "SYS00000",
    "responseDesc" => "Operation success.",
    "serverTimestamp" => round(microtime(true) * 1000),
    "orderId" => "141011081E02531600003009",
    "orderStatus" => "Successful",
    "transTime" => round(microtime(true) * 1000),
    "title" => "Successful",
    "actualAmountDisplay" => "-" . number_format($totalAmount, 2, '.', ''), // Total with fee (negative)
    "originalAmountDisplay" => number_format($amount, 2, '.', ''), // Original amount (positive)
    "discountAmountDisplay" => "0.00",
    "feeAmountDisplay" => number_format($feeAmount, 2, '.', ''),
    "commissionAmountDisplay" => "0.00",
    "actualAmount" => number_format($totalAmount, 2, '.', ''), // Total with fee
    "originalAmount" => number_format($amount, 2, '.', ''), // Original amount
    "discountAmount" => "0.00",
    "feeAmount" => number_format($feeAmount, 2, '.', ''),
    "commissionAmount" => "0.00",
    "taxAmountDisplay" => "0.00",
    "currency" => "ETB",
    "unit" => "ETB",
    "unitType" => "Suffix",
    "displayItems" => [
        [
            "key" => "TransactionTime",
            "label" => "Transaction Time:",
            "value" => round(microtime(true) * 1000)
        ],
        [
            "key" => "TransactionType",
            "label" => "Transaction Type:",
            "value" => "Transfer To Bank"
        ],
        [
            "key" => "TransactionTo",
            "label" => "Transaction To:",
            "value" => isset($full_name) ? $full_name : "ZUFAN SORSA DANA"
        ],
        [
            "key" => "BankAccountNumber",
            "label" => "Bank Account Number:",
            "value" => isset($bank_account) ? $bank_account : "1000337793189"
        ],
        [
            "key" => "BankName",
            "label" => "Bank Name:",
            "value" => "Commercial Bank of Ethiopia"
        ]
    ],
    "accountBalance" => "",
    "accountBalanceDisplay" => "",
    "exportImage" => true,
    "feeDisplayFormat" => "+" . number_format($feeAmount, 2, '.', '') . " ETB",
    "originalDisplayFormat" => "-" . number_format($amount, 2, '.', '') . " ETB",
    "commissionDisplayFormat" => "0.00 ETB",
    "referenceData" => ["showBanner" => "true"],
    "pollFreq" => [5, 5, 10, 10, 15, 15]
];

echo json_encode($response);
exit;
?>