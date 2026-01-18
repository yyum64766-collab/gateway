<?php
// index.php - NimCredit Dashboard with Real-time Database Connection
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

// Get statistics from database
function getDashboardStats($pdo) {
    $stats = [];
    
    // Total Links Generated
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_generations");
    $stats['total_links'] = $stmt->fetch()['total'] ?? 0;
    
    // Total Payments Received
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE payment_status = 'success'");
    $stats['total_payments'] = $stmt->fetch()['total'] ?? 0;
    
    // Total Amount Collected
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_status = 'success'");
    $stats['total_amount'] = number_format($stmt->fetch()['total'] ?? 0, 2);
    
    // Active Agents
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM agents WHERE status = 'active' AND role = 'agent'");
    $stats['active_agents'] = $stmt->fetch()['total'] ?? 0;
    
    // Active Admins
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM agents WHERE status = 'active' AND role = 'admin'");
    $stats['active_admins'] = $stmt->fetch()['total'] ?? 0;
    
    // Today's Revenue
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments 
                         WHERE payment_status = 'success' AND DATE(created_at) = CURDATE()");
    $stats['today_revenue'] = number_format($stmt->fetch()['total'] ?? 0, 2);
    
    // Pending Payments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE payment_status = 'pending'");
    $stats['pending_payments'] = $stmt->fetch()['total'] ?? 0;
    
    // Links Created Today
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_generations WHERE DATE(generated_at) = CURDATE()");
    $stats['today_links'] = $stmt->fetch()['total'] ?? 0;
    
    // Success Rate
    $stmt = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM payments WHERE payment_status = 'success') as successful,
        (SELECT COUNT(*) FROM payments WHERE payment_status IN ('success', 'failed')) as total");
    $result = $stmt->fetch();
    $successful = $result['successful'] ?? 0;
    $total = $result['total'] ?? 0;
    $stats['success_rate'] = $total > 0 ? round(($successful / $total) * 100, 1) : 0;
    
    // Active Links (not expired)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_generations 
                         WHERE link_status = 'active' AND expiry_time > NOW()");
    $stats['active_links'] = $stmt->fetch()['total'] ?? 0;
    
    // Expired Links
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_generations 
                         WHERE link_status = 'active' AND expiry_time <= NOW()");
    $stats['expired_links'] = $stmt->fetch()['total'] ?? 0;
    
    // Total Clicks
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_clicks");
    $stats['total_clicks'] = $stmt->fetch()['total'] ?? 0;
    
    // Unique Customers
    $stmt = $pdo->query("SELECT COUNT(DISTINCT customer_phone) as total FROM link_clicks");
    $stats['unique_customers'] = $stmt->fetch()['total'] ?? 0;
    
    return $stats;
}

