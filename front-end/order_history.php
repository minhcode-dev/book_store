<?php
session_start();
require '../back-end/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Xử lý hủy đơn
if (isset($_POST['cancel_order_id'])) {
    $cancel_order_id = $_POST['cancel_order_id'];

    $conn->begin_transaction();

    try {
        $cancel_query = "UPDATE orders SET status = 'Đã hủy' WHERE id = ? AND user_id = ?";
        $cancel_stmt = $conn->prepare($cancel_query);
        $cancel_stmt->bind_param("ii", $cancel_order_id, $user_id);
        $cancel_stmt->execute();

        if ($cancel_stmt->affected_rows > 0) {
            $delete_items_query = "DELETE FROM order_items WHERE order_id = ?";
            $delete_items_stmt = $conn->prepare($delete_items_query);
            $delete_items_stmt->bind_param("i", $cancel_order_id);
            $delete_items_stmt->execute();

            $delete_order_query = "DELETE FROM orders WHERE id = ? AND user_id = ?";
            $delete_order_stmt = $conn->prepare($delete_order_query);
            $delete_order_stmt->bind_param("ii", $cancel_order_id, $user_id);
            $delete_order_stmt->execute();

            if ($delete_order_stmt->affected_rows > 0) {
                $conn->commit();
                echo "<script>alert('Đơn hàng đã được hủy thành công.'); window.location.href = 'order_history.php';</script>";
            } else {
                throw new Exception('Không thể xóa đơn hàng.');
            }
        } else {
            throw new Exception('Không thể cập nhật trạng thái đơn hàng.');
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Có lỗi xảy ra khi hủy đơn hàng: " . $e->getMessage() . "');</script>";
    }
}

// Phân trang
$limit = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_orders = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $limit);

// Lấy danh sách đơn hàng
$query = "SELECT o.id AS order_id, o.created_at, o.total_amount, o.status, 
                 o.recipient_name, o.phone_number, o.shipping_address, b.image_url, b.title
          FROM orders o
          JOIN order_items oi ON o.id = oi.order_id
          JOIN books b ON oi.book_id = b.id
          WHERE o.user_id = ?
          GROUP BY o.id
          ORDER BY o.created_at
          LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $order_id = $row['order_id'];
    if (!isset($orders[$order_id])) {
        $orders[$order_id] = [
            'created_at' => $row['created_at'],
            'total_amount' => $row['total_amount'],
            'status' => $row['status'],
            'recipient_name' => $row['recipient_name'],
            'phone_number' => $row['phone_number'],
            'shipping_address' => $row['shipping_address'],
            'books' => []
        ];
    }
    $orders[$order_id]['books'][] = [
        'image_url' => $row['image_url'],
        'title' => $row['title']
    ];
}

