<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "User") {
  header("Location: login.php");
  exit();
}

// Validate lesson_id
$lesson_id = filter_input(INPUT_GET, 'lesson_id', FILTER_VALIDATE_INT);
if (!$lesson_id) {
  header("Location: modules_list.php");
  exit();
}
$user_id = $_SESSION['user_id'];

// Handle survey submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey'])) {
    $survey_id = $_POST['survey_id'] ?? null;
    $rating = $_POST['rating'] ?? null;
    $feedback = trim($_POST['feedback'] ?? '');
    $result_id = $_POST['result_id'] ?? null;

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
            $_SESSION['available_surveys'] = array_filter($_SESSION['available_surveys'] ?? [], function($s) use ($survey_id) {
                return $s['id'] != $survey_id;
            });

            // If no more surveys, redirect to quiz_result.php
            if (empty($_SESSION['available_surveys'])) {
                unset($_SESSION['available_surveys']);
                header("Location: quiz_result.php?result_id=" . urlencode($result_id));
                exit();
            }
            // Reload lesson.php to show the next survey
            header("Location: lesson.php?lesson_id=" . urlencode($lesson_id) . "&result_id=" . urlencode($result_id) . "&show_survey=true");
            exit();
        } else {
            $_SESSION['error'] = "Failed to submit survey. Please try again.";
        }
    } else {
        $_SESSION['error'] = "Please provide a valid rating.";
    }
}

// Check if the video has been watched
$progressStmt = $pdo->prepare("SELECT video_watched FROM lesson_progress WHERE user_id = :user_id AND lesson_id = :lesson_id");
$progressStmt->execute([':user_id' => $user_id, ':lesson_id' => $lesson_id]);
$progress = $progressStmt->fetch(PDO::FETCH_ASSOC);
$videoWatched = $progress && $progress['video_watched'] == 1;

// Fetch lesson details
$stmt = $pdo->prepare("SELECT * FROM lessons WHERE id = :lesson_id");
$stmt->execute([':lesson_id' => $lesson_id]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);
$rowCount = $lesson ? 1 : 0;

