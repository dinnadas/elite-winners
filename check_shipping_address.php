<?php
session_start();
require_once 'config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/stripe_errors.log');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("check_shipping_address.php: User not logged in");
    http_response_code(401);
    echo json_encode(['error' => ['message' => 'User not logged in']]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Check if the user has a saved shipping address
    $stmt = $pdo->prepare("SELECT shipping_address FROM user_shipping_addresses WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $address = $stmt->fetchColumn();

    if ($address) {
        // Store the shipping address in the session
        $_SESSION['shipping_address'] = json_decode($address, true);
        error_log("check_shipping_address.php: Shipping address found and stored in session for user_id: $user_id");
        echo json_encode(['has_address' => true]);
    } else {
        error_log("check_shipping_address.php: No shipping address found for user_id: $user_id");
        echo json_encode(['has_address' => false]);
    }
} catch (PDOException $e) {
    error_log("check_shipping_address.php: Database error - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => ['message' => 'Database error occurred']]);
    exit;
}
?>