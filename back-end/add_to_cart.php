<?php
session_start();
require 'db_connect.php';

if (isset($_POST['book_id'])) {
    $book_id = intval($_POST['book_id']);

    // Lấy thông tin sách
    $query = "SELECT * FROM books WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $book = $result->fetch_assoc();

        // Kiểm tra nếu người dùng đã đăng nhập
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];

            // Kiểm tra xem sản phẩm đã tồn tại trong giỏ của người dùng chưa
            $check_query = "SELECT * FROM cart WHERE user_id = ? AND book_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("ii", $user_id, $book_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                // Nếu có rồi, cập nhật số lượng
                $row = $check_result->fetch_assoc();
                $new_quantity = $row['quantity'] + 1;
                $update_query = "UPDATE cart SET quantity = ? WHERE user_id = ? AND book_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("iii", $new_quantity, $user_id, $book_id);
                $update_stmt->execute();

                // Cập nhật session
                $_SESSION['cart'][$book_id]['quantity'] = $new_quantity;
            } else {
                // Nếu chưa có, thêm mới vào cơ sở dữ liệu
                $quantity = 1;
                $price = $book['price'];
                $insert_query = "INSERT INTO cart (user_id, book_id, quantity, price) VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("iiid", $user_id, $book_id, $quantity, $price);
                $insert_stmt->execute();

                // Thêm vào session
                $_SESSION['cart'][$book_id] = [
                    'title' => $book['title'],
                    'price' => $book['price'],
                    'image_url' => $book['image_url'],
                    'quantity' => $quantity
                ];
            }
        } else {
            // Nếu chưa đăng nhập, chỉ xử lý trong session
            if (isset($_SESSION['cart'][$book_id])) {
                $_SESSION['cart'][$book_id]['quantity']++;
            } else {
                $_SESSION['cart'][$book_id] = [
                    'title' => $book['title'],
                    'price' => $book['price'],
                    'image_url' => $book['image_url'],
                    'quantity' => 1
                ];
            }
        }

        // Chuyển hướng về trang giỏ hàng
        header('Location: ../front-end/cart.php');
        exit();
    } else {
        echo "Sản phẩm không tồn tại.";
    }
} else {
    echo "Không có sản phẩm được chọn.";
}
?>