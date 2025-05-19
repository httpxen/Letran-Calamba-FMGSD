<?php
include 'db/db.php';
require 'vendor/autoload.php'; // PHPMailer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];

    // Check if email exists in the database
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Generate a unique token
        $token = bin2hex(random_bytes(50));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour

        // Store the token in the database (using password_resets table)
        $sql = "INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE token = ?, created_at = NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $email, $token, $token);
        $stmt->execute();
        $stmt->close();

        // Send email with reset link using PHPMailer
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Update with your SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = 'opulenciaandrei23@gmail.com'; // Your email
            $mail->Password = 'pkou mbww kqgc hgrh'; // Your app-specific password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('your_email@gmail.com', 'EHS System');
            $mail->addAddress($email);
            $mail->addReplyTo('your_email@gmail.com', 'EHS System Support');

            // Content
            $reset_link = "http://localhost:8000/reset_password.php?email=" . urlencode($email) . "&token=" . $token;
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request for EHS System';
            $mail->Body = "
                <h2>Password Reset Request</h2>
                <p>Dear user,</p>
                <p>We received a request to reset your password for the EHS System.</p>
                <p>Click the link below to reset your password:</p>
                <p><a href='$reset_link'>Reset Password</a></p>
                <p>This link will expire in 1 hour.</p>
                <p>If you did not request a password reset, please ignore this email.</p>
                <p>Best regards,<br>EHS System Team</p>
            ";
            $mail->AltBody = "Dear user, we received a request to reset your password for the EHS System. Click the link to reset your password: $reset_link. This link will expire in 1 hour. If you did not request a password reset, please ignore this email. Best regards, EHS System Team";

            $mail->send();
            $success = "A password reset link has been sent to your email.";
        } catch (Exception $e) {
            $error = "Failed to send email. Error: {$mail->ErrorInfo}";
        }
    } else {
        $error = "No account found with that email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .forgot-password-container {
            opacity: 0;
            animation: fadeIn 0.8s ease-out forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        .logo {
            transform: scale(0.5) rotate(-10deg);
            opacity: 0;
            animation: bounceIn 0.8s ease-out 0.4s forwards;
            position: relative;
        }

        .logo::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 80px;
            height: 80px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.3) 0%, transparent 70%);
            transform: translate(-50%, -50%) scale(0);
            animation: glowPulse 2s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes bounceIn {
            0% {
                transform: scale(0.5) rotate(-10deg);
                opacity: 0;
            }
            60% {
                transform: scale(1.1) rotate(5deg);
                opacity: 0.7;
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        @keyframes glowPulse {
            0%, 100% { transform: translate(-50%, -50%) scale(0.8); opacity: 0.3; }
            50% { transform: translate(-50%, -50%) scale(1.2); opacity: 0.5; }
        }

        .title-text {
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 0.6s ease-out 0.6s forwards;
        }

        .subtitle-text {
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 0.6s ease-out 0.8s forwards;
        }

        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-input {
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 0.6s ease-out 1s forwards;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.7);
            transform: translateY(20px) scale(1.02);
        }

        .form-input:not(:placeholder-shown) + .form-label,
        .form-input:focus + .form-label {
            transform: translateY(-2.5rem) scale(0.8);
            color: #3b82f6;
            font-weight: 600;
        }

        .form-label {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            color: #6b7280;
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .submit-button {
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 0.6s ease-out 1.2s forwards;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .submit-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
        }

        .submit-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s ease;
        }

        .submit-button:hover::before {
            left: 100%;
        }

        .submit-button::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }

        .submit-button:active::after {
            width: 400px;
            height: 400px;
        }

        .loading-spinner {
            display: none;
            border: 4px solid #ffffff;
            border-top: 4px solid transparent;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            animation: spin 0.8s linear infinite;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        @keyframes spin {
            0% { transform: translateX(-50%) rotate(0deg); }
            100% { transform: translateX(-50%) rotate(360deg); }
        }

        .error-message, .success-message {
            transform: translateX(0);
            opacity: 0;
            animation: shakeError 0.5s ease-out forwards;
        }

        @keyframes shakeError {
            0% { transform: translateX(0); opacity: 0; }
            20% { transform: translateX(-10px); opacity: 1; }
            40% { transform: translateX(10px); }
            60% { transform: translateX(-5px); }
            80% { transform: translateX(5px); }
            100% { transform: translateX(0); opacity: 1; }
        }

        .link-animate {
            position: relative;
            display: inline-block;
            color: #2563eb;
            transition: color 0.3s ease, transform 0.3s ease;
        }

        .link-animate::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background-color: #3b82f6;
            transition: width 0.3s ease;
        }

        .link-animate:hover {
            color: #1e40af;
            transform: translateY(-1px);
        }

        .link-animate:hover::after {
            width: 100%;
        }

        .link-animate:active {
            animation: pulseLink 0.3s ease;
        }

        @keyframes pulseLink {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        @keyframes slideUp {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .forgot-password-container {
                margin: 1rem;
            }
        }
    </style>
    <title>EHS | Forgot Password</title>
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center">
    <div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl flex flex-col md:flex-row overflow-hidden forgot-password-container">
        <!-- Left Side: School Image -->
        <div class="hidden md:block md:w-1/2 bg-cover bg-center" style="background-image: url('https://the-post-assets.sgp1.digitaloceanspaces.com/2020/08/LETRAN-15.jpg')"></div>
        
        <!-- Right Side: Forgot Password Form -->
        <div class="w-full md:w-1/2 p-8 flex flex-col justify-center">
            <div class="flex justify-center mb-6">
                <img src="assets/images/icon.png" alt="School Logo" class="h-16 w-16 object-contain logo">
            </div>
            <h1 class="text-4xl font-extrabold text-center text-gray-800 mb-2 title-text">Letran Calamba</h1>
            <p class="text-lg text-center text-gray-600 mb-6 subtitle-text">Reset your password</p>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6 text-center error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-6 text-center success-message"><?php echo $success; ?></div>
            <?php endif; ?>

            <form class="space-y-6" method="POST" action="forgot_password.php" id="forgotPasswordForm">
                <div class="form-group">
                    <input type="email" name="email" id="email" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none form-input" placeholder=" " required>
                    <label for="email" class="form-label">Enter your email</label>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white p-3 rounded-lg font-semibold submit-button" id="submitButton">
                    <span id="buttonText">Send Reset Link</span>
                    <div class="loading-spinner" id="spinner"></div>
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-600">
                Back to <a href="login.php" class="text-blue-600 link-animate">Login</a>
            </p>
        </div>
    </div>

    <script>
        // Form Submission Animation
        const form = document.getElementById('forgotPasswordForm');
        const submitButton = document.getElementById('submitButton');
        const buttonText = document.getElementById('buttonText');
        const spinner = document.getElementById('spinner');

        form.addEventListener('submit', () => {
            submitButton.disabled = true;
            buttonText.style.opacity = '0';
            spinner.style.display = 'block';
            setTimeout(() => {
                submitButton.disabled = false;
                buttonText.style.opacity = '1';
                spinner.style.display = 'none';
            }, 2000); // Simulate loading for 2 seconds
        });
    </script>
</body>
</html>