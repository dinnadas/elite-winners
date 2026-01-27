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
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->execute([$id]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Booking deleted.']);
            exit;
        }

        if ($_POST['action'] === 'update_status' && !empty($_POST['id']) && !empty($_POST['status'])) {
            $id = (int)$_POST['id'];
            $status = $_POST['status'];
            $allowed = ['Pending', 'Confirmed', 'Completed'];
            if (!in_array($status, $allowed)) {
                throw new Exception('Invalid status');
            }

            $stmt = $pdo->prepare("SELECT name, email FROM bookings WHERE id = ?");
            $stmt->execute([$id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                throw new Exception('Booking not found');
            }

            $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);

            $to = $booking['email'];
            $name = $booking['name'];
            $subject = "";
            $message = "";

            if ($status === 'Confirmed') {
                $subject = "Your EliteWinners Session is Confirmed!";
                $message = "Hi $name,\n\nGreat news! Your coaching session has been **confirmed**.\n\nWe look forward to helping you level up!\n\nBest,\nEliteWinnersWorldwide Team";
            } elseif ($status === 'Completed') {
                $subject = "Your Session is Complete – Well Done!";
                $message = "Hi $name,\n\nYour session has been marked as **completed**.\n\nKeep pushing! You're on fire!\n\nEliteWinnersWorldwide";
            }

            if ($subject && $message) {
                $headers = "From: no-reply@elitewinnersworldwide.com\r\n";
                $headers .= "Reply-To: support@elitewinnersworldwide.com\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                mail($to, $subject, $message, $headers);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'new_status' => $status]);
            exit;
        }

        if ($_POST['action'] === 'update_booking' && !empty($_POST['id'])) {
            $id = (int)$_POST['id'];
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $session_type = trim($_POST['session_type'] ?? '');
            $preferred_date = $_POST['preferred_date'] ?? '';
            $status = $_POST['status'] ?? 'Pending';

            if (empty($name) || empty($email) || empty($session_type) || empty($preferred_date)) {
                throw new Exception('All fields are required.');
            }

            $stmt = $pdo->prepare("
                UPDATE bookings 
                SET name = ?, email = ?, session_type = ?, preferred_date = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $email, $session_type, $preferred_date, $status, $id]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Booking updated successfully.']);
            exit;
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

try {
    $stmt = $pdo->query("SELECT id, name, email, session_type, preferred_date, status FROM bookings ORDER BY preferred_date DESC");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $bookings = [];
    $error = "Error fetching bookings: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings - EliteWinnersWorldwide</title>
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
        <!-- Sidebar -->
        <aside class="sidebar hidden lg:block lg:w-64 bg-sidebar-bg text-white">
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-center h-20 px-6 border-b border-gray-700">
                    <div class="flex items-center">
                        <img class="logo" src="logo.png" alt="logo">
                        <span class="text-xl font-heading font-bold">EliteWinners</span>
                    </div>
                </div>
                <!-- Navigation -->
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
                            <a href="bookings.php" class="flex items-center px-4 py-3 rounded-lg bg-eww-green text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Bookings
                            </a>
                        </li>
                        <li>
                            <a href="products.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
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
                    
                    <!-- Bottom section -->
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

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <header class="bg-white border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center">
                        <button id="mobile-menu-button" class="lg:hidden text-gray-500 mr-4">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <div class="relative">
                            <input type="text" placeholder="Search..." class="w-64 pl-10 pr-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-eww-green">
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
                                <div class="w-8 h-8 rounded-full bg-eww-green flex items-center justify-center text-white font-semibold"><?php echo htmlspecialchars(substr($_SESSION['admin_name'], 0, 1)); ?></div>
                                <span class="ml-2 text-sm font-medium hidden md:block"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                                <svg class="h-4 w-4 ml-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
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
                    <h1 class="text-2xl font-heading font-bold">Bookings</h1>
                    <p class="text-gray-600">Manage all bookings for EliteWinnersWorldwide sessions.</p>
                </div>

                <div id="alert-message" class="hidden mb-4 p-4 rounded-lg text-sm"></div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-8">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">All Bookings</h2>
                        <button class="text-eww-green text-sm font-medium">Export Data</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Player</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Preferred Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($bookings)): ?>
                                    <tr><td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500"><?php echo isset($error) ? htmlspecialchars($error) : "No bookings found."; ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($bookings as $booking): ?>
                                        <tr data-id="<?php echo $booking['id']; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div class="h-10 w-10 rounded-full bg-eww-green-light flex items-center justify-center text-eww-green font-semibold text-sm">
                                                            <?php echo htmlspecialchars(strtoupper(substr($booking['name'], 0, 2))); ?>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($booking['name']); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($booking['email']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900"><?php echo htmlspecialchars($booking['session_type']); ?></div></td>
                                            <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900"><?php echo htmlspecialchars($booking['preferred_date']); ?></div></td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <select class="status-select text-xs font-semibold rounded-full px-2 py-1 border-0 focus:ring-2 focus:ring-eww-green <?php echo $booking['status'] == 'Confirmed' ? 'bg-green-100 text-green-800' : ($booking['status'] == 'Pending' ? 'bg-yellow-100 text-yellow-800' : ($booking['status'] == 'Completed' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800')); ?>">
                                                    <option value="Pending" <?php echo $booking['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="Confirmed" <?php echo $booking['status'] == 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                    <option value="Completed" <?php echo $booking['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                </select>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($booking)); ?>)" class="text-eww-green hover:text-eww-dark mr-3">Edit</button>
                                                <button onclick="deleteBooking(<?php echo $booking['id']; ?>)" class="text-red-600 hover:text-red-900">Delete</button>
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

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h3 class="text-lg font-semibold mb-4">Edit Booking</h3>
            <form id="editForm">
                <input type="hidden" id="edit_id">
                <div class="space-y-4">
                    <div><label class="block text-sm font-medium text-gray-700">Name</label><input type="text" id="edit_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm" required></div>
                    <div><label class="block text-sm font-medium text-gray-700">Email</label><input type="email" id="edit_email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm" required></div>
                    <div><label class="block text-sm font-medium text-gray-700">Session Type</label><input type="text" id="edit_session_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm" required></div>
                    <div><label class="block text-sm font-medium text-gray-700">Preferred Date</label><input type="date" id="edit_preferred_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm" required></div>
                    <div><label class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="edit_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-eww-green focus:ring-eww-green sm:text-sm">
                            <option value="Pending">Pending</option>
                            <option value="Confirmed">Confirmed</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type,type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-eww-green text-white rounded-lg hover:bg-eww-dark">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="mobile-menu" class="fixed inset-0 z-50 hidden">
        <div class="mobile-menu-backdrop absolute inset-0 bg-black opacity-50" id="backdrop"></div>
        <div class="absolute left-0 top-0 bottom-0 w-64 bg-sidebar-bg text-white transform transition-transform duration-300 ease-in-out -translate-x-full" id="mobile-sidebar">
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            document.querySelectorAll('.status-select').forEach(select => {
                select.addEventListener('change', function() {
                    const row = this.closest('tr');
                    const id = row.dataset.id;
                    const newStatus = this.value;
                    const prevStatus = this.dataset.prev || 'Pending';

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=update_status&id=${id}&status=${encodeURIComponent(newStatus)}`
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            this.dataset.prev = newStatus;
                            updateStatusBadge(row, newStatus);
                            showAlert(`Status updated to ${newStatus}. Email sent!`, 'success');
                        } else {
                            this.value = prevStatus;
                            showAlert(data.message, 'error');
                        }
                    })
                    .catch(() => {
                        this.value = prevStatus;
                        showAlert('Failed to update status.', 'error');
                    });
                });
                select.dataset.prev = select.value;
            });

            window.openEditModal = function(booking) {
                document.getElementById('edit_id').value = booking.id;
                document.getElementById('edit_name').value = booking.name;
                document.getElementById('edit_email').value = booking.email;
                document.getElementById('edit_session_type').value = booking.session_type;
                document.getElementById('edit_preferred_date').value = booking.preferred_date;
                document.getElementById('edit_status').value = booking.status;
                document.getElementById('editModal').classList.remove('hidden');
            };

            window.closeEditModal = () => document.getElementById('editModal').classList.add('hidden');

            document.getElementById('editForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'update_booking');

                fetch(window.location.href, {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Booking updated!', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(() => showAlert('Update failed.', 'error'));
            });

            window.deleteBooking = function(id) {
                if (!confirm('Delete this booking?')) return;
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete&id=${id}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.querySelector(`tr[data-id="${id}"]`).remove();
                        showAlert('Booking deleted.', 'success');
                    } else {
                        showAlert(data.message, 'error');
                    }
                });
            };

            function updateStatusBadge(row, status) {
                const select = row.querySelector('.status-select');
                const classes = {
                    'Pending': 'bg-yellow-100 text-yellow-800',
                    'Confirmed': 'bg-green-100 text-green-800',
                    'Completed': 'bg-blue-100 text-blue-800'
                };
                select.className = `status-select text-xs font-semibold rounded-full px-2 py-1 border-0 focus:ring-2 focus:ring-eww-green ${classes[status] || 'bg-red-100 text-red-800'}`;
            }

            function showAlert(msg, type) {
                const alert = document.getElementById('alert-message');
                alert.textContent = msg;
                alert.className = `mb-4 p-4 rounded-lg text-sm ${type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`;
                alert.classList.remove('hidden');
                setTimeout(() => alert.classList.add('hidden'), 4000);
            }
        });
    </script>
</body>
</html>