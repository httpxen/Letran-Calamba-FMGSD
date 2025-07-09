<?php
session_start();
require_once '../db/db.php';

// Set Philippine time zone
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "SuperAdmin") {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$query = "SELECT fullname, profile_picture, last_active FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$fullname = htmlspecialchars($user['fullname']);
$profile_picture = htmlspecialchars($user['profile_picture'] ?: '../assets/images/profile-placeholder.png');
$is_online = (strtotime($user['last_active']) > time() - 300) ? true : false;

$update_query = "UPDATE users SET last_active = NOW() WHERE id = ?";
$update_stmt = $conn->prepare($update_query);
$update_stmt->bind_param("i", $user_id);
$update_stmt->execute();
$update_stmt->close();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

// Set registration window directly
$registration_window = 5; // Default to 5 days

$message = $error = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'], $_POST['action'], $_POST['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $user_id_to_update = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
        $action = $_POST['action'];
        if ($user_id_to_update === false || !in_array($action, ['approve', 'decline'])) {
            $error = "Invalid user ID or action.";
        } else {
            $status = ($action === 'approve') ? 'Approved' : 'Declined';

            $user_query = "SELECT fullname, email, registered_at FROM users WHERE id = ?";
            $user_stmt = $conn->prepare($user_query);
            $user_stmt->bind_param("i", $user_id_to_update);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $target_user = $user_result->fetch_assoc();
            $user_stmt->close();

            if ($target_user) {
                $is_late = (strtotime($target_user['registered_at']) < strtotime("-$registration_window days"));
                $update_status_query = "UPDATE users SET approval_status = ? WHERE id = ?";
                $status_stmt = $conn->prepare($update_status_query);
                $status_stmt->bind_param("si", $status, $user_id_to_update);

                if ($status_stmt->execute()) {
                    $message = "User " . ($action === 'approve' ? 'approved' : 'declined') . " successfully.";

                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'opulenciaandrei23@gmail.com';
                        $mail->Password = 'pkou mbww kqgc hgrh';
                        $mail->SMTPSecure = 'tls';
                        $mail->Port = 587;

                        $mail->setFrom('opulenciaandrei23@gmail.com', 'EHS Registration');
                        $mail->addAddress($target_user['email'], $target_user['fullname']);
                        $mail->isHTML(true);
                        $mail->Subject = 'EHS Account Status Update';
                        $mail->Body = "
                            <h2>Hello, {$target_user['fullname']}!</h2>
                            <p>Your EHS account has been " . ($action === 'approve' ? 'approved' : 'declined') . ".</p>
                            " . ($action === 'approve' ? 
                                "<p>You can now log in to your account at <a href='http://localhost:8000/login.php'>our login page</a> using your registered email and password.</p>" : 
                                "<p>" . ($is_late ? "Your registration was declined because it was submitted after the $registration_window-day window." : "Your account was declined by an administrator.") . " Please contact support at <a href='mailto:opulenciaandrei23@gmail.com'>opulenciaandrei23@gmail.com</a> for more information.</p>") . "
                            <p><strong>Your Email:</strong> {$target_user['email']}</p>
                            <br><p>- EHS Team</p>
                            <hr>
                            <p style='font-size:12px;color:#888;'>This is an automated email. Please do not reply directly to this message.</p>
                        ";

                        $mail->send();
                    } catch (Exception $e) {
                        error_log("Mailer Error: " . $mail->ErrorInfo);
                        $error = "User status updated, but failed to send email notification.";
                    }
                } else {
                    $error = "Error updating user status: " . $status_stmt->error;
                }
                $status_stmt->close();
            } else {
                $error = "User not found.";
            }
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch counts for summary
$count_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE approval_status = 'Pending' AND role = 'User') as pending_count,
    (SELECT COUNT(*) FROM users WHERE approval_status = 'Approved' AND role = 'User') as approved_count,
    (SELECT COUNT(*) FROM users WHERE approval_status = 'Declined' AND role = 'User') as declined_count";
$count_result = $conn->query($count_query);
$counts = $count_result->fetch_assoc();

