<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "User") {
    header("Location: login.php");
    exit();
}

$result_id = $_POST['result_id'] ?? $_GET['result_id'] ?? null;
$lesson_id = $_POST['lesson_id'] ?? $_GET['lesson_id'] ?? null;
$user_id = $_SESSION['user_id'];
$available_surveys = $_SESSION['available_surveys'] ?? [];

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
            $_SESSION['available_surveys'] = array_values($_SESSION['available_surveys']); // Reindex array

            // If more surveys are available, reload dashboard with survey trigger
            if (!empty($_SESSION['available_surveys'])) {
                header("Location: dashboard.php?show_survey=1&result_id=" . urlencode($result_id) . "&lesson_id=" . urlencode($lesson_id));
            } else {
                // No more surveys, clear session and go to quiz results
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

    // Redirect back to dashboard with survey trigger if submission fails
    header("Location: dashboard.php?show_survey=1&result_id=" . urlencode($result_id) . "&lesson_id=" . urlencode($lesson_id));
    exit();
} elseif (isset($_POST['skip_survey']) || isset($_GET['skip_survey'])) {
    // Handle survey skip
    $survey_id = $_POST['survey_id'] ?? $_GET['survey_id'] ?? null;
    if ($survey_id) {
        // Remove the skipped survey from the session
        $_SESSION['available_surveys'] = array_filter($available_surveys, function($s) use ($survey_id) {
            return $s['id'] != $survey_id;
        });
        $_SESSION['available_surveys'] = array_values($_SESSION['available_surveys']); // Reindex array
    }

    // If more surveys are available, reload dashboard with survey trigger
    if (!empty($_SESSION['available_surveys'])) {
        header("Location: dashboard.php?show_survey=1&result_id=" . urlencode($result_id) . "&lesson_id=" . urlencode($lesson_id));
    } else {
        // No more surveys, clear session and go to quiz results
        unset($_SESSION['available_surveys']);
        header("Location: quiz_result.php?result_id=" . urlencode($result_id));
    }
    exit();
} else {
    // Invalid request
    header("Location: dashboard.php");
    exit();
}
?>