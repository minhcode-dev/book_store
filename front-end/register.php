<?php session_start(); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../vendor/login.css">
  <title>Đăng Ký - Min Books</title>
</head>
<body>

  <div class="register-container">
    <h2>Đăng Ký</h2>

    <!-- Hiển thị lỗi nếu có -->
    <?php if (isset($_SESSION['error_register'])): ?>
      <p style="color: red;"><?php echo $_SESSION['error_register']; unset($_SESSION['error_register']); ?></p>
    <?php endif; ?>

    <form action="../back-end/process_register.php" method="post">
      <input type="text" name="username" placeholder="Tên đăng nhập" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Mật khẩu" required>
      <input type="password" name="confirm_password" placeholder="Xác nhận mật khẩu" required>
      <input type="hidden" name="register" value="1"> <!-- Thêm trường này -->
      <button type="submit">Đăng ký</button>
    </form>
    <div class="links">
      <p>Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
    </div>
  </div>

</body>
</html>
