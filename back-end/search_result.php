<?php
session_start();
require '../back-end/db_connect.php';

// Lấy từ khóa tìm kiếm từ URL
$query = isset($_GET['query']) ? $_GET['query'] : '';

// Truy vấn tìm sách theo tên hoặc mô tả
$sql = "SELECT * FROM books WHERE title LIKE ? OR description LIKE ?";
$stmt = $conn->prepare($sql);
$search_term = '%' . $query . '%';
$stmt->bind_param("ss", $search_term, $search_term);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kết Quả Tìm Kiếm - Min Book</title>
    <link rel="stylesheet" href="../vendor/style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="search-results-container">
        <h2>Kết Quả Tìm Kiếm: <?= htmlspecialchars($query) ?></h2>
        
        <div class="products">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($book = $result->fetch_assoc()): ?>
                    <div class="product-item">
                        <img src="<?= htmlspecialchars($book['image_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>" width="100">
                        <h3><?= htmlspecialchars($book['title']) ?></h3>
                        <p><?= substr(htmlspecialchars($book['description']), 0, 100) ?>...</p>
                        <a href="../back-end/product_detail.php?id=<?= $book['id'] ?>">Xem chi tiết</a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>Không có sách nào phù hợp với tìm kiếm của bạn.</p>
            <?php endif; ?>
        </div>
    </div>

    <div style="margin-top:30vh;">
        <?php include 'footer.php'; ?>
    </div>
</body>
</html>
