<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: ../index.php");
    exit();
}

$lesson_id = $_POST['lesson_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;
$answers = $_POST['answers'] ?? [];

if (!$lesson_id || !$user_id || empty($answers)) {
    $_SESSION['error'] = "Missing required data. Please try again.";
    header("Location: lesson.php?lesson_id=" . urlencode($lesson_id));
    exit();
}

// Fetch lesson and module details for email
$stmt = $pdo->prepare("
    SELECT l.title AS lesson_title, m.title AS module_title 
    FROM lessons l 
    JOIN modules m ON l.module_id = m.id 
    WHERE l.id = :lesson_id
");
$stmt->execute([':lesson_id' => $lesson_id]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    $_SESSION['error'] = "Lesson not found.";
    header("Location: dashboard.php");
    exit();
}

// Fetch user details for email
$stmt = $pdo->prepare("SELECT fullname, email FROM users WHERE id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: dashboard.php");
    exit();
}

// Fetch quiz questions
$stmt = $pdo->prepare("SELECT id, correct_option FROM quizzes WHERE lesson_id = :lesson_id AND status = 'active'");
$stmt->execute([':lesson_id' => $lesson_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    $_SESSION['error'] = "No active quiz questions found for this lesson.";
    header("Location: lesson.php?lesson_id=" . urlencode($lesson_id));
    exit();
}

$score = 0;
$total = count($questions);
$passingScore = ceil($total * 0.5); // 50% passing score

// Calculate score and save answers
foreach ($questions as $question) {
    $question_id = $question['id'];
    if (isset($answers[$question_id]) && strtoupper($answers[$question_id]) === strtoupper($question['correct_option'])) {
        $score++;
    }

    // Save user's answer
    if (isset($answers[$question_id]) && in_array(strtoupper($answers[$question_id]), ['A', 'B', 'C', 'D'])) {
        $stmt_answer = $pdo->prepare("
            INSERT INTO user_quiz_answers (user_id, quiz_id, selected_option)
            VALUES (:user_id, :quiz_id, :selected_option)
        ");
        $stmt_answer->execute([
            ':user_id' => $user_id,
            ':quiz_id' => $question_id,
            ':selected_option' => strtoupper($answers[$question_id])
        ]);
    }
}

// Save quiz result
$isPassed = ($score >= $passingScore) ? 1 : 0;
$stmt = $pdo->prepare("
    INSERT INTO quiz_results (user_id, lesson_id, score, totalItems, isPassed, isWatched)
    VALUES (:user_id, :lesson_id, :score, :totalItems, :isPassed, :isWatched)
");
$success = $stmt->execute([
    ':user_id' => $user_id,
    ':lesson_id' => $lesson_id,
    ':score' => $score,
    ':totalItems' => $total,
    ':isPassed' => $isPassed,
    ':isWatched' => 1
]);

if ($success) {
    $result_id = $pdo->lastInsertId();

    // Send email with quiz results
    $to = $user['email'];
    $subject = "Your Quiz Results for {$lesson['lesson_title']}";
    $pass_status = $isPassed ? 'Passed' : 'Failed';
    $message = "Dear {$user['fullname']},\n\n";
    $message .= "You have completed the quiz for the following lesson:\n";
    $message .= "Module: {$lesson['module_title']}\n";
    $message .= "Lesson: {$lesson['lesson_title']}\n\n";
    $message .= "Your Results:\n";
    $message .= "Score: {$score} out of {$total}\n";
    $message .= "Percentage: " . number_format(($score / $total) * 100, 2) . "%\n";
    $message .= "Status: {$pass_status}\n\n";
    $message .= "Thank you for participating!\n";
    $message .= "Best regards,\nLetran System Team";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'opulenciaandrei23@gmail.com';
        $mail->Password = 'pkou mbww kqgc hgrh';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('no-reply@letransystem.com', 'Letran System');
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $message;

        $email_sent = $mail->send();
        $_SESSION['success'] = $email_sent
            ? "Quiz submitted successfully! Results have been sent to your email."
            : "Quiz submitted, but email could not be sent.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Quiz submitted, but email could not be sent. Error: {$mail->ErrorInfo}";
    }

    // Store result details in session for quiz_result.php
    $_SESSION['quiz_result'] = [
        'result_id' => $result_id,
        'score' => $score,
        'total' => $total,
        'isPassed' => $isPassed,
        'lesson_title' => $lesson['lesson_title'],
        'module_title' => $lesson['module_title'],
        'lesson_id' => $lesson_id
    ];

    // Check for active surveys
        $stmt = $pdo->prepare("SELECT id, title, description FROM surveys WHERE status = 'active'");
        $stmt->execute();
        $active_surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if user has already responded to surveys for this lesson
        $stmt = $pdo->prepare("SELECT survey_id FROM survey_responses WHERE user_id = :user_id AND survey_id IN (SELECT id FROM surveys WHERE status = 'active')");
        $stmt->execute([':user_id' => $user_id]);
        $responded_surveys = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Filter out surveys the user has already responded to
        $available_surveys = array_filter($active_surveys, function($survey) use ($responded_surveys) {
            return !in_array($survey['id'], $responded_surveys);
        });

        // Debugging: Log the saved result
        $stmt = $pdo->prepare("SELECT * FROM quiz_results WHERE id = :result_id");
        $stmt->execute([':result_id' => $result_id]);
        $debug_result = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Quiz Result Saved: " . print_r($debug_result, true));

        // Store surveys in session and redirect to quiz results
        $_SESSION['available_surveys'] = $available_surveys;
        header("Location: quiz_result.php?result_id=" . urlencode($result_id));
        exit();
}
?>