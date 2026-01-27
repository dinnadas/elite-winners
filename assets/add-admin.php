<?php
require_once 'config.php';
session_start();

// Check if admin already exists
$adminExists = false;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    $adminExists = $stmt->fetchColumn() > 0;
} catch (PDOException $e) {
    $error = "Error checking admin status: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$adminExists) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        try {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO admins (name, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hashedPassword]);
            header("Location: login.php?success=Admin created successfully");
            exit;
        } catch (PDOException $e) {
            $error = "Error creating admin: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin - EliteWinnersWorldwide</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
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
                        'border-rotate': 'borderRotate 3s linear infinite',
                        'float': 'float 6s ease-in-out infinite',
                        'glow': 'glow 2s ease-in-out infinite alternate',
                    },
                    keyframes: {
                        borderRotate: { '0%': { '--border-angle': '0deg' }, '100%': { '--border-angle': '360deg' } },
                        float: { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-10px)' } },
                        glow: {
                            '0%': { boxShadow: '0 0 5px #0B7A4D, 0 0 10px #0B7A4D, 0 0 15px #0B7A4D' },
                            '100%': { boxShadow: '0 0 10px #0B7A4D, 0 0 20px #0B7A4D, 0 0 30px #0B7A4D, 0 0 40px #0B7A4D' },
                        },
                    },
                }
            }
        }
    </script>
    <style type="text/css">
        :root { --border-angle: 0deg; }
        html, body { overflow: hidden; height: 100%; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0c0f1d 0%, #1a2332 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #F8F8F8;
        }
        h1, h2, h3, h4, h5, h6 { font-family: 'Montserrat', sans-serif; }
        .container {
            position: relative;
            background: rgba(26, 26, 26, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            z-index: 10;
            overflow: hidden;
        }
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 16px;
            padding: 3px;
            background: conic-gradient(from var(--border-angle), #0B7A4D, #D4AF37, #0B7A4D);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            animation: border-rotate 3s linear infinite;
            pointer-events: none;
        }
        .input-field {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: white;
            transition: all 0.3s ease;
        }
        .input-field:focus {
            outline: none;
            border-color: #0B7A4D;
            box-shadow: 0 0 0 2px rgba(11, 122, 77, 0.3);
        }
        .btn-submit {
            background: linear-gradient(135deg, #0B7A4D 0%, #0a693f 100%);
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }
        .btn-submit:hover::before { left: 100%; }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(11, 122, 77, 0.3);
        }
        .error, .success { font-size: 0.875rem; }
        .error { color: #EF4444; }
        .success { color: #10B981; }
        .logo { height: 50px; width: 50px; transform: scale(3.1); }
    </style>
</head>
<body class="relative">
    <div class="container animate-glow">
        <div class="text-center mb-8">
            <div class="flex justify-center mb-6">
                <img class="logo" src="logo.png" alt="logo">
            </div>
            <h1 class="text-3xl font-heading font-bold mb-2">Add New Admin</h1>
            <p class="text-sm text-gray-400">Create the first admin account (one-time use).</p>
        </div>
        <?php if (isset($error)): ?>
            <div class="error mb-4"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="success mb-4"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if ($adminExists): ?>
            <p class="text-center text-red-400">Admin account already created. This page is disabled.</p>
        <?php else: ?>
            <form method="POST" class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Full Name</label>
                    <input type="text" id="name" name="name" class="input-field w-full" placeholder="Full Name" required>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email Address</label>
                    <input type="email" id="email" name="email" class="input-field w-full" placeholder="Email" required>
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                    <input type="password" id="password" name="password" class="input-field w-full" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-submit w-full text-white font-heading font-semibold py-3">Create Admin</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>