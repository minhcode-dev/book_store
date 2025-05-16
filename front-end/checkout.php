<?php
session_start();
require '../back-end/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$items = [];
$total = 0;
$selected_items = [];
$stored_address = ''; // Địa chỉ từ bảng users
$stored_name = '';   // Tên người nhận từ đơn hàng gần nhất
$stored_phone = '';  // Số điện thoại từ đơn hàng gần nhất

// Truy vấn lấy địa chỉ từ bảng users
$stmt = $conn->prepare("SELECT address FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->bind_result($stored_address);
    $stmt->fetch();
}
$stmt->close();

// Truy vấn lấy tên và số điện thoại từ đơn hàng gần nhất
$stmt = $conn->prepare("SELECT recipient_name, phone_number FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->bind_result($stored_name, $stored_phone);
    $stmt->fetch();
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trường hợp thanh toán từ giỏ hàng
    if (isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        $selected_items = $_POST['selected_items'];
        if (!empty($selected_items)) {
            $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
            $query = "SELECT c.cart_id, b.title, b.price, c.quantity, b.image_url 
                      FROM cart c
                      JOIN books b ON c.book_id = b.id
                      WHERE c.user_id = ? AND c.cart_id IN ($placeholders)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param(str_repeat('i', count($selected_items) + 1), $user_id, ...$selected_items);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
                $total += $row['price'] * $row['quantity'];
            }
            $stmt->close();
        }
    }
    // Trường hợp mua ngay một sách
    elseif (isset($_POST['book_id'])) {
        $book_id = intval($_POST['book_id']);

        $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo "Sách không tồn tại.";
            exit;
        }

        $book = $result->fetch_assoc();
        $items[] = [
            'cart_id' => null,
            'title' => $book['title'],
            'price' => $book['price'],
            'quantity' => 1,
            'image_url' => $book['image_url']
        ];

        $total = $book['price'];
        $stmt->close();
    } else {
        echo "Không có sản phẩm được chọn.";
        exit;
    }
} else {
    echo "Phương thức gửi dữ liệu không hợp lệ.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Toán - Min Book</title>
    <link rel="stylesheet" href="../vendor/style.css">
    <link rel="stylesheet" href="../vendor/cart.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="cart-container">
        <h2>Thanh Toán</h2>
        <?php if (empty($items)): ?>
            <p>Không có sản phẩm nào được chọn để thanh toán.</p>
            <a href="cart.php" class="checkout-btn">Quay lại giỏ hàng</a>
        <?php else: ?>
            <div class="cart-table-container">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Ảnh</th>
                            <th>Tên Sách</th>
                            <th>Giá</th>
                            <th>Số Lượng</th>
                            <th>Tổng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" width="50"></td>
                                <td><?= htmlspecialchars($item['title']) ?></td>
                                <td><?= number_format($item['price'], 0, ',', '.') ?>đ</td>
                                <td><?= $item['quantity'] ?></td>
                                <td><?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>đ</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="cart-summary">
                <h3>Thông Tin Giao Hàng</h3>
                <form action="../back-end/process_checkout.php" method="POST" class="checkout-form">
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="recipient_name">Họ và Tên:</label>
                            <input type="text" id="recipient_name" name="recipient_name" placeholder="Nguyễn Văn A" value="<?= htmlspecialchars($stored_name) ?>" required>
                        </div>
                        <div class="form-group form-group-half">
                            <label for="phone_number">Số Điện Thoại:</label>
                            <input type="tel" id="phone_number" name="phone_number" placeholder="0123456789" value="<?= htmlspecialchars($stored_phone) ?>" required>
                        </div>
                    </div>
                    <div class="form-group" style="display: flex; flex-direction:column">
                        <label for="shipping_address">Địa Chỉ Giao Hàng:</label>
                        <select style="width: 80%; height:40px" id="shipping_address" name="shipping_address" onchange="toggleAddressInput()">
                            <?php if (!empty($stored_address)): ?>
                                <option value="<?= htmlspecialchars($stored_address) ?>" selected>
                                    <?= htmlspecialchars($stored_address) ?>
                                </option>
                            <?php endif; ?>
                            <option value="">Nhập địa chỉ mới</option>
                        </select>
                        <textarea id="new_address" name="new_address" placeholder="Nhập địa chỉ mới..." style="display: <?= empty($stored_address) ? 'block' : 'none' ?>;"></textarea>
                        <span id="address-error" style="color:red;display:none;">Vui lòng nhập địa chỉ giao hàng</span>
                        <label style="margin-top: 20px;" for="payment_method">Phương Thức Thanh Toán:</label>
                        <select style="width: 80%;" id="payment_method" name="payment_method" required>
                            <option value="">-- Chọn phương thức --</option>
                            <option value="COD">Thanh toán khi nhận hàng (COD)</option>
                            <option value="BANK">Chuyển khoản ngân hàng</option>
                        </select>
                    </div>
                    <?php if (!empty($selected_items)): ?>
                        <?php foreach ($selected_items as $cart_id): ?>
                            <input type="hidden" name="selected_items[]" value="<?= htmlspecialchars($cart_id) ?>">
                        <?php endforeach; ?>
                    <?php elseif (isset($_POST['book_id'])): ?>
                        <input type="hidden" name="book_id" value="<?= htmlspecialchars($_POST['book_id']) ?>">
                    <?php endif; ?>
                    <h3>Tổng Tiền: <?= number_format($total, 0, ',', '.') ?>đ</h3>
                    <div class="form-actions">
                        <button type="submit" class="checkout-btn">Xác Nhận Thanh Toán</button>
                        <a href="cart.php" class="checkout-btn secondary-btn">Quay lại giỏ hàng</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <div style="margin-top:30vh;">
        <?php include 'footer.php'; ?>
    </div>
    <script>
        function toggleAddressInput() {
            var shippingAddressSelect = document.getElementById('shipping_address');
            var newAddressInput = document.getElementById('new_address');
            if (shippingAddressSelect.value === '') {
                newAddressInput.style.display = 'block';
            } else {
                newAddressInput.style.display = 'none';
            }
        }
    </script>
</body>
<script src="../vendor/dropdown.js"></script>
</html>