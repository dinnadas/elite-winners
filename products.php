<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $price = (float)$_POST['price'];
                $label = $_POST['label'];
                $discount_percent = (float)$_POST['discount_percent'];
                $shipping_price = (float)$_POST['shipping_price'];
                $stock = (int)$_POST['stock'];
                $is_visible = isset($_POST['is_visible']) ? 1 : 0;

                $image_path = $_POST['existing_image'] ?? '';
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    $file_type = $_FILES['image']['type'];
                    $file_size = $_FILES['image']['size'];
                    $file_tmp = $_FILES['image']['tmp_name'];
                    $file_name = uniqid() . '_' . basename($_FILES['image']['name']);
                    $upload_dir = 'uploads/';
                    $target_file = $upload_dir . $file_name;

                    if (!in_array($file_type, $allowed_types)) {
                        throw new Exception("Only JPG, JPEG, and PNG files are allowed.");
                    }
                    if ($file_size > $max_size) {
                        throw new Exception("File size exceeds 5MB limit.");
                    }

                    if (move_uploaded_file($file_tmp, $target_file)) {
                        $image_path = $target_file;
                    } else {
                        throw new Exception("Failed to upload image.");
                    }
                }

                $chip_groups = $_POST['chip_groups'] ?? [];

                if ($_POST['action'] === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO products (title, description, price, label, image, discount_percent, shipping_price, stock, is_visible) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $price, $label, $image_path, $discount_percent, $shipping_price, $stock, $is_visible]);
                    $product_id = $pdo->lastInsertId();

                    foreach ($chip_groups as $group) {
                        $chip_title = trim($group['title'] ?? '');
                        $chip_values = $group['values'] ?? [];
                        $chip_prices = $group['prices'] ?? [];

                        if (!empty($chip_title) && !empty($chip_values)) {
                            foreach ($chip_values as $index => $option) {
                                if (!empty(trim($option))) {
                                    $additional_price = isset($chip_prices[$index]) ? (float)$chip_prices[$index] : 0.00;
                                    $stmt = $pdo->prepare("INSERT INTO product_chip_options (product_id, chip_title, option_value, additional_price) VALUES (?, ?, ?, ?)");
                                    $stmt->execute([$product_id, $chip_title, trim($option), $additional_price]);
                                }
                            }
                        }
                    }
                } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
                    $id = (int)$_POST['id'];
                    $stmt = $pdo->prepare("UPDATE products SET title = ?, description = ?, price = ?, label = ?, image = ?, discount_percent = ?, shipping_price = ?, stock = ?, is_visible = ? WHERE id = ?");
                    $stmt->execute([$title, $description, $price, $label, $image_path, $discount_percent, $shipping_price, $stock, $is_visible, $id]);

                    $stmt = $pdo->prepare("DELETE FROM product_chip_options WHERE product_id = ?");
                    $stmt->execute([$id]);

                    foreach ($chip_groups as $group) {
                        $chip_title = trim($group['title'] ?? '');
                        $chip_values = $group['values'] ?? [];
                        $chip_prices = $group['prices'] ?? [];

                        if (!empty($chip_title) && !empty($chip_values)) {
                            foreach ($chip_values as $index => $option) {
                                if (!empty(trim($option))) {
                                    $additional_price = isset($chip_prices[$index]) ? (float)$chip_prices[$index] : 0.00;
                                    $stmt = $pdo->prepare("INSERT INTO product_chip_options (product_id, chip_title, option_value, additional_price) VALUES (?, ?, ?, ?)");
                                    $stmt->execute([$id, $chip_title, trim($option), $additional_price]);
                                }
                            }
                        }
                    }
                }
            } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($product['image'] && file_exists($product['image'])) {
                    unlink($product['image']);
                }
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id]);
            } elseif ($_POST['action'] === 'toggle_visibility' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $is_visible = $_POST['is_visible'] === '1' ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE products SET is_visible = ? WHERE id = ?");
                $stmt->execute([$is_visible, $id]);
            }
            header("Location: products.php");
            exit;
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

