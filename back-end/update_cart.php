<?php
session_start();
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_id']) && isset($_POST['quantity'])) {
    $cart_id = intval($_POST['cart_id']);
    $quantity = intval($_POST['quantity']);

    // Kiểm tra số lượng hợp lệ
    if ($quantity <= 0) {
        echo "Số lượng không hợp lệ.";
        exit();
    }

    // Lấy book_id từ cart_id
    $query = "SELECT book_id FROM cart WHERE cart_id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $cart_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $book_id = $row['book_id'];

        // Cập nhật số lượng trong session
        if (isset($_SESSION['cart'][$book_id])) {
            $_SESSION['cart'][$book_id]['quantity'] = $quantity;
        }

        // Cập nhật số lượng trong cơ sở dữ liệu
        $update_query = "UPDATE cart SET quantity = ? WHERE cart_id = ? AND user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        if (!$update_stmt) {
            die('Lỗi chuẩn bị truy vấn: ' . $conn->error);
        }
        $update_stmt->bind_param("iii", $quantity, $cart_id, $_SESSION['user_id']);
        $update_stmt->execute();
    }

    // Quay lại trang giỏ hàng
    header("Location: ../front-end/cart.php");
    exit();
} else {
    echo "Dữ liệu không hợp lệ hoặc yêu cầu không hợp lệ.";
}
?>