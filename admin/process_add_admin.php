<?php
session_start();
require_once '../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "Admin") {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Validate inputs
    if (empty($fullname) || empty($email) || empty($password)) {
        header("Location: add_admin.php?error=All fields are required");
        exit();
    }

    // Check if email exists
    $query = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        header("Location: add_admin.php?error=Email already exists");
        exit();
    }

    // Insert new admin
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $query = "INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, 'Admin')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $fullname, $email, $hashed_password);
    if ($stmt->execute()) {
        header("Location: add_admin.php?success=Admin added successfully");
        exit();
    } else {
        header("Location: add_admin.php?error=Failed to add admin");
        exit();
    }
}
?>