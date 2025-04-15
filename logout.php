<?php
session_start();
include 'db/db.php'; // Include the database connection

// Update is_online and status to mark the user as offline
if (isset($_SESSION['user_id'])) {
    $update_sql = "UPDATE users SET is_online = 0, status = 'offline' WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    if (!$stmt->execute()) {
        error_log("Failed to update is_online and status for user ID {$_SESSION['user_id']}: " . $stmt->error);
    }
    $stmt->close();
}

// Destroy the session
session_unset();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>