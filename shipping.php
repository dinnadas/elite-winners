<?php
session_start();
require_once 'config.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/stripe_errors.log');

if (!isset($_SESSION['user_id'])) {
    error_log("shipping.php: User not logged in");
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize input
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $line1 = filter_input(INPUT_POST, 'line1', FILTER_SANITIZE_STRING);
        $line2 = filter_input(INPUT_POST, 'line2', FILTER_SANITIZE_STRING);
        $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING);
        $state = filter_input(INPUT_POST, 'state', FILTER_SANITIZE_STRING);
        $postal_code = filter_input(INPUT_POST, 'postal_code', FILTER_SANITIZE_STRING);
        $country = filter_input(INPUT_POST, 'country', FILTER_SANITIZE_STRING);
        $save_address = isset($_POST['save_address']) && $_POST['save_address'] === 'on';

        // Validate required fields
        if (empty($name) || empty($line1) || empty($city) || empty($country)) {
            throw new Exception('Name, Address Line 1, City, and Country are required.');
        }

        // Validate country (exclude SH)
        if ($country === 'SH') {
            throw new Exception('Shipping to Saint Helena (SH) is not allowed.');
        }

        // Store shipping address in session
        $shipping_address = [
            'name' => $name,
            'address' => [
                'line1' => $line1,
                'line2' => $line2 ?: null,
                'city' => $city,
                'state' => $state ?: null,
                'postal_code' => $postal_code ?: null,
                'country' => $country
            ]
        ];
        $_SESSION['shipping_address'] = $shipping_address;
        error_log("shipping.php: Shipping address stored in session: " . json_encode($shipping_address, JSON_PRETTY_PRINT));

        // Save to database if requested
        if ($save_address) {
            $stmt = $pdo->prepare("
                INSERT INTO user_shipping_addresses (user_id, shipping_address)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE shipping_address = ?, updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$_SESSION['user_id'], json_encode($shipping_address), json_encode($shipping_address)]);
            error_log("shipping.php: Shipping address saved to database for user_id: " . $_SESSION['user_id']);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("shipping.php: Error - " . $error);
    }
}

