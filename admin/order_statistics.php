<?php
ob_start();
session_start();
require '../back-end/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$success = $error = '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$top_customers = [];
$orders_per_page = 10;

// Xử lý thống kê top 5 khách hàng (luôn dựa trên tất cả đơn đã giao, không phụ thuộc bộ lọc)
$query = "SELECT u.id, u.username, SUM(o.total_amount) as total_spent, COUNT(o.id) as order_count
          FROM users u 
          JOIN orders o ON u.id = o.user_id
          WHERE o.status = 'delivered'
          GROUP BY u.id
          ORDER BY total_spent DESC 
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$top_customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Loại bỏ trùng lặp dựa trên id (đề phòng)
$unique_customers = [];
$seen_ids = [];
foreach ($top_customers as $customer) {
    if (!in_array($customer['id'], $seen_ids)) {
        $unique_customers[] = $customer;
        $seen_ids[] = $customer['id'];
    }
}
$top_customers = $unique_customers;

foreach ($top_customers as &$customer) {
    $stmt = $conn->prepare("SELECT o.id, o.total_amount, o.created_at, o.status
                            FROM orders o 
                            WHERE o.user_id = ? AND o.status = 'delivered'
                            ORDER BY o.created_at DESC");
    $stmt->bind_param("i", $customer['id']);
    $stmt->execute();
    $customer['orders'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Lấy tổng số đơn hàng đã giao (áp dụng bộ lọc thời gian nếu có)
$total_orders_query = "SELECT COUNT(DISTINCT o.id) AS total 
                      FROM orders o 
                      JOIN users u ON o.user_id = u.id 
                      WHERE o.status = 'delivered'";
if ($start_date) {
    $end_date = $end_date ?: date('Y-m-d');
    if (strtotime($start_date) > strtotime($end_date)) {
        $error = "Ngày bắt đầu phải nhỏ hơn hoặc bằng ngày kết thúc.";
    } else {
        $end_date_full = $end_date . ' 23:59:59';
        $total_orders_query .= " AND o.created_at BETWEEN ? AND ?";
    }
}
$stmt = $conn->prepare($total_orders_query);
if ($start_date && !$error) {
    $stmt->bind_param("ss", $start_date, $end_date_full);
}
$stmt->execute();
$total_delivered_orders = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Tính toán phân trang
$total_pages = max(1, ceil($total_delivered_orders / $orders_per_page));
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $orders_per_page;

// Lấy danh sách đơn hàng đã giao (áp dụng bộ lọc thời gian nếu có)
$query = "SELECT DISTINCT o.id, o.user_id, o.total_amount, o.shipping_address, o.created_at, o.status, u.username 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          WHERE o.status = 'delivered'";
if ($start_date && !$error) {
    $query .= " AND o.created_at BETWEEN ? AND ?";
}
$query .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
if ($start_date && !$error) {
    $stmt->bind_param("ssii", $start_date, $end_date_full, $orders_per_page, $offset);
} else {
    $stmt->bind_param("ii", $orders_per_page, $offset);
}
$stmt->execute();
$delivered_orders = $stmt->get_result();
$stmt->close();

// Lấy chi tiết đơn hàng
$order_details = [];
$show_details_modal = false;
if (isset($_GET['view_details']) && is_numeric($_GET['view_details'])) {
    $order_id = intval($_GET['view_details']);
    $query = "SELECT oi.quantity, oi.price, b.title, b.image_url 
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

    $query = "SELECT total_amount 
              FROM orders 
              WHERE id = ? AND status = 'delivered'";
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
    <title>Thống kê đơn hàng đã giao</title>
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
        .modal-content {
            background-color: #fefefe; margin: 15% auto; padding: 20px;
            border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 5px;
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
        .order-details-table img { width: 50px; height: auto; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a, .pagination span {
            margin: 0 5px; text-decoration: none; padding: 5px 10px;
            border: 1px solid #ddd; border-radius: 4px;
        }
        .pagination a:hover { background: #f2f2f2; }
        .pagination .current { background: #007bff; color: white; border-color: #007bff; }
        .pagination .disabled { color: #ccc; border-color: #ccc; pointer-events: none; }
        ul { margin: 0; padding-left: 20px; }
        ul li { margin-bottom: 5px; }
    </style>
</head>
<body>
    <?php require 'header.php'; ?>
    <div class="admin-container">
        <h2>Thống kê đơn hàng đã giao</h2>
        <?php
        if (isset($_SESSION['success'])) {
            echo '<p class="success">' . htmlspecialchars($_SESSION['success']) . '</p>';
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo '<p class="error">' . htmlspecialchars($_SESSION['error']) . '</p>';
            unset($_SESSION['error']);
        }
        if ($error) {
            echo '<p class="error">' . htmlspecialchars($error) . '</p>';
        }
        ?>
        <form method="GET" action="">
            <div class="form-group">
                <label for="start_date">Từ ngày:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="form-group">
                <label for="end_date">Đến ngày:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Lọc</button>
        </form>

        <h3>Top 5 khách hàng mua nhiều nhất (đơn đã giao)</h3>
        <?php if ($top_customers): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Khách hàng</th>
                        <th>Tổng chi tiêu</th>
                        <th>Số đơn hàng</th>
                        <th>Chi tiết đơn hàng</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_customers as $customer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['username']); ?></td>
                            <td><?php echo number_format($customer['total_spent'], 0, ',', '.'); ?> VND</td>
                            <td><?php echo htmlspecialchars($customer['order_count']); ?></td>
                            <td>
                                <ul>
                                    <?php foreach ($customer['orders'] as $order): ?>
                                        <li>
                                            <a href="?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&page=<?php echo $page; ?>&view_details=<?php echo $order['id']; ?>">Đơn hàng <?php echo $order['id']; ?></a> - 
                                            <?php echo number_format($order['total_amount'], 0, ',', '.'); ?> VND - 
                                            <?php echo htmlspecialchars($order['status']); ?> - 
                                            <?php echo htmlspecialchars($order['created_at']); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Không có khách hàng nào trong khoảng thời gian này.</p>
        <?php endif; ?>

        <h3>Danh sách đơn hàng đã giao</h3>
        <p>Tổng số đơn hàng: <?php echo $total_delivered_orders; ?></p>
        <?php if ($delivered_orders && $delivered_orders->num_rows > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Khách hàng</th>
                        <th>Tổng tiền</th>
                        <th>Địa chỉ</th>
                        <th>Thời gian</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $delivered_orders->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['id']); ?></td>
                            <td><?php echo htmlspecialchars($order['username']); ?></td>
                            <td><?php echo number_format($order['total_amount'], 0, ',', '.'); ?> VND</td>
                            <td><?php echo htmlspecialchars($order['shipping_address']); ?></td>
                            <td><?php echo htmlspecialchars($order['created_at']); ?></td>
                            <td>
                                <a href="?<?php echo http_build_query(['start_date' => $start_date, 'end_date' => $end_date, 'page' => $page, 'view_details' => $order['id']]); ?>" class="btn btn-primary">Xem chi tiết</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div class="pagination">
                <a href="?page=<?php echo $page - 1; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">«</a>
                <span class="current">Trang <?php echo $page; ?> / <?php echo $total_pages; ?></span>
                <a href="?page=<?php echo $page + 1; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="<?php echo $page >= $total_pages ? 'disabled' : ''; ?>">»</a>
            </div>
        <?php else: ?>
            <p>Không có đơn hàng đã giao nào.</p>
        <?php endif; ?>

        <div id="orderDetailsModal" class="modal" style="<?php echo $show_details_modal ? 'display: block;' : 'display: none;'; ?>">
            <div class="modal-content">
                <span class="close" onclick="closeOrderDetailsModal()">×</span>
                <h3>Chi tiết đơn hàng</h3>
                <?php if ($show_details_modal && !empty($order_details)): ?>
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
                                        <td><img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>"></td>
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
            </div>
        </div>
    </div>
    <script>
        function closeOrderDetailsModal() {
            const params = new URLSearchParams(window.location.search);
            params.delete('view_details');
            window.location.href = 'order_statistics.php?' + params.toString();
        }

        window.onclick = function (event) {
            const modal = document.getElementById('orderDetailsModal');
            if (event.target === modal) {
                closeOrderDetailsModal();
            }
        }

        setTimeout(() => {
            document.querySelectorAll('.success, .error').forEach(el => el.style.display = 'none');
        }, 3000);
    </script>
</body>
</html>
<?php ob_end_flush(); ?>