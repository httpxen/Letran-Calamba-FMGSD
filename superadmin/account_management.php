<?php
session_start();
require_once '../db/db.php';

// Check if the user is logged in and has the SuperAdmin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "SuperAdmin") {
    header("Location: ../login.php");
    exit();
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get user info from the database
$user_id = $_SESSION['user_id'];
$query = "SELECT fullname, profile_picture, last_active FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$fullname = htmlspecialchars($user['fullname']);
$profile_picture = htmlspecialchars($user['profile_picture'] ?: '../assets/images/profile-placeholder.png');
$is_online = (strtotime($user['last_active']) > time() - 300) ? true : false;

// Update last_active timestamp
$update_query = "UPDATE users SET last_active = NOW() WHERE id = ?";
$update_stmt = $conn->prepare($update_query);
$update_stmt->bind_param("i", $user_id);
$update_stmt->execute();
$update_stmt->close();

// Pagination setup
$users_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $users_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [];
if ($search) {
    $search_condition = "WHERE fullname LIKE ? OR email LIKE ?";
    $search_term = "%$search%";
    $search_params = [$search_term, $search_term];
}

$total_users_query = "SELECT COUNT(*) as total FROM users $search_condition";
$total_stmt = $conn->prepare($total_users_query);
if ($search) {
    $total_stmt->bind_param("ss", ...$search_params);
}
$total_stmt->execute();
$total_users = $total_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_users / $users_per_page);

$users_query = "SELECT id, fullname, email, role, approval_status, account_status, profile_picture FROM users $search_condition ORDER BY role DESC, fullname ASC LIMIT ? OFFSET ?";
$users_stmt = $conn->prepare($users_query);
if ($search) {
    $users_stmt->bind_param("ssii", ...array_merge($search_params, [$users_per_page, $offset]));
} else {
    $users_stmt->bind_param("ii", $users_per_page, $offset);
}
$users_stmt->execute();
$users_result = $users_stmt->get_result();

