<?php
session_start();
require '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $lesson_id = filter_input(INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT);

    if ($lesson_id) {
        $stmt = $pdo->prepare("
            INSERT INTO lesson_progress (user_id, lesson_id, video_watched, watched_at)
            VALUES (:user_id, :lesson_id, 1, NOW())
            ON DUPLICATE KEY UPDATE video_watched = 1, watched_at = NOW()
        ");
        $stmt->execute([':user_id' => $user_id, ':lesson_id' => $lesson_id]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid lesson ID']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
}
?>