<?php
session_start();
require_once 'config.php';
require_once 'vendor/autoload.php';

use Stripe\Stripe;
use Stripe\Checkout\Session;

// Set Stripe API key
Stripe::setApiKey(STRIPE_SECRET_KEY);

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/stripe_errors.log');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("create_session.php: User not logged in");
    header('Content-Type: application/json');
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch cart items with stock
$stmt = $pdo->prepare("
    SELECT c.product_id, c.quantity, c.chip_selections, c.variant_price_delta,
           p.title, p.price, p.discount_percent, p.shipping_price, p.stock
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cart_items)) {
    error_log("create_session.php: Cart is empty for user_id: $user_id");
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Cart is empty']);
    exit;
}

// Validate stock
foreach ($cart_items as $item) {
    if ($item['quantity'] > $item['stock']) {
        error_log("create_session.php: Insufficient stock for product_id: {$item['product_id']}, requested: {$item['quantity']}, available: {$item['stock']}");
        header('Content-Type: application/json');
        echo json_encode(['error' => "Insufficient stock for {$item['title']}. Only {$item['stock']} available."]);
        exit;
    }
}

// === CALCULATE TOTAL, DISCOUNTS, SHIPPING ===
$line_items = [];
$subtotal_before_discount = 0;
$discount_amount = 0;
$shipping_cost = 0;
$total_quantity = 0;

foreach ($cart_items as $item) {
    $base_price = $item['price'];
    $unit_amount = $base_price * (1 - $item['discount_percent'] / 100) + $item['variant_price_delta'];
    
    $subtotal_before_discount += $base_price * $item['quantity'];
    $discount_amount += ($base_price * $item['discount_percent'] / 100) * $item['quantity'];
    $shipping_cost += $item['shipping_price'] * $item['quantity'];
    $total_quantity += $item['quantity'];

    $line_items[] = [
        'price_data' => [
            'currency' => 'usd',
            'product_data' => [
                'name' => $item['title'] . ($item['chip_selections'] ? " ({$item['chip_selections']})" : ''),
            ],
            'unit_amount' => round($unit_amount * 100),
        ],
        'quantity' => $item['quantity'],
    ];

    if ($item['shipping_price'] > 0) {
        $line_items[] = [
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => 'Shipping for ' . $item['title'],
                ],
                'unit_amount' => round($item['shipping_price'] * 100),
            ],
            'quantity' => $item['quantity'],
        ];
    }
}

$final_total = $subtotal_before_discount - $discount_amount + $shipping_cost;

// Fetch shipping address
$shipping_address = isset($_SESSION['shipping_address']) ? $_SESSION['shipping_address'] : null;
if (!$shipping_address) {
    error_log("create_session.php: No shipping address for user_id: $user_id");
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No shipping address provided']);
    exit;
}

// === CREATE ORDER + ORDER ITEMS WITH CORRECT FIELDS ===
try {
    $pdo->beginTransaction();

    // Insert into orders with full financial data
    $stmt = $pdo->prepare("
        INSERT INTO orders 
        (user_id, total_amount, shipping_cost, discount_amount, payment_status, shipping_address, created_at) 
        VALUES (?, ?, ?, ?, 'pending', ?, NOW())
    ");
    $stmt->execute([
        $user_id,
        $final_total,
        $shipping_cost,
        $discount_amount,
        json_encode($shipping_address)
    ]);
    $order_id = $pdo->lastInsertId();

    // Insert order items with price_at_order
    $stmt = $pdo->prepare("
        INSERT INTO order_items 
        (order_id, product_id, quantity, price_at_order, price_at_purchase, 
         discount_percent, variant_price_delta, chip_selections) 
        VALUES (?, ?, ?, ?, 0.00, ?, ?, ?)
    ");

    foreach ($cart_items as $item) {
        $price_at_order = $item['price']; // Original price at cart time
        $stmt->execute([
            $order_id,
            $item['product_id'],
            $item['quantity'],
            $price_at_order,
            $item['discount_percent'],
            $item['variant_price_delta'],
            $item['chip_selections']
        ]);
    }

    // Create Stripe Checkout session
    $session = Session::create([
        'payment_method_types' => ['card'],
        'line_items' => $line_items,
        'mode' => 'payment',
        'success_url' => 'https://elite.kalonoid.com/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'https://elite.kalonoid.com/cart.php',
        'metadata' => ['order_id' => $order_id],
        'shipping_address_collection' => ['allowed_countries' => ['US', 'GR']],
    ]);

    // Update order with Stripe session ID
    $stmt = $pdo->prepare("UPDATE orders SET stripe_session_id = ? WHERE id = ?");
    $stmt->execute([$session->id, $order_id]);

    $pdo->commit();

    // Clear cart
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);

    header('Content-Type: application/json');
    echo json_encode(['sessionId' => $session->id]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("create_session.php: Error creating order for user_id: $user_id - " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to create checkout session']);
}
?>