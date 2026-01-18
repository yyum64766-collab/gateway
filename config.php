<?php
// config.php - Complete Payment Backend with Database Functions

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

class PaymentBackend {
    private $db;
    private $settings = [];
    
    public function __construct() {
        $this->connectDatabase();
        $this->loadSettings();
    }
    
    private function connectDatabase() {
        $host = 'localhost';
        $dbname = 'hubb895940_repaynim';
        $username = 'hubb895940_repaynim';
        $password = 'hubb895940_repaynim';
        
        try {
            $this->db = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
            // Set timezone
            $this->db->exec("SET time_zone = '+05:30'");
            
        } catch (PDOException $e) {
            $this->sendError('Database connection failed: ' . $e->getMessage());
        }
    }
    
    private function loadSettings() {
        try {
            $sql = "SELECT setting_key, setting_value, setting_type FROM system_settings";
            $stmt = $this->db->query($sql);
            $settings = $stmt->fetchAll();
            
            foreach ($settings as $setting) {
                $key = $setting['setting_key'];
                $value = $setting['setting_value'];
                $type = $setting['setting_type'];
                
                // Convert based on type
                switch ($type) {
                    case 'number':
                        $this->settings[$key] = is_numeric($value) ? $value + 0 : $value;
                        break;
                    case 'boolean':
                        $this->settings[$key] = (bool)$value;
                        break;
                    case 'json':
                        $this->settings[$key] = json_decode($value, true);
                        break;
                    case 'array':
                        $this->settings[$key] = explode(',', $value);
                        break;
                    default:
                        $this->settings[$key] = $value;
                }
            }
        } catch (PDOException $e) {
            error_log('Settings load error: ' . $e->getMessage());
        }
    }
    
