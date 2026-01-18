<?php
// upi.php - Manage UPI accounts
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
    
    // Only superadmin and admin can access
    if ($_SESSION['user_role'] != 'superadmin' && $_SESSION['user_role'] != 'admin') {
        header('Location: index.php');
        exit();
    }
}

$db = connectDB();
checkLogin();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get all UPI accounts
$upiAccounts = $db->query("
    SELECT u.*, a.full_name as assigned_user_name,
           (SELECT COUNT(*) FROM payments WHERE upi_id = u.upi_address) as total_transactions,
           (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE upi_id = u.upi_address AND payment_status = 'success') as total_amount,
           (SELECT COUNT(*) FROM payments WHERE upi_id = u.upi_address AND DATE(created_at) = CURDATE()) as today_transactions,
           (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE upi_id = u.upi_address AND payment_status = 'success' AND DATE(created_at) = CURDATE()) as today_amount
    FROM upi_accounts u
    LEFT JOIN agents a ON u.assigned_to_user = a.agent_id
    ORDER BY 
        CASE u.account_type 
            WHEN 'primary' THEN 1 
            WHEN 'secondary' THEN 2 
            WHEN 'backup' THEN 3 
            ELSE 4 
        END,
        u.upi_address
")->fetchAll();

// Get agents for assignment
$agents = $db->query("SELECT agent_id, full_name FROM agents WHERE status = 'active' ORDER BY full_name")->fetchAll();

// Handle actions
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_upi') {
        $upi_address = trim($_POST['upi_address'] ?? '');
        $account_holder = trim($_POST['account_holder'] ?? '');
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_type = $_POST['account_type'] ?? 'primary';
        $assigned_to_user = $_POST['assigned_to_user'] ?? null;
        $daily_limit = floatval($_POST['daily_limit'] ?? 100000.00);
        $notes = trim($_POST['notes'] ?? '');
        
        // Validate
        if (empty($upi_address) || empty($account_holder)) {
            $error = 'Please fill all required fields';
        } elseif (!filter_var($upi_address, FILTER_VALIDATE_EMAIL) && !preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $upi_address)) {
            $error = 'Please enter a valid UPI address (e.g., username@upi)';
        } elseif ($daily_limit < 1000 || $daily_limit > 10000000) {
            $error = 'Daily limit must be between ₹1,000 and ₹10,000,000';
        } else {
            // Check if UPI exists
            $check = $db->prepare("SELECT COUNT(*) as count FROM upi_accounts WHERE upi_address = ?");
            $check->execute([$upi_address]);
            if ($check->fetch()['count'] > 0) {
                $error = 'UPI address already exists';
            } else {
                // Insert UPI account
                $stmt = $db->prepare("
                    INSERT INTO upi_accounts (upi_address, account_holder, bank_name, account_type, 
                                             assigned_to_role, assigned_to_user, daily_limit, notes, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $assigned_to_role = $assigned_to_user ? 'admin' : 'superadmin';
                $stmt->execute([$upi_address, $account_holder, $bank_name, $account_type, 
                               $assigned_to_role, $assigned_to_user, $daily_limit, $notes]);
                
                // Log activity
                $logStmt = $db->prepare("INSERT INTO activity_logs (agent_id, activity_type, description) VALUES (?, 'upi_added', ?)");
                $logStmt->execute([$user_id, "Added new UPI: $upi_address"]);
                
                $success = 'UPI account added successfully';
                header("Location: upi.php");
                exit();
            }
        }
    }
    
    if ($action === 'update_upi') {
        $upi_id = $_POST['upi_id'] ?? '';
        $account_holder = trim($_POST['account_holder'] ?? '');
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_type = $_POST['account_type'] ?? 'primary';
        $assigned_to_user = $_POST['assigned_to_user'] ?? null;
        $daily_limit = floatval($_POST['daily_limit'] ?? 100000.00);
        $status = $_POST['status'] ?? 'active';
        $notes = trim($_POST['notes'] ?? '');
        
        if ($upi_id) {
            $stmt = $db->prepare("
                UPDATE upi_accounts 
                SET account_holder = ?, bank_name = ?, account_type = ?, 
                    assigned_to_user = ?, daily_limit = ?, status = ?, notes = ?, updated_at = NOW()
                WHERE upi_id = ?
            ");
            $stmt->execute([$account_holder, $bank_name, $account_type, 
                           $assigned_to_user, $daily_limit, $status, $notes, $upi_id]);
            
            // Log activity
            $logStmt = $db->prepare("INSERT INTO activity_logs (agent_id, activity_type, description) VALUES (?, 'upi_updated', ?)");
            $logStmt->execute([$user_id, "Updated UPI account ID: $upi_id"]);
            
            $success = 'UPI account updated successfully';
            header("Location: upi.php");
            exit();
        }
    }
    
    if ($action === 'delete_upi') {
        $upi_id = $_POST['upi_id'] ?? '';
        
        if ($upi_id) {
            // Check if UPI has transactions
            $check = $db->prepare("SELECT COUNT(*) as count FROM payments WHERE upi_id IN (SELECT upi_address FROM upi_accounts WHERE upi_id = ?)");
            $check->execute([$upi_id]);
            if ($check->fetch()['count'] > 0) {
                $error = 'Cannot delete UPI account with existing transactions';
            } else {
                $stmt = $db->prepare("DELETE FROM upi_accounts WHERE upi_id = ?");
                $stmt->execute([$upi_id]);
                
                $success = 'UPI account deleted successfully';
                header("Location: upi.php");
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UPI Accounts - NimCredit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-primary { background: #dbeafe; color: #1e40af; }
        .badge-secondary { background: #e0e7ff; color: #3730a3; }
        .badge-backup { background: #f3f4f6; color: #4b5563; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #f3f4f6; color: #4b5563; }
        .badge-suspended { background: #fee2e2; color: #991b1b; }
        .progress-bar { height: 6px; border-radius: 3px; background: #e5e7eb; overflow: hidden; }
        .progress-fill { height: 100%; }
        .progress-low { background: #10b981; }
        .progress-medium { background: #f59e0b; }
        .progress-high { background: #ef4444; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <?php include 'header.php'; ?>
    
    <div class="flex">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="flex-1 p-6">
            <!-- Page Header -->
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">UPI Accounts</h1>
                        <p class="text-gray-600">Manage UPI accounts for payments</p>
                    </div>
                    <button onclick="openAddUPIModal()" class="bg-gradient-to-r from-blue-500 to-purple-500 text-white px-6 py-3 rounded-lg font-semibold hover:opacity-90">
                        <i class="fas fa-plus mr-2"></i> Add UPI Account
                    </button>
                </div>
            </div>
            
            <!-- Error Message -->
            <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <span class="text-red-700"><?php echo $error; ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Success Message -->
            <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <span class="text-green-700"><?php echo $success; ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <?php
                $totalUPI = count($upiAccounts);
                $activeUPI = count(array_filter($upiAccounts, fn($u) => $u['status'] == 'active'));
                $todayAmount = array_sum(array_column($upiAccounts, 'today_amount'));
                $totalAmount = array_sum(array_column($upiAccounts, 'total_amount'));
                ?>
                
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Total UPI Accounts</p>
                            <h3 class="text-2xl font-bold mt-1"><?php echo number_format($totalUPI); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                            <i class="fas fa-wallet text-xl"></i>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2"><?php echo number_format($activeUPI); ?> active</p>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Today's Collection</p>
                            <h3 class="text-2xl font-bold mt-1">₹<?php echo number_format($todayAmount, 2); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-green-50 text-green-600 flex items-center justify-center">
                            <i class="fas fa-rupee-sign text-xl"></i>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">Across all UPI accounts</p>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Total Collection</p>
                            <h3 class="text-2xl font-bold mt-1">₹<?php echo number_format($totalAmount, 2); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center">
                            <i class="fas fa-chart-line text-xl"></i>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">All time collection</p>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Total Transactions</p>
                            <h3 class="text-2xl font-bold mt-1"><?php echo number_format(array_sum(array_column($upiAccounts, 'total_transactions'))); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center">
                            <i class="fas fa-exchange-alt text-xl"></i>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">Successful payments</p>
                </div>
            </div>
            
            <!-- UPI Accounts Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold">All UPI Accounts (<?php echo count($upiAccounts); ?>)</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UPI Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type & Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Limit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Used</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($upiAccounts as $upi): ?>
                            <?php
                            // Calculate daily usage percentage
                            $dailyUsage = $upi['today_amount'] / $upi['daily_limit'] * 100;
                            $progressClass = $dailyUsage < 50 ? 'progress-low' : ($dailyUsage < 80 ? 'progress-medium' : 'progress-high');
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-mono font-medium text-blue-600"><?php echo htmlspecialchars($upi['upi_address']); ?></div>
                                    <div class="text-xs text-gray-500">ID: <?php echo $upi['upi_id']; ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                        <div class="font-medium"><?php echo htmlspecialchars($upi['account_holder']); ?></div>
                                        <div class="text-gray-600"><?php echo $upi['bank_name'] ?: 'Bank not specified'; ?></div>
                                        <?php if ($upi['assigned_user_name']): ?>
                                        <div class="text-xs text-gray-500 mt-1">
                                            Assigned to: <?php echo $upi['assigned_user_name']; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="space-y-1">
                                        <span class="badge <?php echo $upi['account_type'] == 'primary' ? 'badge-primary' : ($upi['account_type'] == 'secondary' ? 'badge-secondary' : 'badge-backup'); ?>">
                                            <?php echo ucfirst($upi['account_type']); ?>
                                        </span>
                                        <span class="badge <?php echo $upi['status'] == 'active' ? 'badge-active' : ($upi['status'] == 'suspended' ? 'badge-suspended' : 'badge-inactive'); ?>">
                                            <?php echo ucfirst($upi['status']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                        <div class="font-bold">₹<?php echo number_format($upi['daily_limit'], 2); ?></div>
                                        <div class="mt-2">
                                            <div class="flex justify-between text-xs mb-1">
                                                <span>Today's usage:</span>
                                                <span>₹<?php echo number_format($upi['today_amount'], 2); ?></span>
                                            </div>
                                            <div class="progress-bar">
                                                <div class="progress-fill <?php echo $progressClass; ?>" style="width: <?php echo min($dailyUsage, 100); ?>%"></div>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1 text-right">
                                                <?php echo number_format($dailyUsage, 1); ?>%
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                        <div class="font-bold"><?php echo number_format($upi['total_transactions']); ?></div>
                                        <div class="text-gray-600">₹<?php echo number_format($upi['total_amount'], 2); ?></div>
                                        <div class="text-xs text-gray-500">
                                            Today: <?php echo $upi['today_transactions']; ?> txns
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($upi['last_used']): ?>
                                    <div class="text-sm"><?php echo date('d M', strtotime($upi['last_used'])); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($upi['last_used'])); ?></div>
                                    <?php else: ?>
                                    <span class="text-sm text-gray-400">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <button onclick="editUPI(<?php echo htmlspecialchars(json_encode($upi)); ?>)"
                                                class="text-blue-600 hover:text-blue-800" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user_role == 'superadmin'): ?>
                                        <?php if ($upi['status'] == 'active'): ?>
                                        <button onclick="toggleUPIStatus(<?php echo $upi['upi_id']; ?>, 'suspend', '<?php echo $upi['upi_address']; ?>')"
                                                class="text-orange-600 hover:text-orange-800" title="Suspend">
                                            <i class="fas fa-pause"></i>
                                        </button>
                                        <?php else: ?>
                                        <button onclick="toggleUPIStatus(<?php echo $upi['upi_id']; ?>, 'activate', '<?php echo $upi['upi_address']; ?>')"
                                                class="text-green-600 hover:text-green-800" title="Activate">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($upi['total_transactions'] == 0): ?>
                                        <button onclick="deleteUPI(<?php echo $upi['upi_id']; ?>, '<?php echo $upi['upi_address']; ?>')"
                                                class="text-red-600 hover:text-red-800" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- UPI Usage Chart -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-6">UPI Usage Overview</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php foreach ($upiAccounts as $upi): ?>
                    <?php if ($upi['status'] == 'active'): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300">
                        <div class="flex justify-between items-start mb-2">
                            <div class="font-mono text-sm truncate"><?php echo $upi['upi_address']; ?></div>
                            <span class="text-xs <?php echo $upi['account_type'] == 'primary' ? 'text-blue-600' : 'text-gray-600'; ?>">
                                <?php echo $upi['account_type']; ?>
                            </span>
                        </div>
                        <div class="text-2xl font-bold text-green-600 mb-2">
                            ₹<?php echo number_format($upi['today_amount'], 2); ?>
                        </div>
                        <div class="text-xs text-gray-600 mb-2">
                            Today: <?php echo $upi['today_transactions']; ?> transactions
                        </div>
                        <div class="text-xs">
                            <div class="flex justify-between mb-1">
                                <span>Daily limit: ₹<?php echo number_format($upi['daily_limit'], 2); ?></span>
                                <span><?php echo number_format(min(($upi['today_amount'] / $upi['daily_limit']) * 100, 100), 1); ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill <?php echo ($upi['today_amount'] / $upi['daily_limit'] * 100) < 50 ? 'progress-low' : 'progress-medium'; ?>" 
                                     style="width: <?php echo min(($upi['today_amount'] / $upi['daily_limit'] * 100), 100); ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add UPI Modal -->
    <div id="addUPIModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-2xl w-full mx-4 my-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold">Add New UPI Account</h3>
                <button onclick="closeModal('addUPIModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="addUPIForm" method="POST">
                <input type="hidden" name="action" value="add_upi">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            UPI Address <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="upi_address" required 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                               placeholder="username@upi">
                        <p class="text-xs text-gray-500 mt-1">Format: username@bankname</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Account Holder <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="account_holder" required 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                               placeholder="Account holder name">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bank Name</label>
                        <input type="text" name="bank_name"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                               placeholder="e.g., ICICI Bank, HDFC Bank">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Account Type</label>
                        <select name="account_type" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                            <option value="primary">Primary</option>
                            <option value="secondary">Secondary</option>
                            <option value="backup">Backup</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Daily Limit (₹)</label>
                        <input type="number" name="daily_limit" min="1000" max="10000000" step="1000" value="100000"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                        <p class="text-xs text-gray-500 mt-1">Maximum per day collection</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assign to Admin (Optional)</label>
                        <select name="assigned_to_user" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                            <option value="">None (Available to all)</option>
                            <?php foreach ($agents as $agent): ?>
                            <option value="<?php echo $agent['agent_id']; ?>"><?php echo htmlspecialchars($agent['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                        <textarea name="notes" rows="3"
                                  class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                                  placeholder="Any additional notes about this UPI account..."></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('addUPIModal')" 
                            class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-500 text-white rounded-lg hover:opacity-90 font-semibold">
                        Add UPI Account
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit UPI Modal -->
    <div id="editUPIModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-2xl w-full mx-4 my-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold">Edit UPI Account</h3>
                <button onclick="closeModal('editUPIModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="editUPIForm" method="POST">
                <input type="hidden" name="action" value="update_upi">
                <input type="hidden" name="upi_id" id="editUPIId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">UPI Address</label>
                        <input type="text" id="editUPIAddress" readonly
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-100 text-gray-700">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Account Holder <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="account_holder" id="editAccountHolder" required 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bank Name</label>
                        <input type="text" name="bank_name" id="editBankName"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Account Type</label>
                        <select name="account_type" id="editAccountType" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                            <option value="primary">Primary</option>
                            <option value="secondary">Secondary</option>
                            <option value="backup">Backup</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Daily Limit (₹)</label>
                        <input type="number" name="daily_limit" id="editDailyLimit" min="1000" max="10000000" step="1000"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" id="editStatus" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assign to Admin</label>
                        <select name="assigned_to_user" id="editAssignedTo" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                            <option value="">None (Available to all)</option>
                            <?php foreach ($agents as $agent): ?>
                            <option value="<?php echo $agent['agent_id']; ?>"><?php echo htmlspecialchars($agent['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <textarea name="notes" id="editNotes" rows="3"
                                  class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('editUPIModal')" 
                            class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-500 text-white rounded-lg hover:opacity-90 font-semibold">
                        Update UPI Account
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div id="confirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold mb-4" id="confirmTitle"></h3>
            <p class="text-gray-600 mb-6" id="confirmMessage"></p>
            <form id="confirmForm" method="POST">
                <input type="hidden" name="upi_id" id="confirmUPIId">
                <input type="hidden" name="action" id="confirmAction">
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('confirmModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" id="confirmBtn" 
                            class="px-4 py-2 text-white rounded-lg">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAddUPIModal() {
            document.getElementById('addUPIModal').classList.remove('hidden');
            document.getElementById('addUPIModal').classList.add('flex');
        }
        
        function editUPI(upi) {
            document.getElementById('editUPIId').value = upi.upi_id;
            document.getElementById('editUPIAddress').value = upi.upi_address;
            document.getElementById('editAccountHolder').value = upi.account_holder;
            document.getElementById('editBankName').value = upi.bank_name || '';
            document.getElementById('editAccountType').value = upi.account_type;
            document.getElementById('editDailyLimit').value = upi.daily_limit;
            document.getElementById('editStatus').value = upi.status;
            document.getElementById('editAssignedTo').value = upi.assigned_to_user || '';
            document.getElementById('editNotes').value = upi.notes || '';
            
            document.getElementById('editUPIModal').classList.remove('hidden');
            document.getElementById('editUPIModal').classList.add('flex');
        }
        
        function toggleUPIStatus(upiId, action, upiAddress) {
            document.getElementById('confirmUPIId').value = upiId;
            document.getElementById('confirmAction').value = 'update_upi';
            
            if (action === 'suspend') {
                document.getElementById('confirmTitle').textContent = 'Suspend UPI Account';
                document.getElementById('confirmMessage').textContent = `Are you sure you want to suspend ${upiAddress}?`;
                document.getElementById('confirmBtn').className = 'px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600';
                document.getElementById('confirmForm').innerHTML += '<input type="hidden" name="status" value="suspended">';
            } else {
                document.getElementById('confirmTitle').textContent = 'Activate UPI Account';
                document.getElementById('confirmMessage').textContent = `Are you sure you want to activate ${upiAddress}?`;
                document.getElementById('confirmBtn').className = 'px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600';
                document.getElementById('confirmForm').innerHTML += '<input type="hidden" name="status" value="active">';
            }
            
            document.getElementById('confirmModal').classList.remove('hidden');
            document.getElementById('confirmModal').classList.add('flex');
        }
        
        function deleteUPI(upiId, upiAddress) {
            document.getElementById('confirmUPIId').value = upiId;
            document.getElementById('confirmAction').value = 'delete_upi';
            document.getElementById('confirmTitle').textContent = 'Delete UPI Account';
            document.getElementById('confirmMessage').textContent = `Are you sure you want to delete ${upiAddress}? This action cannot be undone.`;
            document.getElementById('confirmBtn').className = 'px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600';
            
            document.getElementById('confirmModal').classList.remove('hidden');
            document.getElementById('confirmModal').classList.add('flex');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.getElementById(modalId).classList.remove('flex');
            
            // Reset forms
            if (modalId === 'confirmModal') {
                document.getElementById('confirmForm').reset();
            }
        }
        
        // Form validation for UPI address
        document.getElementById('addUPIForm').addEventListener('submit', function(e) {
            const upiAddress = document.querySelector('#addUPIForm input[name="upi_address"]').value;
            const upiRegex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            
            if (!upiRegex.test(upiAddress)) {
                alert('Please enter a valid UPI address (format: username@upi)');
                e.preventDefault();
                return false;
            }
            
            const dailyLimit = document.querySelector('#addUPIForm input[name="daily_limit"]').value;
            if (dailyLimit < 1000 || dailyLimit > 10000000) {
                alert('Daily limit must be between ₹1,000 and ₹10,000,000');
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>