<?php
session_start();
require '../back-end/db_connect.php';
// Danh sách chủ đề sách
$categories = ["Tiểu thuyết", "Khoa học", "Lịch sử", "Tâm lý", "Văn học"];
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Min Book</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../vendor/style.css">
</head>

<body>
    <?php include 'header.php'; ?>

    <?php foreach ($categories as $category): ?>
        <div class="category">
            <div class="category-header">
                <h2>
                    <?php
                   
                    echo $category;
                    ?>
                </h2>
                <a class="view-all" href="store.php?category=<?= urlencode($category) ?>">Xem tất cả →</a>
            </div>
            <div class="book-slider">
                <?php
                $stmt = $conn->prepare("SELECT * FROM books WHERE category = ? and is_deleted=0 LIMIT 20");
                $stmt->bind_param("s", $category);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($book = $result->fetch_assoc()):
                ?>
                    <div class="book">
                        <a style="text-decoration:none;color:black" href="book_detail.php?id=<?= $book['id'] ?>">
                            <img src="<?= htmlspecialchars($book['image_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
                            <h4 ><?= htmlspecialchars($book['title']) ?></h4>
                        </a>
                        <div class="book-footer">
                            <p class="price"><?= number_format($book['price'], 0, ',', '.') ?>đ</p>
                            <form action="../back-end/add_to_cart.php" method="POST" class="cart-form">
                                <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                <button type="submit" class="add-to-cart-btn" title="Thêm vào giỏ">
                                    <div class="add-to-cart-icon" title="Thêm vào giỏ hàng">🛒</div>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php require 'footer.php' ?>
</body>
<script src="../vendor/dropdown.js"></script>

</html>
