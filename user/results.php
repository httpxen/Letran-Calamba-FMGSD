<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to view results.";
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all quiz results for the user
$stmt = $pdo->prepare("
    SELECT qr.*, l.title AS lesson_title, m.title AS module_title
    FROM quiz_results qr
    JOIN lessons l ON qr.lesson_id = l.id
    JOIN modules m ON l.module_id = m.id
    WHERE qr.user_id = :user_id
    ORDER BY qr.taken_at DESC
");
$stmt->execute([':user_id' => $user_id]);
$quiz_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Results</title>
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
                <h2 class="text-xl font-bold text-dashboard"><span class="text-red-600">User</span> Dashboard</h2>
            </div>
            <nav class="mt-6">
                <ul class="space-y-1 px-4">
                    <li>
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="modules_list.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) === 'modules_list.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                            </svg>
                            Modules
                        </a>
                    </li>
                    <li>
                        <a href="results.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) === 'results.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                            </svg>
                            View Results
                        </a>
                    </li>
                    <li>
                        <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-red-50 hover:text-red-600 font-medium transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) === 'logout.php' ? 'bg-red-50 text-red-600' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
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
                    <h1 class="text-xl font-semibold text-dashboard">Assessment Results</h1>
                </div>
            </header>

            <!-- Results Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <?php if (empty($quiz_results)): ?>
                        <h3 class="text-lg font-medium text-gray-600 text-center">No quiz results found.</h3>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full table-auto border-collapse">
                                <thead>
                                    <tr class="bg-gray-100 text-gray-700">
                                        <th class="px-4 py-3 text-left font-semibold">Date Taken</th>
                                        <th class="px-4 py-3 text-left font-semibold">Module</th>
                                        <th class="px-4 py-3 text-left font-semibold">Lesson</th>
                                        <th class="px-4 py-3 text-left font-semibold">Score</th>
                                        <th class="px-4 py-3 text-left font-semibold">Total Items</th>
                                        <th class="px-4 py-3 text-left font-semibold">Percentage</th>
                                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                                        <th class="px-4 py-3 text-left font-semibold">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quiz_results as $result): ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="px-4 py-3"><?php echo date('M d, Y h:i A', strtotime($result['taken_at'])); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($result['module_title'] ?? 'N/A'); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($result['lesson_title'] ?? 'N/A'); ?></td>
                                            <td class="px-4 py-3"><?php echo $result['score'] ?? 'N/A'; ?></td>
                                            <td class="px-4 py-3"><?php echo $result['totalItems'] ?? 'N/A'; ?></td>
                                            <td class="px-4 py-3"><?php echo isset($result['score']) && isset($result['totalItems']) ? number_format(($result['score'] / $result['totalItems']) * 100, 2) . '%' : 'N/A'; ?></td>
                                            <td class="px-4 py-3">
                                                <span class="inline-block px-2 py-1 rounded-full text-sm font-medium <?php echo $result['isPassed'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                                    <?php echo $result['isPassed'] ? 'Passed' : 'Failed'; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <a href="answers.php?result_id=<?php echo urlencode($result['id']); ?>" class="text-primary-600 hover:underline">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Sidebar toggle
        const sidebar = document.getElementById("sidebar");
        const sidebarToggle = document.getElementById("sidebar-toggle");
        const sidebarLinks = document.querySelectorAll("#sidebar a");

        // Toggle sidebar on button click
        sidebarToggle.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
        });

        // Close sidebar when clicking a link on mobile
        sidebarLinks.forEach(link => {
            link.addEventListener("click", () => {
                if (window.innerWidth < 768) {
                    sidebar.classList.add("-translate-x-full");
                }
            });
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener("click", (e) => {
            if (
                !sidebar.contains(e.target) &&
                !sidebarToggle.contains(e.target) &&
                !sidebar.classList.contains("-translate-x-full") &&
                window.innerWidth < 768
            ) {
                sidebar.classList.add("-translate-x-full");
            }
        });
    </script>
</body>
</html>