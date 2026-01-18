<?php
// settings.php - Settings Management Page
session_start();
ob_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'hubb895940_repaynim');
define('DB_USER', 'hubb895940_repaynim');
define('DB_PASS', 'hubb895940_repaynim');

// Connect to Database
function connectDB() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Check if user is logged in and has admin/superadmin privileges
function checkAdminAccess() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        header('Location: login.php');
        exit();
    }
    
    $allowedRoles = ['superadmin', 'admin'];
    if (!in_array($_SESSION['user_role'], $allowedRoles)) {
        header('Location: dashboard.php');
        exit();
    }
}

// Get user info
function getUserInfo($pdo, $user_id) {
    $sql = "SELECT * FROM agents WHERE agent_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    return $stmt->fetch();
}

// Get all system settings
function getSystemSettings($pdo) {
    $sql = "SELECT * FROM system_settings ORDER BY category, setting_key";
    $stmt = $pdo->query($sql);
    $settings = $stmt->fetchAll();
    
    // Group by category
    $grouped = [];
    foreach ($settings as $setting) {
        $category = $setting['category'] ?? 'general';
        if (!isset($grouped[$category])) {
            $grouped[$category] = [];
        }
        $grouped[$category][] = $setting;
    }
    
    return $grouped;
}

// Get UPI accounts
function getUpiAccounts($pdo) {
    $sql = "SELECT * FROM upi_accounts ORDER BY account_type, created_at";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

// Get agents list for assignment
function getAgentsList($pdo) {
    $sql = "SELECT agent_id, username, full_name, role FROM agents WHERE status = 'active' ORDER BY role, full_name";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

// Get all agents with statistics
function getAllAgentsStats($pdo) {
    $sql = "SELECT a.*, 
                   COUNT(DISTINCT lg.link_id) as total_links,
                   COUNT(DISTINCT p.payment_id) as total_payments,
                   COALESCE(SUM(CASE WHEN p.payment_status = 'success' THEN p.amount ELSE 0 END), 0) as total_amount
            FROM agents a
            LEFT JOIN link_generations lg ON a.agent_id = lg.agent_id
            LEFT JOIN payments p ON a.agent_id = p.agent_id
            GROUP BY a.agent_id
            ORDER BY a.role, a.full_name";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

// Update setting
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = connectDB();
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'update_setting':
                $key = $_POST['key'] ?? '';
                $value = $_POST['value'] ?? '';
                
                if ($key) {
                    $sql = "UPDATE system_settings SET setting_value = :value WHERE setting_key = :key";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(['key' => $key, 'value' => $value]);
                    
                    // Log activity
                    $logSql = "INSERT INTO activity_logs (agent_id, activity_type, description) VALUES (:agent_id, 'setting_updated', :desc)";
                    $logStmt = $db->prepare($logSql);
                    $logStmt->execute([
                        'agent_id' => $_SESSION['user_id'],
                        'desc' => "Updated setting: $key to $value"
                    ]);
                    
                    echo json_encode(['success' => true, 'message' => 'Setting updated successfully']);
                    exit;
                }
                break;
                
            case 'add_upi':
                $upi_address = $_POST['upi_address'] ?? '';
                $account_holder = $_POST['account_holder'] ?? '';
                $bank_name = $_POST['bank_name'] ?? '';
                $account_type = $_POST['account_type'] ?? 'primary';
                $assigned_to = $_POST['assigned_to'] ?? '';
                $daily_limit = $_POST['daily_limit'] ?? 100000;
                $notes = $_POST['notes'] ?? '';
                
                if ($upi_address && $account_holder) {
                    $sql = "INSERT INTO upi_accounts (upi_address, account_holder, bank_name, account_type, daily_limit, notes, status)
                            VALUES (:upi, :holder, :bank, :type, :limit, :notes, 'active')";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        'upi' => $upi_address,
                        'holder' => $account_holder,
                        'bank' => $bank_name,
                        'type' => $account_type,
                        'limit' => $daily_limit,
                        'notes' => $notes
                    ]);
                    
                    // Log activity
                    $logSql = "INSERT INTO activity_logs (agent_id, activity_type, description) VALUES (:agent_id, 'upi_added', :desc)";
                    $logStmt = $db->prepare($logSql);
                    $logStmt->execute([
                        'agent_id' => $_SESSION['user_id'],
                        'desc' => "Added UPI account: $upi_address"
                    ]);
                    
                    echo json_encode(['success' => true, 'message' => 'UPI account added successfully']);
                    exit;
                }
                break;
                
            case 'update_agent':
                $agent_id = $_POST['agent_id'] ?? '';
                $field = $_POST['field'] ?? '';
                $value = $_POST['value'] ?? '';
                
                if ($agent_id && $field) {
                    $allowedFields = ['commission_rate', 'assigned_to', 'status'];
                    if (in_array($field, $allowedFields)) {
                        $sql = "UPDATE agents SET $field = :value WHERE agent_id = :agent_id";
                        $stmt = $db->prepare($sql);
                        $stmt->execute(['value' => $value, 'agent_id' => $agent_id]);
                        
                        echo json_encode(['success' => true, 'message' => 'Agent updated successfully']);
                        exit;
                    }
                }
                break;
        }
    }
}