// Handle activate/deactivate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'delete') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token.";
    } else {
        $target_user_id = (int)$_POST['user_id'];
        $action = $_POST['action'];
        $new_status = $action === 'activate' ? 'Active' : 'Inactive';

        if ($target_user_id === $user_id) {
            $_SESSION['error_message'] = "You cannot modify your own account status.";
        } else {
            $update_status_query = "UPDATE users SET account_status = ? WHERE id = ?";
            $update_status_stmt = $conn->prepare($update_status_query);
            if ($update_status_stmt === false) {
                error_log("Prepare failed: " . $conn->error);
                $_SESSION['error_message'] = "Database error occurred. Please try again.";
            } else {
                $update_status_stmt->bind_param("si", $new_status, $target_user_id);
                if ($update_status_stmt->execute()) {
                    $_SESSION['success_message'] = "User " . ($action === 'activate' ? 'activated' : 'deactivated') . " successfully!";
                } else {
                    error_log("Update failed: " . $update_status_stmt->error);
                    $_SESSION['error_message'] = "Failed to update user status: " . $update_status_stmt->error;
                }
                $update_status_stmt->close();
            }
        }
        if (isset($_SESSION['success_message'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
    header("Location: account_management.php?page=$page" . ($search ? "&search=" . urlencode($search) : ""));
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Account Management</title>
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
          },
        },
      };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <style>
        @media (max-width: 640px) {
            .min-w-full {
                width: 100%;
                overflow-x: auto;
            }
            .min-w-full th, .min-w-full td {
                min-width: 120px;
            }
            .w-12.h-12 {
                width: 2.5rem;
                height: 2.5rem;
            }
        }
    </style>
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
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02] <?php echo $current_page === 'dashboard.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="user_approvals.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02] <?php echo $current_page === 'user_approvals.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            User Approvals
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02] <?php echo $current_page === 'users.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            Users
                        </a>
                    </li>
                    <li>
                        <a href="user_records.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02] <?php echo $current_page === 'user_records.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                            </svg>
                            User Records
                        </a>
                    </li>
                    <li>
                        <a href="module_list.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02] <?php echo $current_page === 'module_list.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
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
                        <a href="account_management.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02] <?php echo $current_page === 'account_management.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-primary-600">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                            </svg>
                            Account Management
                        </a>
                    </li>
                    <li>
                        <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-red-50 hover:text-red-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02] <?php echo $current_page === 'logout.php' ? 'bg-red-50 text-red-600' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                            </svg>
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col">
            <!-- Topbar -->
            <header class="bg-white shadow-sm flex justify-between items-center px-6 py-4">
                <div class="flex items-center space-x-4">
                    <button id="sidebar-toggle" class="md:hidden text-gray-600 hover:text-primary-600 focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <div>
                        <h1 class="text-xl font-semibold text-dashboard">Account Management</h1>
                        <p class="text-gray-600">Manage user and admin accounts efficiently</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <input
                            type="text"
                            id="search"
                            placeholder="Search accounts..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="pl-10 pr-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-600"
                        />
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1116.65 16.65z" />
                        </svg>
                    </div>
                    <div class="relative flex items-center space-x-2 cursor-pointer" id="profile">
                        <span class="text-gray-600 font-medium"><?php echo $fullname; ?></span>
                        <div class="relative">
                            <img
                                src="<?php echo $profile_picture; ?>"
                                class="w-10 h-10 rounded-full border border-gray-200 shadow-sm object-cover"
                                alt="Profile Picture"
                                onerror="this.src='../assets/images/profile-placeholder.png';"
                            />
                            <span
                                id="status-dot"
                                class="absolute bottom-0 right-0 w-3 h-3 rounded-full border border-white <?php echo $is_online ? 'bg-green-500' : 'bg-red-500'; ?>"
                            ></span>
                        </div>
                        <div
                            id="profile-dropdown"
                            class="absolute right-0 top-full mt-2 w-64 bg-white border border-gray-200 rounded-lg shadow-lg hidden z-50"
                        >
                            <div class="flex items-center p-4 border-b">
                                <img
                                    src="<?php echo $profile_picture; ?>"
                                    class="w-10 h-10 rounded-full object-cover"
                                    alt="Profile Picture"
                                    onerror="this.src='../assets/images/profile-placeholder.png';"
                                />
                                <div class="ml-3">
                                    <p class="text-sm font-semibold text-gray-800"><?php echo $fullname; ?></p>
                                    <p class="text-xs text-gray-500">SuperAdmin</p>
                                </div>
                            </div>
                            <div class="p-2">
                                <a
                                    href="accountsettings.php"
                                    class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-600 rounded-lg transition-all duration-200"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="w-5 h-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 1 1-3 0m30a1.5 1.5 0 1 0-3 0m-7.5 0h7.5m-12-6h3.75m-3.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-9.75 0h9.75"
                                        />
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
                        <li class="text-gray-900 font-medium">Account Management</li>
                    </ol>
                </nav>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="p-4 bg-green-50 text-green-700 rounded-lg border border-green-200 mb-6">
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <?php unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="p-4 bg-red-50 text-red-700 rounded-lg border border-red-200 mb-6">
                        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <?php unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>

                <div class="max-w-full mx-auto space-y-6">
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold text-dashboard">User Accounts</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Profile</th>
                                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Name</th>
                                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Email</th>
                                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Role</th>
                                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Approval Status</th>
                                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Account Status</th>
                                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="user-table" class="divide-y divide-gray-200">
                                    <?php while ($row = $users_result->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50 transition-all duration-200">
                                            <td class="px-6 py-4 flex items-center">
                                                <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden">
                                                    <img
                                                        src="<?php echo htmlspecialchars($row['profile_picture'] ?: '../assets/images/profile-placeholder.png'); ?>"
                                                        alt="Profile"
                                                        class="w-full h-full object-cover"
                                                        onerror="this.src='../assets/images/profile-placeholder.png';"
                                                    />
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['fullname']); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                <div class="flex items-center">
                                                    <div class="w-6 flex-shrink-0">
                                                        <?php if ($row['role'] === 'SuperAdmin'): ?>
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-primary-600">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                                            </svg>
                                                        <?php elseif ($row['role'] === 'Admin'): ?>
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-blue-500">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                                                            </svg>
                                                        <?php else: ?>
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-gray-500">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0ZM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                            </svg>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="ml-2"><?php echo htmlspecialchars($row['role']); ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $row['approval_status'] === 'Approved' ? 'bg-green-100 text-green-800' : ($row['approval_status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                                    <?php echo htmlspecialchars($row['approval_status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $row['account_status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo htmlspecialchars($row['account_status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <div class="flex space-x-2">
                                                    <a href="edit_account.php?id=<?php echo $row['id']; ?>" class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                        </svg>
                                                        Edit
                                                    </a>
                                                    <form method="POST" action="account_management.php?page=<?php echo $page; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                        <input type="hidden" name="action" value="<?php echo $row['account_status'] === 'Active' ? 'deactivate' : 'activate'; ?>">
                                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-<?php echo $row['account_status'] === 'Active' ? 'red-50 text-red-600' : 'green-50 text-green-600'; ?> rounded-lg hover:bg-<?php echo $row['account_status'] === 'Active' ? 'red-100' : 'green-100'; ?> focus:outline-none focus:ring-2 focus:ring-<?php echo $row['account_status'] === 'Active' ? 'red-500' : 'green-500'; ?> transition-all duration-200">
                                                            <?php if (!isset($loading[$row['id']])): ?>
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                    <?php if ($row['account_status'] === 'Active'): ?>
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-1.414-1.414a5 5 0 00-7.072 0L5.636 7.464m12.728-1.828a5 5 0 010 7.072L7.464 18.364a5 5 0 01-7.072 0L5.636 16.95m12.728-1.828l-1.414 1.414a5 5 0 01-7.072 0l-1.414-1.414" />
                                                                    <?php else: ?>
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                                    <?php endif; ?>
                                                                </svg>
                                                                <?php echo $row['account_status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>
                                                            <?php else: ?>
                                                                <svg class="animate-spin w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.373A8 8 0 0012 20v-4c-2.373 0-4-1.627-4-3.627h-2z"></path>
                                                                </svg>
                                                                Processing...
                                                            <?php endif; ?>
                                                        </button>
                                                    </form>
                                                    <button onclick="openDeleteModal(<?php echo $row['id']; ?>)" class="inline-flex items-center px-3 py-1.5 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 transition-all duration-200">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <div class="mt-6 flex justify-between items-center">
                                <p class="text-sm text-gray-600">
                                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $users_per_page, $total_users); ?> of <?php echo $total_users; ?> users
                                </p>
                                <div class="flex space-x-2">
                                    <?php if ($page > 1): ?>
                                        <a href="account_management.php?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-all duration-200">Previous</a>
                                    <?php endif; ?>
                                    <?php if ($page < $total_pages): ?>
                                        <a href="account_management.php?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-all duration-200">Next</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-xl shadow-sm p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Confirm Deletion</h3>
                <button onclick="closeDeleteModal()" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p class="text-sm text-gray-600 mb-6">Are you sure you want to delete this account? This action cannot be undone.</p>
            <form method="POST" action="process_account.php" id="deleteForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="user_id" id="deleteUserId" value="">
                <input type="hidden" name="action" value="delete">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-all duration-200">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-all duration-200">
                        <?php if (!isset($loading['delete'])): ?>
                            Delete
                        <?php else: ?>
                            <span class="flex items-center"><svg class="animate-spin w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.373A8 8 0 0012 20v-4c-2.373 0-4-1.627-4-3.627h-2z"></path></svg>Deleting...</span>
                        <?php endif; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById("sidebar");
        const sidebarToggle = document.getElementById("sidebar-toggle");
        const profile = document.getElementById("profile");
        const profileDropdown = document.getElementById("profile-dropdown");
        const searchInput = document.getElementById("search");
        const deleteModal = document.getElementById("deleteModal");
        const deleteForm = document.getElementById("deleteForm");

        sidebarToggle.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
        });

        document.addEventListener("click", (e) => {
            if (
                !sidebar.contains(e.target) &&
                !sidebarToggle.contains(e.target) &&
                !sidebar.classList.contains("-translate-x-full")
            ) {
                sidebar.classList.add("-translate-x-full");
            }
            if (!profile.contains(e.target)) {
                profileDropdown.classList.add("hidden");
            }
        });

        profile.addEventListener("click", (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle("hidden");
        });

        searchInput.addEventListener("input", () => {
            const query = searchInput.value.trim();
            window.location.href = `account_management.php?page=1${query ? '&search=' + encodeURIComponent(query) : ''}`;
        });

        function openDeleteModal(userId) {
            document.getElementById("deleteUserId").value = userId;
            deleteModal.classList.remove("hidden");
        }

        function closeDeleteModal() {
            deleteModal.classList.add("hidden");
            document.getElementById("deleteUserId").value = "";
        }

        const forms = document.querySelectorAll("form");
        forms.forEach(form => {
            form.addEventListener("submit", () => {
                const button = form.querySelector("button[type='submit']");
                if (button) {
                    button.disabled = true;
                    const originalText = button.innerHTML;
                    button.innerHTML = `<span class="flex items-center"><svg class="animate-spin w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.373A8 8 0 0012 20v-4c-2.373 0-4-1.627-4-3.627h-2z"></path></svg>Processing...</span>`;
                    setTimeout(() => {
                        button.disabled = false;
                        button.innerHTML = originalText;
                    }, 2000);
                }
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>