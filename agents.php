<?php
// agents.php - Manage agents
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

// Get all agents
$agents = $db->query("
    SELECT a.*, 
           (SELECT COUNT(*) FROM link_generations WHERE agent_id = a.agent_id) as total_links,
           (SELECT COUNT(*) FROM payments WHERE agent_id = a.agent_id AND payment_status = 'success') as successful_payments,
           (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE agent_id = a.agent_id AND payment_status = 'success') as total_collected,
           (SELECT full_name FROM agents WHERE agent_id = a.assigned_to) as assigned_admin
    FROM agents a
    WHERE a.role IN ('agent', 'admin')
    ORDER BY 
        CASE a.role 
            WHEN 'admin' THEN 1 
            WHEN 'agent' THEN 2 
            ELSE 3 
        END,
        a.full_name
")->fetchAll();

// Handle actions
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_agent') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'agent';
        $commission_rate = floatval($_POST['commission_rate'] ?? 5.0);
        $assigned_to = $_POST['assigned_to'] ?? null;
        
        // Validate
        if (empty($username) || empty($password) || empty($full_name)) {
            $error = 'Please fill all required fields';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif ($commission_rate < 0 || $commission_rate > 50) {
            $error = 'Commission rate must be between 0 and 50';
        } else {
            // Check if username exists
            $check = $db->prepare("SELECT COUNT(*) as count FROM agents WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()['count'] > 0) {
                $error = 'Username already exists';
            } else {
                // Insert agent
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    INSERT INTO agents (username, password_hash, full_name, email, phone, role, commission_rate, assigned_to, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $stmt->execute([$username, $password_hash, $full_name, $email, $phone, $role, $commission_rate, $assigned_to]);
                
                // Log activity
                $logStmt = $db->prepare("INSERT INTO activity_logs (agent_id, activity_type, description) VALUES (?, 'agent_added', ?)");
                $logStmt->execute([$user_id, "Added new agent: $full_name (@$username)"]);
                
                $success = 'Agent added successfully';
                header("Location: agents.php");
                exit();
            }
        }
    }
    
    if ($action === 'update_agent') {
        $agent_id = $_POST['agent_id'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $commission_rate = floatval($_POST['commission_rate'] ?? 5.0);
        $status = $_POST['status'] ?? 'active';
        $assigned_to = $_POST['assigned_to'] ?? null;
        
        if ($agent_id && $full_name) {
            $stmt = $db->prepare("
                UPDATE agents 
                SET full_name = ?, email = ?, phone = ?, commission_rate = ?, status = ?, assigned_to = ?, updated_at = NOW()
                WHERE agent_id = ?
            ");
            $stmt->execute([$full_name, $email, $phone, $commission_rate, $status, $assigned_to, $agent_id]);
            
            // Log activity
            $logStmt = $db->prepare("INSERT INTO activity_logs (agent_id, activity_type, description) VALUES (?, 'agent_updated', ?)");
            $logStmt->execute([$user_id, "Updated agent: $full_name (ID: $agent_id)"]);
            
            $success = 'Agent updated successfully';
            header("Location: agents.php");
            exit();
        }
    }
    
    if ($action === 'reset_password') {
        $agent_id = $_POST['agent_id'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        if ($agent_id && strlen($new_password) >= 6) {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE agents SET password_hash = ?, updated_at = NOW() WHERE agent_id = ?");
            $stmt->execute([$password_hash, $agent_id]);
            
            // Log activity
            $agent = $db->query("SELECT full_name FROM agents WHERE agent_id = $agent_id")->fetch();
            $logStmt = $db->prepare("INSERT INTO activity_logs (agent_id, activity_type, description) VALUES (?, 'password_reset', ?)");
            $logStmt->execute([$user_id, "Reset password for agent: {$agent['full_name']}"]);
            
            $success = 'Password reset successfully';
            header("Location: agents.php");
            exit();
        }
    }
    
    if ($action === 'delete_agent') {
        $agent_id = $_POST['agent_id'] ?? '';
        
        if ($agent_id && $user_role == 'superadmin') {
            // Check if agent has any activity
            $check = $db->query("SELECT COUNT(*) as count FROM link_generations WHERE agent_id = $agent_id")->fetch();
            if ($check['count'] > 0) {
                $error = 'Cannot delete agent with existing links. Deactivate instead.';
            } else {
                $stmt = $db->prepare("DELETE FROM agents WHERE agent_id = ?");
                $stmt->execute([$agent_id]);
                
                $success = 'Agent deleted successfully';
                header("Location: agents.php");
                exit();
            }
        }
    }
}

// Get admins for assignment
$admins = $db->query("SELECT agent_id, full_name FROM agents WHERE role = 'admin' AND status = 'active'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agents - NimCredit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-admin { background: #dbeafe; color: #1e40af; }
        .badge-agent { background: #d1fae5; color: #065f46; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #f3f4f6; color: #4b5563; }
        .badge-suspended { background: #fee2e2; color: #991b1b; }
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
                        <h1 class="text-2xl font-bold text-gray-800">Agents Management</h1>
                        <p class="text-gray-600">Manage agents and their permissions</p>
                    </div>
                    <button onclick="openAddAgentModal()" class="bg-gradient-to-r from-blue-500 to-purple-500 text-white px-6 py-3 rounded-lg font-semibold hover:opacity-90">
                        <i class="fas fa-user-plus mr-2"></i> Add New Agent
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
                $totalAgents = count(array_filter($agents, fn($a) => $a['role'] == 'agent'));
                $totalAdmins = count(array_filter($agents, fn($a) => $a['role'] == 'admin'));
                $activeAgents = count(array_filter($agents, fn($a) => $a['status'] == 'active'));
                $totalCommission = array_sum(array_column($agents, 'total_commission'));
                ?>
                
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Total Agents</p>
                            <h3 class="text-2xl font-bold mt-1"><?php echo number_format($totalAgents); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2"><?php echo number_format($totalAdmins); ?> admins</p>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Active Users</p>
                            <h3 class="text-2xl font-bold mt-1"><?php echo number_format($activeAgents); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-green-50 text-green-600 flex items-center justify-center">
                            <i class="fas fa-user-check text-xl"></i>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">Currently active</p>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Total Commission</p>
                            <h3 class="text-2xl font-bold mt-1">₹<?php echo number_format($totalCommission, 2); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center">
                            <i class="fas fa-rupee-sign text-xl"></i>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">All time commission</p>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Avg Commission Rate</p>
                            <h3 class="text-2xl font-bold mt-1">
                                <?php 
                                $rates = array_column($agents, 'commission_rate');
                                echo $rates ? number_format(array_sum($rates) / count($rates), 1) : 0;
                                ?>%
                            </h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center">
                            <i class="fas fa-percentage text-xl"></i>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">Average rate</p>
                </div>
            </div>
            
            <!-- Agents Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold">All Agents (<?php echo count($agents); ?>)</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agent</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role & Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($agents as $agent): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center text-white font-bold mr-3">
                                            <?php echo strtoupper(substr($agent['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($agent['full_name']); ?></div>
                                            <div class="text-sm text-gray-500">@<?php echo $agent['username']; ?></div>
                                            <div class="text-xs text-gray-400">ID: <?php echo $agent['agent_id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="space-y-1">
                                        <span class="badge <?php echo $agent['role'] == 'admin' ? 'badge-admin' : 'badge-agent'; ?>">
                                            <?php echo ucfirst($agent['role']); ?>
                                        </span>
                                        <span class="badge <?php echo $agent['status'] == 'active' ? 'badge-active' : ($agent['status'] == 'suspended' ? 'badge-suspended' : 'badge-inactive'); ?>">
                                            <?php echo ucfirst($agent['status']); ?>
                                        </span>
                                        <?php if ($agent['assigned_admin']): ?>
                                        <div class="text-xs text-gray-600 mt-1">
                                            Assigned to: <?php echo $agent['assigned_admin']; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                        <div class="flex items-center">
                                            <i class="fas fa-link text-gray-400 mr-2 text-xs"></i>
                                            <span class="font-medium"><?php echo $agent['total_links']; ?></span> links
                                        </div>
                                        <div class="flex items-center mt-1">
                                            <i class="fas fa-rupee-sign text-gray-400 mr-2 text-xs"></i>
                                            <span class="font-medium"><?php echo $agent['successful_payments']; ?></span> payments
                                        </div>
                                        <div class="text-xs text-gray-600 mt-1">
                                            ₹<?php echo number_format($agent['total_collected'], 2); ?> collected
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                        <div class="font-bold"><?php echo $agent['commission_rate']; ?>%</div>
                                        <div class="text-gray-600">₹<?php echo number_format($agent['total_commission'], 2); ?></div>
                                        <div class="text-xs text-gray-500">
                                            Since <?php echo date('M Y', strtotime($agent['created_at'])); ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($agent['last_login']): ?>
                                    <div class="text-sm"><?php echo date('d M', strtotime($agent['last_login'])); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($agent['last_login'])); ?></div>
                                    <?php else: ?>
                                    <span class="text-sm text-gray-400">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <button onclick="editAgent(<?php echo htmlspecialchars(json_encode($agent)); ?>)"
                                                class="text-blue-600 hover:text-blue-800" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="resetPassword(<?php echo $agent['agent_id']; ?>, '<?php echo $agent['full_name']; ?>')"
                                                class="text-yellow-600 hover:text-yellow-800" title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php if ($user_role == 'superadmin' && $agent['role'] != 'superadmin'): ?>
                                        <?php if ($agent['status'] == 'active'): ?>
                                        <button onclick="toggleStatus(<?php echo $agent['agent_id']; ?>, 'suspend', '<?php echo $agent['full_name']; ?>')"
                                                class="text-orange-600 hover:text-orange-800" title="Suspend">
                                            <i class="fas fa-pause"></i>
                                        </button>
                                        <?php else: ?>
                                        <button onclick="toggleStatus(<?php echo $agent['agent_id']; ?>, 'activate', '<?php echo $agent['full_name']; ?>')"
                                                class="text-green-600 hover:text-green-800" title="Activate">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($agent['total_links'] == 0): ?>
                                        <button onclick="deleteAgent(<?php echo $agent['agent_id']; ?>, '<?php echo $agent['full_name']; ?>')"
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
        </main>
    </div>
    
    <!-- Add Agent Modal -->
    <div id="addAgentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-2xl w-full mx-4 my-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold">Add New Agent</h3>
                <button onclick="closeModal('addAgentModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="addAgentForm" method="POST">
                <input type="hidden" name="action" value="add_agent">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Username <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="username" required 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                               placeholder="Enter username">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="password" required minlength="6"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                               placeholder="At least 6 characters">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="full_name" required 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                               placeholder="Enter full name">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                               placeholder="agent@example.com">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                        <input type="tel" name="phone"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                               placeholder="10-digit number">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                        <select name="role" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                            <option value="agent">Agent</option>
                            <?php if ($user_role == 'superadmin'): ?>
                            <option value="admin">Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Commission Rate (%)</label>
                        <input type="number" name="commission_rate" min="0" max="50" step="0.1" value="5.0"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <?php if ($user_role == 'superadmin' || $user_role == 'admin'): ?>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assign to Admin (Optional)</label>
                        <select name="assigned_to" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                            <option value="">None (Self-managed)</option>
                            <?php foreach ($admins as $admin): ?>
                            <option value="<?php echo $admin['agent_id']; ?>"><?php echo htmlspecialchars($admin['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('addAgentModal')" 
                            class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-500 text-white rounded-lg hover:opacity-90 font-semibold">
                        Add Agent
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Agent Modal -->
    <div id="editAgentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-2xl w-full mx-4 my-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold">Edit Agent</h3>
                <button onclick="closeModal('editAgentModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="editAgentForm" method="POST">
                <input type="hidden" name="action" value="update_agent">
                <input type="hidden" name="agent_id" id="editAgentId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="full_name" id="editFullName" required 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" id="editEmail"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                        <input type="tel" name="phone" id="editPhone"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Commission Rate (%)</label>
                        <input type="number" name="commission_rate" id="editCommissionRate" min="0" max="50" step="0.1"
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
                    
                    <?php if ($user_role == 'superadmin' || $user_role == 'admin'): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assign to Admin</label>
                        <select name="assigned_to" id="editAssignedTo" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none">
                            <option value="">None (Self-managed)</option>
                            <?php foreach ($admins as $admin): ?>
                            <option value="<?php echo $admin['agent_id']; ?>"><?php echo htmlspecialchars($admin['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('editAgentModal')" 
                            class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-500 text-white rounded-lg hover:opacity-90 font-semibold">
                        Update Agent
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold mb-4">Reset Password</h3>
            <form id="resetPasswordForm" method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="agent_id" id="resetAgentId">
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        New Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="new_password" required minlength="6"
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none"
                           placeholder="Enter new password">
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('resetPasswordModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600">
                        Reset Password
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
                <input type="hidden" name="agent_id" id="confirmAgentId">
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
        function openAddAgentModal() {
            document.getElementById('addAgentModal').classList.remove('hidden');
            document.getElementById('addAgentModal').classList.add('flex');
        }
        
        function editAgent(agent) {
            document.getElementById('editAgentId').value = agent.agent_id;
            document.getElementById('editFullName').value = agent.full_name;
            document.getElementById('editEmail').value = agent.email || '';
            document.getElementById('editPhone').value = agent.phone || '';
            document.getElementById('editCommissionRate').value = agent.commission_rate;
            document.getElementById('editStatus').value = agent.status;
            document.getElementById('editAssignedTo').value = agent.assigned_to || '';
            
            document.getElementById('editAgentModal').classList.remove('hidden');
            document.getElementById('editAgentModal').classList.add('flex');
        }
        
        function resetPassword(agentId, agentName) {
            document.getElementById('resetAgentId').value = agentId;
            document.getElementById('resetPasswordModal').classList.remove('hidden');
            document.getElementById('resetPasswordModal').classList.add('flex');
        }
        
        function toggleStatus(agentId, action, agentName) {
            document.getElementById('confirmAgentId').value = agentId;
            document.getElementById('confirmAction').value = 'update_agent';
            
            if (action === 'suspend') {
                document.getElementById('confirmTitle').textContent = 'Suspend Agent';
                document.getElementById('confirmMessage').textContent = `Are you sure you want to suspend ${agentName}? They will not be able to login.`;
                document.getElementById('confirmBtn').className = 'px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600';
                document.getElementById('confirmForm').innerHTML += '<input type="hidden" name="status" value="suspended">';
            } else {
                document.getElementById('confirmTitle').textContent = 'Activate Agent';
                document.getElementById('confirmMessage').textContent = `Are you sure you want to activate ${agentName}?`;
                document.getElementById('confirmBtn').className = 'px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600';
                document.getElementById('confirmForm').innerHTML += '<input type="hidden" name="status" value="active">';
            }
            
            document.getElementById('confirmModal').classList.remove('hidden');
            document.getElementById('confirmModal').classList.add('flex');
        }
        
        function deleteAgent(agentId, agentName) {
            document.getElementById('confirmAgentId').value = agentId;
            document.getElementById('confirmAction').value = 'delete_agent';
            document.getElementById('confirmTitle').textContent = 'Delete Agent';
            document.getElementById('confirmMessage').textContent = `Are you sure you want to delete ${agentName}? This action cannot be undone.`;
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
        
        // Form validation
        document.getElementById('addAgentForm').addEventListener('submit', function(e) {
            const password = document.querySelector('#addAgentForm input[name="password"]').value;
            if (password.length < 6) {
                alert('Password must be at least 6 characters');
                e.preventDefault();
            }
        });
        
        document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
            const password = document.querySelector('#resetPasswordForm input[name="new_password"]').value;
            if (password.length < 6) {
                alert('Password must be at least 6 characters');
                e.preventDefault();
            }
        });
        
        // Auto-format phone number
        const phoneInputs = document.querySelectorAll('input[type="tel"]');
        phoneInputs.forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 10) value = value.substring(0, 10);
                e.target.value = value;
            });
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>