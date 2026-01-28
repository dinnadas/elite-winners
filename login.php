<?php
require_once 'config.php';
session_start();

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, name, password FROM admins WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "Server error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="bg-gray-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – EliteWinners</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'eww-green': '#0ea46f',
                        'eww-green-dark': '#087155',
                        'eww-gold': '#d4af37',
                        'eww-dark': '#111827',
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-gradient-to-b from-gray-950 to-gray-900 flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <!-- Card -->
        <div class="bg-gray-900/70 backdrop-blur-xl border border-gray-800 rounded-2xl shadow-2xl shadow-black/40 overflow-hidden">

            <div class="px-8 pt-10 pb-8">
                <!-- Logo & Title -->
                <div class="text-center mb-8">
                    <?php if (file_exists('logo.png')): ?>
                        <img src="logo.png" alt="EliteWinners Logo" class="h-14 mx-auto mb-5">
                    <?php endif; ?>
                    <h1 class="text-3xl font-semibold text-white tracking-tight">
                        Admin Portal
                    </h1>
                    <p class="mt-2 text-gray-400 text-sm">
                        EliteWinners Management
                    </p>
                </div>

                <!-- Messages -->
                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-950/60 border border-red-800/60 text-red-300 text-sm rounded-xl">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-6 p-4 bg-green-950/60 border border-green-800/60 text-green-300 text-sm rounded-xl">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                            Email address
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            autocomplete="email"
                            required
                            placeholder="admin@elitewinners.com"
                            class="w-full px-4 py-3.5 bg-gray-800/60 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:border-eww-green focus:ring-2 focus:ring-eww-green/30 outline-none transition-all duration-200"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                            Password
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                            placeholder="••••••••"
                            class="w-full px-4 py-3.5 bg-gray-800/60 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:border-eww-green focus:ring-2 focus:ring-eww-green/30 outline-none transition-all duration-200"
                        >
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <a href="#" class="text-eww-green hover:text-eww-green-dark transition-colors">
                            Forgot password?
                        </a>
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3.5 px-4 bg-eww-green hover:bg-eww-green-dark text-white font-medium rounded-xl focus:outline-none focus:ring-2 focus:ring-eww-green/40 focus:ring-offset-2 focus:ring-offset-gray-900 transition-all duration-200 shadow-lg shadow-eww-green/20"
                    >
                        Sign in
                    </button>
                </form>
            </div>

            <!-- Subtle footer -->
            <div class="px-8 py-5 border-t border-gray-800 bg-black/30 text-center text-xs text-gray-500">
                © <?= date('Y') ?> EliteWinners Worldwide
            </div>

        </div>
    </div>

</body>
</html>