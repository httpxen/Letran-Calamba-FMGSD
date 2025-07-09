<?php
ob_start(); // Start output buffering to prevent header issues
session_start();
require_once '../db/db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the user is logged in and has the SuperAdmin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "SuperAdmin") {
    $_SESSION['error_message'] = "Unauthorized access.";
    header("Location: ../login.php");
    exit();
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get current user info
$user_id = $_SESSION['user_id'];
$query = "SELECT fullname, profile_picture, last_active FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    $_SESSION['error_message'] = "Database error: " . $conn->error;
    header("Location: account_management.php");
    exit();
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    $_SESSION['error_message'] = "User not found.";
    header("Location: ../login.php");
    exit();
}

$fullname = htmlspecialchars($user['fullname']);
$profile_picture = htmlspecialchars($user['profile_picture'] ?: '../assets/images/profile-placeholder.png');
$is_online = (strtotime($user['last_active']) > time() - 300) ? true : false;

// Update last active
$update_query = "UPDATE users SET last_active = NOW() WHERE id = ?";
$update_stmt = $conn->prepare($update_query);
$update_stmt->bind_param("i", $user_id);
$update_stmt->execute();
$update_stmt->close();

// Get user to edit
$edit_user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($edit_user_id === 0) {
    $_SESSION['error_message'] = "Invalid user ID.";
    header("Location: account_management.php");
    exit();
}

$edit_query = "SELECT id, fullname, email, role, approval_status, account_status, profile_picture FROM users WHERE id = ?";
$edit_stmt = $conn->prepare($edit_query);
if (!$edit_stmt) {
    $_SESSION['error_message'] = "Database error: " . $conn->error;
    header("Location: account_management.php");
    exit();
}
$edit_stmt->bind_param("i", $edit_user_id);
$edit_stmt->execute();
$edit_result = $edit_stmt->get_result();
$edit_user = $edit_result->fetch_assoc();

