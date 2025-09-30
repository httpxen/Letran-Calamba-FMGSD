<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $module_id = $_GET['module_id'];
    $moduleStmt = $pdo->prepare("SELECT * FROM modules WHERE id = :module_id");
    $moduleStmt->execute([':module_id' => $module_id]);
    $module = $moduleStmt->fetch(PDO::FETCH_ASSOC);

    $video_title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (empty($module_id)) {
        die('Module ID is not found');
    }

    if (empty($video_title)) {
        $errors['title'] = 'Video title is required';
    }

    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['video']['tmp_name'])) {
        $file_tmp = $_FILES['video']['tmp_name'];
        $file_name = $_FILES['video']['name'];
        $file_type = mime_content_type($file_tmp);

        $allowed_types = ['video/mp4', 'video/webm', 'video/ogg', 'video/x-webm', 'video/x-matroska'];
        if (!in_array($file_type, $allowed_types)) {
            $errors['video'] = 'Only MP4, WEBM, and OGG files are allowed';
        }
    } else {
        $errors['video'] = 'Video is required';
    }

    $questions = $_POST['question'] ?? [];
    $options_a = $_POST['option_a'] ?? [];
    $options_b = $_POST['option_b'] ?? [];
    $options_c = $_POST['option_c'] ?? [];
    $options_d = $_POST['option_d'] ?? [];
    $correct_options = $_POST['correct_option'] ?? [];

    foreach ($questions as $index => $question) {
        if (empty(trim($question)) || empty(trim($options_a[$index])) || empty(trim($options_b[$index])) || empty(trim($options_c[$index])) || empty(trim($options_d[$index])) || empty(trim($correct_options[$index]))) {
            $errors['questions'] = 'All questions and options are required';
            break;
        }
    }

    if (empty($errors)) {
        if (isset($_FILES['video'])) {
            $video_tmp_path = $_FILES['video']['tmp_name'];
            $original_filename = $_FILES['video']['name'];
            $uploads_dir = '../Uploads/videos/';
            $unique_name = uniqid('video_', true) . '.mp4';
            $destination_path = $uploads_dir . $unique_name;

            $ffmpeg_cmd = "ffmpeg -i " . escapeshellarg($video_tmp_path) . " -vcodec libx264 -acodec aac -strict -2 " . escapeshellarg($destination_path) . " 2>&1";
            $output = shell_exec($ffmpeg_cmd);

            if (!file_exists($destination_path)) {
                $errors['video'] = 'Video conversion failed. Check your FFmpeg installation';
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("INSERT INTO lessons (module_id, title, video_url) VALUES (:module_id, :title, :video_url)");
                    $stmt->execute([':module_id' => $module_id, ':title' => $video_title, ':video_url' => $destination_path]);
                    $lesson_id = $pdo->lastInsertId();

                    if (!empty($_POST['question']) && is_array($_POST['question'])) {
                        $insertQuestionStmt = $pdo->prepare("INSERT INTO quizzes (lesson_id, question, option_a, option_b, option_c, option_d, correct_option) VALUES (:lesson_id, :question, :option_a, :option_b, :option_c, :option_d, :correct_option)");

                        foreach ($questions as $i => $q) {
                            if (!empty($q)) {
                                $params = [
                                    ':lesson_id' => $lesson_id,
                                    ':question' => $q,
                                    ':option_a' => $options_a[$i],
                                    ':option_b' => $options_b[$i],
                                    ':option_c' => $options_c[$i],
                                    ':option_d' => $options_d[$i],
                                    ':correct_option' => $correct_options[$i]
                                ];
                                $insertQuestionStmt->execute($params);
                            }
                        }

                        $pdo->commit();
                        $errors['none'] = 'Lesson added successfully';
                    } else {
                        $errors['questions'] = 'Add at least 1 question';
                    }
                } catch (\PDOException $e) {
                    $pdo->rollBack();
                    $errors['database'] = "Database Error: " . $e->getMessage();
                }
            }
        }
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['module_id'])) {
        die('Module ID is not set');
    }

    $module_id = $_GET['module_id'];
    $moduleStmt = $pdo->prepare("SELECT * FROM modules WHERE id = :module_id");
    $moduleStmt->execute([':module_id' => $module_id]);
    $module = $moduleStmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Lesson - <?= htmlspecialchars($module['title'] ?? '') ?></title>
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
                        accent: {
                            500: "#10b981",
                            600: "#059669",
                        },
                        dashboard: "#12234e",
                    },
                    boxShadow: {
                        'glow': '0 0 15px rgba(79, 70, 229, 0.2)',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-out',
                        'scale-in': 'scaleIn 0.2s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        scaleIn: {
                            '0%': { transform: 'scale(0.95)' },
                            '100%': { transform: 'scale(1)' },
                        },
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
        <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-white border-r shadow-lg transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 md:static md:shadow-none">
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
            <header class="bg-white shadow-sm flex justify-between items-center px-6 py-4">
                <div class="flex items-center space-x-4">
                    <button id="sidebar-toggle" class="md:hidden text-gray-600 hover:text-primary-600 focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <h1 class="text-xl font-semibold text-dashboard">Create New Lesson</h1>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <div class="max-w-7xl mx-auto">
                    <?php if (isset($errors['database'])) : ?>
                        <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-lg shadow-md animate-fade-in"><?php echo htmlspecialchars($errors['database']); ?></div>
                    <?php elseif (isset($errors['none'])) : ?>
                        <div class="mb-6 p-4 bg-green-50 text-green-600 rounded-lg shadow-md animate-fade-in"><?php echo htmlspecialchars($errors['none']); ?></div>
                    <?php endif; ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Form Section -->
                        <div class="bg-white rounded-lg shadow-md p-6 animate-scale-in">
                            <h2 class="text-lg font-semibold text-gray-800 mb-6">Lesson Details - <?= htmlspecialchars($module['title'] ?? '') ?></h2>
                            <form id="lesson-form" method="POST" enctype="multipart/form-data" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Lesson Title *</label>
                                        <input type="text" id="title" name="title" value="<?= isset($errors['none']) ? '' : htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="Enter lesson title" class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200">
                                        <?php if (isset($errors['title'])) : ?>
                                            <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['title']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <label for="question-count" class="block text-sm font-medium text-gray-700 mb-1">Number of Questions *</label>
                                        <select id="question-count" name="question_count" class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200">
                                            <option value="1" selected>1</option>
                                            <?php for ($i = 2; $i <= 50; $i++) : ?>
                                                <option value="<?= $i ?>"><?= $i ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label for="video" class="block text-sm font-medium text-gray-700 mb-1">Upload Video * (MP4/WEBM/OGG)</label>
                                    <input type="file" id="video" name="video" accept="video/mp4,video/webm,video/ogg" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-600 hover:file:bg-primary-100 transition-all duration-200">
                                    <?php if (isset($errors['video'])) : ?>
                                        <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['video']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div id="questions-container" class="space-y-4">
                                    <div class="question-block bg-gray-50 p-6 rounded-lg">
                                        <h4 class="text-sm font-medium text-gray-700 mb-2">Question 1</h4>
                                        <textarea name="question[]" placeholder="Enter question text" rows="3" class="block w-full rounded-lg border border-gray-200 bg-white px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200"></textarea>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                                            <input type="text" name="option_a[]" placeholder="Option A" class="block w-full rounded-lg border border-gray-200 bg-white px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200">
                                            <input type="text" name="option_b[]" placeholder="Option B" class="block w-full rounded-lg border border-gray-200 bg-white px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200">
                                            <input type="text" name="option_c[]" placeholder="Option C" class="block w-full rounded-lg border border-gray-200 bg-white px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200">
                                            <input type="text" name="option_d[]" placeholder="Option D" class="block w-full rounded-lg border border-gray-200 bg-white px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200">
                                        </div>
                                        <div class="mt-4">
                                            <label for="correct_option_0" class="block text-sm font-medium text-gray-700">Correct Answer:</label>
                                            <select name="correct_option[]" id="correct_option_0" class="mt-1 block w-full sm:w-1/3 rounded-lg border border-gray-200 bg-white px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200">
                                                <option value="" disabled selected>Choose correct answer</option>
                                                <option value="A">A</option>
                                                <option value="B">B</option>
                                                <option value="C">C</option>
                                                <option value="D">D</option>
                                            </select>
                                        </div>
                                    </div>
                                    <?php if (isset($errors['questions'])) : ?>
                                        <p class="text-sm text-red-600"><?php echo htmlspecialchars($errors['questions']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex justify-end space-x-4">
                                    <button type="button" onclick="window.location.href='module_list.php'" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-all duration-200">Cancel</button>
                                    <button type="submit" id="add-new-lesson-btn" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-600 font-medium transition-all duration-200">Create Lesson</button>
                                </div>
                            </form>
                        </div>
                        <!-- Preview Section -->
                        <div class="bg-white rounded-lg shadow-md p-6 animate-scale-in lg:block hidden">
                            <h2 class="text-lg font-semibold text-gray-800 mb-6">Lesson Preview</h2>
                            <div id="lesson-preview" class="bg-gray-50 rounded-lg p-6 transition-all duration-200">
                                <div class="relative aspect-video bg-gray-200 rounded-lg overflow-hidden">
                                    <video id="preview-video" class="w-full h-full object-cover" controls>
                                        <source src="" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                    <div id="preview-video-placeholder" class="absolute inset-0 flex items-center justify-center text-gray-500 bg-gray-200">No video selected</div>
                                </div>
                                <h3 id="preview-title" class="mt-4 text-base font-semibold text-gray-800">Enter lesson title</h3>
                                <div id="preview-questions" class="mt-4 space-y-4">
                                    <div class="preview-question">
                                        <p class="text-sm font-medium text-gray-700">Question 1</p>
                                        <p class="text-sm text-gray-600">Enter question text</p>
                                        <ul class="mt-2 space-y-1 text-sm text-gray-600">
                                            <li>A: Option A</li>
                                            <li>B: Option B</li>
                                            <li>C: Option C</li>
                                            <li>D: Option D</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmation-modal" class="fixed inset-0 bg-gray-900/40 flex items-center justify-center z-50 hidden">
        <div class="relative bg-white rounded-lg shadow-md p-6 max-w-md w-full mx-4 animate-scale-in focus:outline-none" tabindex="-1" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <button id="modal-close" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-600 rounded-full p-1 transition-colors duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span class="sr-only">Close modal</span>
            </button>
            <div class="flex items-center gap-3 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <h2 id="modal-title" class="text-lg font-semibold text-gray-800">Confirm Lesson Upload</h2>
            </div>
            <p class="text-sm text-gray-600 mb-6 leading-relaxed">Are you sure you want to upload this lesson to the module <strong><?= htmlspecialchars($module['title'] ?? '') ?></strong>? This action cannot be undone.</p>
            <div class="flex justify-end space-x-4">
                <button id="modal-cancel" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400 font-medium transition-all duration-200">Cancel</button>
                <button id="modal-confirm" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-600 font-medium transition-all duration-200">Confirm Upload</button>
            </div>
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

        // Dynamic question generation
        const questionCountSelect = document.getElementById('question-count');
        const questionsContainer = document.getElementById('questions-container');
        const questionTemplate = questionsContainer.querySelector('.question-block');

        questionCountSelect.addEventListener('change', () => {
            const count = parseInt(questionCountSelect.value);
            questionsContainer.innerHTML = '';
            for (let i = 0; i < count; i++) {
                const newQuestion = questionTemplate.cloneNode(true);
                newQuestion.querySelector('h4').textContent = `Question ${i + 1}`;
                newQuestion.querySelectorAll('textarea, input, select').forEach(input => input.value = '');
                newQuestion.querySelector('select').id = `correct_option_${i}`;
                questionsContainer.appendChild(newQuestion);
            }
        });

        // Real-time preview updates
        const titleInput = document.getElementById('title');
        const videoInput = document.getElementById('video');
        const previewTitle = document.getElementById('preview-title');
        const previewVideo = document.getElementById('preview-video');
        const previewVideoPlaceholder = document.getElementById('preview-video-placeholder');
        const previewQuestions = document.getElementById('preview-questions');

        titleInput.addEventListener('input', () => {
            previewTitle.textContent = titleInput.value.trim() || 'Enter lesson title';
        });

        videoInput.addEventListener('change', () => {
            const file = videoInput.files[0];
            if (file && ['video/mp4', 'video/webm', 'video/ogg'].includes(file.type)) {
                const url = URL.createObjectURL(file);
                previewVideo.src = url;
                previewVideoPlaceholder.style.display = 'none';
                previewVideo.style.display = 'block';
            } else {
                previewVideo.src = '';
                previewVideoPlaceholder.style.display = 'flex';
                previewVideo.style.display = 'none';
            }
        });

        function updateQuestionPreview() {
            const questions = questionsContainer.querySelectorAll('.question-block');
            previewQuestions.innerHTML = '';
            questions.forEach((block, index) => {
                const questionText = block.querySelector('textarea').value.trim() || 'Enter question text';
                const optionA = block.querySelector('input[name="option_a[]"]').value.trim() || 'Option A';
                const optionB = block.querySelector('input[name="option_b[]"]').value.trim() || 'Option B';
                const optionC = block.querySelector('input[name="option_c[]"]').value.trim() || 'Option C';
                const optionD = block.querySelector('input[name="option_d[]"]').value.trim() || 'Option D';
                const previewQuestion = document.createElement('div');
                previewQuestion.className = 'preview-question';
                previewQuestion.innerHTML = `
                    <p class="text-sm font-medium text-gray-700">Question ${index + 1}</p>
                    <p class="text-sm text-gray-600">${questionText}</p>
                    <ul class="mt-2 space-y-1 text-sm text-gray-600">
                        <li>A: ${optionA}</li>
                        <li>B: ${optionB}</li>
                        <li>C: ${optionC}</li>
                        <li>D: ${optionD}</li>
                    </ul>
                `;
                previewQuestions.appendChild(previewQuestion);
            });
        }

        questionsContainer.addEventListener('input', updateQuestionPreview);

        // Form validation and submission with modal
        const form = document.getElementById('lesson-form');
        const addLessonBtn = document.getElementById('add-new-lesson-btn');
        const confirmationModal = document.getElementById('confirmation-modal');
        const modalConfirm = document.getElementById('modal-confirm');
        const modalCancel = document.getElementById('modal-cancel');
        const modalClose = document.getElementById('modal-close');

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            let hasError = false;
            const title = titleInput.value.trim();
            const video = videoInput.files[0];
            const questions = questionsContainer.querySelectorAll('.question-block');

            document.querySelectorAll('.error-message').forEach(el => el.remove());

            if (!title) {
                hasError = true;
                addErrorMessage('title', 'Lesson title is required');
            }

            if (!video) {
                hasError = true;
                addErrorMessage('video', 'Video is required');
            } else if (!['video/mp4', 'video/webm', 'video/ogg'].includes(video.type)) {
                hasError = true;
                addErrorMessage('video', 'Only MP4, WEBM, and OGG files are allowed');
            }

            questions.forEach((block, index) => {
                const question = block.querySelector('textarea').value.trim();
                const optionA = block.querySelector('input[name="option_a[]"]').value.trim();
                const optionB = block.querySelector('input[name="option_b[]"]').value.trim();
                const optionC = block.querySelector('input[name="option_c[]"]').value.trim();
                const optionD = block.querySelector('input[name="option_d[]"]').value.trim();
                const correctOption = block.querySelector('select').value;

                if (!question || !optionA || !optionB || !optionC || !optionD || !correctOption) {
                    hasError = true;
                    if (!document.querySelector('.error-message.questions')) {
                        const error = document.createElement('p');
                        error.className = 'text-sm text-red-600 error-message questions';
                        error.textContent = 'All questions and options are required';
                        questionsContainer.appendChild(error);
                    }
                }
            });

            if (hasError) {
                addLessonBtn.innerText = 'Create Lesson';
                addLessonBtn.disabled = false;
            } else {
                confirmationModal.classList.remove('hidden');
                confirmationModal.querySelector('div').focus(); // Focus modal for accessibility
            }
        });

        function closeModal() {
            confirmationModal.classList.add('hidden');
            addLessonBtn.innerText = 'Create Lesson';
            addLessonBtn.disabled = false;
        }

        modalConfirm.addEventListener('click', () => {
            closeModal();
            addLessonBtn.innerText = 'Creating...';
            addLessonBtn.disabled = true;
            form.submit();
        });

        modalCancel.addEventListener('click', closeModal);
        modalClose.addEventListener('click', closeModal);

        // Close modal on click outside
        confirmationModal.addEventListener('click', (e) => {
            if (e.target === confirmationModal) {
                closeModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !confirmationModal.classList.contains('hidden')) {
                closeModal();
            }
        });

        function addErrorMessage(fieldId, message) {
            const field = document.getElementById(fieldId);
            const error = document.createElement('p');
            error.className = 'mt-1 text-sm text-red-600 error-message';
            error.textContent = message;
            field.parentElement.appendChild(error);
        }

        // Initialize question preview
        updateQuestionPreview();
    </script>
</body>
</html>
