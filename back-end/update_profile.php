<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$address = trim($_POST['address']);


$stmt = $conn->prepare("UPDATE users SET address = ? WHERE id = ?");
$stmt->bind_param("si", $address, $user_id);

if ($stmt->execute()) {
    $success = "Cập nhật địa chỉ thành công!";
    header("Location: ../front-end/profile.php?success=" . urlencode($success));
} else {
    $error = "Lỗi cập nhật: " . $stmt->error;
    header("Location: ../front-end/profile.php?error=" . urlencode($error));
}

$stmt->close();
?>
