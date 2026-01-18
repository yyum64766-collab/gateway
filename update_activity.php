<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    $db = connectDB();
    updateLastActivity($db, $_SESSION['user_id']);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>