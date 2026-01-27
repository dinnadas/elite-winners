<?php
require_once 'config.php';
require_once 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendOrderEmail($pdo, $order_id, $status, $failure_reason = null) {
    $stmt = $pdo->prepare("
        SELECT o.id, o.user_id, o.total, o.created_at, o.shipping_address, o.payment_status,
               u.first_name, u.last_name, u.email
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_log("send_order_email.php: Order not found for ID $order_id");
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT oi.quantity, oi.price, oi.discount_percent, oi.variant_price_delta, oi.chip_selections,
               p.title
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $order_details = '';
    $subtotal = 0;
    foreach ($order_items as $item) {
        $item_price = $item['price'] * (1 - $item['discount_percent'] / 100) + $item['variant_price_delta'];
        $item_total = $item_price * $item['quantity'];
        $subtotal += $item_total;
        $chip_info = $item['chip_selections'] ? " ({$item['chip_selections']})" : '';
        $order_details .= "<li>{$item['title']}{$chip_info} - Quantity: {$item['quantity']} - Price: $" . number_format($item_total, 2) . "</li>";
    }
    $shipping_price = $order['total'] - $subtotal;
    $order_details .= "<li>Shipping: $" . number_format($shipping_price, 2) . "</li>";
    $order_details .= "<li><strong>Total: $" . number_format($order['total'], 2) . "</strong></li>";

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'email';
    $mail->Password = 'password';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->setFrom('info@domain.com', 'EliteWinnersWorldwide');

    try {
        $mail->addAddress($order['email'], "{$order['first_name']} {$order['last_name']}");
        $mail->isHTML(true);

        if ($status === 'paid') {
            $mail->Subject = 'Your Order Confirmation - Order #' . $order_id;
            $mail->Body = "
                <h2>Thank You for Your Order!</h2>
                <p>Dear {$order['first_name']},</p>
                <p>Your order has been successfully placed. Below are the details:</p>
                <ul>
                    <li><strong>Order ID:</strong> {$order_id}</li>
                    <li><strong>Order Date:</strong> " . date('F j, Y, g:i A', strtotime($order['created_at'])) . "</li>
                    <li><strong>Order Details:</strong>
                        <ul>{$order_details}</ul>
                    </li>
                </ul>
                <p>We will notify you once your order has shipped.</p>
                <p>Best regards,<br>EliteWinnersWorldwide Team</p>
            ";
        } else {
            $mail->Subject = 'Order Attempt Failed - Order #' . $order_id;
            $mail->Body = "
                <h2>Order Attempt Failed</h2>
                <p>Dear {$order['first_name']},</p>
                <p>We’re sorry, but there was an issue processing your order. Please review your payment details and try again.</p>
                <ul>
                    <li><strong>Order ID:</strong> {$order_id}</li>
                    <li><strong>Order Date:</strong> " . date('F j, Y, g:i A', strtotime($order['created_at'])) . "</li>
                    <li><strong>Order Details:</strong>
                        <ul>{$order_details}</ul>
                    </li>
                    <li><strong>Reason for Failure:</strong> " . htmlspecialchars($failure_reason ?? 'Unknown error') . "</li>
                </ul>
                <p>Please contact us at info@elitewinnersworldwide.com if you need assistance.</p>
                <p>Best regards,<br>EliteWinnersWorldwide Team</p>
            ";
        }
        $mail->send();
        error_log("send_order_email.php: User email sent for order ID $order_id, status: $status");

        $mail->clearAddresses();
        $mail->addAddress('dinaolenku@gmail.com', 'Admin');
        $shipping_address = json_decode($order['shipping_address'], true);
        $address_formatted = $shipping_address ? 
            "{$shipping_address['name']}<br>" .
            "{$shipping_address['address']['line1']}" .
            ($shipping_address['address']['line2'] ? "<br>{$shipping_address['address']['line2']}" : '') . "<br>" .
            "{$shipping_address['address']['city']}, {$shipping_address['address']['postal_code']}<br>" .
            "{$shipping_address['address']['country']}" : 'Not provided';

        if ($status === 'paid') {
            $mail->Subject = 'New Order Received - Order #' . $order_id;
            $mail->Body = "
                <h2>New Order Notification</h2>
                <p>A new order has been successfully placed. Below are the details:</p>
                <ul>
                    <li><strong>Order ID:</strong> {$order_id}</li>
                    <li><strong>Customer Name:</strong> {$order['first_name']} {$order['last_name']}</li>
                    <li><strong>Customer Email:</strong> {$order['email']}</li>
                    <li><strong>Order Date:</strong> " . date('F j, Y, g:i A', strtotime($order['created_at'])) . "</li>
                    <li><strong>Shipping Address:</strong><br>{$address_formatted}</li>
                    <li><strong>Order Details:</strong>
                        <ul>{$order_details}</ul>
                    </li>
                </ul>
                <p>Please process the order accordingly.</p>
                <p>Best regards,<br>EliteWinnersWorldwide System</p>
            ";
        } else {
            $mail->Subject = 'Failed Order Attempt - Order #' . $order_id;
            $mail->Body = "
                <h2>Failed Order Attempt</h2>
                <p>An order attempt has failed. Below are the details:</p>
                <ul>
                    <li><strong>Order ID:</strong> {$order_id}</li>
                    <li><strong>Customer Name:</strong> {$order['first_name']} {$order['last_name']}</li>
                    <li><strong>Customer Email:</strong> {$order['email']}</li>
                    <li><strong>Order Date:</strong> " . date('F j, Y, g:i A', strtotime($order['created_at'])) . "</li>
                    <li><strong>Shipping Address:</strong><br>{$address_formatted}</li>
                    <li><strong>Order Details:</strong>
                        <ul>{$order_details}</ul>
                    </li>
                    <li><strong>Reason for Failure:</strong> " . htmlspecialchars($failure_reason ?? 'Unknown error') . "</li>
                </ul>
                <p>Please review the issue and contact the customer if necessary.</p>
                <p>Best regards,<br>EliteWinnersWorldwide System</p>
            ";
        }
        $mail->send();
        error_log("send_order_email.php: Admin email sent for order ID $order_id, status: $status");
        return true;
    } catch (Exception $e) {
        error_log("send_order_email.php: Failed to send email for order ID $order_id - " . $mail->ErrorInfo);
        return false;
    }
}
?>