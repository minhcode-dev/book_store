<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../front-end/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$selected_items = isset($_POST['selected_items']) ? $_POST['selected_items'] : [];
$book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : null;
$recipient_name = isset($_POST['recipient_name']) ? trim($_POST['recipient_name']) : '';
$phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
$shipping_address = isset($_POST['shipping_address']) ? trim($_POST['shipping_address']) : '';

if (empty($shipping_address) && isset($_POST['new_address'])) {
    $shipping_address = trim($_POST['new_address']);
}

$payment_method = isset($_POST['payment_method']) && !empty($_POST['payment_method']) ? $_POST['payment_method'] : 'COD';
$payment_text = null;

if ($payment_method) {
    $payment_method_map = [
        'COD' => 'Thanh toán khi nhận hàng (COD)',
        'BANK' => 'Chuyển khoản ngân hàng'
    ];
    $payment_text = isset($payment_method_map[$payment_method]) ? $payment_method_map[$payment_method] : 'Thanh toán khi nhận hàng (COD)';
}

// Kiểm tra dữ liệu đầu vào
if ((empty($selected_items) && empty($book_id)) || empty($recipient_name) || empty($phone_number) || empty($shipping_address)) {
    header("Location: ../front-end/checkout.php");
    exit();
}

// Bắt đầu giao dịch
$conn->begin_transaction();

try {
    $total_amount = 0;
    $items = [];

    // Trường hợp thanh toán từ giỏ hàng
    if (!empty($selected_items)) {
        $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
        $query = "SELECT c.cart_id, c.book_id, c.quantity, b.price 
                  FROM cart c
                  JOIN books b ON c.book_id = b.id
                  WHERE c.user_id = ? AND c.cart_id IN ($placeholders)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param(str_repeat('i', count($selected_items) + 1), $user_id, ...$selected_items);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
            $total_amount += $row['price'] * $row['quantity'];
        }
    }
    // Trường hợp "Mua ngay"
    elseif ($book_id) {
        $stmt = $conn->prepare("SELECT id AS book_id, price FROM books WHERE id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Sách không tồn tại.");
        }

        $book = $result->fetch_assoc();
        $items[] = [
            'book_id' => $book['book_id'],
            'quantity' => 1,
            'price' => $book['price']
        ];
        $total_amount = $book['price'];
    } else {
        throw new Exception("Không có sản phẩm để thanh toán.");
    }

    // Tạo đơn hàng
    $query = "INSERT INTO orders (user_id, total_amount, recipient_name, phone_number, shipping_address, payment_method) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("idssss", $user_id, $total_amount, $recipient_name, $phone_number, $shipping_address, $payment_text);
    $stmt->execute();
    $order_id = $conn->insert_id;

    // Thêm chi tiết đơn hàng
    $query = "INSERT INTO order_items (order_id, book_id, quantity, price) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    foreach ($items as $item) {
        $stmt->bind_param("iiid", $order_id, $item['book_id'], $item['quantity'], $item['price']);
        $stmt->execute();
    }

    // Xóa sản phẩm khỏi giỏ hàng (nếu có)
    if (!empty($selected_items)) {
        $query = "DELETE FROM cart WHERE user_id = ? AND cart_id IN ($placeholders)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param(str_repeat('i', count($selected_items) + 1), $user_id, ...$selected_items);
        $stmt->execute();
    }

    // Commit giao dịch
    $conn->commit();

    // Chuyển hướng đến trang xác nhận
    header("Location: ../front-end/order_confirmation.php?order_id=$order_id");
    exit();
} catch (Exception $e) {
    // Rollback nếu có lỗi
    $conn->rollback();
    echo "Lỗi thanh toán: " . $e->getMessage();
    exit();
}
?>