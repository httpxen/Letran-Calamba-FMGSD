<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Kung gumagamit ng Composer
// Manual installation: i-uncomment kung hindi gumagamit ng Composer
// require 'vendor/PHPMailer/src/PHPMailer.php';
// require 'vendor/PHPMailer/src/SMTP.php';
// require 'vendor/PHPMailer/src/Exception.php';

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input data
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);

    // Validate input
    if (empty($name) || empty($email) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.php?status=error&message=Invalid input");
        exit;
    }

    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Palitan kung ibang SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'opulenciaandrei23@gmail.com'; // Ilagay ang iyong email
        $mail->Password = 'pkou mbww kqgc hgrh'; // Ilagay ang iyong password o App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom($email, $name);
        $mail->addAddress('opulenciaandrei23@gmail.com'); // Receiving email

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'New Contact Form Submission';
        $mail->Body = "<h3>New Message from $name</h3>
                       <p><strong>Email:</strong> $email</p>
                       <p><strong>Message:</strong><br>$message</p>";
        $mail->AltBody = "Name: $name\nEmail: $email\nMessage: $message";

        // Send email
        $mail->send();
        header("Location: index.php?status=success&message=Message sent successfully");
    } catch (Exception $e) {
        header("Location: index.php?status=error&message=Failed to send message: {$mail->ErrorInfo}");
    }
} else {
    header("Location: index.php?status=error&message=Invalid request");
}
?>