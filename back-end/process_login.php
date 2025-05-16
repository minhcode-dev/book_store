<?php
session_start();
require 'db_connect.php';  // Kết nối cơ sở dữ liệu

// Kiểm tra nếu người dùng đã gửi form đăng nhập
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    // Lấy dữ liệu từ form
    $user_name = mysqli_real_escape_string($conn, $_POST['username']);
    $user_password = mysqli_real_escape_string($conn, $_POST['password']);

    // Truy vấn cơ sở dữ liệu để kiểm tra thông tin đăng nhập
    $sql = "SELECT * FROM users WHERE username = '$user_name'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Nếu người dùng tồn tại trong cơ sở dữ liệu
        $user = $result->fetch_assoc();

        // Kiểm tra trạng thái tài khoản
        if ($user['is_active'] == 0) {
            // Tài khoản bị khóa
            $_SESSION['error_login'] = "Tài khoản của bạn đã bị khóa!";
            header("Location: ../front-end/login.php");
            exit();
        }

        // Kiểm tra mật khẩu
        if (password_verify($user_password, $user['password'])) {
            // Đăng nhập thành công, lưu thông tin người dùng vào session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // Chuyển hướng đến trang chính hoặc trang cá nhân
            header("Location: ../front-end/homepage.php");
            exit();
        } else {
            // Mật khẩu không đúng
            $_SESSION['error_login'] = "Mật khẩu không chính xác!";
            header("Location: ../front-end/login.php");
            exit();
        }
    } else {
        // Tên đăng nhập không tồn tại
        $_SESSION['error_login'] = "Tên đăng nhập không tồn tại!";
        header("Location: ../front-end/login.php");
        exit();
    }
}

// Đóng kết nối
$conn->close();
?>
