<?php
ob_start(); // Bật output buffering để tránh lỗi headers
session_start();
require '../back-end/db_connect.php';

// Kiểm tra admin đăng nhập
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$success = $error = '';
$users_per_page = 10; // Số người dùng hiển thị mỗi trang

// Lấy tổng số người dùng
$total_users_result = $conn->query("SELECT COUNT(*) AS total FROM users");
if (!$total_users_result) {
    $error = "Lỗi truy vấn cơ sở dữ liệu: " . $conn->error;
    $total_users = 0;
} else {
    $total_users = $total_users_result->fetch_assoc()['total'];
}
$total_pages = ceil($total_users / $users_per_page);

// Lấy số trang hiện tại
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
$offset = ($page - 1) * $users_per_page;

// Lấy danh sách người dùng cho trang hiện tại
$users = $conn->query("SELECT * FROM users LIMIT $users_per_page OFFSET $offset");
if (!$users) {
    $error = "Lỗi khi lấy danh sách người dùng: " . $conn->error;
}

// Xử lý các hành động POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        $role = $_POST['role'];

        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            $error = "Lỗi chuẩn bị truy vấn: " . $conn->error;
        } else {
            $stmt->bind_param("ssss", $username, $email, $password, $role);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Thêm người dùng thành công.";
                header("Location: manage_users.php?page=$page");
                exit();
            } else {
                $error = "Lỗi khi thêm người dùng: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['edit_user'])) {
        $user_id = intval($_POST['user_id']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];

        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
        if (!$stmt) {
            $error = "Lỗi chuẩn bị truy vấn: " . $conn->error;
        } else {
            $stmt->bind_param("sssi", $username, $email, $role, $user_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Cập nhật người dùng thành công.";
                header("Location: manage_users.php?page=$page");
                exit();
            } else {
                $error = "Lỗi khi cập nhật người dùng: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['toggle_status'])) {
        $user_id = intval($_POST['user_id']);

        // Ngăn admin thay đổi trạng thái của chính mình
        if ($user_id == $_SESSION['admin_id']) {
            $error = "Bạn không thể thay đổi trạng thái tài khoản của chính mình.";
        } else {
            $is_active = $_POST['is_active'] ? 0 : 1;

            $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            if (!$stmt) {
                $error = "Lỗi chuẩn bị truy vấn: " . $conn->error;
            } else {
                $stmt->bind_param("ii", $is_active, $user_id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Cập nhật trạng thái người dùng thành công.";
                    header("Location: manage_users.php?page=$page");
                    exit();
                } else {
                    $error = "Lỗi khi cập nhật trạng thái: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý người dùng</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .success {
            color: green;
            margin-bottom: 10px;
        }

        .error {
            color: red;
            margin-bottom: 10px;
        }

        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 5px;
            min-width: 80px;
            text-align: center;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th,
        .table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        .table th {
            background: #f2f2f2;
            font-weight: bold;
        }

        .pagination {
            margin-top: 20px;
            text-align: center;
        }

        .pagination a,
        .pagination span {
            margin: 0 5px;
            text-decoration: none;
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .pagination a:hover {
            background: #f2f2f2;
        }

        .pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .pagination .disabled {
            color: #ccc;
            border-color: #ccc;
            pointer-events: none;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }



        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <?php require 'header.php'; ?>
    <div class="admin-container">
        <h2 style="text-align: center;">Quản lý người dùng</h2>
        <?php
        // Hiển thị thông báo từ session
        if (isset($_SESSION['success'])) {
            echo '<p class="success">' . htmlspecialchars($_SESSION['success']) . '</p>';
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo '<p class="error">' . htmlspecialchars($_SESSION['error']) . '</p>';
            unset($_SESSION['error']);
        }
        // Hiển thị thông báo cục bộ
        if ($success) {
            echo '<p class="success">' . htmlspecialchars($success) . '</p>';
        }
        if ($error) {
            echo '<p class="error">' . htmlspecialchars($error) . '</p>';
        }
        ?>
        <div style="display: flex; justify-content: flex-end;">
            <button onclick="openAddUserModal()" class="btn btn-primary">+ Thêm người dùng</button>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên đăng nhập</th>
                    <th>Email</th>
                    <th>Vai trò</th>
                    <th>Trạng thái</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users && $users->num_rows > 0): ?>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td><?php echo $user['is_active'] ? 'Hoạt động' : 'Khóa'; ?></td>
                            <td>
                                <form id="toggle_form_<?php echo $user['id']; ?>" method="POST" action="">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $user['is_active']; ?>">
                                    <input type="hidden" name="toggle_status" value="1">
                                    <button type="button"
                                        onclick="confirmToggleStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active']; ?>)"
                                        class="btn <?php echo $user['is_active'] ? 'btn-delete' : 'btn-primary'; ?>">
                                        <?php echo $user['is_active'] ? 'Khóa' : 'Mở'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">Không có người dùng nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="pagination">
            <a href="?page=<?php echo $page > 1 ? $page - 1 : 1; ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">«</a>
            <span class="current">Trang <?php echo $page; ?> / <?php echo $total_pages; ?></span>
            <a href="?page=<?php echo $page < $total_pages ? $page + 1 : $total_pages; ?>" class="<?php echo $page >= $total_pages ? 'disabled' : ''; ?>">»</a>
        </div>
    </div>
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddUserModal()">×</span>
            <h3>Thêm người dùng</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Tên đăng nhập:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Mật khẩu:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="role">Vai trò:</label>
                    <select id="role" name="role" required>
                        <option value="user">Người dùng</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" name="add_user" class="btn btn-primary">Thêm</button>
            </form>
        </div>
    </div>
    <script>
        // Tự động ẩn thông báo sau 3 giây
        setTimeout(function () {
            const successMsg = document.querySelector('.success');
            if (successMsg) successMsg.style.display = 'none';
            const errorMsg = document.querySelector('.error');
            if (errorMsg) errorMsg.style.display = 'none';
        }, 3000);

        // Hàm xác nhận thay đổi trạng thái
        function confirmToggleStatus(user_id, current_status) {
            var action = current_status ? 'Khóa' : 'Mở';
            if (confirm('Bạn có chắc chắn muốn ' + action + ' tài khoản này?')) {
                document.getElementById('toggle_form_' + user_id).submit();
            }
        }

        // Modal functions
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'block';
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
        }

        // Đóng modal khi bấm ra ngoài
        window.onclick = function (event) {
            var modal = document.getElementById('addUserModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
<?php ob_end_flush(); // Kết thúc output buffering ?>