// Lấy chi tiết đơn hàng nếu có yêu cầu
$order_details = null;
if (isset($_GET['detail_order_id'])) {
    $detail_order_id = intval($_GET['detail_order_id']);
    $detail_query = "SELECT o.id AS order_id, o.created_at, o.total_amount, o.status, 
                            o.recipient_name, o.phone_number, o.shipping_address,
                            b.image_url, b.title, oi.quantity, oi.price
                     FROM orders o
                     JOIN order_items oi ON o.id = oi.order_id
                     JOIN books b ON oi.book_id = b.id
                     WHERE o.id = ? AND o.user_id = ?
                     ";
                     
    $detail_stmt = $conn->prepare($detail_query);
    $detail_stmt->bind_param("ii", $detail_order_id, $user_id);
    $detail_stmt->execute();
    $detail_result = $detail_stmt->get_result();

    if ($detail_result->num_rows > 0) {
        $order_details = ['books' => []];
        while ($row = $detail_result->fetch_assoc()) {
            if (!isset($order_details['order_id'])) {
                $order_details['order_id'] = $row['order_id'];
                $order_details['created_at'] = date("d/m/Y H:i", strtotime($row['created_at']));
                $order_details['total_amount'] = number_format($row['total_amount'], 0, ',', '.');
                $order_details['status'] = $row['status'];
                $order_details['recipient_name'] = $row['recipient_name'];
                $order_details['phone_number'] = $row['phone_number'];
                $order_details['shipping_address'] = $row['shipping_address'];
            }
            $order_details['books'][] = [
                'image_url' => $row['image_url'],
                'title' => $row['title'],
                'quantity' => $row['quantity'],
                'price' => number_format($row['price'], 0, ',', '.')
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lịch Sử Đơn Hàng - Min Book</title>
    <link rel="stylesheet" href="../vendor/style.css">
    <link rel="stylesheet" href="../vendor/cart.css">
    <style>
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 10px;
            padding: 10px;
            border-radius: 5px;
        }

        .pagination a {
            padding: 8px 12px;
            border: 1px solid #007bff;
            border-radius: 4px;
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
            transition: all 0.3s;
        }

        .pagination a:hover:not(.disabled) {
            background-color: #007bff;
            color: white;
        }

        .pagination a.disabled {
            color: #6c757d;
            border-color: #6c757d;
            pointer-events: none;
            cursor: not-allowed;
        }

        .pagination .current {
            padding: 8px 12px;
            color: #333;
            font-weight: bold;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 700px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        #orderDetailContent {
            margin-top: 20px;
        }

        #orderDetailContent table {
            width: 100%;
            border-collapse: collapse;
        }

        #orderDetailContent th,
        #orderDetailContent td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        #orderDetailContent th {
            background-color: #f4f4f4;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="cart-container">
        <h2>Lịch Sử Đơn Hàng</h2>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Mã Đơn</th>
                    <th>Sách đã mua</th>
                    <th>Ngày Đặt</th>
                    <th>Trạng Thái</th>
                    <th>Hủy Đơn</th>
                    <th>Chi tiết</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order_id => $order): ?>
                        <tr>
                            <td><?= htmlspecialchars($order_id) ?></td>
                            <td>
                                <?php foreach ($order['books'] as $book): ?>
                                    <div style="margin-bottom: 10px;">
                                        <img src="<?= htmlspecialchars($book['image_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>" width="50" style="display: block; margin-bottom: 4px;">
                                        <span style="font-size: 12px; display: block; max-width: 150px;">
                                            <?= htmlspecialchars($book['title']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td><?= date("d/m/Y H:i", strtotime($order['created_at'])) ?></td>
                            <td>
                                <?php
                                switch ($order['status']) {
                                    case 'Chờ xác nhận':
                                        echo "<span style='color: orange;'>Chờ xác nhận</span>";
                                        break;
                                    case 'Đã xác nhận':
                                        echo "<span style='color: green;'>Đã xác nhận</span>";
                                        break;
                                    case 'Đang giao':
                                        echo "<span style='color: blue;'>Đang giao</span>";
                                        break;
                                    case 'Đã giao':
                                        echo "<span style='color: darkgreen;'>Đã giao</span>";
                                        break;
                                    case 'Đã hủy':
                                        echo "<span style='color: red;'>Đã hủy</span>";
                                        break;
                                    default:
                                        echo htmlspecialchars($order['status']);
                                        break;
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($order['status'] == 'Chờ xác nhận'): ?>
                                    <form action="" method="POST">
                                        <input type="hidden" name="cancel_order_id" value="<?= $order_id ?>">
                                        <button type="submit" onclick="return confirm('Bạn có chắc chắn muốn hủy đơn hàng này?');"
                                            style="color: red;">Hủy Đơn</button>
                                    </form>
                                <?php else: ?>
                                    <span>Không thể hủy</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?page=<?= $page ?>&detail_order_id=<?= $order_id ?>" class="detail-btn" style="color: blue;">Xem chi tiết</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">Bạn chưa có đơn hàng nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Phân trang: Định dạng mới -->
 
    </div>

    <!-- Modal chi tiết đơn hàng -->
    <?php if ($order_details): ?>
        <div id="orderDetailModal" class="modal" style="display: block;">
            <div class="modal-content">
                <a href="?page=<?= $page ?>" class="close">×</a>
                <h2>Chi tiết đơn hàng</h2>
                <div id="orderDetailContent">
                    <h3>Đơn hàng <?= htmlspecialchars($order_details['order_id']) ?></h3>
                    <p><strong>Ngày đặt:</strong> <?= htmlspecialchars($order_details['created_at']) ?></p>
                    <p><strong>Trạng thái:</strong> <?= htmlspecialchars($order_details['status']) ?></p>
                    <p><strong>Tổng tiền:</strong> <?= htmlspecialchars($order_details['total_amount']) ?>đ</p>
                    <p><strong>Người nhận:</strong> <?= htmlspecialchars($order_details['recipient_name']) ?></p>
                    <p><strong>Số điện thoại:</strong> <?= htmlspecialchars($order_details['phone_number']) ?></p>
                    <p><strong>Địa chỉ giao hàng:</strong> <?= htmlspecialchars($order_details['shipping_address']) ?></p>
                    <h4>Danh sách sách</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Hình ảnh</th>
                                <th>Tên sách</th>
                                <th>Số lượng</th>
                                <th>Giá</th>
                                <th>Tổng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_details['books'] as $book): ?>
                                <tr>
                                    <td><img src="<?= htmlspecialchars($book['image_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>" width="50"></td>
                                    <td><?= htmlspecialchars($book['title']) ?></td>
                                    <td><?= htmlspecialchars($book['quantity']) ?></td>
                                    <td><?= htmlspecialchars($book['price']) ?>đ</td>
                                    <td><?= number_format($book['quantity'] * str_replace('.', '', $book['price']), 0, ',', '.') ?>đ</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div style="margin-top:30vh;">
        <?php include 'footer.php'; ?>
    </div>

    <?php $conn->close(); ?>
</body>
<script src="../vendor/dropdown.js"></script>
</html>