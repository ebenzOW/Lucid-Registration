<?php
// reset_helper.php
use PHPMailer\PHPMailer\PHPMailer;

function sendPasswordResetCode(mysqli $conn, string $email): bool {
    // 1. Locate user in LOGIN table
    $stmt = $conn->prepare("SELECT User_ID FROM LOGIN WHERE User_Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows !== 1) {
        return false; // Checking if Email Exists
    }

    $user = $result->fetch_assoc();

    // 2. Generate 6-digit code and upsert into PASSWORD_RESET
    $secureNumber = random_int(100000, 999999);
    $resetStmt = $conn->prepare("INSERT INTO PASSWORD_RESET (User_ID, Reset_Code, Time_Code_Sent)
                                VALUES (?, ?, CURRENT_TIMESTAMP)
                                ON DUPLICATE KEY UPDATE 
                                    Reset_Code = VALUES(Reset_Code), 
                                    Time_Code_Sent = CURRENT_TIMESTAMP");
    $resetStmt->bind_param("is", $user["User_ID"], $secureNumber);
    $resetStmt->execute();
    $resetStmt->close();

    // 3. Dispatch code via PHPMailer
    $mailConfig = require 'email_config.php';
    require_once 'vendor/autoload.php';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $mailConfig['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailConfig['smtp_user'];
        $mail->Password   = $mailConfig['smtp_pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $mailConfig['smtp_port'];

        $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
        $mail->addAddress($email);
        $mail->Subject = 'Lucid University - Password Reset Code';
        $mail->Body    = "Your verification code is: $secureNumber\n\nThis code expires in 15 minutes.";
        $mail->send();

        // Stash email in session for reset_password.php
        $_SESSION['reset_email'] = $email;
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

?>