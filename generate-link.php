<?php
// generate-link.php - Generate new payment link
session_start();
ob_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'hubb895940_repaynim');
define('DB_USER', 'hubb895940_repaynim');
define('DB_PASS', 'hubb895940_repaynim');

function connectDB() {
    try {
        return new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (PDOException $e) {
        die("Database connection failed");
    }
}

function checkLogin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        header('Location: login.php');
        exit();
    }
}

// Get user info
$db = connectDB();
checkLogin();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get UPI accounts
$upiAccounts = $db->query("SELECT * FROM upi_accounts WHERE status = 'active' ORDER BY account_type")->fetchAll();

// Get agents for admin
$agents = [];
if ($user_role == 'superadmin' || $user_role == 'admin') {
    $agents = $db->query("SELECT agent_id, username, full_name FROM agents WHERE status = 'active' AND role = 'agent' ORDER BY full_name")->fetchAll();
}

// Handle form submission
$error = '';
$success = '';
$generatedLinks = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = $_POST['customer_name'] ?? '';
    $customer_phone = $_POST['customer_phone'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $reference = $_POST['reference'] ?? '';
    $upi_address = $_POST['upi_address'] ?? '';
    $agent_id = $_POST['agent_id'] ?? $user_id;
    $expiry_hours = $_POST['expiry_hours'] ?? 6;
    
    // Validate inputs
    if (empty($customer_name) || empty($customer_phone) || empty($amount) || empty($upi_address)) {
        $error = 'Please fill all required fields';
    } elseif (!preg_match('/^\d{10}$/', $customer_phone)) {
        $error = 'Please enter a valid 10-digit phone number';
    } elseif (!is_numeric($amount) || $amount < 1) {
        $error = 'Please enter a valid amount';
    } elseif ($amount > 50000) {
        $error = 'Maximum amount per link is ₹50,000';
    } else {
        // Generate unique link ID
        $link_id = 'LNK' . time() . rand(100, 999);
        
        // Generate payment URL
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $payment_url = "$base_url/pay.html?link_id=$link_id&amount=$amount&phone=$customer_phone&name=" . urlencode($customer_name) . "&upi=" . urlencode($upi_address);
        
        if (!empty($reference)) {
            $payment_url .= "&ref=" . urlencode($reference);
        }
        
        // Calculate expiry time
        $expiry_time = date('Y-m-d H:i:s', strtotime("+$expiry_hours hours"));
        
        // Insert into database
        $stmt = $db->prepare("INSERT INTO link_generations (link_id, agent_id, customer_name, customer_phone, amount, upi_address, reference_number, link_url, link_status, expiry_time, generated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())");
        $stmt->execute([$link_id, $agent_id, $customer_name, $customer_phone, $amount, $upi_address, $reference, $payment_url, $expiry_time]);
        
        // Log activity
        $logStmt = $db->prepare("INSERT INTO activity_logs (agent_id, activity_type, description) VALUES (?, 'link_generated', ?)");
        $logStmt->execute([$user_id, "Generated link for $customer_name - ₹$amount"]);
        
        // Prepare WhatsApp message
        $whatsapp_message = "Hi $customer_name,\n\n";
        $whatsapp_message .= "Your payment link for ₹$amount has been generated.\n";
        $whatsapp_message .= "Please pay using this link: $payment_url\n\n";
        $whatsapp_message .= "Link valid for $expiry_hours hours.\n";
        $whatsapp_message .= "Reference: $reference\n\n";
        $whatsapp_message .= "- NimCredit Team";
        
        // Store generated link info
        $generatedLinks[] = [
            'link_id' => $link_id,
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'amount' => $amount,
            'payment_url' => $payment_url,
            'whatsapp_message' => $whatsapp_message,
            'expiry_time' => $expiry_time
        ];
        
        $success = 'Payment link generated successfully!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Link - NimCredit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <?php include 'header.php'; ?>
    
    <div class="flex">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="flex-1 p-6">
            <div class="max-w-4xl mx-auto">
                <!-- Page Header -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-800">Generate Payment Link</h1>
                    <p class="text-gray-600">Create a new payment link for your customer</p>
                </div>
                
                <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                        <span class="text-red-700"><?php echo $error; ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($success && empty($generatedLinks)): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <span class="text-green-700"><?php echo $success; ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Generation Form -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                    <form method="POST" id="linkForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Customer Details -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Customer Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="customer_name" required 
                                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                                           placeholder="Enter customer name">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Phone Number <span class="text-red-500">*</span>
                                    </label>
                                    <input type="tel" name="customer_phone" required maxlength="10"
                                           pattern="[0-9]{10}"
                                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                                           placeholder="10-digit mobile number">
                                </div>
                            </div>
                            
                            <!-- Payment Details -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Amount (₹) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" name="amount" required min="1" max="50000" step="0.01"
                                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                                           placeholder="Enter amount">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Reference Number
                                    </label>
                                    <input type="text" name="reference"
                                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                                           placeholder="Optional reference">
                                </div>
                            </div>
                            
                            <!-- UPI and Agent Selection -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        UPI Address <span class="text-red-500">*</span>
                                    </label>
                                    <select name="upi_address" required 
                                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                                        <option value="">Select UPI Account</option>
                                        <?php foreach ($upiAccounts as $upi): ?>
                                        <option value="<?php echo htmlspecialchars($upi['upi_address']); ?>">
                                            <?php echo htmlspecialchars($upi['upi_address']); ?> 
                                            (<?php echo ucfirst($upi['account_type']); ?> - <?php echo $upi['bank_name'] ?? 'Bank'; ?>)
                                        </option>
                                        <?php endforeach; ?>
                                        <?php if (empty($upiAccounts)): ?>
                                        <option value="" disabled>No UPI accounts found. Please add one first.</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <?php if ($user_role == 'superadmin' || $user_role == 'admin'): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Assign to Agent
                                    </label>
                                    <select name="agent_id"
                                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                                        <option value="<?php echo $user_id; ?>">Myself</option>
                                        <?php foreach ($agents as $agent): ?>
                                        <option value="<?php echo $agent['agent_id']; ?>">
                                            <?php echo htmlspecialchars($agent['full_name']); ?> (@<?php echo $agent['username']; ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php else: ?>
                                <input type="hidden" name="agent_id" value="<?php echo $user_id; ?>">
                                <?php endif; ?>
                            </div>
                            
                            <!-- Expiry and Notes -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Link Expiry
                                    </label>
                                    <select name="expiry_hours"
                                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                                        <option value="1">1 hour</option>
                                        <option value="3">3 hours</option>
                                        <option value="6" selected>6 hours</option>
                                        <option value="12">12 hours</option>
                                        <option value="24">24 hours</option>
                                        <option value="48">48 hours</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Notes (Optional)
                                    </label>
                                    <textarea name="notes" rows="2"
                                             class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                                             placeholder="Any additional notes..."></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Terms and Submit -->
                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <div class="flex items-start mb-6">
                                <input type="checkbox" id="terms" required 
                                       class="mt-1 mr-3 h-5 w-5 text-blue-600 rounded">
                                <label for="terms" class="text-sm text-gray-700">
                                    I confirm that I have verified the customer details and amount. 
                                    The generated link will be valid for the selected duration.
                                </label>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" 
                                        class="bg-gradient-to-r from-blue-500 to-purple-500 text-white px-8 py-3 rounded-lg font-semibold hover:opacity-90 transition shadow-lg">
                                    <i class="fas fa-link mr-2"></i> Generate Payment Link
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Generated Links -->
                <?php if (!empty($generatedLinks)): ?>
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold">Generated Link</h2>
                        <span class="text-sm text-gray-600">
                            Expires: <?php echo date('d M Y, h:i A', strtotime($generatedLinks[0]['expiry_time'])); ?>
                        </span>
                    </div>
                    
                    <?php foreach ($generatedLinks as $link): ?>
                    <div class="space-y-4">
                        <!-- Link Details -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label class="text-sm text-gray-600">Link ID</label>
                                    <div class="font-mono font-medium"><?php echo $link['link_id']; ?></div>
                                </div>
                                <div>
                                    <label class="text-sm text-gray-600">Customer</label>
                                    <div class="font-medium"><?php echo htmlspecialchars($link['customer_name']); ?></div>
                                    <div class="text-sm text-gray-600"><?php echo $link['customer_phone']; ?></div>
                                </div>
                                <div>
                                    <label class="text-sm text-gray-600">Amount</label>
                                    <div class="text-2xl font-bold text-green-600">₹<?php echo number_format($link['amount'], 2); ?></div>
                                </div>
                            </div>
                            
                            <!-- Payment URL -->
                            <div class="mb-4">
                                <label class="text-sm text-gray-600 mb-2 block">Payment Link</label>
                                <div class="flex">
                                    <input type="text" id="paymentUrl" readonly 
                                           value="<?php echo htmlspecialchars($link['payment_url']); ?>"
                                           class="flex-1 border border-gray-300 rounded-l-lg px-4 py-2 bg-gray-100 text-gray-700">
                                    <button onclick="copyToClipboard('paymentUrl')" 
                                            class="bg-blue-500 text-white px-4 rounded-r-lg hover:bg-blue-600">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex flex-wrap gap-3">
                                <a href="<?php echo $link['payment_url']; ?>" target="_blank"
                                   class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600">
                                    <i class="fas fa-external-link-alt mr-2"></i> Open Link
                                </a>
                                
                                <button onclick="openWhatsApp('<?php echo $link['customer_phone']; ?>', '<?php echo urlencode($link['whatsapp_message']); ?>')"
                                        class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                                    <i class="fab fa-whatsapp mr-2"></i> Send via WhatsApp
                                </button>
                                
                                <button onclick="copyWhatsAppMessage('<?php echo urlencode($link['whatsapp_message']); ?>')"
                                        class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">
                                    <i class="fas fa-copy mr-2"></i> Copy Message
                                </button>
                                
                                <a href="links.php" 
                                   class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                                    <i class="fas fa-list mr-2"></i> View All Links
                                </a>
                            </div>
                        </div>
                        
                        <!-- WhatsApp Message Preview -->
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <label class="text-sm font-medium text-green-800 mb-2 block">
                                <i class="fab fa-whatsapp mr-1"></i> WhatsApp Message Preview
                            </label>
                            <div class="bg-white rounded p-3 text-sm whitespace-pre-line">
                                <?php echo htmlspecialchars($link['whatsapp_message']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            element.select();
            element.setSelectionRange(0, 99999); // For mobile devices
            navigator.clipboard.writeText(element.value).then(() => {
                alert('Copied to clipboard!');
            });
        }
        
        function copyWhatsAppMessage(message) {
            const decodedMessage = decodeURIComponent(message);
            navigator.clipboard.writeText(decodedMessage).then(() => {
                alert('WhatsApp message copied to clipboard!');
            });
        }
        
        function openWhatsApp(phone, message) {
            const url = `https://wa.me/91${phone}?text=${message}`;
            window.open(url, '_blank');
        }
        
        // Auto-format phone number
        document.querySelector('input[name="customer_phone"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) value = value.substring(0, 10);
            e.target.value = value;
        });
        
        // Auto-format amount
        document.querySelector('input[name="amount"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9.]/g, '');
            const parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts[1];
            }
            if (parts[1] && parts[1].length > 2) {
                value = parts[0] + '.' + parts[1].substring(0, 2);
            }
            e.target.value = value;
        });
        
        // Form validation
        document.getElementById('linkForm').addEventListener('submit', function(e) {
            const phone = document.querySelector('input[name="customer_phone"]').value;
            const amount = document.querySelector('input[name="amount"]').value;
            const upi = document.querySelector('select[name="upi_address"]').value;
            
            if (!/^\d{10}$/.test(phone)) {
                alert('Please enter a valid 10-digit phone number');
                e.preventDefault();
                return false;
            }
            
            if (!amount || isNaN(amount) || parseFloat(amount) < 1) {
                alert('Please enter a valid amount (minimum ₹1)');
                e.preventDefault();
                return false;
            }
            
            if (!upi) {
                alert('Please select a UPI account');
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>