<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'send_order_email.php';

use Stripe\Stripe;
use Stripe\Webhook;

// Set Stripe API key
Stripe::setApiKey(STRIPE_SECRET_KEY);

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/stripe_errors.log');

// Get the webhook payload and signature
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = 'whsec_lLkyMEzz7booUKHeUB0bqmhz2qBabIId'; // Your correct secret

error_log("handle_payment.php: Received webhook - Event time: " . date('Y-m-d H:i:s') . ", Payload length: " . strlen($payload) . ", Signature: " . $sig_header);

try {
    $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    error_log("handle_payment.php: Webhook signature verified. Event type: " . $event->type . ", Event ID: " . $event->id);

    // Check if event was already processed
    $stmt = $pdo->prepare("SELECT id FROM webhook_events WHERE event_id = ?");
    $stmt->execute([$event->id]);
    if ($stmt->fetch()) {
        error_log("handle_payment.php: Event ID {$event->id} already processed, skipping");
        http_response_code(200);
        echo "OK";
        exit;
    }

    // Record the event
    $stmt = $pdo->prepare("INSERT INTO webhook_events (event_id, event_type, processed_at) VALUES (?, ?, ?)");
    $stmt->execute([$event->id, $event->type, date('Y-m-d H:i:s')]);

    // Handle the event
    switch ($event->type) {
        case 'checkout.session.completed':
    $session = $event->data->object;
    $order_id = $session->metadata->order_id ?? null;
    if (!$order_id) {
        error_log("handle_payment.php: No order_id in metadata");
        http_response_code(400);
        exit("No order_id");
    }

    // Prevent duplicate
    $stmt = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    if ($stmt->fetchColumn() === 'paid') {
        error_log("handle_payment.php: Order $order_id already paid");
        http_response_code(200);
        echo "OK";
        exit;
    }

    // === FETCH ORDER ITEMS WITH price_at_order ===
    $stmt = $pdo->prepare("
        SELECT 
            oi.id,
            oi.product_id,
            oi.quantity,
            oi.price_at_order,
            o.shipping_cost,
            o.discount_amount
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($order_items)) {
        error_log("handle_payment.php: No items for order $order_id");
        http_response_code(400);
        exit("No items");
    }

    // === CALCULATE SUBTOTAL USING price_at_order ===
    $subtotal = 0;
    $total_quantity = 0;

    foreach ($order_items as $item) {
        $price = $item['price_at_order'] ?? 0;
        if ($price <= 0) {
            error_log("handle_payment.php: price_at_order missing for item ID {$item['id']}");
            $price = $pdo->query("SELECT price FROM products WHERE id = ?")->fetchColumn();
        }

        $item_total = $price * $item['quantity'];
        $subtotal += $item_total;
        $total_quantity += $item['quantity'];
    }

    // Apply order discount (if any)
    $discount = $order_items[0]['discount_amount'] ?? 0;
    $subtotal_after_discount = $subtotal - $discount;

    // Add shipping
    $shipping = $order_items[0]['shipping_cost'] ?? 0;
    $final_total = max(0, $subtotal_after_discount + $shipping);

    // === UPDATE ORDERS TABLE ===
    $pdo->prepare("
        UPDATE orders 
        SET total_amount = ?, payment_status = 'paid', updated_at = NOW()
        WHERE id = ?
    ")->execute([$final_total, $order_id]);

    // === CALCULATE price_per_item ===
    $price_per_item = $total_quantity > 0 ? $final_total / $total_quantity : 0;

    // === UPDATE order_items ===
    foreach ($order_items as $item) {
        $pdo->prepare("
            UPDATE order_items 
            SET price_at_purchase = ? 
            WHERE id = ?
        ")->execute([$price_per_item, $item['id']]);
    }

    // === REDUCE STOCK ===
    foreach ($order_items as $item) {
        $pdo->prepare("
            UPDATE products SET stock = stock - ?
            WHERE id = ? AND stock >= ?
        ")->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
    }

    // === SEND EMAIL ===
    sendOrderEmail($pdo, $order_id, 'paid');

    error_log("handle_payment.php: Order $order_id PAID | Total: $$final_total | Items: $total_quantity | Price per item: $$price_per_item");
    break;

        case 'checkout.session.expired':
        case 'charge.failed':
            $session = $event->data->object;
            $order_id = $session->metadata->order_id ?? null;
            if (!$order_id) {
                error_log("handle_payment.php: No order_id in metadata for {$event->type}, Event ID: " . $event->id);
                http_response_code(400);
                exit("No order_id in metadata");
            }

            // Check if order is already failed
            $stmt = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($order && $order['payment_status'] === 'failed') {
                error_log("handle_payment.php: Order ID $order_id already failed, skipping");
                http_response_code(200);
                echo "OK";
                exit;
            }

            // Update order status to failed and store failure reason
            $failure_reason = $event->type === 'checkout.session.expired' ? 'Checkout session expired' : 'Payment failed';
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'failed', failure_reason = ? WHERE id = ?");
            $stmt->execute([$failure_reason, $order_id]);

            // Send failure emails
            if (sendOrderEmail($pdo, $order_id, 'failed', $failure_reason)) {
                error_log("handle_payment.php: Failure emails sent for order ID $order_id");
            } else {
                error_log("handle_payment.php: Failed to send failure emails for order ID $order_id");
            }
            break;

        default:
            error_log("handle_payment.php: Unhandled event type {$event->type}, Event ID: " . $event->id);
    }

    http_response_code(200);
    echo "OK";
} catch (\UnexpectedValueException $e) {
    error_log("handle_payment.php: Invalid payload - " . $e->getMessage() . " | Payload: " . substr($payload, 0, 500));
    http_response_code(400);
    exit("Webhook Error: Invalid payload");
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    error_log("handle_payment.php: Invalid signature - " . $e->getMessage() . " | Expected secret: " . substr($endpoint_secret, 0, 10) . "...");
    http_response_code(400);
    exit("Webhook Error: Invalid signature");
} catch (Exception $e) {
    error_log("handle_payment.php: Unexpected error - " . $e->getMessage());
    http_response_code(500);
    exit("Webhook Error: " . $e->getMessage());
}
?>