// Calculate cart count for header
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as cart_count FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_count = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    error_log("shipping.php: Cart count query failed: " . $e->getMessage());
    $cart_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Address - EliteWinnersWorldwide</title>
    <!-- OpenGraph meta tags -->
    <meta property="og:title" content="Shipping Address - EliteWinnersWorldwide">
    <meta property="og:description" content="Enter your shipping details to proceed to checkout">
    <meta property="og:image" content="https://example.com/elitewinners-shipping-preview.jpg">
    <meta property="og:url" content="https://elitewinnersworldwide.com/shipping">
    <meta property="og:type" content="website">
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚽</text></svg>">
    <!-- Preconnect to external domains -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Tailwind CSS and Stripe -->
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
        .input-focus {
            transition: all 0.2s;
        }
        .input-focus:focus {
            border-color: #0B7A4D;
            ring: 2px;
            ring-color: #0B7A4D;
            outline: none;
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
                <a href="logout.php" class="text-white hover:text-eww-gold transition-colors ml-4">Logout</a>
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
                    <a href="logout.php" class="bg-eww-gold text-eww-dark px-6 py-3 rounded-2xl font-semibold text-lg">Logout</a>
                    <a href="cart.php" class="border border-eww-gold text-eww-gold px-6 py-3 rounded-2xl font-semibold text-lg">View Cart</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Page Header -->
    <section class="relative pt-32 pb-12">
        <div class="absolute inset-0 z-0">
            <div class="absolute inset-0 hero-gradient z-10"></div>
            <img src="https://images.unsplash.com/photo-1575361204480-aadea25e6e68?ixlib=rb-4.0.3&auto=format&fit=crop&w=1974&q=80" 
                 alt="Soccer player in action" class="w-full h-full object-cover">
        </div>
        <div class="container mx-auto px-4 relative z-20">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <h1 class="text-3xl md:text-4xl font-heading font-bold text-white mb-4 md:mb-0">Shipping Address</h1>
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="index.php" class="text-gray-400 hover:text-white">Home</a></li>
                        <li class="flex items-center">
                            <span class="text-gray-400 mx-2">/</span>
                            <a href="cart.php" class="text-gray-400 hover:text-white">Cart</a>
                        </li>
                        <li class="flex items-center">
                            <span class="text-gray-400 mx-2">/</span>
                            <span class="text-eww-gold">Shipping</span>
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </section>

    <!-- Shipping Form Section -->
    <section class="py-12 bg-eww-light">
        <div class="container mx-auto px-4">
            <div class="max-w-lg mx-auto bg-white rounded-2xl shadow-lg p-8 animation-slide-up">
                <h2 class="text-2xl font-heading font-bold mb-6 text-eww-dark">Enter Your Shipping Details</h2>
                <?php if (isset($error)): ?>
                    <p class="text-red-500 mb-6 p-4 bg-red-50 rounded-lg"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
                <form id="shippingForm" class="space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input type="text" id="name" name="name" required class="mt-1 block w-full border border-gray-300 rounded-lg p-3 text-eww-dark input-focus">
                    </div>
                    <div>
                        <label for="line1" class="block text-sm font-medium text-gray-700">Address Line 1</label>
                        <input type="text" id="line1" name="line1" required class="mt-1 block w-full border border-gray-300 rounded-lg p-3 text-eww-dark input-focus">
                    </div>
                    <div>
                        <label for="line2" class="block text-sm font-medium text-gray-700">Address Line 2 (Optional)</label>
                        <input type="text" id="line2" name="line2" class="mt-1 block w-full border border-gray-300 rounded-lg p-3 text-eww-dark input-focus">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                            <input type="text" id="city" name="city" required class="mt-1 block w-full border border-gray-300 rounded-lg p-3 text-eww-dark input-focus">
                        </div>
                        <div>
                            <label for="state" class="block text-sm font-medium text-gray-700">State/Province (Optional)</label>
                            <input type="text" id="state" name="state" class="mt-1 block w-full border border-gray-300 rounded-lg p-3 text-eww-dark input-focus">
                        </div>
                    </div>
                    <div>
                        <label for="postal_code" class="block text-sm font-medium text-gray-700">Postal Code (Optional)</label>
                        <input type="text" id="postal_code" name="postal_code" class="mt-1 block w-full border border-gray-300 rounded-lg p-3 text-eww-dark input-focus">
                    </div>
                    <div>
                        <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                        <select id="country" name="country" required class="mt-1 block w-full border border-gray-300 rounded-lg p-3 text-eww-dark input-focus">
                            <option value="">Select a country</option>
                            <?php
                            $supported_countries = [
                                'AU' => 'Australia', 'AT' => 'Austria', 'BE' => 'Belgium', 'BR' => 'Brazil', 'BG' => 'Bulgaria',
                                'CA' => 'Canada', 'HR' => 'Croatia', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark',
                                'EE' => 'Estonia', 'FI' => 'Finland', 'FR' => 'France', 'DE' => 'Germany', 'GI' => 'Gibraltar',
                                'GR' => 'Greece', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IE' => 'Ireland', 'IT' => 'Italy',
                                'JP' => 'Japan', 'LV' => 'Latvia', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg',
                                'MY' => 'Malaysia', 'MT' => 'Malta', 'MX' => 'Mexico', 'NL' => 'Netherlands', 'NZ' => 'New Zealand',
                                'NO' => 'Norway', 'PL' => 'Poland', 'PT' => 'Portugal', 'RO' => 'Romania', 'SG' => 'Singapore',
                                'SK' => 'Slovakia', 'SI' => 'Slovenia', 'ES' => 'Spain', 'SE' => 'Sweden', 'CH' => 'Switzerland',
                                'TH' => 'Thailand', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States'
                            ];
                            foreach ($supported_countries as $code => $name) {
                                echo "<option value='$code'>$name</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="save_address" name="save_address" class="h-4 w-4 text-eww-green focus:ring-eww-green border-gray-300">
                        <label for="save_address" class="ml-2 text-sm font-medium text-gray-700">Save shipping address</label>
                        <span id="profile_message" class="ml-2 text-sm text-gray-500 hidden">(You'll edit this later in Profile.)</span>
                    </div>
                    <button type="submit" id="submitButton" class="w-full bg-eww-green text-white py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all">Proceed to Payment</button>
                    <div id="errorMessage" class="hidden text-red-500 mt-2 text-center"></div>
                </form>
                <div class="text-center mt-6">
                    <a href="cart.php" class="text-eww-green font-semibold flex items-center justify-center group">
                        Return to Cart
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1 transform group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
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
        const form = document.getElementById('shippingForm');
        const submitButton = document.getElementById('submitButton');
        const errorMessage = document.getElementById('errorMessage');
        const saveAddressCheckbox = document.getElementById('save_address');
        const profileMessage = document.getElementById('profile_message');

        saveAddressCheckbox.addEventListener('change', function() {
            profileMessage.classList.toggle('hidden', !this.checked);
        });

        form.addEventListener('submit', async function (evt) {
            evt.preventDefault();
            setLoading(true);

            // Validate form fields on client side
            const name = document.getElementById('name').value.trim();
            const line1 = document.getElementById('line1').value.trim();
            const city = document.getElementById('city').value.trim();
            const country = document.getElementById('country').value.trim();

            if (!name || !line1 || !city || !country) {
                showError('Please fill in all required fields.');
                setLoading(false);
                return;
            }

            if (country === 'SH') {
                showError('Shipping to Saint Helena (SH) is not allowed.');
                setLoading(false);
                return;
            }

            // Submit form data to shipping.php for server-side validation and session storage
            const formData = new FormData(form);
            try {
                const response = await fetch('shipping.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    showError(errorData.error?.message || 'An error occurred while saving the shipping address.');
                    setLoading(false);
                    return;
                }

                // Call create_session.php to get Stripe session
                const sessionResponse = await fetch('create_session.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({})
                });

                const sessionData = await sessionResponse.json();

                if (sessionData.sessionId) {
                    // Redirect to Stripe Checkout
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
            } catch (error) {
                showError('An error occurred: ' + error.message);
                setLoading(false);
            }
        });

        function setLoading(isLoading) {
            if (isLoading) {
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';
            } else {
                submitButton.disabled = false;
                submitButton.textContent = 'Proceed to Payment';
            }
        }

        function showError(message) {
            errorMessage.classList.remove('hidden');
            errorMessage.textContent = message;
            setTimeout(() => {
                errorMessage.classList.add('hidden');
                errorMessage.textContent = '';
            }, 5000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Set current year in footer
            document.getElementById('current-year').textContent = new Date().getFullYear();

            // Mobile menu toggle
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');

            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('-translate-x-full');
                document.body.classList.toggle('overflow-hidden');
            });

            // Close mobile menu when clicking links
            const mobileMenuLinks = mobileMenu.querySelectorAll('a');
            mobileMenuLinks.forEach(link => {
                link.addEventListener('click', function() {
                    mobileMenu.classList.add('-translate-x-full');
                    document.body.classList.remove('overflow-hidden');
                });
            });

            // Sticky header
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