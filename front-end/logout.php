<?php
session_start();  // Mở session
session_unset();  // Xóa tất cả session variables
session_destroy();  // Hủy session

// Chuyển hướng về trang đăng nhập
header("Location: login.php");
exit();
?>
