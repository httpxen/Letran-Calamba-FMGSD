<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "User") {
    header("Location: login.php");
    exit();
}

$result_id = $_GET['result_id'] ?? null;
$lesson_id = $_GET['lesson_id'] ?? null;
$user_id = $_SESSION['user_id'];
$available_surveys = $_SESSION['available_surveys'] ?? [];

if (!$result_id || !$lesson_id || empty($available_surveys)) {
    header("Location: quiz_result.php?result_id=" . urlencode($result_id));
    exit();
}

// Get the first available survey
$survey = reset($available_surveys);

// Check if form is submitted
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

            // If more surveys are available, reload the page; otherwise, redirect to quiz results
            if (!empty($_SESSION['available_surveys'])) {
                header("Location: survey_popup.php?result_id=" . urlencode($result_id) . "&lesson_id=" . urlencode($lesson_id));
            } else {
                unset($_SESSION['available_surveys']);
                header("Location: quiz_result.php?result_id=" . urlencode($result_id));
            }
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
    <title>Survey Feedback</title>
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
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased">
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
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
</body>
</html>