<?php
include 'config.php';
session_start();
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Google\Client as GoogleClient;
use Google\Service\Oauth2;

$google_client = new GoogleClient();
$google_client->setClientId('');
$google_client->setClientSecret(''); 
$google_client->setRedirectUri('');
$google_client->addScope('email');
$google_client->addScope('profile');

$google_auth_url = $google_client->createAuthUrl();

// Handle Google Callback
if (isset($_GET['code'])) {
    $token = $google_client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        $error = "Google authentication failed.";
    } else {
        $google_client->setAccessToken($token['access_token']);
        $oauth = new Oauth2($google_client);
        $google_account_info = $oauth->userinfo->get();

        $email = $google_account_info->email;
        $first_name = $google_account_info->givenName;
        $last_name = $google_account_info->familyName;

        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Login
            $_SESSION['user_id'] = $user['id'];
            header("Location: index.php");
            exit;
        } else {
            // Store Google data in session and redirect to age input step
            $_SESSION['google_data'] = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email
            ];
            header("Location: register.php?step=google_age");
            exit;
        }
    }
}

// Handle Google age submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['google_finish'])) {
    if (!isset($_SESSION['google_data'])) {
        $error = "Session data missing. Please try again.";
    } else {
        $data = $_SESSION['google_data'];
        $age = (int)$_POST['age'];
        $phone = trim($_POST['phone'] ?? '');

        if ($age < 8 || $age > 25) {
            $error = "Age must be between 8 and 25.";
        } else {
            $password = bin2hex(random_bytes(16)); // Random password for Google users
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, age, email, phone, password, verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['first_name'], $data['last_name'], $age, $data['email'], $phone ?: null, $hashed_password, 1]);
            $_SESSION['user_id'] = $pdo->lastInsertId();
            unset($_SESSION['google_data']);
            header("Location: index.php");
            exit;
        }
    }
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Fetch cart count for logged-in user
$cart_count = 0;
if ($is_logged_in) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_count = $stmt->fetchColumn();
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $age = (int)$_POST['age'];
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif ($age < 8 || $age > 25) {
        $error = "Age must be between 8 and 25.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already registered.";
        } else {
            $otp = rand(100000, 999999);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, age, email, phone, password, otp, verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$first_name, $last_name, $age, $email, $phone, $hashed_password, $otp, 0]);

            // Send OTP email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // Update with your SMTP host
                $mail->SMTPAuth = true;
                $mail->Username = 'kalonoidcom@gmail.com'; // Update with your email
                $mail->Password = 'bglc wcdf rgke heqj'; // Update with your password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                $mail->setFrom('info@kalonoid.com', 'EliteWinnersWorldwide');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Your OTP for Registration';
                $mail->Body = "Dear $first_name,<br>Your OTP for registration is: <b>$otp</b><br>Please enter this code to verify your account.";
                $mail->send();

                $_SESSION['email'] = $email;
                $_SESSION['otp_sent'] = true;
                header("Location: register.php?step=verify");
                exit;
            } catch (Exception $e) {
                $error = "Failed to send OTP: " . $mail->ErrorInfo;
            }
        }
    }
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp']) && isset($_SESSION['email'])) {
    $otp = trim($_POST['otp']);
    $email = $_SESSION['email'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND otp = ? AND verified = 0");
    $stmt->execute([$email, $otp]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET verified = 1, otp = NULL WHERE email = ?");
        $stmt->execute([$email]);
        $_SESSION['user_id'] = $user['id'];
        unset($_SESSION['email'], $_SESSION['otp_sent']);
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid OTP.";
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND verified = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - EliteWinnersWorldwide</title>
    <meta name="description" content="Sign up or log in to EliteWinnersWorldwide to access premium soccer training and apparel.">
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
    <style>
        body { font-family: 'Inter', sans-serif; overflow-x: hidden; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Montserrat', sans-serif; }
        html { scroll-behavior: smooth; }
        .logo { height: 50px; width: 50px; transform: scale(2.1); }
        .hero-gradient { background: linear-gradient(135deg, rgba(11, 122, 77, 0.9) 0%, rgba(26, 26, 26, 0.85) 100%); }
        .blurred-container { backdrop-filter: blur(10px); background: rgba(255, 255, 255, 0.2); }
    </style>
</head>
<body class="bg-eww-dark text-eww-dark font-body">
    <!-- Header / Navigation -->
    <header class="fixed w-full z-50 transition-all duration-300" id="header">
        <nav class="container mx-auto px-4 py-4 flex justify-between items-center">
            <!-- Logo -->
            <a href="index.php" class="flex items-center space-x-2 z-60">
                <img class="logo" src="logo.png" alt="logo">
                <span class="text-white font-heading font-bold text-xl hidden md:block">EliteWinnersWorldwide</span>
            </a>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="index.php#home" class="text-white hover:text-eww-gold transition-colors">Home</a>
                <a href="index.php#services" class="text-white hover:text-eww-gold transition-colors">Services</a>
                <a href="index.php#subshop" class="text-white hover:text-eww-gold transition-colors">Shop</a>
                <a href="index.php#about" class="text-white hover:text-eww-gold transition-colors">About</a>
                <a href="index.php#testimonials" class="text-white hover:text-eww-gold transition-colors">Testimonials</a>
                <a href="index.php#contact" class="text-white hover:text-eww-gold transition-colors">Contact</a>
                <a href="cart.php" class="text-white ml-4 relative" aria-label="Shopping cart">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span class="absolute -top-2 -right-2 bg-eww-gold text-eww-dark rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold"><?php echo $cart_count; ?></span>
                </a>
                <?php if (!$is_logged_in): ?>
                    <a href="register.php" class="bg-eww-gold text-eww-dark px-4 py-2 rounded-2xl font-semibold hover:bg-opacity-90 transition-all ml-4">Sign Up</a>
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
                <a href="index.php#home" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Home</a>
                <a href="index.php#services" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Services</a>
                <a href="index.php#subshop" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Shop</a>
                <a href="index.php#about" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">About</a>
                <a href="index.php#testimonials" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Testimonials</a>
                <a href="index.php#contact" class="text-white text-2xl font-heading font-semibold hover:text-eww-gold">Contact</a>
                <div class="pt-8 flex space-x-4">
                    <?php if (!$is_logged_in): ?>
                        <a href="register.php" class="bg-eww-gold text-eww-dark px-6 py-3 rounded-2xl font-semibold text-lg">Sign Up</a>
                    <?php endif; ?>
                    <a href="index.php#subshop" class="border border-eww-gold text-eww-gold px-6 py-3 rounded-2xl font-semibold text-lg">Shop</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <section class="relative min-h-screen pt-24 pb-12 flex items-center justify-center bg-eww-dark overflow-hidden">
        <div class="absolute inset-0 z-0">
            <div class="absolute inset-0 hero-gradient z-10"></div>
            <img src="https://images.unsplash.com/photo-1575361204480-aadea25e6e68?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80" 
                 alt="Soccer player in action" class="w-full h-full object-cover">
        </div>
        <div class="container mx-auto px-4 z-20 max-w-md">
            <?php if (isset($_GET['step']) && $_GET['step'] === 'verify' && isset($_SESSION['otp_sent'])): ?>
                <!-- OTP Verification Form -->
                <div class="blurred-container rounded-2xl shadow-lg p-8 text-white">
                    <h2 class="text-2xl font-heading font-bold mb-6 text-center">Verify Your Account</h2>
                    <?php if (isset($error)): ?>
                        <div class="mb-4 p-4 bg-red-500 text-white rounded-2xl flex justify-between items-center">
                            <span><?php echo htmlspecialchars($error); ?></span>
                            <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    <?php endif; ?>
                    <p class="mb-6 text-center">Enter the 6-digit OTP sent to your email.</p>
                    <form method="POST">
                        <div class="mb-4">
                            <label for="otp" class="block text-sm font-medium mb-1">OTP</label>
                            <input type="text" id="otp" name="otp" required class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-gold focus:border-eww-gold bg-transparent text-white placeholder-gray-400" maxlength="6">
                        </div>
                        <button type="submit" name="verify_otp" class="w-full bg-eww-gold text-eww-dark py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all">Verify OTP</button>
                    </form>
                </div>
            <?php elseif (isset($_GET['step']) && $_GET['step'] === 'google_age' && isset($_SESSION['google_data'])): ?>
                <!-- Google Age Input Form -->
                <div class="blurred-container rounded-2xl shadow-lg p-8 text-white">
                    <h2 class="text-2xl font-heading font-bold mb-6 text-center">Complete Your Profile</h2>
                    <?php if (isset($error)): ?>
                        <div class="mb-4 p-4 bg-red-500 text-white rounded-2xl flex justify-between items-center">
                            <span><?php echo htmlspecialchars($error); ?></span>
                            <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-4">
                            <label for="age" class="block text-sm font-medium mb-1">Age (must be between 8 and 25)</label>
                            <input type="number" id="age" name="age" min="8" max="25" required class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-gold focus:border-eww-gold bg-transparent text-white placeholder-gray-400">
                        </div>
                        <div class="mb-4">
                            <label for="phone" class="block text-sm font-medium mb-1">Phone Number (optional)</label>
                            <input type="tel" id="phone" name="phone" class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-gold focus:border-eww-gold bg-transparent text-white placeholder-gray-400">
                        </div>
                        <button type="submit" name="google_finish" class="w-full bg-eww-gold text-eww-dark py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all">Finish</button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Registration/Login Toggle -->
                <div class="blurred-container rounded-2xl shadow-lg p-8 text-white">
                    <div class="flex justify-center mb-6">
                        <button id="register-tab" class="px-4 py-2 font-semibold border-b-2 border-eww-gold text-eww-gold">Sign Up</button>
                        <button id="login-tab" class="px-4 py-2 font-semibold text-gray-400">Log In</button>
                    </div>
                    <?php if (isset($error)): ?>
                        <div class="mb-4 p-4 bg-red-500 text-white rounded-2xl flex justify-between items-center">
                            <span><?php echo htmlspecialchars($error); ?></span>
                            <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    <?php endif; ?>
                    <!-- Registration Form -->
                    <form id="register-form" method="POST">
                        <input type="hidden" name="register" value="1">
                        <div id="step1">
                            <div class="mb-4">
                                <label for="email" class="block text-sm font-medium mb-1">Email Address</label>
                                <input type="email" id="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-gold focus:border-eww-gold bg-transparent text-white placeholder-gray-400">
                            </div>
                            <div class="flex items-center mb-4">
                                <hr class="flex-grow border-gray-400">
                                <span class="px-2 text-gray-400">or</span>
                                <hr class="flex-grow border-gray-400">
                            </div>
                            <a href="<?php echo htmlspecialchars($google_auth_url); ?>" class="w-full bg-white text-gray-800 py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all mb-4 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24" class="mr-2"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/><path d="M1 1h22v22H1z" fill="none"/></svg>
                                Continue with Google
                            </a>
                            <button type="button" onclick="nextStep(2)" class="w-full bg-eww-gold text-eww-dark py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all">Signup</button>
                        </div>
                        <div id="step2" class="hidden">
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="first_name" class="block text-sm font-medium mb-1">First Name</label>
                                    <input type="text" id="first_name" name="first_name" required class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-gold focus:border-eww-gold bg-transparent text-white placeholder-gray-400">
                                </div>
                                <div>
                                    <label for="last_name" class="block text-sm font-medium mb-1">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" required class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-gold focus:border-eww-gold bg-transparent text-white placeholder-gray-400">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="age" class="block text-sm font-medium mb-1">Age</label>
                                <input type="number" id="age" name="age" min="8" max="25" required class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-gold focus:border-eww-gold bg-transparent text-white placeholder-gray-400">
                            </div>
                            <div class="mb-4">
                                <label for="phone" class="block text-sm font-medium mb-1">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-gold focus:border-eww-gold bg-transparent text-white placeholder-gray-400">
                            </div>
                            <button type="button" onclick="nextStep(3)" class="w-full bg-eww-gold text-eww-dark py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all mb-2">Continue</button>
                            <button type="button" onclick="nextStep(1)" class="w-full text-gray-400 py-3">Back</button>
                        </div>
                        <div id="step3" class="hidden">
                            <div class="mb-4">
                                <label for="password" class="block text-sm font-medium mb-1">Password</label>
                                <input type="password" id="password" name="password" required class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-gold focus:border-eww-gold bg-transparent text-white placeholder-gray-400">
                            </div>
                            <div class="mb-6">
                                <label for="confirm_password" class="block text-sm font-medium mb-1">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-gold focus:border-eww-gold bg-transparent text-white placeholder-gray-400">
                            </div>
                            <button type="button" onclick="validateAndSubmit()" class="w-full bg-eww-gold text-eww-dark py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all mb-2">Finish</button>
                            <button type="button" onclick="nextStep(2)" class="w-full text-gray-400 py-3">Back</button>
                        </div>
                    </form>
                    <!-- Login Form -->
                    <form id="login-form" class="hidden" method="POST">
                        <div class="mb-4">
                            <label for="login_email" class="block text-sm font-medium mb-1">Email Address</label>
                            <input type="email" id="login_email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-gold focus:border-eww-gold bg-transparent text-white placeholder-gray-400">
                        </div>
                        <div class="mb-6">
                            <label for="login_password" class="block text-sm font-medium mb-1">Password</label>
                            <input type="password" id="login_password" name="password" required class="w-full px-4 py-2 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-eww-gold focus:border-eww-gold bg-transparent text-white placeholder-gray-400">
                        </div>
                        <div class="flex items-center mb-4">
                            <hr class="flex-grow border-gray-400">
                            <span class="px-2 text-gray-400">or</span>
                            <hr class="flex-grow border-gray-400">
                        </div>
                        <a href="<?php echo htmlspecialchars($google_auth_url); ?>" class="w-full bg-white text-gray-800 py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all mb-4 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24" class="mr-2"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/><path d="M1 1h22v22H1z" fill="none"/></svg>
                            Continue with Google
                        </a>
                        <button type="submit" name="login" class="w-full bg-eww-gold text-eww-dark py-3 rounded-2xl font-semibold hover:bg-opacity-90 transition-all">Log In</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-eww-dark text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div>
                    <a href="index.php" class="flex items-center space-x-2 mb-6">
                        <img class="logo" src="logo.png" alt="logo">
                        <span class="font-heading font-bold text-xl">EliteWinnersWorldwide</span>
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
                        <li><a href="index.php#home" class="text-gray-400 hover:text-eww-gold transition-colors">Home</a></li>
                        <li><a href="index.php#services" class="text-gray-400 hover:text-eww-gold transition-colors">Services</a></li>
                        <li><a href="index.php#subshop" class="text-gray-400 hover:text-eww-gold transition-colors">Shop</a></li>
                        <li><a href="index.php#about" class="text-gray-400 hover:text-eww-gold transition-colors">About</a></li>
                        <li><a href="index.php#testimonials" class="text-gray-400 hover:text-eww-gold transition-colors">Testimonials</a></li>
                        <li><a href="index.php#contact" class="text-gray-400 hover:text-eww-gold transition-colors">Contact</a></li>
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
                <div>
                    <h3 class="font-heading font-bold text-lg mb-6">Newsletter</h3>
                    <p class="text-gray-400 mb-4">Subscribe to get training tips, product updates, and special offers.</p>
                    <form class="flex flex-col space-y-3">
                        <input type="email" placeholder="Your email address" class="px-4 py-2 rounded-2xl bg-gray-800 text-white border border-gray-700 focus:border-eww-gold focus:ring-2 focus:ring-eww-gold">
                        <button type="submit" class="bg-eww-gold text-eww-dark py-2 rounded-2xl font-semibold hover:bg-opacity-90 transition-all">Subscribe</button>
                    </form>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-12 pt-8 text-center">
                <p class="text-gray-400 text-sm">© <span id="current-year"></span> EliteWinnersWorldwide. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        document.getElementById('current-year').textContent = new Date().getFullYear();

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('bg-eww-dark', 'shadow-lg');
            } else {
                header.classList.remove('bg-eww-dark', 'shadow-lg');
            }
        });

        // Mobile menu toggle
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('-translate-x-full');
        });

        const mobileMenuLinks = mobileMenu.querySelectorAll('a');
        mobileMenuLinks.forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.add('-translate-x-full');
            });
        });

        // Form toggle
        const registerTab = document.getElementById('register-tab');
        const loginTab = document.getElementById('login-tab');
        const registerForm = document.getElementById('register-form');
        const loginForm = document.getElementById('login-form');

        if (registerTab && loginTab) {
            registerTab.addEventListener('click', () => {
                registerTab.classList.add('border-b-2', 'border-eww-gold', 'text-eww-gold');
                loginTab.classList.remove('border-b-2', 'border-eww-gold', 'text-eww-gold');
                loginTab.classList.add('text-gray-400');
                registerForm.classList.remove('hidden');
                loginForm.classList.add('hidden');
            });

            loginTab.addEventListener('click', () => {
                loginTab.classList.add('border-b-2', 'border-eww-gold', 'text-eww-gold');
                registerTab.classList.remove('border-b-2', 'border-eww-gold', 'text-eww-gold');
                registerTab.classList.add('text-gray-400');
                loginForm.classList.remove('hidden');
                registerForm.classList.add('hidden');
            });
        }

        // Multi-step form
        function nextStep(step) {
            document.getElementById('step1').classList.add('hidden');
            document.getElementById('step2').classList.add('hidden');
            document.getElementById('step3').classList.add('hidden');
            document.getElementById(`step${step}`).classList.remove('hidden');
        }

        function validateAndSubmit() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return;
            }
            document.getElementById('register-form').submit();
        }
    </script>
</body>
</html>