try {
    $stmt = $pdo->query("SELECT DISTINCT p.* FROM products p ORDER BY p.created_at DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chip_options = [];
    foreach ($products as $product) {
        $stmt = $pdo->prepare("SELECT chip_title, option_value, additional_price FROM product_chip_options WHERE product_id = ? ORDER BY chip_title, option_value");
        $stmt->execute([$product['id']]);
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($options as $option) {
            $chip_options[$product['id']][$option['chip_title']][] = [
                'option_value' => $option['option_value'],
                'additional_price' => $option['additional_price']
            ];
        }
    }
} catch (PDOException $e) {
    $products = [];
    $error = "Error fetching products: " . $e->getMessage();
}

$editProduct = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($editProduct) {
        $stmt = $pdo->prepare("SELECT chip_title, option_value, additional_price FROM product_chip_options WHERE product_id = ? ORDER BY chip_title, option_value");
        $stmt->execute([$id]);
        $editProduct['chip_options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - EliteWinnersWorldwide</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚽</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@500;600;700;800&display=stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'eww-green': '#0B7A4D',
                        'eww-green-light': '#0B7A4D20',
                        'eww-gold': '#D4AF37',
                        'eww-gold-light': '#D4AF3720',
                        'eww-dark': '#1A1A1A',
                        'eww-light': '#F8F8F8',
                        'sidebar-bg': '#1E293B',
                    },
                    fontFamily: {
                        heading: ['Montserrat', 'sans-serif'],
                        body: ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out forwards',
                        'slide-in': 'slideIn 0.3s ease-out forwards',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideIn: {
                            '0%': { transform: 'translateX(-100%)' },
                            '100%': { transform: 'translateX(0)' },
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
            font-family: 'Montserrat', 'sans-serif';
        }
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #0B7A4D;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #0B7A4D;
        }
        .logo { height: 50px; width: 50px; transform: scale(2.0); }
        .chip {
            display: inline-flex;
            align-items: center;
            background-color: #e5e7eb;
            border-radius: 9999px;
            padding: 0.25rem 0.75rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .chip input {
            background: transparent;
            border: none;
            outline: none;
            width: 100px;
        }
        .chip-price-input {
            display: none;
            margin-top: 0.5rem;
        }
        .chip-container {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-eww-light text-eww-dark font-body antialiased">
    <div class="flex h-screen overflow-hidden">
        <aside class="sidebar hidden lg:block lg:w-64 bg-sidebar-bg text-white">
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-center h-20 px-6 border-b border-gray-700">
                    <div class="flex items-center">
                        <img class="logo" src="logo.png" alt="logo">
                        <span class="text-xl font-heading font-bold">EliteWinners W</span>
                    </div>
                </div>
                <nav class="flex-1 px-4 py-6 overflow-y-auto">
                    <ul class="space-y-2">
                        <li>
                            <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="bookings.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Bookings
                            </a>
                        </li>
                        <li>
                            <a href="products.php" class="flex items-center px-4 py-3 rounded-lg bg-eww-green text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                                Products
                            </a>
                        </li>
                        <li>
                            <a href="users.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Users
                            </a>
                        </li>
                        <li>
                            <a href="testimonials.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Testimonials
                            </a>
                        </li>
                        <li><a href="news.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                            <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2v-1m2 3a2 2 0 002-2v-10a2 2 0 00-2-2H9a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2z"/></svg>
                            News
                        </a></li>
                        <li>
                            <a href="#" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Analytics
                            </a>
                        </li>
                        <li>
                            <a href="site_visitors.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Site Visitors
                            </a>
                        </li>
                        <li>
                            <a href="#" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Settings
                            </a>
                        </li>
                    </ul>
                    <div class="mt-10 pt-6 border-t border-gray-700">
                        <a href="#" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Logout
                        </a>
                    </div>
                </nav>
            </div>
        </aside>

        <div class="flex-1 overflow-auto">
            <header class="bg-white border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center">
                        <button id="mobile-menu-button" class="lg:hidden text-gray-500 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <div class="relative">
                            <input type="text" placeholder="Search..." class="w-64 pl-10 pr-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-eww-green focus:border-transparent">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button class="relative p-1 text-gray-500 hover:text-eww-dark">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span class="absolute top-0 right-0 flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-eww-gold opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-eww-gold"></span>
                            </span>
                        </button>
                        <div class="relative">
                            <button id="user-menu-button" class="flex items-center focus:outline-none">
                                <div class="w-8 h-8 rounded-full bg-eww-green flex items-center justify-center text-white font-semibold"><?php echo htmlspecialchars(substr($_SESSION['admin_name'], 0, 1)); ?></div>
                                <span class="ml-2 text-sm font-medium hidden md:block"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-10 border border-gray-200">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                <div class="border-t border-gray-200 my-2"></div>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <div class="mb-6">
                    <h1 class="text-2xl font-heading font-bold">Products Management</h1>
                    <p class="text-gray-600">Manage your products here.</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="mb-6">
                    <button id="add-product-btn" class="bg-eww-green text-white px-6 py-3 rounded-lg font-medium hover:bg-eww-dark transition-colors">
                        Add Product
                    </button>
                </div>

                <div id="product-form-section" class="hidden bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-8">
                    <h2 class="text-lg font-semibold mb-4"><?php echo $editProduct ? 'Edit Product' : 'Add New Product'; ?></h2>
                    <form method="POST" action="products.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?php echo $editProduct ? 'edit' : 'add'; ?>">
                        <?php if ($editProduct): ?>
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editProduct['id']); ?>">
                            <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($editProduct['image'] ?? ''); ?>">
                        <?php endif; ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                                <input type="text" name="title" value="<?php echo htmlspecialchars($editProduct['title'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                                <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($editProduct['price'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                                <select name="label" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green" required>
                                    <option value="new" <?php echo isset($editProduct['label']) && $editProduct['label'] === 'new' ? 'selected' : ''; ?>>New</option>
                                    <option value="normal" <?php echo isset($editProduct['label']) && $editProduct['label'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
                                <?php if ($editProduct && !empty($editProduct['image'])): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo htmlspecialchars($editProduct['image']); ?>" alt="Current Image" class="h-20 w-20 object-cover rounded">
                                        <p class="text-sm text-gray-500">Current image. Upload a new one to replace.</p>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="image" accept="image/jpeg,image/jpg,image/png" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green" <?php echo $editProduct ? '' : 'required'; ?>>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Discount Percent</label>
                                <input type="number" step="0.01" name="discount_percent" value="<?php echo htmlspecialchars($editProduct['discount_percent'] ?? '0.00'); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Shipping Price</label>
                                <input type="number" step="0.01" name="shipping_price" value="<?php echo htmlspecialchars($editProduct['shipping_price'] ?? '0.00'); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Stock Quantity</label>
                                <input type="number" name="stock" value="<?php echo htmlspecialchars($editProduct['stock'] ?? '0'); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Visible on Main Page</label>
                                <input type="checkbox" name="is_visible" value="1" <?php echo isset($editProduct['is_visible']) && $editProduct['is_visible'] == 1 ? 'checked' : ''; ?> class="h-5 w-5 text-eww-green focus:ring-eww-green border-gray-300 rounded">
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green" rows="4" required><?php echo htmlspecialchars($editProduct['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="mt-4">
                            <button type="button" id="add-chips-btn" class="bg-eww-gold text-white px-4 py-2 rounded-lg font-medium hover:bg-eww-dark transition-colors">
                                Add Chips
                            </button>
                            <div id="chips-section" class="mt-4 <?php echo $editProduct && !empty($editProduct['chip_options']) ? '' : 'hidden'; ?>">
                                <div id="chips-container">
                                    <?php if ($editProduct && !empty($editProduct['chip_options'])): ?>
                                        <?php
                                        $chip_groups = [];
                                        foreach ($editProduct['chip_options'] as $option) {
                                            $chip_groups[$option['chip_title']][] = [
                                                'option_value' => $option['option_value'],
                                                'additional_price' => $option['additional_price']
                                            ];
                                        }
                                        foreach ($chip_groups as $chip_title => $options): ?>
                                            <div class="chip-container" data-group-id="<?php echo htmlspecialchars($chip_title); ?>">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Chips Title</label>
                                                <input type="text" name="chip_groups[<?php echo htmlspecialchars($chip_title); ?>][title]" value="<?php echo htmlspecialchars($chip_title); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green mb-2" placeholder="e.g., Size, Color">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Chips</label>
                                                <div class="chips-list flex flex-wrap items-center">
                                                    <?php foreach ($options as $index => $option): ?>
                                                        <div class="chip">
                                                            <input type="text" name="chip_groups[<?php echo htmlspecialchars($chip_title); ?>][values][]" value="<?php echo htmlspecialchars($option['option_value']); ?>" placeholder="Chips <?php echo $index + 1; ?>" class="text-sm">
                                                            <button type="button" class="toggle-price-btn ml-2 text-eww-green hover:text-eww-dark">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                                </svg>
                                                            </button>
                                                            <button type="button" class="remove-chip ml-2 text-red-600 hover:text-red-800">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                </svg>
                                                            </button>
                                                            <input type="number" step="0.01" name="chip_groups[<?php echo htmlspecialchars($chip_title); ?>][prices][]" value="<?php echo htmlspecialchars($option['additional_price']); ?>" class="chip-price-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green" placeholder="Additional Price ($)">
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <button type="button" class="add-chip-btn ml-2 text-eww-green hover:text-eww-dark">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="chip-container" data-group-id="group-1">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Chips Title</label>
                                            <input type="text" name="chip_groups[group-1][title]" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green mb-2" placeholder="e.g., Size, Color">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Chips</label>
                                            <div class="chips-list flex flex-wrap items-center">
                                                <div class="chip">
                                                    <input type="text" name="chip_groups[group-1][values][]" placeholder="Chips 1" class="text-sm">
                                                    <button type="button" class="toggle-price-btn ml-2 text-eww-green hover:text-eww-dark">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                        </svg>
                                                    </button>
                                                    <button type="button" class="remove-chip ml-2 text-red-600 hover:text-red-800">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                    <input type="number" step="0.01" name="chip_groups[group-1][prices][]" class="chip-price-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green" placeholder="Additional Price ($)">
                                                </div>
                                                <button type="button" class="add-chip-btn ml-2 text-eww-green hover:text-eww-dark">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button type="button" id="add-chip-group-btn" class="mt-2 bg-eww-gold text-white px-4 py-2 rounded-lg font-medium hover:bg-eww-dark transition-colors">
                                    Add Another Chip Group
                                </button>
                            </div>
                        </div>
                        <div class="mt-6">
                            <button type="submit" class="bg-eww-green text-white px-6 py-3 rounded-lg font-medium hover:bg-eww-dark transition-colors">
                                <?php echo $editProduct ? 'Update Product' : 'Add Product'; ?>
                            </button>
                            <button type="button" id="cancel-form" class="ml-4 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-lg font-semibold">Products List</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Label</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount %</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shipping</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visibility</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chips</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="11" class="px-6 py-4 text-center text-gray-500">No products found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <img src="<?php echo htmlspecialchars($product['image'] ?? 'Uploads/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="h-10 w-10 object-cover rounded">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['title']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(substr($product['description'], 0, 50)) . '...'; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$<?php echo htmlspecialchars(number_format($product['price'], 2)); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($product['label']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(number_format($product['discount_percent'], 2)); ?>%</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$<?php echo htmlspecialchars(number_format($product['shipping_price'], 2)); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo ($product['stock'] ?? 0) > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                <?php echo ($product['stock'] ?? 0) > 0 ? 'Available (' . htmlspecialchars($product['stock']) . ')' : 'Out of Stock'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo ($product['is_visible'] ?? 1) == 1 ? 'Visible' : 'Hidden'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php if (!empty($chip_options[$product['id']])): ?>
                                                    <?php foreach ($chip_options[$product['id']] as $chip_title => $options): ?>
                                                        <span class="font-medium"><?php echo htmlspecialchars($chip_title); ?>:</span>
                                                        <?php foreach ($options as $option): ?>
                                                            <span class="inline-block bg-gray-200 rounded-full px-2 py-1 text-xs mr-1 mb-1">
                                                                <?php echo htmlspecialchars($option['option_value']); ?>
                                                                <?php if ($option['additional_price'] > 0): ?>
                                                                    (+$<?php echo htmlspecialchars(number_format($option['additional_price'], 2)); ?>)
                                                                <?php endif; ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                        <br>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    None
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <a href="products.php?edit=<?php echo htmlspecialchars($product['id']); ?>" class="text-eww-green hover:text-eww-dark mr-3">Edit</a>
                                                <form method="POST" action="products.php" class="inline" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($product['id']); ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900 mr-3">Delete</button>
                                                </form>
                                                <form method="POST" action="products.php" class="inline" onsubmit="return confirm('Are you sure you want to <?php echo ($product['is_visible'] ?? 1) == 1 ? 'hide' : 'show'; ?> this product on the main page?');">
                                                    <input type="hidden" name="action" value="toggle_visibility">
                                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($product['id']); ?>">
                                                    <input type="hidden" name="is_visible" value="<?php echo ($product['is_visible'] ?? 1); ?>">
                                                    <button type="submit" class="text-blue-600 hover:text-blue-900"><?php echo ($product['is_visible'] ?? 1) == 1 ? 'Hide' : 'Show'; ?></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="mobile-menu" class="fixed inset-0 z-50 hidden">
        <div class="mobile-menu-backdrop absolute inset-0 bg-black opacity-50" id="backdrop"></div>
        <div class="absolute left-0 top-0 bottom-0 w-64 bg-sidebar-bg text-white transform transition-transform duration-300 ease-in-out -translate-x-full" id="mobile-sidebar">
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between h-20 px-6 border-b border-gray-700">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-eww-green rounded-full flex items-center justify-center text-white font-heading font-bold mr-2">EW</div>
                        <span class="text-xl font-heading font-bold">EliteWinners</span>
                    </div>
                    <button id="close-mobile-menu" class="text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <nav class="flex-1 px-4 py-6 overflow-y-auto">
                    <ul class="space-y-2">
                        <li><a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Dashboard</a></li>
                        <li><a href="bookings.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Bookings</a></li>
                        <li><a href="products.php" class="flex items-center px-4 py-3 rounded-lg bg-eww-green text-white">Products</a></li>
                        <li><a href="#" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Users</a></li>
                        <li><a href="testimonials.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Testimonials</a></li>
                        <li><a href="#" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Analytics</a></li>
                        <li><a href="site_visitors.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Site Visitors</a></li>
                        <li><a href="#" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Settings</a></li>
                        <li><a href="#" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            const mobileSidebar = document.getElementById('mobile-sidebar');
            const backdrop = document.getElementById('backdrop');
            const closeMobileMenu = document.getElementById('close-mobile-menu');

            function openMobileMenu() {
                mobileMenu.classList.remove('hidden');
                setTimeout(() => {
                    mobileSidebar.classList.remove('-translate-x-full');
                }, 10);
            }

            function closeMobileMenuFunc() {
                mobileSidebar.classList.add('-translate-x-full');
                setTimeout(() => {
                    mobileMenu.classList.add('hidden');
                }, 300);
            }

            mobileMenuButton.addEventListener('click', openMobileMenu);
            closeMobileMenu.addEventListener('click', closeMobileMenuFunc);
            backdrop.addEventListener('click', closeMobileMenuFunc);

            const userMenuButton = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('user-dropdown');

            userMenuButton.addEventListener('click', function() {
                userDropdown.classList.toggle('hidden');
            });

            document.addEventListener('click', function(event) {
                if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
                    userDropdown.classList.add('hidden');
                }
            });

            const addProductBtn = document.getElementById('add-product-btn');
            const productFormSection = document.getElementById('product-form-section');
            const cancelFormBtn = document.getElementById('cancel-form');

            addProductBtn.addEventListener('click', function() {
                productFormSection.classList.remove('hidden');
                if (!window.location.search.includes('edit')) {
                    document.querySelector('form').reset();
                    document.querySelector('input[name="is_visible"]').checked = true;
                    const chipsContainer = document.getElementById('chips-container');
                    chipsContainer.innerHTML = `
                        <div class="chip-container" data-group-id="group-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Chips Title</label>
                            <input type="text" name="chip_groups[group-1][title]" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green mb-2" placeholder="e.g., Size, Color">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Chips</label>
                            <div class="chips-list flex flex-wrap items-center">
                                <div class="chip">
                                    <input type="text" name="chip_groups[group-1][values][]" placeholder="Chips 1" class="text-sm">
                                    <button type="button" class="toggle-price-btn ml-2 text-eww-green hover:text-eww-dark">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </button>
                                    <button type="button" class="remove-chip ml-2 text-red-600 hover:text-red-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                    <input type="number" step="0.01" name="chip_groups[group-1][prices][]" class="chip-price-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green" placeholder="Additional Price ($)">
                                </div>
                                <button type="button" class="add-chip-btn ml-2 text-eww-green hover:text-eww-dark">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    `;
                    document.getElementById('chips-section').classList.add('hidden');
                }
            });

            cancelFormBtn.addEventListener('click', function() {
                productFormSection.classList.add('hidden');
                if (window.location.search.includes('edit')) {
                    window.location.href = 'products.php';
                }
            });

            const addChipsBtn = document.getElementById('add-chips-btn');
            const chipsSection = document.getElementById('chips-section');
            addChipsBtn.addEventListener('click', function() {
                chipsSection.classList.remove('hidden');
            });

            let chipGroupCounter = <?php echo $editProduct && !empty($editProduct['chip_options']) ? count($chip_groups) + 1 : 2; ?>;
            document.getElementById('add-chip-group-btn').addEventListener('click', function() {
                const chipsContainer = document.getElementById('chips-container');
                const newChipContainer = document.createElement('div');
                const groupId = `group-${chipGroupCounter}`;
                newChipContainer.className = 'chip-container';
                newChipContainer.setAttribute('data-group-id', groupId);
                newChipContainer.innerHTML = `
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chips Title</label>
                    <input type="text" name="chip_groups[${groupId}][title]" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green mb-2" placeholder="e.g., Size, Color">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chips</label>
                    <div class="chips-list flex flex-wrap items-center">
                        <div class="chip">
                            <input type="text" name="chip_groups[${groupId}][values][]" placeholder="Chips 1" class="text-sm">
                            <button type="button" class="toggle-price-btn ml-2 text-eww-green hover:text-eww-dark">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </button>
                            <button type="button" class="remove-chip ml-2 text-red-600 hover:text-red-800">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <input type="number" step="0.01" name="chip_groups[${groupId}][prices][]" class="chip-price-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green" placeholder="Additional Price ($)">
                        </div>
                        <button type="button" class="add-chip-btn ml-2 text-eww-green hover:text-eww-dark">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                    </div>
                `;
                chipsContainer.appendChild(newChipContainer);
                chipGroupCounter++;
            });

            // Add/Remove Chip and Toggle Price Input
            document.addEventListener('click', function(event) {
                if (event.target.closest('.add-chip-btn')) {
                    const chipsList = event.target.closest('.chips-list');
                    const chipContainer = chipsList.closest('.chip-container');
                    const groupId = chipContainer.getAttribute('data-group-id');
                    const chipCounter = chipsList.querySelectorAll('.chip').length + 1;
                    const newChip = document.createElement('div');
                    newChip.className = 'chip';
                    newChip.innerHTML = `
                        <input type="text" name="chip_groups[${groupId}][values][]" placeholder="Chips ${chipCounter}" class="text-sm">
                        <button type="button" class="toggle-price-btn ml-2 text-eww-green hover:text-eww-dark">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                        <button type="button" class="remove-chip ml-2 text-red-600 hover:text-red-800">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <input type="number" step="0.01" name="chip_groups[${groupId}][prices][]" class="chip-price-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-eww-green focus:border-eww-green" placeholder="Additional Price ($)">
                    `;
                    chipsList.insertBefore(newChip, event.target.closest('.add-chip-btn'));
                }
                if (event.target.closest('.remove-chip')) {
                    event.target.closest('.chip').remove();
                }
                if (event.target.closest('.toggle-price-btn')) {
                    const chip = event.target.closest('.chip');
                    const priceInput = chip.querySelector('.chip-price-input');
                    priceInput.style.display = priceInput.style.display === 'block' ? 'none' : 'block';
                }
            });

            <?php if ($editProduct): ?>
                productFormSection.classList.remove('hidden');
                <?php if (!empty($editProduct['chip_options'])): ?>
                    chipsSection.classList.remove('hidden');
                <?php endif; ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>