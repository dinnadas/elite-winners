<?php
header('Content-Type: application/json');
include 'config.php'; // Include database connection
require 'vendor/autoload.php'; // Include PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    // Check if the request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    // Retrieve and sanitize form data
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $age = filter_input(INPUT_POST, 'age', FILTER_SANITIZE_NUMBER_INT);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $session_type = filter_input(INPUT_POST, 'session-type', FILTER_SANITIZE_STRING);
    $preferred_date = filter_input(INPUT_POST, 'preferred-date', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

    // Basic server-side validation
    if (empty($name) || empty($age) || empty($email) || empty($session_type)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }

    if ($age < 8 || $age > 25) {
        echo json_encode(['success' => false, 'message' => 'Age must be between 8 and 25']);
        exit;
    }

    // Validate session type
    $valid_sessions = ['one-on-one', 'group', 'goalkeeper', 'pro'];
    if (!in_array($session_type, $valid_sessions)) {
        echo json_encode(['success' => false, 'message' => 'Invalid session type']);
        exit;
    }

    // Format session type for display
    $session_display = ucwords(str_replace('-', ' ', $session_type));

    // Prepare and execute database insertion
    $stmt = $pdo->prepare("
        INSERT INTO bookings (name, age, email, phone, session_type, preferred_date, notes)
        VALUES (:name, :age, :email, :phone, :session_type, :preferred_date, :notes)
    ");

    $stmt->execute([
        'name' => $name,
        'age' => $age,
        'email' => $email,
        'phone' => $phone ?: null,
        'session_type' => $session_type,
        'preferred_date' => $preferred_date ?: null,
        'notes' => $notes ?: null
    ]);

    // Initialize PHPMailer
    $mail = new PHPMailer(true);

    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'kalonoidcom@gmail.com';
    $mail->Password = 'bglc wcdf rgke heqj';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Sender and Reply-To
    $mail->setFrom('info@kalonoid.com', 'EliteWinnersWorldwide');
    $mail->addReplyTo('info@kalonoid.com', 'EliteWinnersWorldwide');

    // =============================================
    // EMAIL TO CLIENT - CONFIRMATION
    // =============================================
    $mail->addAddress($email, $name);
    $mail->isHTML(true);
    $mail->Subject = 'Booking Confirmed! – EliteWinnersWorldwide';

    $mail->Body = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Booking Confirmed</title>
        <style>
            body {margin:0; padding:0; background:#f4f4f4; font-family:Arial,Helvetica,sans-serif;}
            table {border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;}
            a {color:#FFD700; text-decoration:none;}
            .btn {background:#FFD700; color:#1A1A1A; padding:14px 30px; border-radius:4px; font-weight:600; display:inline-block;}
        </style>
    </head>
    <body style="background:#f4f4f4; margin:0; padding:20px 0;">
        <center>
        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; margin:auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
            <!-- HEADER -->
            <tr>
                <td bgcolor="#1A1A1A" style="padding:20px; text-align:center;">
                    <img src="https://elite.kalonoid.com/logo.png" alt="EliteWinnersWorldwide" width="180" style="display:block; margin:auto;">
                </td>
            </tr>

            <!-- HERO -->
            <tr>
                <td style="padding:40px 30px; text-align:center; background:linear-gradient(135deg, #FFD700, #FFC107); color:#1A1A1A;">
                    <h1 style="margin:0; font-size:28px; font-weight:700;">Booking Confirmed!</h1>
                    <p style="margin:10px 0 0; font-size:16px;">Hi <strong>' . htmlspecialchars($name) . '</strong>, your session is locked in.</p>
                </td>
            </tr>

            <!-- DETAILS CARD -->
            <tr>
                <td style="padding:30px; background:#ffffff;">
                    <h2 style="margin-top:0; color:#1A1A1A; font-size:20px;">Your Session Details</h2>
                    <table width="100%" cellpadding="8" cellspacing="0" style="font-size:15px; color:#333;">
                        <tr><td width="40%"><strong>Name</strong></td><td>' . htmlspecialchars($name) . '</td></tr>
                        <tr><td><strong>Age</strong></td><td>' . $age . '</td></tr>
                        <tr><td><strong>Email</strong></td><td>' . htmlspecialchars($email) . '</td></tr>
                        <tr><td><strong>Phone</strong></td><td>' . ($phone ?: 'Not provided') . '</td></tr>
                        <tr><td><strong>Session Type</strong></td><td>' . $session_display . '</td></tr>
                        <tr><td><strong>Preferred Date</strong></td><td>' . ($preferred_date ?: 'Not specified') . '</td></tr>
                        <tr><td><strong>Notes</strong></td><td>' . ($notes ? nl2br(htmlspecialchars($notes)) : 'None') . '</td></tr>
                    </table>
                </td>
            </tr>

            <!-- FOOTER -->
            <tr>
                <td bgcolor="#1A1A1A" style="padding:25px; color:#bbbbbb; font-size:13px; text-align:center;">
                    <p style="margin:0 0 10px;">
                        <a href="https://facebook.com/elitewinners" style="color:#FFD700; margin:0 8px;">Facebook</a> |
                        <a href="https://instagram.com/elitewinners" style="color:#FFD700; margin:0 8px;">Instagram</a> |
                        <a href="https://twitter.com/elitewinners" style="color:#FFD700; margin:0 8px;">Twitter</a>
                    </p>
                    <p style="margin:0;">
                        EliteWinnersWorldwide • Nairobi, Kenya<br>
                        <a href="mailto:elitewinners@gmail.com" style="color:#FFD700;">elitewinners@gmail.com</a>
                    </p>
                    <p style="margin:15px 0 0; font-size:11px; color:#777;">
                        <a href="*|UNSUB|*" style="color:#777;">Unsubscribe</a> | 
                        <a href="https://kalonoid.com/privacy" style="color:#777;">Privacy Policy</a>
                    </p>
                </td>
            </tr>
        </table>
        </center>
    </body>
    </html>';

    $mail->AltBody = "Dear $name,\n\nThank you for booking a $session_display session with EliteWinnersWorldwide. We have received your request and will contact you soon to confirm your session details.\n\nBooking Details:\nName: $name\nAge: $age\nEmail: $email\nPhone: " . ($phone ?: 'Not provided') . "\nSession Type: $session_display\nPreferred Date: " . ($preferred_date ?: 'Not specified') . "\nNotes: " . ($notes ?: 'None') . "\n\nWe look forward to helping you achieve your breakthrough!\n\nBest regards,\nEliteWinnersWorldwide Team";

    // Send email to user
    $mail->send();

    // Clear recipients for admin email
    $mail->clearAddresses();

    // =============================================
    // EMAIL TO ADMIN - NOTIFICATION
    // =============================================
    $mail->addAddress('dinaolenku@gmail.com', 'Admin');
    $mail->Subject = 'New Booking Request – EliteWinnersWorldwide';

    $mail->Body = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>New Booking</title>
        <style>
            body {margin:0; padding:0; background:#f9f9f9; font-family:Arial,sans-serif;}
            table {border-collapse:collapse;}
            a {color:#FFD700;}
            .btn {background:#FFD700; color:#1A1A1A; padding:12px 28px; border-radius:4px; font-weight:600; display:inline-block; font-size:15px;}
        </style>
    </head>
    <body style="background:#f9f9f9; margin:0; padding:20px 0;">
        <center>
        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; margin:auto; background:#fff; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <!-- HEADER -->
            <tr><td bgcolor="#1A1A1A" style="padding:15px; text-align:center;">
                <img src="https://elite.kalonoid.com/logo.png" alt="EliteWinnersWorldwide" width="140">
            </td></tr>

            <!-- TITLE -->
            <tr><td style="padding:30px 25px 20px; background:#fff; color:#1A1A1A; font-size:22px; font-weight:600; text-align:center;">
                New Booking Request
            </td></tr>

            <!-- DETAILS -->
            <tr><td style="padding:0 25px 30px;">
                <table width="100%" cellpadding="7" cellspacing="0" style="font-size:14px; color:#333;">
                    <tr><td width="40%"><strong>Name</strong></td><td>' . htmlspecialchars($name) . '</td></tr>
                    <tr><td><strong>Age</strong></td><td>' . $age . '</td></tr>
                    <tr><td><strong>Email</strong></td><td>' . htmlspecialchars($email) . '</td></tr>
                    <tr><td><strong>Phone</strong></td><td>' . ($phone ?: '–') . '</td></tr>
                    <tr><td><strong>Session</strong></td><td>' . $session_display . '</td></tr>
                    <tr><td><strong>Date</strong></td><td>' . ($preferred_date ?: '–') . '</td></tr>
                    <tr><td><strong>Notes</strong></td><td>' . ($notes ? htmlspecialchars($notes) : '–') . '</td></tr>
                </table>
            </td></tr>

            <!-- ACTION -->
            <tr><td style="padding:0 25px 30px; text-align:center;">
                <a href="https://elite.kalonoid.com/bookings.php" class="btn">
                    Open Admin Panel
                </a>
            </td></tr>

            <!-- FOOTER -->
            <tr><td bgcolor="#1A1A1A" style="padding:20px; color:#aaa; font-size:12px; text-align:center;">
                EliteWinnersWorldwide System • Auto-generated • ' . date('Y') . '
            </td></tr>
        </table>
        </center>
    </body>
    </html>';

    $mail->AltBody = "New Booking Request\n\nA new booking request has been submitted.\n\nBooking Details:\nName: $name\nAge: $age\nEmail: $email\nPhone: " . ($phone ?: 'Not provided') . "\nSession Type: $session_display\nPreferred Date: " . ($preferred_date ?: 'Not specified') . "\nNotes: " . ($notes ?: 'None') . "\n\nPlease review and follow up with the client.\n\nBest regards,\nEliteWinnersWorldwide System";

    // Send email to admin
    $mail->send();

    // Return success response
    echo json_encode(['success' => true, 'message' => 'Booking request submitted successfully! Check your email.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>