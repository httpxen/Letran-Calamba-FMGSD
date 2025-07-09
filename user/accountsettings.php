<?php
session_start();
include '../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "User") {
    header("Location: login.php");
    exit();
}

// Check if profile_picture column exists, if not, add it
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT '../assets/images/profile-placeholder.png'");
}

// Get current user info
$user_id = $_SESSION['user_id'];
$query = "SELECT fullname, email, profile_picture, password FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle form submission (profile picture, full name, email, and password)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $update_fields = [];
    $update_params = [];
    $update_types = "";

    // Validate Full Name
    if (!empty($fullname) && $fullname !== $user['fullname']) {
        $update_fields[] = "fullname = ?";
        $update_params[] = $fullname;
        $update_types .= "s";
        $_SESSION['fullname'] = $fullname;
    }

    // Validate Email
    if (!empty($email) && $email !== $user['email']) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
            $email_check_stmt = $conn->prepare($email_check_query);
            $email_check_stmt->bind_param("si", $email, $user_id);
            $email_check_stmt->execute();
            $email_check_result = $email_check_stmt->get_result();

            if ($email_check_result->num_rows == 0) {
                $update_fields[] = "email = ?";
                $update_params[] = $email;
                $update_types .= "s";
            } else {
                $error_message = "This email is already in use by another user.";
            }
        } else {
            $error_message = "Please enter a valid email address.";
        }
    }

    // Handle Password Change
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "Please fill in all password fields to change your password.";
        } else {
            if (password_verify($current_password, $user['password'])) {
                if ($new_password === $confirm_password) {
                    if (strlen($new_password) >= 8) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_fields[] = "password = ?";
                        $update_params[] = $hashed_password;
                        $update_types .= "s";
                    } else {
                        $error_message = "New password must be at least 8 characters long.";
                    }
                } else {
                    $error_message = "New password and confirmation do not match.";
                }
            } else {
                $error_message = "Current password is incorrect.";
            }
        }
    }

    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $target_dir = "../Uploads/profile_pics/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = uniqid() . '_' . basename($_FILES["profile_picture"]["name"]);
        $target_file = $target_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
        if ($check !== false) {
            if ($_FILES["profile_picture"]["size"] <= 5000000) {
                if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                    if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                        if (
                            !empty($user['profile_picture']) &&
                            $user['profile_picture'] != '../assets/images/profile-placeholder.png' &&
                            file_exists($user['profile_picture'])
                        ) {
                            unlink($user['profile_picture']);
                        }

                        $update_fields[] = "profile_picture = ?";
                        $update_params[] = $target_file;
                        $update_types .= "s";
                        $_SESSION['profile_picture'] = $target_file;
                        $user['profile_picture'] = $target_file;
                    } else {
                        $error_message = "Sorry, there was an error uploading your file.";
                    }
                } else {
                    $error_message = "Only JPG, JPEG, PNG & GIF files are allowed.";
                }
            } else {
                $error_message = "File is too large. Maximum size is 5MB.";
            }
        } else {
            $error_message = "File is not an image.";
        }
    }

    // Update the database if there are changes
    if (!empty($update_fields) && !isset($error_message)) {
        $update_query = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
        $update_params[] = $user_id;
        $update_types .= "i";

        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param($update_types, ...$update_params);
        $update_stmt->execute();

        $success_message = "Profile updated successfully!";

        $query = "SELECT fullname, email, profile_picture, password FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    } elseif (empty($update_fields) && !isset($error_message)) {
        $error_message = "No changes were made.";
    }
}

