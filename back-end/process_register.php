<?php
session_start(); // Đảm bảo session được khởi tạo trước khi xuất bất kỳ dữ liệu nào

require 'db_connect.php';

// Xử lý đăng ký
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $user_name = mysqli_real_escape_string($conn, $_POST['username']);
    $user_email = mysqli_real_escape_string($conn, $_POST['email']);
    $user_password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);

    // Kiểm tra mật khẩu xác nhận
    if ($user_password !== $confirm_password) {
        $_SESSION['error_register'] = "Mật khẩu xác nhận không khớp!";
        header("Location: ../front-end/register.php");
        exit();
    }

    // Kiểm tra mật khẩu có ít nhất 8 ký tự
    if (strlen($user_password) < 8) {
        $_SESSION['error_register'] = "Mật khẩu phải có ít nhất 8 ký tự!";
        header("Location: ../front-end/register.php");
        exit();
    }

    // Mã hóa mật khẩu trước khi lưu vào cơ sở dữ liệu
    $hashed_password = password_hash($user_password, PASSWORD_DEFAULT);

    // Kiểm tra xem tên đăng nhập hoặc email đã tồn tại chưa
    $check_username = "SELECT * FROM users WHERE username = '$user_name' OR email = '$user_email'";
    $result = $conn->query($check_username);

    if ($result->num_rows > 0) {
        $_SESSION['error_register'] = "Tên đăng nhập hoặc email đã tồn tại!";
        header("Location: ../front-end/register.php");
        exit();
    }
    $role = 'user';
    $is_active = 1;

    // Thực hiện việc thêm người dùng mới vào cơ sở dữ liệu
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, is_active) 
    VALUES (?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssi", $user_name, $user_email, $hashed_password, $role, $is_active);
    if ($stmt->execute()) {
        $_SESSION['success_register'] = "Đăng ký thành công!";
        header("Location: ../front-end/login.php");
        exit();
    } else {
        $_SESSION['error_register'] = "Lỗi hệ thống: " . $stmt->error;
        header("Location: ../front-end/register.php");
        exit();
    }
}

// Đóng kết nối
$conn->close();
?>