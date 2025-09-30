<?php
session_start();
require_once '../db/db.php';

// Check if the user is logged in and has the SuperAdmin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "SuperAdmin") {
    header("Location: ../login.php");
    exit();
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
// Determine online status (active within the last 5 minutes)
$is_online = (strtotime($user['last_active']) > time() - 300) ? true : false;

// Update last_active timestamp on page load
$update_query = "UPDATE users SET last_active = NOW() WHERE id = ?";
$update_stmt = $conn->prepare($update_query);
$update_stmt->bind_param("i", $user_id);
$update_stmt->execute();

// Handle search/filter inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_module = isset($_GET['filter_module']) ? trim($_GET['filter_module']) : '';
$filter_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';
$filter_date_from = isset($_GET['filter_date_from']) ? trim($_GET['filter_date_from']) : '';
$filter_date_to = isset($_GET['filter_date_to']) ? trim($_GET['filter_date_to']) : '';
$filter_lesson = isset($_GET['filter_lesson']) ? trim($_GET['filter_lesson']) : '';

// Fetch available modules and lessons for filters
$moduleStmt = $conn->query("SELECT DISTINCT m.title FROM modules m INNER JOIN lessons l ON l.module_id = m.id INNER JOIN quiz_results qr ON qr.lesson_id = l.id");
$moduleTitles = $moduleStmt->fetch_all(MYSQLI_ASSOC);

$lessonStmt = $conn->query("SELECT DISTINCT l.title FROM lessons l INNER JOIN quiz_results qr ON qr.lesson_id = l.id");
$lessonTitles = $lessonStmt->fetch_all(MYSQLI_ASSOC);

// Build dynamic SQL with latest quiz_results
$sql = "
    SELECT 
        u.fullname AS user_name,
        m.id AS module_id,
        m.title AS module_title,
        l.id AS lesson_id,
        l.title AS lesson_title,
        q.id AS quiz_id,
        q.question,
        q.option_a,
        q.option_b,
        q.option_c,
        q.option_d,
        q.correct_option,
        q.status AS question_status,
        uqa.selected_option,
        qr.score,
        qr.totalItems,
        qr.isPassed,
        qr.taken_at
    FROM (
        SELECT user_id, lesson_id, MAX(taken_at) AS latest_taken_at
        FROM quiz_results
        GROUP BY user_id, lesson_id
    ) latest
    INNER JOIN quiz_results qr ON qr.user_id = latest.user_id AND qr.lesson_id = latest.lesson_id AND qr.taken_at = latest.latest_taken_at
    INNER JOIN users u ON qr.user_id = u.id
    INNER JOIN lessons l ON qr.lesson_id = l.id
    INNER JOIN modules m ON l.module_id = m.id
    LEFT JOIN quizzes q ON l.id = q.lesson_id
    LEFT JOIN user_quiz_answers uqa ON q.id = uqa.quiz_id AND uqa.user_id = qr.user_id
    WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND u.fullname LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}

if (!empty($filter_module)) {
    $sql .= " AND m.title = ?";
    $params[] = $filter_module;
    $types .= 's';
}

if (!empty($filter_lesson)) {
    $sql .= " AND l.title = ?";
    $params[] = $filter_lesson;
    $types .= 's';
}

if (!empty($filter_status)) {
    $sql .= " AND qr.isPassed = ?";
    $params[] = $filter_status === 'passed' ? 1 : 0;
    $types .= 'i';
}

if (!empty($filter_date_from)) {
    $sql .= " AND qr.taken_at >= ?";
    $params[] = $filter_date_from;
    $types .= 's';
}

if (!empty($filter_date_to)) {
    $sql .= " AND qr.taken_at <= ?";
    $params[] = $filter_date_to . ' 23:59:59';
    $types .= 's';
}

