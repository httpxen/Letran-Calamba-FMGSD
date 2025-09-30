<?php
session_start();
require_once '../db/db.php';

// Check if the user is logged in and has the Admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "Admin") {
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

// Get total number of users
$user_count_query = "SELECT COUNT(*) as total_users FROM users WHERE role = 'User' AND account_status = 'Active'";
$user_count_result = $conn->query($user_count_query);
$total_users = $user_count_result->fetch_assoc()['total_users'];

// Get total number of modules
$module_count_query = "SELECT COUNT(*) as total_modules FROM modules";
$module_count_result = $conn->query($module_count_query);
$total_modules = $module_count_result->fetch_assoc()['total_modules'];

// Get exam takers data
$exam_takers_query = "
    SELECT m.title AS module_title, l.title AS lesson_title, COUNT(DISTINCT qr.user_id) AS taker_count
    FROM quiz_results qr
    JOIN lessons l ON qr.lesson_id = l.id
    JOIN modules m ON l.module_id = m.id
    GROUP BY m.id, l.id, m.title, l.title
    ORDER BY taker_count DESC
    LIMIT 5";
$exam_takers_result = $conn->query($exam_takers_query);
$exam_takers = [];
while ($row = $exam_takers_result->fetch_assoc()) {
    $row['percentage'] = $total_users > 0 ? round(($row['taker_count'] / $total_users) * 100, 1) : 0;
    $exam_takers[] = $row;
}

// Get pending user approvals for notification
$pending_approvals_query = "SELECT COUNT(*) as pending_count FROM users WHERE approval_status = 'Pending'";
$pending_approvals_result = $conn->query($pending_approvals_query);
$pending_approvals = $pending_approvals_result->fetch_assoc()['pending_count'];

// Get recent passing users (passed quizzes in the last 24 hours)
$passing_users_query = "
    SELECT u.fullname, l.title as lesson_title, qr.score, qr.totalItems, qr.taken_at
    FROM quiz_results qr
    JOIN users u ON qr.user_id = u.id
    JOIN lessons l ON qr.lesson_id = l.id
    WHERE qr.isPassed = 1
    AND qr.taken_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY qr.taken_at DESC
    LIMIT 5";
$passing_users_result = $conn->query($passing_users_query);
$passing_users = [];
while ($row = $passing_users_result->fetch_assoc()) {
    $passing_users[] = $row;
}

// Update notification count
$notification_count = $pending_approvals + count($passing_users);

// Get user activity summary (quizzes taken this week and average score)
$quizzes_taken_query = "
    SELECT COUNT(*) as quizzes_taken
    FROM quiz_results
    WHERE taken_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$quizzes_taken_result = $conn->query($quizzes_taken_query);
$quizzes_taken = $quizzes_taken_result->fetch_assoc()['quizzes_taken'];

$avg_score_query = "
    SELECT AVG(score / totalItems * 100) as avg_score
    FROM quiz_results";
$avg_score_result = $conn->query($avg_score_query);
$avg_score = round($avg_score_result->fetch_assoc()['avg_score'], 2);

// Get Total Passed 1st Taken
$passed_first_query = "
    SELECT COUNT(DISTINCT qr.user_id) AS passed_first_attempt
    FROM quiz_results qr
    JOIN (
        SELECT user_id, lesson_id, MIN(taken_at) AS first_attempt
        FROM quiz_results
        GROUP BY user_id, lesson_id
    ) first_attempts ON qr.user_id = first_attempts.user_id 
        AND qr.lesson_id = first_attempts.lesson_id 
        AND qr.taken_at = first_attempts.first_attempt
    WHERE qr.isPassed = 1";
$passed_first_result = $conn->query($passed_first_query);
$passed_first = $passed_first_result->fetch_assoc()['passed_first_attempt'];

// Get Total Taken Module 1st Taken
$total_first_query = "
    SELECT COUNT(*) AS total_first_attempts
    FROM (
        SELECT user_id, lesson_id, MIN(taken_at) AS first_attempt
        FROM quiz_results
        GROUP BY user_id, lesson_id
    ) first_attempts";
$total_first_result = $conn->query($total_first_query);
$total_first_attempts = $total_first_result->fetch_assoc()['total_first_attempts'];

