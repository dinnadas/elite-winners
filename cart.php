<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!file_exists('config.php')) {
    die("Error: config.php not found.");
}

try {
    require_once 'config.php';
} catch (Exception $e) {
    die("Error loading config.php: " . $e->getMessage());
}


ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/stripe_errors.log');

$is_logged_in = isset($_SESSION['user_id']);
if (!$is_logged_in) {
    header("Location: register.php");
    exit;
}

$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.product_id, c.quantity, c.chip_selections, c.variant_price_delta, p.title, p.price, p.discount_percent, p.image, p.shipping_price, p.stock
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chips = [];
    foreach ($cart_items as $item) {
        $stmt = $pdo->prepare("SELECT chip_title, option_value, additional_price FROM product_chip_options WHERE product_id = ?");
        $stmt->execute([$item['product_id']]);
        $chip_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($chip_options as $option) {
            $chips[$item['product_id']][$option['chip_title']][] = [
                'option_value' => $option['option_value'],
                'additional_price' => $option['additional_price']
            ];
        }
    }
} catch (PDOException $e) {
    error_log("cart.php: Database query failed - " . $e->getMessage());
    die("Database query failed: " . $e->getMessage());
}

$cart_count = 0;
$subtotal = 0;
$total_shipping = 0;
foreach ($cart_items as $item) {
    $cart_count += $item['quantity'];
    $total_price = $item['price'] + ($item['variant_price_delta'] ?? 0.00);
    $discounted_price = $item['discount_percent'] > 0 ? $total_price * (1 - $item['discount_percent'] / 100) : $total_price;
    $subtotal += $discounted_price * $item['quantity'];
    $total_shipping += ($item['shipping_price'] ?? 0.00) * $item['quantity'];
}
$total = $subtotal + $total_shipping;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
    try {
        $cart_id = (int)$_POST['cart_id'];
        $quantity = max(1, (int)$_POST['quantity']);
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$quantity, $cart_id, $user_id]);
        header("Location: cart.php");
        exit;
    } catch (PDOException $e) {
        error_log("cart.php: Quantity update failed - " . $e->getMessage());
        die("Quantity update failed: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    try {
        $cart_id = (int)$_POST['cart_id'];
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$cart_id, $user_id]);
        header("Location: cart.php");
        exit;
    } catch (PDOException $e) {
        error_log("cart.php: Remove item failed - " . $e->getMessage());
        die("Remove item failed: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_chips'])) {
    try {
        $cart_id = (int)$_POST['cart_id'];
        $chip_selections = [];
        $variant_price_delta = 0.00;

        foreach ($_POST as $key => $value) {
            if (strpos($key, 'chip_') === 0 && !empty($value)) {
                $chip_title = str_replace('chip_', '', $key);
                $chip_selections[] = "$chip_title:$value";

                $stmt = $pdo->prepare("
                    SELECT additional_price 
                    FROM product_chip_options 
                    WHERE product_id = (SELECT product_id FROM cart WHERE id = ?) 
                    AND chip_title = ? 
                    AND option_value = ?
                ");
                $stmt->execute([$cart_id, $chip_title, $value]);
                $additional_price = $stmt->fetchColumn();
                $variant_price_delta += (float)$additional_price;
            }
        }
        $chip_selections_str = implode(',', $chip_selections);
        $stmt = $pdo->prepare("UPDATE cart SET chip_selections = ?, variant_price_delta = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$chip_selections_str, $variant_price_delta, $cart_id, $user_id]);
        header("Location: cart.php");
        exit;
    } catch (PDOException $e) {
        error_log("cart.php: Chip selection update failed - " . $e->getMessage());
        die("Chip selection update failed: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - EliteWinnersWorldwide</title>
    <meta property="og:title" content="Shopping Cart - EliteWinnersWorldwide">
    <meta property="og:description" content="Review your items and proceed to checkout">
    <meta property="og:image" content="https://example.com/elitewinners-cart-preview.jpg">
    <meta property="og:url" content="https://elitewinnersworldwide.com/cart">
    <meta property="og:type" content="website">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚽</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'eww-green': '#0B7A4D',
                        'eww-gold': '#D4AF37',
                        'eww-dark': '#1A1A1A',
                        'eww-light': '#F8F8F8',
                    },
                    fontFamily: {
                        heading: ['Montserrat', 'sans-serif'],
                        body: ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out forwards',
                        'slide-up': 'slideUp 0.6s ease-out forwards',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                    },
                }
            }
        }
    </script>
    <style type="text/css">
        body {
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Montserrat', sans-serif;
        }
        html {
            scroll-behavior: smooth;
        }
        .hero-gradient {
            background: linear-gradient(135deg, rgba(11, 122, 77, 0.9) 0%, rgba(26, 26, 26, 0.85) 100%);
        }
        .logo {
            height: 50px;
            width: 50px;
            transform: scale(2.1);
        }
        .logo:hover {
            transform: scale(1.1);
        }
        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 4px 8px;
        }
        .quantity-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background-color: #f8f8f8;
            cursor: pointer;
            transition: all 0.2s;
        }
        .quantity-btn:hover {
            background-color: #0B7A4D;
            color: white;
            border-color: #0B7A4D;
        }
        .chip {
            display: inline-block;
            padding: 6px 12px;
            margin: 4px;
            border: 1px solid #e5e7eb;
            border-radius: 9999px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .chip:hover {
            background-color: #e5e7eb;
        }
        .chip.selected {
            background-color: #0B7A4D;
            color: white;
            border-color: #0B7A4D;
        }
    </style>
</head>
<body class="bg-eww-light text-eww-dark font-body antialiased">
    <!-- Header / Navigation -->
    <header class="fixed w-full z-50 transition-all duration-300" id="header">
        <div class="absolute inset-0 z-0"></div>
        <nav class="container mx-auto px-4 py-4 flex justify-between items-center relative z-20">
            <!-- Logo -->
            <a href="index.php" class="flex items-center space-x-2 z-60">
                <img class="logo" src="https://www.kalonoid.com/uploads/logo.png" alt="logo">
                <span class="text-white font-heading font-bold text-xl hidden md:block">EliteWinnersWorldwide</span>
            </a>
            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="index.php" class="text-white hover:text-eww-gold transition-colors">Home</a>
                <a href="index.php#services" class="text-white hover:text-eww-gold transition-colors">Services</a>
                <a href="index.php#shop" class="text-white hover:text-eww-gold transition-colors">Shop</a>
                <a href="index.php#about" class="text-white hover:text-eww-gold transition-colors">About</a>
                <a href="index.php#testimonials" class="text-white hover:text-eww-gold transition-colors">Testimonials</a>
                <a href="index.php#contact" class="text-white hover:text-eww-gold transition-colors">Contact</a>
                <a href="cart.php" class="text-white ml-4 relative" aria-label="Shopping cart">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span class="absolute -top-2 -right-2 bg-eww-gold text-eww-dark rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold" id="cart-count"><?php echo $cart_count; ?></span>
                </a>
                <?php if ($is_logged_in): ?>
                    <a href="logout.php" class="text-white hover:text-eww-gold transition-colors ml-4">Logout</a>
                <?php else: ?>
                    <a href="register.php " class="bg-eww-gold text-eww-dark px-4 py-2 rounded-2xl font-semibold hover:bg-opacity-90 transition-all ml-4">Sign Up</a>
                <?php endif; ?>
            </div>
            <!-- Mobile menu button -->
            <button class="md:hidden text-white z-60" id="mobile-menu-button" aria-label="Toggle mobile menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                </svg>
            </button>
            <!-- Mobile Navigation -->
            <div class="fixed inset-0 bg-eww-dark bg-opacity-95 z-50 flex flex-col items-center justify-center space-y-8 transform -translate-x-full transition-transform duration-300 md:hidden" id="mobile-menu">
                <a href="index.php" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Home</a>
                <a href="index.php#services" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Services</a>
                <a href="index.php#shop" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Shop</a>
                <a href="index.php#about" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">About</a>
                <a href="index.php#testimonials" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Testimonials</a>
                <a href="index.php#contact" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Contact</a>
                <div class="pt-8 flex space-x-4">
                    <?php if ($is_logged_in): ?>
                        <a href="logout.php" class="bg-eww-gold text-eww-dark px-6 py-3 rounded-2xl font-semibold text-lg">Logout</a>
                    <?php else: ?>
                        <a href="register.php " class="bg-eww-gold text-eww-dark px-6 py-3 rounded-2xl font-semibold text-lg">Sign Up</a>
                    <?php endif; ?>
                    <a href="cart.php" class="border border-eww-gold text-eww-gold px-6 py-3 rounded-2xl font-semibold text-lg">View Cart</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Page Header -->
    <section class="relative pt-32 pb-12">
        <div class="absolute inset-0 z-0">
            <div class="absolute inset-0 hero-gradient z-10"></div>
            <img src="https://images.unsplash.com/photo-1575361204480-aadea25e6e68?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80" 
                 alt="Soccer player in action" class="w-full h-full object-cover">
        </div>
        <div class="container mx-auto px-4 relative z-20">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <h1 class="text-3xl md:text-4xl font-heading font-bold text-white mb-4 md:mb-0">Your Shopping Cart</h1>
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="index.php" class="text-gray-400">Home</a></li>
                        <li class="flex items-center">
                            <span class="text-gray-400 mx-2">/</span>
                            <span class="text-eww-gold hover:text-white transition-colors">Cart</span>
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </section>

    <!-- Cart Section -->
    <section class="py-12 bg-eww-light">
        <div class="container mx-auto px-4">
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Cart Items -->
                <div class="lg:w-2/3">
                    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
                        <h2 class="text-xl font-heading font-bold mb-6">Cart Items (<?php echo $cart_count; ?>)</h2>
                        <?php if (empty($cart_items)): ?>
                            <p class="text-gray-600">Your cart is empty. <a href="index.php#shop" class="text-eww-green hover:underline">Continue shopping</a>.</p>
                        <?php else: ?>
                            <?php foreach ($cart_items as $item): ?>
                                <?php
                                $total_price = $item['price'] + ($item['variant_price_delta'] ?? 0.00);
                                $discounted_price = $item['discount_percent'] > 0 ? $total_price * (1 - $item['discount_percent'] / 100) : $total_price;
                                $shipping_display = ($item['shipping_price'] ?? 0.00) == 0 ? 'Free' : '$' . number_format($item['shipping_price'], 2);
                                $item_total = ($discounted_price + ($item['shipping_price'] ?? 0.00)) * $item['quantity'];
                                ?>
                                <div class="flex flex-col sm:flex-row items-center border-b border-gray-200 pb-6 mb-6">
                                    <div class="flex-shrink-0 w-24 h-24 bg-gray-200 rounded-lg overflow-hidden mr-6">
                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="w-full h-full object-cover">
                                    </div>
                                    <div class="flex-grow mt-4 sm:mt-0">
                                        <h3 class="text-lg font-heading font-bold"><?php echo htmlspecialchars($item['title']); ?></h3>
                                        <div class="flex items-center mt-2">
                                            <span class="text-eww-green font-bold">$<?php echo number_format($discounted_price, 2); ?></span>
                                            <?php if ($item['discount_percent'] > 0): ?>
                                                <span class="text-gray-400 line-through text-sm ml-2">$<?php echo number_format($total_price, 2); ?></span>
                                                <span class="text-gray-500 text-sm ml-2">(<?php echo number_format($item['discount_percent'], 0); ?>% off)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-2 text-sm">
                                            <span>Shipping: <?php echo $shipping_display; ?></span>
                                        </div>
                                        <!-- Chips Selection -->
                                        <?php if (!empty($chips[$item['product_id']])): ?>
                                            <form method="POST" class="mt-4">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                                <input type="hidden" name="update_chips" value="1">
                                                <?php
                                                $selected_chips = [];
                                                if (!empty($item['chip_selections'])) {
                                                    $selections = explode(',', $item['chip_selections']);
                                                    foreach ($selections as $selection) {
                                                        list($title, $option) = explode(':', $selection);
                                                        $selected_chips[$title] = $option;
                                                    }
                                                }
                                                foreach ($chips[$item['product_id']] as $chip_title => $options):
                                                ?>
                                                    <div class="mb-2">
                                                        <label class="text-sm font-semibold"><?php echo htmlspecialchars($chip_title); ?>:</label>
                                                        <div class="flex flex-wrap mt-1">
                                                            <?php foreach ($options as $option): ?>
                                                                <span class="chip<?php echo (isset($selected_chips[$chip_title]) && $selected_chips[$chip_title] === $option['option_value']) ? ' selected' : ''; ?>" 
                                                                      data-chip-title="<?php echo htmlspecialchars($chip_title); ?>" 
                                                                      data-chip-option="<?php echo htmlspecialchars($option['option_value']); ?>">
                                                                    <?php echo htmlspecialchars($option['option_value']); ?>
                                                                    <?php if ($option['additional_price'] > 0): ?>
                                                                        (+$<?php echo number_format($option['additional_price'], 2); ?>)
                                                                    <?php endif; ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <input type="hidden" name="chip_<?php echo htmlspecialchars($chip_title); ?>" 
                                                               value="<?php echo isset($selected_chips[$chip_title]) ? htmlspecialchars($selected_chips[$chip_title]) : ''; ?>">
                                                    </div>
                                                <?php endforeach; ?>
                                                <button type="submit" class="mt-2 bg-eww-green text-white px-4 py-1 rounded-2xl font-semibold hover:bg-opacity-90 transition-all">
                                                    Update Options
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center mt-4 sm:mt-0">
                                        <form method="POST" class="flex items-center mr-6">
                                            <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="update_quantity" value="1">
                                            <button type="button" class="quantity-btn decrease">-</button>
                                            <input type="number" name="quantity" min="1" value="<?php echo $item['quantity']; ?>" class="quantity-input mx-2">
                                            <button type="button" class="quantity-btn increase">+</button>
                                            <button type="submit" class="hidden">Update</button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="remove_item" value="1">
                                            <button type="submit" class="text-red-500 hover:text-red-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="lg:w-1/3">
                    <div class="bg-white rounded-2xl shadow-lg p-6 sticky top-32">
                        <h2 class="text-xl font-heading font-bold mb-6">Order Summary</h2>
                        <div class="space-y-4 mb-6">
                            <div class="flex justify-between">
                                <span>Subtotal (<?php echo $cart_count; ?> items)</span>
                                <span>$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Shipping</span>
                                <span class="<?php echo $total_shipping == 0 ? 'text-eww-green' : ''; ?>">
                                    <?php echo $total_shipping == 0 ? 'Free' : '$' . number_format($total_shipping, 2); ?>
                                </span>
                            </div>
                        </div>
                        <div class="border-t border-gray-200 pt-4 mb-6">
                            <div class="flex justify-between text-lg font-heading font-bold">
                                <span>Total</span>
                                <span>$<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>
                        <button id="payButton" class="w-full bg-eww-green text-white py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all mb-4">
                            Proceed to Checkout
                        </button>
                        <div id="paymentResponse" class="hidden text-red-500 mt-2"></div>
                        <div class="text-center">
                            <p class="text-sm text-gray-600 mb-2">or</p>
                            <a href="index.php#shop" class="text-eww-green font-semibold flex items-center justify-center group">
                                Continue Shopping
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1 transform group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                        <div class="mt-6 p-4 bg-eww-light rounded-lg">
                            <div class="flex items-start">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-green mt-0.5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732l-3.354 1.935-1.18 4.455a1 1 0 01-1.933 0L9.854 12.2 6.5 10.266a1 1 0 010-1.732l3.354-1.935 1.18-4.455A1 1 0 0112 2z" clip-rule="evenodd" />
                                </svg>
                                <div>
                                    <p class="text-sm font-semibold">Elite Member Benefits</p>
                                    <p class="text-xs text-gray-600">Free shipping on orders over $50 & exclusive discounts</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Security Badges -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 mt-6">
                        <h3 class="text-lg font-heading font-bold mb-4">Secure Checkout</h3>
                        <div class="flex justify-between items-center">
                            <img src="https://cdn-icons-png.flaticon.com/512/196/196578.png" alt="SSL Secure" class="h-8">
                            <img src="https://cdn-icons-png.flaticon.com/512/196/196561.png" alt="Visa" class="h-8">
                            <img src="https://cdn-icons-png.flaticon.com/512/196/196565.png" alt="Mastercard" class="h-8">
                            <img src="https://cdn-icons-png.flaticon.com/512/196/196566.png" alt="PayPal" class="h-8">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-eww-dark text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div>
                    <a href="index.php" class="flex items-center space-x-2 mb-6">
                        <img class="logo" src="https://www.kalonoid.com/uploads/logo.png" alt="logo">
                        <span class="text-white font-heading font-bold text-xl">EliteWinnersWorldwide</span>
                    </a>
                    <p class="text-gray-400 mb-6">Where talent meets breakthrough. Professional soccer training and apparel for the next generation of champions.</p>
                    <div class="flex space-x-4">
                        <a href="#" aria-label="Facebook">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400 hover:text-eww-gold" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/>
                            </svg>
                        </a>
                        <a href="#" aria-label="Instagram">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400 hover:text-eww-gold" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                            </svg>
                        </a>
                        <a href="#" aria-label="Twitter">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400 hover:text-eww-gold" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                            </svg>
                        </a>
                    </div>
                </div>
                <div>
                    <h3 class="font-heading font-bold text-lg mb-6">Quick Links</h3>
                    <ul class="space-y-3">
                        <li><a href="index.php" class="text-gray-400 hover:text-eww-gold transition-colors">Home</a></li>
                        <li><a href="index.php#services" class="text-gray-400 hover:text-eww-gold transition-colors">Services</a></li>
                        <li><a href="index.php#shop" class="text-gray-400 hover:text-eww-gold transition-colors">Shop</a></li>
                        <li><a href="index.php#about" class="text-gray-400 hover:text-eww-gold transition-colors">About Us</a></li>
                        <li><a href="index.php#testimonials" class="text-gray-400 hover:text-eww-gold transition-colors">Testimonials</a></li>
                        <li><a href="index.php#contact" class="text-gray-400 hover:text-eww-gold transition-colors">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-heading font-bold text-lg mb-6">Customer Service</h3>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-eww-gold transition-colors">Track Order</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-eww-gold transition-colors">Shipping Information</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-eww-gold transition-colors">Returns & Exchanges</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-eww-gold transition-colors">FAQs</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-eww-gold transition-colors">Size Guide</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-heading font-bold text-lg mb-6">Contact Info</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-gold mr-3 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="text-gray-400">Addis Ababa/ETHIOPIA</span>
                        </li>
                        <li class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-gold mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <span class="text-gray-400">+251912003855</span>
                        </li>
                        <li class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-gold mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <span class="text-gray-400">info@elitewinnersworldwide.com</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-12 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400 text-sm">© <span id="current-year"></span> EliteWinnersWorldwide. All rights reserved.</p>
                <div class="flex space-x-6 mt-4 md:mt-0">
                    <a href="#" class="text-gray-400 hover:text-eww-gold text-sm">Privacy Policy</a>
                    <a href="#" class="text-gray-400 hover:text-eww-gold text-sm">Terms of Service</a>
                    <a href="#" class="text-gray-400 hover:text-eww-gold text-sm">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        const stripe = Stripe('<?php echo STRIPE_PUBLISHABLE_KEY; ?>');
        const payButton = document.getElementById('payButton');
        const paymentResponse = document.getElementById('paymentResponse');

        // Quantity controls
        document.querySelectorAll('.quantity-btn').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('.quantity-input');
                let value = parseInt(input.value);
                if (this.classList.contains('increase')) {
                    input.value = value + 1;
                } else if (this.classList.contains('decrease') && value > 1) {
                    input.value = value - 1;
                }
                // Trigger form submission
                this.parentElement.querySelector('button[type="submit"]').click();
            });
        });

        // Chip selection
        document.querySelectorAll('.chip').forEach(chip => {
            chip.addEventListener('click', function() {
                const chipTitle = this.dataset.chipTitle;
                const chipOption = this.dataset.chipOption;
                const parent = this.closest('.flex');
                const input = parent.parentElement.querySelector(`input[name="chip_${chipTitle}"]`);
                
                // Deselect other chips in the same group
                parent.querySelectorAll('.chip').forEach(c => c.classList.remove('selected'));
                // Select the clicked chip
                this.classList.add('selected');
                input.value = chipOption;
            });
        });

        // Checkout handler with stock check
        payButton.addEventListener('click', async function() {
            setLoading(true);

            try {
                // Check if cart is empty
                const cartItems = <?php echo json_encode($cart_items); ?>;
                if (cartItems.length === 0) {
                    showError('Your cart is empty.');
                    setLoading(false);
                    return;
                }

                // Check stock availability
                const stockResponse = await fetch('check_stock.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: '<?php echo $user_id; ?>' })
                });

                const stockData = await stockResponse.json();
                if (!stockData.success) {
                    showError(stockData.message);
                    setLoading(false);
                    return;
                }

                // Check for saved shipping address
                const addressResponse = await fetch('check_shipping_address.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: '<?php echo $user_id; ?>' })
                });

                const addressData = await addressResponse.json();

                if (addressData.has_address) {
                    // If saved address exists, store it in session and proceed to checkout
                    const sessionResponse = await fetch('create_session.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({})
                    });

                    const sessionData = await sessionResponse.json();

                    if (sessionData.sessionId) {
                        stripe.redirectToCheckout({ sessionId: sessionData.sessionId })
                            .then(result => {
                                if (result.error) {
                                    showError(result.error.message);
                                    setLoading(false);
                                }
                            });
                    } else {
                        showError(sessionData.error?.message || 'Failed to create checkout session.');
                        setLoading(false);
                    }
                } else {
                    window.location.href = 'shipping.php';
                }
            } catch (error) {
                showError('An error occurred: ' + error.message);
                setLoading(false);
            }
        });

        function setLoading(isLoading) {
            if (isLoading) {
                payButton.disabled = true;
                payButton.textContent = 'Processing...';
            } else {
                payButton.disabled = false;
                payButton.textContent = 'Proceed to Checkout';
            }
        }

        function showError(message) {
            paymentResponse.classList.remove('hidden');
            paymentResponse.textContent = message;
            setTimeout(() => {
                paymentResponse.classList.add('hidden');
                paymentResponse.textContent = '';
            }, 5000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('current-year').textContent = new Date().getFullYear();

            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');

            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('-translate-x-full');
                document.body.classList.toggle('overflow-hidden');
            });

            const mobileMenuLinks = mobileMenu.querySelectorAll('a');
            mobileMenuLinks.forEach(link => {
                link.addEventListener('click', function() {
                    mobileMenu.classList.add('-translate-x-full');
                    document.body.classList.remove('overflow-hidden');
                });
            });

            const header = document.getElementById('header');
            window.addEventListener('scroll', function() {
                if (window.scrollY > 50) {
                    header.classList.add('bg-eww-dark', 'bg-opacity-90', 'backdrop-blur-sm', 'shadow-md');
                } else {
                    header.classList.remove('bg-eww-dark', 'bg-opacity-90', 'backdrop-blur-sm', 'shadow-md');
                }
            });
        });
    </script>
</body>
</html>