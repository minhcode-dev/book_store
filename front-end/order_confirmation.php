<?php
session_start();
require '../back-end/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Lấy thông tin đơn hàng
$query = "SELECT o.id, o.total_amount, o.created_at, o.recipient_name, o.phone_number, o.shipping_address,o.payment_method
          FROM orders o 
          WHERE o.id = ? AND o.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

// Lấy chi tiết đơn hàng
$query = "SELECT b.title, oi.quantity, oi.price 
          FROM order_items oi
          JOIN books b ON oi.book_id = b.id
          WHERE oi.order_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Nhận Đơn Hàng - Min Book</title>
    <link rel="stylesheet" href="../vendor/style.css">
    <link rel="stylesheet" href="../vendor/order_confirmation.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="cart-container">
        <h2>Xác Nhận Đơn Hàng</h2>
        <?php if ($order): ?>
            <p>Cảm ơn bạn đã đặt hàng! Dưới đây là chi tiết đơn hàng của bạn:</p>
            <p><strong>Mã đơn hàng:</strong> <?= $order['id'] ?></p>
            <p><strong>Ngày đặt:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
            <p><strong>Người nhận:</strong> <?= htmlspecialchars($order['recipient_name']) ?></p>
            <p><strong>Số điện thoại:</strong> <?= htmlspecialchars($order['phone_number']) ?></p>
            <p><strong>Địa chỉ giao hàng:</strong> <?= htmlspecialchars($order['shipping_address']) ?></p>
            <p><strong>Phương thức thanh toán:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>

            <div class="cart-table-container">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Tên Sách</th>
                            <th>Giá</th>
                            <th>Số Lượng</th>
                            <th>Tổng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $items->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['title']) ?></td>
                                <td><?= number_format($item['price'], 0, ',', '.') ?>đ</td>
                                <td><?= $item['quantity'] ?></td>
                                <td><?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>đ</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="cart-summary">
                <h3>Tổng Tiền: <?= number_format($order['total_amount'], 0, ',', '.') ?>đ</h3>
                <a href="cart.php" class="checkout-btn">Quay lại giỏ hàng</a>
            </div>
        <?php else: ?>
            <p>Không tìm thấy đơn hàng.</p>
            <a href="cart.php" class="checkout-btn">Quay lại giỏ hàng</a>
        <?php endif; ?>
    </div>
    <div style="margin-top:30vh;">
        <?php include 'footer.php'; ?>
    </div>
</body>
<script src="../vendor/dropdown.js"></script>
</html>