// Calculate Percentage
$first_attempt_pass_percentage = $total_first_attempts > 0 ? round(($passed_first / $total_first_attempts) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard</title>
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
              dashboard: {
                light: "#12234e",
              },
            },
            animation: {
              'fade-in': 'fadeIn 0.5s ease-in-out',
              'pulse': 'pulse 1.5s infinite',
            },
            keyframes: {
              fadeIn: {
                '0%': { opacity: '0', transform: 'translateY(10px)' },
                '100%': { opacity: '1', transform: 'translateY(0)' },
              },
              pulse: {
                '0%, 100%': { transform: 'scale(1)', opacity: '1' },
                '50%': { transform: 'scale(1.1)', opacity: '0.8' },
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
  <body class="bg-gray-100 text-gray-900 font-sans antialiased">
    <div class="flex min-h-screen">
      <!-- Sidebar -->
      <aside
        id="sidebar"
        class="fixed inset-y-0 left-0 w-64 bg-white border-r shadow-lg transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 md:static md:shadow-none flex flex-col"
      >
        <div class="flex items-center space-x-3 p-6 border-b">
          <img
            src="../assets/images/favicon.ico"
            alt="Logo"
            class="w-10 h-10 rounded-md"
          />
          <h2 class="text-xl font-bold text-dashboard-light">
            <span class="text-red-600">Admin</span> Dashboard
          </h2>
        </div>

        <nav class="mt-6 flex-1">
          <ul class="space-y-1 px-4">
            <!-- Dashboard -->
            <li>
              <a
                href="dashboard.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-600 font-medium transition-all duration-200"
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

            <!-- User Approvals -->
            <li>
              <a
                href="user_approvals.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200"
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
                    d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
                User Approvals
              </a>
            </li>

            <!-- Users -->
            <li>
              <a
                href="users.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200"
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
                    d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                  />
                </svg>
                Users
              </a>
            </li>

            <!-- User Records -->
            <li>
              <a
                href="user_records.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200"
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
                    d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"
                  />
                </svg>
                User Records
              </a>
            </li>

            <!-- Module List -->
            <li>
              <a
                href="module_list.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200"
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

            <!-- Logout -->
            <li>
              <a
                href="../logout.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-red-50 hover:text-red-600 font-medium transition-all duration-200"
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

        <!-- Version Footer -->
        <div class="p-4 border-t border-gray-100 mt-auto">
          <p class="text-xs text-gray-500">Version 1.0.0</p>
        </div>
      </aside>

      <!-- Main content -->
      <div class="flex-1 flex flex-col">
        <!-- Topbar -->
        <header class="bg-white shadow-sm flex justify-between items-center px-6 py-4">
          <div class="flex items-center space-x-4">
            <button
              id="sidebar-toggle"
              class="md:hidden text-gray-600 hover:text-primary-600 focus:outline-none"
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
            <h1 class="text-xl font-semibold text-dashboard-light">
              Welcome, <?php echo $fullname; ?>!
            </h1>
            <p class="text-gray-600">
              Manage users, modules, and track progress.
            </p>
          </div>
          <div class="flex items-center space-x-4">
            <!-- Notification Bell -->
            <div class="relative">
              <button id="notification-bell" class="text-gray-600 hover:text-primary-600 focus:outline-none relative p-1 rounded-full <?php echo $notification_count > 0 ? 'bg-primary-50' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.454 1.31m5.714 0a3 3 0 11-5.714 0" />
                </svg>
                <?php if ($notification_count > 0): ?>
                  <span class="absolute top-0 right-0 bg-red-600 text-white text-xs font-semibold rounded-full w-5 h-5 flex items-center justify-center shadow-md animate-pulse"><?php echo $notification_count; ?></span>
                <?php endif; ?>
              </button>
              <!-- Notification Dropdown -->
              <div id="notification-dropdown" class="absolute right-0 top-full mt-2 w-64 bg-white border border-gray-200 rounded-lg shadow-lg hidden z-50">
                <div class="p-4 border-b">
                  <p class="text-sm font-semibold text-gray-800">Notifications</p>
                </div>
                <div class="p-2 max-h-64 overflow-y-auto">
                  <?php if ($notification_count > 0): ?>
                    <!-- Pending Approvals -->
                    <?php if ($pending_approvals > 0): ?>
                      <a href="user_approvals.php" class="block px-4 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-600 rounded-lg transition-all duration-200">
                        <p class="text-sm"><?php echo $pending_approvals; ?> Pending User Approvals</p>
                      </a>
                    <?php endif; ?>
                    <!-- Passing Users -->
                    <?php if (count($passing_users) > 0): ?>
                      <?php foreach ($passing_users as $passer): ?>
                        <div class="px-4 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-600 rounded-lg transition-all duration-200">
                          <p class="text-sm font-semibold"><?php echo htmlspecialchars($passer['fullname']); ?> passed "<?php echo htmlspecialchars($passer['lesson_title']); ?>"</p>
                          <p class="text-xs text-gray-500">Score: <?php echo $passer['score'] . '/' . $passer['totalItems']; ?> (<?php echo date('M d, Y H:i', strtotime($passer['taken_at'])); ?>)</p>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  <?php else: ?>
                    <p class="px-4 py-2 text-gray-500 text-sm">No new notifications</p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <!-- Profile -->
            <div class="relative flex items-center space-x-2 cursor-pointer" id="profile">
              <span class="text-gray-600 font-medium"><?php echo $fullname; ?></span>
              <div class="relative">
                <img
                  src="<?php echo $profile_picture; ?>"
                  class="w-10 h-10 rounded-full border border-gray-200 shadow-sm"
                  alt="Profile Picture"
                />
                <span
                  id="status-dot"
                  class="absolute bottom-0 right-0 w-3 h-3 rounded-full border border-white <?php echo $is_online ? 'bg-green-500' : 'bg-red-500'; ?>"
                ></span>
              </div>
              <!-- Profile Dropdown -->
              <div
                id="profile-dropdown"
                class="absolute right-0 top-full mt-2 w-64 bg-white border border-gray-200 rounded-lg shadow-lg hidden z-50"
              >
                <div class="flex items-center p-4 border-b">
                  <img
                    src="<?php echo $profile_picture; ?>"
                    class="w-10 h-10 rounded-full"
                    alt="Profile Picture"
                  />
                  <div class="ml-3">
                    <p class="text-sm font-semibold text-gray-800"><?php echo $fullname; ?></p>
                    <p class="text-xs text-gray-500">Admin</p>
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
                        d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-7.5 0h7.5m-12-6h3.75m-3.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-9.75 0h9.75"
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
        <main class="flex-1 overflow-y-auto">
          <div class="max-w-7xl mx-auto p-6 space-y-6">
            <!-- Overview Section -->
            <section class="animate-fade-in">
              <h2 class="text-2xl font-bold text-dashboard-light mb-4">Overview</h2>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Total Users Card -->
                <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 min-h-[120px]">
                  <div class="flex items-center space-x-4">
                    <div class="p-3 bg-primary-50 rounded-full relative group">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="w-6 h-6 text-primary-600 group-hover:text-primary-700 transition-colors duration-200 transform group-hover:scale-110"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                        />
                      </svg>
                      <div class="absolute inset-0 bg-primary-100 rounded-full opacity-0 group-hover:opacity-50 transition-opacity duration-200"></div>
                    </div>
                    <div>
                      <h3 class="text-lg font-semibold text-gray-800">Total Users</h3>
                      <p class="text-3xl font-bold text-primary-600"><?php echo $total_users; ?></p>
                      <p class="text-sm text-gray-500">Active users in the system</p>
                    </div>
                  </div>
                </div>
                <!-- Total Modules Card -->
                <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 min-h-[120px]">
                  <div class="flex items-center space-x-4">
                    <div class="p-3 bg-primary-50 rounded-full relative group">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="w-6 h-6 text-primary-600 group-hover:text-primary-700 transition-colors duration-200 transform group-hover:scale-110"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"
                        />
                      </svg>
                      <div class="absolute inset-0 bg-primary-100 rounded-full opacity-0 group-hover:opacity-50 transition-opacity duration-200"></div>
                    </div>
                    <div>
                      <h3 class="text-lg font-semibold text-gray-800">Total Modules</h3>
                      <p class="text-3xl font-bold text-primary-600"><?php echo $total_modules; ?></p>
                      <p class="text-sm text-gray-500">Available modules in the system</p>
                    </div>
                  </div>
                </div>
                <!-- User Activity Card -->
                <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 min-h-[120px]">
                  <div class="flex items-center space-x-4">
                    <div class="p-3 bg-primary-50 rounded-full relative group">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="w-6 h-6 text-primary-600 group-hover:text-primary-700 transition-colors duration-200 transform group-hover:scale-110"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"
                        />
                      </svg>
                      <div class="absolute inset-0 bg-primary-100 rounded-full opacity-0 group-hover:opacity-50 transition-opacity duration-200"></div>
                    </div>
                    <div>
                      <h3 class="text-lg font-semibold text-gray-800">User Activity</h3>
                      <p class="text-sm text-gray-600">Quizzes Taken (This Week): <span class="font-bold text-primary-600"><?php echo $quizzes_taken; ?></span></p>
                      <p class="text-sm text-gray-600">Average Score: <span class="font-bold text-primary-600"><?php echo $avg_score; ?>%</span></p>
                    </div>
                  </div>
                </div>
                <!-- First Attempt Pass Rate Card -->
                <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 min-h-[120px]">
                  <div class="flex items-center space-x-4">
                    <div class="p-3 bg-primary-50 rounded-full relative group">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="w-6 h-6 text-primary-600 group-hover:text-primary-700 transition-colors duration-200 transform group-hover:scale-110"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                      </svg>
                      <div class="absolute inset-0 bg-primary-100 rounded-full opacity-0 group-hover:opacity-50 transition-opacity duration-200"></div>
                    </div>
                    <div>
                      <h3 class="text-lg font-semibold text-gray-800">First Attempt Pass Rate</h3>
                      <p class="text-sm text-gray-600">Passed 1st Taken: <span class="font-bold text-primary-600"><?php echo $passed_first; ?></span></p>
                      <p class="text-sm text-gray-600">Total 1st Attempts: <span class="font-bold text-primary-600"><?php echo $total_first_attempts; ?></span></p>
                      <p class="text-sm text-gray-600">Pass Rate: <span class="font-bold text-primary-600"><?php echo $first_attempt_pass_percentage; ?>%</span></p>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <!-- Exam Takers Section -->
            <section class="animate-fade-in">
              <div class="bg-white p-6 rounded-xl shadow-lg">
                <div class="flex justify-between items-center mb-4">
                  <h3 class="text-xl font-bold text-dashboard-light">Exam Takers</h3>
                  <a href="user_records.php" class="text-primary-600 hover:text-primary-700 text-sm font-medium">View All</a>
                </div>
                <div class="overflow-x-auto">
                  <table class="w-full text-left border-separate border-spacing-y-2">
                    <thead>
                      <tr class="text-gray-600">
                        <th class="py-3 px-4 font-semibold">Module Name</th>
                        <th class="py-3 px-4 font-semibold">Lesson Name</th>
                        <th class="py-3 px-4 font-semibold">Number of Takers</th>
                        <th class="py-3 px-4 font-semibold">Percentage (Takers/Total Users)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (count($exam_takers) > 0): ?>
                        <?php foreach ($exam_takers as $taker): ?>
                          <tr class="bg-gray-50 rounded-lg hover:bg-primary-50 transition-colors duration-200 animate-fade-in">
                            <td class="py-3 px-4 rounded-l-lg"><?php echo htmlspecialchars($taker['module_title']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($taker['lesson_title']); ?></td>
                            <td class="py-3 px-4"><?php echo $taker['taker_count']; ?></td>
                            <td class="py-3 px-4 rounded-r-lg"><?php echo $taker['percentage']; ?>%</td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="4" class="py-4 px-4 text-center text-gray-500">No exam takers found.</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </section>
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

      // Profile dropdown toggle
      const profile = document.getElementById("profile");
      const profileDropdown = document.getElementById("profile-dropdown");

      profile.addEventListener("click", (e) => {
        e.stopPropagation();
        profileDropdown.classList.toggle("hidden");
      });

      // Close profile dropdown when clicking outside
      document.addEventListener("click", (e) => {
        if (!profile.contains(e.target)) {
          profileDropdown.classList.add("hidden");
        }
      });

      // Notification dropdown toggle with sound
      const notificationBell = document.getElementById("notification-bell");
      const notificationDropdown = document.getElementById("notification-dropdown");
      const notificationSound = new Audio('../assets/sounds/notification.mp3');

      notificationBell.addEventListener("click", (e) => {
        e.stopPropagation();
        notificationDropdown.classList.toggle("hidden");
        if (!notificationDropdown.classList.contains("hidden")) {
          notificationSound.play().catch(error => console.log("Error playing sound:", error));
        }
      });

      // Close notification dropdown when clicking outside
      document.addEventListener("click", (e) => {
        if (!notificationBell.contains(e.target)) {
          notificationDropdown.classList.add("hidden");
        }
      });
    </script>
  </body>
</html>