$sql .= " ORDER BY u.fullname, m.title, l.title, q.id";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Debug: Log raw results (uncomment to use)
// error_log("Raw quiz results: " . print_r($results, true));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - User Records</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.ico">
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-white border-r shadow-lg transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 md:static md:shadow-none">
            <div class="flex items-center space-x-3 p-6 border-b">
                <img src="../assets/images/favicon.ico" alt="Logo" class="w-10 h-10 rounded-md">
                <h2 class="text-xl font-bold text-dashboard"><span class="text-red-600">SuperAdmin</span> Dashboard</h2>
            </div>
            <nav class="mt-6">
                <ul class="space-y-1 px-4">
                    <li>
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="user_approvals.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            User Approvals
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            Users
                        </a>
                    </li>
                    <li>
                        <a href="user_records.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                            </svg>
                            User Records
                        </a>
                    </li>
                    <li>
                        <a href="module_list.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
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
                        <a href="account_management.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                            </svg>
                            Account Management
                        </a>
                    </li>
                    <li>
                        <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-red-50 hover:text-red-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0l3-3m0 0-3-3m3 3H9" />
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
                    <h1 class="text-xl font-semibold text-dashboard">User Records</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative flex items-center space-x-2 cursor-pointer" id="profile">
                        <span class="text-gray-600 font-medium"><?= $fullname; ?></span>
                        <div class="relative">
                            <img src="<?= $profile_picture; ?>" class="w-10 h-10 rounded-full border border-gray-200 shadow-sm" alt="Profile Picture">
                            <span id="status-dot" class="absolute bottom-0 right-0 w-3 h-3 rounded-full border border-white <?= $is_online ? 'bg-green-500' : 'bg-red-500'; ?>"></span>
                        </div>
                        <!-- Profile Dropdown -->
                        <div id="profile-dropdown" class="absolute right-0 top-full mt-2 w-64 bg-white border border-gray-200 rounded-lg shadow-lg hidden z-50">
                            <div class="flex items-center p-4 border-b">
                                <img src="<?= $profile_picture; ?>" class="w-10 h-10 rounded-full" alt="Profile Picture">
                                <div class="ml-3">
                                    <p class="text-sm font-semibold text-gray-800"><?= $fullname; ?></p>
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

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <h2 class="text-lg font-semibold text-gray-800">Quiz Results</h2>
                </div>

                <!-- Filter Form -->
                <form method="GET" class="mb-6 bg-white p-6 rounded-lg shadow-md">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-600">Search User</label>
                            <div class="relative">
                                <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by user name..." class="mt-1 block w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-600">
                                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1116.65 16.65z" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <label for="filter_module" class="block text-sm font-medium text-gray-600">Module</label>
                            <select id="filter_module" name="filter_module" class="mt-1 block w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-600 bg-white">
                                <option value="">All Modules</option>
                                <?php foreach ($moduleTitles as $module): ?>
                                    <option value="<?= htmlspecialchars($module['title']) ?>" <?= $filter_module === $module['title'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($module['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="filter_lesson" class="block text-sm font-medium text-gray-600">Lesson</label>
                            <select id="filter_lesson" name="filter_lesson" class="mt-1 block w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-600 bg-white">
                                <option value="">All Lessons</option>
                                <?php foreach ($lessonTitles as $lesson): ?>
                                    <option value="<?= htmlspecialchars($lesson['title']) ?>" <?= $filter_lesson === $lesson['title'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lesson['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="filter_status" class="block text-sm font-medium text-gray-600">Status</label>
                            <select id="filter_status" name="filter_status" class="mt-1 block w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-600 bg-white">
                                <option value="">All Statuses</option>
                                <option value="passed" <?= $filter_status === 'passed' ? 'selected' : '' ?>>Passed</option>
                                <option value="failed" <?= $filter_status === 'failed' ? 'selected' : '' ?>>Failed</option>
                            </select>
                        </div>
                        <div>
                            <label for="filter_date_from" class="block text-sm font-medium text-gray-600">Date From</label>
                            <input type="date" id="filter_date_from" name="filter_date_from" value="<?= htmlspecialchars($filter_date_from) ?>" class="mt-1 block w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-600 bg-white">
                        </div>
                        <div>
                            <label for="filter_date_to" class="block text-sm font-medium text-gray-600">Date To</label>
                            <input type="date" id="filter_date_to" name="filter_date_to" value="<?= htmlspecialchars($filter_date_to) ?>" class="mt-1 block w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-600 bg-white">
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" onclick="window.location.href='user_records.php'" class="px-4 py-2 bg-gray-200 text-gray-600 rounded-lg hover:bg-gray-300 transition-colors duration-200">Reset</button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-7.5 0h7.5m-12-6h3.75m-3.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-9.75 0h9.75" />
                            </svg>
                            Apply
                        </button>
                    </div>
                </form>

                <!-- Loading Spinner -->
                <div id="loading" class="hidden fixed inset-0 bg-gray-900/50 flex items-center justify-center z-50">
                    <div class="animate-spin rounded-full h-12 w-12 border-t-4 border-primary-600"></div>
                </div>

                <?php if (empty($results)): ?>
                    <div class="text-center py-10 bg-white rounded-lg shadow-md">
                        <p class="text-gray-600">No quiz results found.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $grouped_results = [];
                    foreach ($results as $row) {
                        $user_name = $row['user_name'];
                        $module_title = $row['module_title'];
                        $lesson_title = $row['lesson_title'];

                        if (!isset($grouped_results[$user_name])) $grouped_results[$user_name] = [];
                        if (!isset($grouped_results[$user_name][$module_title])) $grouped_results[$user_name][$module_title] = [];
                        if (!isset($grouped_results[$user_name][$module_title][$lesson_title])) {
                            $grouped_results[$user_name][$module_title][$lesson_title] = [
                                'questions' => [],
                                'quiz_result' => [
                                    'score' => $row['score'],
                                    'totalItems' => $row['totalItems'],
                                    'isPassed' => $row['isPassed'],
                                    'taken_at' => $row['taken_at']
                                ]
                            ];
                        }

                        if (!empty($row['question'])) {
                            $grouped_results[$user_name][$module_title][$lesson_title]['questions'][] = $row;
                        }
                    }
                    ?>

                    <?php foreach ($grouped_results as $user_name => $modules): ?>
                        <div class="mb-6 bg-white rounded-lg shadow-md">
                            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                                <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                    </svg>
                                    <?= htmlspecialchars($user_name); ?>
                                </h2>
                                <button class="toggle-section text-gray-600 hover:text-primary-600" data-target="user-<?= md5($user_name) ?>">
                                    <svg class="w-5 h-5 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                    </svg>
                                </button>
                            </div>
                            <div id="user-<?= md5($user_name) ?>" class="section-content hidden p-6">
                                <?php foreach ($modules as $module_title => $lessons): ?>
                                    <div class="mb-6">
                                        <h3 class="text-md font-medium text-gray-700 mb-3 flex items-center gap-2">
                                            <svg class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                            </svg>
                                            <?= htmlspecialchars($module_title); ?>
                                        </h3>
                                        <?php foreach ($lessons as $lesson_title => $data): ?>
                                            <div class="mb-6">
                                                <h4 class="text-sm font-medium text-gray-600 mb-2 flex items-center gap-2">
                                                    <svg class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597-.237 1.17-.659 1.591L19.8 14.5M14.25 3.104c.251.023.501.05.75.082M5 14.5l4.096-4.096m0 0a2.25 2.25 0 0 1 1.591.659l4.096 4.096M5 14.5l4.096 4.096" />
                                                    </svg>
                                                    <?= htmlspecialchars($lesson_title); ?>
                                                </h4>
                                                <?php if (empty($data['questions'])): ?>
                                                    <p class="text-gray-600 text-sm">No questions available for this lesson.</p>
                                                <?php else: ?>
                                                    <!-- Desktop Table -->
                                                    <div class="hidden md:block overflow-x-auto">
                                                        <table class="min-w-full bg-white rounded-lg">
                                                            <thead class="sticky top-0 bg-gray-100 text-primary-600 uppercase text-xs font-semibold">
                                                                <tr>
                                                                    <th class="py-3 px-4 text-left">Question</th>
                                                                    <th class="py-3 px-4 text-center">Correct Answer</th>
                                                                    <th class="py-3 px-4 text-center">User Answer</th>
                                                                    <th class="py-3 px-4 text-center">Status</th>
                                                                    <th class="py-3 px-4 text-center">Question Status</th>
                                                                    <th class="py-3 px-4 text-center">Quiz Result</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="text-gray-600 text-sm divide-y divide-gray-200">
                                                                <?php foreach ($data['questions'] as $row): ?>
                                                                    <tr class="hover:bg-gray-50 transition-colors">
                                                                        <td class="py-3 px-4 text-left"><?= htmlspecialchars($row['question']); ?></td>
                                                                        <td class="py-3 px-4 text-center"><?= htmlspecialchars($row['option_' . strtolower($row['correct_option'])] ?? 'N/A'); ?></td>
                                                                        <td class="py-3 px-4 text-center">
                                                                            <?php
                                                                            if ($row['selected_option'] && in_array($row['selected_option'], ['A', 'B', 'C', 'D'])) {
                                                                                echo htmlspecialchars($row['option_' . strtolower($row['selected_option'])] ?? 'Invalid Option');
                                                                            } else {
                                                                                echo 'Not Answered';
                                                                            }
                                                                            ?>
                                                                        </td>
                                                                        <td class="py-3 px-4 text-center">
                                                                            <?php
                                                                            if ($row['selected_option'] && in_array($row['selected_option'], ['A', 'B', 'C', 'D'])) {
                                                                                echo $row['selected_option'] === $row['correct_option']
                                                                                    ? '<span class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">Correct</span>'
                                                                                    : '<span class="inline-flex items-center px-2 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">Incorrect</span>';
                                                                            } else {
                                                                                echo '<span class="text-gray-600 text-xs">Not Answered</span>';
                                                                            }
                                                                            ?>
                                                                        </td>
                                                                        <td class="py-3 px-4 text-center group relative">
                                                                            <?= $row['question_status'] === 'active'
                                                                                ? '<span class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">Active</span>'
                                                                                : '<span class="inline-flex items-center px-2 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">Inactive</span>'; ?>
                                                                            <span class="absolute hidden group-hover:block bg-gray-800 text-white text-xs rounded py-1 px-2 -top-8 left-1/2 transform -translate-x-1/2 whitespace-nowrap">
                                                                                <?= $row['question_status'] === 'active' ? 'Question is active' : 'Question is inactive' ?>
                                                                            </span>
                                                                        </td>
                                                                        <td class="py-3 px-4 text-center group relative">
                                                                            <div class="text-xs">
                                                                                <div>
                                                                                    Score: 
                                                                                    <?php 
                                                                                    if ($data['quiz_result']['score'] !== null && $data['quiz_result']['totalItems'] > 0) {
                                                                                        echo htmlspecialchars($data['quiz_result']['score'] . '/' . $data['quiz_result']['totalItems']); 
                                                                                    } else {
                                                                                        echo 'N/A';
                                                                                    }
                                                                                    ?>
                                                                                </div>
                                                                                <div>
                                                                                    Status: 
                                                                                    <?php 
                                                                                    if ($data['quiz_result']['isPassed'] !== null) {
                                                                                        echo $data['quiz_result']['isPassed']
                                                                                            ? '<span class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">Passed</span>'
                                                                                            : '<span class="inline-flex items-center px-2 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">Failed</span>';
                                                                                    } else {
                                                                                        echo '<span class="inline-flex items-center px-2 py-1 rounded-full bg-gray-100 text-gray-700 text-xs font-medium">Not Evaluated</span>';
                                                                                    }
                                                                                    ?>
                                                                                </div>
                                                                                <div>
                                                                                    Taken: 
                                                                                    <?php 
                                                                                    echo $data['quiz_result']['taken_at'] 
                                                                                        ? date('M j, Y', strtotime($data['quiz_result']['taken_at'])) 
                                                                                        : 'N/A';
                                                                                    ?>
                                                                                </div>
                                                                            </div>
                                                                            <span class="absolute hidden group-hover:block bg-gray-800 text-white text-xs rounded py-1 px-2 -top-8 left-1/2 transform -translate-x-1/2">
                                                                                <?php 
                                                                                echo $data['quiz_result']['taken_at'] 
                                                                                    ? 'Quiz taken on ' . date('M j, Y', strtotime($data['quiz_result']['taken_at'])) 
                                                                                    : 'Quiz not yet evaluated';
                                                                                ?>
                                                                            </span>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <!-- Mobile Card View -->
                                                    <div class="md:hidden space-y-4">
                                                        <?php foreach ($data['questions'] as $row): ?>
                                                            <div class="bg-white p-4 rounded-lg shadow-md">
                                                                <p class="text-sm font-medium text-gray-800 mb-2"><?= htmlspecialchars($row['question']); ?></p>
                                                                <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                                                                    <div>
                                                                        <span class="font-medium">Correct:</span>
                                                                        <?= htmlspecialchars($row['option_' . strtolower($row['correct_option'])] ?? 'N/A'); ?>
                                                                    </div>
                                                                    <div>
                                                                        <span class="font-medium">User:</span>
                                                                        <?php
                                                                        if ($row['selected_option'] && in_array($row['selected_option'], ['A', 'B', 'C', 'D'])) {
                                                                            echo htmlspecialchars($row['option_' . strtolower($row['selected_option'])] ?? 'Invalid Option');
                                                                        } else {
                                                                            echo 'Not Answered';
                                                                        }
                                                                        ?>
                                                                    </div>
                                                                    <div>
                                                                        <span class="font-medium">Status:</span>
                                                                        <?php
                                                                        if ($row['selected_option'] && in_array($row['selected_option'], ['A', 'B', 'C', 'D'])) {
                                                                            echo $row['selected_option'] === $row['correct_option']
                                                                                ? '<span class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">Correct</span>'
                                                                                : '<span class="inline-flex items-center px-2 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">Incorrect</span>';
                                                                        } else {
                                                                            echo '<span class="text-gray-600 text-xs">Not Answered</span>';
                                                                        }
                                                                        ?>
                                                                    </div>
                                                                    <div>
                                                                        <span class="font-medium">Question:</span>
                                                                        <?= $row['question_status'] === 'active'
                                                                            ? '<span class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">Active</span>'
                                                                            : '<span class="inline-flex items-center px-2 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">Inactive</span>'; ?>
                                                                    </div>
                                                                    <div class="col-span-2">
                                                                        <span class="font-medium">Quiz:</span>
                                                                        Score: 
                                                                        <?php 
                                                                        if ($data['quiz_result']['score'] !== null && $data['quiz_result']['totalItems'] > 0) {
                                                                            echo htmlspecialchars($data['quiz_result']['score'] . '/' . $data['quiz_result']['totalItems']); 
                                                                        } else {
                                                                            echo 'N/A';
                                                                        }
                                                                        ?>,
                                                                        Status: 
                                                                        <?php 
                                                                        if ($data['quiz_result']['isPassed'] !== null) {
                                                                            echo $data['quiz_result']['isPassed']
                                                                                ? '<span class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">Passed</span>'
                                                                                : '<span class="inline-flex items-center px-2 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">Failed</span>';
                                                                        } else {
                                                                            echo '<span class="inline-flex items-center px-2 py-1 rounded-full bg-gray-100 text-gray-700 text-xs font-medium">Not Evaluated</span>';
                                                                        }
                                                                        ?>,
                                                                        Taken: 
                                                                        <?php 
                                                                        echo $data['quiz_result']['taken_at'] 
                                                                            ? date('M j, Y', strtotime($data['quiz_result']['taken_at'])) 
                                                                            : 'N/A';
                                                                        ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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

        document.addEventListener("click", (e) => {
            if (
                !sidebar.contains(e.target) &&
                !sidebarToggle.contains(e.target) &&
                !sidebar.classList.contains("-translate-x-full")
            ) {
                sidebar.classList.add("-translate-x-full");
            }
        });

        // Profile dropdown toggle
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

        // Show loading spinner on form submit
        const form = document.querySelector("form");
        const loading = document.getElementById("loading");
        form.addEventListener("submit", () => {
            loading.classList.remove("hidden");
        });

        // Toggle sections
        document.querySelectorAll(".toggle-section").forEach(button => {
            button.addEventListener("click", () => {
                const targetId = button.getAttribute("data-target");
                const target = document.getElementById(targetId);
                const icon = button.querySelector("svg");
                target.classList.toggle("hidden");
                icon.classList.toggle("rotate-180");
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>