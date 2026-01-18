<?php
// payments.php - Manage all payments
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

// Main execution
$db = connectDB();
checkLogin();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'agent_id' => $_GET['agent_id'] ?? '',
    'search' => $_GET['search'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'page' => $_GET['page'] ?? 1,
    'per_page' => 20
];

// If agent, only show their payments
if ($user_role == 'agent') {
    $filters['agent_id'] = $user_id;
}

// Build query
$where = [];
$params = [];

if (!empty($filters['status'])) {
    $where[] = "p.payment_status = :status";
    $params[':status'] = $filters['status'];
}

if (!empty($filters['agent_id'])) {
    $where[] = "p.agent_id = :agent_id";
    $params[':agent_id'] = $filters['agent_id'];
}

if (!empty($filters['search'])) {
    $where[] = "(p.customer_phone LIKE :search OR p.transaction_ref LIKE :search OR p.payment_id LIKE :search)";
    $params[':search'] = "%" . $filters['search'] . "%";
}

if (!empty($filters['date_from'])) {
    $where[] = "DATE(p.created_at) >= :date_from";
    $params[':date_from'] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $where[] = "DATE(p.created_at) <= :date_to";
    $params[':date_to'] = $filters['date_to'];
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$countSql = "SELECT COUNT(*) as total FROM payments p $whereClause";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetch()['total'];

// Pagination
$page = $filters['page'];
$perPage = $filters['per_page'];
$offset = ($page - 1) * $perPage;
$totalPages = ceil($total / $perPage);

// Get payments
$sql = "SELECT p.*, a.full_name as agent_name, lg.customer_name, lg.link_id
        FROM payments p
        LEFT JOIN agents a ON p.agent_id = a.agent_id
        LEFT JOIN link_generations lg ON p.link_id = lg.link_id
        $whereClause
        ORDER BY p.created_at DESC
        LIMIT :offset, :per_page";

$stmt = $db->prepare($sql);
$params[':offset'] = $offset;
$params[':per_page'] = $perPage;

foreach ($params as $key => $value) {
    if (is_int($value)) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
}

$stmt->execute();
$payments = $stmt->fetchAll();

// Get agents for filter
$agents = [];
if ($user_role == 'superadmin' || $user_role == 'admin') {
    $agents = $db->query("SELECT agent_id, username, full_name FROM agents WHERE status = 'active' ORDER BY full_name")->fetchAll();
}

// Get payment statistics
function getPaymentStats($db, $user_id, $user_role) {
    $stats = [];
    
    // Total payments
    $sql = "SELECT COUNT(*) as total FROM payments";
    if ($user_role == 'agent') {
        $sql .= " WHERE agent_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id]);
    } else {
        $stmt = $db->query($sql);
    }
    $stats['total_payments'] = $stmt->fetch()['total'] ?? 0;
    
    // Total amount
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_status = 'success'";
    if ($user_role == 'agent') {
        $sql .= " AND agent_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id]);
    } else {
        $stmt = $db->query($sql);
    }
    $stats['total_amount'] = number_format($stmt->fetch()['total'] ?? 0, 2);
    
    // Today's revenue
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_status = 'success' AND DATE(created_at) = CURDATE()";
    if ($user_role == 'agent') {
        $sql .= " AND agent_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id]);
    } else {
        $stmt = $db->query($sql);
    }
    $stats['today_revenue'] = number_format($stmt->fetch()['total'] ?? 0, 2);
    
    // Pending payments
    $sql = "SELECT COUNT(*) as total FROM payments WHERE payment_status = 'pending'";
    if ($user_role == 'agent') {
        $sql .= " AND agent_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id]);
    } else {
        $stmt = $db->query($sql);
    }
    $stats['pending_payments'] = $stmt->fetch()['total'] ?? 0;
    
    // Success rate
    $sql = "SELECT 
            SUM(CASE WHEN payment_status = 'success' THEN 1 ELSE 0 END) as successful,
            COUNT(*) as total
            FROM payments";
    if ($user_role == 'agent') {
        $sql .= " WHERE agent_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id]);
    } else {
        $stmt = $db->query($sql);
    }
    $result = $stmt->fetch();
    $successful = $result['successful'] ?? 0;
    $total = $result['total'] ?? 0;
    $stats['success_rate'] = $total > 0 ? round(($successful / $total) * 100, 1) : 0;
    
    return $stats;
}

$stats = getPaymentStats($db, $user_id, $user_role);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $payment_id = $_POST['payment_id'] ?? '';
    
    if ($action === 'update_status' && !empty($payment_id)) {
        $new_status = $_POST['status'] ?? '';
        
        if (in_array($new_status, ['success', 'failed', 'refunded'])) {
            $stmt = $db->prepare("UPDATE payments SET payment_status = ?, updated_at = NOW() WHERE payment_id = ?");
            $stmt->execute([$new_status, $payment_id]);
            
            // Log activity
            $logStmt = $db->prepare("INSERT INTO activity_logs (agent_id, activity_type, description) VALUES (?, 'payment_updated', ?)");
            $logStmt->execute([$user_id, "Updated payment $payment_id to $new_status"]);
            
            $_SESSION['success'] = "Payment status updated successfully";
            header("Location: payments.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - NimCredit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-card { transition: all 0.3s ease; }
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-failed { background: #fee2e2; color: #991b1b; }
        .badge-processing { background: #dbeafe; color: #1e40af; }
        .badge-expired { background: #f3f4f6; color: #4b5563; }
        .badge-refunded { background: #ede9fe; color: #5b21b6; }
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
                        <h1 class="text-2xl font-bold text-gray-800">Payments</h1>
                        <p class="text-gray-600">View and manage all payments</p>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="exportToCSV()" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 font-medium">
                            <i class="fas fa-download mr-2"></i> Export
                        </button>
                        <a href="generate-link.php" class="bg-gradient-to-r from-blue-500 to-purple-500 text-white px-6 py-2 rounded-lg font-semibold hover:opacity-90">
                            <i class="fas fa-plus mr-2"></i> New Payment
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Success Message -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <span class="text-green-700"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-2xl p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100">Total Revenue</p>
                            <h3 class="text-3xl font-bold mt-2">₹<?php echo $stats['total_amount']; ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-rupee-sign text-2xl"></i>
                        </div>
                    </div>
                    <p class="text-green-200 text-sm mt-2"><?php echo $stats['total_payments']; ?> total payments</p>
                </div>
                
                <div class="stat-card bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-2xl p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100">Today's Revenue</p>
                            <h3 class="text-3xl font-bold mt-2">₹<?php echo $stats['today_revenue']; ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-calendar-day text-2xl"></i>
                        </div>
                    </div>
                    <p class="text-blue-200 text-sm mt-2">Today's collection</p>
                </div>
                
                <div class="stat-card bg-gradient-to-r from-orange-500 to-red-600 text-white rounded-2xl p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100">Pending Payments</p>
                            <h3 class="text-3xl font-bold mt-2"><?php echo number_format($stats['pending_payments']); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-clock text-2xl"></i>
                        </div>
                    </div>
                    <p class="text-orange-200 text-sm mt-2">Awaiting confirmation</p>
                </div>
                
                <div class="stat-card bg-gradient-to-r from-purple-500 to-pink-600 text-white rounded-2xl p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100">Success Rate</p>
                            <h3 class="text-3xl font-bold mt-2"><?php echo $stats['success_rate']; ?>%</h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-chart-line text-2xl"></i>
                        </div>
                    </div>
                    <p class="text-purple-200 text-sm mt-2">Payment success ratio</p>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                            <option value="">All Status</option>
                            <option value="success" <?php echo ($filters['status'] == 'success') ? 'selected' : ''; ?>>Success</option>
                            <option value="pending" <?php echo ($filters['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo ($filters['status'] == 'processing') ? 'selected' : ''; ?>>Processing</option>
                            <option value="failed" <?php echo ($filters['status'] == 'failed') ? 'selected' : ''; ?>>Failed</option>
                            <option value="expired" <?php echo ($filters['status'] == 'expired') ? 'selected' : ''; ?>>Expired</option>
                            <option value="refunded" <?php echo ($filters['status'] == 'refunded') ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                    
                    <?php if ($user_role == 'superadmin' || $user_role == 'admin'): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Agent</label>
                        <select name="agent_id" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                            <option value="">All Agents</option>
                            <?php foreach ($agents as $agent): ?>
                            <option value="<?php echo $agent['agent_id']; ?>" <?php echo ($filters['agent_id'] == $agent['agent_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($agent['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                        <input type="date" name="date_from" value="<?php echo $filters['date_from']; ?>" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                        <input type="date" name="date_to" value="<?php echo $filters['date_to']; ?>" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <div class="flex">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                   placeholder="Search by phone, transaction ID or payment ID..." 
                                   class="flex-1 border border-gray-300 rounded-l-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                            <button type="submit" class="bg-blue-500 text-white px-6 rounded-r-lg hover:bg-blue-600">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="w-full bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 font-medium">
                            Apply Filters
                        </button>
                        <a href="payments.php" class="w-full bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 font-medium text-center">
                            Clear
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Payments Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h2 class="text-lg font-semibold">All Payments (<?php echo number_format($total); ?>)</h2>
                        <div class="text-sm text-gray-600">
                            Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agent</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($payments as $payment): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-mono text-sm text-blue-600"><?php echo substr($payment['payment_id'], 0, 12) . '...'; ?></div>
                                    <div class="text-xs text-gray-500">Ref: <?php echo $payment['transaction_ref'] ?? 'N/A'; ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium"><?php echo htmlspecialchars($payment['customer_name'] ?? $payment['customer_phone']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo $payment['customer_phone']; ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-lg">₹<?php echo number_format($payment['amount'], 2); ?></div>
                                    <?php if ($payment['upi_id']): ?>
                                    <div class="text-xs text-gray-500">UPI: <?php echo substr($payment['upi_id'], 0, 15) . '...'; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm"><?php echo htmlspecialchars($payment['agent_name']); ?></div>
                                    <?php if ($payment['link_id']): ?>
                                    <div class="text-xs text-gray-500">Link: <?php echo substr($payment['link_id'], 0, 8) . '...'; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php 
                                    $status = $payment['payment_status'];
                                    $badgeClasses = [
                                        'success' => 'badge-success',
                                        'pending' => 'badge-pending',
                                        'processing' => 'badge-processing',
                                        'failed' => 'badge-failed',
                                        'expired' => 'badge-expired',
                                        'refunded' => 'badge-refunded'
                                    ];
                                    $statusText = [
                                        'success' => 'Success',
                                        'pending' => 'Pending',
                                        'processing' => 'Processing',
                                        'failed' => 'Failed',
                                        'expired' => 'Expired',
                                        'refunded' => 'Refunded'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $badgeClasses[$status] ?? 'badge-pending'; ?>">
                                        <?php echo $statusText[$status] ?? ucfirst($status); ?>
                                    </span>
                                    <?php if ($payment['expiry_time'] && strtotime($payment['expiry_time']) < time() && $status == 'pending'): ?>
                                    <div class="text-xs text-red-500 mt-1">Expired</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($payment['transaction_id']): ?>
                                    <div class="font-mono text-sm"><?php echo substr($payment['transaction_id'], 0, 12) . '...'; ?></div>
                                    <?php endif; ?>
                                    <?php if ($payment['bank_reference']): ?>
                                    <div class="text-xs text-gray-500">Bank: <?php echo substr($payment['bank_reference'], 0, 10) . '...'; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm"><?php echo date('d M Y', strtotime($payment['created_at'])); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($payment['created_at'])); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <button onclick="viewPayment('<?php echo $payment['payment_id']; ?>')"
                                                class="text-blue-600 hover:text-blue-800" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($user_role == 'superadmin' || $user_role == 'admin'): ?>
                                        <?php if ($payment['payment_status'] == 'pending'): ?>
                                        <button onclick="markSuccess('<?php echo $payment['payment_id']; ?>')"
                                                class="text-green-600 hover:text-green-800" title="Mark as Success">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button onclick="markFailed('<?php echo $payment['payment_id']; ?>')"
                                                class="text-red-600 hover:text-red-800" title="Mark as Failed">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($payment['payment_status'] == 'success'): ?>
                                        <button onclick="markRefunded('<?php echo $payment['payment_id']; ?>')"
                                                class="text-purple-600 hover:text-purple-800" title="Mark as Refunded">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-receipt text-4xl mb-4 text-gray-300"></i>
                                    <p class="text-lg">No payments found</p>
                                    <p class="text-sm mt-2">Try adjusting your filters or generate a new payment link</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-700">
                            Showing <?php echo (($page - 1) * $perPage) + 1; ?> to 
                            <?php echo min($page * $perPage, $total); ?> of 
                            <?php echo number_format($total); ?> payments
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                               class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                                <i class="fas fa-chevron-left mr-2"></i> Previous
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                               class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                                Next <i class="fas fa-chevron-right ml-2"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Update Status Modal -->
    <div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold mb-4" id="modalTitle">Update Payment Status</h3>
            <form id="statusForm" method="POST">
                <input type="hidden" name="payment_id" id="paymentId">
                <input type="hidden" name="action" value="update_status">
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Status</label>
                    <select name="status" id="statusSelect" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                        <option value="success">Success</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                    <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none" placeholder="Add any notes..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Payment Modal -->
    <div id="viewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-2xl w-full mx-4 my-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold">Payment Details</h3>
                <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="paymentDetails"></div>
        </div>
    </div>
    
    <script>
        let currentPaymentId = '';
        
        function viewPayment(paymentId) {
            currentPaymentId = paymentId;
            
            // Fetch payment details via AJAX
            fetch(`get-payment-details.php?id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const payment = data.payment;
                        let html = `
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm text-gray-600">Payment ID</label>
                                        <div class="font-mono font-medium">${payment.payment_id}</div>
                                    </div>
                                    <div>
                                        <label class="text-sm text-gray-600">Transaction Ref</label>
                                        <div class="font-medium">${payment.transaction_ref || 'N/A'}</div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm text-gray-600">Customer</label>
                                        <div class="font-medium">${payment.customer_name || payment.customer_phone}</div>
                                        <div class="text-sm text-gray-600">${payment.customer_phone}</div>
                                    </div>
                                    <div>
                                        <label class="text-sm text-gray-600">Agent</label>
                                        <div class="font-medium">${payment.agent_name || 'N/A'}</div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm text-gray-600">Amount</label>
                                        <div class="text-2xl font-bold text-green-600">₹${parseFloat(payment.amount).toFixed(2)}</div>
                                    </div>
                                    <div>
                                        <label class="text-sm text-gray-600">Status</label>
                                        <div>
                                            <span class="badge ${getStatusClass(payment.payment_status)}">
                                                ${getStatusText(payment.payment_status)}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm text-gray-600">UPI ID</label>
                                        <div class="font-mono text-sm">${payment.upi_id || 'N/A'}</div>
                                    </div>
                                    <div>
                                        <label class="text-sm text-gray-600">Transaction ID</label>
                                        <div class="font-mono text-sm">${payment.transaction_id || 'N/A'}</div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm text-gray-600">Created At</label>
                                        <div>${formatDateTime(payment.created_at)}</div>
                                    </div>
                                    <div>
                                        <label class="text-sm text-gray-600">Expiry Time</label>
                                        <div>${formatDateTime(payment.expiry_time)}</div>
                                    </div>
                                </div>
                                
                                ${payment.bank_reference ? `
                                <div>
                                    <label class="text-sm text-gray-600">Bank Reference</label>
                                    <div class="font-mono text-sm">${payment.bank_reference}</div>
                                </div>
                                ` : ''}
                                
                                ${payment.notes ? `
                                <div>
                                    <label class="text-sm text-gray-600">Notes</label>
                                    <div class="bg-gray-50 p-3 rounded">${payment.notes}</div>
                                </div>
                                ` : ''}
                            </div>
                        `;
                        
                        document.getElementById('paymentDetails').innerHTML = html;
                        document.getElementById('viewModal').classList.remove('hidden');
                        document.getElementById('viewModal').classList.add('flex');
                    }
                });
        }
        
        function getStatusClass(status) {
            const classes = {
                'success': 'badge-success',
                'pending': 'badge-pending',
                'processing': 'badge-processing',
                'failed': 'badge-failed',
                'expired': 'badge-expired',
                'refunded': 'badge-refunded'
            };
            return classes[status] || 'badge-pending';
        }
        
        function getStatusText(status) {
            const texts = {
                'success': 'Success',
                'pending': 'Pending',
                'processing': 'Processing',
                'failed': 'Failed',
                'expired': 'Expired',
                'refunded': 'Refunded'
            };
            return texts[status] || status;
        }
        
        function formatDateTime(datetime) {
            if (!datetime) return 'N/A';
            const date = new Date(datetime);
            return date.toLocaleDateString('en-IN') + ' ' + date.toLocaleTimeString('en-IN');
        }
        
        function markSuccess(paymentId) {
            currentPaymentId = paymentId;
            document.getElementById('paymentId').value = paymentId;
            document.getElementById('statusSelect').value = 'success';
            document.getElementById('modalTitle').textContent = 'Mark as Success';
            document.getElementById('statusModal').classList.remove('hidden');
            document.getElementById('statusModal').classList.add('flex');
        }
        
        function markFailed(paymentId) {
            currentPaymentId = paymentId;
            document.getElementById('paymentId').value = paymentId;
            document.getElementById('statusSelect').value = 'failed';
            document.getElementById('modalTitle').textContent = 'Mark as Failed';
            document.getElementById('statusModal').classList.remove('hidden');
            document.getElementById('statusModal').classList.add('flex');
        }
        
        function markRefunded(paymentId) {
            currentPaymentId = paymentId;
            document.getElementById('paymentId').value = paymentId;
            document.getElementById('statusSelect').value = 'refunded';
            document.getElementById('modalTitle').textContent = 'Mark as Refunded';
            document.getElementById('statusModal').classList.remove('hidden');
            document.getElementById('statusModal').classList.add('flex');
        }
        
        function closeModal() {
            document.getElementById('statusModal').classList.add('hidden');
            document.getElementById('statusModal').classList.remove('flex');
            currentPaymentId = '';
        }
        
        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
            document.getElementById('viewModal').classList.remove('flex');
        }
        
        function exportToCSV() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = 'export-payments.php?' + params.toString();
        }
        
        // Auto-refresh every 30 seconds
        setInterval(() => {
            if (!document.hidden) {
                window.location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
<?php ob_end_flush(); ?>