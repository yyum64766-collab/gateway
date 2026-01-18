<?php
// links.php - Manage all payment links
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

// Get all links with filters
function getAllLinks($pdo, $filters = []) {
    $where = [];
    $params = [];
    
    // Apply filters
    if (!empty($filters['status'])) {
        $where[] = "lg.link_status = :status";
        $params[':status'] = $filters['status'];
    }
    
    if (!empty($filters['agent_id'])) {
        $where[] = "lg.agent_id = :agent_id";
        $params[':agent_id'] = $filters['agent_id'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(lg.customer_name LIKE :search OR lg.customer_phone LIKE :search OR lg.link_id LIKE :search)";
        $params[':search'] = "%" . $filters['search'] . "%";
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = "DATE(lg.generated_at) >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "DATE(lg.generated_at) <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    
    // Build WHERE clause
    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM link_generations lg $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Pagination
    $page = $filters['page'] ?? 1;
    $perPage = $filters['per_page'] ?? 20;
    $offset = ($page - 1) * $perPage;
    
    // Get links
    $sql = "SELECT lg.*, a.full_name as agent_name, a.username as agent_username,
                   (SELECT COUNT(*) FROM link_clicks WHERE link_id = lg.link_id) as click_count,
                   (SELECT COUNT(*) FROM payments WHERE link_id = lg.link_id AND payment_status = 'success') as payment_count,
                   (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE link_id = lg.link_id AND payment_status = 'success') as amount_collected
            FROM link_generations lg
            LEFT JOIN agents a ON lg.agent_id = a.agent_id
            $whereClause
            ORDER BY lg.generated_at DESC
            LIMIT :offset, :per_page";
    
    $stmt = $pdo->prepare($sql);
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
    $links = $stmt->fetchAll();
    
    return [
        'links' => $links,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($total / $perPage)
    ];
}

// Get agents for filter dropdown
function getAgents($pdo) {
    $sql = "SELECT agent_id, username, full_name FROM agents WHERE status = 'active' ORDER BY full_name";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

// Get link statistics
function getLinkStats($pdo) {
    $stats = [];
    
    // Total links
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_generations");
    $stats['total_links'] = $stmt->fetch()['total'] ?? 0;
    
    // Active links
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_generations WHERE link_status = 'active' AND expiry_time > NOW()");
    $stats['active_links'] = $stmt->fetch()['total'] ?? 0;
    
    // Expired links
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_generations WHERE link_status = 'active' AND expiry_time <= NOW()");
    $stats['expired_links'] = $stmt->fetch()['total'] ?? 0;
    
    // Used links
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_generations WHERE link_status = 'used'");
    $stats['used_links'] = $stmt->fetch()['total'] ?? 0;
    
    // Today's links
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_generations WHERE DATE(generated_at) = CURDATE()");
    $stats['today_links'] = $stmt->fetch()['total'] ?? 0;
    
    // Total clicks
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM link_clicks");
    $stats['total_clicks'] = $stmt->fetch()['total'] ?? 0;
    
    // Total collected amount
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount_collected), 0) as total FROM link_generations");
    $stats['total_collected'] = number_format($stmt->fetch()['total'] ?? 0, 2);
    
    return $stats;
}

// Main Execution
$db = connectDB();
checkLogin();
$user = getUserInfo($db, $_SESSION['user_id']);

// Handle filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'agent_id' => $_GET['agent_id'] ?? '',
    'search' => $_GET['search'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'page' => $_GET['page'] ?? 1,
    'per_page' => 20
];

// If agent, only show their links
if ($user['role'] == 'agent') {
    $filters['agent_id'] = $user['agent_id'];
}

// Get data
$agents = getAgents($db);
$linksData = getAllLinks($db, $filters);
$stats = getLinkStats($db);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $link_id = $_POST['link_id'] ?? '';
    
    if ($action === 'delete' && !empty($link_id)) {
        // Check permissions
        if ($user['role'] == 'superadmin' || $user['role'] == 'admin') {
            $stmt = $db->prepare("DELETE FROM link_generations WHERE link_id = ?");
            $stmt->execute([$link_id]);
            
            // Log activity
            $logStmt = $db->prepare("INSERT INTO activity_logs (agent_id, activity_type, description) VALUES (?, 'link_deleted', ?)");
            $logStmt->execute([$user['agent_id'], "Deleted link: $link_id"]);
            
            $_SESSION['success'] = "Link deleted successfully";
            header("Location: links.php");
            exit();
        }
    }
    
    if ($action === 'expire_now' && !empty($link_id)) {
        $stmt = $db->prepare("UPDATE link_generations SET expiry_time = NOW(), link_status = 'expired' WHERE link_id = ?");
        $stmt->execute([$link_id]);
        
        // Log activity
        $logStmt = $db->prepare("INSERT INTO activity_logs (agent_id, activity_type, description) VALUES (?, 'link_expired', ?)");
        $logStmt->execute([$user['agent_id'], "Manually expired link: $link_id"]);
        
        $_SESSION['success'] = "Link expired successfully";
        header("Location: links.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Links - NimCredit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-2px); }
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-expired { background: #fee2e2; color: #991b1b; }
        .badge-used { background: #dbeafe; color: #1e40af; }
        .badge-cancelled { background: #f3f4f6; color: #4b5563; }
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
                        <h1 class="text-2xl font-bold text-gray-800">Payment Links</h1>
                        <p class="text-gray-600">Manage all generated payment links</p>
                    </div>
                    <a href="generate-link.php" class="bg-gradient-to-r from-blue-500 to-purple-500 text-white px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition">
                        <i class="fas fa-plus mr-2"></i> Generate New Link
                    </a>
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
                <div class="stat-card bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Total Links</p>
                            <h3 class="text-2xl font-bold mt-1"><?php echo number_format($stats['total_links']); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                            <i class="fas fa-link text-xl"></i>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2"><?php echo $stats['today_links']; ?> created today</p>
                </div>
                
                <div class="stat-card bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Active Links</p>
                            <h3 class="text-2xl font-bold mt-1"><?php echo number_format($stats['active_links']); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-green-50 text-green-600 flex items-center justify-center">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2"><?php echo number_format($stats['expired_links']); ?> expired</p>
                </div>
                
                <div class="stat-card bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Total Clicks</p>
                            <h3 class="text-2xl font-bold mt-1"><?php echo number_format($stats['total_clicks']); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center">
                            <i class="fas fa-mouse-pointer text-xl"></i>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">All time clicks</p>
                </div>
                
                <div class="stat-card bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Amount Collected</p>
                            <h3 class="text-2xl font-bold mt-1">₹<?php echo $stats['total_collected']; ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center">
                            <i class="fas fa-rupee-sign text-xl"></i>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">From successful payments</p>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                            <option value="">All Status</option>
                            <option value="active" <?php echo ($filters['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="expired" <?php echo ($filters['status'] == 'expired') ? 'selected' : ''; ?>>Expired</option>
                            <option value="used" <?php echo ($filters['status'] == 'used') ? 'selected' : ''; ?>>Used</option>
                            <option value="cancelled" <?php echo ($filters['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <?php if ($user['role'] == 'superadmin' || $user['role'] == 'admin'): ?>
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
                                   placeholder="Search by customer name, phone or link ID..." 
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
                        <a href="links.php" class="w-full bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 font-medium text-center">
                            Clear
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Links Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h2 class="text-lg font-semibold">All Links (<?php echo number_format($linksData['total']); ?>)</h2>
                        <div class="text-sm text-gray-600">
                            Page <?php echo $linksData['page']; ?> of <?php echo $linksData['total_pages']; ?>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Link ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agent</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clicks/Payments</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($linksData['links'] as $link): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-mono text-sm text-blue-600"><?php echo substr($link['link_id'], 0, 12) . '...'; ?></div>
                                    <div class="text-xs text-gray-500 mt-1"><?php echo $link['reference_number'] ?? 'No ref'; ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium"><?php echo htmlspecialchars($link['customer_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo $link['customer_phone']; ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold">₹<?php echo number_format($link['amount'], 2); ?></div>
                                    <?php if ($link['amount_collected'] > 0): ?>
                                    <div class="text-xs text-green-600">₹<?php echo number_format($link['amount_collected'], 2); ?> collected</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm"><?php echo htmlspecialchars($link['agent_name']); ?></div>
                                    <div class="text-xs text-gray-500">@<?php echo $link['agent_username']; ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php 
                                    $status = $link['link_status'];
                                    $isExpired = strtotime($link['expiry_time']) < time() && $status == 'active';
                                    
                                    if ($isExpired) {
                                        $status = 'expired';
                                    }
                                    
                                    $badgeClasses = [
                                        'active' => 'badge-active',
                                        'expired' => 'badge-expired',
                                        'used' => 'badge-used',
                                        'cancelled' => 'badge-cancelled'
                                    ];
                                    $statusText = [
                                        'active' => 'Active',
                                        'expired' => 'Expired',
                                        'used' => 'Used',
                                        'cancelled' => 'Cancelled'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $badgeClasses[$status] ?? 'badge-cancelled'; ?>">
                                        <?php echo $statusText[$status] ?? ucfirst($status); ?>
                                    </span>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php echo date('d M, h:i A', strtotime($link['expiry_time'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                        <span class="font-medium"><?php echo $link['click_count']; ?></span> clicks
                                    </div>
                                    <div class="text-sm">
                                        <span class="font-medium"><?php echo $link['payment_count']; ?></span> payments
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm"><?php echo date('d M Y', strtotime($link['generated_at'])); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($link['generated_at'])); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <a href="view-link.php?id=<?php echo $link['link_id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo $link['link_url']; ?>" target="_blank"
                                           class="text-green-600 hover:text-green-800" title="Open Link">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        <button onclick="copyLink('<?php echo $link['link_url']; ?>')"
                                                class="text-purple-600 hover:text-purple-800" title="Copy Link">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <?php if ($user['role'] == 'superadmin' || $user['role'] == 'admin'): ?>
                                        <?php if ($status == 'active'): ?>
                                        <button onclick="expireLink('<?php echo $link['link_id']; ?>')"
                                                class="text-orange-600 hover:text-orange-800" title="Expire Now">
                                            <i class="fas fa-clock"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button onclick="deleteLink('<?php echo $link['link_id']; ?>')"
                                                class="text-red-600 hover:text-red-800" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($linksData['links'])): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-link text-4xl mb-4 text-gray-300"></i>
                                    <p class="text-lg">No links found</p>
                                    <p class="text-sm mt-2">Try adjusting your filters or generate a new link</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($linksData['total_pages'] > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-700">
                            Showing <?php echo (($linksData['page'] - 1) * $linksData['per_page']) + 1; ?> to 
                            <?php echo min($linksData['page'] * $linksData['per_page'], $linksData['total']); ?> of 
                            <?php echo number_format($linksData['total']); ?> links
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($linksData['page'] > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $linksData['page'] - 1])); ?>" 
                               class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                                <i class="fas fa-chevron-left mr-2"></i> Previous
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($linksData['page'] < $linksData['total_pages']): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $linksData['page'] + 1])); ?>" 
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
    
    <!-- Confirmation Modal -->
    <div id="confirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold mb-4" id="modalTitle"></h3>
            <p class="text-gray-600 mb-6" id="modalMessage"></p>
            <div class="flex justify-end space-x-3">
                <button onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button onclick="confirmAction()" id="confirmBtn" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">
                    Confirm
                </button>
            </div>
        </div>
    </div>
    
    <script>
        let currentAction = '';
        let currentLinkId = '';
        
        function copyLink(url) {
            navigator.clipboard.writeText(url).then(() => {
                alert('Link copied to clipboard!');
            });
        }
        
        function deleteLink(linkId) {
            currentAction = 'delete';
            currentLinkId = linkId;
            document.getElementById('modalTitle').textContent = 'Delete Link';
            document.getElementById('modalMessage').textContent = 'Are you sure you want to delete this link? This action cannot be undone.';
            document.getElementById('confirmBtn').className = 'px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600';
            document.getElementById('confirmModal').classList.remove('hidden');
            document.getElementById('confirmModal').classList.add('flex');
        }
        
        function expireLink(linkId) {
            currentAction = 'expire';
            currentLinkId = linkId;
            document.getElementById('modalTitle').textContent = 'Expire Link Now';
            document.getElementById('modalMessage').textContent = 'Are you sure you want to expire this link immediately?';
            document.getElementById('confirmBtn').className = 'px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600';
            document.getElementById('confirmModal').classList.remove('hidden');
            document.getElementById('confirmModal').classList.add('flex');
        }
        
        function closeModal() {
            document.getElementById('confirmModal').classList.add('hidden');
            document.getElementById('confirmModal').classList.remove('flex');
            currentAction = '';
            currentLinkId = '';
        }
        
        function confirmAction() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'links.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = currentAction === 'delete' ? 'delete' : 'expire_now';
            form.appendChild(actionInput);
            
            const linkInput = document.createElement('input');
            linkInput.type = 'hidden';
            linkInput.name = 'link_id';
            linkInput.value = currentLinkId;
            form.appendChild(linkInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Export to CSV
        function exportToCSV() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = 'export-links.php?' + params.toString();
        }
        
        // Auto-refresh every 60 seconds
        setInterval(() => {
            if (!document.hidden) {
                window.location.reload();
            }
        }, 60000);
    </script>
</body>
</html>
<?php ob_end_flush(); ?>