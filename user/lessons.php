<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "User") {
    header("Location: login.php");
    exit();
}

// Validate module_id
$module_id = filter_input(INPUT_GET, 'module_id', FILTER_VALIDATE_INT);
if (!$module_id) {
    header("Location: modules_list.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$lesson_count = 1;

// Fetch lessons
$lessonStmt = $pdo->prepare("SELECT id, title FROM lessons WHERE module_id = :module_id ORDER BY id");
$lessonStmt->execute([':module_id' => $module_id]);
$lessons = $lessonStmt->fetchAll(PDO::FETCH_ASSOC);
$rowCount = count($lessons);

// Fetch latest quiz results for this module
$latestResultsStmt = $pdo->prepare("
    SELECT qr.lesson_id, qr.isWatched, qr.isPassed, qr.score, qr.totalItems,
           (SELECT COUNT(*) FROM quizzes q WHERE q.lesson_id = qr.lesson_id) as quiz_count
    FROM quiz_results qr
    WHERE qr.user_id = :user_id 
    AND qr.lesson_id IN (SELECT id FROM lessons WHERE module_id = :module_id)
    AND qr.taken_at = (
        SELECT MAX(taken_at)
        FROM quiz_results
        WHERE user_id = qr.user_id AND lesson_id = qr.lesson_id
    )
");
$latestResultsStmt->execute([':user_id' => $user_id, ':module_id' => $module_id]);
$latestResults = $latestResultsStmt->fetchAll(PDO::FETCH_ASSOC);

// Build arrays for watched and completed lessons
$watchedLessons = [];
$completedLessons = [];
foreach ($latestResults as $result) {
    if ($result['isWatched'] == 1) {
        $watchedLessons[$result['lesson_id']] = true;
        // A lesson is completed if it's watched and either has no quiz or the quiz is passed
        if ($result['quiz_count'] == 0 || ($result['isPassed'] == 1)) {
            $completedLessons[$result['lesson_id']] = true;
        }
    }
}

// Determine lesson accessibility (first lesson is always accessible)
$isPreviousLessonCompleted = true;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User - Lessons List</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ["Inter", "sans-serif"] },
                    colors: {
                        primary: { 50: "#f5f3ff", 600: "#4f46e5", 700: "#4338ca" },
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
                    <li><a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        Dashboard
                    </a></li>
                    <li><a href="modules_list.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-600 font-medium transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                        Modules
                    </a></li>
                    <li><a href="results.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                        </svg>
                        View Results
                    </a></li>
                    <li><a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-red-50 hover:text-red-600 font-medium transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                        Logout
                    </a></li>
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
                    <h1 class="text-xl font-semibold text-dashboard">Lessons</h1>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-gray-800">Available Lessons</h2>
                </div>

                <!-- Completed Lesson Message -->
                <div id="completed-message" class="hidden fixed bottom-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-md z-50 transition-opacity duration-300">
                    <p class="text-sm">You have already finished this lesson!</p>
                </div>

                <!-- Locked Lesson Message -->
                <div id="locked-message" class="hidden fixed bottom-4 right-4 bg-gray-100 border border-gray-400 text-gray-700 px-4 py-3 rounded-lg shadow-md z-50 transition-opacity duration-300">
                    <p class="text-sm">Complete the previous lesson to unlock this one!</p>
                </div>

                <!-- Lesson Confirmation Modal -->
                <div id="lesson-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Start Lesson</h3>
                        <p class="text-sm text-gray-600 mb-6">Do you want to start '<span id="lesson-title" class="font-medium"></span>'?</p>
                        <div class="flex justify-end gap-4">
                            <button id="cancel-lesson" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-200 rounded hover:bg-gray-300 transition-colors">Cancel</button>
                            <button id="start-lesson" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded hover:bg-primary-700 transition-colors">Start Lesson</button>
                        </div>
                    </div>
                </div>

                <!-- Loader -->
                <div class="loader-wrapper hidden fixed inset-0 bg-gray-100 bg-opacity-50 flex items-center justify-center z-50">
                    <div class="animate-spin rounded-full h-12 w-12 border-t-4 border-primary-600 border-solid"></div>
                </div>

                <?php if ($rowCount > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($lessons as $index => $lesson): ?>
                            <?php
                            $lesson_id = $lesson['id'];
                            $title = htmlspecialchars($lesson['title']);
                            $isWatched = isset($watchedLessons[$lesson_id]);
                            $isCompleted = isset($completedLessons[$lesson_id]);
                            $isLocked = !$isPreviousLessonCompleted && $lesson_count > 1;

                            // Check if there’s a quiz and the latest result
                            $result = array_filter($latestResults, fn($r) => $r['lesson_id'] == $lesson_id);
                            $hasQuiz = !empty($result) && reset($result)['quiz_count'] > 0;
                            $isFailed = $isWatched && $hasQuiz && !$isCompleted;
                            ?>

                            <?php if ($isCompleted): ?>
                                <div class="watched-lesson-card bg-white rounded-lg shadow-md p-4 flex justify-between items-center cursor-pointer hover:shadow-lg transition-shadow duration-200" onclick="showCompletedMessage()">
                                    <div class="flex items-center gap-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        <div>
                                            <h3 class="text-base font-semibold text-gray-800">Lesson <?= $lesson_count ?> - <?= $title ?></h3>
                                            <p class="text-sm text-gray-500">Completed</p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-medium text-green-600 bg-green-100 rounded">Completed</span>
                                </div>
                            <?php elseif ($isFailed): ?>
                                <a href="lesson.php?lesson_id=<?= $lesson_id ?>" class="failed-lesson-card bg-white rounded-lg shadow-md p-4 flex justify-between items-center hover:shadow-lg transition-shadow duration-200">
                                    <div class="flex items-center gap-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                        <div>
                                            <h3 class="text-base font-semibold text-gray-800">Lesson <?= $lesson_count ?> - <?= $title ?></h3>
                                            <p class="text-sm text-gray-500">Try Again</p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-medium text-red-600 bg-red-100 rounded">Failed</span>
                                </a>
                            <?php elseif ($isLocked): ?>
                                <div class="locked-lesson-card bg-gray-100 rounded-lg shadow-md p-4 flex justify-between items-center cursor-not-allowed opacity-60" onclick="showLockedMessage()">
                                    <div class="flex items-center gap-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                        </svg>
                                        <div>
                                            <h3 class="text-base font-semibold text-gray-800">Lesson <?= $lesson_count ?> - <?= $title ?></h3>
                                            <p class="text-sm text-gray-500">Complete previous lesson to unlock</p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-medium text-gray-600 bg-gray-200 rounded">Locked</span>
                                </div>
                            <?php else: ?>
                                <div class="lesson-card bg-white rounded-lg shadow-md p-4 flex justify-between items-center hover:shadow-lg transition-shadow duration-200" onclick="showLessonModal('lesson.php?lesson_id=<?= $lesson_id ?>', 'Lesson <?= $lesson_count ?> - <?= addslashes($title) ?>')">
                                    <div class="flex items-center gap-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28 0.53v11.38a.75.75 0 0 1-1.28 0.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                                        </svg>
                                        <div>
                                            <h3 class="text-base font-semibold text-gray-800">Lesson <?= $lesson_count ?> - <?= $title ?></h3>
                                            <p class="text-sm text-gray-500">Start Learning</p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-medium text-primary-600 bg-primary-50 rounded">Available</span>
                                </div>
                            <?php endif; ?>
                            <?php
                            // Update for next iteration: a lesson is completed if watched and either no quiz or quiz passed
                            $isPreviousLessonCompleted = $isCompleted;
                            $lesson_count++;
                            ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-lessons text-center py-10">
                        <p class="text-gray-600 text-lg">No lessons uploaded yet for this module.</p>
                    </div>
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

        // Loader
        window.addEventListener("load", () => {
            document.querySelector(".loader-wrapper").classList.add("hidden");
        });

        // Show completed lesson message
        function showCompletedMessage() {
            const message = document.getElementById("completed-message");
            message.classList.remove("hidden");
            setTimeout(() => {
                message.classList.add("hidden");
            }, 3000);
        }

        // Show locked lesson message
        function showLockedMessage() {
            const message = document.getElementById("locked-message");
            message.classList.remove("hidden");
            setTimeout(() => {
                message.classList.add("hidden");
            }, 3000);
        }

        // Show lesson confirmation modal
        let lessonUrl = "";
        function showLessonModal(url, title) {
            lessonUrl = url;
            document.getElementById("lesson-title").textContent = title;
            const modal = document.getElementById("lesson-modal");
            modal.classList.remove("hidden");
        }

        // Handle modal buttons
        document.getElementById("cancel-lesson").addEventListener("click", () => {
            document.getElementById("lesson-modal").classList.add("hidden");
            document.getElementById("lesson-title").textContent = "";
            lessonUrl = "";
        });

        document.getElementById("start-lesson").addEventListener("click", () => {
            if (lessonUrl) {
                window.location.href = lessonUrl;
            }
            document.getElementById("lesson-modal").classList.add("hidden");
            document.getElementById("lesson-title").textContent = "";
            lessonUrl = "";
        });
    </script>
</body>
</html>