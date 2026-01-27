<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_testimonial'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $quote = trim($_POST['quote'] ?? '');
    $avatar_image = '';

    if (isset($_FILES['avatar_image']) && $_FILES['avatar_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $avatar_image = $upload_dir . time() . '_' . basename($_FILES['avatar_image']['name']);
        if (!move_uploaded_file($_FILES['avatar_image']['tmp_name'], $avatar_image)) {
            $error = "Failed to upload avatar image.";
        }
    }

    if ($full_name && $role && $quote && $avatar_image) {
        try {
            $stmt = $pdo->prepare("INSERT INTO testimonials (avatar_image, full_name, role, quote) VALUES (:avatar_image, :full_name, :role, :quote)");
            $stmt->execute([
                ':avatar_image' => $avatar_image,
                ':full_name' => $full_name,
                ':role' => $role,
                ':quote' => $quote
            ]);
            $success = "Testimonial added successfully.";
            header("Location: testimonials.php?success=added");
            exit;
        } catch (PDOException $e) {
            $error = "Error adding testimonial: " . $e->getMessage();
        }
    } else {
        $error = "All fields are required.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_testimonial'])) {
    $id = (int)$_POST['id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $quote = trim($_POST['quote'] ?? '');
    $avatar_image = $_POST['existing_avatar'] ?? '';

    if (isset($_FILES['avatar_image']) && $_FILES['avatar_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $new_avatar = $upload_dir . time() . '_' . basename($_FILES['avatar_image']['name']);
        if (move_uploaded_file($_FILES['avatar_image']['tmp_name'], $new_avatar)) {
            if ($avatar_image && file_exists($avatar_image)) {
                unlink($avatar_image);
            }
            $avatar_image = $new_avatar;
        } else {
            $error = "Failed to upload new avatar image.";
        }
    }

    if ($id && $full_name && $role && $quote && $avatar_image) {
        try {
            $stmt = $pdo->prepare("UPDATE testimonials SET avatar_image = :avatar_image, full_name = :full_name, role = :role, quote = :quote, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([
                ':avatar_image' => $avatar_image,
                ':full_name' => $full_name,
                ':role' => $role,
                ':quote' => $quote,
                ':id' => $id
            ]);
            $success = "Testimonial updated successfully.";
            header("Location: testimonials.php?success=updated");
            exit;
        } catch (PDOException $e) {
            $error = "Error updating testimonial: " . $e->getMessage();
        }
    } else {
        $error = "All fields are required.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_testimonial'])) {
    $id = (int)$_POST['delete_id'];
    try {
        $stmt = $pdo->prepare("SELECT avatar_image FROM testimonials WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $testimonial = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($testimonial) {
            if ($testimonial['avatar_image'] && file_exists($testimonial['avatar_image'])) {
                unlink($testimonial['avatar_image']);
            }
            
            $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $success = "Testimonial deleted successfully.";
            header("Location: testimonials.php?success=deleted");
            exit;
        } else {
            $error = "Testimonial not found.";
        }
    } catch (PDOException $e) {
        $error = "Error deleting testimonial: " . $e->getMessage();
    }
}

try {
    $stmt = $pdo->query("SELECT id, avatar_image, full_name, role, quote, created_at FROM testimonials ORDER BY created_at DESC");
    $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $testimonials = [];
    $error = "Error fetching testimonials: " . $e->getMessage();
}

$edit_testimonial = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $edit_testimonial = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials - EliteWinnersWorldwide</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚽</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@500;600;700;800&display=swap" rel="stylesheet">
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
            font-family: 'Montserrat', sans-serif;
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
        .sidebar {
            transition: all 0.3s ease;
        }
        .mobile-menu-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }
        .logo { height: 50px; width: 50px; transform: scale(2.0); }
    </style>
</head>
<body class="bg-eww-light text-eww-dark font-body antialiased">
    <div class="flex h-screen overflow-hidden">
        <aside class="sidebar hidden lg:block lg:w-64 bg-sidebar-bg text-white">
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-center h-20 px-6 border-b border-gray-700">
                    <div class="flex items-center">
                        <img class="logo" src="logo.png" alt="logo">
                        <span class="text-xl font-heading font-bold">EliteWinners</span>
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
                            <a href="#" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
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
                            <a href="testimonials.php" class="flex items-center px-4 py-3 rounded-lg bg-eww-green text-white">
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
                            <a href="#" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
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
                    <h1 class="text-2xl font-heading font-bold">Testimonials</h1>
                    <p class="text-gray-600">Manage testimonials for EliteWinnersWorldwide.</p>
                </div>
                
                <div class="mb-6">
                    <button id="add-testimonial-button" class="bg-eww-green text-white px-4 py-2 rounded-lg hover:bg-eww-dark transition-colors">
                        Add Testimonial
                    </button>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="mb-6 p-4 bg-green-100 text-green-700 rounded-lg">
                        <?php 
                        $success_msg = $_GET['success'] === 'added' ? 'Testimonial added successfully.' : 
                                       ($_GET['success'] === 'updated' ? 'Testimonial updated successfully.' : 
                                       ($_GET['success'] === 'deleted' ? 'Testimonial deleted successfully.' : 'Success!'));
                        echo htmlspecialchars($success_msg); 
                        ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="mb-6 p-4 bg-red-100 text-red-700 rounded-lg">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($testimonials)): ?>
                        <div class="col-span-full text-center text-gray-500 p-6">
                            No testimonials found.
                        </div>
                    <?php else: ?>
                        <?php foreach ($testimonials as $testimonial): ?>
                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                                <div class="flex items-center mb-4">
                                    <img src="<?php echo htmlspecialchars($testimonial['avatar_image']); ?>" alt="Avatar" class="w-12 h-12 rounded-full object-cover mr-4">
                                    <div>
                                        <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($testimonial['full_name']); ?></h3>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($testimonial['role']); ?></p>
                                    </div>
                                </div>
                                <p class="text-gray-600 italic">"<?php echo htmlspecialchars($testimonial['quote']); ?>"</p>
                                <div class="mt-4 flex justify-end space-x-2">
                                    <a href="?edit=<?php echo $testimonial['id']; ?>" class="text-eww-green hover:text-eww-dark">Edit</a>
                                    <button class="text-red-600 hover:text-red-900 delete-testimonial-button" data-id="<?php echo $testimonial['id']; ?>">Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <div id="add-testimonial-modal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-heading font-bold">Add New Testimonial</h2>
                <button id="close-add-modal-button" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="avatar_image" class="block text-sm font-medium text-gray-700">Avatar Image</label>
                    <input type="file" id="avatar_image" name="avatar_image" accept="image/*" class="mt-1 block w-full border border-gray-300 rounded-lg p-2 focus:ring-eww-green focus:border-eww-green" required>
                </div>
                <div class="mb-4">
                    <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required class="mt-1 block w-full border border-gray-300 rounded-lg p-2 focus:ring-eww-green focus:border-eww-green">
                </div>
                <div class="mb-4">
                    <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                    <input type="text" id="role" name="role" required class="mt-1 block w-full border border-gray-300 rounded-lg p-2 focus:ring-eww-green focus:border-eww-green">
                </div>
                <div class="mb-4">
                    <label for="quote" class="block text-sm font-medium text-gray-700">Quote</label>
                    <textarea id="quote" name="quote" required rows="4" class="mt-1 block w-full border border-gray-300 rounded-lg p-2 focus:ring-eww-green focus:border-eww-green"></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="button" id="cancel-add-modal-button" class="mr-2 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancel</button>
                    <button type="submit" name="add_testimonial" class="px-4 py-2 bg-eww-green text-white rounded-lg hover:bg-eww-dark">Save Testimonial</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($edit_testimonial): ?>
    <div id="edit-testimonial-modal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-heading font-bold">Edit Testimonial</h2>
                <button id="close-edit-modal-button" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $edit_testimonial['id']; ?>">
                <input type="hidden" name="existing_avatar" value="<?php echo htmlspecialchars($edit_testimonial['avatar_image']); ?>">
                <div class="mb-4">
                    <label for="avatar_image_edit" class="block text-sm font-medium text-gray-700">Avatar Image (leave empty to keep current)</label>
                    <input type="file" id="avatar_image_edit" name="avatar_image" accept="image/*" class="mt-1 block w-full border border-gray-300 rounded-lg p-2 focus:ring-eww-green focus:border-eww-green">
                    <p class="text-sm text-gray-500 mt-1">Current: <?php echo htmlspecialchars(basename($edit_testimonial['avatar_image'])); ?></p>
                </div>
                <div class="mb-4">
                    <label for="full_name_edit" class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" id="full_name_edit" name="full_name" value="<?php echo htmlspecialchars($edit_testimonial['full_name']); ?>" required class="mt-1 block w-full border border-gray-300 rounded-lg p-2 focus:ring-eww-green focus:border-eww-green">
                </div>
                <div class="mb-4">
                    <label for="role_edit" class="block text-sm font-medium text-gray-700">Role</label>
                    <input type="text" id="role_edit" name="role" value="<?php echo htmlspecialchars($edit_testimonial['role']); ?>" required class="mt-1 block w-full border border-gray-300 rounded-lg p-2 focus:ring-eww-green focus:border-eww-green">
                </div>
                <div class="mb-4">
                    <label for="quote_edit" class="block text-sm font-medium text-gray-700">Quote</label>
                    <textarea id="quote_edit" name="quote" required rows="4" class="mt-1 block w-full border border-gray-300 rounded-lg p-2 focus:ring-eww-green focus:border-eww-green"><?php echo htmlspecialchars($edit_testimonial['quote']); ?></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="button" id="cancel-edit-modal-button" class="mr-2 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancel</button>
                    <button type="submit" name="edit_testimonial" class="px-4 py-2 bg-eww-green text-white rounded-lg hover:bg-eww-dark">Update Testimonial</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        document.getElementById('edit-testimonial-modal').classList.remove('hidden');
    </script>
    <?php endif; ?>

    <div id="delete-testimonial-modal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-heading font-bold text-red-600">Delete Testimonial</h2>
                <button id="close-delete-modal-button" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p class="text-gray-600 mb-6">Are you sure you want to delete this testimonial? This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="delete_id" id="delete-testimonial-id">
                <div class="flex justify-end space-x-2">
                    <button type="button" id="cancel-delete-modal-button" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancel</button>
                    <button type="submit" name="delete_testimonial" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Confirm Delete</button>
                </div>
            </form>
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
                        <li><a href="#" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Products</a></li>
                        <li><a href="#" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Users</a></li>
                        <li><a href="testimonials.php" class="flex items-center px-4 py-3 rounded-lg bg-eww-green text-white">Testimonials</a></li>
                        <li><a href="#" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Analytics</a></li>
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

            const addTestimonialButton = document.getElementById('add-testimonial-button');
            const addTestimonialModal = document.getElementById('add-testimonial-modal');
            const closeAddModalButton = document.getElementById('close-add-modal-button');
            const cancelAddModalButton = document.getElementById('cancel-add-modal-button');

            function openAddModal() {
                addTestimonialModal.classList.remove('hidden');
            }

            function closeAddModal() {
                addTestimonialModal.classList.add('hidden');
            }

            addTestimonialButton.addEventListener('click', openAddModal);
            closeAddModalButton.addEventListener('click', closeAddModal);
            cancelAddModalButton.addEventListener('click', closeAddModal);

            const editTestimonialModal = document.getElementById('edit-testimonial-modal');
            if (editTestimonialModal) {
                const closeEditModalButton = document.getElementById('close-edit-modal-button');
                const cancelEditModalButton = document.getElementById('cancel-edit-modal-button');

                closeEditModalButton.addEventListener('click', () => {
                    window.location.href = 'testimonials.php'; 
                });
                cancelEditModalButton.addEventListener('click', () => {
                    window.location.href = 'testimonials.php'; 
                });
            }

            const deleteTestimonialModal = document.getElementById('delete-testimonial-modal');
            const closeDeleteModalButton = document.getElementById('close-delete-modal-button');
            const cancelDeleteModalButton = document.getElementById('cancel-delete-modal-button');
            const deleteTestimonialButtons = document.querySelectorAll('.delete-testimonial-button');
            const deleteTestimonialIdInput = document.getElementById('delete-testimonial-id');

            function openDeleteModal(id) {
                deleteTestimonialIdInput.value = id;
                deleteTestimonialModal.classList.remove('hidden');
            }

            function closeDeleteModal() {
                deleteTestimonialModal.classList.add('hidden');
                deleteTestimonialIdInput.value = '';
            }

            deleteTestimonialButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const id = button.getAttribute('data-id');
                    openDeleteModal(id);
                });
            });

            closeDeleteModalButton.addEventListener('click', closeDeleteModal);
            cancelDeleteModalButton.addEventListener('click', closeDeleteModal);
        });
    </script>
</body>
</html>