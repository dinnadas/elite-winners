<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = (int)$_POST['product_id'];
    $quantity = 1; 

    try {
        $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            die("Product not found.");
        }

        $current_price = (float)$product['price'];

        $stmt = $pdo->prepare("
            SELECT id, quantity 
            FROM cart 
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$user_id, $product_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $new_quantity = $existing['quantity'] + $quantity;
            $stmt = $pdo->prepare("
                UPDATE cart 
                SET quantity = ? 
                WHERE id = ?
            ");
            $stmt->execute([$new_quantity, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO cart 
                (user_id, product_id, quantity, price_at_order, added_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $product_id,
                $quantity,
                $current_price
            ]);
        }

        $_SESSION['cart_message'] = "Item added to cart!";

        header("Location: cart.php");
        exit;

    } catch (PDOException $e) {
        error_log("add_to_cart.php error: " . $e->getMessage());
        die("Add to cart failed. Please try again.");
    }
}
?>