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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - EliteWinnersWorldwide</title>
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
            overflow-x: hidden;
            color: #F8F8F8;
        }
        h1, h2, h3, h4, h5, h6 { font-family: 'Montserrat', sans-serif; }
        .login-container {
            position: relative;
            background: rgba(26, 26, 26, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            height: 520px;
            max-width: 420px;
            z-index: 10;
            overflow: hidden;
        }
        .login-container::before {
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
        .shooting-star {
            position: absolute;
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, #cd8922, transparent);
            animation: shootingStar 8s linear infinite;
            opacity: 0;
        }
        @keyframes shootingStar {
            0% { transform: translate(-100px, -100px) rotate(230deg); opacity: 0; }
            5% { opacity: 1; }
            20% { transform: translate(500px, 500px) rotate(230deg); opacity: 0; }
            100% { opacity: 0; }
        }
        .floating-soccer-ball {
            position: absolute;
            width: 60px;
            height: 60px;
            background: radial-gradient(circle at 30% 30%, #F8F8F8, #595757);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
            box-shadow: 0 0 20px rgba(249, 249, 249, 0.5);
        }
        .floating-soccer-ball::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border: 2px solid #848484;
            border-radius: 50%;
            box-shadow: 0 0 65px #ffffff;
            animation: pulse 2s ease-in-out infinite alternate;
        }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.7; }
            100% { transform: scale(1.1); opacity: 1; }
        }
        .background-glow {
            position: fixed;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(#0B7A4D, transparent 70%);
            opacity: 0.15;
            filter: blur(40px);
            z-index: 0;
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
        .btn-login {
            background: linear-gradient(135deg, #0B7A4D 0%, #0a693f 100%);
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }
        .btn-login:hover::before { left: 100%; }
        .btn-login:hover {
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
    <div class="background-glow top-1/4 left-1/4"></div>
    <div class="background-glow bottom-1/4 right-1/4" style="background: radial-gradient(#D4AF37, transparent 70%); opacity: 0.1;"></div>
    <div class="shooting-star" style="top: 20%; left: 10%; animation-delay: 0s;"></div>
    <div class="shooting-star" style="top: 60%; left: 5%; animation-delay: 4s;"></div>
    <div class="shooting-star" style="top: 80%; left: 15%; animation-delay: 2s;"></div>
    <div class="floating-soccer-ball" style="top: 15%; left: 10%;"></div>
    <div class="login-container animate-glow">
        <div class="text-center mb-8">
            <div class="flex justify-center mb-6">
                <img class="logo" src="logo.png" alt="logo">
            </div>
            <h1 class="text-3xl font-heading font-bold mb-2">EliteWinners</h1>
        </div>
        <?php if ($error): ?>
            <div class="error mb-4"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success mb-4"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email Address</label>
                <input type="email" id="email" name="email" class="input-field w-full" placeholder="Email" required>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                <input type="password" id="password" name="password" class="input-field w-full" placeholder="••••••••" required>
            </div>
            <div class="flex items-center justify-between">
                <a href="#" class="text-sm text-eww-green hover:underline">Forgot Password?</a>
            </div>
            <button type="submit" class="btn-login w-full text-white font-heading font-semibold py-3">Sign In</button>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function createShootingStar() {
                const star = document.createElement('div');
                star.classList.add('shooting-star');
                const top = Math.random() * 100;
                const left = Math.random() * 20;
                star.style.top = `${top}%`;
                star.style.left = `${left}%`;
                star.style.animationDelay = `${Math.random() * 8}s`;
                document.body.appendChild(star);
                setTimeout(() => star.remove(), 3000);
            }
            for (let i = 0; i < 5; i++) createShootingStar();
            setInterval(createShootingStar, 3000);
            const loginContainer = document.querySelector('.login-container');
            loginContainer.addEventListener('mouseenter', () => {
                loginContainer.style.transform = 'translateY(-5px)';
                loginContainer.style.transition = 'transform 0.3s ease';
            });
            loginContainer.addEventListener('mouseleave', () => {
                loginContainer.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>