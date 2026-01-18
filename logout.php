<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $device_id = $input['device_id'] ?? 0;
    
    $db = connectDB();
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if ($user_id > 0 && $device_id > 0) {
        $success = logoutDevice($db, $device_id, $user_id);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Device logged out successfully' : 'Failed to logout device'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request'
        ]);
    }
}
?>