<?php
session_start();
require_once 'config.php';
require_once 'vendor/autoload.php';

use Stripe\Stripe;
use Stripe\Checkout\Session;

Stripe::setApiKey(STRIPE_SECRET_KEY);

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/stripe_errors.log');

$session_id = $_GET['session_id'] ?? null;
if (!$session_id) {
    error_log("success.php: No session_id provided in URL");
    die("Error: Invalid request.");
}

$order_id = null;
$customer_email = null;
$amount_total = null;
$currency = null;
$items = [];

try {
    $session = Session::retrieve($session_id);
    $order_id = $session->metadata->order_id ?? null;

    if (!$order_id) {
        error_log("success.php: No order_id in session metadata for session_id: $session_id");
        die("Error: Order not found.");
    }

    $stmt = $pdo->prepare("SELECT payment_status, user_id FROM orders WHERE id = ? AND stripe_session_id = ?");
    $stmt->execute([$order_id, $session_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_log("success.php: Order not found for order_id: $order_id");
        die("Error: Order not found.");
    }

    $stmt = $pdo->prepare("
        SELECT p.title, oi.quantity, oi.price_at_purchase 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $customer_email = $session->customer_details->email ?? 'N/A';
    $amount_total = $session->amount_total / 100;
    $currency = strtoupper($session->currency);

    if ($order['payment_status'] === 'paid') {
        $already_paid = true;
    } else {
        $already_paid = false;
        if ($session->payment_status === 'paid') {
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ? AND stripe_session_id = ?");
            $stmt->execute([$order_id, $session_id]);
            error_log("success.php: Fallback update - order_id: $order_id marked as paid");
        } else {
            error_log("success.php: Payment not completed for session_id: $session_id");
            die("Error: Payment not completed.");
        }
    }
} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("success.php: Stripe API error - " . $e->getMessage());
    die("Error: Unable to verify payment.");
} catch (Exception $e) {
    error_log("success.php: Unexpected error - " . $e->getMessage());
    die("Error: An unexpected error occurred.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed | EliteWinnersWorldwide</title>
    <meta name="description" content="Your order has been successfully placed. Thank you for shopping with EliteWinnersWorldwide!">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'eww-green': '#0B7A4D',
                        'eww-gold': '#ec8704',
                        'eww-dark': '#1A1A1A',
                        'eww-light': '#F8F8F8',
                    },
                    fontFamily: {
                        heading: ['Montserrat', 'sans-serif'],
                        body: ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.6s ease-out forwards',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.8.5/dist/dotlottie-wc.js" type="module"></script>
</head>
<body class="bg-eww-light min-h-screen font-body">

    <div class="container mx-auto px-4 py-12 md:py-20">
        <div class="max-w-3xl mx-auto">
            <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
                
                <div class="bg-gradient-to-r from-eww-green to-eww-dark text-white p-8 md:p-12 text-center">
                    <div class="flex justify-center mb-6">
                        <dotlottie-wc 
                            id="success-lottie"
                            src="https://lottie.host/031b387f-793d-4063-be5b-5e83ab76a86b/kVlnXg6RP0.lottie" 
                            style="width: 180px; height: 180px;" 
                            autoplay>
                        </dotlottie-wc>
                    </div>
                    <h1 class="text-3xl md:text-5xl font-heading font-bold mb-3">Order Confirmed!</h1>
                    <p class="text-lg md:text-xl opacity-90">Thank you for your purchase</p>
                </div>

                <div class="p-8 md:p-12">
                    <div class="text-center mb-8 animate-fade-in-up" style="animation-delay: 0.3s;">
                        <p class="text-eww-dark text-lg font-semibold">Order ID: <span class="text-eww-green">#<?php echo htmlspecialchars($order_id); ?></span></p>
                        <?php if ($already_paid): ?>
                            <p class="text-sm text-gray-600 mt-2">This order was already confirmed.</p>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-4 mb-8 animate-fade-in-up" style="animation-delay: 0.5s;">
                        <?php foreach ($items as $item): ?>
                            <div class="flex justify-between items-center p-4 bg-eww-light rounded-2xl">
                                <div>
                                    <h4 class="font-semibold text-eww-dark"><?php echo htmlspecialchars($item['title']); ?></h4>
                                    <p class="text-sm text-gray-600">Quantity: <?php echo $item['quantity']; ?></p>
                                </div>
                                <span class="font-bold text-eww-green">
                                    $<?php echo number_format($item['price_at_purchase'] * $item['quantity'], 2); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="border-t pt-6 animate-fade-in-up" style="animation-delay: 0.7s;">
                        <div class="flex justify-between items-center text-xl font-bold">
                            <span class="text-eww-dark">Total Paid</span>
                            <span class="text-eww-green">
                                <?php echo $currency; ?> $<?php echo number_format($amount_total, 2); ?>
                            </span>
                        </div>
                    </div>

                    <div class="mt-8 p-6 bg-gradient-to-r from-eww-green/5 to-eww-gold/5 rounded-2xl animate-fade-in-up" style="animation-delay: 0.9s;">
                        <h3 class="font-heading font-bold text-eww-dark mb-3">Delivery Information</h3>
                        <p class="text-gray-700"><strong>Email:</strong> <?php echo htmlspecialchars($customer_email); ?></p>
                        <p class="text-sm text-gray-600 mt-2">A confirmation email has been sent with tracking details.</p>
                    </div>

                    <div class="mt-8 flex items-center justify-center text-sm text-gray-500 animate-fade-in-up" style="animation-delay: 1.1s;">
                        <svg class="w-5 h-5 text-eww-green mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                        </svg>
                        <span>Secure checkout powered by Stripe</span>
                    </div>

                    <div class="mt-10 flex flex-col sm:flex-row gap-4 animate-fade-in-up" style="animation-delay: 1.3s;">
                        <a href="index.php" class="flex-1 text-center bg-eww-green text-white font-heading font-bold py-4 rounded-2xl hover:bg-eww-dark transition-all transform hover:scale-105">
                            Continue Shopping
                        </a>
                        <a href="profile.php" class="flex-1 text-center border-2 border-eww-green text-eww-green font-heading font-bold py-4 rounded-2xl hover:bg-eww-green hover:text-white transition-all">
                            View Orders
                        </a>
                    </div>
                </div>
            </div>

            <p class="text-center text-gray-500 text-sm mt-12 animate-fade-in-up" style="animation-delay: 1.5s;">
                Need help? Contact us at <a href="mailto:elitewinnersworldwide@gmail.com" class="text-eww-green underline">elitewinnersworldwide@gmail.com</a>
            </p>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            document.querySelectorAll('[class*="animate-"]').forEach(el => {
                el.style.opacity = '1';
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            const lottie = document.getElementById('success-lottie');
            if (!lottie) return;

            lottie.addEventListener('complete', () => {
                lottie.stop();               
                lottie.removeAttribute('loop'); 
            });

            setTimeout(() => {
                if (lottie.getAttribute('loop') !== null) {
                    lottie.stop();
                    lottie.removeAttribute('loop');
                }
            }, 2300);
        });
    </script>
</body>
</html>