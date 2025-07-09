<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';

$lesson_id = $_GET['lesson_id'];
$lessonStmt = $pdo->prepare("SELECT l.*, m.id as module_id, m.title as module_title FROM lessons l JOIN modules m ON l.module_id = m.id WHERE l.id = :lesson_id");
$lessonStmt->execute([':lesson_id' => $lesson_id]);
$lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $question = filter($_POST['question']);
  $option_a = filter($_POST['option_a']);
  $option_b = filter($_POST['option_b']);
  $option_c = filter($_POST['option_c']);
  $option_d = filter($_POST['option_d']);
  $correct_option = $_POST['correct_option'];

  if (empty($question)) {
    $errors['question'] = 'Question is required';
  }
  if (empty($option_a)) {
    $errors['option_a'] = 'Option A is required';
  }
  if (empty($option_b)) {
    $errors['option_b'] = 'Option B is required';
  }
  if (empty($option_c)) {
    $errors['option_c'] = 'Option C is required';
  }
  if (empty($option_d)) {
    $errors['option_d'] = 'Option D is required';
  }
  if (empty($correct_option) || !in_array($correct_option, ['A', 'B', 'C', 'D'])) {
    $errors['correct_option'] = 'Please select a valid correct answer';
  }

  if (empty($errors)) {
    try {
      $stmt = $pdo->prepare("INSERT INTO quizzes (lesson_id, question, option_a, option_b, option_c, option_d, correct_option) VALUES (:lesson_id, :question, :option_a, :option_b, :option_c, :option_d, :correct_option)");
      $stmt->execute([
        ':lesson_id' => $lesson_id,
        ':question' => $question,
        ':option_a' => $option_a,
        ':option_b' => $option_b,
        ':option_c' => $option_c,
        ':option_d' => $option_d,
        ':correct_option' => $correct_option
      ]);
      $_SESSION['success_message'] = 'Quiz added successfully!';
      header("Location: edit_quizzes.php?lesson_id=$lesson_id");
      exit;
    } catch (\PDOException $e) {
      $errors['database'] = "Database Error: " . $e->getMessage();
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Quiz for <?= htmlspecialchars($lesson['title']) ?></title>
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
          <button id="sidebar-toggle" class="md:hidden text-gray-600 hover:text-primary-600 focus:outline-none transition-all duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
          </button>
          <h1 class="text-2xl font-semibold text-dashboard">Add Quiz: <?= htmlspecialchars($lesson['title']) ?></h1>
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
            <li>
              <a href="edit_quizzes.php?lesson_id=<?= htmlspecialchars($lesson_id) ?>" class="hover:text-primary-600 transition-all duration-200">Quizzes</a>
            </li>
            <li><span class="text-gray-400">/</span></li>
            <li class="text-gray-900 font-medium">Add Quiz</li>
          </ol>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Form Section -->
          <div class="lg:col-span-2 bg-white p-8 rounded-xl shadow-soft">
            <form class="space-y-6" method="POST">
              <?php if (isset($errors['database'])) : ?>
                <p class="text-red-500 text-sm font-medium bg-red-50 p-3 rounded-lg"><?= htmlspecialchars($errors['database']) ?></p>
              <?php endif; ?>

              <div class="space-y-2">
                <label for="question" class="block text-sm font-medium text-gray-700">Question <span class="text-red-500">*</span></label>
                <textarea id="question" name="question" placeholder="Enter quiz question" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all duration-200 min-h-[100px]"><?php echo isset($_POST['question']) ? htmlspecialchars($_POST['question']) : ''; ?></textarea>
                <?php if (isset($errors['question'])) : ?>
                  <p class="text-red-500 text-xs font-medium mt-1"><?= htmlspecialchars($errors['question']) ?></p>
                <?php endif; ?>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <label for="option_a" class="block text-sm font-medium text-gray-700">Option A <span class="text-red-500">*</span></label>
                  <input type="text" id="option_a" name="option_a" placeholder="Enter Option A" value="<?php echo isset($_POST['option_a']) ? htmlspecialchars($_POST['option_a']) : ''; ?>" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all duration-200">
                  <?php if (isset($errors['option_a'])) : ?>
                    <p class="text-red-500 text-xs font-medium mt-1"><?= htmlspecialchars($errors['option_a']) ?></p>
                  <?php endif; ?>
                </div>
                <div class="space-y-2">
                  <label for="option_b" class="block text-sm font-medium text-gray-700">Option B <span class="text-red-500">*</span></label>
                  <input type="text" id="option_b" name="option_b" placeholder="Enter Option B" value="<?php echo isset($_POST['option_b']) ? htmlspecialchars($_POST['option_b']) : ''; ?>" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all duration-200">
                  <?php if (isset($errors['option_b'])) : ?>
                    <p class="text-red-500 text-xs font-medium mt-1"><?= htmlspecialchars($errors['option_b']) ?></p>
                  <?php endif; ?>
                </div>
                <div class="space-y-2">
                  <label for="option_c" class="block text-sm font-medium text-gray-700">Option C <span class="text-red-500">*</span></label>
                  <input type="text" id="option_c" name="option_c" placeholder="Enter Option C" value="<?php echo isset($_POST['option_c']) ? htmlspecialchars($_POST['option_c']) : ''; ?>" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all duration-200">
                  <?php if (isset($errors['option_c'])) : ?>
                    <p class="text-red-500 text-xs font-medium mt-1"><?= htmlspecialchars($errors['option_c']) ?></p>
                  <?php endif; ?>
                </div>
                <div class="space-y-2">
                  <label for="option_d" class="block text-sm font-medium text-gray-700">Option D <span class="text-red-500">*</span></label>
                  <input type="text" id="option_d" name="option_d" placeholder="Enter Option D" value="<?php echo isset($_POST['option_d']) ? htmlspecialchars($_POST['option_d']) : ''; ?>" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all duration-200">
                  <?php if (isset($errors['option_d'])) : ?>
                    <p class="text-red-500 text-xs font-medium mt-1"><?= htmlspecialchars($errors['option_d']) ?></p>
                  <?php endif; ?>
                </div>
              </div>

              <div class="space-y-2">
                <label for="correct_option" class="block text-sm font-medium text-gray-700">Correct Answer <span class="text-red-500">*</span></label>
                <select id="correct_option" name="correct_option" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all duration-200">
                  <option value="" <?php echo !isset($_POST['correct_option']) ? 'selected' : ''; ?>>Select Correct Answer</option>
                  <option value="A" <?php echo isset($_POST['correct_option']) && $_POST['correct_option'] === 'A' ? 'selected' : ''; ?>>A</option>
                  <option value="B" <?php echo isset($_POST['correct_option']) && $_POST['correct_option'] === 'B' ? 'selected' : ''; ?>>B</option>
                  <option value="C" <?php echo isset($_POST['correct_option']) && $_POST['correct_option'] === 'C' ? 'selected' : ''; ?>>C</option>
                  <option value="D" <?php echo isset($_POST['correct_option']) && $_POST['correct_option'] === 'D' ? 'selected' : ''; ?>>D</option>
                </select>
                <?php if (isset($errors['correct_option'])) : ?>
                  <p class="text-red-500 text-xs font-medium mt-1"><?= htmlspecialchars($errors['correct_option']) ?></p>
                <?php endif; ?>
              </div>

              <div class="flex justify-end space-x-4">
                <a href="edit_quizzes.php?lesson_id=<?= htmlspecialchars($lesson_id) ?>" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium transition-all duration-200 ease-in-out transform hover:scale-105">Cancel</a>
                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg hover:from-primary-700 hover:to-primary-800 font-medium transition-all duration-200 ease-in-out transform hover:scale-105">Add Quiz</button>
              </div>
            </form>
          </div>

          <!-- Lesson Details and Actions -->
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
                  <span class="text-sm font-medium text-gray-900"><?php
                    $quizCountStmt = $pdo->prepare("SELECT COUNT(*) as quiz_count FROM quizzes WHERE lesson_id = :lesson_id");
                    $quizCountStmt->execute([':lesson_id' => $lesson_id]);
                    echo $quizCountStmt->fetch(PDO::FETCH_ASSOC)['quiz_count'];
                  ?></span>
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
                <a href="edit_quizzes.php?lesson_id=<?= htmlspecialchars($lesson_id) ?>" class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium transition-all duration-200">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                  </svg>
                  Back to Quizzes
                </a>
                <a href="lessons.php?module_id=<?= htmlspecialchars($lesson['module_id']) ?>" class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium transition-all duration-200">
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