    // Handle incoming payment request
    public function handlePaymentRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'POST':
                $this->processPayment();
                break;
            case 'GET':
                $this->getPaymentStatus();
                break;
            case 'OPTIONS':
                $this->sendResponse(['message' => 'Options']);
                break;
            default:
                $this->sendError('Method not allowed');
        }
    }
    
    private function processPayment() {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $input = $_POST;
        }
        
        // Validate required fields
        $required = ['amount', 'upi_id', 'customer_phone', 'transaction_ref', 'link_id'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $this->sendError("Missing required field: $field");
            }
        }
        
        // Check if link is expired
        $linkId = $this->sanitize($input['link_id']);
        if (!$this->isLinkValid($linkId)) {
            $this->sendError("Payment link has expired. Links are valid for " . ($this->settings['link_expiry_hours'] ?? 6) . " hours only.");
        }
        
        // Sanitize inputs
        $amount = floatval($input['amount']);
        $upiId = $this->sanitize($input['upi_id']);
        $customerPhone = $this->sanitize($input['customer_phone']);
        $transactionRef = $this->sanitize($input['transaction_ref']);
        $note = $this->sanitize($input['note'] ?? 'Loan Repayment');
        $merchantName = $this->sanitize($input['merchant_name'] ?? 'NimCredit');
        $agentId = $this->sanitize($input['agent_id'] ?? '');
        
        // Validate amount limits
        $minAmount = $this->settings['min_amount_per_link'] ?? 100;
        $maxAmount = $this->settings['max_amount_per_link'] ?? 50000;
        
        if ($amount < $minAmount) {
            $this->sendError("Minimum payment amount is ₹$minAmount");
        }
        
        if ($amount > $maxAmount) {
            $this->sendError("Maximum payment amount is ₹$maxAmount");
        }
        
        // Check UPI daily limit
        if (!$this->checkUpiDailyLimit($upiId, $amount)) {
            $dailyLimit = $this->settings['daily_payment_limit'] ?? 1000000;
            $this->sendError("UPI daily limit reached. Maximum ₹" . number_format($dailyLimit) . " per day");
        }
        
        // Generate unique payment ID
        $paymentId = 'PAY' . time() . rand(1000, 9999);
        
        // Generate UPI deep link
        $upiDeepLink = $this->generateUpiDeepLink([
            'pa' => $upiId,
            'pn' => $merchantName,
            'tid' => $transactionRef,
            'tr' => $paymentId,
            'tn' => $note,
            'am' => $amount,
            'cu' => 'INR',
            'url' => $this->getCallbackUrl($paymentId)
        ]);
        
        // Get link details
        $linkDetails = $this->getLinkDetails($linkId);
        
        // Store payment in database
        $expiryHours = $this->settings['link_expiry_hours'] ?? 6;
        $this->storePayment([
            'payment_id' => $paymentId,
            'transaction_ref' => $transactionRef,
            'link_id' => $linkId,
            'loan_id' => $linkDetails['loan_id'] ?? null,
            'amount' => $amount,
            'upi_id' => $upiId,
            'customer_phone' => $customerPhone,
            'agent_id' => $agentId,
            'status' => 'pending',
            'upi_deep_link' => $upiDeepLink,
            'payment_apps' => json_encode($this->getPaymentApps($upiDeepLink, $amount)),
            'created_at' => date('Y-m-d H:i:s'),
            'expiry_time' => date('Y-m-d H:i:s', strtotime("+$expiryHours hours"))
        ]);
        
        // Track link click for statistics
        $this->trackLinkClick($linkId, $agentId, $customerPhone, $paymentId);
        
        // Update link generation stats
        $this->updateLinkStats($linkId);
        
        // Log activity
        $this->logActivity($agentId, 'payment_initiated', "Payment ₹$amount initiated for $customerPhone");
        
        // Return payment initiation response
        $this->sendResponse([
            'success' => true,
            'payment_id' => $paymentId,
            'upi_deep_link' => $upiDeepLink,
            'payment_apps' => $this->getPaymentApps($upiDeepLink, $amount),
            'amount' => $amount,
            'link_expiry' => date('Y-m-d H:i:s', strtotime("+$expiryHours hours")),
            'message' => 'Payment initiated successfully'
        ]);
    }
    
    private function getLinkDetails($linkId) {
        try {
            $sql = "SELECT * FROM link_generations WHERE link_id = :link_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['link_id' => $linkId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Get link details error: ' . $e->getMessage());
            return [];
        }
    }
    
    private function isLinkValid($linkId) {
        try {
            $expiryHours = $this->settings['link_expiry_hours'] ?? 6;
            $maxClicks = $this->settings['max_clicks_per_link'] ?? 100;
            
            $sql = "SELECT * FROM link_generations 
                    WHERE link_id = :link_id 
                    AND link_status = 'active'
                    AND expiry_time > NOW() 
                    AND total_clicks < :max_clicks";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'link_id' => $linkId,
                'max_clicks' => $maxClicks
            ]);
            
            $link = $stmt->fetch();
            return !empty($link);
            
        } catch (PDOException $e) {
            error_log('Link validation error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function checkUpiDailyLimit($upiId, $amount) {
        try {
            $dailyLimit = $this->settings['daily_payment_limit'] ?? 1000000;
            
            $sql = "SELECT COALESCE(SUM(amount), 0) as daily_total 
                    FROM payments 
                    WHERE upi_id = :upi_id 
                    AND DATE(created_at) = CURDATE() 
                    AND payment_status IN ('success', 'processing')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['upi_id' => $upiId]);
            $result = $stmt->fetch();
            
            return ($result['daily_total'] + $amount) <= $dailyLimit;
            
        } catch (PDOException $e) {
            error_log('UPI limit check error: ' . $e->getMessage());
            return true;
        }
    }
    
    private function trackLinkClick($linkId, $agentId, $customerPhone, $paymentId) {
        try {
            // Get device information
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            $deviceType = $this->getDeviceType($userAgent);
            $browser = $this->getBrowser($userAgent);
            $platform = $this->getPlatform($userAgent);
            
            // Record click
            $sql = "INSERT INTO link_clicks 
                    (link_id, agent_id, customer_phone, ip_address, user_agent, 
                     device_type, browser, platform, payment_initiated, payment_status)
                    VALUES (:link_id, :agent_id, :customer_phone, :ip_address, :user_agent,
                            :device_type, :browser, :platform, TRUE, 'pending')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'link_id' => $linkId,
                'agent_id' => $agentId,
                'customer_phone' => $customerPhone,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'device_type' => $deviceType,
                'browser' => $browser,
                'platform' => $platform
            ]);
            
        } catch (PDOException $e) {
            error_log('Link tracking error: ' . $e->getMessage());
        }
    }
    
    private function updateLinkStats($linkId) {
        try {
            // Update click count
            $sql = "UPDATE link_generations 
                   SET total_clicks = total_clicks + 1,
                       last_clicked = NOW()
                   WHERE link_id = :link_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['link_id' => $linkId]);
            
        } catch (PDOException $e) {
            error_log('Update link stats error: ' . $e->getMessage());
        }
    }
    
    private function generateUpiDeepLink($params) {
        // Build UPI URL
        $queryString = http_build_query($params);
        return "upi://pay?$queryString";
    }
    
    private function getPaymentApps($upiDeepLink, $amount) {
        // For amounts above 2000, return only generic UPI
        if ($amount > 2000) {
            return [
                'generic' => $upiDeepLink,
                'message' => 'For amounts above ₹2000, please copy UPI ID and pay directly in your UPI app'
            ];
        }
        
        // Return different app-specific deep links for amounts <= 2000
        return [
            'google_pay' => str_replace('upi://', 'tez://', $upiDeepLink),
            'phonepe' => str_replace('upi://pay', 'phonepe://pay', $upiDeepLink),
            'paytm' => str_replace('upi://pay', 'paytmmp://pay', $upiDeepLink),
            'bhim' => str_replace('upi://pay', 'bhim://pay', $upiDeepLink),
            'whatsapp' => str_replace('upi://pay', 'whatsapp://pay', $upiDeepLink),
            'generic' => $upiDeepLink
        ];
    }
    
    private function getCallbackUrl($paymentId) {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                   . "://$_SERVER[HTTP_HOST]";
        return $baseUrl . "/payment-callback.php?payment_id=" . urlencode($paymentId);
    }
    
    private function storePayment($data) {
        try {
            $sql = "INSERT INTO payments (
                payment_id, transaction_ref, link_id, loan_id, amount, upi_id, 
                customer_phone, agent_id, payment_status, upi_deep_link, payment_apps, 
                created_at, expiry_time
            ) VALUES (
                :payment_id, :transaction_ref, :link_id, :loan_id, :amount, :upi_id,
                :customer_phone, :agent_id, :status, :upi_deep_link, :payment_apps, 
                :created_at, :expiry_time
            )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($data);
            
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
        }
    }
    
    private function getPaymentStatus() {
        $paymentId = $_GET['payment_id'] ?? '';
        
        if (empty($paymentId)) {
            $this->sendError('Payment ID required');
        }
        
        try {
            $sql = "SELECT * FROM payments WHERE payment_id = :payment_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['payment_id' => $paymentId]);
            $payment = $stmt->fetch();
            
            if (!$payment) {
                $this->sendError('Payment not found');
            }
            
            // Check if payment is expired
            if (strtotime($payment['expiry_time']) < time() && $payment['payment_status'] === 'pending') {
                $payment['payment_status'] = 'expired';
                $this->updatePaymentStatus($paymentId, 'expired', '', '');
            }
            
            // Get additional details
            $payment['agent'] = $this->getAgentDetails($payment['agent_id']);
            $payment['loan'] = $this->getLoanDetails($payment['loan_id']);
            $payment['link'] = $this->getLinkDetails($payment['link_id']);
            
            $this->sendResponse([
                'success' => true,
                'payment' => $payment
            ]);
            
        } catch (PDOException $e) {
            $this->sendError('Database error: ' . $e->getMessage());
        }
    }
    
    private function getAgentDetails($agentId) {
        try {
            $sql = "SELECT agent_id, username, full_name, role FROM agents WHERE agent_id = :agent_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['agent_id' => $agentId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
    
    private function getLoanDetails($loanId) {
        if (!$loanId) return null;
        
        try {
            $sql = "SELECT loan_id, loan_number, customer_name, loan_amount FROM loans WHERE loan_id = :loan_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['loan_id' => $loanId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
    
    // Handle payment callback from UPI app
    public function handlePaymentCallback() {
        $paymentId = $_GET['payment_id'] ?? '';
        $status = $_GET['status'] ?? 'unknown';
        $transactionId = $_GET['transaction_id'] ?? '';
        $bankRef = $_GET['bank_ref'] ?? '';
        
        if (empty($paymentId)) {
            $this->sendError('Payment ID required');
        }
        
        // Update payment status in database
        $this->updatePaymentStatus($paymentId, $status, $transactionId, $bankRef);
        
        // Send response for UPI app
        if ($status === 'success') {
            echo "Payment successful! Transaction ID: $transactionId";
        } else {
            echo "Payment failed. Please try again.";
        }
    }
    
    private function updatePaymentStatus($paymentId, $status, $transactionId, $bankRef) {
        try {
            $sql = "UPDATE payments 
                    SET payment_status = :status,
                        transaction_id = :transaction_id,
                        bank_reference = :bank_ref,
                        updated_at = NOW(),
                        completed_at = CASE 
                            WHEN :status = 'success' THEN NOW() 
                            ELSE NULL 
                        END
                    WHERE payment_id = :payment_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'payment_id' => $paymentId,
                'status' => $status,
                'transaction_id' => $transactionId,
                'bank_ref' => $bankRef
            ]);
            
            // Update link click status
            $this->updateClickPaymentStatus($paymentId, $status);
            
            // If payment successful, update loan status and link statistics
            if ($status === 'success') {
                $this->updateLoanAfterPayment($paymentId);
                $this->updateLinkPaymentStats($paymentId);
                $this->recordCommission($paymentId);
                $this->sendNotifications($paymentId);
            }
            
            // Log activity
            $payment = $this->getPaymentDetails($paymentId);
            if ($payment) {
                $this->logActivity($payment['agent_id'], 'payment_' . $status, 
                    "Payment ₹{$payment['amount']} " . $status . " for {$payment['customer_phone']}");
            }
            
        } catch (PDOException $e) {
            error_log('Update status error: ' . $e->getMessage());
        }
    }
    
    private function updateClickPaymentStatus($paymentId, $status) {
        try {
            // Find the click associated with this payment
            $sql = "UPDATE link_clicks lc
                   INNER JOIN payments p ON lc.link_id = p.link_id 
                   AND lc.customer_phone = p.customer_phone
                   SET lc.payment_status = :status
                   WHERE p.payment_id = :payment_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'payment_id' => $paymentId,
                'status' => $status
            ]);
        } catch (PDOException $e) {
            error_log('Update click status error: ' . $e->getMessage());
        }
    }
    
    private function getPaymentDetails($paymentId) {
        try {
            $sql = "SELECT * FROM payments WHERE payment_id = :payment_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['payment_id' => $paymentId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
    
    private function updateLinkPaymentStats($paymentId) {
        try {
            // Get payment to find link_id
            $sql = "SELECT link_id, amount FROM payments WHERE payment_id = :payment_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['payment_id' => $paymentId]);
            $payment = $stmt->fetch();
            
            if ($payment && $payment['link_id']) {
                // Update successful payment count
                $updateSql = "UPDATE link_generations 
                             SET successful_payments = successful_payments + 1,
                                 total_amount_collected = total_amount_collected + :amount,
                                 last_payment = NOW()
                             WHERE link_id = :link_id";
                
                $updateStmt = $this->db->prepare($updateSql);
                $updateStmt->execute([
                    'link_id' => $payment['link_id'],
                    'amount' => $payment['amount']
                ]);
            }
            
        } catch (PDOException $e) {
            error_log('Update link stats error: ' . $e->getMessage());
        }
    }
    
    private function updateLoanAfterPayment($paymentId) {
        try {
            // Get payment details
            $payment = $this->getPaymentDetails($paymentId);
            
            if ($payment && $payment['loan_id']) {
                // Update loan repayment count
                $updateSql = "UPDATE loans 
                             SET repayment_count = repayment_count + 1,
                                 total_repaid = total_repaid + :amount,
                                 outstanding_amount = loan_amount - total_repaid,
                                 last_payment_date = NOW(),
                                 updated_at = NOW()
                             WHERE loan_id = :loan_id";
                
                $updateStmt = $this->db->prepare($updateSql);
                $updateStmt->execute([
                    'loan_id' => $payment['loan_id'],
                    'amount' => $payment['amount']
                ]);
                
                // Check if loan is fully paid
                $checkSql = "SELECT loan_amount, total_repaid FROM loans WHERE loan_id = :loan_id";
                $checkStmt = $this->db->prepare($checkSql);
                $checkStmt->execute(['loan_id' => $payment['loan_id']]);
                $loan = $checkStmt->fetch();
                
                if ($loan && $loan['total_repaid'] >= $loan['loan_amount']) {
                    $finalizeSql = "UPDATE loans SET loan_status = 'closed' WHERE loan_id = :loan_id";
                    $finalizeStmt = $this->db->prepare($finalizeSql);
                    $finalizeStmt->execute(['loan_id' => $payment['loan_id']]);
                }
            }
            
        } catch (PDOException $e) {
            error_log('Update loan error: ' . $e->getMessage());
        }
    }
    
    private function recordCommission($paymentId) {
        try {
            // Get payment details
            $payment = $this->getPaymentDetails($paymentId);
            if (!$payment) return;
            
            // Get agent's commission rate
            $sql = "SELECT agent_id, commission_rate FROM agents WHERE agent_id = :agent_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['agent_id' => $payment['agent_id']]);
            $agent = $stmt->fetch();
            
            if ($agent) {
                $commission = $payment['amount'] * ($agent['commission_rate'] / 100);
                
                // Record commission
                $insertSql = "INSERT INTO commissions 
                             (agent_id, payment_id, loan_id, commission_amount, 
                              commission_rate, commission_status, payment_date)
                             VALUES (:agent_id, :payment_id, :loan_id, :commission_amount,
                                     :commission_rate, 'pending', NOW())";
                
                $insertStmt = $this->db->prepare($insertSql);
                $insertStmt->execute([
                    'agent_id' => $agent['agent_id'],
                    'payment_id' => $paymentId,
                    'loan_id' => $payment['loan_id'],
                    'commission_amount' => $commission,
                    'commission_rate' => $agent['commission_rate']
                ]);
                
                // Update agent's total commission
                $updateAgentSql = "UPDATE agents 
                                  SET total_commission = total_commission + :commission,
                                      last_commission_date = NOW()
                                  WHERE agent_id = :agent_id";
                
                $updateStmt = $this->db->prepare($updateAgentSql);
                $updateStmt->execute([
                    'agent_id' => $agent['agent_id'],
                    'commission' => $commission
                ]);
            }
            
        } catch (PDOException $e) {
            error_log('Commission error: ' . $e->getMessage());
        }
    }
    
    private function sendNotifications($paymentId) {
        if (empty($this->settings['enable_email_notifications']) && 
            empty($this->settings['enable_sms_notifications']) &&
            empty($this->settings['enable_whatsapp_notifications'])) {
            return;
        }
        
        try {
            // Get payment details with agent and customer info
            $sql = "SELECT p.*, a.full_name as agent_name, a.email as agent_email,
                           l.customer_name, l.customer_email as customer_email
                    FROM payments p
                    LEFT JOIN agents a ON p.agent_id = a.agent_id
                    LEFT JOIN loans l ON p.loan_id = l.loan_id
                    WHERE p.payment_id = :payment_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['payment_id' => $paymentId]);
            $payment = $stmt->fetch();
            
            if (!$payment) return;
            
            // Prepare notification message
            $title = "Payment Received - ₹" . $payment['amount'];
            $message = "Payment of ₹{$payment['amount']} received from {$payment['customer_name']} ({$payment['customer_phone']})";
            
            // Create notification for agent
            $notifSql = "INSERT INTO notifications 
                        (agent_id, notification_type, title, message, 
                         related_table, related_id)
                        VALUES (:agent_id, 'payment_success', :title, :message,
                                'payments', :payment_id)";
            
            $notifStmt = $this->db->prepare($notifSql);
            $notifStmt->execute([
                'agent_id' => $payment['agent_id'],
                'title' => $title,
                'message' => $message,
                'payment_id' => $paymentId
            ]);
            
            // Send email notification if enabled
            if ($this->settings['enable_email_notifications'] && !empty($payment['agent_email'])) {
                $this->sendEmailNotification($payment);
            }
            
            // Send SMS notification if enabled
            if ($this->settings['enable_sms_notifications'] && !empty($payment['customer_phone'])) {
                $this->sendSmsNotification($payment);
            }
            
            // Send WhatsApp notification if enabled
            if ($this->settings['enable_whatsapp_notifications'] && !empty($payment['customer_phone'])) {
                $this->sendWhatsAppNotification($payment);
            }
            
        } catch (PDOException $e) {
            error_log('Notification error: ' . $e->getMessage());
        }
    }
    
    private function sendEmailNotification($payment) {
        // This is a placeholder - implement actual email sending
        $to = $payment['agent_email'];
        $subject = "Payment Received - NimCredit";
        $message = "Dear {$payment['agent_name']},\n\n" .
                   "A payment of ₹{$payment['amount']} has been successfully received from " .
                   "{$payment['customer_name']} ({$payment['customer_phone']}).\n\n" .
                   "Transaction ID: {$payment['transaction_id']}\n" .
                   "Payment ID: {$payment['payment_id']}\n" .
                   "Date: " . date('d-m-Y H:i:s') . "\n\n" .
                   "Thank you,\nNimCredit Team";
        
        $headers = "From: notifications@nimcredit.com\r\n" .
                   "Reply-To: support@nimcredit.com\r\n" .
                   "X-Mailer: PHP/" . phpversion();
        
        // mail($to, $subject, $message, $headers);
        error_log("Email notification sent to: $to");
    }
    
    private function sendSmsNotification($payment) {
        // This is a placeholder - implement actual SMS gateway integration
        $apiKey = $this->settings['sms_api_key'] ?? '';
        $senderId = $this->settings['sms_sender_id'] ?? 'NIMCRD';
        $phone = $payment['customer_phone'];
        
        $message = "Dear {$payment['customer_name']}, payment of ₹{$payment['amount']} received. " .
                   "Transaction ID: {$payment['transaction_id']}. Thank you! - NimCredit";
        
        // Use SMS gateway API here
        error_log("SMS notification sent to: $phone");
    }
    
    private function sendWhatsAppNotification($payment) {
        // This is a placeholder - implement actual WhatsApp API integration
        $phone = $payment['customer_phone'];
        $template = $this->settings['whatsapp_message_template'] ?? '';
        
        $message = str_replace(
            ['{customer_name}', '{amount}', '{transaction_id}', '{payment_date}'],
            [$payment['customer_name'], $payment['amount'], $payment['transaction_id'], date('d-m-Y H:i:s')],
            $template
        );
        
        error_log("WhatsApp notification sent to: $phone");
    }
    
    private function getDeviceType($userAgent) {
        $userAgent = strtolower($userAgent);
        
        if (strpos($userAgent, 'mobile') !== false) {
            return 'Mobile';
        } elseif (strpos($userAgent, 'tablet') !== false) {
            return 'Tablet';
        } else {
            return 'Desktop';
        }
    }
    
    private function getBrowser($userAgent) {
        $userAgent = strtolower($userAgent);
        
        if (strpos($userAgent, 'chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($userAgent, 'firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($userAgent, 'safari') !== false) {
            return 'Safari';
        } elseif (strpos($userAgent, 'edge') !== false) {
            return 'Edge';
        } elseif (strpos($userAgent, 'opera') !== false) {
            return 'Opera';
        } else {
            return 'Unknown';
        }
    }
    
    private function getPlatform($userAgent) {
        $userAgent = strtolower($userAgent);
        
        if (strpos($userAgent, 'windows') !== false) {
            return 'Windows';
        } elseif (strpos($userAgent, 'mac') !== false) {
            return 'Mac';
        } elseif (strpos($userAgent, 'linux') !== false) {
            return 'Linux';
        } elseif (strpos($userAgent, 'android') !== false) {
            return 'Android';
        } elseif (strpos($userAgent, 'iphone') !== false || strpos($userAgent, 'ipad') !== false) {
            return 'iOS';
        } else {
            return 'Unknown';
        }
    }
    
    private function logActivity($agentId, $activityType, $description) {
        try {
            $sql = "INSERT INTO activity_logs 
                    (agent_id, activity_type, description, ip_address, user_agent)
                    VALUES (:agent_id, :activity_type, :description, :ip_address, :user_agent)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'agent_id' => $agentId,
                'activity_type' => $activityType,
                'description' => $description,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (PDOException $e) {
            error_log('Activity log error: ' . $e->getMessage());
        }
    }
    
    // Get agent statistics
    public function getAgentStats($agentId) {
        try {
            // Basic stats
            $sql = "SELECT 
                    COUNT(DISTINCT link_id) as total_links,
                    COUNT(*) as total_clicks,
                    COUNT(DISTINCT customer_phone) as unique_customers,
                    SUM(CASE WHEN payment_status = 'success' THEN 1 ELSE 0 END) as successful_payments,
                    SUM(CASE WHEN clicked_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as clicks_last_7_days,
                    SUM(CASE WHEN clicked_at > DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as clicks_today
                    FROM link_clicks 
                    WHERE agent_id = :agent_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['agent_id' => $agentId]);
            $stats = $stmt->fetch();
            
            // Payment stats
            $paymentSql = "SELECT 
                          COUNT(*) as total_payments,
                          SUM(amount) as total_amount,
                          SUM(CASE WHEN payment_status = 'success' THEN amount ELSE 0 END) as successful_amount,
                          SUM(CASE WHEN payment_status = 'success' THEN 1 ELSE 0 END) as successful_count
                          FROM payments 
                          WHERE agent_id = :agent_id";
            
            $paymentStmt = $this->db->prepare($paymentSql);
            $paymentStmt->execute(['agent_id' => $agentId]);
            $paymentStats = $paymentStmt->fetch();
            
            // Commission stats
            $commissionSql = "SELECT 
                             SUM(commission_amount) as total_commission,
                             SUM(CASE WHEN commission_status = 'paid' THEN commission_amount ELSE 0 END) as paid_commission
                             FROM commissions 
                             WHERE agent_id = :agent_id";
            
            $commissionStmt = $this->db->prepare($commissionSql);
            $commissionStmt->execute(['agent_id' => $agentId]);
            $commissionStats = $commissionStmt->fetch();
            
            // Recent links
            $linksSql = "SELECT * FROM link_generations 
                        WHERE agent_id = :agent_id 
                        ORDER BY generated_at DESC 
                        LIMIT 10";
            
            $linksStmt = $this->db->prepare($linksSql);
            $linksStmt->execute(['agent_id' => $agentId]);
            $recentLinks = $linksStmt->fetchAll();
            
            // Recent payments
            $paymentsSql = "SELECT p.*, l.customer_name 
                           FROM payments p
                           LEFT JOIN loans l ON p.loan_id = l.loan_id
                           WHERE p.agent_id = :agent_id 
                           ORDER BY p.created_at DESC 
                           LIMIT 10";
            
            $paymentsStmt = $this->db->prepare($paymentsSql);
            $paymentsStmt->execute(['agent_id' => $agentId]);
            $recentPayments = $paymentsStmt->fetchAll();
            
            return [
                'stats' => $stats,
                'payment_stats' => $paymentStats,
                'commission_stats' => $commissionStats,
                'recent_links' => $recentLinks,
                'recent_payments' => $recentPayments
            ];
            
        } catch (PDOException $e) {
            error_log('Get stats error: ' . $e->getMessage());
            return null;
        }
    }
    
    private function sanitize($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
    
    private function sendResponse($data) {
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    private function sendError($message) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $message
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// Initialize and run the backend
$backend = new PaymentBackend();

// Route based on URL
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

if (strpos($path, 'payment-callback') !== false) {
    $backend->handlePaymentCallback();
} elseif (strpos($path, 'agent-stats') !== false && isset($_GET['agent_id'])) {
    $stats = $backend->getAgentStats($_GET['agent_id']);
    echo json_encode(['success' => true, 'data' => $stats], JSON_PRETTY_PRINT);
} else {
    $backend->handlePaymentRequest();
}
?>