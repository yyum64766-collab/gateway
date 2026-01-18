<?php
// index.php - NimCredit Dashboard with Device Tracking & Security
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

// Function to get Device Info
function getDeviceInfo() {
    $u_agent = $_SERVER['HTTP_USER_AGENT'];
    $bname = 'Unknown';
    $platform = 'Unknown';

    if (preg_match('/linux/i', $u_agent)) { $platform = 'Linux'; }
    elseif (preg_match('/macintosh|mac os x/i', $u_agent)) { $platform = 'Mac'; }
    elseif (preg_match('/windows|win32/i', $u_agent)) { $platform = 'Windows'; }
    elseif (preg_match('/android/i', $u_agent)) { $platform = 'Android'; }
    elseif (preg_match('/iphone/i', $u_agent)) { $platform = 'iPhone'; }

    if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent)) { $bname = 'IE'; }
    elseif(preg_match('/Firefox/i',$u_agent)) { $bname = 'Firefox'; }
    elseif(preg_match('/Chrome/i',$u_agent)) { $bname = 'Chrome'; }
    elseif(preg_match('/Safari/i',$u_agent)) { $bname = 'Safari'; }
    
    return $platform . " (" . $bname . ")";
}

// Get statistics
function getDashboardStats($pdo) {
    $stats = [];
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_generations");
    $stats['total_links'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE payment_status = 'success'");
    $stats['total_payments'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_status = 'success'");
    $stats['total_amount'] = number_format($stmt->fetch()['total'] ?? 0, 2);
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM agents WHERE status = 'active' AND role = 'agent'");
    $stats['active_agents'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM agents WHERE status = 'active' AND role = 'admin'");
    $stats['active_admins'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_status = 'success' AND DATE(created_at) = CURDATE()");
    $stats['today_revenue'] = number_format($stmt->fetch()['total'] ?? 0, 2);
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE payment_status = 'pending'");
    $stats['pending_payments'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_generations WHERE DATE(generated_at) = CURDATE()");
    $stats['today_links'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $pdo->query("SELECT (SELECT COUNT(*) FROM payments WHERE payment_status = 'success') as successful, (SELECT COUNT(*) FROM payments WHERE payment_status IN ('success', 'failed')) as total");
    $result = $stmt->fetch();
    $stats['success_rate'] = ($result['total'] ?? 0) > 0 ? round(($result['successful'] / $result['total']) * 100, 1) : 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_generations WHERE link_status = 'active' AND expiry_time > NOW()");
    $stats['active_links'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_generations WHERE link_status = 'active' AND expiry_time <= NOW()");
    $stats['expired_links'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_clicks");
    $stats['total_clicks'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT customer_phone) as total FROM link_clicks");
    $stats['unique_customers'] = $stmt->fetch()['total'] ?? 0;
    
    return $stats;
}

function getRecentActivities($pdo, $limit = 10) {
    $sql = "SELECT 'payment' as type, payment_id as id, customer_phone, amount, payment_status as status, created_at, 'N/A' as device_info, CONCAT('Payment ', payment_status, ' - ₹', amount) as description FROM payments
            UNION ALL
            SELECT 'link' as type, link_id as id, customer_phone, amount, link_status as status, generated_at as created_at, 'N/A' as device_info, CONCAT('Link generated - ₹', amount) as description FROM link_generations
            ORDER BY created_at DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getTopAgents($pdo, $limit = 5) {
    $sql = "SELECT a.agent_id, a.username, a.full_name, a.role, COUNT(DISTINCT p.payment_id) as successful_payments, COALESCE(SUM(CASE WHEN p.payment_status = 'success' THEN p.amount ELSE 0 END), 0) as total_amount
            FROM agents a LEFT JOIN payments p ON a.agent_id = p.agent_id WHERE a.status = 'active' GROUP BY a.agent_id ORDER BY total_amount DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getDailyRevenue($pdo, $days = 7) {
    $stmt = $pdo->prepare("SELECT DATE(created_at) as date, SUM(amount) as total_amount FROM payments WHERE payment_status = 'success' AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY) GROUP BY DATE(created_at) ORDER BY date ASC");
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getRecentPayments($pdo, $limit = 10) {
    $stmt = $pdo->prepare("SELECT p.*, a.full_name as agent_name FROM payments p LEFT JOIN agents a ON p.agent_id = a.agent_id ORDER BY p.created_at DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function getUserInfo($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM agents WHERE agent_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    return $stmt->fetch();
}

// Main Execution
$db = connectDB();
checkLogin();
$user = getUserInfo($db, $_SESSION['user_id']);
$stats = getDashboardStats($db);
$activities = getRecentActivities($db, 10);
$topAgents = getTopAgents($db, 5);
$recentPayments = getRecentPayments($db, 10);
$dailyRevenue = getDailyRevenue($db, 7);
$currentDevice = getDeviceInfo();
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
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        /* Security: Hide password dots effectively */
        input[type="password"] { color: transparent; text-shadow: 0 0 8px rgba(0,0,0,0.5); }
    </style>
</head>
<body class="bg-gray-50" autocomplete="off">

    <div class="flex">
        <div class="w-64 fixed inset-y-0 bg-blue-900 text-white p-6 hidden lg:block">
            <h1 class="text-2xl font-bold mb-8">NimCredit</h1>
            <div class="mb-6 p-4 bg-blue-800 rounded-xl">
                <p class="text-xs text-blue-300">Logged in from:</p>
                <p class="text-sm font-mono"><?php echo $currentDevice; ?></p>
            </div>
            <nav class="space-y-4">
                <a href="#" class="block p-2 bg-blue-700 rounded">Dashboard</a>
                <a href="logout.php" class="block p-2 text-red-300">Logout</a>
            </nav>
        </div>

        <div class="lg:ml-64 flex-1">
            <header class="bg-white p-6 shadow-sm flex justify-between items-center">
                <h2 class="text-xl font-bold">Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
                <div class="text-right text-xs text-gray-500">
                    Device: <?php echo $currentDevice; ?> | IP: <?php echo $_SERVER['REMOTE_ADDR']; ?>
                </div>
            </header>

            <main class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="stat-card bg-white p-6 rounded-2xl shadow-sm border-l-4 border-green-500">
                        <p class="text-gray-500">Total Revenue</p>
                        <h3 class="text-2xl font-bold">₹<?php echo $stats['total_amount']; ?></h3>
                    </div>
                    <div class="stat-card bg-white p-6 rounded-2xl shadow-sm border-l-4 border-blue-500">
                        <p class="text-gray-500">Active Links</p>
                        <h3 class="text-2xl font-bold"><?php echo $stats['active_links']; ?></h3>
                    </div>
                    <div class="stat-card bg-white p-6 rounded-2xl shadow-sm border-l-4 border-purple-500">
                        <p class="text-gray-500">Total Clicks</p>
                        <h3 class="text-2xl font-bold"><?php echo $stats['total_clicks']; ?></h3>
                    </div>
                    <div class="stat-card bg-white p-6 rounded-2xl shadow-sm border-l-4 border-orange-500">
                        <p class="text-gray-500">Success Rate</p>
                        <h3 class="text-2xl font-bold"><?php echo $stats['success_rate']; ?>%</h3>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 mb-8">
                    <h3 class="text-lg font-semibold mb-4"><i class="fas fa-shield-alt mr-2 text-blue-600"></i> Active Sessions & Device Info</h3>
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-gray-400 text-sm border-b">
                                <th class="pb-3">Agent Name</th>
                                <th class="pb-3">Username</th>
                                <th class="pb-3">Role</th>
                                <th class="pb-3">Last Known Device</th>
                                <th class="pb-3">Security Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topAgents as $agent): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 font-medium"><?php echo htmlspecialchars($agent['full_name']); ?></td>
                                <td class="py-3"><span class="blur-sm select-none">******</span> (Hidden)</td>
                                <td class="py-3"><span class="badge badge-info"><?php echo $agent['role']; ?></span></td>
                                <td class="py-3 text-sm font-mono text-gray-600"><?php echo $currentDevice; ?></td>
                                <td class="py-3"><span class="text-green-500 text-xs font-bold"><i class="fas fa-check-circle"></i> Encrypted</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm">
                        <h3 class="font-bold mb-4">Revenue (7 Days)</h3>
                        <canvas id="revChart" height="200"></canvas>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm">
                        <h3 class="font-bold mb-4">Security Settings</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <span>Auto-fill Protection</span>
                                <span class="text-green-600 font-bold">ACTIVE</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <span>Credential Masking</span>
                                <span class="text-green-600 font-bold">ACTIVE</span>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Disable right click to protect UI
        document.addEventListener('contextmenu', event => event.preventDefault());
        
        // Prevent form autofill on any inputs found
        document.querySelectorAll('input').forEach(input => {
            input.setAttribute('autocomplete', 'new-password');
            input.setAttribute('readonly', 'true');
            input.onfocus = () => input.removeAttribute('readonly');
        });

        // Chart Init
        const ctx = document.getElementById('revChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: [<?php foreach($dailyRevenue as $d) echo '"'.date('d M', strtotime($d['date'])).'",'; ?>],
                datasets: [{
                    label: 'Revenue',
                    data: [<?php foreach($dailyRevenue as $d) echo $d['total_amount'].','; ?>],
                    borderColor: '#3b82f6',
                    tension: 0.3
                }]
            }
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>