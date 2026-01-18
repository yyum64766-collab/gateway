<?php
// update_tables.php - Add missing tables and columns
header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$dbname = 'hubb895940_repaynim';
$username = 'hubb895940_repaynim';
$password = 'hubb895940_repaynim';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "<h2>Updating Database Tables...</h2>";
    
    // Add missing columns to agents table
    $pdo->exec("ALTER TABLE agents 
                ADD COLUMN IF NOT EXISTS email VARCHAR(100),
                ADD COLUMN IF NOT EXISTS phone VARCHAR(15),
                ADD COLUMN IF NOT EXISTS assigned_to INT,
                ADD COLUMN IF NOT EXISTS commission_rate DECIMAL(5,2) DEFAULT 5.00,
                ADD COLUMN IF NOT EXISTS total_commission DECIMAL(12,2) DEFAULT 0.00");
    
    echo "✓ Updated agents table<br>";
    
    // Add missing columns to payments table
    $pdo->exec("ALTER TABLE payments 
                ADD COLUMN IF NOT EXISTS loan_id INT,
                ADD COLUMN IF NOT EXISTS payment_apps JSON,
                ADD COLUMN IF NOT EXISTS metadata JSON");
    
    echo "✓ Updated payments table<br>";
    
    // Insert sample data for testing
    $check = $pdo->query("SELECT COUNT(*) as count FROM agents WHERE role = 'agent'")->fetch();
    if ($check['count'] == 0) {
        // Insert sample agents
        $sampleAgents = [
            ['agent1', 'Agent One', 'agent'],
            ['agent2', 'Agent Two', 'agent'],
            ['admin1', 'Admin One', 'admin']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO agents (username, full_name, role, password_hash, status) VALUES (?, ?, ?, ?, 'active')");
        foreach ($sampleAgents as $agent) {
            $passwordHash = password_hash('password123', PASSWORD_DEFAULT);
            $stmt->execute([$agent[0], $agent[1], $agent[2], $passwordHash]);
        }
        
        echo "✓ Added sample agents<br>";
    }
    
    // Insert sample links
    $check = $pdo->query("SELECT COUNT(*) as count FROM link_generations")->fetch();
    if ($check['count'] == 0) {
        $agents = $pdo->query("SELECT agent_id FROM agents WHERE role = 'agent' LIMIT 2")->fetchAll();
        
        $sampleLinks = [
            ['LNK' . time() . '01', 'Customer One', '9876543210', 5000, 'active'],
            ['LNK' . time() . '02', 'Customer Two', '9876543211', 7500, 'active'],
            ['LNK' . time() . '03', 'Customer Three', '9876543212', 10000, 'expired']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO link_generations (link_id, agent_id, customer_name, customer_phone, amount, upi_address, link_url, link_status, expiry_time) VALUES (?, ?, ?, ?, ?, 'test@upi', 'https://example.com/pay', ?, DATE_ADD(NOW(), INTERVAL 6 HOUR))");
        
        foreach ($sampleLinks as $index => $link) {
            $agentId = $agents[$index % count($agents)]['agent_id'];
            $stmt->execute([$link[0], $agentId, $link[1], $link[2], $link[3], $link[4]]);
        }
        
        echo "✓ Added sample links<br>";
    }
    
    // Insert sample payments
    $check = $pdo->query("SELECT COUNT(*) as count FROM payments")->fetch();
    if ($check['count'] == 0) {
        $links = $pdo->query("SELECT link_id, agent_id FROM link_generations LIMIT 3")->fetchAll();
        
        $samplePayments = [
            ['PAY' . time() . '01', 'TXN001', 5000, 'success'],
            ['PAY' . time() . '02', 'TXN002', 7500, 'success'],
            ['PAY' . time() . '03', 'TXN003', 10000, 'pending']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO payments (payment_id, transaction_ref, link_id, amount, upi_id, customer_phone, agent_id, payment_status, created_at, expiry_time) VALUES (?, ?, ?, ?, 'test@upi', '9876543210', ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 6 HOUR))");
        
        foreach ($samplePayments as $index => $payment) {
            $link = $links[$index] ?? $links[0];
            $stmt->execute([$payment[0], $payment[1], $link['link_id'], $payment[2], $link['agent_id'], $payment[3]]);
        }
        
        echo "✓ Added sample payments<br>";
    }
    
    echo "<h3 style='color: green;'>✅ Database update completed successfully!</h3>";
    
    // Show current data
    echo "<h4>Current Data Summary:</h4>";
    
    $tables = ['agents', 'link_generations', 'payments', 'link_clicks'];
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) as count FROM $table")->fetch()['count'];
        echo "<p><strong>$table:</strong> $count records</p>";
    }
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Error: " . $e->getMessage() . "</h3>";
}
?>