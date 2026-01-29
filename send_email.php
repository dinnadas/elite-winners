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

        // ── Common variables for branding ───────────────────────────────────────
        $brand_green   = '#0B7A4D';
        $brand_dark    = '#111827';
        $brand_gold    = '#D97706';
        $light_bg      = '#F9FAFB';
        $gray_text     = '#4B5563';

        // ── Subject & Body ──────────────────────────────────────────────────────
        if ($status === 'Confirmed') {
            $mail->Subject = "Your EliteWinners Session is Confirmed!";

            $body = '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Your Session is Confirmed</title>
                <!--[if !mso]><!-->
                <style type="text/css">
                    @media only screen and (max-width: 480px) {
                        .container { width: 100% !important; }
                        .mobile-center { text-align: center !important; }
                        .mobile-stack { display: block !important; width: 100% !important; }
                        .btn { width: 100% !important; }
                    }
                </style>
                <!--<![endif]-->
            </head>
            <body style="margin:0; padding:0; background-color:'.$light_bg.'; font-family:Arial,Helvetica,sans-serif; -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:'.$light_bg.'; padding:20px 0;">
                    <tr>
                        <td align="center">

                            <!-- Main container -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" class="container" style="background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,0.08);">
                                
                                <!-- Header -->
                                <tr>
                                    <td style="background: linear-gradient(to right, '.$brand_green.', #085f3a); padding:40px 30px; text-align:center; color:#ffffff;">
                                        <h1 style="margin:0; font-size:32px; font-weight:bold; letter-spacing:-0.5px;">Session Confirmed!</h1>
                                        <p style="margin:12px 0 0; font-size:18px; opacity:0.95;">Great news, '.$name.'!</p>
                                    </td>
                                </tr>

                                <!-- Content -->
                                <tr>
                                    <td style="padding:40px 30px;">
                                        <p style="margin:0 0 24px; font-size:16px; line-height:1.6; color:'.$gray_text.';">
                                            Your coaching session has been <strong style="color:'.$brand_green.';">confirmed</strong>.
                                        </p>

                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 32px;">
                                            <tr>
                                                <td style="padding:16px; background-color:'.$light_bg.'; border-radius:8px; border:1px solid #e5e7eb;">
                                                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                                        <tr>
                                                            <td style="font-size:15px; color:'.$gray_text.'; padding-bottom:8px;"><strong>Session Type:</strong></td>
                                                            <td style="font-size:15px; color:'.$brand_dark.'; text-align:right;">'.$session_type.'</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="font-size:15px; color:'.$gray_text.'; padding-bottom:8px;"><strong>Date & Time:</strong></td>
                                                            <td style="font-size:15px; color:'.$brand_dark.'; text-align:right;">'.$date.'</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="margin:0 0 32px; font-size:16px; line-height:1.6; color:'.$gray_text.';">
                                            We’re excited to work with you and help you reach new heights. Prepare any questions — let’s make this session count!
                                        </p>

                                        <!-- CTA Button -->
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td align="center">
                                                    <a href="https://elitewinnersworldwide.com/profile" target="_blank" 
                                                       style="display:inline-block; background-color:'.$brand_green.'; color:#ffffff; font-size:16px; font-weight:bold; text-decoration:none; padding:16px 36px; border-radius:8px; line-height:1;">
                                                        View My Sessions
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- Footer -->
                                <tr>
                                    <td style="background-color:#f3f4f6; padding:30px; text-align:center; font-size:14px; color:'.$gray_text.'; border-top:1px solid #e5e7eb;">
                                        <p style="margin:0 0 12px;">EliteWinnersWorldwide Team</p>
                                        <p style="margin:0 0 8px;">
                                            <a href="mailto:support@elitewinnersworldwide.com" style="color:'.$brand_green.'; text-decoration:underline;">support@elitewinnersworldwide.com</a>
                                        </p>
                                        <p style="margin:0; font-size:13px; color:#9ca3af;">
                                            You’re receiving this because you booked a session with us.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>
                </table>
            </body>
            </html>';
        } 
        elseif ($status === 'Completed') {
            $mail->Subject = "Session Complete – Thank You!";

            $body = '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Session Completed – Thank You</title>
                <!--[if !mso]><!-->
                <style type="text/css">
                    @media only screen and (max-width: 480px) {
                        .container { width: 100% !important; }
                        .mobile-center { text-align: center !important; }
                    }
                </style>
                <!--<![endif]-->
            </head>
            <body style="margin:0; padding:0; background-color:'.$light_bg.'; font-family:Arial,Helvetica,sans-serif;">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:'.$light_bg.'; padding:20px 0;">
                    <tr>
                        <td align="center">

                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" class="container" style="background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,0.08);">

                                <!-- Header -->
                                <tr>
                                    <td style="background: linear-gradient(to right, '.$brand_green.', #085f3a); padding:40px 30px; text-align:center; color:#ffffff;">
                                        <h1 style="margin:0; font-size:32px; font-weight:bold;">Well Done!</h1>
                                        <p style="margin:12px 0 0; font-size:18px; opacity:0.95;">Session Completed</p>
                                    </td>
                                </tr>

                                <!-- Content -->
                                <tr>
                                    <td style="padding:40px 30px; text-align:center;">
                                        <p style="margin:0 0 24px; font-size:16px; line-height:1.6; color:'.$gray_text.';">
                                            Hi '.$name.',<br>Your session has been marked as <strong style="color:'.$brand_green.';">completed</strong>.
                                        </p>

                                        <p style="margin:0 0 32px; font-size:16px; line-height:1.6; color:'.$gray_text.';">
                                            Thank you for trusting us with your growth.<br>
                                            It was an honor to coach you and witness your progress.
                                        </p>

                                        <p style="margin:0 0 32px; font-size:17px; font-style:italic; color:'.$brand_dark.';">
                                            Keep rising — the world needs more winners like you!
                                        </p>

                                        <!-- CTA -->
                                        <a href="https://elitewinnersworldwide.com/feedback" target="_blank" 
                                           style="display:inline-block; background-color:'.$brand_green.'; color:#ffffff; font-size:16px; font-weight:bold; text-decoration:none; padding:16px 40px; border-radius:8px;">
                                            Share Feedback (optional)
                                        </a>
                                    </td>
                                </tr>

                                <!-- Footer -->
                                <tr>
                                    <td style="background-color:#f3f4f6; padding:30px; text-align:center; font-size:14px; color:'.$gray_text.'; border-top:1px solid #e5e7eb;">
                                        <p style="margin:0 0 12px;"><strong>EliteWinnersWorldwide Team</strong></p>
                                        <p style="margin:0 0 8px;">
                                            <a href="mailto:support@elitewinnersworldwide.com" style="color:'.$brand_green.'; text-decoration:underline;">support@elitewinnersworldwide.com</a>
                                        </p>
                                        <p style="margin:0; font-size:13px; color:#9ca3af;">
                                            Questions? We’re here to help.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>
                </table>
            </body>
            </html>';
        }

        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>