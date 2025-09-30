<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';

// Include TCPDF library
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to view answers.";
    header("Location: ../login.php");
    exit();
}

$result_id = $_GET['result_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$result_id) {
    $_SESSION['error'] = "Invalid quiz result.";
    header("Location: results.php");
    exit();
}

// Handle PDF download
if (isset($_GET['download_pdf']) && $_GET['download_pdf'] == '1') {
    // Fetch quiz result details
    $stmt = $pdo->prepare("
        SELECT qr.*, l.title AS lesson_title, m.title AS module_title, m.id AS module_id
        FROM quiz_results qr
        JOIN lessons l ON qr.lesson_id = l.id
        JOIN modules m ON l.module_id = m.id
        WHERE qr.id = :result_id AND qr.user_id = :user_id
    ");
    $stmt->execute([':result_id' => $result_id, ':user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        $_SESSION['error'] = "Quiz result not found.";
        header("Location: results.php");
        exit();
    }

    // Fetch user's answers and quiz questions
    $stmt = $pdo->prepare("
        SELECT uqa.selected_option, q.question, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option
        FROM user_quiz_answers uqa
        JOIN quizzes q ON uqa.quiz_id = q.id
        WHERE uqa.user_id = :user_id AND q.lesson_id = :lesson_id
        ORDER BY q.id
    ");
    $stmt->execute([':user_id' => $user_id, ':lesson_id' => $result['lesson_id']]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Quiz System');
    $pdf->SetTitle('Reviewer - ' . htmlspecialchars($result['lesson_title']));
    $pdf->SetSubject('Quiz Reviewer');
    $pdf->SetKeywords('Quiz, Reviewer');

    // Set custom header
    $pdf->SetHeaderData('', 0, 'Quiz Reviewer', htmlspecialchars($result['lesson_title']), array(18, 35, 78), array(18, 35, 78));
    $pdf->setHeaderFont(['helvetica', '', 10]);
    $pdf->SetHeaderMargin(10);

    // Set margins
    $pdf->SetMargins(15, 30, 15);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 15);

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Add a page
    $pdf->AddPage();

    // Custom CSS-like styles for HTML
    $html = '
    <style>
        h1 { color: #12234e; font-size: 20px; font-weight: bold; margin-bottom: 10px; }
        h2 { color: #12234e; font-size: 16px; font-weight: bold; margin: 15px 0 10px; }
        p { font-size: 10px; margin: 5px 0; color: #333333; }
        .summary-box { border: 1px solid #e5e7eb; background-color: #f9fafb; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .question { font-weight: bold; font-size: 11px; margin: 10px 0 5px; }
        .option { font-size: 10px; margin: 3px 0; }
        .correct { color: #15803d; font-weight: bold; }
        .incorrect { color: #b91c1c; font-weight: bold; }
        .divider { border-top: 1px solid #e5e7eb; margin: 10px 0; }
        .icon { font-size: 12px; vertical-align: middle; }
    </style>';

    // Quiz Header (Module and Lesson Name)
    $html .= '<div class="summary-box">';
    $html .= '<h1>' . htmlspecialchars($result['lesson_title']) . '</h1>';
    $html .= '<p><strong>Module:</strong> ' . htmlspecialchars($result['module_title']) . '</p>';
    $html .= '</div>';

    // Quiz Questions and Answers
    $html .= '<h2>Questions and Answers</h2>';
    foreach ($answers as $index => $answer) {
        $is_correct = $answer['selected_option'] === $answer['correct_option'];
        $html .= '<p class="question">' . ($index + 1) . '. ' . htmlspecialchars($answer['question']) . ' <span class="' . ($is_correct ? 'correct' : 'incorrect') . ' icon">' . ($is_correct ? '✔' : '✘') . '</span></p>';

        foreach (['A', 'B', 'C', 'D'] as $option) {
            $option_text = htmlspecialchars($answer['option_' . strtolower($option)]);
            $class = '';
            $suffix = '';
            if ($answer['selected_option'] === $option) {
                $class = $answer['correct_option'] === $option ? 'correct' : 'incorrect';
                $suffix .= ' (Your Answer)';
            }
            if ($answer['correct_option'] === $option) {
                $class = 'correct';
                $suffix .= ($suffix ? ' & ' : '') . 'Correct Answer';
            }
            $html .= '<p class="option ' . $class . '">' . $option . '. ' . $option_text . ($suffix ? ' <span style="font-size: 9px; color: #6b7280;">' . $suffix . '</span>' : '') . '</p>';
        }
        $html .= '<div class="divider"></div>';
    }

    // Write HTML content to PDF
    $pdf->writeHTML($html, true, false, true, false, '');

    // Output the PDF as a download
    $pdf->Output('reviewer_' . $result_id . '.pdf', 'D');
    exit();
}

// Fetch quiz result details (for page display)
$stmt = $pdo->prepare("
    SELECT qr.*, l.title AS lesson_title, m.title AS module_title, m.id AS module_id
    FROM quiz_results qr
    JOIN lessons l ON qr.lesson_id = l.id
    JOIN modules m ON l.module_id = m.id
    WHERE qr.id = :result_id AND qr.user_id = :user_id
");
$stmt->execute([':result_id' => $result_id, ':user_id' => $user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    $_SESSION['error'] = "Quiz result not found.";
    header("Location: results.php");
    exit();
}

// Fetch user's answers and quiz questions
$stmt = $pdo->prepare("
    SELECT uqa.selected_option, q.question, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option
    FROM user_quiz_answers uqa
    JOIN quizzes q ON uqa.quiz_id = q.id
    WHERE uqa.user_id = :user_id AND q.lesson_id = :lesson_id
    ORDER BY q.id
");
$stmt->execute([':user_id' => $user_id, ':lesson_id' => $result['lesson_id']]);
$answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate correct/incorrect answers
$correct_count = 0;
foreach ($answers as $answer) {
    if ($answer['selected_option'] === $answer['correct_option']) {
        $correct_count++;
    }
}
$incorrect_count = count($answers) - $correct_count;

// Check if user can retake the quiz (e.g., if they failed)
$can_retake = !$result['isPassed'];

// Fetch module progress (number of lessons completed)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT qr.lesson_id) AS completed_lessons, 
           (SELECT COUNT(*) FROM lessons WHERE module_id = :module_id) AS total_lessons
    FROM quiz_results qr
    JOIN lessons l ON qr.lesson_id = l.id
    WHERE qr.user_id = :user_id AND l.module_id = :module_id AND qr.isPassed = 1
");
$stmt->execute([':user_id' => $user_id, ':module_id' => $result['module_id']]);
$progress = $stmt->fetch(PDO::FETCH_ASSOC);
$progress_percentage = ($progress['total_lessons'] > 0) ? ($progress['completed_lessons'] / $progress['total_lessons']) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Answers - <?php echo htmlspecialchars($result['lesson_title']); ?></title>
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
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-primary-50 text-primary-600' : ''; ?>" aria-label="Go to Dashboard">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="modules_list.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) === 'modules_list.php' ? 'bg-primary-50 text-primary-600' : ''; ?>" aria-label="Go to Modules">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                            </svg>
                            Modules
                        </a>
                    </li>
                    <li>
                        <a href="results.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) === 'results.php' || basename($_SERVER['PHP_SELF']) === 'answers.php' ? 'bg-primary-50 text-primary-600' : ''; ?>" aria-label="Go to Results">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                            </svg>
                            View Results
                        </a>
                    </li>
                    <li>
                        <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-red-50 hover:text-red-600 font-medium transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) === 'logout.php' ? 'bg-red-50 text-red-600' : ''; ?>" aria-label="Log out">
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
                    <button id="sidebar-toggle" class="md:hidden text-gray-600 hover:text-primary-600 focus:outline-none" aria-label="Toggle sidebar">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <h1 class="text-xl font-semibold text-dashboard">Quiz Answers</h1>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto bg-gray-100">
                <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Quiz Answers -->
                    <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-2xl font-semibold text-dashboard mb-4"><?php echo htmlspecialchars($result['lesson_title']); ?></h2>
                        <p class="text-gray-600 mb-4">Module: <?php echo htmlspecialchars($result['module_title']); ?></p>
                        <p class="text-gray-600 mb-4">Score: <?php echo $result['score']; ?> / <?php echo $result['totalItems']; ?> (<?php echo number_format(($result['score'] / $result['totalItems']) * 100, 2); ?>%)</p>
                        <p class="text-gray-600 mb-6">Status: 
                            <span class="inline-block px-2 py-1 rounded-full text-sm font-medium <?php echo $result['isPassed'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                <?php echo $result['isPassed'] ? 'Passed' : 'Failed'; ?>
                            </span>
                        </p>

                        <?php if (empty($answers)): ?>
                            <p class="text-gray-600">No answers found for this quiz.</p>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php foreach ($answers as $index => $answer): ?>
                                    <div class="border-b pb-4">
                                        <p class="text-base font-medium text-gray-800 mb-2 flex items-center gap-2">
                                            <span><?php echo ($index + 1) . '. ' . htmlspecialchars($answer['question']); ?></span>
                                            <?php if ($answer['selected_option'] === $answer['correct_option']): ?>
                                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            <?php else: ?>
                                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            <?php endif; ?>
                                        </p>
                                        <div class="space-y-2">
                                            <?php foreach (['A', 'B', 'C', 'D'] as $option): ?>
                                                <p class="text-sm flex items-center gap-2 <?php echo $answer['selected_option'] === $option ? 'text-primary-600 font-medium' : 'text-gray-600'; ?>">
                                                    <span><?php echo $option; ?>. <?php echo htmlspecialchars($answer['option_' . strtolower($option)]); ?></span>
                                                    <?php if ($answer['selected_option'] === $option): ?>
                                                        <span>(Your Answer)</span>
                                                    <?php endif; ?>
                                                </p>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-6 flex gap-4 flex-wrap">
                            <a href="results.php" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">Back to Results</a>
                            <a href="dashboard.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">Back to Dashboard</a>
                            <?php if ($can_retake): ?>
                                <a href="lesson.php?lesson_id=<?php echo urlencode($result['lesson_id']); ?>" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors duration-200">Retake Quiz</a>
                            <?php endif; ?>
                            <a href="answers.php?result_id=<?php echo urlencode($result_id); ?>&download_pdf=1" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Download PDF
                            </a>
                        </div>
                    </div>

                    <!-- Sidebar (Right) -->
                    <div class="lg:col-span-1 space-y-6">
                        <!-- Quiz Summary Card -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-semibold text-dashboard mb-4">Quiz Summary</h3>
                            <div class="space-y-3">
                                <p class="flex justify-between"><span class="text-gray-600">Date Taken:</span> <span class="font-medium"><?php echo date('M d, Y h:i A', strtotime($result['taken_at'])); ?></span></p>
                                <p class="flex justify-between"><span class="text-gray-600">Correct Answers:</span> <span class="font-medium text-green-600"><?php echo $correct_count; ?></span></p>
                                <p class="flex justify-between"><span class="text-gray-600">Incorrect Answers:</span> <span class="font-medium text-red-600"><?php echo $incorrect_count; ?></span></p>
                                <p class="flex justify-between"><span class="text-gray-600">Passing Score:</span> <span class="font-medium"><?php echo ceil($result['totalItems'] * 0.5); ?></span></p>
                            </div>
                        </div>

                        <!-- Module Progress -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-semibold text-dashboard mb-4">Module Progress</h3>
                            <p class="text-gray-600 mb-2">Lessons Completed: <?php echo $progress['completed_lessons']; ?> / <?php echo $progress['total_lessons']; ?></p>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-primary-600 h-2.5 rounded-full" style="width: <?php echo $progress_percentage; ?>%"></div>
                            </div>
                            <p class="text-sm text-gray-500 mt-2"><?php echo number_format($progress_percentage, 1); ?>% Complete</p>
                            <a href="modules_list.php" class="mt-4 inline-block text-primary-600 hover:underline">View All Modules</a>
                        </div>

                        <!-- Feedback Form -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-semibold text-dashboard mb-4">Quiz Feedback</h3>
                            <form action="submit_feedback.php" method="POST">
                                <input type="hidden" name="lesson_id" value="<?php echo htmlspecialchars($result['lesson_id']); ?>">
                                <textarea name="feedback" rows="4" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-600" placeholder="Share your thoughts about this quiz..." required></textarea>
                                <button type="submit" class="mt-3 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">Submit Feedback</button>
                            </form>
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
