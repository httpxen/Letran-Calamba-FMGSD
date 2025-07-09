<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "Admin") {
    header("Location: ../login.php");
    exit();
}

$lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

// Fetch lesson
$lessonStmt = $pdo->prepare("SELECT l.*, m.title AS module_title FROM lessons l JOIN modules m ON l.module_id = m.id WHERE l.id = :lesson_id");
$lessonStmt->execute([':lesson_id' => $lesson_id]);
$lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    $_SESSION['error_message'] = "Lesson not found.";
    header("Location: edit_quizzes.php?lesson_id=$lesson_id");
    exit();
}

// Fetch quiz
$quizStmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = :quiz_id");
$quizStmt->execute([':quiz_id' => $quiz_id]);
$quiz = $quizStmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    $_SESSION['error_message'] = "Quiz not found.";
    header("Location: edit_quizzes.php?lesson_id=$lesson_id");
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = filter_input(INPUT_POST, 'question', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $option_a = filter_input(INPUT_POST, 'option_a', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $option_b = filter_input(INPUT_POST, 'option_b', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $option_c = filter_input(INPUT_POST, 'option_c', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $option_d = filter_input(INPUT_POST, 'option_d', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $correct_option = filter_input(INPUT_POST, 'correct_option', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Validate inputs
    if (empty($question) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d) || empty($correct_option) || empty($status)) {
        $errors['required'] = 'All fields are required.';
    } elseif (!in_array($correct_option, ['A', 'B', 'C', 'D'])) {
        $errors['correct_option'] = 'Invalid correct option selected.';
    } elseif (!in_array($status, ['active', 'inactive'])) {
        $errors['status'] = 'Invalid status selected.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE quizzes 
                SET question = :question,
                    option_a = :option_a,
                    option_b = :option_b,
                    option_c = :option_c,
                    option_d = :option_d,
                    correct_option = :correct_option,
                    status = :status
                WHERE id = :quiz_id
            ");
            $updated = $stmt->execute([
                ':question' => $question,
                ':option_a' => $option_a,
                ':option_b' => $option_b,
                ':option_c' => $option_c,
                ':option_d' => $option_d,
                ':correct_option' => $correct_option,
                ':status' => $status,
                ':quiz_id' => $quiz_id,
            ]);

            // Debug: Log the update attempt
            error_log("Update Quiz ID: $quiz_id, Status: $status, Rows Affected: " . $stmt->rowCount());

            if ($updated && $stmt->rowCount() > 0) {
                $_SESSION['success_message'] = 'Quiz updated successfully.';
                header("Location: edit_quizzes.php?lesson_id={$lesson_id}");
                exit();
            } else {
                $errors['database'] = 'Failed to update quiz. No changes made.';
            }
        } catch (PDOException $e) {
            $errors['database'] = 'Database error: ' . $e->getMessage();
            error_log("Quiz Update Error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Quiz - <?= htmlspecialchars($lesson['title'] ?? '') ?></title>
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
<body class="bg-gray-50 text-gray-900 font-sans antialiased min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-white border-r shadow-soft transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 md:static md:shadow-none">
            <div class="flex items-center space-x-3 p-6 border-b">
                <img src="../assets/images/favicon.ico" alt="Logo" class="w-10 h-10 rounded-md">
                <h2 class="text-xl font-bold text-dashboard"><span class="text-red-600">Admin</span> Dashboard</h2>
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
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
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
                    <button id="sidebar-toggle" class="md:hidden text-gray-600 hover:text-primary-600 focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <h1 class="text-xl font-semibold text-dashboard">Update Quiz - <?= htmlspecialchars($lesson['title'] ?? '') ?></h1>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <div class="max-w-7xl mx-auto">
                    <?php if (isset($errors['database'])) : ?>
                        <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-xl"><?= htmlspecialchars($errors['database']); ?></div>
                    <?php elseif (isset($errors['required'])) : ?>
                        <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-xl"><?= htmlspecialchars($errors['required']); ?></div>
                    <?php elseif (isset($errors['correct_option'])) : ?>
                        <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-xl"><?= htmlspecialchars($errors['correct_option']); ?></div>
                    <?php elseif (isset($errors['status'])) : ?>
                        <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-xl"><?= htmlspecialchars($errors['status']); ?></div>
                    <?php elseif (isset($_SESSION['success_message'])) : ?>
                        <div class="mb-6 p-4 bg-green-50 text-green-600 rounded-xl"><?= htmlspecialchars($_SESSION['success_message']); ?></div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Form Section -->
                        <div class="bg-white rounded-2xl shadow-soft p-8">
                            <h2 class="text-2xl font-bold text-dashboard mb-6">Update Quiz Question</h2>
                            <form id="quiz-form" method="POST" class="space-y-6">
                                <input type="hidden" name="quiz_id" value="<?= htmlspecialchars($quiz_id) ?>">
                                <div>
                                    <label for="question" class="block text-sm font-medium text-gray-700 mb-1">Question *</label>
                                    <textarea id="question" name="question" placeholder="Enter question text" rows="4" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200"><?php echo htmlspecialchars($_POST['question'] ?? $quiz['question'] ?? ''); ?></textarea>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="option_a" class="block text-sm font-medium text-gray-700 mb-1">Option A *</label>
                                        <input type="text" id="option_a" name="option_a" placeholder="Option A" value="<?php echo htmlspecialchars($_POST['option_a'] ?? $quiz['option_a'] ?? ''); ?>" class="option-input w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200 <?php echo ($quiz['correct_option'] === 'A') ? 'border-green-500 bg-green-50' : ''; ?>">
                                    </div>
                                    <div>
                                        <label for="option_b" class="block text-sm font-medium text-gray-700 mb-1">Option B *</label>
                                        <input type="text" id="option_b" name="option_b" placeholder="Option B" value="<?php echo htmlspecialchars($_POST['option_b'] ?? $quiz['option_b'] ?? ''); ?>" class="option-input w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200 <?php echo ($quiz['correct_option'] === 'B') ? 'border-green-500 bg-green-50' : ''; ?>">
                                    </div>
                                    <div>
                                        <label for="option_c" class="block text-sm font-medium text-gray-700 mb-1">Option C *</label>
                                        <input type="text" id="option_c" name="option_c" placeholder="Option C" value="<?php echo htmlspecialchars($_POST['option_c'] ?? $quiz['option_c'] ?? ''); ?>" class="option-input w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200 <?php echo ($quiz['correct_option'] === 'C') ? 'border-green-500 bg-green-50' : ''; ?>">
                                    </div>
                                    <div>
                                        <label for="option_d" class="block text-sm font-medium text-gray-700 mb-1">Option D *</label>
                                        <input type="text" id="option_d" name="option_d" placeholder="Option D" value="<?php echo htmlspecialchars($_POST['option_d'] ?? $quiz['option_d'] ?? ''); ?>" class="option-input w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200 <?php echo ($quiz['correct_option'] === 'D') ? 'border-green-500 bg-green-50' : ''; ?>">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="correct_option" class="block text-sm font-medium text-gray-700 mb-1">Correct Answer *</label>
                                        <select id="correct_option" name="correct_option" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200">
                                            <option value="" disabled <?php echo empty($quiz['correct_option']) ? 'selected' : ''; ?>>Choose correct answer</option>
                                            <option value="A" <?php echo ($quiz['correct_option'] === 'A') ? 'selected' : ''; ?>>A</option>
                                            <option value="B" <?php echo ($quiz['correct_option'] === 'B') ? 'selected' : ''; ?>>B</option>
                                            <option value="C" <?php echo ($quiz['correct_option'] === 'C') ? 'selected' : ''; ?>>C</option>
                                            <option value="D" <?php echo ($quiz['correct_option'] === 'D') ? 'selected' : ''; ?>>D</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                                        <select id="status" name="status" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200">
                                            <option value="active" <?php echo ($quiz['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($quiz['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-4">
                                    <button type="button" onclick="window.location.href='edit_quizzes.php?lesson_id=<?= htmlspecialchars($lesson_id) ?>'" class="px-6 py-3 bg-gray-100 text-gray-600 rounded-xl hover:bg-gray-200 font-medium transition-all duration-200">Cancel</button>
                                    <button type="submit" id="update_quiz-btn" class="px-6 py-3 bg-primary-600 text-white rounded-xl hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-600 font-medium transition-all duration-200">Update Quiz</button>
                                </div>
                            </form>
                        </div>
                        <!-- Preview Section -->
                        <div class="bg-white rounded-2xl shadow-soft p-8 lg:block hidden">
                            <h2 class="text-2xl font-bold text-dashboard mb-6">Quiz Preview</h2>
                            <div id="quiz-preview" class="bg-gray-50 rounded-xl p-6">
                                <p id="preview-question" class="text-sm font-medium text-gray-700 mb-2"><?php echo htmlspecialchars($quiz['question'] ?? 'Enter question text'); ?></p>
                                <ul id="preview-options" class="space-y-2 text-sm text-gray-600">
                                    <li id="preview-option-a" class="<?php echo ($quiz['correct_option'] === 'A') ? 'text-green-600 font-medium' : ''; ?>">A: <?php echo htmlspecialchars($quiz['option_a'] ?? 'Option A'); ?></li>
                                    <li id="preview-option-b" class="<?php echo ($quiz['correct_option'] === 'B') ? 'text-green-600 font-medium' : ''; ?>">B: <?php echo htmlspecialchars($quiz['option_b'] ?? 'Option B'); ?></li>
                                    <li id="preview-option-c" class="<?php echo ($quiz['correct_option'] === 'C') ? 'text-green-600 font-medium' : ''; ?>">C: <?php echo htmlspecialchars($quiz['option_c'] ?? 'Option C'); ?></li>
                                    <li id="preview-option-d" class="<?php echo ($quiz['correct_option'] === 'D') ? 'text-green-600 font-medium' : ''; ?>">D: <?php echo htmlspecialchars($quiz['option_d'] ?? 'Option D'); ?></li>
                                </ul>
                                <p id="preview-status" class="mt-4 text-sm text-gray-600">Status: <span class="capitalize"><?php echo htmlspecialchars($quiz['status'] ?? 'Active'); ?></span></p>
                            </div>
                        </div>
                    </div>
                    <!-- Confirmation Modal -->
                    <div id="confirm-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50" role="dialog" aria-labelledby="modal-title">
                        <div class="bg-white rounded-xl shadow-soft p-6 w-full max-w-md transform transition-all duration-200">
                            <h3 id="modal-title" class="text-lg font-semibold text-dashboard mb-4">Confirm Update</h3>
                            <p class="text-sm text-gray-600 mb-6">Are you sure you want to update this quiz?</p>
                            <div class="flex justify-end space-x-4">
                                <button id="modal-cancel" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-xl hover:bg-gray-200 font-medium transition-all duration-200">Cancel</button>
                                <button id="modal-confirm" class="px-4 py-2 bg-primary-600 text-white rounded-xl hover:bg-primary-700 font-medium transition-all duration-200">Confirm</button>
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

        document.addEventListener("click", (e) => {
            if (
                !sidebar.contains(e.target) &&
                !sidebarToggle.contains(e.target) &&
                !sidebar.classList.contains("-translate-x-full")
            ) {
                sidebar.classList.add("-translate-x-full");
            }
        });

        // Highlight correct answer
        const correctSelect = document.getElementById('correct_option');
        const optionInputs = {
            'A': document.getElementById('option_a'),
            'B': document.getElementById('option_b'),
            'C': document.getElementById('option_c'),
            'D': document.getElementById('option_d')
        };

        correctSelect.addEventListener('change', () => {
            Object.values(optionInputs).forEach(input => {
                input.classList.remove('border-green-500', 'bg-green-50');
                input.classList.add('border-gray-200', 'bg-gray-50');
            });

            const selectedOption = correctSelect.value;
            if (selectedOption && optionInputs[selectedOption]) {
                optionInputs[selectedOption].classList.remove('border-gray-200', 'bg-gray-50');
                optionInputs[selectedOption].classList.add('border-green-500', 'bg-green-50');
            }
        });

        // Real-time preview updates
        const questionInput = document.getElementById('question');
        const optionAInput = document.getElementById('option_a');
        const optionBInput = document.getElementById('option_b');
        const optionCInput = document.getElementById('option_c');
        const optionDInput = document.getElementById('option_d');
        const statusSelect = document.getElementById('status');
        const previewQuestion = document.getElementById('preview-question');
        const previewOptions = {
            'A': document.getElementById('preview-option-a'),
            'B': document.getElementById('preview-option-b'),
            'C': document.getElementById('preview-option-c'),
            'D': document.getElementById('preview-option-d')
        };
        const previewStatus = document.getElementById('preview-status');

        function updatePreview() {
            previewQuestion.textContent = questionInput.value.trim() || 'Enter question text';
            previewOptions['A'].textContent = `A: ${optionAInput.value.trim() || 'Option A'}`;
            previewOptions['B'].textContent = `B: ${optionBInput.value.trim() || 'Option B'}`;
            previewOptions['C'].textContent = `C: ${optionCInput.value.trim() || 'Option C'}`;
            previewOptions['D'].textContent = `D: ${optionDInput.value.trim() || 'Option D'}`;
            previewStatus.textContent = `Status: ${statusSelect.value.charAt(0).toUpperCase() + statusSelect.value.slice(1)}`;

            Object.values(previewOptions).forEach(option => {
                option.classList.remove('text-green-600', 'font-medium');
            });

            const selectedOption = correctSelect.value;
            if (selectedOption && previewOptions[selectedOption]) {
                previewOptions[selectedOption].classList.add('text-green-600', 'font-medium');
            }
        }

        questionInput.addEventListener('input', updatePreview);
        optionAInput.addEventListener('input', updatePreview);
        optionBInput.addEventListener('input', updatePreview);
        optionCInput.addEventListener('input', updatePreview);
        optionDInput.addEventListener('input', updatePreview);
        correctSelect.addEventListener('input', updatePreview);
        statusSelect.addEventListener('input', updatePreview);

        // Form validation and submission with modal
        const form = document.getElementById('quiz-form');
        const updateQuizBtn = document.getElementById('update_quiz-btn');
        const confirmModal = document.getElementById('confirm-modal');
        const modalCancel = document.getElementById('modal-cancel');
        const modalConfirm = document.getElementById('modal-confirm');

        form.addEventListener('submit', (e) => {
            e.preventDefault(); // Prevent default form submission

            let hasError = false;
            const question = questionInput.value.trim();
            const optionA = optionAInput.value.trim();
            const optionB = optionBInput.value.trim();
            const optionC = optionCInput.value.trim();
            const optionD = optionDInput.value.trim();
            const correctOption = correctSelect.value;
            const status = statusSelect.value;

            document.querySelectorAll('.error-message').forEach(el => el.remove());

            if (!question) {
                hasError = true;
                addErrorMessage('question', 'Question is required');
            }
            if (!optionA) {
                hasError = true;
                addErrorMessage('option_a', 'Option A is required');
            }
            if (!optionB) {
                hasError = true;
                addErrorMessage('option_b', 'Option B is required');
            }
            if (!optionC) {
                hasError = true;
                addErrorMessage('option_c', 'Option C is required');
            }
            if (!optionD) {
                hasError = true;
                addErrorMessage('option_d', 'Option D is required');
            }
            if (!correctOption) {
                hasError = true;
                addErrorMessage('correct_option', 'Correct answer is required');
            }
            if (!status) {
                hasError = true;
                addErrorMessage('status', 'Status is required');
            }

            if (hasError) {
                updateQuizBtn.textContent = 'Update Quiz';
                updateQuizBtn.disabled = false;
            } else {
                // Show the modal
                confirmModal.classList.remove('hidden');
                modalConfirm.focus(); // Set focus to Confirm button for accessibility
            }
        });

        // Modal Cancel button
        modalCancel.addEventListener('click', () => {
            confirmModal.classList.add('hidden');
            updateQuizBtn.textContent = 'Update Quiz';
            updateQuizBtn.disabled = false;
        });

        // Modal Confirm button
        modalConfirm.addEventListener('click', () => {
            confirmModal.classList.add('hidden');
            updateQuizBtn.textContent = 'Updating...';
            updateQuizBtn.disabled = true;
            form.submit(); // Programmatically submit the form
        });

        // Close modal on clicking outside
        confirmModal.addEventListener('click', (e) => {
            if (e.target === confirmModal) {
                confirmModal.classList.add('hidden');
                updateQuizBtn.textContent = 'Update Quiz';
                updateQuizBtn.disabled = false;
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !confirmModal.classList.contains('hidden')) {
                confirmModal.classList.add('hidden');
                updateQuizBtn.textContent = 'Update Quiz';
                updateQuizBtn.disabled = false;
            }
        });

        function addErrorMessage(fieldId, message) {
            const field = document.getElementById(fieldId);
            const error = document.createElement('p');
            error.className = 'mt-1 text-sm text-red-600 error-message';
            error.textContent = message;
            field.parentElement.appendChild(error);
        }

        // Initialize preview
        updatePreview();
    </script>
</body>
</html>