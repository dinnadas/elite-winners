<?php
// ── Your original PHP code remains 100% unchanged ────────────────────────────
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
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed — Elite Winners Worldwide</title>
    <meta name="description" content="Your order has been successfully placed and confirmed. Thank you for choosing EliteWinnersWorldwide!">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'eww-green': '#0B7A4D',
                        'eww-gold'  : '#D97706',
                        'eww-dark'  : '#111827',
                        'eww-light' : '#F9FAFB',
                    },
                    fontFamily: {
                        heading: ['Montserrat', 'sans-serif'],
                        body:   ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.7s ease-out forwards',
                        'scale-in'  : 'scaleIn 0.6s ease-out forwards',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(16px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        scaleIn: {
                            '0%': { opacity: '0', transform: 'scale(0.95)' },
                            '100%': { opacity: '1', transform: 'scale(1)' }
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@latest/dist/dotlottie-wc.js" type="module"></script>
</head>
<body class="bg-eww-light min-h-screen font-body antialiased">

    <div class="min-h-screen flex items-center justify-center py-10 px-5 sm:px-6 lg:px-8">
        <div class="w-full max-w-2xl">

            <!-- Main card -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">

                <!-- Hero / Success banner -->
                <div class="bg-gradient-to-br from-eww-green to-emerald-800 text-white px-8 py-12 sm:py-16 text-center relative overflow-hidden">
                    <div class="absolute inset-0 opacity-10 pointer-events-none">
                        <div class="absolute -top-24 -right-24 w-64 h-64 bg-white rounded-full blur-3xl"></div>
                    </div>

                    <div class="relative">
                        <div class="flex justify-center mb-6 transform transition-all duration-500">
                            <dotlottie-wc 
                                src="https://lottie.host/031b387f-793d-4063-be5b-5e83ab76a86b/kVlnXg6RP0.lottie"
                                style="width: 160px; height: 160px;"
                                autoplay
                                speed="0.9">
                            </dotlottie-wc>
                        </div>

                        <h1 class="text-4xl sm:text-5xl font-heading font-extrabold tracking-tight drop-shadow-md">
                            Order Confirmed
                        </h1>
                        <p class="mt-3 text-lg sm:text-xl text-emerald-100/90 font-medium">
                            Thank you for shopping with us!
                        </p>
                    </div>
                </div>

                <!-- Content -->
                <div class="px-6 sm:px-10 py-8 sm:py-10">

                    <!-- Order ID & status -->
                    <div class="text-center mb-10 animate-fade-in-up" style="animation-delay: 0.2s;">
                        <p class="text-lg font-semibold text-eww-dark">
                            Order ID: <span class="text-eww-green font-bold">#<?php echo htmlspecialchars($order_id); ?></span>
                        </p>
                        <?php if ($already_paid): ?>
                            <p class="mt-2 text-sm text-gray-500">This order was previously confirmed.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Items -->
                    <div class="space-y-4 mb-10 animate-fade-in-up" style="animation-delay: 0.4s;">
                        <?php foreach ($items as $item): ?>
                            <div class="flex items-center justify-between p-5 bg-gray-50 rounded-xl border border-gray-100 hover:border-eww-green/30 transition-colors duration-200">
                                <div class="flex-1 pr-4">
                                    <h4 class="font-semibold text-eww-dark line-clamp-2"><?php echo htmlspecialchars($item['title']); ?></h4>
                                    <p class="text-sm text-gray-600 mt-1">Qty: <?php echo $item['quantity']; ?></p>
                                </div>
                                <div class="text-right font-bold text-eww-green whitespace-nowrap">
                                    $<?php echo number_format($item['price_at_purchase'] * $item['quantity'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Total -->
                    <div class="border-t border-gray-200 pt-6 mb-10 animate-fade-in-up" style="animation-delay: 0.6s;">
                        <div class="flex justify-between items-center text-xl sm:text-2xl font-bold">
                            <span class="text-eww-dark">Total Paid</span>
                            <span class="text-eww-green">
                                <?php echo $currency; ?> <?php echo number_format($amount_total, 2); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Delivery & trust box -->
                    <div class="p-6 bg-gradient-to-br from-emerald-50 to-amber-50 rounded-2xl border border-emerald-100 animate-fade-in-up mb-10" style="animation-delay: 0.8s;">
                        <h3 class="font-heading font-bold text-eww-dark text-lg mb-4">Order & Delivery Information</h3>
                        <div class="space-y-3 text-gray-700">
                            <p><strong class="text-eww-dark">Email:</strong> <?php echo htmlspecialchars($customer_email); ?></p>
                            <p class="text-sm text-gray-600">A detailed confirmation with tracking information has been sent to your inbox.</p>
                        </div>
                    </div>

                    <!-- Trust badge -->
                    <div class="flex items-center justify-center text-sm text-gray-500 mb-10 animate-fade-in-up" style="animation-delay: 1s;">
                        <svg class="w-5 h-5 text-eww-green mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                        </svg>
                        <span>Secure payment processed by <span class="font-semibold text-gray-700">Stripe</span></span>
                    </div>

                    <!-- Actions -->
                    <div class="grid sm:grid-cols-2 gap-4 animate-fade-in-up" style="animation-delay: 1.2s;">
                        <a href="index.php" 
                           class="inline-flex items-center justify-center bg-eww-green text-white font-heading font-bold text-lg py-4 px-8 rounded-xl hover:bg-emerald-900 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                            Continue Shopping
                        </a>
                        <a href="profile.php" 
                           class="inline-flex items-center justify-center border-2 border-eww-green text-eww-green font-heading font-bold text-lg py-4 px-8 rounded-xl hover:bg-eww-green hover:text-white transition-all duration-300">
                            View My Orders
                        </a>
                    </div>
                </div>
            </div>

            <!-- Footer help -->
            <p class="text-center text-gray-500 text-sm mt-10 animate-fade-in-up" style="animation-delay: 1.4s;">
                Questions? Reach us at 
                <a href="mailto:elitewinnersworldwide@gmail.com" 
                   class="text-eww-green font-medium hover:underline transition-colors">
                    elitewinnersworldwide@gmail.com
                </a>
            </p>

        </div>
    </div>

    <script>
        // Minimal Lottie control – stop after one play
        document.addEventListener('DOMContentLoaded', () => {
            const lottie = document.querySelector('dotlottie-wc');
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
            }, 3000);
        });
    </script>
</body>
</html>