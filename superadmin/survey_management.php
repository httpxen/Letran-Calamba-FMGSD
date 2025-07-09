<?php
session_start();
require_once '../db/db.php';

// Check if the user is logged in and has the SuperAdmin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "SuperAdmin") {
    header("Location: ../login.php");
    exit();
}

// Set the current page
$current_page = 'survey_management.php';

// Get user info
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

// Handle survey creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_survey'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];

    if (!empty($title)) {
        $query = "INSERT INTO surveys (title, description, status) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $title, $description, $status);
        if ($stmt->execute()) {
            $success = "Survey created successfully!";
        } else {
            $error = "Failed to create survey: " . $conn->error;
        }
    } else {
        $error = "Title is required.";
    }
}

// Handle survey update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_survey'])) {
    $survey_id = $_POST['survey_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];

    if (!empty($title)) {
        $query = "UPDATE surveys SET title = ?, description = ?, status = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssi", $title, $description, $status, $survey_id);
        if ($stmt->execute()) {
            $success = "Survey updated successfully!";
        } else {
            $error = "Failed to update survey: " . $conn->error;
        }
    } else {
        $error = "Title is required.";
    }
}

// Handle survey deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_survey'])) {
    $survey_id = $_POST['delete_survey_id'];
    $query = "DELETE FROM surveys WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $survey_id);
    if ($stmt->execute()) {
        $success = "Survey deleted successfully!";
    } else {
        $error = "Failed to delete survey: " . $conn->error;
    }
}

// Fetch all surveys
$surveys_query = "SELECT * FROM surveys ORDER BY created_at DESC";
$surveys_result = $conn->query($surveys_query);
if (!$surveys_result) {
    die("Query failed: " . $conn->error);
}