// Fetch all lessons in the same module for the progress tracker
if ($lesson) {
  $module_id = $lesson['module_id'];
  $lessonsStmt = $pdo->prepare("
    SELECT lessons.id, lessons.title, 
           (SELECT video_watched FROM lesson_progress WHERE user_id = :user_id AND lesson_id = lessons.id) as video_watched,
           (SELECT isWatched FROM quiz_results WHERE user_id = :user_id AND lesson_id = lessons.id ORDER BY taken_at DESC LIMIT 1) as is_completed
    FROM lessons 
    WHERE module_id = :module_id
    ORDER BY id
  ");
  $lessonsStmt->execute([':user_id' => $user_id, ':module_id' => $module_id]);
  $lessons = $lessonsStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
  $lessons = [];
}

// Fetch quiz questions
$quizStmt = $pdo->prepare("SELECT * FROM quizzes WHERE lesson_id = :lesson_id AND status = 'active'");
$quizStmt->execute([':lesson_id' => $lesson_id]);
$questions = $quizStmt->fetchAll(PDO::FETCH_ASSOC);
$quizItem = count($questions);
$totalQuestions = count($questions);

// Shuffle questions
$shuffleQuestions = $questions;
shuffle($shuffleQuestions);

// Get survey data if available
$result_id = $_GET['result_id'] ?? null;
$show_survey = isset($_GET['show_survey']) && $_GET['show_survey'] === 'true';
$available_surveys = $_SESSION['available_surveys'] ?? [];
$survey = !empty($available_surveys) ? reset($available_surveys) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($lesson['title'] ?? 'Lesson') ?></title>
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
            progress: {
              100: "#eef2ff",
              200: "#e0e7ff",
              300: "#c7d2fe",
              500: "#6366f1",
            },
          },
        },
      },
    };
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    .fade {
      transition: opacity 0.3s ease-in-out;
    }
    .hidden {
      opacity: 0;
      pointer-events: none;
    }
    .visible {
      opacity: 1;
      pointer-events: auto;
    }
    .video-container {
      position: relative;
    }
    .video-player-wrapper {
      position: relative;
      margin-bottom: 1rem;
    }
    .button-container {
      display: flex;
      justify-content: flex-end;
      gap: 1rem;
      margin-top: 1rem;
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
            <a href="modules_list.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-600 font-medium transition-all duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
              </svg>
              Modules
            </a>
          </li>
          <li>
            <a href="results.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
              </svg>
              View Results
            </a>
          </li>
          <li>
            <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-red-50 hover:text-red-600 font-medium transition-all duration-200">
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
          <h1 class="text-xl font-semibold text-dashboard"><?= htmlspecialchars($lesson['title'] ?? 'Lesson') ?></h1>
        </div>
      </header>

      <!-- Main Content -->
      <main class="flex-1 p-6 overflow-y-auto bg-gray-100">
        <div class="max-w-7xl mx-auto">
          <!-- Loader -->
          <div class="loader-wrapper fixed inset-0 bg-gray-100 bg-opacity-50 flex items-center justify-center z-50 pointer-events-none">
            <div class="animate-pulse rounded-full h-12 w-12 bg-primary-600"></div>
          </div>

          <div class="lesson-quiz-container flex flex-col lg:flex-row gap-6">

            <!-- Lesson Progress Tracker -->
            <div class="w-full lg:w-1/4 bg-white rounded-xl shadow-md p-4 border border-gray-200">
              <h3 class="text-lg font-semibold text-progress-500 mb-4 flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Lesson Progress
              </h3>
              <div class="relative space-y-6">
                <?php foreach ($lessons as $index => $tracked_lesson): ?>
                  <div class="relative flex items-start gap-4">
                    <!-- Connector Line -->
                    <?php if ($index < count($lessons) - 1): ?>
                      <div class="absolute left-3 top-[2.25rem] h-[3.25rem] w-1 bg-gray-200"></div>
                    <?php endif; ?>
                    <!-- Progress Circle -->
                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-600 font-medium <?php echo $tracked_lesson['id'] == $lesson_id ? 'bg-progress-500 text-white' : ($tracked_lesson['is_completed'] ? 'bg-progress-500 text-white' : ($tracked_lesson['video_watched'] ? 'bg-yellow-500 text-white' : '')); ?>">
                      <?php if ($tracked_lesson['is_completed'] || $tracked_lesson['id'] == $lesson_id): ?>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 1 0 000-1.414z" clip-rule="evenodd" />
                        </svg>
                      <?php elseif ($tracked_lesson['video_watched']): ?>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                          <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                        </svg>
                      <?php else: ?>
                        <span><?php echo $index + 1; ?></span>
                      <?php endif; ?>
                    </div>
                    <!-- Lesson Title and Status -->
                    <div class="flex-1">
                      <p class="text-sm font-medium text-gray-800 <?php echo $tracked_lesson['id'] == $lesson_id ? 'text-progress-500' : ''; ?> line-clamp-2"><?php echo htmlspecialchars($tracked_lesson['title']); ?></p>
                      <?php if ($tracked_lesson['id'] == $lesson_id): ?>
                        <span class="inline-flex items-center mt-1 px-2 py-1 text-xs font-medium text-red-500 bg-progress-100 rounded-full">
                          <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1"></span> Now Playing
                        </span>
                      <?php elseif ($tracked_lesson['is_completed']): ?>
                        <span class="inline-flex items-center mt-1 px-2 py-1 text-green-600 bg-green-50 text-xs font-medium rounded-full">
                          <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 1 0 000-1.414z" clip-rule="evenodd" />
                          </svg> Completed
                        </span>
                      <?php elseif ($tracked_lesson['video_watched']): ?>
                        <span class="inline-flex items-center mt-1 px-2 py-1 text-yellow-600 bg-yellow-50 text-xs font-medium rounded-full">
                          <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                          </svg> Video Watched
                        </span>
                      <?php else: ?>
                        <span class="inline-flex items-center mt-1 px-2 py-1 text-xs font-medium text-gray-500 bg-gray-100 rounded-full">
                          <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg> Not Started
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Video and Quiz Container -->
            <div class="w-full lg:w-3/4">
              <?php if ($quizItem > 0): ?>
                <div class="video-container bg-white rounded-lg shadow-md p-6 border border-gray-200 fade <?php echo $videoWatched ? 'hidden' : 'visible'; ?>" id="video-container">
                  <?php if ($rowCount > 0): ?>
                    <div class="video-player-wrapper">
                      <video id="lesson_video" controls controlsList="nodownload noplaybackrate" disablePictureInPicture oncontextmenu="return false;" class="w-full h-auto aspect-video">
                        <source src="<?= htmlspecialchars($lesson['video_url']) ?>" type="video/mp4">
                        Your browser does not support the video tag.
                      </video>
                    </div>
                    <!-- Buttons (Initially Hidden Until Video Ends) -->
                    <div id="proceed-to-quiz" class="button-container hidden fade">
                      <button id="proceed-button" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-colors duration-200" aria-label="Take quiz after watching the lesson video">
                        Take Quiz
                      </button>
                      <a href="modules_list.php" class="px-6 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400 transition-colors duration-200" title="Return to modules without taking the quiz. Your video progress is saved.">
                        Back to Modules
                      </a>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">You can return to modules without taking the quiz. Your video progress is saved.</p>
                  <?php else: ?>
                    <div class="text-center py-12">
                      <p class="text-gray-600 text-lg font-medium">No lesson uploaded yet for this module.</p>
                      <a href="modules_list.php" class="mt-4 inline-block px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">Back to Modules</a>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Options for Rewatch or Proceed (Shown if Video Watched) -->
                <div id="options-container" class="bg-white rounded-lg shadow-md border border-gray-200 fade <?php echo $videoWatched ? 'visible' : 'hidden'; ?>">
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 gap-4">
                    <h3 class="text-lg font-semibold text-gray-800">You’ve watched this lesson video</h3>
                    <div class="flex flex-wrap gap-3">
                      <button id="rewatch-button" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors duration-200">
                        Rewatch Video
                      </button>
                      <button id="proceed-to-quiz-direct" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-colors duration-200">
                        Proceed to Quiz
                      </button>
                      <a href="modules_list.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400 transition-colors duration-200" title="Return to modules without taking the quiz. Your video progress is saved.">
                        Back to Modules
                      </a>
                    </div>
                  </div>
                  <p class="text-sm text-gray-500 px-4 pb-3">You can return to modules without taking the quiz. Your video progress is saved.</p>
                </div>

                <div id="quiz-container" class="quiz-container bg-white rounded-lg shadow-md p-6 hidden fade">
                  <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= $totalQuestions ?> Questions</h3>
                  <form action="handle_quiz_submit.php" method="POST" id="quiz-form">
                    <input type="hidden" name="lesson_id" value="<?= $lesson_id ?>">
                    <?php foreach ($shuffleQuestions as $index => $question): ?>
                      <div class="questions-container mb-6">
                        <p class="text-base font-medium text-gray-800 mb-3"><?= ($index + 1) . '. ' . htmlspecialchars($question['question']) ?></p>
                        <?php foreach (['A', 'B', 'C', 'D'] as $letter): ?>
                          <label class="option flex items-center gap-2 p-3 rounded-lg hover:bg-gray-100 cursor-pointer transition-colors duration-200">
                            <input type="radio" name="answers[<?= $question['id'] ?>]" value="<?= $letter ?>" class="text-primary-600 focus:ring-primary-600">
                            <span class="text-sm text-gray-700"><?= $letter ?>. <?= htmlspecialchars($question["option_" . strtolower($letter)]) ?></span>
                          </label>
                        <?php endforeach; ?>
                      </div>
                    <?php endforeach; ?>
                    <?php if ($quizItem > 0): ?>
                      <div class="flex justify-end">
                        <button type="button" id="fake-submit-button" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-colors duration-200">
                          Submit
                        </button>
                      </div>
                    <?php endif; ?>
                  </form>

                  <!-- Modal -->
                  <div id="confirmation-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center hidden z-50">
                    <div class="bg-white rounded-lg p-6 max-w-sm w-full">
                      <p class="text-gray-800 text-base mb-4">Are you sure you want to submit your answers?</p>
                      <div class="flex justify-end gap-3">
                        <button type="submit" form="quiz-form" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-600">Yes, Submit</button>
                        <button type="button" id="cancel-modal" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400">Cancel</button>
                      </div>
                    </div>
                  </div>
                </div>

                <div id="waiting" class="text-center text-sm text-gray-500 mt-4 <?php echo $videoWatched ? 'hidden' : ''; ?>">
                  Please watch the video to proceed to the quiz.
                </div>
              <?php else: ?>
                <div class="bg-white rounded-lg shadow-md p-6 text-center">
                  <p class="text-gray-600 text-lg">This lesson doesn't have any quiz.</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Survey Modal -->
        <?php if ($survey && $show_survey && $result_id): ?>
          <div id="survey-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
              <h2 class="text-lg font-semibold text-dashboard mb-4"><?php echo htmlspecialchars($survey['title']); ?></h2>
              <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
                  <?php echo htmlspecialchars($_SESSION['error']); ?>
                  <?php unset($_SESSION['error']); ?>
                </div>
              <?php endif; ?>
              <form method="POST">
                <input type="hidden" name="survey_id" value="<?php echo $survey['id']; ?>">
                <input type="hidden" name="result_id" value="<?php echo htmlspecialchars($result_id); ?>">
                <div class="mb-4">
                  <label class="block text-sm font-medium text-gray-700"><?php echo htmlspecialchars($survey['description'] ?? 'Please provide your feedback for this lesson.'); ?></label>
                </div>
                <div class="mb-4">
                  <label class="block text-sm font-medium text-gray-700">Rating (1-5)</label>
                  <div class="flex gap-2 mt-2">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <label class="flex items-center gap-1">
                        <input type="radio" name="rating" value="<?php echo $i; ?>" class="text-primary-600 focus:ring-primary-600" required>
                        <span><?php echo $i; ?></span>
                      </label>
                    <?php endfor; ?>
                  </div>
                </div>
                <div class="mb-4">
                  <label for="feedback" class="block text-sm font-medium text-gray-700">Feedback (Optional)</label>
                  <textarea name="feedback" id="feedback" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-600 focus:ring focus:ring-primary-600 focus:ring-opacity-50" rows="4"></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                  <a href="quiz_result.php?result_id=<?php echo urlencode($result_id); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Skip</a>
                  <button type="submit" name="submit_survey" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">Submit</button>
                </div>
              </form>
            </div>
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

    // Video and quiz logic
    document.addEventListener('DOMContentLoaded', function() {
      const video = document.getElementById('lesson_video');
      const videoContainer = document.getElementById('video-container');
      const optionsContainer = document.getElementById('options-container');
      const quizForm = document.getElementById('quiz-container');
      const waitingMsg = document.getElementById('waiting');
      const proceedContainer = document.getElementById('proceed-to-quiz');
      const proceedButton = document.getElementById('proceed-button');
      const rewatchButton = document.getElementById('rewatch-button');
      const proceedToQuizDirect = document.getElementById('proceed-to-quiz-direct');

      if (video) {
        video.addEventListener('ended', () => {
          setTimeout(() => {
            waitingMsg.classList.add("hidden");
            proceedContainer.classList.remove("hidden");
            proceedContainer.classList.add("visible");

            // Mark video as watched via AJAX
            fetch('mark_video_watched.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `lesson_id=${encodeURIComponent(<?= $lesson_id ?>)}`
            }).catch(error => console.error('Error marking video as watched:', error));
          }, 1000);
        });

        if (proceedButton) {
          proceedButton.addEventListener('click', () => {
            videoContainer.classList.remove("visible");
            videoContainer.classList.add("hidden");
            quizForm.classList.remove("hidden");
            quizForm.classList.add("visible");
            if (optionsContainer) {
              optionsContainer.classList.remove("visible");
              optionsContainer.classList.add("hidden");
            }
          });
        }
      }

      if (rewatchButton) {
        rewatchButton.addEventListener('click', () => {
          videoContainer.classList.remove("hidden");
          videoContainer.classList.add("visible");
          optionsContainer.classList.remove("visible");
          optionsContainer.classList.add("hidden");
          waitingMsg.classList.remove("hidden");
          proceedContainer.classList.add("hidden");
          proceedContainer.classList.remove("visible");
        });
      }

      if (proceedToQuizDirect) {
        proceedToQuizDirect.addEventListener('click', () => {
          videoContainer.classList.remove("visible");
          videoContainer.classList.add("hidden");
          optionsContainer.classList.remove("visible");
          optionsContainer.classList.add("hidden");
          quizForm.classList.remove("hidden");
          quizForm.classList.add("visible");
        });
      }

      // Loader
      const loader = document.querySelector('.loader-wrapper');
      if (loader) {
        setTimeout(() => {
          loader.classList.add('hidden');
        }, 500);
      }

      // Submit button and modal
      const submitBtn = document.getElementById('fake-submit-button');
      const modal = document.getElementById('confirmation-modal');
      const cancelBtn = document.getElementById('cancel-modal');

      if (submitBtn) {
        submitBtn.addEventListener('click', () => {
          modal.classList.remove('hidden');
        });
      }

      if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
          modal.classList.add('hidden');
        });
      }

      // Radio button styling
      const radioButtons = document.querySelectorAll('.questions-container input[type="radio"]');
      radioButtons.forEach(radio => {
        radio.addEventListener('change', () => {
          const container = radio.closest('.questions-container');
          container.querySelectorAll('.option').forEach(label => {
            label.classList.remove('bg-primary-50', 'text-primary-600');
          });
          radio.parentElement.classList.add('bg-primary-50', 'text-primary-600');
        });
      });
    });
  </script>
</body>
</html>