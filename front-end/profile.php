<?php
session_start();
require '../back-end/db_connect.php'; // kết nối tới CSDL

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Lấy thông tin người dùng
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, address FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username, $email, $address);
$stmt->fetch();
$stmt->close();
?>
<style>
    .success {
        color: green;
        margin-bottom: 10px;
    }

    .error {
        color: red;
        margin-bottom: 10px;
    }
</style>
<!DOCTYPE html>
<html>

<head>
    <title>Thông tin cá nhân</title>
    <link rel="stylesheet" href="../vendor/style.css">

</head>

<body>
    <?php require 'header.php' ?>
    <h2>Thông tin cá nhân</h2>

    <div class="container">
        <?php if (isset($_GET['success'])): ?>
            <p class="success"><?php echo htmlspecialchars($_GET['success']); ?></p>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <p class="error"><?php echo htmlspecialchars($_GET['error']); ?></p>
        <?php endif; ?>
        <form method="post" action="../back-end/update_profile.php">
            <label>Tên đăng nhập:</label><br>
            <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" readonly><br><br>

            <label>Email:</label><br>
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" readonly><br><br>

            <label>Địa chỉ:</label><br>
            <input type="text" name="address" value="<?= htmlspecialchars($address) ?>" required><br><br>

            <input type="submit" value="Cập nhật">
        </form>
    </div>

    <?php require 'footer.php' ?>

</body>
<script src="../vendor/dropdown.js"></script>
<script>
    // Set timeout để ẩn thông báo success/error sau 5 giây
    setTimeout(function () {
        var successMessage = document.querySelector('.success');
        var errorMessage = document.querySelector('.error');

        if (successMessage) {
            successMessage.style.display = 'none';
        }
        if (errorMessage) {
            errorMessage.style.display = 'none';
        }
    }, 3000); // 5000ms = 5 giây
</script>

</html>