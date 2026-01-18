<?php
// login.php
session_start();

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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        $db = connectDB();
        $stmt = $db->prepare("SELECT * FROM agents WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // For demo, using simple password check. In production, use password_hash/password_verify
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['agent_id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            
            // Update last login
            $updateStmt = $db->prepare("UPDATE agents SET last_login = NOW() WHERE agent_id = ?");
            $updateStmt->execute([$user['agent_id']]);
            
            // Log activity
            $logStmt = $db->prepare("INSERT INTO activity_logs (agent_id, activity_type, description, ip_address) VALUES (?, 'login', 'User logged in', ?)");
            $logStmt->execute([$user['agent_id'], $_SERVER['REMOTE_ADDR']]);
            
            header('Location: index.php');
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    }
}

// Demo credentials for testing
$demoCredentials = [
    ['username' => 'superadmin', 'password' => 'password', 'role' => 'Super Admin'],
    ['username' => 'admin1', 'password' => 'admin123', 'role' => 'Admin'],
    ['username' => 'agent1', 'password' => 'agent123', 'role' => 'Agent']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NimCredit - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="login-card bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md mx-4">
        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-credit-card text-white text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800">NimCredit</h1>
            <p class="text-gray-600 mt-2">Secure Payment Dashboard</p>
        </div>
        
        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <span class="text-red-700"><?php echo $error; ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                <span class="text-green-700"><?php echo $success; ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                <div class="relative">
                    <i class="fas fa-user absolute left-3 top-3 text-gray-400"></i>
                    <input type="text" name="username" required 
                           class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none transition"
                           placeholder="Enter username">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
                    <input type="password" name="password" required 
                           class="w-full pl-10 pr-10 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none transition"
                           placeholder="Enter password">
                    <button type="button" onclick="togglePassword(this)" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-purple-500 text-white py-3 rounded-lg font-semibold hover:opacity-90 transition shadow-lg">
                Sign In <i class="fas fa-arrow-right ml-2"></i>
            </button>
        </form>
        
        <div class="mt-8 pt-6 border-t border-gray-200">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Demo Accounts:</h3>
            <div class="space-y-2">
                <?php foreach ($demoCredentials as $cred): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <span class="font-medium text-gray-800"><?php echo $cred['username']; ?></span>
                        <span class="text-xs text-gray-600 ml-2">(<?php echo $cred['role']; ?>)</span>
                    </div>
                    <span class="font-mono text-sm text-gray-700"><?php echo $cred['password']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="mt-6 text-center text-sm text-gray-600">
            <p>Need help? <a href="#" class="text-blue-600 hover:text-blue-800">Contact Support</a></p>
        </div>
    </div>
    
    <script>
        function togglePassword(button) {
            const input = button.parentElement.querySelector('input');
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        // Auto-fill demo credentials for testing
        function fillDemoCredential(index) {
            const credentials = <?php echo json_encode($demoCredentials); ?>;
            if (credentials[index]) {
                document.querySelector('input[name="username"]').value = credentials[index].username;
                document.querySelector('input[name="password"]').value = credentials[index].password;
            }
        }
        
        // Auto-fill first demo account
        window.onload = function() {
            fillDemoCredential(0);
        };
    </script>
</body>
</html>