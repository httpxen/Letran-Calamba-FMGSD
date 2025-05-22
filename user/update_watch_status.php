<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "User") {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit();
}

$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$lesson_id = filter_input(INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT);

if (!$user_id || !$lesson_id) {
  echo json_encode(['success' => false, 'message' => 'Invalid input']);
  exit();
}

try {
  $stmt = $pdo->prepare("
    INSERT INTO quiz_results (user_id, lesson_id, isWatched, taken_at, created_at)
    VALUES (:user_id, :lesson_id, 1, NOW(), NOW())
    ON DUPLICATE KEY UPDATE isWatched = 1, taken_at = NOW()
  ");
  $stmt->execute([':user_id' => $user_id, ':lesson_id' => $lesson_id]);
  echo json_encode(['success' => true]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>