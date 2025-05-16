<?php session_start(); ?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../vendor/login.css">
    <title>Đăng Nhập - Min Books</title>
</head>

<body>

    <div class="login-container">
        <a href="homepage.php" class="back-home">← Trang chủ</a>

        <h2>Đăng Nhập</h2>

        <!-- Hiển thị lỗi nếu có -->
        <?php if (isset($_SESSION['error_login'])): ?>
            <p style="color: red;"><?php echo $_SESSION['error_login'];
            unset($_SESSION['error_login']); ?></p>
        <?php endif; ?>

        <form action="../back-end/process_login.php" method="post">
            <input type="text" name="username" placeholder="Tên đăng nhập" required>
            <input type="password" name="password" placeholder="Mật khẩu" required>
            <button type="submit" name="login">Đăng nhập</button>
        </form>
        <div class="links">
            <p>Chưa có tài khoản? <a href="register.php">Đăng ký</a></p>
        </div>
    </div>

</body>

</html>