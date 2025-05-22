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

// Check if the user has watched the video
$watchStmt = $pdo->prepare("
    SELECT isWatched
    FROM quiz_results
    WHERE user_id = :user_id AND lesson_id = :lesson_id
    ORDER BY taken_at DESC
    LIMIT 1
");
$watchStmt->execute([':user_id' => $user_id, ':lesson_id' => $lesson_id]);
$watchResult = $watchStmt->fetch(PDO::FETCH_ASSOC);
$isWatched = $watchResult && $watchResult['isWatched'] == 1;
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
          <div class="loader-wrapper hidden fixed inset-0 bg-gray-100 bg-opacity-50 flex items-center justify-center z-50">
            <div class="animate-spin rounded-full h-12 w-12 border-t-4 border-primary-600 border-solid"></div>
          </div>

          <?php if ($quizItem > 0): ?>
            <div class="flex flex-col lg:flex-row gap-6">
              <!-- Lesson Progress Tracker -->
              <div class="w-full lg:w-1/4 bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-dashboard mb-6 flex items-center gap-2">
                  <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Lesson Progress
                </h3>
                <div class="relative space-y-6">
                  <?php foreach ($lessons as $index => $tracked_lesson): ?>
                    <div class="relative flex items-start gap-4 group">
                      <?php if ($index < count($lessons) - 1): ?>
                        <div class="absolute left-5 top-12 h-[calc(100%-2.5rem)] w-0.5 bg-gray-200 group-last:hidden <?php echo $tracked_lesson['is_completed'] ? 'bg-primary-600' : ''; ?>"></div>
                      <?php endif; ?>
                      <div class="w-10 h-10 flex items-center justify-center rounded-full transition-all duration-300 z-10 <?php echo $tracked_lesson['id'] == $lesson_id ? 'bg-primary-600 shadow-lg scale-110' : ($tracked_lesson['is_completed'] ? 'bg-primary-600' : 'bg-gray-200'); ?>">
                        <?php if ($tracked_lesson['is_completed'] || $tracked_lesson['id'] == $lesson_id): ?>
                          <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 1 0 000-1.414z" clip-rule="evenodd" />
                          </svg>
                        <?php else: ?>
                          <span class="text-gray-600 font-semibold"><?php echo $index + 1; ?></span>
                        <?php endif; ?>
                      </div>
                      <div class="flex-1 pt-2">
                        <p class="text-sm font-medium <?php echo $tracked_lesson['id'] == $lesson_id ? 'text-primary-600 font-bold' : 'text-gray-800'; ?> group-hover:text-primary-600 transition-colors duration-300"><?php echo htmlspecialchars($tracked_lesson['title']); ?></p>
                        <?php if ($tracked_lesson['id'] == $lesson_id): ?>
                          <p class="text-xs text-primary-600 mt-1 font-medium">Now Playing</p>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Video Section -->
              <div class="w-full lg:w-3/4">
                <div class="video-container bg-white rounded-lg shadow-md p-6 border border-gray-200 <?php echo $isWatched ? 'hidden' : ''; ?>">
                  <?php if ($rowCount > 0): ?>
                    <div class="relative w-full">
                      <div class="relative rounded-md overflow-hidden border border-gray-300">
                        <video id="lesson_video" controls controlsList="nodownload noplaybackrate" disablePictureInPicture oncontextmenu="return false;" class="w-full h-auto aspect-video">
                          <source src="<?php echo htmlspecialchars($lesson['video_url']); ?>" type="video/mp4">
                          Your browser does not support the video tag.
                        </video>
                      </div>
                      <div class="mt-4">
                        <h3 class="text-lg font-semibold text-dashboard"><?php echo htmlspecialchars($lesson['title'] ?? 'Lesson'); ?></h3>
                        <p class="text-sm text-gray-600 mt-2">Learn about sustainability and its impact on the environment. Complete this video to unlock the quiz.</p>
                        <?php if (!$isWatched): ?>
                          <div class="mt-3">
                            <p id="waiting" class="text-sm text-gray-500">Please watch the video to proceed to the quiz.</p>
                          </div>
                        <?php endif; ?>
                        <div class="mt-4">
                          <div class="w-full bg-gray-200 rounded-full h-2">
                            <div id="video-progress" class="bg-primary-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                          </div>
                          <p class="text-xs text-gray-600 mt-1">Progress: <span id="progress-text">0%</span></p>
                        </div>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="text-center py-12">
                      <p class="text-gray-600 text-lg font-medium">No lesson uploaded yet for this module.</p>
                      <a href="modules_list.php" class="mt-4 inline-block px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">Back to Modules</a>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Quiz Section -->
                <div id="quiz-container" class="quiz-container bg-white rounded-lg shadow-md p-6 <?php echo $isWatched ? '' : 'hidden'; ?>">
                  <h3 class="text-lg font-semibold text-gray-800 mb-4"><?php echo $totalQuestions; ?> Questions</h3>
                  <form action="handle_quiz_submit.php" method="POST" id="quiz-form">
                    <input type="hidden" name="lesson_id" value="<?php echo $lesson_id; ?>">
                    <?php foreach ($shuffleQuestions as $index => $question): ?>
                      <div class="questions-container mb-6">
                        <p class="text-base font-medium text-gray-800 mb-3"><?php echo ($index + 1) . '. ' . htmlspecialchars($question['question']); ?></p>
                        <?php foreach (['A', 'B', 'C', 'D'] as $letter): ?>
                          <label class="option flex items-center gap-2 p-3 rounded-lg hover:bg-gray-100 cursor-pointer transition-colors duration-200">
                            <input type="radio" name="answers[<?php echo $question['id']; ?>]" value="<?php echo $letter; ?>" class="text-primary-600 focus:ring-primary-600">
                            <span class="text-sm text-gray-700"><?php echo $letter; ?>. <?php echo htmlspecialchars($question["option_" . strtolower($letter)]); ?></span>
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
              </div>
            </div>
          <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
              <p class="text-gray-600 text-lg">This lesson doesn't have any quiz.</p>
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
      const videoContainer = document.querySelector('.video-container');
      const quizForm = document.getElementById('quiz-container');
      const waitingMsg = document.getElementById('waiting');
      const isWatched = <?php echo json_encode($isWatched); ?>;
      const progressBar = document.getElementById('video-progress');
      const progressText = document.getElementById('progress-text');

      // Prevent video seeking
      if (video) {
        video.addEventListener('seeking', (e) => {
          if (video.currentTime > video.played.end(0)) {
            video.currentTime = video.played.end(0);
          }
        });

        // Update isWatched when video ends
        video.addEventListener('ended', () => {
          fetch('update_watch_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `lesson_id=<?php echo $lesson_id; ?>&user_id=<?php echo $user_id; ?>`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              videoContainer.classList.add("hidden");
              quizForm.classList.remove("hidden");
              waitingMsg.classList.add("hidden");
            } else {
              console.error('Failed to update watch status:', data.message);
            }
          })
          .catch(error => console.error('Error:', error));
        });

        // Video Progress Bar
        video.addEventListener('timeupdate', () => {
          const progress = (video.currentTime / video.duration) * 100;
          progressBar.style.width = `${progress}%`;
          progressText.textContent = `${Math.round(progress)}%`;
        });
      }

      // Loader
      const loader = document.querySelector('.loader-wrapper');
      if (loader) {
        setTimeout(() => {
          loader.classList.add('hidden');
        }, 1500);
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