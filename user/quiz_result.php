<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to view quiz results.";
    header("Location: ../login.php");
    exit();
}

$result_id = $_GET['result_id'] ?? null;
$quiz_result = $_SESSION['quiz_result'] ?? null;

if (!$result_id || !$quiz_result || $quiz_result['result_id'] != $result_id) {
    $_SESSION['error'] = "Invalid quiz result.";
    header("Location: dashboard.php");
    exit();
}

// Fetch result details from database for verification
$stmt = $pdo->prepare("
    SELECT qr.*, l.title AS lesson_title, m.title AS module_title
    FROM quiz_results qr
    JOIN lessons l ON qr.lesson_id = l.id
    JOIN modules m ON l.module_id = m.id
    WHERE qr.id = :result_id AND qr.user_id = :user_id
");
$stmt->execute([':result_id' => $result_id, ':user_id' => $_SESSION['user_id']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    $_SESSION['error'] = "Quiz result not found.";
    header("Location: dashboard.php");
    exit();
}

// Handle survey submission
$user_id = $_SESSION['user_id'];
$available_surveys = $_SESSION['available_surveys'] ?? [];
$survey = !empty($available_surveys) ? reset($available_surveys) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey'])) {
    $survey_id = $_POST['survey_id'] ?? null;
    $rating = $_POST['rating'] ?? null;
    $feedback = trim($_POST['feedback'] ?? '');

    if ($survey_id && $rating && in_array($rating, [1, 2, 3, 4, 5])) {
        $stmt = $pdo->prepare("
            INSERT INTO survey_responses (survey_id, user_id, rating, feedback)
            VALUES (:survey_id, :user_id, :rating, :feedback)
        ");
        $success = $stmt->execute([
            ':survey_id' => $survey_id,
            ':user_id' => $user_id,
            ':rating' => $rating,
            ':feedback' => $feedback
        ]);

        if ($success) {
            // Remove the submitted survey from the session
            $_SESSION['available_surveys'] = array_filter($available_surveys, function($s) use ($survey_id) {
                return $s['id'] != $survey_id;
            });

            // Reload the page to show the next survey or clear the modal
            header("Location: quiz_result.php?result_id=" . urlencode($result_id));
            exit();
        } else {
            $_SESSION['error'] = "Failed to submit survey. Please try again.";
        }
    } else {
        $_SESSION['error'] = "Please provide a valid rating.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - <?php echo htmlspecialchars($result['lesson_title']); ?></title>
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
        .rating-star {
            transition: transform 0.2s ease-in-out;
        }
        .rating-star:hover {
            transform: scale(1.2);
        }
        .rating-star.selected {
            border: 2px solid #facc15;
            border-radius: 50%;
            padding: 2px;
            background-color: #fef9c3;
        }
    </style>
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
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="modules_list.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                            </svg>
                            Modules
                        </a>
                    </li>
                    <li>
                        <a href="results.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3 a.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                            </svg>
                            View Results
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
                    <h1 class="text-xl font-semibold text-dashboard">Quiz Results</h1>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto bg-gray-100">
                <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-semibold text-dashboard mb-4"><?php echo htmlspecialchars($result['lesson_title']); ?></h2>
                    <p class="text-gray-600 mb-4">Module: <?php echo htmlspecialchars($result['module_title']); ?></p>
                    
                    <div class="mb-6">
                        <p class="text-lg font-medium">Your Score: 
                            <span class="text-primary-600"><?php echo $result['score']; ?> / <?php echo $result['totalItems']; ?></span>
                        </p>
                        <p class="text-lg font-medium">Percentage: 
                            <span class="text-primary-600"><?php echo number_format(($result['score'] / $result['totalItems']) * 100, 2); ?>%</span>
                        </p>
                        <p class="text-lg font-medium">Status: 
                            <span class="inline-block px-2 py-1 rounded-full text-sm font-medium <?php echo $result['isPassed'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                <?php echo $result['isPassed'] ? 'Passed' : 'Failed'; ?>
                            </span>
                        </p>
                        <p class="text-lg font-medium">Date Taken: 
                            <span class="text-primary-600"><?php echo date('M d, Y h:i A', strtotime($result['taken_at'])); ?></span>
                        </p>
                    </div>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg">
                            <?php echo htmlspecialchars($_SESSION['success']); ?>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
                            <?php echo htmlspecialchars($_SESSION['error']); ?>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <div class="flex gap-4">
                        <a href="dashboard.php" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">
                            Back to Dashboard
                        </a>
                        <a href="results.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                            View All Results
                        </a>
                        <a href="answers.php?result_id=<?php echo urlencode($result_id); ?>" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">
                            View Answers
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Survey Modal -->
    <?php if ($survey): ?>
    <div id="survey-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 fade visible">
        <div class="modal-content rounded-2xl p-8 w-full max-w-lg shadow-2xl">
            <div class="flex justify-center items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($survey['title']); ?></h2>
            </div>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-lg text-sm">
                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="survey_id" value="<?php echo $survey['id']; ?>">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3 text-center"><?php echo htmlspecialchars($survey['description'] ?? 'Please provide your feedback for this lesson.'); ?></label>
                </div>
                <div class="mb-6">
                    <label class="block text-xs font-medium text-gray-700 mb-3 text-center">Rate your experience</label>
                    <div class="flex justify-center gap-3">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label class="rating-star cursor-pointer relative group">
                                <input type="radio" name="rating" value="<?php echo $i; ?>" class="hidden" required>
                                <span 
                                    class="text-3xl transition-transform duration-200 <?php echo $i <= (isset($_POST['rating']) ? $_POST['rating'] : 0) ? 'text-yellow-400 selected' : 'text-gray-300'; ?>" 
                                    title="<?php
                                        switch ($i) {
                                            case 1: echo 'Very Sad'; break;
                                            case 2: echo 'Sad'; break;
                                            case 3: echo 'Neutral'; break;
                                            case 4: echo 'Happy'; break;
                                            case 5: echo 'Very Happy'; break;
                                        }
                                    ?>"
                                >
                                    <?php
                                    switch ($i) {
                                        case 1: echo '😣'; break; // Very Sad
                                        case 2: echo '🙁'; break; // Sad
                                        case 3: echo '😐'; break; // Neutral
                                        case 4: echo '🙂'; break; // Happy
                                        case 5: echo '😊'; break; // Very Happy
                                    }
                                    ?>
                                </span>
                                <!-- Tooltip for rating description -->
                                <span class="absolute invisible group-hover:visible bg-gray-800 text-white text-xs rounded py-1 px-2 bottom-full mb-2 left-1/2 transform -translate-x-1/2">
                                    <?php
                                    switch ($i) {
                                        case 1: echo 'Very Sad'; break;
                                        case 2: echo 'Sad'; break;
                                        case 3: echo 'Neutral'; break;
                                        case 4: echo 'Happy'; break;
                                        case 5: echo 'Very Happy'; break;
                                    }
                                    ?>
                                </span>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <!-- Confirmation message area -->
                    <div id="rating-message" class="text-center text-sm font-medium text-gray-600 mt-3"></div>
                </div>
                <div class="mb-6">
                    <label for="feedback" class="block text-sm font-medium text-gray-700 mb-2">Your Feedback (Optional)</label>
                    <textarea name="feedback" id="feedback" class="w-full rounded-lg border border-gray-200 p-3 bg-white/50 focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all duration-200" rows="5" placeholder="Share your thoughts..."></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="submit" name="submit_survey" class="px-5 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200 font-medium">Submit</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

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

        // Survey modal functionality
        const surveyModal = document.getElementById("survey-modal");
        const skipSurveyButton = document.getElementById("skip-survey");

        function closeSurveyModal() {
            fetch('clear_surveys.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'clear_surveys=true'
            }).then(() => {
                surveyModal.classList.remove("visible");
                surveyModal.classList.add("hidden");
            });
        }

        if (skipSurveyButton) {
            skipSurveyButton.addEventListener("click", closeSurveyModal);
        }

        // Rating faces interactivity
        const ratingStars = document.querySelectorAll('.rating-star');
        const ratingMessage = document.getElementById('rating-message');

        ratingStars.forEach(star => {
            star.addEventListener('click', () => {
                const input = star.querySelector('input');
                const value = parseInt(input.value);
                const ratingText = star.querySelector('span').getAttribute('title');

                // Update star visuals
                ratingStars.forEach((s, index) => {
                    const span = s.querySelector('span');
                    span.classList.toggle('text-yellow-400', index < value);
                    span.classList.toggle('text-gray-300', index >= value);
                    span.classList.toggle('selected', index < value);
                });

                // Show confirmation message
                ratingMessage.textContent = `You selected: ${ratingText}`;
                ratingMessage.classList.add('text-primary-600');
            });
        });
    </script>
</body>
</html>