// Main Execution
$db = connectDB();
checkAdminAccess();
$user = getUserInfo($db, $_SESSION['user_id']);
$settings = getSystemSettings($db);
$upiAccounts = getUpiAccounts($db);
$agents = getAllAgentsStats($db);
$activeTab = $_GET['tab'] ?? 'general';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - NimCredit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tab-active { 
            border-bottom: 3px solid #3b82f6; 
            color: #3b82f6; 
            font-weight: 600; 
        }
        .setting-card { 
            transition: all 0.2s ease; 
        }
        .setting-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
        }
        .badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-primary { background: #dbeafe; color: #1e40af; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Loading Overlay -->
    <div id="loading" class="fixed inset-0 bg-white bg-opacity-80 flex items-center justify-center z-50 hidden">
        <div class="text-center">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            <p class="mt-4 text-gray-600">Saving Changes...</p>
        </div>
    </div>

    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">System Settings</h2>
                        <p class="text-gray-600">Manage application configuration</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="hidden md:block text-right">
                        <div class="text-sm text-gray-600">Logged in as</div>
                        <div class="font-semibold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center text-white">
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="p-6">
        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-sm mb-6">
            <div class="border-b">
                <nav class="flex space-x-8 px-6" aria-label="Tabs">
                    <a href="?tab=general" 
                       class="<?php echo $activeTab == 'general' ? 'tab-active' : 'text-gray-500 hover:text-gray-700'; ?> py-4 px-1">
                       <i class="fas fa-cog mr-2"></i> General
                    </a>
                    <a href="?tab=upi" 
                       class="<?php echo $activeTab == 'upi' ? 'tab-active' : 'text-gray-500 hover:text-gray-700'; ?> py-4 px-1">
                       <i class="fas fa-wallet mr-2"></i> UPI Accounts
                    </a>
                    <a href="?tab=agents" 
                       class="<?php echo $activeTab == 'agents' ? 'tab-active' : 'text-gray-500 hover:text-gray-700'; ?> py-4 px-1">
                       <i class="fas fa-users mr-2"></i> Agent Management
                    </a>
                    <a href="?tab=notifications" 
                       class="<?php echo $activeTab == 'notifications' ? 'tab-active' : 'text-gray-500 hover:text-gray-700'; ?> py-4 px-1">
                       <i class="fas fa-bell mr-2"></i> Notifications
                    </a>
                    <a href="?tab=security" 
                       class="<?php echo $activeTab == 'security' ? 'tab-active' : 'text-gray-500 hover:text-gray-700'; ?> py-4 px-1">
                       <i class="fas fa-shield-alt mr-2"></i> Security
                    </a>
                    <?php if ($user['role'] == 'superadmin'): ?>
                    <a href="?tab=advanced" 
                       class="<?php echo $activeTab == 'advanced' ? 'tab-active' : 'text-gray-500 hover:text-gray-700'; ?> py-4 px-1">
                       <i class="fas fa-tools mr-2"></i> Advanced
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <?php if ($activeTab == 'general'): ?>
            <!-- General Settings -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <i class="fas fa-sliders-h mr-2 text-blue-500"></i>
                    General Application Settings
                </h3>
                <p class="text-gray-600 mb-6">Configure basic application parameters and limits</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Payment Settings -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-gray-800 border-b pb-2">Payment Settings</h4>
                        <?php 
                        $paymentSettings = $settings['payment'] ?? [];
                        foreach ($paymentSettings as $setting): 
                        ?>
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <div class="flex justify-between items-start mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    <?php 
                                    $labels = [
                                        'payment_timeout_minutes' => 'Payment Timeout (minutes)',
                                        'max_amount_per_link' => 'Max Amount per Link',
                                        'min_amount_per_link' => 'Min Amount per Link',
                                        'daily_payment_limit' => 'Daily Payment Limit'
                                    ];
                                    echo $labels[$setting['setting_key']] ?? ucfirst(str_replace('_', ' ', $setting['setting_key']));
                                    ?>
                                </label>
                                <span class="text-xs text-gray-500"><?php echo $setting['setting_type']; ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <?php if ($setting['setting_type'] == 'boolean'): ?>
                                <div class="relative inline-block w-12 align-middle select-none">
                                    <input type="checkbox" 
                                           data-key="<?php echo $setting['setting_key']; ?>"
                                           class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"
                                           <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                                    <label class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                </div>
                                <?php else: ?>
                                <input type="<?php echo $setting['setting_type'] == 'number' ? 'number' : 'text'; ?>"
                                       data-key="<?php echo $setting['setting_key']; ?>"
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                       class="flex-1 px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none"
                                       placeholder="Enter value">
                                <?php endif; ?>
                                <button onclick="saveSetting('<?php echo $setting['setting_key']; ?>', this)"
                                        class="px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                                    Save
                                </button>
                            </div>
                            <?php if (!empty($setting['description'])): ?>
                            <p class="text-xs text-gray-500 mt-2"><?php echo htmlspecialchars($setting['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Link Settings -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-gray-800 border-b pb-2">Link Settings</h4>
                        <?php 
                        $generalSettings = $settings['general'] ?? [];
                        foreach ($generalSettings as $setting): 
                            if (strpos($setting['setting_key'], 'link') !== false || 
                                strpos($setting['setting_key'], 'click') !== false):
                        ?>
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <div class="flex justify-between items-start mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    <?php 
                                    $labels = [
                                        'link_expiry_hours' => 'Link Expiry (hours)',
                                        'max_clicks_per_link' => 'Max Clicks per Link'
                                    ];
                                    echo $labels[$setting['setting_key']] ?? ucfirst(str_replace('_', ' ', $setting['setting_key']));
                                    ?>
                                </label>
                                <span class="text-xs text-gray-500"><?php echo $setting['setting_type']; ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <?php if ($setting['setting_type'] == 'boolean'): ?>
                                <div class="relative inline-block w-12 align-middle select-none">
                                    <input type="checkbox" 
                                           data-key="<?php echo $setting['setting_key']; ?>"
                                           class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"
                                           <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                                    <label class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                </div>
                                <?php else: ?>
                                <input type="<?php echo $setting['setting_type'] == 'number' ? 'number' : 'text'; ?>"
                                       data-key="<?php echo $setting['setting_key']; ?>"
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                       class="flex-1 px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none"
                                       placeholder="Enter value">
                                <?php endif; ?>
                                <button onclick="saveSetting('<?php echo $setting['setting_key']; ?>', this)"
                                        class="px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                                    Save
                                </button>
                            </div>
                            <?php if (!empty($setting['description'])): ?>
                            <p class="text-xs text-gray-500 mt-2"><?php echo htmlspecialchars($setting['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                        
                        <!-- Commission Settings -->
                        <h4 class="font-medium text-gray-800 border-b pb-2 mt-6">Commission Settings</h4>
                        <?php 
                        $commissionSettings = $settings['commission'] ?? [];
                        foreach ($commissionSettings as $setting): 
                        ?>
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <div class="flex justify-between items-start mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    <?php 
                                    $labels = [
                                        'commission_rate_default' => 'Default Commission Rate (%)'
                                    ];
                                    echo $labels[$setting['setting_key']] ?? ucfirst(str_replace('_', ' ', $setting['setting_key']));
                                    ?>
                                </label>
                                <span class="text-xs text-gray-500"><?php echo $setting['setting_type']; ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <input type="<?php echo $setting['setting_type'] == 'number' ? 'number' : 'text'; ?>"
                                       step="0.01"
                                       data-key="<?php echo $setting['setting_key']; ?>"
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                       class="flex-1 px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none"
                                       placeholder="Enter value">
                                <button onclick="saveSetting('<?php echo $setting['setting_key']; ?>', this)"
                                        class="px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                                    Save
                                </button>
                            </div>
                            <?php if (!empty($setting['description'])): ?>
                            <p class="text-xs text-gray-500 mt-2"><?php echo htmlspecialchars($setting['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Timezone and Other Settings -->
            <div class="border-t pt-8">
                <h4 class="font-medium text-gray-800 mb-4">Other Settings</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php 
                    $otherSettings = [];
                    foreach ($settings as $category => $items) {
                        foreach ($items as $item) {
                            if (!in_array($category, ['payment', 'general', 'commission', 'sms', 'notification'])) {
                                $otherSettings[] = $item;
                            }
                        }
                    }
                    foreach ($otherSettings as $setting): 
                    ?>
                    <div class="setting-card bg-gray-50 p-4 rounded-lg">
                        <div class="flex justify-between items-start mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                <?php echo ucfirst(str_replace('_', ' ', $setting['setting_key'])); ?>
                            </label>
                            <span class="text-xs text-gray-500"><?php echo $setting['setting_type']; ?></span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <?php if ($setting['setting_type'] == 'boolean'): ?>
                            <div class="relative inline-block w-12 align-middle select-none">
                                <input type="checkbox" 
                                       data-key="<?php echo $setting['setting_key']; ?>"
                                       class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"
                                       <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                                <label class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                            </div>
                            <?php else: ?>
                            <input type="<?php echo $setting['setting_type'] == 'number' ? 'number' : 'text'; ?>"
                                   data-key="<?php echo $setting['setting_key']; ?>"
                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                   class="flex-1 px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none"
                                   placeholder="Enter value">
                            <?php endif; ?>
                            <button onclick="saveSetting('<?php echo $setting['setting_key']; ?>', this)"
                                    class="px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                                Save
                            </button>
                        </div>
                        <?php if (!empty($setting['description'])): ?>
                        <p class="text-xs text-gray-500 mt-2"><?php echo htmlspecialchars($setting['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php elseif ($activeTab == 'upi'): ?>
            <!-- UPI Accounts Management -->
            <div class="mb-8">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-2 flex items-center">
                            <i class="fas fa-wallet mr-2 text-green-500"></i>
                            UPI Accounts Management
                        </h3>
                        <p class="text-gray-600">Manage payment UPI IDs and their limits</p>
                    </div>
                    <button onclick="openAddUpiModal()" 
                            class="px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg hover:opacity-90">
                        <i class="fas fa-plus mr-2"></i> Add UPI Account
                    </button>
                </div>

                <!-- UPI Accounts Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-gray-500 text-sm border-b">
                                <th class="pb-3">UPI Address</th>
                                <th class="pb-3">Account Holder</th>
                                <th class="pb-3">Bank</th>
                                <th class="pb-3">Type</th>
                                <th class="pb-3">Daily Limit</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3">Total Amount</th>
                                <th class="pb-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upiAccounts as $account): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3">
                                    <div class="font-medium"><?php echo htmlspecialchars($account['upi_address']); ?></div>
                                    <div class="text-xs text-gray-500">ID: <?php echo $account['upi_id']; ?></div>
                                </td>
                                <td class="py-3">
                                    <?php echo htmlspecialchars($account['account_holder']); ?>
                                    <?php if ($account['assigned_to_user']): ?>
                                    <div class="text-xs text-gray-500">Assigned to user</div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3"><?php echo htmlspecialchars($account['bank_name'] ?? 'N/A'); ?></td>
                                <td class="py-3">
                                    <?php 
                                    $typeColors = [
                                        'primary' => 'badge-primary',
                                        'secondary' => 'badge-info',
                                        'backup' => 'badge-warning'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $typeColors[$account['account_type']] ?? 'badge-info'; ?>">
                                        <?php echo ucfirst($account['account_type']); ?>
                                    </span>
                                </td>
                                <td class="py-3 font-medium">
                                    ₹<?php echo number_format($account['daily_limit']); ?>
                                </td>
                                <td class="py-3">
                                    <?php 
                                    $statusColors = [
                                        'active' => 'badge-success',
                                        'inactive' => 'badge-warning',
                                        'suspended' => 'badge-danger'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $statusColors[$account['status']] ?? 'badge-info'; ?>">
                                        <?php echo ucfirst($account['status']); ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="font-medium">₹<?php echo number_format($account['total_amount'] ?? 0, 2); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo $account['total_transactions'] ?? 0; ?> txns</div>
                                </td>
                                <td class="py-3">
                                    <div class="flex space-x-2">
                                        <button onclick="editUpiAccount(<?php echo $account['upi_id']; ?>)"
                                                class="px-3 py-1 text-sm bg-blue-50 text-blue-600 rounded hover:bg-blue-100">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="toggleUpiStatus(<?php echo $account['upi_id']; ?>, '<?php echo $account['status']; ?>')"
                                                class="px-3 py-1 text-sm bg-gray-50 text-gray-600 rounded hover:bg-gray-100">
                                            <?php if ($account['status'] == 'active'): ?>
                                            <i class="fas fa-pause"></i>
                                            <?php else: ?>
                                            <i class="fas fa-play"></i>
                                            <?php endif; ?>
                                        </button>
                                        <button onclick="deleteUpiAccount(<?php echo $account['upi_id']; ?>)"
                                                class="px-3 py-1 text-sm bg-red-50 text-red-600 rounded hover:bg-red-100">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($upiAccounts)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-8 text-gray-500">
                                    <i class="fas fa-wallet text-3xl mb-2"></i>
                                    <p>No UPI accounts found</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- UPI Statistics -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-6 rounded-xl">
                        <div class="flex items-center">
                            <div class="w-12 h-12 rounded-full bg-blue-500 bg-opacity-20 flex items-center justify-center mr-4">
                                <i class="fas fa-wallet text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-semibold text-gray-800">
                                    <?php echo count($upiAccounts); ?> Accounts
                                </h4>
                                <p class="text-sm text-gray-600">Total UPI accounts</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-green-50 to-green-100 p-6 rounded-xl">
                        <div class="flex items-center">
                            <div class="w-12 h-12 rounded-full bg-green-500 bg-opacity-20 flex items-center justify-center mr-4">
                                <i class="fas fa-rupee-sign text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-semibold text-gray-800">
                                    ₹<?php 
                                    $totalAmount = array_sum(array_column($upiAccounts, 'total_amount'));
                                    echo number_format($totalAmount, 2);
                                    ?>
                                </h4>
                                <p class="text-sm text-gray-600">Total processed</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-purple-50 to-purple-100 p-6 rounded-xl">
                        <div class="flex items-center">
                            <div class="w-12 h-12 rounded-full bg-purple-500 bg-opacity-20 flex items-center justify-center mr-4">
                                <i class="fas fa-exchange-alt text-purple-600 text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-semibold text-gray-800">
                                    <?php 
                                    $totalTxns = array_sum(array_column($upiAccounts, 'total_transactions'));
                                    echo number_format($totalTxns);
                                    ?> Transactions
                                </h4>
                                <p class="text-sm text-gray-600">Total payments</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($activeTab == 'agents'): ?>
            <!-- Agent Management -->
            <div class="mb-8">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-2 flex items-center">
                            <i class="fas fa-users mr-2 text-purple-500"></i>
                            Agent Management
                        </h3>
                        <p class="text-gray-600">Manage agents, commissions, and assignments</p>
                    </div>
                    <a href="add-agent.php" 
                       class="px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-600 text-white rounded-lg hover:opacity-90">
                        <i class="fas fa-user-plus mr-2"></i> Add New Agent
                    </a>
                </div>

                <!-- Agents Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-gray-500 text-sm border-b">
                                <th class="pb-3">Agent</th>
                                <th class="pb-3">Role</th>
                                <th class="pb-3">Commission</th>
                                <th class="pb-3">Links</th>
                                <th class="pb-3">Payments</th>
                                <th class="pb-3">Amount</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agents as $agent): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center text-white">
                                            <?php echo strtoupper(substr($agent['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($agent['full_name']); ?></div>
                                            <div class="text-xs text-gray-500">@<?php echo htmlspecialchars($agent['username']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <?php 
                                    $roleColors = [
                                        'superadmin' => 'badge-danger',
                                        'admin' => 'badge-primary',
                                        'agent' => 'badge-info'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $roleColors[$agent['role']] ?? 'badge-info'; ?>">
                                        <?php echo ucfirst($agent['role']); ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="font-medium"><?php echo $agent['commission_rate']; ?>%</div>
                                    <div class="text-xs text-gray-500">₹<?php echo number_format($agent['total_commission'], 2); ?></div>
                                </td>
                                <td class="py-3">
                                    <div class="font-medium"><?php echo $agent['total_links'] ?? 0; ?></div>
                                </td>
                                <td class="py-3">
                                    <div class="font-medium"><?php echo $agent['total_payments'] ?? 0; ?></div>
                                </td>
                                <td class="py-3">
                                    <div class="font-medium">₹<?php echo number_format($agent['total_amount'] ?? 0, 2); ?></div>
                                </td>
                                <td class="py-3">
                                    <?php 
                                    $statusColors = [
                                        'active' => 'badge-success',
                                        'inactive' => 'badge-warning',
                                        'suspended' => 'badge-danger'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $statusColors[$agent['status']] ?? 'badge-info'; ?>">
                                        <?php echo ucfirst($agent['status']); ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="flex space-x-2">
                                        <button onclick="editAgent(<?php echo $agent['agent_id']; ?>)"
                                                class="px-3 py-1 text-sm bg-blue-50 text-blue-600 rounded hover:bg-blue-100">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="toggleAgentStatus(<?php echo $agent['agent_id']; ?>, '<?php echo $agent['status']; ?>')"
                                                class="px-3 py-1 text-sm bg-gray-50 text-gray-600 rounded hover:bg-gray-100">
                                            <?php if ($agent['status'] == 'active'): ?>
                                            <i class="fas fa-pause"></i>
                                            <?php else: ?>
                                            <i class="fas fa-play"></i>
                                            <?php endif; ?>
                                        </button>
                                        <a href="agent-details.php?id=<?php echo $agent['agent_id']; ?>"
                                           class="px-3 py-1 text-sm bg-green-50 text-green-600 rounded hover:bg-green-100">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($agents)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-8 text-gray-500">
                                    <i class="fas fa-users text-3xl mb-2"></i>
                                    <p>No agents found</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Quick Actions -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white border rounded-xl p-4">
                        <div class="text-sm text-gray-600 mb-1">Total Agents</div>
                        <div class="text-2xl font-bold text-gray-800"><?php echo count($agents); ?></div>
                    </div>
                    <div class="bg-white border rounded-xl p-4">
                        <div class="text-sm text-gray-600 mb-1">Active Agents</div>
                        <div class="text-2xl font-bold text-green-600">
                            <?php 
                            $activeCount = 0;
                            foreach ($agents as $agent) {
                                if ($agent['status'] == 'active') $activeCount++;
                            }
                            echo $activeCount;
                            ?>
                        </div>
                    </div>
                    <div class="bg-white border rounded-xl p-4">
                        <div class="text-sm text-gray-600 mb-1">Total Commission</div>
                        <div class="text-2xl font-bold text-purple-600">
                            ₹<?php 
                            $totalCommission = array_sum(array_column($agents, 'total_commission'));
                            echo number_format($totalCommission, 2);
                            ?>
                        </div>
                    </div>
                    <div class="bg-white border rounded-xl p-4">
                        <div class="text-sm text-gray-600 mb-1">Avg Commission Rate</div>
                        <div class="text-2xl font-bold text-blue-600">
                            <?php 
                            $avgRate = 0;
                            $agentCount = count($agents);
                            if ($agentCount > 0) {
                                $sumRates = array_sum(array_column($agents, 'commission_rate'));
                                $avgRate = round($sumRates / $agentCount, 2);
                            }
                            echo $avgRate; ?>%
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($activeTab == 'notifications'): ?>
            <!-- Notification Settings -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold mb-6 flex items-center">
                    <i class="fas fa-bell mr-2 text-yellow-500"></i>
                    Notification Settings
                </h3>
                <p class="text-gray-600 mb-6">Configure email, SMS, and WhatsApp notifications</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Email Settings -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-gray-800 border-b pb-2">Email Notifications</h4>
                        <?php 
                        $emailSettings = $settings['notification'] ?? [];
                        foreach ($emailSettings as $setting): 
                            if (strpos($setting['setting_key'], 'email') !== false):
                        ?>
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <div class="flex justify-between items-start mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    <?php 
                                    $labels = [
                                        'enable_email_notifications' => 'Enable Email Notifications'
                                    ];
                                    echo $labels[$setting['setting_key']] ?? ucfirst(str_replace('_', ' ', $setting['setting_key']));
                                    ?>
                                </label>
                                <span class="text-xs text-gray-500"><?php echo $setting['setting_type']; ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <?php if ($setting['setting_type'] == 'boolean'): ?>
                                <div class="relative inline-block w-12 align-middle select-none">
                                    <input type="checkbox" 
                                           data-key="<?php echo $setting['setting_key']; ?>"
                                           class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"
                                           <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                                    <label class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                </div>
                                <?php else: ?>
                                <input type="<?php echo $setting['setting_type'] == 'number' ? 'number' : 'text'; ?>"
                                       data-key="<?php echo $setting['setting_key']; ?>"
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                       class="flex-1 px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none"
                                       placeholder="Enter value">
                                <?php endif; ?>
                                <button onclick="saveSetting('<?php echo $setting['setting_key']; ?>', this)"
                                        class="px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                                    Save
                                </button>
                            </div>
                            <?php if (!empty($setting['description'])): ?>
                            <p class="text-xs text-gray-500 mt-2"><?php echo htmlspecialchars($setting['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                        
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Email Templates
                            </label>
                            <select class="w-full px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none">
                                <option>Payment Received Template</option>
                                <option>Payment Failed Template</option>
                                <option>Link Expiry Template</option>
                            </select>
                            <button class="mt-3 w-full px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                                Edit Template
                            </button>
                        </div>
                    </div>
                    
                    <!-- SMS & WhatsApp Settings -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-gray-800 border-b pb-2">SMS & WhatsApp</h4>
                        <?php 
                        $smsSettings = $settings['sms'] ?? [];
                        foreach ($smsSettings as $setting): 
                        ?>
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <div class="flex justify-between items-start mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    <?php 
                                    $labels = [
                                        'sms_api_key' => 'SMS API Key',
                                        'sms_sender_id' => 'SMS Sender ID'
                                    ];
                                    echo $labels[$setting['setting_key']] ?? ucfirst(str_replace('_', ' ', $setting['setting_key']));
                                    ?>
                                </label>
                                <span class="text-xs text-gray-500"><?php echo $setting['setting_type']; ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <input type="<?php echo $setting['setting_type'] == 'number' ? 'number' : 'text'; ?>"
                                       data-key="<?php echo $setting['setting_key']; ?>"
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                       class="flex-1 px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none"
                                       placeholder="Enter value">
                                <button onclick="saveSetting('<?php echo $setting['setting_key']; ?>', this)"
                                        class="px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                                    Save
                                </button>
                            </div>
                            <?php if (!empty($setting['description'])): ?>
                            <p class="text-xs text-gray-500 mt-2"><?php echo htmlspecialchars($setting['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- WhatsApp Template -->
                        <?php 
                        $whatsappSetting = null;
                        foreach ($settings['whatsapp'] ?? [] as $setting) {
                            if ($setting['setting_key'] == 'whatsapp_message_template') {
                                $whatsappSetting = $setting;
                                break;
                            }
                        }
                        ?>
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                WhatsApp Message Template
                            </label>
                            <textarea data-key="whatsapp_message_template"
                                      class="w-full px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none h-32"
                                      placeholder="Enter WhatsApp message template"><?php echo htmlspecialchars($whatsappSetting['setting_value'] ?? ''); ?></textarea>
                            <div class="mt-2 text-xs text-gray-500">
                                Available variables: {customer_name}, {amount}, {payment_link}, {transaction_id}
                            </div>
                            <button onclick="saveSetting('whatsapp_message_template', this)"
                                    class="mt-3 w-full px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                                Save Template
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($activeTab == 'security'): ?>
            <!-- Security Settings -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold mb-6 flex items-center">
                    <i class="fas fa-shield-alt mr-2 text-red-500"></i>
                    Security Settings
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Password Policy -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-gray-800 border-b pb-2">Password Policy</h4>
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Minimum Password Length
                            </label>
                            <div class="flex items-center space-x-2">
                                <input type="number" min="6" max="20" value="8"
                                       class="flex-1 px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none">
                                <button class="px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                                    Save
                                </button>
                            </div>
                        </div>
                        
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Password Expiry (days)
                            </label>
                            <div class="flex items-center space-x-2">
                                <input type="number" min="0" max="365" value="90"
                                       class="flex-1 px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none"
                                       placeholder="0 for no expiry">
                                <button class="px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Session & Login Security -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-gray-800 border-b pb-2">Session Security</h4>
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Session Timeout (minutes)
                            </label>
                            <div class="flex items-center space-x-2">
                                <input type="number" min="5" max="1440" value="30"
                                       class="flex-1 px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none">
                                <button class="px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                                    Save
                                </button>
                            </div>
                        </div>
                        
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Max Failed Login Attempts
                            </label>
                            <div class="flex items-center space-x-2">
                                <input type="number" min="1" max="10" value="5"
                                       class="flex-1 px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none">
                                <button class="px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- IP Whitelist -->
                <div class="mt-8">
                    <h4 class="font-medium text-gray-800 mb-4">IP Whitelist</h4>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="flex items-center justify-between mb-4">
                            <p class="text-sm text-gray-600">Restrict access to specific IP addresses</p>
                            <button class="px-3 py-1 text-sm bg-blue-50 text-blue-600 rounded hover:bg-blue-100">
                                <i class="fas fa-plus mr-1"></i> Add IP
                            </button>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between p-2 bg-white rounded">
                                <span class="font-mono">192.168.1.1</span>
                                <button class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="flex items-center justify-between p-2 bg-white rounded">
                                <span class="font-mono">10.0.0.1</span>
                                <button class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($activeTab == 'advanced' && $user['role'] == 'superadmin'): ?>
            <!-- Advanced Settings -->
            <div class="mb-8">
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <strong>Warning:</strong> These settings affect the core functionality of the application. 
                                Changes should only be made by experienced administrators.
                            </p>
                        </div>
                    </div>
                </div>
                
                <h3 class="text-lg font-semibold mb-6 flex items-center">
                    <i class="fas fa-tools mr-2 text-gray-500"></i>
                    Advanced Settings
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Database Settings -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-gray-800 border-b pb-2">Database</h4>
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Auto-backup Interval (hours)
                            </label>
                            <div class="flex items-center space-x-2">
                                <input type="number" min="1" max="168" value="24"
                                       class="flex-1 px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none">
                                <button class="px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                                    Save
                                </button>
                            </div>
                        </div>
                        
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Backup Database</p>
                                    <p class="text-xs text-gray-500">Create manual backup</p>
                                </div>
                                <button class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100">
                                    <i class="fas fa-download mr-2"></i> Backup Now
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- System Maintenance -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-gray-800 border-b pb-2">Maintenance</h4>
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Clear Cache</p>
                                    <p class="text-xs text-gray-500">Remove temporary files</p>
                                </div>
                                <button class="px-4 py-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100">
                                    <i class="fas fa-broom mr-2"></i> Clear
                                </button>
                            </div>
                        </div>
                        
                        <div class="setting-card bg-gray-50 p-4 rounded-lg">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Optimize Database</p>
                                    <p class="text-xs text-gray-500">Improve performance</p>
                                </div>
                                <button class="px-4 py-2 bg-purple-50 text-purple-600 rounded-lg hover:bg-purple-100">
                                    <i class="fas fa-tachometer-alt mr-2"></i> Optimize
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="mt-8">
                    <h4 class="font-medium text-gray-800 mb-4">System Information</h4>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">PHP Version</p>
                                <p class="font-medium"><?php echo phpversion(); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Database Size</p>
                                <p class="font-medium">
                                    <?php 
                                    $sizeQuery = $db->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
                                    $size = $sizeQuery->fetch()['size'] ?? 0;
                                    echo $size . ' MB';
                                    ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Server Uptime</p>
                                <p class="font-medium"><?php echo round(@file_get_contents('/proc/uptime') / 3600, 2) . ' hours'; ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Memory Usage</p>
                                <p class="font-medium"><?php echo round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add UPI Modal -->
    <div id="addUpiModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Add UPI Account</h3>
                <button onclick="closeAddUpiModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="addUpiForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">UPI Address</label>
                    <input type="text" name="upi_address" required
                           class="w-full px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none"
                           placeholder="username@bank">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Holder Name</label>
                    <input type="text" name="account_holder" required
                           class="w-full px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none"
                           placeholder="John Doe">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                    <input type="text" name="bank_name"
                           class="w-full px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none"
                           placeholder="ICICI Bank">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Account Type</label>
                        <select name="account_type" class="w-full px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none">
                            <option value="primary">Primary</option>
                            <option value="secondary">Secondary</option>
                            <option value="backup">Backup</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Daily Limit (₹)</label>
                        <input type="number" name="daily_limit" value="100000"
                               class="w-full px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2"
                              class="w-full px-3 py-2 border rounded-lg focus:border-blue-500 focus:outline-none"
                              placeholder="Additional information"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeAddUpiModal()"
                            class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Add UPI Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Show loading
        function showLoading() {
            document.getElementById('loading').classList.remove('hidden');
        }
        
        // Hide loading
        function hideLoading() {
            document.getElementById('loading').classList.add('hidden');
        }
        
        // Save setting
        function saveSetting(key, button) {
            const input = button.parentElement.querySelector('input') || 
                         button.parentElement.querySelector('textarea') ||
                         button.parentElement.querySelector('select');
            let value;
            
            if (input.type === 'checkbox') {
                value = input.checked ? '1' : '0';
            } else {
                value = input.value;
            }
            
            showLoading();
            
            fetch('settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=update_setting&key=' + encodeURIComponent(key) + '&value=' + encodeURIComponent(value)
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showNotification('Setting updated successfully', 'success');
                } else {
                    showNotification('Failed to update setting', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Network error: ' + error, 'error');
            });
        }
        
        // Add UPI Modal
        function openAddUpiModal() {
            document.getElementById('addUpiModal').classList.remove('hidden');
        }
        
        function closeAddUpiModal() {
            document.getElementById('addUpiModal').classList.add('hidden');
            document.getElementById('addUpiForm').reset();
        }
        
        // Handle UPI form submission
        document.getElementById('addUpiForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_upi');
            
            showLoading();
            
            fetch('settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showNotification('UPI account added successfully', 'success');
                    closeAddUpiModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('Failed to add UPI account', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Network error: ' + error, 'error');
            });
        });
        
        // Toggle functions
        function toggleUpiStatus(upiId, currentStatus) {
            if (confirm('Are you sure you want to ' + (currentStatus === 'active' ? 'deactivate' : 'activate') + ' this UPI account?')) {
                showLoading();
                // Implement status toggle logic
                setTimeout(() => {
                    hideLoading();
                    location.reload();
                }, 1000);
            }
        }
        
        function toggleAgentStatus(agentId, currentStatus) {
            if (confirm('Are you sure you want to ' + (currentStatus === 'active' ? 'deactivate' : 'activate') + ' this agent?')) {
                showLoading();
                fetch('settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=update_agent&agent_id=' + agentId + '&field=status&value=' + (currentStatus === 'active' ? 'inactive' : 'active')
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showNotification('Agent status updated', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification('Failed to update agent', 'error');
                    }
                });
            }
        }
        
        // Delete UPI account
        function deleteUpiAccount(upiId) {
            if (confirm('Are you sure you want to delete this UPI account? This action cannot be undone.')) {
                showLoading();
                // Implement delete logic
                setTimeout(() => {
                    hideLoading();
                    showNotification('UPI account deleted', 'success');
                    setTimeout(() => location.reload(), 1000);
                }, 1000);
            }
        }
        
        // Edit functions (to be implemented)
        function editUpiAccount(upiId) {
            alert('Edit UPI account: ' + upiId + ' (Feature coming soon)');
        }
        
        function editAgent(agentId) {
            window.location.href = 'edit-agent.php?id=' + agentId;
        }
        
        // Show notification
        function showNotification(message, type = 'info') {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };
            
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 text-white px-6 py-3 rounded-lg shadow-lg ${colors[type]} z-50`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        // Initialize toggle switches
        document.addEventListener('DOMContentLoaded', function() {
            const toggleCheckboxes = document.querySelectorAll('.toggle-checkbox');
            toggleCheckboxes.forEach(checkbox => {
                const label = checkbox.nextElementSibling;
                if (checkbox.checked) {
                    label.style.backgroundColor = '#10b981';
                }
                
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        label.style.backgroundColor = '#10b981';
                    } else {
                        label.style.backgroundColor = '#d1d5db';
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>