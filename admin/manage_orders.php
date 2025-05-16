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
$orders_per_page = 10; // Số đơn hàng hiển thị mỗi trang
$filters = [
    'status' => isset($_GET['status']) ? $_GET['status'] : '',
    'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : '',
    'end_date' => isset($_GET['end_date']) ? $_GET['end_date'] : '',
    'address' => isset($_GET['address']) ? $_GET['address'] : ''
];

// Xử lý cập nhật trạng thái đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];

    // Kiểm tra trạng thái hợp lệ (chỉ cho phép cập nhật xuôi hoặc bằng)
    $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $current_status = $stmt->get_result()->fetch_assoc()['status'];

    $status_order = ['pending' => 1, 'confirmed' => 2, 'delivered' => 3, 'cancelled' => 3];
    if ($status_order[$new_status] >= $status_order[$current_status]) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Cập nhật trạng thái đơn hàng thành công.";
            header("Location: manage_orders.php?page=$page&" . http_build_query($filters));
            exit();
        } else {
            $error = "Lỗi khi cập nhật trạng thái: " . $stmt->error;
        }
    } else {
        $error = "Không thể cập nhật trạng thái ngược.";
    }
    $stmt->close();
}

// Lấy tổng số đơn hàng
$total_orders_query = "SELECT COUNT(*) AS total FROM orders o JOIN users u ON o.user_id = u.id WHERE 1=1";
$params = [];
$types = '';

if ($filters['status']) {
    $total_orders_query .= " AND o.status = ?";
    $params[] = $filters['status'];
    $types .= 's';
}
if ($filters['start_date']) {
    $total_orders_query .= " AND o.created_at >= ?";
    $params[] = $filters['start_date'];
    $types .= 's';
}
if ($filters['end_date']) {
    $total_orders_query .= " AND o.created_at <= ?";
    $params[] = $filters['end_date'];
    $types .= 's';
}
if ($filters['address']) {
    $total_orders_query .= " AND o.shipping_address LIKE ?";
    $params[] = "%{$filters['address']}%";
    $types .= 's';
}

