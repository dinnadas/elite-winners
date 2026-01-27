<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();

        if ($_POST['action'] === 'delete' && !empty($_POST['id'])) {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("SELECT image FROM news WHERE id = ?");
            $stmt->execute([$id]);
            $news = $stmt->fetch();
            if ($news && $news['image'] && file_exists($news['image'])) {
                unlink($news['image']);
            }
            $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
            $stmt->execute([$id]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'News deleted.']);
            exit;
        }

        if ($_POST['action'] === 'create') {
            $type = trim($_POST['type'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $additional_description = trim($_POST['additional_description'] ?? '');
            $publication_datetime = $_POST['publication_datetime'] ?? '';
            $read_time = trim($_POST['read_time'] ?? '5 min read');

            if (empty($type) || empty($title) || empty($description) || empty($publication_datetime)) {
                throw new Exception('Required fields missing.');
            }

            $image_path = '';
            if (!empty($_FILES['image']['name'])) {
                $target_dir = "uploads/news/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                $filename = time() . "_" . basename($_FILES["image"]["name"]);
                $target_file = $target_dir . $filename;
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $image_path = $target_file;
                } else {
                    throw new Exception('Image upload failed.');
                }
            } else {
                throw new Exception('Image is required.');
            }

            $stmt = $pdo->prepare("INSERT INTO news (type, image, title, description, additional_description, publication_datetime, read_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$type, $image_path, $title, $description, $additional_description, $publication_datetime, $read_time]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'News created.']);
            exit;
        }

        // === UPDATE NEWS ===
        if ($_POST['action'] === 'update' && !empty($_POST['id'])) {
            $id = (int)$_POST['id'];
            $type = trim($_POST['type'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $additional_description = trim($_POST['additional_description'] ?? '');
            $publication_datetime = $_POST['publication_datetime'] ?? '';
            $read_time = trim($_POST['read_time'] ?? '5 min read');

            if (empty($type) || empty($title) || empty($description) || empty($publication_datetime)) {
                throw new Exception('Required fields missing.');
            }

            $image_path = $_POST['existing_image'] ?? '';
            if (!empty($_FILES['image']['name'])) {
                $target_dir = "uploads/news/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                $filename = time() . "_" . basename($_FILES["image"]["name"]);
                $target_file = $target_dir . $filename;
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    if ($image_path && file_exists($image_path)) unlink($image_path);
                    $image_path = $target_file;
                } else {
                    throw new Exception('Image upload failed.');
                }
            }

            $stmt = $pdo->prepare("UPDATE news SET type = ?, image = ?, title = ?, description = ?, additional_description = ?, publication_datetime = ?, read_time = ? WHERE id = ?");
            $stmt->execute([$type, $image_path, $title, $description, $additional_description, $publication_datetime, $read_time, $id]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'News updated.']);
            exit;
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

try {
    $stmt = $pdo->query("SELECT * FROM news ORDER BY publication_datetime DESC");
    $news_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $news_list = [];
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News - EliteWinnersWorldwide</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>News</text></svg>">
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
                        fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                        slideIn: { '0%': { transform: 'translateX(-100%)' }, '100%': { transform: 'translateX(0)' } },
                    },
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; overflow-x: hidden; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Montserrat', 'sans-serif'; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #0B7A4D; border-radius: 3px; }
        .sidebar { transition: all 0.3s ease; }
        .mobile-menu-backdrop { background-color: rgba(0, 0, 0, 0.5); }
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
                        <li><a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                            <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            Dashboard
                        </a></li>
                        <li><a href="bookings.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                            <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Bookings
                        </a></li>
                        <li><a href="products.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                            <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            Products
                        </a></li>
                        <li><a href="users.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                            <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Users
                        </a></li>
                        <li><a href="testimonials.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                            <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Testimonials
                        </a></li>
                        <li><a href="news.php" class="flex items-center px-4 py-3 rounded-lg bg-eww-green text-white">
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
                        <li><a href="site_visitors.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                            <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Site Visitors
                        </a></li>
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
                        <a href="logout.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                            <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
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
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <div class="relative">
                            <input type="text" placeholder="Search..." class="w-64 pl-10 pr-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-eww-green focus:border-transparent">
                            <svg class="h-5 w-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button class="relative p-1 text-gray-500 hover:text-eww-dark">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            <span class="absolute top-0 right-0 flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-eww-gold opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-eww-gold"></span></span>
                        </button>
                        <div class="relative">
                            <button id="user-menu-button" class="flex items-center focus:outline-none">
                                <div class="w-8 h-8 rounded-full bg-eww-green flex items-center justify-center text-white font-semibold"><?php echo htmlspecialchars(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?></div>
                                <span class="ml-2 text-sm font-medium hidden md:block"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                                <svg class="h-4 w-4 ml-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-10 border border-gray-200">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                <div class="border-t border-gray-200 my-2"></div>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <div id="alert-message" class="hidden mb-4 p-4 rounded-lg text-sm"></div>

                <div class="mb-6 flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-heading font-bold">News</h1>
                        <p class="text-gray-600">Manage news and announcements.</p>
                    </div>
                    <button id="create-post-btn" onclick="openCreateModal()" class="px-4 py-2 bg-eww-green text-white rounded-lg hover:bg-eww-dark font-medium transition">
                        Create Post
                    </button>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-lg font-semibold">All Posts</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Published</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($news_list)): ?>
                                    <tr><td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No news found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($news_list as $item): ?>
                                        <tr data-id="<?php echo $item['id']; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="" class="h-12 w-12 rounded object-cover">
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['title']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($item['read_time']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php
                                                    $types = ['announcement'=>'bg-blue-100 text-blue-800', 'event'=>'bg-green-100 text-green-800', 'update'=>'bg-yellow-100 text-yellow-800', 'success'=>'bg-purple-100 text-purple-800', 'training'=>'bg-indigo-100 text-indigo-800', 'product'=>'bg-pink-100 text-pink-800'];
                                                    echo $types[$item['type']] ?? 'bg-gray-100 text-gray-800';
                                                ?>">
                                                    <?php echo ucfirst(htmlspecialchars($item['type'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo date('M j, Y g:i A', strtotime($item['publication_datetime'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>)" class="text-eww-green hover:text-eww-dark mr-3">Edit</button>
                                                <button onclick="deleteNews(<?php echo $item['id']; ?>)" class="text-red-600 hover:text-red-900">Delete</button>
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
        <div class="mobile-menu-backdrop absolute inset-0" id="backdrop"></div>
        <div class="absolute left-0 top-0 bottom-0 w-64 bg-sidebar-bg text-white transform transition-transform duration-300 -translate-x-full" id="mobile-sidebar">
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between h-20 px-6 border-b border-gray-700">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-eww-green rounded-full flex items-center justify-center text-white font-heading font-bold mr-2">EW</div>
                        <span class="text-xl font-heading font-bold">EliteWinners</span>
                    </div>
                    <button id="close-mobile-menu" class="text-white">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <nav class="flex-1 px-4 py-6 overflow-y-auto">
                    <ul class="space-y-2">
                        <li><a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Dashboard</a></li>
                        <li><a href="bookings.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Bookings</a></li>
                        <li><a href="products.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Products</a></li>
                        <li><a href="users.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Users</a></li>
                        <li><a href="testimonials.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Testimonials</a></li>
                        <li><a href="news.php" class="flex items-center px-4 py-3 rounded-lg bg-eww-green text-white">News</a></li>
                        <li><a href="site_visitors.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Site Visitors</a></li>
                        <li><a href="logout.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full p-6 max-h-screen overflow-y-auto">
            <h3 class="text-lg font-semibold mb-4">Create News Post</h3>
            <form id="createForm" enctype="multipart/form-data">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Type</label>
                        <select name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm" required>
                            <option value="announcement">Announcement</option>
                            <option value="event">Event</option>
                            <option value="update">Update</option>
                            <option value="success">Success Story</option>
                            <option value="training">Training</option>
                            <option value="product">Product</option>
                        </select>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700">Title</label><input type="text" name="title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm" required></div>
                    <div><label class="block text-sm font-medium text-gray-700">Image</label><input type="file" name="image" accept="image/*" class="mt-1 block w-full" required></div>
                    <div><label class="block text-sm font-medium text-gray-700">Description</label><textarea name="description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm" required></textarea></div>
                    <div><label class="block text-sm font-medium text-gray-700">Additional Description</label><textarea name="additional_description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm"></textarea></div>
                    <div><label class="block text-sm font-medium text-gray-700">Publication Date & Time</label><input type="datetime-local" name="publication_datetime" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm" required></div>
                    <div><label class="block text-sm font-medium text-gray-700">Read Time</label><input type="text" name="read_time" value="5 min read" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm"></div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">Cancel</button>
                    <button type="submit" id="create-submit-btn" class="px-4 py-2 bg-eww-green text-white rounded-lg hover:bg-eww-dark">Create Post</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full p-6 max-h-screen overflow-y-auto">
            <h3 class="text-lg font-semibold mb-4">Edit News Post</h3>
            <form id="editForm" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="existing_image" id="edit_existing_image">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Type</label>
                        <select name="type" id="edit_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm" required>
                            <option value="announcement">Announcement</option>
                            <option value="event">Event</option>
                            <option value="update">Update</option>
                            <option value="success">Success Story</option>
                            <option value="training">Training</option>
                            <option value="product">Product</option>
                        </select>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700">Title</label><input type="text" name="title" id="edit_title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm" required></div>
                    <div><label class="block text-sm font-medium text-gray-700">Image</label>
                        <input type="file" name="image" accept="image/*" class="mt-1 block w-full">
                        <img id="edit_image_preview" src="" alt="Current" class="mt-2 h-20 w-20 rounded object-cover">
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700">Description</label><textarea name="description" id="edit_description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm" required></textarea></div>
                    <div><label class="block text-sm font-medium text-gray-700">Additional Description</label><textarea name="additional_description" id="edit_additional_description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm"></textarea></div>
                    <div><label class="block text-sm font-medium text-gray-700">Publication Date & Time</label><input type="datetime-local" name="publication_datetime" id="edit_publication_datetime" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm" required></div>
                    <div><label class="block text-sm font-medium text-gray-700">Read Time</label><input type="text" name="read_time" id="edit_read_time" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm"></div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">Cancel</button>
                    <button type="submit" id="edit-submit-btn" class="px-4 py-2 bg-eww-green text-white rounded-lg hover:bg-eww-dark">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('mobile-menu-button').addEventListener('click', () => {
            document.getElementById('mobile-menu').classList.remove('hidden');
            setTimeout(() => document.getElementById('mobile-sidebar').classList.remove('-translate-x-full'), 10);
        });
        document.getElementById('close-mobile-menu').addEventListener('click', closeMobile);
        document.getElementById('backdrop').addEventListener('click', closeMobile);
        function closeMobile() {
            document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
            setTimeout(() => document.getElementById('mobile-menu').classList.add('hidden'), 300);
        }

        document.getElementById('user-menu-button').addEventListener('click', () => {
            document.getElementById('user-dropdown').classList.toggle('hidden');
        });
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('user-dropdown');
            const button = document.getElementById('user-menu-button');
            if (!button.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.add('hidden');
        });

        function openCreateModal() {
            document.getElementById('createModal').classList.remove('hidden');
        }
        function closeCreateModal() {
            document.getElementById('createModal').classList.add('hidden');
            document.getElementById('createForm').reset();
        }
        function openEditModal(news) {
            document.getElementById('edit_id').value = news.id;
            document.getElementById('edit_type').value = news.type;
            document.getElementById('edit_title').value = news.title;
            document.getElementById('edit_description').value = news.description;
            document.getElementById('edit_additional_description').value = news.additional_description || '';
            document.getElementById('edit_publication_datetime').value = news.publication_datetime.replace(' ', 'T');
            document.getElementById('edit_read_time').value = news.read_time;
            document.getElementById('edit_existing_image').value = news.image;
            document.getElementById('edit_image_preview').src = news.image;
            document.getElementById('editModal').classList.remove('hidden');
        }
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        document.getElementById('createForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('create-submit-btn');
            btn.disabled = true;
            btn.textContent = 'Creating...';
            btn.classList.add('opacity-75');

            const formData = new FormData(this);
            formData.append('action', 'create');

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    showAlert(data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Create Post';
                        btn.classList.remove('opacity-75');
                    }
                })
                .catch(() => {
                    showAlert('Network error.', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Create Post';
                    btn.classList.remove('opacity-75');
                });
        });

        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('edit-submit-btn');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            btn.classList.add('opacity-75');

            const formData = new FormData(this);
            formData.append('action', 'update');

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    showAlert(data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Save Changes';
                        btn.classList.remove('opacity-75');
                    }
                });
        });

        function deleteNews(id) {
            if (!confirm('Delete this news post?')) return;
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`tr[data-id="${id}"]`).remove();
                    showAlert('News deleted.', 'success');
                } else showAlert(data.message, 'error');
            });
        }

        function showAlert(msg, type) {
            const alert = document.getElementById('alert-message');
            alert.textContent = msg;
            alert.className = `mb-4 p-4 rounded-lg text-sm ${type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`;
            alert.classList.remove('hidden');
            setTimeout(() => alert.classList.add('hidden'), 4000);
        }
    </script>
</body>
</html>