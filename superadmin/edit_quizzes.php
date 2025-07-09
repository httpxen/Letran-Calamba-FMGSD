<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "SuperAdmin") {
  header("Location: login.php");
  exit();
}

$lesson_id = $_GET['lesson_id'];
$lessonStmt = $pdo->prepare("SELECT l.*, m.id as module_id, m.title as module_title FROM lessons l JOIN modules m ON l.module_id = m.id WHERE l.id = :lesson_id");
$lessonStmt->execute([':lesson_id' => $lesson_id]);
$lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC);

$quizzesStmt = $pdo->prepare("SELECT * FROM quizzes WHERE lesson_id = :lesson_id ORDER BY id DESC");
$quizzesStmt->execute([':lesson_id' => $lesson_id]);
$quizzes = $quizzesStmt->fetchAll(PDO::FETCH_ASSOC);

$itemNumber = 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quizzes for <?= htmlspecialchars($lesson['title'] ?? '') ?></title>
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
          boxShadow: {
            'soft': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.05)',
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
    <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-white border-r shadow-soft transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 md:static md:shadow-none">
      <div class="flex items-center space-x-3 p-6 border-b">
        <img src="../assets/images/favicon.ico" alt="Logo" class="w-10 h-10 rounded-md">
        <h2 class="text-xl font-bold text-dashboard"><span class="text-red-600">SuperAdmin</span> Dashboard</h2>
      </div>
      <nav class="mt-6">
        <ul class="space-y-1 px-4">
          <li>
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-colors duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
              </svg>
              Dashboard
            </a>
          </li>
          <li>
            <a href="user_approvals.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-colors duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0Z" />
              </svg>
              User Approvals
            </a>
          </li>
          <li>
            <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-colors duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
              </svg>
              Users
            </a>
          </li>
          <li>
            <a href="user_records.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-colors duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
              </svg>
              User Records
            </a>
          </li>
          <li>
            <a href="module_list.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-600 font-medium transition-colors duration-200">
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
            <a href="account_management.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-colors duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0012 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 116 0Zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0Zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0Z" />
              </svg>
              Account Management
            </a>
          </li>
          <li>
            <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-red-50 hover:text-red-600 font-medium transition-colors duration-200">
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
      <header class="bg-white shadow-soft flex justify-between items-center px-6 py-4">
        <div class="flex items-center space-x-4">
          <button id="sidebar-toggle" class="md:hidden text-gray-600 hover:text-primary-600 focus:outline-none transition-all duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
          </button>
          <h1 class="text-2xl font-semibold text-dashboard">Quizzes: <?= htmlspecialchars($lesson['title'] ?? '') ?></h1>
        </div>
        <div class="flex items-center space-x-4">
          <a href="add_quiz.php?lesson_id=<?= htmlspecialchars($lesson_id) ?>" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg hover:from-primary-700 hover:to-primary-800 font-medium transition-all duration-200 ease-in-out transform hover:scale-105">Add New Quiz</a>
        </div>
      </header>

      <!-- Main Content -->
      <main class="flex-1 p-6 overflow-y-auto">
        <!-- Breadcrumbs -->
        <nav class="mb-6">
          <ol class="flex items-center space-x-2 text-sm text-gray-600">
            <li>
              <a href="module_list.php" class="hover:text-primary-600 transition-all duration-200">Modules</a>
            </li>
            <li><span class="text-gray-400">/</span></li>
            <li>
              <a href="lessons.php?module_id=<?= htmlspecialchars($lesson['module_id']) ?>" class="hover:text-primary-600 transition-all duration-200"><?= htmlspecialchars($lesson['module_title']) ?></a>
            </li>
            <li><span class="text-gray-400">/</span></li>
            <li class="text-gray-900 font-medium">Quizzes</li>
          </ol>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Quiz List -->
          <div class="lg:col-span-2 space-y-6">
            <?php if(isset($_SESSION['success_message'])) : ?>
              <p class="text-green-500 text-sm font-medium bg-green-50 p-3 rounded-lg"><?= htmlspecialchars($_SESSION['success_message']) ?></p>
              <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (!empty($quizzes)) : ?>
              <?php foreach ($quizzes as $quiz) : ?>
                <div class="bg-white rounded-xl shadow-soft p-6 hover:shadow-md transition-all duration-200 ease-in-out transform hover:scale-[1.01] <?php echo $quiz['status'] === 'inactive' ? 'opacity-50' : ''; ?>">
                  <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center space-x-2">
                      <p class="text-gray-800 font-medium text-base"><?= $itemNumber ?>. <?= htmlspecialchars($quiz['question']) ?></p>
                      <span class="text-xs font-medium px-2 py-1 rounded-full <?php echo $quiz['status'] === 'active' ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-red-600'; ?>">
                        <?= htmlspecialchars(ucfirst($quiz['status'])) ?>
                      </span>
                    </div>
                    <div class="flex items-center space-x-2">
                      <a href="update_quiz.php?quiz_id=<?= $quiz['id'] ?>&lesson_id=<?= $lesson_id ?>" class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700 font-medium transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                        Edit
                      </a>
                    </div>
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4">
                    <p class="text-gray-600 text-sm">A. <?= htmlspecialchars($quiz['option_a']) ?></p>
                    <p class="text-gray-600 text-sm">B. <?= htmlspecialchars($quiz['option_b']) ?></p>
                    <p class="text-gray-600 text-sm">C. <?= htmlspecialchars($quiz['option_c']) ?></p>
                    <p class="text-gray-600 text-sm">D. <?= htmlspecialchars($quiz['option_d']) ?></p>
                  </div>
                  <p class="text-gray-700 font-medium text-sm">Correct Answer: <span class="text-primary-600"><?= htmlspecialchars($quiz['correct_option']) ?></span></p>
                  <?php $itemNumber++ ?>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="bg-white rounded-xl shadow-soft p-6 text-center">
                <p class="text-gray-600 text-base">No quizzes available for this lesson.</p>
                <a href="add_quiz.php?lesson_id=<?= htmlspecialchars($lesson_id) ?>" class="mt-4 inline-flex items-center gap-2 text-primary-600 hover:text-primary-700 font-medium transition-all duration-200">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                  </svg>
                  Add a Quiz
                </a>
              </div>
            <?php endif; ?>
          </div>

          <!-- Lesson Details and Stats -->
          <div class="space-y-6">
            <!-- Lesson Details -->
            <div class="bg-white p-6 rounded-xl shadow-soft">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Lesson Details</h3>
              <div class="space-y-3">
                <div class="flex justify-between items-center">
                  <span class="text-sm font-medium text-gray-600">Lesson</span>
                  <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($lesson['title']) ?></span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-sm font-medium text-gray-600">Module</span>
                  <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($lesson['module_title']) ?></span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-sm font-medium text-gray-600">Quizzes</span>
                  <span class="text-sm font-medium text-gray-900"><?= count($quizzes) ?></span>
                </div>
              </div>
              <a href="lesson_edit.php?module_id=<?= htmlspecialchars($lesson['module_id']) ?>&lesson_id=<?= htmlspecialchars($lesson_id) ?>" class="mt-4 inline-flex items-center gap-2 text-primary-600 hover:text-primary-700 font-medium transition-all duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                </svg>
                Edit Lesson
              </a>
            </div>

            <!-- Actions -->
            <div class="bg-white p-6 rounded-xl shadow-soft">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
              <div class="space-y-3">
                <a href="add_quiz.php?lesson_id=<?= htmlspecialchars($lesson_id) ?>" class="flex items-center gap-2 px-4 py-2 bg-primary-50 text-primary-600 rounded-lg hover:bg-primary-100 font-medium transition-all duration-200">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                  </svg>
                  Add New Quiz
                </a>
                <a href="lessons.php?module_id=<?= htmlspecialchars($lesson['module_id']) ?>" class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 font-medium transition-all duration-200">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                  </svg>
                  Back to Lessons
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
  </script>
</body>
</html>