$stmt = $conn->prepare($total_orders_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_orders = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Tính toán phân trang
$total_pages = ceil($total_orders / $orders_per_page);
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
$offset = ($page - 1) * $orders_per_page;

// Lọc đơn hàng
$query = "SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE 1=1";
if ($filters['status']) {
    $query .= " AND o.status = ?";
}
if ($filters['start_date']) {
    $query .= " AND o.created_at >= ?";
}
if ($filters['end_date']) {
    $query .= " AND o.created_at <= ?";
}
if ($filters['address']) {
    $query .= " AND o.shipping_address LIKE ?";
}
$query .= " LIMIT $orders_per_page OFFSET $offset";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

// Lấy chi tiết đơn hàng nếu có yêu cầu (dùng GET parameter để mở modal)
$order_details = [];
$show_details_modal = false;
if (isset($_GET['view_details']) && is_numeric($_GET['view_details'])) {
    $order_id = intval($_GET['view_details']);
    $query = "SELECT oi.quantity, oi.price, b.title,b.image_url
              FROM order_items oi 
              JOIN books b ON oi.book_id = b.id 
              WHERE oi.order_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $order_details[] = $row;
    }
    $stmt->close();

    // Lấy tổng tiền đơn hàng
    $query = "SELECT total_amount FROM orders WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $total_amount = $stmt->get_result()->fetch_assoc()['total_amount'];
    $stmt->close();

    $order_details['total_amount'] = $total_amount;
    $show_details_modal = !empty($order_details);
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý đơn hàng</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .success { color: green; margin-bottom: 10px; }
        .error { color: red; margin-bottom: 10px; }
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group select {
            width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;
        }
        .btn {
            padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;
            font-size: 14px; margin-right: 5px; min-width: 80px; text-align: center;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .table th { background: #f2f2f2; font-weight: bold; }
        .modal {
            display: none; position: fixed; z-index: 1; left: 0; top: 0;
            width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);
        }

        .close {
            color: #aaa; float: right; font-size: 28px; font-weight: bold;
        }
        .close:hover, .close:focus { color: black; text-decoration: none; cursor: pointer; }
        .order-details-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .order-details-table th, .order-details-table td {
            border: 1px solid #ddd; padding: 8px; text-align: left;
        }
        .order-details-table th { background: #f2f2f2; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a, .pagination span {
            margin: 0 5px; text-decoration: none; padding: 5px 10px;
            border: 1px solid #ddd; border-radius: 4px;
        }
        .pagination a:hover { background: #f2f2f2; }
        .pagination .current { background: #007bff; color: white; border-color: #007bff; }
        .pagination .disabled { color: #ccc; border-color: #ccc; pointer-events: none; }
    </style>
</head>

<body>
    <?php require 'header.php'; ?>
    <div class="admin-container">
        <h2 style="text-align: center;">Quản lý đơn hàng</h2>
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
        <div id="filterModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeFilterModal()">×</span>
                <h3>Lọc đơn hàng</h3>
                <form method="GET" action="">
                    <div class="form-group">
                        <label for="status">Trạng thái:</label>
                        <select id="status" name="status">
                            <option value="">Tất cả</option>
                            <option value="pending" <?php echo $filters['status'] == 'pending' ? 'selected' : ''; ?>>Chưa xác nhận</option>
                            <option value="confirmed" <?php echo $filters['status'] == 'confirmed' ? 'selected' : ''; ?>>Đã xác nhận</option>
                            <option value="delivered" <?php echo $filters['status'] == 'delivered' ? 'selected' : ''; ?>>Đã giao</option>
                            <option value="cancelled" <?php echo $filters['status'] == 'cancelled' ? 'selected' : ''; ?>>Hủy</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="start_date">Từ ngày:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $filters['start_date']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date">Đến ngày:</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $filters['end_date']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="address">Địa chỉ giao hàng:</label>
                        <input type="text" id="address" name="address" value="<?php echo $filters['address']; ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Lọc</button>
                </form>
            </div>
        </div>
        <div style="display: flex; justify-content: flex-end;">
            <button onclick="openFilterModal()" class="btn btn-primary">Lọc</button>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Khách hàng</th>
                    <th>Tổng tiền</th>
                    <th>Địa chỉ</th>
                    <th>Trạng thái</th>
                    <th>Thời gian</th>
                    <th>Chi tiết</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($orders && $orders->num_rows > 0): ?>
                    <?php while ($order = $orders->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['id']); ?></td>
                            <td><?php echo htmlspecialchars($order['username']); ?></td>
                            <td><?php echo number_format($order['total_amount'], 0, ',', '.'); ?> VND</td>
                            <td><?php echo htmlspecialchars($order['shipping_address']); ?></td>
                            <td><?php echo htmlspecialchars($order['status']); ?></td>
                            <td><?php echo htmlspecialchars($order['created_at']); ?></td>
                            <td>
                                <a href="?<?php echo http_build_query(array_merge($filters, ['page' => $page, 'view_details' => $order['id']])); ?>" class="btn btn-primary">Xem chi tiết</a>
                            </td>
                            <td>
                                <form method="POST" action="" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Chưa xác nhận</option>
                                        <option value="confirmed" <?php echo $order['status'] == 'confirmed' ? 'selected' : ''; ?>>Đã xác nhận</option>
                                        <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Đã giao</option>
                                        <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Hủy</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">Không có đơn hàng nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <!-- Phân trang -->
        <div class="pagination">
            <a href="?page=<?php echo $page > 1 ? $page - 1 : 1; ?>&<?php echo http_build_query($filters); ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">«</a>
            <span class="current">Trang <?php echo $page; ?> / <?php echo $total_pages; ?></span>
            <a href="?page=<?php echo $page < $total_pages ? $page + 1 : $total_pages; ?>&<?php echo http_build_query($filters); ?>" class="<?php echo $page >= $total_pages ? 'disabled' : ''; ?>">»</a>
        </div>
        <!-- Modal chi tiết đơn hàng -->
        <div id="orderDetailsModal" class="modal" style="<?php echo $show_details_modal ? 'display: block;' : 'display: none;'; ?>">
            <div class="modal-content">
                <span class="close" onclick="closeOrderDetailsModal()">×</span>
                <h3>Chi tiết đơn hàng</h3>
                <?php if ($show_details_modal): ?>
                    <?php if (!empty($order_details)): ?>
                        <table class="order-details-table">
                            <thead>
                                <tr>
                                <th>Hình ảnh</th>
                                    <th>Sách</th>
                                    <th>Số lượng</th>
                                    <th>Giá</th>
                                    <th>Tổng</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_details as $item): ?>
                                    <?php if (isset($item['title'])): ?>
                                        <tr>
                                        <td><img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>" width="50"></td>

                                            <td><?php echo htmlspecialchars($item['title']); ?></td>
                                            <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                            <td><?php echo number_format($item['price'], 0, ',', '.'); ?> VND</td>
                                            <td><?php echo number_format($item['quantity'] * $item['price'], 0, ',', '.'); ?> VND</td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p><strong>Tổng tiền: </strong><?php echo number_format($order_details['total_amount'], 0, ',', '.'); ?> VND</p>
                    <?php else: ?>
                        <p class="error">Không tìm thấy chi tiết đơn hàng.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
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

        // Modal lọc
        function openFilterModal() {
            document.getElementById('filterModal').style.display = 'block';
        }

        function closeFilterModal() {
            document.getElementById('filterModal').style.display = 'none';
        }

        // Modal chi tiết đơn hàng
        function closeOrderDetailsModal() {
            window.location.href = 'manage_orders.php?page=<?php echo $page; ?>&<?php echo http_build_query($filters); ?>';
        }

        // Đóng modal khi bấm ra ngoài
        window.onclick = function (event) {
            const filterModal = document.getElementById('filterModal');
            const detailsModal = document.getElementById('orderDetailsModal');
            if (event.target == filterModal) {
                filterModal.style.display = 'none';
            }
            if (event.target == detailsModal) {
                closeOrderDetailsModal();
            }
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>