// Get filter parameters
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Survey Management</title>
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
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <style>
      .fade {
        transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
      }
      .hidden {
        opacity: 0;
        transform: scale(0.95);
        pointer-events: none;
      }
      .visible {
        opacity: 1;
        transform: scale(1);
        pointer-events: auto;
      }
      .modal-content {
        backdrop-filter: blur(12px);
        background: rgba(255, 255, 255, 0.9);
      }
      .truncate {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      .tooltip {
        position: relative;
      }
      .tooltip:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #1f2937;
        color: white;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 12px;
        white-space: normal;
        width: 200px;
        z-index: 10;
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
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
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
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0l3-3m0 0-3-3m3 3H9" />
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
        <header class="bg-white shadow-sm flex justify-between items-center px-6 py-4">
          <div class="flex items-center space-x-4">
            <button id="sidebar-toggle" class="md:hidden text-gray-600 hover:text-primary-600 focus:outline-none">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
              </svg>
            </button>
            <h1 class="text-xl font-semibold text-dashboard">Survey Management</h1>
            <p class="text-gray-600">Create and manage client satisfaction surveys.</p>
          </div>
          <div class="flex items-center space-x-4">
            <div class="relative">
              <input
                type="text"
                placeholder="Search..."
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
                  class="w-10 h-10 rounded-full border border-gray-200 shadow-sm"
                  alt="Profile Picture"
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
                    class="w-10 h-10 rounded-full"
                    alt="Profile Picture"
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
        <main class="flex-1 p-6 overflow-y-auto">
          <!-- Notifications -->
          <?php if (isset($success)): ?>
            <div class="mb-6 p-4 bg-green-100 text-green-700 rounded-lg"><?php echo $success; ?></div>
          <?php endif; ?>
          <?php if (isset($error)): ?>
            <div class="mb-6 p-4 bg-red-100 text-red-700 rounded-lg"><?php echo $error; ?></div>
          <?php endif; ?>

          <!-- Create Survey Button -->
          <div class="mb-6">
            <button id="create-survey-btn" class="px-5 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all duration-200 font-medium shadow-sm">Create New Survey</button>
          </div>

          <!-- Surveys Table -->
          <div class="bg-white shadow-lg rounded-xl overflow-hidden mb-6">
            <div class="p-6 border-b border-gray-100">
              <h2 class="text-xl font-semibold text-dashboard">Surveys</h2>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php while ($survey = $surveys_result->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition-colors duration-150">
                      <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($survey['title']); ?></td>
                      <td class="px-6 py-4 text-sm text-gray-600 max-w-xs">
                        <span class="tooltip truncate block" data-tooltip="<?php echo htmlspecialchars($survey['description'] ?? 'No description'); ?>">
                          <?php echo htmlspecialchars($survey['description'] ?? 'No description'); ?>
                        </span>
                      </td>
                      <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $survey['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                          <?php echo ucfirst($survey['status']); ?>
                        </span>
                      </td>
                      <td class="px-6 py-4 text-sm text-gray-600"> <?php echo date('M d, Y h:i A', strtotime($survey['created_at'])); ?></td>
                      <td class="px-6 py-4 space-x-3">
                        <button class="edit-survey-btn text-primary-600 hover:text-primary-700 font-medium transition-colors duration-200" data-id="<?php echo $survey['id']; ?>" data-title="<?php echo htmlspecialchars($survey['title']); ?>" data-description="<?php echo htmlspecialchars($survey['description'] ?? ''); ?>" data-status="<?php echo $survey['status']; ?>">Edit</button>
                        <button class="delete-survey-btn text-red-600 hover:text-red-700 font-medium transition-colors duration-200" data-id="<?php echo $survey['id']; ?>" data-title="<?php echo htmlspecialchars($survey['title']); ?>" data-description="<?php echo htmlspecialchars($survey['description'] ?? 'No description'); ?>">Delete</button>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Survey Responses Section -->
          <div class="bg-white shadow-lg rounded-xl overflow-hidden mb-6">
            <div class="p-6 border-b border-gray-100">
              <h2 class="text-xl font-semibold text-dashboard">Survey Responses</h2>
              <p class="text-sm text-gray-600 mt-1">View and analyze responses for each survey.</p>
            </div>
            <!-- Filter Form -->
            <div class="p-6 border-b border-gray-100">
              <form id="filter-form" method="GET" class="flex flex-col sm:flex-row sm:items-end gap-4">
                <div class="flex-1">
                  <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">Sort By Date</label>
                  <select name="sort_order" id="sort_order" class="w-full rounded-lg border border-gray-200 p-3 bg-white/50 focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all duration-200">
                    <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>Oldest First</option>
                  </select>
                </div>
                <div class="flex-1">
                  <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                  <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="w-full rounded-lg border border-gray-200 p-3 bg-white/50 focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all duration-200">
                </div>
                <div class="flex-1">
                  <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                  <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="w-full rounded-lg border border-gray-200 p-3 bg-white/50 focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all duration-200">
                </div>
                <div class="flex gap-3">
                  <button type="submit" class="px-5 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all duration-200 font-medium">Apply Filters</button>
                  <button type="button" id="reset-filters" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all duration-200 font-medium">Reset</button>
                </div>
              </form>
            </div>
            <div class="p-6">
              <?php
              // Fetch surveys to group responses
              $surveys_query = "SELECT * FROM surveys ORDER BY created_at DESC";
              $surveys_result = $conn->query($surveys_query);
              if (!$surveys_result) {
                die("Query failed: " . $conn->error);
              }

              while ($survey = $surveys_result->fetch_assoc()):
                $survey_id = $survey['id'];
                // Build responses query with filters
                $responses_query = "SELECT sr.id, sr.user_id, sr.rating, sr.feedback, sr.submitted_at, u.fullname
                                  FROM survey_responses sr
                                  JOIN users u ON sr.user_id = u.id
                                  WHERE sr.survey_id = ?";
                $params = [$survey_id];
                $types = "i";

                // Add date range filter
                if ($start_date && $end_date) {
                    $responses_query .= " AND sr.submitted_at BETWEEN ? AND ?";
                    $params[] = $start_date . " 00:00:00";
                    $params[] = $end_date . " 23:59:59";
                    $types .= "ss";
                } elseif ($start_date) {
                    $responses_query .= " AND sr.submitted_at >= ?";
                    $params[] = $start_date . " 00:00:00";
                    $types .= "s";
                } elseif ($end_date) {
                    $responses_query .= " AND sr.submitted_at <= ?";
                    $params[] = $end_date . " 23:59:59";
                    $types .= "s";
                }

                // Add sort order
                $responses_query .= " ORDER BY sr.submitted_at " . ($sort_order === 'asc' ? 'ASC' : 'DESC');

                // Prepare and execute query
                $stmt = $conn->prepare($responses_query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $responses_result = $stmt->get_result();

                // Calculate summary statistics
                $total_responses = $responses_result->num_rows;
                $total_rating = 0;
                $responses_result->data_seek(0); // Reset result pointer
                while ($row = $responses_result->fetch_assoc()) {
                  $total_rating += $row['rating'];
                }
                $average_rating = $total_responses > 0 ? round($total_rating / $total_responses, 1) : 0;
                $responses_result->data_seek(0); // Reset result pointer for display
              ?>
                <div class="border border-gray-200 rounded-lg mb-4">
                  <!-- Survey Header (Accordion Toggle) -->
                  <button class="w-full flex justify-between items-center px-5 py-4 bg-gray-50 hover:bg-gray-100 transition-colors duration-200 accordion-toggle" data-target="responses-<?php echo $survey_id; ?>">
                    <div class="flex items-center space-x-4">
                      <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($survey['title']); ?></h3>
                      <span class="text-sm text-gray-500">(<?php echo $total_responses; ?> responses)</span>
                    </div>
                    <div class="flex items-center space-x-4">
                      <span class="text-sm font-medium text-gray-600">Avg. Rating: <?php echo $average_rating; ?>/5</span>
                      <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $survey['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                        <?php echo ucfirst($survey['status']); ?>
                      </span>
                      <svg class="w-5 h-5 text-gray-500 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                      </svg>
                    </div>
                  </button>
                  <!-- Survey Description -->
                  <div class="px-5 py-3 bg-gray-50 border-t border-gray-100">
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($survey['description'] ?? 'No description'); ?></p>
                  </div>
                  <!-- Responses (Collapsible Content) -->
                  <div id="responses-<?php echo $survey_id; ?>" class="hidden">
                    <?php if ($total_responses > 0): ?>
                      <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                          <thead class="bg-gray-50">
                            <tr>
                              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Feedback</th>
                              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted At</th>
                            </tr>
                          </thead>
                          <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($response = $responses_result->fetch_assoc()): ?>
                              <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($response['fullname']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo $response['rating']; ?>/5</td>
                                <td class="px-6 py-4 text-sm text-gray-600 max-w-md">
                                  <?php
                                  $feedback = htmlspecialchars($response['feedback'] ?? 'No feedback');
                                  if (strlen($feedback) > 100):
                                  ?>
                                    <span class="truncate block"><?php echo substr($feedback, 0, 100); ?>...</span>
                                    <button class="text-primary-600 hover:text-primary-700 text-sm font-medium mt-1 view-feedback-btn" data-feedback="<?php echo $feedback; ?>">View Full</button>
                                  <?php else: ?>
                                    <?php echo $feedback; ?>
                                  <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo date('M d, Y h:i A', strtotime($response['submitted_at'])); ?></td>
                              </tr>
                            <?php endwhile; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php else: ?>
                      <div class="px-6 py-4 text-sm text-gray-500 text-center">No responses for this survey yet.</div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endwhile; ?>
            </div>
          </div>

          <!-- Create/Edit Survey Modal -->
          <div id="survey-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-50 fade">
            <div class="modal-content rounded-2xl p-8 w-full max-w-lg shadow-2xl">
              <div class="flex justify-between items-center mb-6">
                <h2 id="modal-title" class="text-2xl font-bold text-gray-800"></h2>
                <button id="cancel-btn" class="text-gray-500 hover:text-gray-700">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <form id="survey-form" method="POST">
                <input type="hidden" name="survey_id" id="survey_id">
                <div class="mb-6">
                  <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                  <input type="text" name="title" id="title" class="w-full rounded-lg border border-gray-200 p-3 bg-white/50 focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all duration-200" required>
                </div>
                <div class="mb-6">
                  <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                  <textarea name="description" id="description" class="w-full rounded-lg border border-gray-200 p-3 bg-white/50 focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all duration-200" rows="5" placeholder="Enter survey description..."></textarea>
                </div>
                <div class="mb-6">
                  <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                  <select name="status" id="status" class="w-full rounded-lg border border-gray-200 p-3 bg-white/50 focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all duration-200">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
                <div class="flex justify-end gap-3">
                  <button type="button" id="cancel-btn" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200 font-medium">Cancel</button>
                  <button type="button" id="confirm-btn" class="px-5 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200 font-medium">Confirm</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Survey Confirmation Modal -->
          <div id="survey-confirm-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-50 fade">
            <div class="modal-content rounded-2xl p-8 w-full max-w-md shadow-2xl">
              <div class="flex justify-between items-center mb-6">
                <h2 id="confirm-modal-title" class="text-2xl font-bold text-gray-800"></h2>
                <button id="confirm-cancel-btn" class="text-gray-500 hover:text-gray-700">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <div class="mb-6">
                <p class="text-gray-700 text-sm mb-4" id="confirm-modal-text"></p>
                <div class="bg-gray-50 p-4 rounded-lg">
                  <p class="text-sm font-medium text-gray-900"><span class="font-semibold">Title:</span> <span id="confirm-survey-title"></span></p>
                  <p class="text-sm text-gray-600 mt-2"><span class="font-semibold">Description:</span> <span id="confirm-survey-description"></span></p>
                </div>
              </div>
              <form id="confirm-form" method="POST">
                <input type="hidden" name="survey_id" id="confirm_survey_id">
                <input type="hidden" name="title" id="confirm_title">
                <input type="hidden" name="description" id="confirm_description">
                <input type="hidden" name="status" id="confirm_status">
                <input type="hidden" name="action" id="confirm_action">
                <div class="flex justify-end gap-3">
                  <button type="button" id="confirm-cancel-btn-footer" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200 font-medium">Cancel</button>
                  <button type="submit" id="confirm-submit-btn" name="" class="px-5 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200 font-medium"></button>
                </div>
              </form>
            </div>
          </div>

          <!-- Delete Confirmation Modal -->
          <div id="delete-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-50 fade">
            <div class="modal-content rounded-2xl p-8 w-full max-w-md shadow-2xl">
              <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Confirm Deletion</h2>
                <button id="delete-cancel-btn" class="text-gray-500 hover:text-gray-700">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <div class="mb-6">
                <p class="text-gray-700 text-sm mb-4">Are you sure you want to delete this survey?</p>
                <div class="bg-gray-50 p-4 rounded-lg">
                  <p class="text-sm font-medium text-gray-900"><span class="font-semibold">Title:</span> <span id="delete-survey-title"></span></p>
                  <p class="text-sm text-gray-600 mt-2"><span class="font-semibold">Description:</span> <span id="delete-survey-description"></span></p>
                </div>
              </div>
              <form id="delete-form" method="POST">
                <input type="hidden" name="delete_survey_id" id="delete_survey_id">
                <div class="flex justify-end gap-3">
                  <button type="button" id="delete-cancel-btn" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200 font-medium">Cancel</button>
                  <button type="submit" name="delete_survey" class="px-5 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200 font-medium">Delete</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Feedback Modal (for long feedback) -->
          <div id="feedback-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-50 fade">
            <div class="modal-content rounded-2xl p-8 w-full max-w-md shadow-2xl">
              <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Full Feedback</h2>
                <button id="feedback-close-btn" class="text-gray-500 hover:text-gray-700">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <div class="mb-6">
                <p id="feedback-content" class="text-sm text-gray-700"></p>
              </div>
              <div class="flex justify-end">
                <button id="feedback-close-btn-footer" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200 font-medium">Close</button>
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

      // Close sidebar when clicking outside
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

      // Close dropdown when clicking outside
      document.addEventListener("click", (e) => {
        if (!profile.contains(e.target)) {
          profileDropdown.classList.add("hidden");
        }
      });

      // Create/Edit Survey Modal handling
      const surveyModal = document.getElementById("survey-modal");
      const createSurveyBtn = document.getElementById("create-survey-btn");
      const surveyCancelBtn = document.querySelectorAll("#cancel-btn");
      const surveyForm = document.getElementById("survey-form");
      const modalTitle = document.getElementById("modal-title");
      const confirmBtn = document.getElementById("confirm-btn");

      createSurveyBtn.addEventListener("click", () => {
        modalTitle.textContent = "Create New Survey";
        surveyForm.reset();
        document.getElementById("survey_id").value = "";
        surveyModal.classList.remove("hidden");
        surveyModal.classList.add("visible");
      });

      surveyCancelBtn.forEach(btn => {
        btn.addEventListener("click", () => {
          surveyModal.classList.remove("visible");
          surveyModal.classList.add("hidden");
        });
      });

      // Survey Confirmation Modal handling
      const confirmModal = document.getElementById("survey-confirm-modal");
      const confirmCancelBtn = document.querySelectorAll("#confirm-cancel-btn, #confirm-cancel-btn-footer");
      const confirmForm = document.getElementById("confirm-form");
      const confirmSubmitBtn = document.getElementById("confirm-submit-btn");

      confirmBtn.addEventListener("click", () => {
        const surveyId = document.getElementById("survey_id").value;
        const title = document.getElementById("title").value.trim();
        const description = document.getElementById("description").value.trim();
        const status = document.getElementById("status").value;
        
        if (!title) {
          alert("Title is required.");
          return;
        }

        document.getElementById("confirm_survey_id").value = surveyId;
        document.getElementById("confirm_title").value = title;
        document.getElementById("confirm_description").value = description;
        document.getElementById("confirm_status").value = status;
        document.getElementById("confirm-survey-title").textContent = title;
        document.getElementById("confirm-survey-description").textContent = description || "No description";
        document.getElementById("confirm-modal-title").textContent = surveyId ? "Confirm Update Survey" : "Confirm Create Survey";
        document.getElementById("confirm-modal-text").textContent = surveyId ? "Are you sure you want to update this survey?" : "Are you sure you want to create this survey?";
        document.getElementById("confirm_action").value = surveyId ? "update_survey" : "create_survey";
        confirmSubmitBtn.name = surveyId ? "update_survey" : "create_survey";
        confirmSubmitBtn.textContent = surveyId ? "Update" : "Create";
        
        surveyModal.classList.remove("visible");
        surveyModal.classList.add("hidden");
        confirmModal.classList.remove("hidden");
        confirmModal.classList.add("visible");
      });

      confirmCancelBtn.forEach(btn => {
        btn.addEventListener("click", () => {
          confirmModal.classList.remove("visible");
          confirmModal.classList.add("hidden");
        });
      });

      // Edit survey
      document.querySelectorAll(".edit-survey-btn").forEach(btn => {
        btn.addEventListener("click", () => {
          modalTitle.textContent = "Edit Survey";
          document.getElementById("survey_id").value = btn.dataset.id;
          document.getElementById("title").value = btn.dataset.title;
          document.getElementById("description").value = btn.dataset.description;
          document.getElementById("status").value = btn.dataset.status;
          surveyModal.classList.remove("hidden");
          surveyModal.classList.add("visible");
        });
      });

      // Delete Survey Modal handling
      const deleteModal = document.getElementById("delete-modal");
      const deleteCancelBtn = document.querySelectorAll("#delete-cancel-btn");
      const deleteForm = document.getElementById("delete-form");

      document.querySelectorAll(".delete-survey-btn").forEach(btn => {
        btn.addEventListener("click", () => {
          document.getElementById("delete_survey_id").value = btn.dataset.id;
          document.getElementById("delete-survey-title").textContent = btn.dataset.title;
          document.getElementById("delete-survey-description").textContent = btn.dataset.description;
          deleteModal.classList.remove("hidden");
          deleteModal.classList.add("visible");
        });
      });

      deleteCancelBtn.forEach(btn => {
        btn.addEventListener("click", () => {
          deleteModal.classList.remove("visible");
          deleteModal.classList.add("hidden");
        });
      });

      // Accordion toggle for survey responses
      document.querySelectorAll('.accordion-toggle').forEach(button => {
        button.addEventListener('click', () => {
          const targetId = button.getAttribute('data-target');
          const target = document.getElementById(targetId);
          const isOpen = !target.classList.contains('hidden');
          
          // Close all other accordions
          document.querySelectorAll('.accordion-toggle').forEach(otherButton => {
            const otherTargetId = otherButton.getAttribute('data-target');
            const otherTarget = document.getElementById(otherTargetId);
            if (otherTargetId !== targetId) {
              otherTarget.classList.add('hidden');
              otherButton.querySelector('svg').classList.remove('rotate-180');
            }
          });

          // Toggle current accordion
          target.classList.toggle('hidden');
          button.querySelector('svg').classList.toggle('rotate-180');
        });
      });

      // Feedback modal handling
      const feedbackModal = document.getElementById('feedback-modal');
      const feedbackContent = document.getElementById('feedback-content');
      const feedbackCloseBtns = document.querySelectorAll('#feedback-close-btn, #feedback-close-btn-footer');

      document.querySelectorAll('.view-feedback-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          feedbackContent.textContent = btn.dataset.feedback;
          feedbackModal.classList.remove('hidden');
          feedbackModal.classList.add('visible');
        });
      });

      feedbackCloseBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          feedbackModal.classList.remove('visible');
          feedbackModal.classList.add('hidden');
        });
      });

      // Close feedback modal when clicking outside
      document.addEventListener('click', (e) => {
        if (e.target === feedbackModal) {
          feedbackModal.classList.remove('visible');
          feedbackModal.classList.add('hidden');
        }
      });

      // Reset filters
      document.getElementById('reset-filters').addEventListener('click', () => {
        document.getElementById('filter-form').reset();
        window.location.href = 'survey_management.php';
      });
    </script>
  </body>
</html>
<?php
$conn->close();
?>