<?php
require_once '../db/db.php';

$query = "SELECT id, fullname, email, profile_picture, last_login, is_online, current_device FROM users";
$result = mysqli_query($conn, $query);

if (!$result) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Query failed: ' . mysqli_error($conn)]);
    exit;
}

$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = [
        'id' => $row['id'],
        'fullname' => $row['fullname'],
        'email' => $row['email'],
        'last_login' => $row['last_login'],
        'status' => $row['is_online'] ? 'Online' : 'Offline',
        'status_class' => $row['is_online'] ? 'bg-green-500' : 'bg-red-500',
        'current_device' => $row['current_device']
    ];
}

header('Content-Type: application/json');
echo json_encode($users);

mysqli_free_result($result);
mysqli_close($conn);
?>