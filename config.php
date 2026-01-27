<?php
$host = 'localhost';
$dbname = '';
$username = ''; 
$password = ''; 

define('STRIPE_PUBLISHABLE_KEY', ''); 
define('STRIPE_SECRET_KEY', '');
define('STRIPE_SUCCESS_URL', 'https://domain/success.php?session_id={CHECKOUT_SESSION_ID}');
define('STRIPE_CANCEL_URL', 'https://domain/cart.php');
define('CURRENCY', 'usd');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>