if (!$edit_user) {
    $_SESSION['error_message'] = "User not found.";
    header("Location: account_management.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received: " . print_r($_POST, true)); // Debug POST data

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token.";
        error_log("CSRF validation failed. Expected: " . $_SESSION['csrf_token'] . ", Got: " . ($_POST['csrf_token'] ?? 'None'));
    } else {
        $new_fullname = trim($_POST['fullname']);
        $new_email = trim($_POST['email']);
        $new_role = $_POST['role'];
        $new_approval_status = $_POST['approval_status'];
        $new_account_status = $_POST['account_status'];

        // Validate input
        if (empty($new_fullname) || empty($new_email)) {
            $_SESSION['error_message'] = "Fullname and email are required.";
            error_log("Validation failed: Empty fullname or email");
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error_message'] = "Invalid email format.";
            error_log("Validation failed: Invalid email format - $new_email");
        } elseif (!in_array($new_role, ['SuperAdmin', 'Admin', 'User'])) {
            $_SESSION['error_message'] = "Invalid role selected.";
            error_log("Validation failed: Invalid role - $new_role");
        } elseif (!in_array($new_approval_status, ['Approved', 'Pending', 'Rejected'])) {
            $_SESSION['error_message'] = "Invalid approval status selected.";
            error_log("Validation failed: Invalid approval status - $new_approval_status");
        } elseif (!in_array($new_account_status, ['Active', 'Inactive'])) {
            $_SESSION['error_message'] = "Invalid account status selected.";
            error_log("Validation failed: Invalid account status - $new_account_status");
        } else {
            // Check if email is already taken by another user
            $email_check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
            $email_check_stmt = $conn->prepare($email_check_query);
            if (!$email_check_stmt) {
                $_SESSION['error_message'] = "Email check query preparation failed: " . $conn->error;
                error_log("Email check query failed: " . $conn->error);
            } else {
                $email_check_stmt->bind_param("si", $new_email, $edit_user_id);
                $email_check_stmt->execute();
                if ($email_check_stmt->get_result()->num_rows > 0) {
                    $_SESSION['error_message'] = "Email is already in use.";
                    error_log("Validation failed: Email already in use - $new_email");
                } else {
                    // Update user
                    $update_user_query = "UPDATE users SET fullname = ?, email = ?, role = ?, approval_status = ?, account_status = ? WHERE id = ?";
                    $update_user_stmt = $conn->prepare($update_user_query);
                    if (!$update_user_stmt) {
                        $_SESSION['error_message'] = "Update query preparation failed: " . $conn->error;
                        error_log("Update query failed: " . $conn->error);
                    } else {
                        $update_user_stmt->bind_param("sssssi", $new_fullname, $new_email, $new_role, $new_approval_status, $new_account_status, $edit_user_id);
                        if ($update_user_stmt->execute()) {
                            $_SESSION['success_message'] = "User updated successfully!";
                            error_log("User updated successfully: ID $edit_user_id");
                            header("Location: account_management.php");
                            exit();
                        } else {
                            $_SESSION['error_message'] = "Failed to update user: " . $update_user_stmt->error;
                            error_log("Update failed: " . $update_user_stmt->error);
                        }
                        $update_user_stmt->close();
                    }
                }
                $email_check_stmt->close();
            }
        }
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenerate CSRF token
    }
    // Redirect to the same page to show errors
    header("Location: edit_account.php?id=$edit_user_id");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit Account</title>
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
              'elevated': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
            },
            animation: {
              'fade-in': 'fadeIn 0.3s ease-out',
              'spin-slow': 'spin 1s linear infinite',
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
        <!-- Sidebar -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-white border-r shadow-soft transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 md:static md:shadow-none flex flex-col">
            <div class="flex items-center space-x-3 p-6 border-b border-gray-100">
                <img src="../assets/images/favicon.ico" alt="Logo" class="w-10 h-10 rounded-full" />
                <h2 class="text-xl font-bold text-dashboard"><span class="text-red-600">SuperAdmin</span> Dashboard</h2>
            </div>
            <nav class="mt-6 flex-1">
                <ul class="space-y-1 px-4">
                <li>
                    <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out <?php echo $current_page === 'dashboard.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="user_approvals.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out <?php echo $current_page === 'user_approvals.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0Z" />
                        </svg>
                        User Approvals
                    </a>
                </li>
                <li>
                    <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out <?php echo $current_page === 'users.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0Zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0Z" />
                        </svg>
                        Users
                    </a>
                </li>
                <li>
                    <a href="user_records.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out <?php echo $current_page === 'user_records.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                        </svg>
                        User Records
                    </a>
                </li>
                <li>
                    <a href="module_list.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out <?php echo $current_page === 'module_list.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
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
                    <a href="account_management.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out <?php echo $current_page === 'account_management.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0012 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 116 0Zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0Zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0Z" />
                        </svg>
                        Account Management
                    </a>
                </li>
                <li>
                    <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-red-50 hover:text-red-600 font-medium transition-all duration-200 ease-in-out <?php echo $current_page === 'logout.php' ? 'bg-red-50 text-red-600' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                        Logout
                    </a>
                </li>
                </ul>
            </nav>
            <div class="p-4 border-t border-gray-100">
                <p class="text-xs text-gray-500">Version 1.0.0</p>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col">
            <!-- Topbar -->
            <header class="bg-white shadow-soft flex justify-between items-center px-6 py-4">
                <div class="flex items-center space-x-4">
                    <button id="sidebar-toggle" class="md:hidden text-gray-600 hover:text-primary-600 focus:outline-none transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <div>
                        <h1 class="text-2xl font-semibold text-dashboard">Edit Account</h1>
                        <p class="text-sm text-gray-600">Update user account details</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative flex items-center space-x-2 cursor-pointer" id="profile">
                        <span class="text-gray-600 font-medium hidden sm:block"><?php echo $fullname; ?></span>
                        <div class="relative">
                            <img src="<?php echo $profile_picture; ?>" class="w-10 h-10 rounded-full border border-gray-200 shadow-sm" alt="Profile Picture" />
                            <span id="status-dot" class="absolute bottom-0 right-0 w-4 h-4 rounded-full border-2 border-white <?php echo $is_online ? 'bg-green-500' : 'bg-red-500'; ?>"></span>
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

            <!-- Main content area -->
            <main class="flex-1 p-6 overflow-y-auto">
                <nav class="mb-6">
                    <ol class="flex items-center space-x-2 text-sm text-gray-600">
                        <li><a href="dashboard.php" class="hover:text-primary-600 transition-all duration-200">Dashboard</a></li>
                        <li><span class="text-gray-400">/</span></li>
                        <li><a href="account_management.php" class="hover:text-primary-600 transition-all duration-200">Account Management</a></li>
                        <li><span class="text-gray-400">/</span></li>
                        <li class="text-gray-900 font-medium">Edit Account</li>
                    </ol>
                </nav>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="p-4 bg-green-50 text-green-700 rounded-lg border border-green-200 animate-fade-in mb-6">
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <?php unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="p-4 bg-red-50 text-red-700 rounded-lg border border-red-200 animate-fade-in mb-6">
                        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <?php unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>

                <div class="max-w-full mx-auto space-y-6">
                    <div class="bg-white rounded-xl shadow-elevated p-6 animate-fade-in">
                        <h2 class="text-xl font-semibold text-dashboard mb-6">Edit User Account</h2>

                        <form method="POST" action="edit_account.php?id=<?php echo $edit_user_id; ?>" class="space-y-6">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="grid gap-6 sm:grid-cols-2">
                                <div>
                                    <label for="fullname" class="block text-sm font-medium text-gray-700">Full Name</label>
                                    <input type="text" name="fullname" id="fullname" value="<?php echo htmlspecialchars($edit_user['fullname']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 sm:text-sm" required>
                                </div>
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 sm:text-sm" required>
                                </div>
                                <div>
                                    <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                                    <select name="role" id="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 sm:text-sm">
                                        <!-- <option value="SuperAdmin" <?php echo $edit_user['role'] === 'SuperAdmin' ? 'selected' : ''; ?>>SuperAdmin</option> -->
                                        <option value="Admin" <?php echo $edit_user['role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="User" <?php echo $edit_user['role'] === 'User' ? 'selected' : ''; ?>>User</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="approval_status" class="block text-sm font-medium text-gray-700">Approval Status</label>
                                    <select name="approval_status" id="approval_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 sm:text-sm">
                                        <option value="Approved" <?php echo $edit_user['approval_status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="Pending" <?php echo $edit_user['approval_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Rejected" <?php echo $edit_user['approval_status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="account_status" class="block text-sm font-medium text-gray-700">Account Status</label>
                                    <select name="account_status" id="account_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 sm:text-sm">
                                        <option value="Active" <?php echo $edit_user['account_status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="Inactive" <?php echo $edit_user['account_status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end space-x-3">
                                <a href="account_management.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-all duration-200">Cancel</a>
                                <button type="submit" id="save-button" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition-all duration-200">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById("sidebar");
        const sidebarToggle = document.getElementById("sidebar-toggle");
        const profile = document.getElementById("profile");
        const profileDropdown = document.getElementById("profile-dropdown");

        sidebarToggle.addEventListener("click", () => sidebar.classList.toggle("-translate-x-full"));
        document.addEventListener("click", (e) => {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target) && !sidebar.classList.contains("-translate-x-full")) {
                sidebar.classList.add("-translate-x-full");
            }
            if (!profile.contains(e.target)) profileDropdown.classList.add("hidden");
        });

        profile.addEventListener("click", (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle("hidden");
        });

        // Add loading state for form submission
        const form = document.querySelector("form");
        form.addEventListener("submit", (e) => {
            const button = document.getElementById("save-button");
            if (button) {
                button.disabled = true;
                button.innerHTML = `<span class="flex items-center"><svg class="animate-spin-slow w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.373A8 8 0 0012 20v-4c-2.373 0-4-1.627-4-3.627h-2z"></path></svg>Processing...</span>`;
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
ob_end_flush(); // End output buffering
?>