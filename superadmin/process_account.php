<?php
session_start();
require_once '../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "SuperAdmin") {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'], $_POST['action'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];

    if ($action === 'activate') {
        $stmt = $conn->prepare("UPDATE users SET status = 1 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
    } elseif ($action === 'deactivate') {
        $stmt = $conn->prepare("UPDATE users SET status = 0 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
    }

    if ($stmt->execute()) {
        header("Location: account_management.php?success=1");
    } else {
        header("Location: account_management.php?error=1");
    }
    $stmt->close();
}
$conn->close();
?>