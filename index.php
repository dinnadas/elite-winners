<?php
include 'config.php';
session_start();

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $list = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            $first = filter_var($list[0], FILTER_VALIDATE_IP);
            if ($first) $ip = $first;
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $client_ip = filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP);
            if ($client_ip) $ip = $client_ip;
        }

        $today = date('Y-m-d');
        $now   = date('Y-m-d H:i:s');

        $country = 'Unknown';
        if ($ip !== '127.0.0.1' && $ip !== '::1') {
            $context = stream_context_create(['http' => ['timeout' => 2]]);
            $data = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country", false, $context);
            if ($data) {
                $json = json_decode($data, true);
                if ($json && isset($json['country'])) {
                    $country = $json['country'];
                }
            }
        }

        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $check = $pdo->prepare(
            "SELECT 1 FROM site_visitors WHERE ip_address = ? AND visit_date = ? LIMIT 1"
        );
        $check->execute([$ip, $today]);

        if ($check->fetchColumn() === false) {
            $insert = $pdo->prepare(
                "INSERT INTO site_visitors 
                 (ip_address, visit_date, visit_time, country, user_agent) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            $insert->execute([$ip, $today, $now, $country, $user_agent]);
        }
    } catch (Throwable $e) {
        error_log('Visitor tracking error: ' . $e->getMessage());
    }
}

