<?php
// test_connection.php - Test database connection

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    
    echo "<h2 style='color: green;'>✅ Database Connection Successful!</h2>";
    echo "<p><strong>Database:</strong> $dbname</p>";
    echo "<p><strong>Host:</strong> $host</p>";
    
    // Test query
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch();
    echo "<p><strong>MySQL Version:</strong> " . $version['version'] . "</p>";
    
    // Show tables
    echo "<h3>Tables in Database:</h3>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "<p style='color: orange;'>No tables found. Please run setup_database.php first.</p>";
    } else {
        echo "<ul>";
        foreach ($tables as $table) {
            // Get row count
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $countStmt->fetch()['count'];
            echo "<li>$table ($count rows)</li>";
        }
        echo "</ul>";
    }
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>❌ Database Connection Failed!</h2>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>Database credentials in config.php</li>";
    echo "<li>Database server is running</li>";
    echo "<li>Database user has proper permissions</li>";
    echo "</ul>";
}
?>