// Fetch users based on tab
$status = isset($_GET['status']) && in_array($_GET['status'], ['Pending', 'Approved', 'Declined']) ? $_GET['status'] : 'Pending';
$pending_query = "SELECT id, fullname, email, registered_at FROM users WHERE approval_status = ? AND role = 'User' ORDER BY registered_at DESC";
$pending_stmt = $conn->prepare($pending_query);
$pending_stmt->bind_param("s", $status);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
if (!$pending_result) {
    $error = "Error fetching users: " . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User Approvals</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.ico" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ["Inter", "sans-serif"],
            },
            colors: {
              primary: {
                50: "#f5f3ff",
                600: "#4f46e5",
                700: "#4338ca",
              },
              dashboard: "#12234e",
            },
            boxShadow: {
              'soft': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.05)',
            },
            animation: {
              'fade-in': 'fadeIn 0.3s ease-out',
            },
            keyframes: {
              fadeIn: {
                '0%': { opacity: '0', transform: 'translateY(10px)' },
                '100%': { opacity: '1', transform: 'translateY(0)' },
              },
            },
          },
        },
      };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased">
    <div class="flex h-screen overflow-hidden">
        <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-white border-r shadow-soft transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 md:static md:shadow-none flex flex-col">
            <div class="flex items-center space-x-3 p-6 border-b border-gray-100">
                <img src="../assets/images/favicon.ico" alt="Logo" class="w-10 h-10 rounded-full" />
                <h2 class="text-xl font-bold text-dashboard"><span class="text-red-600">SuperAdmin</span> Dashboard</h2>
            </div>
            <nav class="mt-6 flex-1">
                <ul class="space-y-1 px-4">
                    <li>
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="user_approvals.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-primary-50 text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            User Approvals
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            Users
                        </a>
                    </li>
                    <li>
                        <a href="user_records.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                            </svg>
                            User Records
                        </a>
                    </li>
                    <li>
                        <a href="module_list.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                            </svg>
                            Modules
                        </a>
                    </li>
                    <li>
                    <a href="survey_management.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02] <?php echo $current_page === 'survey_management.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.125 1.125 0 0 1 0 2.25H5.625a1.125 1.125 0 0 1 0-2.25Z" />
                        </svg>
                        Survey Management
                    </a>
                    </li>   
                    <li>
                        <a href="account_management.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-primary-600">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                            </svg>
                            Account Management
                        </a>
                    </li>
                    <li>
                        <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-red-50 hover:text-red-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0l3-3m0 0-3-3m3 3H9" />
                            </svg>
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <div class="flex-1 flex flex-col">
            <header class="bg-white shadow-soft flex justify-between items-center px-6 py-4">
                <div class="flex items-center space-x-4">
                    <button id="sidebar-toggle" class="md:hidden text-gray-600 hover:text-primary-600 focus:outline-none transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <h1 class="text-2xl font-semibold text-dashboard">User Approvals</h1>
                    <p class="text-gray-600">Review and approve or decline user registrations</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative flex items-center space-x-2 cursor-pointer" id="profile">
                        <span class="text-gray-600 font-medium hidden sm:block"><?php echo $fullname; ?></span>
                        <div class="relative">
                            <img src="<?php echo $profile_picture; ?>" class="w-10 h-10 rounded-full border border-gray-200 shadow-sm" alt="Profile Picture" />
                            <span id="status-dot" class="absolute bottom-0 right-0 w-3 h-3 rounded-full border border-white <?php echo $is_online ? 'bg-green-500' : 'bg-red-500'; ?>"></span>
                        </div>
                        <div id="profile-dropdown" class="absolute right-0 top-full mt-2 w-64 bg-white border border-gray-200 rounded-lg shadow-lg hidden z-50">
                            <div class="flex items-center p-4 border-b">
                                <img src="<?php echo $profile_picture; ?>" class="w-10 h-10 rounded-full" alt="Profile Picture" />
                                <div class="ml-3">
                                    <p class="text-sm font-semibold text-gray-800"><?php echo $fullname; ?></p>
                                    <p class="text-xs text-gray-500">SuperAdmin</p>
                                </div>
                            </div>
                            <div class="p-2">
                                <a href="accountsettings.php" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-600 rounded-lg transition-all duration-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-7.5 0h7.5m-12-6h3.75m-3.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-9.75 0h9.75" />
                                    </svg>
                                    Account Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-6 overflow-y-auto">
                <div class="max-w-6xl mx-auto space-y-6">
                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-white rounded-lg shadow-soft p-6 flex items-center justify-between animate-fade-in">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Pending Users</h3>
                                <p class="text-2xl font-bold text-primary-600"><?php echo $counts['pending_count']; ?></p>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="bg-white rounded-lg shadow-soft p-6 flex items-center justify-between animate-fade-in">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Approved Users</h3>
                                <p class="text-2xl font-bold text-green-600"><?php echo $counts['approved_count']; ?></p>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="bg-white rounded-lg shadow-soft p-6 flex items-center justify-between animate-fade-in">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Declined Users</h3>
                                <p class="text-2xl font-bold text-red-600"><?php echo $counts['declined_count']; ?></p>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="bg-white rounded-lg shadow-soft p-6 animate-fade-in">
                        <div class="flex border-b mb-6">
                            <a href="?status=Pending" class="px-4 py-2 text-sm font-medium <?php echo $status === 'Pending' ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500 hover:text-primary-600'; ?>">Pending</a>
                            <a href="?status=Approved" class="px-4 py-2 text-sm font-medium <?php echo $status === 'Approved' ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500 hover:text-primary-600'; ?>">Approved</a>
                            <a href="?status=Declined" class="px-4 py-2 text-sm font-medium <?php echo $status === 'Declined' ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500 hover:text-primary-600'; ?>">Declined</a>
                        </div>

                        <?php if (isset($message)): ?>
                            <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-6 animate-fade-in"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        <?php if (isset($error)): ?>
                            <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6 animate-fade-in"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <?php if ($pending_result && $pending_result->num_rows > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white border border-gray-200">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 cursor-pointer hover:text-primary-600" onclick="sortTable(0)">Full Name</th>
                                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 cursor-pointer hover:text-primary-600" onclick="sortTable(1)">Email</th>
                                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 cursor-pointer hover:text-primary-600" onclick="sortTable(2)">Registered At</th>
                                            <?php if ($status === 'Pending' || $status === 'Declined'): ?>
                                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody id="user-table">
                                        <?php while ($row = $pending_result->fetch_assoc()): ?>
                                            <?php
                                            $is_late = ($status === 'Pending' && strtotime($row['registered_at']) < strtotime("-$registration_window days"));
                                            ?>
                                            <tr class="border-t hover:bg-gray-50 transition-all duration-200 <?php echo $is_late ? 'bg-red-50' : ''; ?>">
                                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['fullname']); ?></td>
                                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td class="px-6 py-4 text-sm text-gray-900">
                                                    <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($row['registered_at']))); ?>
                                                    <?php if ($is_late): ?>
                                                        <span class="text-red-600 font-semibold">(Late)</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($status === 'Pending' || $status === 'Declined'): ?>
                                                    <td class="px-6 py-4 text-sm">
                                                        <form method="POST" class="flex space-x-3">
                                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                            <?php if ($status === 'Pending'): ?>
                                                                <button type="submit" name="action" value="approve" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200 <?php echo $is_late ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $is_late ? 'disabled' : ''; ?> aria-label="Approve user">
                                                                    Approve
                                                                </button>
                                                                <button type="submit" name="action" value="decline" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition-all duration-200" aria-label="Decline user">
                                                                    Decline
                                                                </button>
                                                            <?php elseif ($status === 'Declined'): ?>
                                                                <button type="submit" name="action" value="approve" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200" aria-label="Re-approve user">
                                                                    Re-Approve
                                                                </button>
                                                            <?php endif; ?>
                                                        </form>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Pagination Placeholder -->
                            <div class="mt-4 flex justify-between items-center">
                                <p class="text-sm text-gray-600">Showing 1 to <?php echo $pending_result->num_rows; ?> of <?php echo $pending_result->num_rows; ?> entries</p>
                                <div class="flex space-x-2">
                                    <button class="px-3 py-1 bg-gray-200 text-gray-600 rounded-md hover:bg-gray-300" disabled>Previous</button>
                                    <button class="px-3 py-1 bg-gray-200 text-gray-600 rounded-md hover:bg-gray-300" disabled>Next</button>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-600 text-center">No <?php echo strtolower($status); ?> users found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById("sidebar");
        const sidebarToggle = document.getElementById("sidebar-toggle");
        sidebarToggle.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
        });

        document.addEventListener("click", (e) => {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target) && !sidebar.classList.contains("-translate-x-full")) {
                sidebar.classList.add("-translate-x-full");
            }
        });

        const profile = document.getElementById("profile");
        const profileDropdown = document.getElementById("profile-dropdown");
        profile.addEventListener("click", (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle("hidden");
        });

        document.addEventListener("click", (e) => {
            if (!profile.contains(e.target)) {
                profileDropdown.classList.add("hidden");
            }
        });

        // Client-side search
        const searchInput = document.getElementById("search");
        searchInput.addEventListener("input", () => {
            const filter = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll("#user-table tr");
            rows.forEach(row => {
                const name = row.cells[0].textContent.toLowerCase();
                const email = row.cells[1].textContent.toLowerCase();
                if (name.includes(filter) || email.includes(filter)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        });

        // Table sorting
        function sortTable(n) {
            const table = document.getElementById("user-table");
            let rows, switching = true, i, shouldSwitch, dir = "asc", switchcount = 0;
            while (switching) {
                switching = false;
                rows = table.rows;
                for (i = 0; i < (rows.length - 1); i++) {
                    shouldSwitch = false;
                    const x = rows[i].cells[n].textContent.toLowerCase();
                    const y = rows[i + 1].cells[n].textContent.toLowerCase();
                    if (dir === "asc") {
                        if (x > y) {
                            shouldSwitch = true;
                            break;
                        }
                    } else if (dir === "desc") {
                        if (x < y) {
                            shouldSwitch = true;
                            break;
                        }
                    }
                }
                if (shouldSwitch) {
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                    switchcount++;
                } else if (switchcount === 0 && dir === "asc") {
                    dir = "desc";
                    switching = true;
                }
            }
        }
    </script>
</body>
</html>
<?php
if ($pending_result) {
    $pending_result->free();
}
$conn->close();
?>