<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

function sendBookingStatusEmail($to, $name, $status, $session_type = '', $date = '') {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'email'; 
        $mail->Password   = 'password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('no-reply@elitewinnersworldwide.com', 'EliteWinners');
        $mail->addAddress($to, $name);
        $mail->addReplyTo('support@elitewinnersworldwide.com', 'Support');
        $mail->isHTML(true);

        if ($status === 'Confirmed') {
            $mail->Subject = "Your EliteWinners Session is Confirmed!";
            $mail->Body = "
                <h2>Hi $name,</h2>
                <p>Great news! Your coaching session has been <strong>confirmed</strong>.</p>
                <ul>
                    <li><strong>Session:</strong> $session_type</li>
                    <li><strong>Date:</strong> $date</li>
                </ul>
                <p>We look forward to helping you level up!</p>
                <p><strong>EliteWinnersWorldwide Team</strong></p>
            ";
        } 
        elseif ($status === 'Completed') {
            $mail->Subject = "Session Complete – Thank You!";
            $mail->Body = "
                <h2>Well done, $name!</h2>
                <p>Your session has been marked as <strong>completed</strong>.</p>
                <p><strong>Thank you</strong> for trusting us with your growth. It was an honor to coach you and see you push your limits.</p>
                <p>Keep rising — the world needs more winners like you!</p>
                <p><em>With gratitude,</em><br><strong>EliteWinnersWorldwide Team</strong></p>
            ";
        }

        $mail->AltBody = strip_tags($mail->Body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>