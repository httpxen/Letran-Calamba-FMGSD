<?php
session_start();

// Check if fullname is set in session, otherwise redirect back to register
if (empty($_SESSION['fullname'])) {
    header("Location: register.php");
    exit;
}

// Store and sanitize the fullname
$fullname = filter_var($_SESSION['fullname'], FILTER_SANITIZE_STRING);

// Clear sensitive session data after use
unset($_SESSION['fullname']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Registration Successful - Letran System</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <style>
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #45a049;
            --text-dark: #333;
            --text-light: #666;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #f0f2f5, #e0e4e8);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .success-container {
            padding: 20px;
        }

        .success-message {
            background: #fff;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: var(--shadow);
            text-align: center;
            max-width: 450px;
            animation: fadeIn 0.5s ease-in-out;
        }

        .checkmark {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
        }

        .checkmark-circle {
            fill: none;
            stroke: var(--primary-color);
            stroke-width: 3;
            stroke-miterlimit: 10;
            animation: fillCircle 0.5s ease-in-out 0.3s forwards;
        }

        .checkmark-check {
            fill: none;
            stroke: var(--primary-color);
            stroke-width: 5;
            stroke-linecap: round;
            stroke-linejoin: round;
            animation: strokeCheck 0.4s ease-in-out 0.7s forwards;
        }

        h2 {
            color: var(--text-dark);
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        p {
            color: var(--text-light);
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .login-link {
            display: inline-block;
            padding: 12px 25px;
            background: var(--primary-color);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s, transform 0.2s;
        }

        .login-link:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        .login-link:active {
            transform: translateY(0);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes strokeCheck {
            from { stroke-dasharray: 0 100; }
            to { stroke-dasharray: 100 0; }
        }

        @keyframes fillCircle {
            from { stroke-dasharray: 0 158; }
            to { stroke-dasharray: 158 0; }
        }

        @media (max-width: 480px) {
            .success-message {
                margin: 20px;
                padding: 1.5rem;
            }
            .checkmark {
                width: 80px;
                height: 80px;
            }
            h2 {
                font-size: 1.5rem;
            }
            p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-message">
            <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                <circle class="checkmark-circle" cx="26" cy="26" r="25"/>
                <path class="checkmark-check" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
            </svg>
            <h2>Registration Successful!</h2>
            <p>Welcome aboard, <?php echo htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8'); ?>! Your account has been created successfully.</p>
            <a href="login.php" class="login-link">Proceed to Login</a>
        </div>
    </div>

    <script>
        // Optional: Redirect to login after 5 seconds
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 5000);
    </script>
</body>
</html>