<?php
include 'config.php';
session_start();

$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_count = $stmt->fetchColumn() ?: 0;
}

header('Content-Type: application/json');
echo json_encode(['cart_count' => $cart_count]);
?>