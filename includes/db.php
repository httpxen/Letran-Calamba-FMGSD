<?php
$host = 'localhost';
$db = 'letran_system';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo 'Success';
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
