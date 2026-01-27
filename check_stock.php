<?php
session_start();
require_once 'config.php';

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/stripe_errors.log');

header('Content-Type: application/json');

error_log("check_stock.php: Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));

$user_id = $_POST['user_id'] ?? $_SESSION['user_id'] ?? null;
if (!$user_id) {
    error_log("check_stock.php: No user_id received in POST or SESSION");
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.product_id, c.quantity, p.title, p.stock
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($cart_items as $item) {
    if ($item['quantity'] > $item['stock']) {
        error_log("check_stock.php: Insufficient stock for product_id: {$item['product_id']}, requested: {$item['quantity']}, available: {$item['stock']}");
        echo json_encode([
            'success' => false,
            'message' => "Sorry, Insufficient stock for {$item['title']}. If You Have Multiple Items in the cart Try Removing {$item['title']} to continue."
        ]);
        exit;
    }
}

echo json_encode(['success' => true]);
?>