$is_logged_in = isset($_SESSION['user_id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_destroy();
    header("Location: register.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart']) && $is_logged_in) {
    try {
        $product_id = (int)$_POST['product_id'];
        $user_id = (int)$_SESSION['user_id'];
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        if ($quantity < 1) {
            header("Location: index.php?status=error&message=" . urlencode("Invalid quantity."));
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, stock FROM products WHERE id = ? AND is_visible = 1");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            header("Location: index.php?status=error&message=" . urlencode("Product not found or unavailable."));
            exit;
        }
        if ($product['stock'] > 0 && $quantity > $product['stock']) {
            $pdo->rollBack();
            header("Location: index.php?status=error&message=" . urlencode("Requested quantity exceeds available stock."));
            exit;
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $existing_cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing_cart_item) {
            $new_quantity = $existing_cart_item['quantity'] + $quantity;
            if ($product['stock'] > 0 && $new_quantity > $product['stock']) {
                $pdo->rollBack();
                header("Location: index.php?status=error&message=" . urlencode("Total quantity exceeds available stock."));
                exit;
            }
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->execute([$new_quantity, $existing_cart_item['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $product_id, $quantity]);
        }
        $pdo->commit();
        header("Location: index.php?status=success&message=" . urlencode("Item added to cart successfully."));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Cart insert/update error: ' . $e->getMessage());
        header("Location: index.php?status=error&message=" . urlencode("Failed to add item to cart: Database error."));
        exit;
    } catch (Exception $e) {
        error_log('Cart error: ' . $e->getMessage());
        header("Location: index.php?status=error&message=" . urlencode("An unexpected error occurred."));
        exit;
    }
}
$cart_count = 0;
if ($is_logged_in) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_count = (int)$stmt->fetchColumn();
   
    $stmt = $pdo->prepare("SELECT first_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $user_initial = strtoupper(substr($user['first_name'] ?? '', 0, 1));
}
$status_message = '';
$status = '';
if (isset($_GET['status']) && isset($_GET['message'])) {
    $status = $_GET['status'];
    $status_message = urldecode($_GET['message']);
}
$stmt = $pdo->prepare("SELECT id, image, title, description, price, label, discount_percent FROM products WHERE is_visible = 1");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EliteWinnersWorldwide - Professional Soccer Training & Apparel</title>
    <meta name="description" content="World-class soccer training programs and premium apparel. Where talent meets breakthrough.">
    <meta property="og:title" content="EliteWinnersWorldwide - Professional Soccer Training">
    <meta property="og:description" content="World-class soccer training programs and premium apparel.">
    <meta property="og:image" content="https://example.com/elitewinners-preview.jpg">
    <meta property="og:url" content="https://elitewinnersworldwide.com">
    <meta property="og:type" content="website">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚽</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
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
                        'fade-in': 'fadeIn 0.5s ease-out forwards',
                        'slide-up': 'slideUp 0.6s ease-out forwards',
                        'dropdown': 'dropdown 0.3s ease-out forwards',
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
                        dropdown: {
                            '0%': { transform: 'translateY(-10px)', opacity: '0' },
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
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .hero-gradient {
            background: linear-gradient(135deg, rgba(11, 122, 77, 0.9) 0%, rgba(26, 26, 26, 0.85) 100%);
        }
        .logo {
            height: 50px;
            width: 50px;
            transform: scale(2.1);
        }
        .modal-blur {
            backdrop-filter: blur(3px);
        }
        .profile-dropdown {
            display: none;
        }
        .profile-dropdown.active {
            display: block;
            animation: dropdown 0.3s ease-out forwards;
        }
        #header.scrolled {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            background: rgba(26, 26, 26, 0.15);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-eww-light text-eww-dark font-body antialiased">
    <header class="fixed w-full z-50 transition-all duration-300" id="header">
        <nav class="container mx-auto px-4 py-4 flex justify-between items-center">
            <a href="#" class="flex items-center space-x-2 z-60">
                <img class="logo" src="logo.png" alt="logo">
                <span class="text-white font-heading font-bold text-xl hidden md:block">EliteWinnersWorldwide</span>
            </a>
            <div class="hidden md:flex items-center space-x-8">
                <a href="#home" class="text-white hover:text-eww-gold transition-colors">Home</a>
                <a href="#services" class="text-white hover:text-eww-gold transition-colors">Services</a>
                <a href="#subshop" class="text-white hover:text-eww-gold transition-colors">Shop</a>
                <a href="#about" class="text-white hover:text-eww-gold transition-colors">About</a>
                <a href="#testimonials" class="text-white hover:text-eww-gold transition-colors">Testimonials</a>
                <a href="#contact" class="text-white hover:text-eww-gold transition-colors">Contact</a>
                <a href="cart.php" class="text-white ml-4 relative" aria-label="Shopping cart">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span class="absolute -top-2 -right-2 bg-eww-gold text-eww-dark rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold" id="cart-count"><?php echo $cart_count; ?></span>
                </a>
                <?php if ($is_logged_in): ?>
                    <div class="relative">
                        <button id="profile-button" class="flex items-center text-white hover:text-eww-gold transition-colors" aria-label="Profile menu">
                            <div class="w-8 h-8 rounded-full bg-eww-gold text-eww-dark flex items-center justify-center font-semibold">
                                <?php echo htmlspecialchars($user_initial); ?>
                            </div>
                        </button>
                        <div id="profile-dropdown" class="profile-dropdown absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-lg py-2 z-50">
                            <a href="profile.php" class="block px-4 py-2 text-eww-dark hover:bg-eww-light">My Profile</a>
                            <a href="support.php" class="block px-4 py-2 text-eww-dark hover:bg-eww-light">Support</a>
                            <form method="POST">
                                <button type="submit" name="logout" class="block w-full text-left px-4 py-2 text-eww-dark hover:bg-eww-light">Logout</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="register.php" class="bg-eww-gold text-eww-dark px-4 py-2 rounded-2xl font-semibold hover:bg-opacity-90 transition-all ml-4">Sign Up</a>
                <?php endif; ?>
            </div>
            <div class="flex items-center space-x-4 md:hidden z-60">
                <a href="cart.php" class="text-white relative" aria-label="Shopping cart">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span class="absolute -top-2 -right-2 bg-eww-gold text-eww-dark rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold"><?php echo $cart_count; ?></span>
                </a>
                <button class="text-white" id="mobile-menu-button" aria-label="Toggle mobile menu">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                    </svg>
                </button>
            </div>
        </nav>
        <div class="fixed top-0 left-0 bottom-0 w-80 bg-eww-dark z-50 transform -translate-x-full transition-transform duration-300 md:hidden overflow-y-auto" id="mobile-menu">
            <div class="p-6 flex flex-col h-full">
                <button id="close-mobile-menu" class="absolute top-4 right-4 text-white hover:text-eww-gold transition-colors z-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="flex flex-col space-y-6 mt-12">
                    <a href="#home" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Home</a>
                    <a href="#services" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Services</a>
                    <a href="#shop" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Shop</a>
                    <a href="#about" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">About</a>
                    <a href="#testimonials" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Testimonials</a>
                    <a href="#contact" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Contact</a>
                    <a href="cart.php" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Cart</a>
                    <?php if ($is_logged_in): ?>
                        <a href="profile.php" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">My Profile</a>
                        <a href="support.php" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Support</a>
                        <form method="POST">
                            <button type="submit" name="logout" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Logout</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="mt-auto pt-8 flex space-x-4">
                    <?php if (!$is_logged_in): ?>
                        <a href="register.php" class="bg-eww-gold text-eww-dark px-6 py-3 rounded-2xl font-semibold text-lg">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div id="mobile-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden modal-blur"></div>
    </header>
    <section id="home" class="relative h-screen flex items-center justify-center bg-eww-dark overflow-hidden">
        <div class="absolute inset-0 z-0">
            <div class="absolute inset-0 hero-gradient z-10"></div>
            <img src="https://elite.kalonoid.com/background.jpg"
                 alt="Soccer player in action" class="w-full h-full object-cover">
        </div>
        <div class="container mx-auto px-4 z-20 text-center text-white">
            <h1 class="text-4xl md:text-6xl lg:text-7xl font-heading font-bold mb-6 animate-slide-up">
                Where Talent Meets <span class="text-eww-gold">Breakthrough</span>
            </h1>
            <p class="text-xl md:text-2xl max-w-3xl mx-auto mb-10 opacity-90 animate-slide-up" style="animation-delay: 0.2s;">
                Elite training programs and premium apparel designed to elevate your game to professional standards.
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-4 animate-slide-up" style="animation-delay: 0.4s;">
                <?php if (!$is_logged_in): ?>
                    <a href="register.php" class="bg-eww-gold text-eww-dark font-heading font-bold px-8 py-4 rounded-2xl text-lg hover:bg-opacity-90 transition-all transform hover:-translate-y-1">
                        Sign Up
                    </a>
                <?php endif; ?>
                <a href="#shop" class="bg-transparent border-2 border-white text-white font-heading font-bold px-8 py-4 rounded-2xl text-lg hover:bg-white hover:text-eww-dark transition-all transform hover:-translate-y-1">
                    Shop Products
                </a>
            </div>
        </div>
        <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 z-20 animate-bounce">
            <a href="#news" class="text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                </svg>
            </a>
        </div>
    </section>
<section id="news" class="py-20 bg-eww-light">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-3xl md:text-4xl font-heading font-bold mb-4">Latest <span class="text-eww-green">News</span> & Updates</h2>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">Stay informed with the latest from EliteWinnersWorldwide - training tips, success stories, and industry insights.</p>
        </div>

        <?php
        try {
            $stmt = $pdo->query("SELECT * FROM news ORDER BY publication_datetime DESC LIMIT 3");
            $news_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('News fetch error: ' . $e->getMessage());
            $news_items = [];
        }
        ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php if (empty($news_items)): ?>
                <div class="col-span-full text-center py-12">
                    <p class="text-gray-500">No news available at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($news_items as $news): ?>
                    <article class="bg-white rounded-2xl overflow-hidden shadow-lg transition-all duration-300 hover:shadow-xl hover:-translate-y-2">
                        <div class="h-48 overflow-hidden relative">
                            <img src="<?php echo htmlspecialchars($news['image']); ?>"
                                 alt="<?php echo htmlspecialchars($news['title']); ?>"
                                 class="w-full h-full object-cover transition-transform duration-500 hover:scale-105">
                            <div class="absolute top-4 left-4">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?php
                                    $type_map = [
                                        'training' => 'bg-eww-green text-white',
                                        'products' => 'bg-eww-gold text-eww-dark',
                                        'success' => 'bg-purple-500 text-white',
                                        'announcement' => 'bg-blue-500 text-white',
                                        'event' => 'bg-orange-500 text-white',
                                        'update' => 'bg-yellow-500 text-eww-dark'
                                    ];
                                    echo $type_map[strtolower($news['type'])] ?? 'bg-gray-500 text-white';
                                ?>">
                                    <?php echo ucfirst(htmlspecialchars($news['type'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center text-sm text-gray-500 mb-3">
                                <span><?php echo date('F j, Y', strtotime($news['publication_datetime'])); ?></span>
                                <span class="mx-2">•</span>
                                <span><?php echo htmlspecialchars($news['read_time']); ?></span>
                            </div>
                            <h3 class="text-xl font-heading font-bold mb-3 line-clamp-2">
                                <?php echo htmlspecialchars($news['title']); ?>
                            </h3>
                            <p class="text-gray-600 mb-4 line-clamp-3">
                                <?php echo htmlspecialchars($news['description']); ?>
                            </p>
                            <?php if (!empty(trim($news['additional_description']))): ?>
                                <button onclick="openNewsModal(<?php echo htmlspecialchars(json_encode([
                                    'title' => $news['title'],
                                    'image' => $news['image'],
                                    'type' => ucfirst($news['type']),
                                    'date' => date('F j, Y g:i A', strtotime($news['publication_datetime'])),
                                    'read_time' => $news['read_time'],
                                    'description' => $news['description'],
                                    'additional_description' => $news['additional_description']
                                ])); ?>)" class="text-eww-green font-semibold flex items-center group">
                                    Read more
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1 transform group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="text-center mt-12">
            <a href="news.php" class="inline-flex items-center border-2 border-eww-green text-eww-green font-heading font-bold px-6 py-3 rounded-2xl hover:bg-eww-green hover:text-white transition-all group">
                View All News
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2 transform group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </div>
    </div>
</section>

<div id="newsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4 modal-blur">
    <div class="bg-white rounded-2xl shadow-xl max-w-3xl w-full max-h-screen overflow-y-auto">
        <div class="relative">
            <img id="modalImage" src="" alt="" class="w-full h-64 object-cover rounded-t-2xl">
            <button onclick="closeNewsModal()" class="absolute top-4 right-4 bg-white rounded-full p-2 shadow-md hover:shadow-lg transition-shadow">
                <svg class="h-5 w-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-3">
                <span id="modalType" class="px-3 py-1 rounded-full text-xs font-semibold"></span>
                <span id="modalDate" class="text-sm text-gray-500"></span>
                <span id="modalReadTime" class="text-sm text-gray-500"></span>
            </div>
            <h2 id="modalTitle" class="text-2xl font-heading font-bold mb-4"></h2>
            <div class="prose prose-sm max-w-none text-gray-700 mb-6" id="modalDescription"></div>
            <div class="prose prose-sm max-w-none text-gray-700" id="modalAdditional"></div>
        </div>
    </div>
</div>
    <section id="services" class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-heading font-bold mb-4">Our <span class="text-eww-green">Training Programs</span></h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Professional coaching designed to develop skills, build confidence, and unlock potential at every level.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="bg-eww-light rounded-2xl overflow-hidden shadow-lg transition-all duration-300 hover:shadow-xl hover:-translate-y-2">
                    <div class="h-48 overflow-hidden">
                        <img src="https://elite.kalonoid.com/uploads/elite-one-on-one.jpg"
                             alt="One-on-One Training" class="w-full h-full object-cover">
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-heading font-bold mb-2">One-on-One Training</h3>
                        <p class="text-gray-600 mb-4">Personalized coaching focused on your specific development needs.</p>
                        <ul class="mb-6 space-y-2">
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-green mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Customized training plans
                            </li>
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-green mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Video analysis
                            </li>
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-green mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Progress tracking
                            </li>
                        </ul>
                        <button class="w-full bg-eww-green text-white py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all">
                            Schedule a Session
                        </button>
                    </div>
                </div>
                <div class="bg-eww-light rounded-2xl overflow-hidden shadow-lg transition-all duration-300 hover:shadow-xl hover:-translate-y-2">
                    <div class="h-48 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1516466723877-e4ec1d736c8a?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=874&q=80"
                             alt="Small Group Sessions" class="w-full h-full object-cover">
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-heading font-bold mb-2">Small Group Sessions</h3>
                        <p class="text-gray-600 mb-4">Train with peers in focused groups to develop teamwork and competitive skills.</p>
                        <ul class="mb-6 space-y-2">
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-green mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Team dynamics development
                            </li>
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-green mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Competitive scenarios
                            </li>
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-green mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Affordable premium coaching
                            </li>
                        </ul>
                        <button class="w-full bg-eww-green text-white py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all">
                            Schedule a Session
                        </button>
                    </div>
                </div>
                <div class="bg-eww-light rounded-2xl overflow-hidden shadow-lg transition-all duration-300 hover:shadow-xl hover:-translate-y-2">
                    <div class="h-48 overflow-hidden">
                        <img src="https://kalonoid.com/uploads/goalkeeper.jpg"
                             alt="Goalkeeper Clinics" class="w-full h-full object-cover">
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-heading font-bold mb-2">Goalkeeper Clinics</h3>
                        <p class="text-gray-600 mb-4">Specialized training for goalkeepers focusing on technique, positioning, and reflexes.</p>
                        <ul class="mb-6 space-y-2">
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-green mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Shot stopping techniques
                            </li>
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-green mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Distribution skills
                            </li>
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-green mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Aerial command training
                            </li>
                        </ul>
                        <button class="w-full bg-eww-green text-white py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all">
                            Schedule a Session
                        </button>
                    </div>
                </div>
                <div class="bg-eww-light rounded-2xl overflow-hidden shadow-lg transition-all duration-300 hover:shadow-xl hover:-translate-y-2">
                    <div class="h-48 overflow-hidden">
                        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcReqQYJsdXJ7mr-gXfIzSl20BQk7-3R1bswUw&s"
                             alt="Pro Recruitment Prep" class="w-full h-full object-cover">
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-heading font-bold mb-2">Pro Recruitment Prep</h3>
                        <p class="text-gray-600 mb-4">Comprehensive program designed to prepare athletes for professional opportunities.</p>
                        <ul class="mb-6 space-y-2">
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-green mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Scouting network access
                            </li>
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-green mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Highlight reel production
                            </li>
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-green mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Contract negotiation guidance
                            </li>
                        </ul>
                        <button class="w-full bg-eww-green text-white py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all">
                            Schedule a Session
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section id="shop" class="py-20 bg-eww-light">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-heading font-bold mb-4">Elite <span class="text-eww-green">Performance Apparel</span></h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Premium quality gear designed for performance, comfort, and style.</p>
            </div>
            <section id="subshop">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($products as $product): ?>
                        <?php
                        $original_price = number_format($product['price'], 2);
                        $discount_percent = $product['discount_percent'];
                        $discounted_price = $discount_percent > 0 ? number_format($product['price'] * (1 - $discount_percent / 100), 2) : null;
                        ?>
                        <div class="bg-white rounded-2xl overflow-hidden shadow-lg transition-all duration-300 hover:shadow-xl hover:-translate-y-2">
                            <div class="h-64 overflow-hidden relative">
                                <img src="<?php echo htmlspecialchars($product['image']); ?>"
                                     alt="<?php echo htmlspecialchars($product['title']); ?>"
                                     class="w-full h-full object-cover transition-transform duration-500 hover:scale-105">
                                <?php if ($product['label'] === 'new'): ?>
                                    <span class="absolute top-4 right-4 bg-eww-gold text-eww-dark px-3 py-1 rounded-full text-sm font-semibold">New</span>
                                <?php endif; ?>
                                <?php if ($discount_percent > 0): ?>
                                    <span class="absolute top-4 left-4 bg-red-500 text-white px-3 py-1 rounded-full text-sm font-semibold"><?php echo number_format($discount_percent, 0); ?>% OFF</span>
                                <?php endif; ?>
                            </div>
                            <div class="p-6">
                                <h3 class="text-xl font-heading font-bold mb-2"><?php echo htmlspecialchars($product['title']); ?></h3>
                                <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($product['description']); ?></p>
                                <div class="flex justify-between items-center">
                                    <div class="text-2xl font-bold text-eww-green">
                                        <?php if ($discount_percent > 0): ?>
                                            <span class="line-through text-gray-500 mr-2">$<?php echo $original_price; ?></span>
                                            <span>$<?php echo $discounted_price; ?></span>
                                        <?php else: ?>
                                            <span>$<?php echo $original_price; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($is_logged_in): ?>
                                        <form method="POST" class="add-to-cart-form">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <input type="hidden" name="add_to_cart" value="1">
                                            <button type="submit" class="bg-eww-green text-white px-4 py-2 rounded-2xl font-semibold hover:bg-opacity-90 transition-all">
                                                Add to Cart
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="register.php" class="bg-eww-green text-white px-4 py-2 rounded-2xl font-semibold hover:bg-opacity-90 transition-all">
                                            Add to Cart
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <div class="text-center mt-12">
                <a href="#" class="inline-block border-2 border-eww-green text-eww-green font-heading font-bold px-8 py-3 rounded-2xl hover:bg-eww-green hover:text-white transition-all">
                    View All Products
                </a>
            </div>
        </div>
    </section>
    <section id="about" class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="flex flex-col lg:flex-row items-center gap-12">
                <div class="lg:w-1/2">
                    <img src="https://images.unsplash.com/photo-1529900748604-07564a03e7a6?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=870&q=80"
                         alt="EliteWinnersWorldwide Team" class="rounded-2xl shadow-lg w-full">
                </div>
                <div class="lg:w-1/2">
                    <h2 class="text-3xl md:text-4xl font-heading font-bold mb-6">Our <span class="text-eww-green">Story</span></h2>
                    <p class="text-lg text-gray-600 mb-6">
                        We created Elite Winners — a place where the kids are the bosses and individual attention is the foundation Our mission is to develop not just better players, but better people through the beautiful game of soccer.
                    </p>
                    <p class="text-lg text-gray-600 mb-8">
                        Inspired by the biblical story of David and Goliath, our "Winners Perazim" philosophy emphasizes that breakthrough comes through preparation, perseverance, and faith in one's abilities.
                    </p>
                    <div class="grid grid-cols-2 gap-6 mb-8">
                        <div class="text-center p-4 bg-eww-light rounded-2xl">
                            <span class="text-4xl font-heading font-bold text-eww-green">2+</span>
                            <p class="text-gray-600">Years Experience</p>
                        </div>
                        <div class="text-center p-4 bg-eww-light rounded-2xl">
                            <span class="text-4xl font-heading font-bold text-eww-green">500+</span>
                            <p class="text-gray-600">Players Trained</p>
                        </div>
                        <div class="text-center p-4 bg-eww-light rounded-2xl">
                            <span class="text-4xl font-heading font-bold text-eww-green">12</span>
                            <p class="text-gray-600">Countries</p>
                        </div>
                        <div class="text-center p-4 bg-eww-light rounded-2xl">
                            <span class="text-4xl font-heading font-bold text-eww-green">8-25</span>
                            <p class="text-gray-600">Age Range</p>
                        </div>
                    </div>
                    <h3 class="text-xl font-heading font-bold mb-4">Our Core Values</h3>
                    <div class="flex flex-wrap gap-3 mb-8">
                        <span class="bg-eww-green bg-opacity-10 text-eww-green px-4 py-2 rounded-full font-semibold">Resilience</span>
                        <span class="bg-eww-green bg-opacity-10 text-eww-green px-4 py-2 rounded-full font-semibold">Breakthrough</span>
                        <span class="bg-eww-green bg-opacity-10 text-eww-green px-4 py-2 rounded-full font-semibold">Excellence</span>
                        <span class="bg-eww-green bg-opacity-10 text-eww-green px-4 py-2 rounded-full font-semibold">Faith-driven</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section id="booking" class="py-20 bg-eww-green">
        <div class="container mx-auto px-4">
            <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="md:flex">
                    <div class="md:w-1/3 bg-eww-dark p-8 text-white hidden md:block">
                        <h3 class="text-2xl font-heading font-bold mb-6">Book Your Session</h3>
                        <p class="mb-6">Take the first step toward unlocking your potential with our professional training programs.</p>
                        <ul class="space-y-4">
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-gold mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Free 15-minute consultation
                            </li>
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-gold mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Personalized training assessment
                            </li>
                            <li class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-gold mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Flexible scheduling
                            </li>
                        </ul>
                    </div>
                    <div class="md:w-2/3 p-8">
                        <h3 class="text-2xl font-heading font-bold mb-6 text-center md:text-left">Training Inquiry Form</h3>
                        <div id="notification" class="hidden mb-4 p-4 rounded-2xl text-white max-w-full">
                            <span id="notification-message"></span>
                            <button id="close-notification" class="ml-4 text-white hover:text-gray-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <form id="booking-form">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                    <input type="text" id="name" name="name" required class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-green focus:border-eww-green">
                                </div>
                                <div>
                                    <label for="age" class="block text-sm font-medium text-gray-700 mb-1">Age</label>
                                    <input type="number" id="age" name="age" min="8" max="25" required class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-green focus:border-eww-green">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                <input type="email" id="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-green focus:border-eww-green">
                            </div>
                            <div class="mb-4">
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-green focus:border-eww-green">
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="session-type" class="block text-sm font-medium text-gray-700 mb-1">Session Type</label>
                                    <select id="session-type" name="session-type" required class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-green focus:border-eww-green">
                                        <option value="">Select a session</option>
                                        <option value="one-on-one">One-on-One Training</option>
                                        <option value="group">Small Group Sessions</option>
                                        <option value="goalkeeper">Goalkeeper Clinics</option>
                                        <option value="pro">Pro Recruitment Prep</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="preferred-date" class="block text-sm font-medium text-gray-700 mb-1">Preferred Date</label>
                                    <input type="date" id="preferred-date" name="preferred-date" class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-green focus:border-eww-green">
                                </div>
                            </div>
                            <div class="mb-6">
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
                                <textarea id="notes" name="notes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-green focus:border-eww-green"></textarea>
                            </div>
                            <button type="submit" id="submit-button" class="w-full bg-eww-green text-white py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all flex items-center justify-center">
                                <span id="button-text">Submit Booking Request</span>
                                <svg id="loading-spinner" class="hidden animate-spin h-5 w-5 ml-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                            <p class="text-xs text-center text-gray-500 mt-4">* Free 15-minute consultation included with all session bookings</p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section id="testimonials" class="py-20 bg-eww-light">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-heading font-bold mb-4">Success <span class="text-eww-green">Stories</span></h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Hear from athletes who have transformed their game through our programs.</p>
            </div>
            <div class="max-w-4xl mx-auto relative">
                <div class="overflow-hidden relative">
                    <div class="flex transition-transform duration-500 ease-in-out" id="testimonial-slider">
                        <div class="w-full flex-shrink-0 px-4">
                            <div class="bg-white rounded-2xl shadow-lg p-8 md:p-12">
                                <div class="flex items-center mb-6">
                                    <div class="w-16 h-16 rounded-full overflow-hidden mr-4">
                                        <img src="https://www.kalonoid.com/uploads/dinaol.png"
                                             alt="Dinaol" class="w-full h-full object-cover">
                                    </div>
                                    <div>
                                        <h4 class="font-heading font-bold text-lg">Dinaol Enku</h4>
                                        <p class="text-eww-green">Forward, College Scholarship</p>
                                    </div>
                                </div>
                                <p class="text-gray-600 text-lg italic">
                                    "The pro recruitment program completely changed my trajectory. The coaches saw potential I didn't even know I had and helped me secure a Division I scholarship. The personalized training and video analysis took my game to another level."
                                </p>
                            </div>
                        </div>
                        <div class="w-full flex-shrink-0 px-4">
                            <div class="bg-white rounded-2xl shadow-lg p-8 md:p-12">
                                <div class="flex items-center mb-6">
                                    <div class="w-16 h-16 rounded-full overflow-hidden mr-4">
                                        <img src="https://www.kalonoid.com/uploads/kirubel.png"
                                             alt="kirubel" class="w-full h-full object-cover">
                                    </div>
                                    <div>
                                        <h4 class="font-heading font-bold text-lg">Kiruel Samuel</h4>
                                        <p class="text-eww-green">Goalkeeper, Youth National Team</p>
                                    </div>
                                </div>
                                <p class="text-gray-600 text-lg italic">
                                    "The goalkeeper clinics transformed my confidence and technique. The specialized training on positioning and reflexes helped me earn a spot on the regional team. The coaches don't just train players—they build character and resilience."
                                </p>
                            </div>
                        </div>
                        <div class="w-full flex-shrink-0 px-4">
                            <div class="bg-white rounded-2xl shadow-lg p-8 md:p-12">
                                <div class="flex items-center mb-6">
                                    <div class="w-16 h-16 rounded-full overflow-hidden mr-4">
                                        <img src="https://www.kalonoid.com/uploads/dagm.png"
                                             alt="dagm" class="w-full h-full object-cover">
                                    </div>
                                    <div>
                                        <h4 class="font-heading font-bold text-lg">Dagm Belachew</h4>
                                        <p class="text-eww-green">Parent of U12 Player</p>
                                    </div>
                                </div>
                                <p class="text-gray-600 text-lg italic">
                                    "We've tried several training programs, but none compare to EliteWinners. The coaches genuinely care about each player's development. My son's technical skills improved dramatically, but more importantly, he gained confidence and love for the game."
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-center mt-8 space-x-4">
                    <button class="p-2 rounded-full bg-eww-green text-white slider-control" aria-label="Previous testimonial">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button class="p-2 rounded-full bg-eww-green text-white slider-control" aria-label="Next testimonial">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </section>
    <footer class="bg-eww-dark text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div>
                    <a href="#" class="flex items-center space-x-2 mb-6">
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
                        <a href="https://www.instagram.com/elitewinnersworldwide?igsh=aXlnZThzYWFpd293&utm_source=qr" aria-label="Instagram">
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
                        <li><a href="#home" class="text-gray-400 hover:text-eww-gold transition-colors">Home</a></li>
                        <li><a href="#services" class="text-gray-400 hover:text-eww-gold transition-colors">Services</a></li>
                        <li><a href="#shop" class="text-gray-400 hover:text-eww-gold transition-colors">Shop</a></li>
                        <li><a href="#about" class="text-gray-400 hover:text-eww-gold transition-colors">About Us</a></li>
                        <li><a href="#testimonials" class="text-gray-400 hover:text-eww-gold transition-colors">Testimonials</a></li>
                        <li><a href="#contact" class="text-gray-400 hover:text-eww-gold transition-colors">Contact</a></li>
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
                            <span class="text-gray-400">Seattle, Tacoma, Everet, Redmond, Issaquah</span>
                        </li>
                        <li class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-gold mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <a href="mailto:elitewinnersworldwide@gmail.com" class="text-gray-400 hover:text-eww-gold transition-colors">elitewinnersworldwide@gmail.com</a>
                        </li>
                        <li class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-eww-gold mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <a href="tel:+1234567890" class="text-gray-400 hover:text-eww-gold transition-colors">+1 (209) 565-2697</a>
                        </li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-heading font-bold text-lg mb-6">Newsletter</h3>
                    <p class="text-gray-400 mb-4">Subscribe to get the latest updates, training tips, and exclusive offers.</p>
                    <form class="flex">
                        <input type="email" placeholder="Your email" class="w-full px-4 py-2 rounded-l-2xl border-none focus:ring-2 focus:ring-eww-gold text-eww-dark" required>
                        <button type="submit" class="bg-eww-gold text-eww-dark px-4 py-2 rounded-r-2xl font-semibold hover:bg-opacity-90 transition-all">
                            Subscribe
                        </button>
                    </form>
                </div>
            </div>
            <div class="mt-12 pt-8 border-t border-gray-700 text-center">
                <p class="text-gray-400">&copy; 2023 EliteWinnersWorldwide. All rights reserved.</p>
            </div>
        </div>
    </footer>
    <?php if ($status_message): ?>
        <div id="status-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 modal-blur">
            <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-heading font-bold <?php echo $status === 'success' ? 'text-eww-green' : 'text-red-600'; ?>">
                        <?php echo $status === 'success' ? 'Success' : 'Error'; ?>
                    </h3>
                    <button id="close-modal" class="text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <p class="text-gray-600"><?php echo htmlspecialchars($status_message); ?></p>
            </div>
        </div>
    <?php endif; ?>
    <script>
    const header = document.getElementById('header');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    });
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const closeMobileMenu = document.getElementById('close-mobile-menu');
    const mobileOverlay = document.getElementById('mobile-overlay');
    mobileMenuButton.addEventListener('click', () => {
        mobileMenu.classList.remove('-translate-x-full');
        mobileOverlay.classList.remove('hidden');
    });
    if (closeMobileMenu) {
        closeMobileMenu.addEventListener('click', () => {
            mobileMenu.classList.add('-translate-x-full');
            mobileOverlay.classList.add('hidden');
        });
    }
    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', () => {
            mobileMenu.classList.add('-translate-x-full');
            mobileOverlay.classList.add('hidden');
        });
    }
    mobileMenu.querySelectorAll('a, button[type="submit"]').forEach(el => {
        el.addEventListener('click', () => {
            mobileMenu.classList.add('-translate-x-full');
            mobileOverlay.classList.add('hidden');
        });
    });

    function openNewsModal(data) {
        document.getElementById('modalImage').src = data.image;
        document.getElementById('modalTitle').textContent = data.title;
        document.getElementById('modalDescription').innerHTML = data.description.replace(/\n/g, '<br>');
        document.getElementById('modalAdditional').innerHTML = data.additional_description.replace(/\n/g, '<br>');
        
        const typeSpan = document.getElementById('modalType');
        typeSpan.textContent = data.type;
        const typeLower = data.type.toLowerCase();
        const typeStyles = {
            'training': 'bg-eww-green text-white',
            'products': 'bg-eww-gold text-eww-dark',
            'success': 'bg-purple-500 text-white',
            'announcement': 'bg-blue-500 text-white',
            'event': 'bg-orange-500 text-white',
            'update': 'bg-yellow-500 text-eww-dark'
        };
        typeSpan.className = `px-3 py-1 rounded-full text-xs font-semibold ${typeStyles[typeLower] || 'bg-gray-500 text-white'}`;
        
        document.getElementById('modalDate').textContent = data.date;
        document.getElementById('modalReadTime').textContent = data.read_time;
        document.getElementById('newsModal').classList.remove('hidden');
    }

    function closeNewsModal() {
        document.getElementById('newsModal').classList.add('hidden');
    }

    document.getElementById('newsModal').addEventListener('click', function(e) {
        if (e.target === this) closeNewsModal();
    });

    const profileButton = document.getElementById('profile-button');
    const profileDropdown = document.getElementById('profile-dropdown');
    if (profileButton && profileDropdown) {
        profileButton.addEventListener('click', () => {
            profileDropdown.classList.toggle('active');
        });
        document.addEventListener('click', (e) => {
            if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('active');
            }
        });
    }
    const slider = document.getElementById('testimonial-slider');
    const sliderControls = document.querySelectorAll('.slider-control');
    let currentSlide = 0;
    const slides = slider.children.length;
    sliderControls.forEach(button => {
        button.addEventListener('click', () => {
            const direction = button.querySelector('svg').getAttribute('aria-label').includes('Next') ? 1 : -1;
            currentSlide = (currentSlide + direction + slides) % slides;
            slider.style.transform = `translateX(-${currentSlide * 100}%)`;
        });
    });
    const bookingForm = document.getElementById('booking-form');
    const submitButton = document.getElementById('submit-button');
    const buttonText = document.getElementById('button-text');
    const loadingSpinner = document.getElementById('loading-spinner');
    const notification = document.getElementById('notification');
    const notificationMessage = document.getElementById('notification-message');
    const closeNotification = document.getElementById('close-notification');
    if (bookingForm) {
        bookingForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            submitButton.disabled = true;
            buttonText.textContent = 'Submitting...';
            loadingSpinner.classList.remove('hidden');
            const formData = new FormData(bookingForm);
            try {
                const response = await fetch('process_booking.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                notification.classList.remove('hidden');
                notification.classList.remove('bg-eww-green', 'bg-red-600');
                notification.classList.add(result.success ? 'bg-eww-green' : 'bg-red-600');
                notificationMessage.textContent = result.message;
                if (result.success) {
                    bookingForm.reset();
                }
            } catch (error) {
                console.error('Booking form error:', error);
                notification.classList.remove('hidden');
                notification.classList.remove('bg-eww-green');
                notification.classList.add('bg-red-600');
                notificationMessage.textContent = 'An error occurred. Please try again.';
            } finally {
                submitButton.disabled = false;
                buttonText.textContent = 'Submit Booking Request';
                loadingSpinner.classList.add('hidden');
            }
        });
        if (closeNotification) {
            closeNotification.addEventListener('click', () => {
                notification.classList.add('hidden');
            });
        }
    }
    const statusModal = document.getElementById('status-modal');
    const closeModalButton = document.getElementById('close-modal');
    if (statusModal && closeModalButton) {
        closeModalButton.addEventListener('click', () => {
            statusModal.classList.add('hidden');
        });
    }
    const images = document.querySelectorAll('img[data-src]');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                observer.unobserve(img);
            }
        });
    }, { rootMargin: '0px 0px 200px 0px' });
    images.forEach(img => observer.observe(img));
    const addToCartForms = document.querySelectorAll('.add-to-cart-form');
    addToCartForms.forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            const originalButtonText = submitButton.textContent;
            submitButton.textContent = 'Adding...';
            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                if (response.ok) {
                    const cartCountElement = document.getElementById('cart-count');
                    if (cartCountElement) {
                        const currentCount = parseInt(cartCountElement.textContent) || 0;
                        cartCountElement.textContent = currentCount + 1;
                    }
                    const statusModal = document.getElementById('status-modal');
                    if (statusModal) {
                        statusModal.classList.remove('hidden');
                        const statusTitle = statusModal.querySelector('h3');
                        const statusMessage = statusModal.querySelector('p');
                        statusTitle.textContent = 'Success';
                        statusTitle.classList.add('text-eww-green');
                        statusTitle.classList.remove('text-red-600');
                        statusMessage.textContent = 'Item added to cart successfully.';
                    } else {
                        window.location.reload(); 
                    }
                } else {
                    throw new Error('Server responded with status: ' + response.status);
                }
            } catch (error) {
                console.error('Add to cart error:', error);
                const statusModal = document.getElementById('status-modal');
                if (statusModal) {
                    statusModal.classList.remove('hidden');
                    const statusTitle = statusModal.querySelector('h3');
                    const statusMessage = statusModal.querySelector('p');
                    statusTitle.textContent = 'Error';
                    statusTitle.classList.add('text-red-600');
                    statusTitle.classList.remove('text-eww-green');
                    statusMessage.textContent = 'Failed to add item to cart. Please try again.';
                }
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = originalButtonText;
            }
        });
    });
    </script>
</body>
</html>