// Set profile picture with fallback
$profile_pic = isset($user['profile_picture']) && !empty($user['profile_picture']) && file_exists($user['profile_picture'])
    ? $user['profile_picture']
    : '../assets/images/profile-placeholder.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User Account Settings</title>
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
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside
            id="sidebar"
            class="fixed inset-y-0 left-0 w-64 bg-white border-r shadow-soft transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 md:static md:shadow-none flex flex-col"
        >
            <div class="flex items-center space-x-3 p-6 border-b border-gray-100">
                <img
                    src="../assets/images/favicon.ico"
                    alt="Logo"
                    class="w-10 h-10 rounded-full"
                />
                <h2 class="text-xl font-bold text-dashboard">
                    <span class="text-red-600">User</span> Dashboard
                </h2>
            </div>
            <nav class="mt-6 flex-1">
                <ul class="space-y-1 px-4">
                    <li>
                        <a
                            href="dashboard.php"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02]"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="w-5 h-5 text-primary-600"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
                                />
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a
                            href="modules_list.php"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02]"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="w-5 h-5 text-primary-600"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"
                                />
                            </svg>
                            Modules
                        </a>
                    </li>
                    <li>
                        <a
                            href="results.php"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02]"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="w-5 h-5 text-primary-600"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"
                                />
                            </svg>
                            View Results
                        </a>
                    </li>
                    <li>
                        <a
                            href="../logout.php"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-red-50 hover:text-red-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02]"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="w-5 h-5 text-red-600"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"
                                />
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
            <header
                class="bg-white shadow-soft flex justify-between items-center px-6 py-4"
            >
                <div class="flex items-center space-x-4">
                    <button
                        id="sidebar-toggle"
                        class="md:hidden text-gray-600 hover:text-primary-600 focus:outline-none transition-all duration-200"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="w-6 h-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"
                            />
                        </svg>
                    </button>
                    <h1 class="text-2xl font-semibold text-dashboard">
                        Account Settings
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-600 font-medium hidden sm:block"><?php echo htmlspecialchars($user['fullname']); ?></span>
                        <img
                            src="<?php echo htmlspecialchars($profile_pic); ?>"
                            class="w-10 h-10 rounded-full border border-gray-200 shadow-sm"
                            alt="Profile Picture"
                        />
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <!-- Breadcrumbs -->
                <nav class="mb-6">
                    <ol class="flex items-center space-x-2 text-sm text-gray-600">
                        <li>
                            <a href="dashboard.php" class="hover:text-primary-600 transition-all duration-200">Dashboard</a>
                        </li>
                        <li><span class="text-gray-400">/</span></li>
                        <li class="text-gray-900 font-medium">Account Settings</li>
                    </ol>
                </nav>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Profile and Password Form -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Success/Error Messages -->
                        <?php if (isset($success_message)): ?>
                            <div class="p-4 bg-green-50 text-green-700 rounded-lg border border-green-200 animate-fade-in">
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($error_message)): ?>
                            <div class="p-4 bg-red-50 text-red-700 rounded-lg border border-red-200 animate-fade-in">
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Profile Card -->
                        <div class="bg-white p-8 rounded-xl shadow-soft animate-fade-in">
                            <h2 class="text-lg font-semibold text-dashboard mb-6">Profile Information</h2>
                            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                                <!-- Profile Picture -->
                                <div class="flex items-center space-x-6 mb-6">
                                    <div class="relative">
                                        <img
                                            id="profile-preview"
                                            src="<?php echo htmlspecialchars($profile_pic); ?>"
                                            class="w-24 h-24 rounded-full border border-gray-200 shadow-sm object-cover"
                                            alt="Profile Picture"
                                        />
                                        <label for="profile_picture" class="absolute bottom-0 right-0 bg-primary-600 text-white rounded-full p-2 cursor-pointer hover:bg-primary-700 transition-all duration-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                            </svg>
                                            <input
                                                type="file"
                                                id="profile_picture"
                                                name="profile_picture"
                                                accept="image/*"
                                                class="hidden"
                                                onchange="previewProfilePic(event)"
                                            />
                                        </label>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-700">Profile Picture</p>
                                        <p class="text-xs text-gray-500 mt-1">JPG, PNG, GIF (Max 5MB)</p>
                                    </div>
                                </div>

                                <!-- Full Name -->
                                <div class="space-y-2">
                                    <label for="fullname" class="block text-sm font-medium text-gray-700">Full Name <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input
                                            type="text"
                                            name="fullname"
                                            id="fullname"
                                            value="<?php echo htmlspecialchars($user['fullname']); ?>"
                                            class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200"
                                            required
                                        />
                                        <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- Email -->
                                <div class="space-y-2">
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input
                                            type="email"
                                            name="email"
                                            id="email"
                                            value="<?php echo htmlspecialchars($user['email']); ?>"
                                            class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200"
                                            required
                                        />
                                        <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- Save Button -->
                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg hover:from-primary-700 hover:to-primary-800 font-medium transition-all duration-200 ease-in-out transform hover:scale-105"
                                    >
                                        Save Profile
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Password Card -->
                        <div class="bg-white p-8 rounded-xl shadow-soft animate-fade-in">
                            <h2 class="text-lg font-semibold text-dashboard mb-6">Change Password</h2>
                            <form method="POST" class="space-y-6">
                                <!-- Current Password -->
                                <div class="space-y-2">
                                    <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                                    <div class="relative">
                                        <input
                                            type="password"
                                            name="current_password"
                                            id="current_password"
                                            class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200"
                                        />
                                        <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- New Password -->
                                <div class="space-y-2">
                                    <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                                    <div class="relative">
                                        <input
                                            type="password"
                                            name="new_password"
                                            id="new_password"
                                            class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200"
                                            oninput="checkPasswordStrength(this.value)"
                                        />
                                        <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                        </svg>
                                    </div>
                                    <div id="password-strength" class="text-xs mt-2 hidden">
                                        <span id="strength-text" class="font-medium"></span>
                                        <div id="strength-bar" class="h-1 rounded-full mt-1"></div>
                                    </div>
                                </div>

                                <!-- Confirm Password -->
                                <div class="space-y-2">
                                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                    <div class="relative">
                                        <input
                                            type="password"
                                            name="confirm_password"
                                            id="confirm_password"
                                            class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200"
                                        />
                                        <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.22.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- Update Button -->
                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg hover:from-primary-700 hover:to-primary-800 font-medium transition-all duration-200 ease-in-out transform hover:scale-105"
                                    >
                                        Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- User Info and Actions -->
                    <div class="space-y-6">
                        <!-- User Info Card -->
                        <div class="bg-white p-6 rounded-xl shadow-soft animate-fade-in">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">User Information</h3>
                            <div class="flex items-center space-x-4 mb-4">
                                <img
                                    src="<?php echo htmlspecialchars($profile_pic); ?>"
                                    class="w-16 h-16 rounded-full border border-gray-200 shadow-sm"
                                    alt="Profile Picture"
                                />
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['fullname']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <p class="text-sm text-gray-600">
                                    <span class="font-medium">Role:</span> User
                                </p>
                                <p class="text-sm text-gray-600">
                                    <span class="font-medium">Last Updated:</span> <?php echo date('M d, Y'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Actions Card -->
                        <div class="bg-white p-6 rounded-xl shadow-soft animate-fade-in">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
                            <div class="space-y-3">
                                <a
                                    href="dashboard.php"
                                    class="flex items-center gap-2 px-4 py-2 bg-primary-50 text-primary-600 rounded-lg hover:bg-primary-100 font-medium transition-all duration-200"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                                    </svg>
                                    Back to Dashboard
                                </a>
                                <a
                                    href="../logout.php"
                                    class="flex items-center gap-2 px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 font-medium transition-all duration-200"
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
                                            d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"
                                        />
                                    </svg>
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Sidebar toggle
        const sidebar = document.getElementById("sidebar");
        const sidebarToggle = document.getElementById("sidebar-toggle");

        sidebarToggle.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener("click", (e) => {
            if (
                !sidebar.contains(e.target) &&
                !sidebarToggle.contains(e.target) &&
                !sidebar.classList.contains("-translate-x-full")
            ) {
                sidebar.classList.add("-translate-x-full");
            }
        });

        // Profile picture preview
        function previewProfilePic(event) {
            const preview = document.getElementById('profile-preview');
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');
            const strengthDiv = document.getElementById('password-strength');
            let strength = 0;

            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;

            strengthDiv.classList.remove('hidden');
            strengthBar.style.width = strength + '%';

            if (strength <= 25) {
                strengthText.textContent = 'Weak';
                strengthBar.classList.remove('bg-yellow-400', 'bg-green-400');
                strengthBar.classList.add('bg-red-400');
            } else if (strength <= 50) {
                strengthText.textContent = 'Fair';
                strengthBar.classList.remove('bg-red-400', 'bg-green-400');
                strengthBar.classList.add('bg-yellow-400');
            } else if (strength <= 75) {
                strengthText.textContent = 'Good';
                strengthBar.classList.remove('bg-red-400', 'bg-yellow-400');
                strengthBar.classList.add('bg-green-400');
            } else {
                strengthText.textContent = 'Strong';
                strengthBar.classList.remove('bg-red-400', 'bg-yellow-400');
                strengthBar.classList.add('bg-green-400');
            }
        }
    </script>
</body>
</html>