// Get Recent Activities
function getRecentActivities($pdo, $limit = 10) {
    $sql = "SELECT 'payment' as type, payment_id as id, customer_phone, amount, 
                   payment_status as status, created_at, NULL as agent_id,
                   CONCAT('Payment ', payment_status, ' - ₹', amount) as description
            FROM payments
            UNION ALL
            SELECT 'link' as type, link_id as id, customer_phone, amount, 
                   link_status as status, generated_at as created_at, agent_id,
                   CONCAT('Link generated - ₹', amount) as description
            FROM link_generations
            UNION ALL
            SELECT 'click' as type, click_id as id, customer_phone, NULL as amount, 
                   payment_status as status, clicked_at as created_at, agent_id,
                   'Link clicked' as description
            FROM link_clicks
            ORDER BY created_at DESC
            LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get Top Performing Agents
function getTopAgents($pdo, $limit = 5) {
    $sql = "SELECT a.agent_id, a.username, a.full_name, a.role,
                   COUNT(DISTINCT lg.link_id) as total_links,
                   COUNT(DISTINCT lc.click_id) as total_clicks,
                   COUNT(DISTINCT p.payment_id) as successful_payments,
                   COALESCE(SUM(CASE WHEN p.payment_status = 'success' THEN p.amount ELSE 0 END), 0) as total_amount,
                   a.total_commission
            FROM agents a
            LEFT JOIN link_generations lg ON a.agent_id = lg.agent_id
            LEFT JOIN link_clicks lc ON a.agent_id = lc.agent_id
            LEFT JOIN payments p ON a.agent_id = p.agent_id
            WHERE a.status = 'active'
            GROUP BY a.agent_id, a.username, a.full_name, a.role, a.total_commission
            ORDER BY total_amount DESC
            LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get Daily Revenue Chart Data
function getDailyRevenue($pdo, $days = 7) {
    $sql = "SELECT DATE(created_at) as date,
                   COUNT(*) as payment_count,
                   COALESCE(SUM(amount), 0) as total_amount
            FROM payments
            WHERE payment_status = 'success'
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get Recent Payments
function getRecentPayments($pdo, $limit = 10) {
    $sql = "SELECT p.*, a.full_name as agent_name, lg.customer_name
            FROM payments p
            LEFT JOIN agents a ON p.agent_id = a.agent_id
            LEFT JOIN link_generations lg ON p.link_id = lg.link_id
            ORDER BY p.created_at DESC
            LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Check if user is logged in
function checkLogin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        header('Location: login.php');
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
// Main Execution से पहले
$db = connectDB();

// Check and create table if not exists
$tableCheck = $db->query("SHOW TABLES LIKE 'device_logins'");
if ($tableCheck->rowCount() == 0) {
    createDeviceLoginsTable($db);
}

checkLogin();
// ... rest of the code
// Main Execution
$db = connectDB();
checkLogin();
$user = getUserInfo($db, $_SESSION['user_id']);
$stats = getDashboardStats($db);
$activities = getRecentActivities($db, 15);
$topAgents = getTopAgents($db, 5);
$recentPayments = getRecentPayments($db, 10);
$dailyRevenue = getDailyRevenue($db, 7);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NimCredit Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .sidebar { transition: all 0.3s ease; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
        }
        .chart-container { position: relative; height: 300px; width: 100%; }
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-primary { background: #e0e7ff; color: #3730a3; }
        .activity-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        .dot-success { background: #10b981; }
        .dot-warning { background: #f59e0b; }
        .dot-danger { background: #ef4444; }
        .dot-info { background: #3b82f6; }
        .progress-bar { height: 6px; border-radius: 3px; background: #e5e7eb; overflow: hidden; }
        .progress-fill { height: 100%; transition: width 0.5s ease; }
        .progress-success { background: #10b981; }
        .progress-warning { background: #f59e0b; }
        .progress-danger { background: #ef4444; }
        .progress-info { background: #3b82f6; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Loading Overlay -->
    <div id="loading" class="fixed inset-0 bg-white bg-opacity-80 flex items-center justify-center z-50">
        <div class="text-center">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            <p class="mt-4 text-gray-600">Loading Dashboard...</p>
        </div>
    </div>

    <!-- Mobile Menu Button -->
    <div class="lg:hidden fixed top-4 left-4 z-40">
        <button id="menuToggle" class="p-2 rounded-lg bg-white shadow-md">
            <i class="fas fa-bars text-gray-700 text-lg"></i>
        </button>
    </div>

    <!-- Sidebar -->
    <div class="sidebar fixed inset-y-0 left-0 z-30 w-64 bg-gradient-to-b from-blue-900 to-purple-900 text-white">
        <div class="p-6">
            <div class="flex items-center space-x-3 mb-8">
                <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center">
                    <i class="fas fa-credit-card text-white"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold">NimCredit</h1>
                    <p class="text-xs text-blue-200">Payment Dashboard</p>
                </div>
            </div>
            
            <!-- User Info -->
            <div class="mb-8 p-4 bg-blue-800 bg-opacity-30 rounded-xl">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center">
                        <i class="fas fa-user text-white text-lg"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                        <p class="text-xs text-blue-200">
                            <?php 
                            $role = $user['role'];
                            $roleNames = [
                                'superadmin' => 'Super Admin',
                                'admin' => 'Administrator',
                                'agent' => 'Agent'
                            ];
                            echo $roleNames[$role] ?? 'User';
                            ?>
                        </p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                    <div class="text-center p-2 bg-blue-800 bg-opacity-40 rounded">
                        <div class="font-bold">ID</div>
                        <div><?php echo $user['agent_id']; ?></div>
                    </div>
                    <div class="text-center p-2 bg-blue-800 bg-opacity-40 rounded">
                        <div class="font-bold">Since</div>
                        <div><?php echo date('M Y', strtotime($user['created_at'])); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Navigation -->
            <nav class="space-y-2">
                <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-xl bg-blue-700 bg-opacity-50">
                    <i class="fas fa-chart-line w-5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="links.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-blue-800 hover:bg-opacity-30">
                    <i class="fas fa-link w-5"></i>
                    <span>Links</span>
                    <span class="ml-auto badge badge-primary"><?php echo $stats['total_links']; ?></span>
                </a>
                <a href="payments.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-blue-800 hover:bg-opacity-30">
                    <i class="fas fa-rupee-sign w-5"></i>
                    <span>Payments</span>
                    <span class="ml-auto badge badge-success"><?php echo $stats['total_payments']; ?></span>
                </a>
                <a href="agents.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-blue-800 hover:bg-opacity-30">
                    <i class="fas fa-users w-5"></i>
                    <span>Agents</span>
                    <span class="ml-auto badge badge-info"><?php echo $stats['active_agents']; ?></span>
                </a>
                <a href="upi.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-blue-800 hover:bg-opacity-30">
                    <i class="fas fa-wallet w-5"></i>
                    <span>UPI Accounts</span>
                </a>
                <a href="reports.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-blue-800 hover:bg-opacity-30">
                    <i class="fas fa-chart-bar w-5"></i>
                    <span>Reports</span>
                </a>
                <?php if ($user['role'] == 'superadmin' || $user['role'] == 'admin'): ?>
                <a href="settings.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-blue-800 hover:bg-opacity-30">
                    <i class="fas fa-cog w-5"></i>
                    <span>Settings</span>
                </a>
                <?php endif; ?>
            </nav>
            
            <!-- Quick Actions -->
            <div class="mt-8 pt-8 border-t border-blue-700">
                <h3 class="text-sm font-semibold text-blue-300 mb-3">Quick Actions</h3>
                <div class="space-y-2">
                    <a href="generate-link.php" class="flex items-center space-x-2 text-sm p-2 rounded-lg bg-green-600 hover:bg-green-700">
                        <i class="fas fa-plus w-4"></i>
                        <span>Generate New Link</span>
                    </a>
                    <a href="add-agent.php" class="flex items-center space-x-2 text-sm p-2 rounded-lg bg-blue-600 hover:bg-blue-700">
                        <i class="fas fa-user-plus w-4"></i>
                        <span>Add Agent</span>
                    </a>
                    <a href="export-data.php" class="flex items-center space-x-2 text-sm p-2 rounded-lg bg-purple-600 hover:bg-purple-700">
                        <i class="fas fa-download w-4"></i>
                        <span>Export Data</span>
                    </a>
                </div>
            </div>
            
            <!-- Logout -->
            <div class="absolute bottom-0 left-0 right-0 p-6">
                <a href="logout.php" class="flex items-center justify-center space-x-2 p-3 rounded-xl bg-red-600 hover:bg-red-700">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="px-6 py-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Dashboard Overview</h2>
                        <p class="text-gray-600">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! Here's what's happening.</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="hidden md:block">
                            <div class="text-sm text-gray-600">Last updated</div>
                            <div class="font-semibold" id="currentTime"><?php echo date('h:i A'); ?></div>
                        </div>
                        <button onclick="refreshData()" class="p-2 rounded-full bg-blue-50 text-blue-600 hover:bg-blue-100">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <div class="relative">
                            <button id="notificationsBtn" class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200">
                                <i class="fas fa-bell"></i>
                                <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">3</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-6">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Revenue -->
                <div class="stat-card bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-2xl p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-green-100">Total Revenue</p>
                            <h3 class="text-3xl font-bold mt-2">₹<?php echo $stats['total_amount']; ?></h3>
                            <p class="text-green-200 text-sm mt-2"><?php echo $stats['total_payments']; ?> successful payments</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-rupee-sign text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm">
                        <i class="fas fa-arrow-up mr-1"></i>
                        <span>₹<?php echo $stats['today_revenue']; ?> today</span>
                    </div>
                </div>
                
                <!-- Total Links -->
                <div class="stat-card bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-2xl p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-blue-100">Total Links</p>
                            <h3 class="text-3xl font-bold mt-2"><?php echo number_format($stats['total_links']); ?></h3>
                            <p class="text-blue-200 text-sm mt-2"><?php echo $stats['today_links']; ?> created today</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-link text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm mb-1">
                            <span>Active: <?php echo $stats['active_links']; ?></span>
                            <span>Expired: <?php echo $stats['expired_links']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill progress-success" style="width: <?php echo $stats['total_links'] > 0 ? ($stats['active_links'] / $stats['total_links'] * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Active Users -->
                <div class="stat-card bg-gradient-to-r from-purple-500 to-pink-600 text-white rounded-2xl p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-purple-100">Active Users</p>
                            <h3 class="text-3xl font-bold mt-2"><?php echo $stats['active_agents'] + $stats['active_admins']; ?></h3>
                            <p class="text-purple-200 text-sm mt-2"><?php echo $stats['active_agents']; ?> agents, <?php echo $stats['active_admins']; ?> admins</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm">
                            <span>Total Clicks: <?php echo number_format($stats['total_clicks']); ?></span>
                            <span>Unique: <?php echo number_format($stats['unique_customers']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Success Rate -->
                <div class="stat-card bg-gradient-to-r from-orange-500 to-red-600 text-white rounded-2xl p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-orange-100">Success Rate</p>
                            <h3 class="text-3xl font-bold mt-2"><?php echo $stats['success_rate']; ?>%</h3>
                            <p class="text-orange-200 text-sm mt-2"><?php echo $stats['pending_payments']; ?> pending payments</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-chart-line text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="progress-bar">
                            <div class="progress-fill progress-warning" style="width: <?php echo $stats['success_rate']; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts and Tables Row -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Revenue Chart -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold">Revenue Trend (Last 7 Days)</h3>
                        <div class="flex space-x-2">
                            <button class="px-3 py-1 text-sm rounded-lg bg-blue-50 text-blue-600">Week</button>
                            <button class="px-3 py-1 text-sm rounded-lg hover:bg-gray-100">Month</button>
                            <button class="px-3 py-1 text-sm rounded-lg hover:bg-gray-100">Year</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                
                <!-- Top Agents -->
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-6">Top Performing Agents</h3>
                    <div class="space-y-4">
                        <?php foreach ($topAgents as $index => $agent): ?>
                        <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center text-white font-bold">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div>
                                    <h4 class="font-medium"><?php echo htmlspecialchars($agent['full_name']); ?></h4>
                                    <p class="text-xs text-gray-500">@<?php echo htmlspecialchars($agent['username']); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold">₹<?php echo number_format($agent['total_amount'], 2); ?></div>
                                <div class="text-xs text-gray-500">
                                    <?php echo $agent['successful_payments']; ?> payments
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($topAgents)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-users text-3xl mb-2"></i>
                            <p>No agent data available</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <a href="agents.php" class="mt-6 block text-center text-blue-600 hover:text-blue-800 font-medium">
                        View All Agents <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            
            <!-- Recent Payments -->
            <div class="bg-white rounded-2xl shadow-sm p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold">Recent Payments</h3>
                    <a href="payments.php" class="text-blue-600 hover:text-blue-800 font-medium">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-gray-500 text-sm border-b">
                                <th class="pb-3">Payment ID</th>
                                <th class="pb-3">Customer</th>
                                <th class="pb-3">Amount</th>
                                <th class="pb-3">Agent</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3">
                                    <div class="font-mono text-sm"><?php echo substr($payment['payment_id'], 0, 8) . '...'; ?></div>
                                </td>
                                <td class="py-3">
                                    <div class="font-medium"><?php echo htmlspecialchars($payment['customer_name'] ?? $payment['customer_phone']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo $payment['customer_phone']; ?></div>
                                </td>
                                <td class="py-3 font-bold">₹<?php echo number_format($payment['amount'], 2); ?></td>
                                <td class="py-3">
                                    <div class="text-sm"><?php echo htmlspecialchars($payment['agent_name'] ?? 'N/A'); ?></div>
                                </td>
                                <td class="py-3">
                                    <?php 
                                    $status = $payment['payment_status'];
                                    $statusClasses = [
                                        'success' => 'badge-success',
                                        'pending' => 'badge-warning',
                                        'failed' => 'badge-danger',
                                        'expired' => 'badge-danger',
                                        'processing' => 'badge-info'
                                    ];
                                    $statusText = [
                                        'success' => 'Success',
                                        'pending' => 'Pending',
                                        'failed' => 'Failed',
                                        'expired' => 'Expired',
                                        'processing' => 'Processing'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $statusClasses[$status] ?? 'badge-info'; ?>">
                                        <?php echo $statusText[$status] ?? ucfirst($status); ?>
                                    </span>
                                </td>
                                <td class="py-3 text-sm text-gray-500">
                                    <?php echo date('h:i A', strtotime($payment['created_at'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentPayments)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-8 text-gray-500">
                                    <i class="fas fa-receipt text-3xl mb-2"></i>
                                    <p>No recent payments</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-6">Recent Activity</h3>
                <div class="space-y-4">
                    <?php foreach ($activities as $activity): ?>
                    <div class="flex items-start space-x-3 p-3 rounded-lg hover:bg-gray-50">
                        <?php 
                        $type = $activity['type'];
                        $icon = '';
                        $color = '';
                        if ($type == 'payment') {
                            $icon = 'fa-rupee-sign';
                            $color = 'text-green-500 bg-green-50';
                        } elseif ($type == 'link') {
                            $icon = 'fa-link';
                            $color = 'text-blue-500 bg-blue-50';
                        } else {
                            $icon = 'fa-mouse-pointer';
                            $color = 'text-purple-500 bg-purple-50';
                        }
                        ?>
                        <div class="w-10 h-10 rounded-full <?php echo $color; ?> flex items-center justify-center flex-shrink-0">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="flex-1">
                            <div class="flex justify-between">
                                <h4 class="font-medium"><?php echo htmlspecialchars($activity['description']); ?></h4>
                                <span class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($activity['created_at'])); ?></span>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">
                                <?php if (!empty($activity['customer_phone'])): ?>
                                Customer: <?php echo $activity['customer_phone']; ?>
                                <?php endif; ?>
                                <?php if (!empty($activity['amount'])): ?>
                                • Amount: ₹<?php echo number_format($activity['amount'], 2); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($activities)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-history text-3xl mb-2"></i>
                        <p>No recent activity</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="bg-white border-t px-6 py-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="text-gray-600 text-sm">
                    &copy; <?php echo date('Y'); ?> NimCredit. All rights reserved.
                </div>
                <div class="flex items-center space-x-4 mt-2 md:mt-0">
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-database mr-1"></i>
                        Database: <?php echo DB_NAME; ?>
                    </div>
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-server mr-1"></i>
                        <?php echo gethostname(); ?>
                    </div>
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-clock mr-1"></i>
                        <?php echo date('d M Y'); ?>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Notifications Panel -->
    <div id="notificationsPanel" class="fixed inset-y-0 right-0 z-40 w-80 bg-white shadow-xl transform translate-x-full transition-transform">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold">Notifications</h3>
                <button onclick="closeNotifications()" class="p-2 rounded-full hover:bg-gray-100">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="space-y-4">
                <!-- Notification items would go here -->
                <div class="p-3 rounded-lg bg-blue-50 border border-blue-100">
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                            <i class="fas fa-rupee-sign text-sm"></i>
                        </div>
                        <div>
                            <p class="font-medium text-sm">New Payment Received</p>
                            <p class="text-xs text-gray-600 mt-1">₹5,000 from customer 9876543210</p>
                            <p class="text-xs text-gray-500 mt-1">2 minutes ago</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Hide loading screen
        window.addEventListener('load', function() {
            document.getElementById('loading').style.display = 'none';
        });

        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Notifications panel
        document.getElementById('notificationsBtn').addEventListener('click', function() {
            document.getElementById('notificationsPanel').classList.remove('translate-x-full');
        });

        function closeNotifications() {
            document.getElementById('notificationsPanel').classList.add('translate-x-full');
        }

        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        setInterval(updateTime, 60000);
        updateTime();

        // Refresh data
        function refreshData() {
            document.getElementById('loading').style.display = 'flex';
            setTimeout(() => {
                location.reload();
            }, 1000);
        }

        // Revenue Chart
        const revenueData = {
            labels: [<?php 
                foreach($dailyRevenue as $day) {
                    echo '"' . date('d M', strtotime($day['date'])) . '", ';
                }
            ?>],
            datasets: [{
                label: 'Daily Revenue (₹)',
                data: [<?php 
                    foreach($dailyRevenue as $day) {
                        echo $day['total_amount'] . ', ';
                    }
                ?>],
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        };

        const revenueConfig = {
            type: 'line',
            data: revenueData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            display: true,
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        };

        // Initialize chart
        window.onload = function() {
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, revenueConfig);
        };

        // Auto refresh every 30 seconds
        setInterval(refreshData, 30000);
    </script>
</body>
</html>
<?php
ob_end_flush();
?>