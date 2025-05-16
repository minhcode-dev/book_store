<?php
session_start();
require '../back-end/db_connect.php'; // Kết nối cơ sở dữ liệu

// Kiểm tra nếu có tham số cart_id trong URL (khi nhấn nút "Xóa" trong giỏ hàng)
if (isset($_GET['cart_id'])) {
    $cart_id = intval($_GET['cart_id']);  // Lấy cart_id từ URL

    // Kiểm tra nếu cart_id hợp lệ
    if ($cart_id > 0) {
        // Xóa sản phẩm khỏi giỏ hàng trong session
        if (isset($_SESSION['cart']) && isset($_SESSION['cart'][$cart_id])) {
            unset($_SESSION['cart'][$cart_id]);
        }

        // Kiểm tra nếu người dùng đã đăng nhập (có user_id trong session)
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];

            // Xóa sản phẩm khỏi cơ sở dữ liệu (bảng cart)
            $delete_query = "DELETE FROM cart WHERE user_id = ? AND cart_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            if (!$delete_stmt) {
                die('Lỗi chuẩn bị truy vấn: ' . $conn->error);
            }
            $delete_stmt->bind_param("ii", $user_id, $cart_id);
            $delete_stmt->execute();

            // Kiểm tra xem có ảnh hưởng gì đến cơ sở dữ liệu không
            if ($delete_stmt->affected_rows > 0) {
                echo "Sản phẩm đã bị xóa thành công khỏi giỏ hàng.";
            } else {
                echo "Không tìm thấy sản phẩm trong cơ sở dữ liệu.";
            }
        }

        // Quay lại trang giỏ hàng sau khi xóa
        header("Location: ../front-end/cart.php");
        exit();
    } else {
        echo "Dữ liệu không hợp lệ.";
    }
} else {
    echo "Không có tham số cart_id.";
}
?>
