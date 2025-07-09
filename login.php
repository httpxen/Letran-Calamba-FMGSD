<?php
include 'db/db.php';
session_start();

// Session timeout duration (e.g., 30 minutes)
$timeout_duration = 1800;

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        $update_sql = "UPDATE users SET is_online = 0, status = 'offline' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $_SESSION['user_id']);
        $update_stmt->execute();
        $update_stmt->close();

        session_unset();
        session_destroy();
        header("Location: login.php?message=session_expired");
        exit();
    }
    $_SESSION['last_activity'] = time();

    // Check role and redirect accordingly
    if ($_SESSION['role'] == "SuperAdmin") {
        header("Location: superadmin/dashboard.php");
    } elseif ($_SESSION['role'] == "Admin") {
        header("Location: admin/dashboard.php");
    } else {
        $sql = "SELECT approval_status FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user['approval_status'] === 'Approved') {
            header("Location: user/dashboard.php");
        } else {
            session_unset();
            session_destroy();
            header("Location: login.php?message=" . urlencode($user['approval_status'] === 'Pending' ? 'pending_approval' : 'account_declined'));
            exit();
        }
    }
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_input = filter_var($_POST['login_input'], FILTER_SANITIZE_STRING);
    $password = $_POST['password'];

    if (empty($login_input) || empty($password)) {
        $error = "Username or Email and password are required.";
    } else {
        // Check if input is a valid email format
        $is_email = filter_var($login_input, FILTER_VALIDATE_EMAIL);

        // Prepare query to check either username (for SuperAdmin) or email (for Admin/User)
        $sql = "SELECT id, fullname, password, role, approval_status, account_status, username 
                FROM users 
                WHERE " . ($is_email ? "email = ?" : "username = ?");
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $login_input);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            // Check account status
            if ($user['account_status'] === 'Inactive') {
                $error = "Your account is inactive. Please contact support.";
            } elseif ($user['role'] === 'User' && $user['approval_status'] !== 'Approved') {
                // Skip approval check for SuperAdmin and Admin
                $error = $user['approval_status'] === 'Pending' 
                    ? "Your account is pending approval. Please wait for admin approval."
                    : "Your account has been declined. Please contact support.";
            } else {
                // Capture user agent
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $user_agent = substr($user_agent, 0, 255);

                // Update user data
                $update_sql = "UPDATE users SET last_login = NOW(), is_online = 1, status = 'online', current_device = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $user_agent, $user['id']);
                $update_stmt->execute();
                $update_stmt->close();

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_picture'] = $user['profile_picture'] ?? '../assets/images/profile-placeholder.png';
                $_SESSION['last_activity'] = time();

                // Redirect based on role
                if ($user['role'] == "SuperAdmin") {
                    header("Location: superadmin/dashboard.php");
                } elseif ($user['role'] == "Admin") {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: user/dashboard.php");
                }
                exit();
            }
        } else {
            $error = "Invalid login credentials.";
        }
        $stmt->close();
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

        .login-container {
            transform: perspective(1000px) rotateY(90deg);
            opacity: 0;
            animation: flipIn 0.8s ease-out forwards;
        }

        @keyframes flipIn {
            to {
                transform: perspective(1000px) rotateY(0deg);
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

        @keyframes slideUp {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-input {
            transform: translateY(20px);
            opacity: 0;
            animation: floatUp 0.6s ease-out forwards;
            animation-delay: calc(0.2s * var(--i));
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

        @keyframes floatUp {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .submit-button {
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

        .error-message {
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

        .toggle-password {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            width: 1.25rem;
            height: 1.25rem;
            cursor: pointer;
            transition: opacity 0.2s ease, transform 0.3s ease;
        }

        .toggle-password:hover {
            opacity: 0.8;
            transform: translateY(-50%) scale(1.1);
        }

        .toggle-password:active {
            transform: translateY(-50%) scale(0.9);
        }

        .toggle-password.show {
            transform: translateY(-50%) rotate(180deg);
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

        @media (max-width: 768px) {
            .login-container {
                margin: 1rem;
            }
        }
    </style>
    <title>EHS | Log In</title>
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center">
    <div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl flex flex-col md:flex-row overflow-hidden login-container">
        <!-- Left Side: School Image -->
        <div class="hidden md:block md:w-1/2 bg-cover bg-center" style="background-image: url('https://the-post-assets.sgp1.digitaloceanspaces.com/2020/08/LETRAN-15.jpg')"></div>
        
        <!-- Right Side: Login Form -->
        <div class="w-full md:w-1/2 p-8 flex flex-col justify-center">
            <div class="flex justify-center mb-6">
                <img src="assets/images/icon.png" alt="School Logo" class="h-16 w-16 object-contain logo">
            </div>
            <h1 class="text-4xl font-extrabold text-center text-gray-800 mb-2 title-text">Letran Calamba</h1>
            <p class="text-lg text-center text-gray-600 mb-6 subtitle-text">Portal Login</p>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6 text-center error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['message'])): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6 text-center error-message">
                    <?php
                    if ($_GET['message'] == 'session_expired') {
                        echo "Your session has expired. Please log in again.";
                    } elseif ($_GET['message'] == 'pending_approval') {
                        echo "Your account is pending approval. Please wait for admin approval.";
                    } elseif ($_GET['message'] == 'account_declined') {
                        echo "Your account has been declined. Please contact support.";
                    }
                    ?>
                </div>
            <?php endif; ?>

            <form class="space-y-6" method="POST" id="loginForm">
                <div class="form-group" style="--i: 1">
                    <input type="text" name="login_input" id="login_input" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none form-input" placeholder=" " required>
                    <label for="login_input" class="form-label">Username or Email</label>
                </div>
                <div class="form-group relative" style="--i: 2">
                    <input type="password" name="password" id="password" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none form-input" placeholder=" " required>
                    <label for="password" class="form-label">Password</label>
                    <img src="assets/icons/eye-off.png" alt="Show/Hide Password" class="toggle-password" id="togglePassword">
                </div>
                <div class="text-right">
                    <a href="forgot_password.php" class="text-sm text-blue-600 link-animate">Forgot Password?</a>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white p-3 rounded-lg font-semibold submit-button" id="submitButton">
                    <span id="buttonText">Log In</span>
                    <div class="loading-spinner" id="spinner"></div>
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-600">
                Don't have an account? <a href="register.php" class="text-blue-600 link-animate">Register here</a>
            </p>
        </div>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', () => {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            togglePassword.src = isPassword ? 'assets/icons/eye-on.png' : 'assets/icons/eye-off.png';
            togglePassword.classList.toggle('show', isPassword);
        });

        const form = document.getElementById('loginForm');
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
            }, 2000);
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>