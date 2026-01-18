<?php
// reports.php - Reports and analytics
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

$db = connectDB();
checkLogin();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get date range
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$agent_id = $_GET['agent_id'] ?? '';

// Build filters
$where = ["DATE(p.created_at) BETWEEN :date_from AND :date_to"];
$params = [':date_from' => $date_from, ':date_to' => $date_to];

if ($user_role == 'agent') {
    $where[] = "p.agent_id = :agent_id";
    $params[':agent_id'] = $user_id;
} elseif (!empty($agent_id)) {
    $where[] = "p.agent_id = :agent_id";
    $params[':agent_id'] = $agent_id;
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Get summary stats
$summarySql = "
    SELECT 
        COUNT(*) as total_payments,
        COUNT(CASE WHEN p.payment_status = 'success' THEN 1 END) as successful_payments,
        COUNT(CASE WHEN p.payment_status = 'pending' THEN 1 END) as pending_payments,
        COUNT(CASE WHEN p.payment_status = 'failed' THEN 1 END) as failed_payments,
        COALESCE(SUM(CASE WHEN p.payment_status = 'success' THEN p.amount END), 0) as total_amount,
        COALESCE(AVG(CASE WHEN p.payment_status = 'success' THEN p.amount END), 0) as avg_amount,
        COUNT(DISTINCT p.customer_phone) as unique_customers,
        COUNT(DISTINCT p.agent_id) as active_agents
    FROM payments p
    $whereClause
";

$summaryStmt = $db->prepare($summarySql);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

// Get daily stats
$dailySql = "
    SELECT 
        DATE(p.created_at) as date,
        COUNT(*) as payment_count,
        COUNT(CASE WHEN p.payment_status = 'success' THEN 1 END) as successful_count,
        COALESCE(SUM(CASE WHEN p.payment_status = 'success' THEN p.amount END), 0) as total_amount
    FROM payments p
    $whereClause
    GROUP BY DATE(p.created_at)
    ORDER BY date
";

$dailyStmt = $db->prepare($dailySql);
$dailyStmt->execute($params);
$dailyStats = $dailyStmt->fetchAll();

// Get agent performance
$agentSql = "
    SELECT 
        a.agent_id,
        a.full_name,
        a.username,
        a.role,
        COUNT(p.payment_id) as total_payments,
        COUNT(CASE WHEN p.payment_status = 'success' THEN 1 END) as successful_payments,
        COALESCE(SUM(CASE WHEN p.payment_status = 'success' THEN p.amount END), 0) as total_amount,
        COALESCE(AVG(CASE WHEN p.payment_status = 'success' THEN p.amount END), 0) as avg_amount,
        (COUNT(CASE WHEN p.payment_status = 'success' THEN 1 END) * 100.0 / COUNT(p.payment_id)) as success_rate
    FROM agents a
    LEFT JOIN payments p ON a.agent_id = p.agent_id AND DATE(p.created_at) BETWEEN :date_from AND :date_to
    WHERE a.status = 'active'
    GROUP BY a.agent_id, a.full_name, a.username, a.role
    HAVING total_payments > 0
    ORDER BY total_amount DESC
";

$agentStmt = $db->prepare($agentSql);
$agentStmt->execute([':date_from' => $date_from, ':date_to' => $date_to]);
$agentStats = $agentStmt->fetchAll();

// Get UPI performance
$upiSql = "
    SELECT 
        p.upi_id,
        COUNT(*) as total_payments,
        COUNT(CASE WHEN p.payment_status = 'success' THEN 1 END) as successful_payments,
        COALESCE(SUM(CASE WHEN p.payment_status = 'success' THEN p.amount END), 0) as total_amount,
        (COUNT(CASE WHEN p.payment_status = 'success' THEN 1 END) * 100.0 / COUNT(*)) as success_rate
    FROM payments p
    $whereClause
    GROUP BY p.upi_id
    HAVING total_payments > 0
    ORDER BY total_amount DESC
    LIMIT 10
";

$upiStmt = $db->prepare($upiSql);
$upiStmt->execute($params);
$upiStats = $upiStmt->fetchAll();

// Get agents for filter
$agents = [];
if ($user_role == 'superadmin' || $user_role == 'admin') {
    $agents = $db->query("SELECT agent_id, username, full_name FROM agents WHERE status = 'active' ORDER BY full_name")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - NimCredit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-2px); }
        .chart-container { position: relative; height: 300px; width: 100%; }
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
                        <h1 class="text-2xl font-bold text-gray-800">Reports & Analytics</h1>
                        <p class="text-gray-600">Detailed analytics and performance reports</p>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="exportToPDF()" class="bg-red-500 text-white px-6 py-2 rounded-lg hover:bg-red-600 font-medium">
                            <i class="fas fa-file-pdf mr-2"></i> Export PDF
                        </button>
                        <button onclick="exportToCSV()" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 font-medium">
                            <i class="fas fa-file-csv mr-2"></i> Export CSV
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <?php if ($user_role == 'superadmin' || $user_role == 'admin'): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Agent</label>
                        <select name="agent_id" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                            <option value="">All Agents</option>
                            <?php foreach ($agents as $agent): ?>
                            <option value="<?php echo $agent['agent_id']; ?>" <?php echo ($agent_id == $agent['agent_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($agent['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 font-medium">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                    </div>
                </form>
                
                <!-- Date Quick Select -->
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="?date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?><?php echo $agent_id ? "&agent_id=$agent_id" : ''; ?>" 
                       class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200">
                        Today
                    </a>
                    <a href="?date_from=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&date_to=<?php echo date('Y-m-d'); ?><?php echo $agent_id ? "&agent_id=$agent_id" : ''; ?>" 
                       class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        Last 7 Days
                    </a>
                    <a href="?date_from=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&date_to=<?php echo date('Y-m-d'); ?><?php echo $agent_id ? "&agent_id=$agent_id" : ''; ?>" 
                       class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        Last 30 Days
                    </a>
                    <a href="?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-t'); ?><?php echo $agent_id ? "&agent_id=$agent_id" : ''; ?>" 
                       class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        This Month
                    </a>
                    <a href="?date_from=<?php echo date('Y-m-d', strtotime('first day of last month')); ?>&date_to=<?php echo date('Y-m-d', strtotime('last day of last month')); ?><?php echo $agent_id ? "&agent_id=$agent_id" : ''; ?>" 
                       class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        Last Month
                    </a>
                </div>
            </div>
            
            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-2xl p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100">Total Revenue</p>
                            <h3 class="text-3xl font-bold mt-2">₹<?php echo number_format($summary['total_amount'], 2); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-rupee-sign text-2xl"></i>
                        </div>
                    </div>
                    <p class="text-green-200 text-sm mt-2"><?php echo $summary['successful_payments']; ?> successful payments</p>
                </div>
                
                <div class="stat-card bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-2xl p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100">Total Payments</p>
                            <h3 class="text-3xl font-bold mt-2"><?php echo number_format($summary['total_payments']); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-credit-card text-2xl"></i>
                        </div>
                    </div>
                    <p class="text-blue-200 text-sm mt-2">
                        <?php echo $summary['successful_payments']; ?> success • 
                        <?php echo $summary['pending_payments']; ?> pending • 
                        <?php echo $summary['failed_payments']; ?> failed
                    </p>
                </div>
                
                <div class="stat-card bg-gradient-to-r from-purple-500 to-pink-600 text-white rounded-2xl p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100">Average Amount</p>
                            <h3 class="text-3xl font-bold mt-2">₹<?php echo number_format($summary['avg_amount'], 2); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-chart-bar text-2xl"></i>
                        </div>
                    </div>
                    <p class="text-purple-200 text-sm mt-2">Per successful payment</p>
                </div>
                
                <div class="stat-card bg-gradient-to-r from-orange-500 to-red-600 text-white rounded-2xl p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100">Success Rate</p>
                            <h3 class="text-3xl font-bold mt-2">
                                <?php echo $summary['total_payments'] > 0 ? number_format(($summary['successful_payments'] / $summary['total_payments']) * 100, 1) : 0; ?>%
                            </h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-percentage text-2xl"></i>
                        </div>
                    </div>
                    <p class="text-orange-200 text-sm mt-2">
                        <?php echo $summary['unique_customers']; ?> unique customers • 
                        <?php echo $summary['active_agents']; ?> active agents
                    </p>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Revenue Trend Chart -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-6">Revenue Trend</h3>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                
                <!-- Payment Status Chart -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-6">Payment Status Distribution</h3>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Agent Performance -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                <h3 class="text-lg font-semibold mb-6">Agent Performance</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agent</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Payments</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Successful</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Success Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($agentStats as $agent): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-medium"><?php echo htmlspecialchars($agent['full_name']); ?></div>
                                    <div class="text-sm text-gray-500">@<?php echo $agent['username']; ?></div>
                                    <div class="text-xs text-gray-400"><?php echo ucfirst($agent['role']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold"><?php echo number_format($agent['total_payments']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-green-600"><?php echo number_format($agent['successful_payments']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold">₹<?php echo number_format($agent['total_amount'], 2); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div>₹<?php echo number_format($agent['avg_amount'], 2); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-24 bg-gray-200 rounded-full h-2 mr-3">
                                            <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo min($agent['success_rate'], 100); ?>%"></div>
                                        </div>
                                        <span class="font-bold"><?php echo number_format($agent['success_rate'], 1); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($agentStats)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-users text-4xl mb-4 text-gray-300"></i>
                                    <p class="text-lg">No agent data found for the selected period</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- UPI Performance -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-6">UPI Performance</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UPI Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Payments</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Successful</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Success Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($upiStats as $upi): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-mono text-sm"><?php echo htmlspecialchars($upi['upi_id']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold"><?php echo number_format($upi['total_payments']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-green-600"><?php echo number_format($upi['successful_payments']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold">₹<?php echo number_format($upi['total_amount'], 2); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-24 bg-gray-200 rounded-full h-2 mr-3">
                                            <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo min($upi['success_rate'], 100); ?>%"></div>
                                        </div>
                                        <span class="font-bold"><?php echo number_format($upi['success_rate'], 1); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($upiStats)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-wallet text-4xl mb-4 text-gray-300"></i>
                                    <p class="text-lg">No UPI data found for the selected period</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Revenue Chart Data
        const revenueDates = [<?php foreach($dailyStats as $stat) { echo '"' . date('d M', strtotime($stat['date'])) . '", '; } ?>];
        const revenueAmounts = [<?php foreach($dailyStats as $stat) { echo $stat['total_amount'] . ', '; } ?>];
        const paymentCounts = [<?php foreach($dailyStats as $stat) { echo $stat['payment_count'] . ', '; } ?>];
        
        // Status Data
        const statusData = {
            success: <?php echo $summary['successful_payments']; ?>,
            pending: <?php echo $summary['pending_payments']; ?>,
            failed: <?php echo $summary['failed_payments']; ?>
        };
        
        // Initialize charts when page loads
        window.onload = function() {
            // Revenue Trend Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: revenueDates,
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: revenueAmounts,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Payments Count',
                        data: paymentCounts,
                        borderColor: 'rgb(139, 92, 246)',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Revenue (₹)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Payment Count'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
            
            // Status Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Success', 'Pending', 'Failed'],
                    datasets: [{
                        data: [statusData.success, statusData.pending, statusData.failed],
                        backgroundColor: [
                            'rgb(34, 197, 94)',
                            'rgb(249, 115, 22)',
                            'rgb(239, 68, 68)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    const total = statusData.success + statusData.pending + statusData.failed;
                                    const percentage = Math.round((context.raw / total) * 100);
                                    label += context.raw + ' (' + percentage + '%)';
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        };
        
        function exportToPDF() {
            alert('PDF export feature would be implemented here');
            // In production, this would generate a PDF report
        }
        
        function exportToCSV() {
            // Get current filters
            const params = new URLSearchParams(window.location.search);
            window.location.href = 'export-report.php?' + params.toString();
        }
        
        // Auto-refresh every 5 minutes
        setInterval(() => {
            if (!document.hidden) {
                window.location.reload();
            }
        }, 300000);
    </script>
</body>
</html>
<?php ob_end_flush(); ?>