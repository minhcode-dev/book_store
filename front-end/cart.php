<?php
session_start();
require '../back-end/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Lấy danh sách sản phẩm trong giỏ hàng
$query = "SELECT c.cart_id, b.title, b.price, c.quantity, b.image_url 
          FROM cart c
          JOIN books b ON c.book_id = b.id
          WHERE c.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ Hàng - Min Book</title>
    <link rel="stylesheet" href="../vendor/style.css">
    <link rel="stylesheet" href="../vendor/cart.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="cart-container">
        <h2>Giỏ Hàng Của Bạn (<?php echo $result->num_rows; ?> sản phẩm)</h2>
        <div class="cart-table-container">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all" onchange="toggleSelectAll()"> Chọn</th>
                        <th>Ảnh</th>
                        <th>Tên Sách</th>
                        <th>Giá</th>
                        <th>Số Lượng</th>
                        <th>Tổng</th>
                        <th>Hành Động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $item_total = $row['price'] * $row['quantity'];
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="item-checkbox" value="<?= $row['cart_id'] ?>" 
                                           onchange="updateTotal()" data-price="<?= $row['price'] ?>" 
                                           data-quantity="<?= $row['quantity'] ?>">
                                </td>
                                <td><img src="<?= htmlspecialchars($row['image_url']) ?>" alt="<?= htmlspecialchars($row['title']) ?>" width="50"></td>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= number_format($row['price'], 0, ',', '.') ?>đ</td>
                                <td>
                                    <form method="POST" action="../back-end/update_cart.php" class="update-form">
                                        <input type="number" name="quantity" value="<?= $row['quantity'] ?>" min="1" max="99" />
                                        <input type="hidden" name="cart_id" value="<?= $row['cart_id'] ?>" />
                                        <button type="submit">Cập Nhật</button>
                                    </form>
                                </td>
                                <td><?= number_format($item_total, 0, ',', '.') ?>đ</td>
                                <td>
                                    <a href="#" class="remove-btn" onclick="return confirmDelete(<?= $row['cart_id'] ?>)">Xóa</a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo "<tr><td colspan='7'>Giỏ hàng của bạn đang trống.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <form id="cart-form" action="checkout.php" method="POST" onsubmit="return validateForm()">
                <div class="cart-summary">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <h3>Tổng Tiền: <span id="total-amount">0</span>đ</h3>
                        <div id="selected-items-container"></div>
                        <button type="submit" class="checkout-btn" id="checkout-btn" disabled>Thanh Toán</button>

                    <?php endif; ?>
                </div>
            </form>

        </div>
    </div>
    <div style="margin-top:30vh;">
        <?php include 'footer.php'; ?>
    </div>
    <script>
        // Chọn/t bỏ chọn tất cả
        function toggleSelectAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            updateTotal();
        }

        // Cập nhật tổng tiền và trạng thái nút Thanh Toán
        function updateTotal() {
            const checkboxes = document.querySelectorAll('.item-checkbox:checked');
            let total = 0;
            checkboxes.forEach(checkbox => {
                const price = parseFloat(checkbox.getAttribute('data-price'));
                const quantity = parseInt(checkbox.getAttribute('data-quantity'));
                total += price * quantity;
            });
            document.getElementById('total-amount').textContent = total.toLocaleString('vi-VN');
            document.getElementById('checkout-btn').disabled = checkboxes.length === 0;
        }

        // Kiểm tra form trước khi gửi
        function validateForm() {
            const checkboxes = document.querySelectorAll('.item-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Vui lòng chọn ít nhất một sản phẩm để thanh toán.');
                return false;
            }

            // Thêm input ẩn cho selected_items
            const container = document.getElementById('selected-items-container');
            container.innerHTML = ''; // Xóa input cũ
            checkboxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_items[]';
                input.value = checkbox.value;
                container.appendChild(input);
            });

            return true;
        }

        // Xác nhận xóa
        function confirmDelete(cart_id) {
            var confirmed = confirm("Bạn có chắc chắn muốn xóa sản phẩm này khỏi giỏ hàng?");
            if (confirmed) {
                window.location.href = '../back-end/remove_from_cart.php?cart_id=' + cart_id;
            }
            return false;
        }

        // Khởi tạo tổng tiền khi tải trang
        document.addEventListener('DOMContentLoaded', updateTotal);
    </script>
</body>
<script src="../vendor/dropdown.js"></script>

</html>