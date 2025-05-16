<?php
session_start();
require '../back-end/db_connect.php';
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header('Location: store.php');
  exit;
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
  echo "Sách không tồn tại.";
  exit;
}

$book = $result->fetch_assoc();

// Truy vấn sách cùng thể loại
$category = $book['category'];
$stmt_related = $conn->prepare("SELECT * FROM books WHERE category = ? AND id != ? LIMIT 5");
$stmt_related->bind_param("si", $category, $id);
$stmt_related->execute();
$related_books_result = $stmt_related->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title><?= $book['title']; ?> - Chi tiết sách</title>
  <link rel="stylesheet" href="../vendor/style.css">
  <link rel="stylesheet" href="../vendor/book_detail.css">
</head>
<body>
<?php require 'header.php'; ?>

<div class="book-detail-container">
  <div class="book-image">
    <img src="<?= $book['image_url']; ?>" alt="<?= $book['title']; ?>">
  </div>
  <div class="book-info">
    <h2><?= $book['title']; ?></h2>
    <p><strong>Tác giả:</strong> <?= $book['author']; ?></p>
    <p><strong>Thể loại:</strong> <?= $book['category']; ?></p>
    <p><strong>Giá:</strong> <?= number_format($book['price'], 0, ',', '.'); ?> VND</p>
    <p><strong>Mô tả:</strong></p>
    <p><?= nl2br($book['description']); ?></p>

    <form method="post" action="../back-end/add_to_cart.php">
      <input type="hidden" name="book_id" value="<?= $book['id']; ?>">
      <button type="submit" class="cart-btn">
        Thêm vào giỏ hàng
      </button>
    </form>

        <!-- Nút Mua ngay -->
    <form method="post" action="checkout.php">
      <input type="hidden" name="book_id" value="<?= $book['id']; ?>">
      <button type="submit" class="buy-now-btn" name="buy_now">
        Mua ngay
      </button>
    </form>
    
    <br>
    <a href="store.php" class="back_home">← Quay lại cửa hàng</a>
  </div>
</div>

<!-- Sách cùng thể loại -->
<div class="related-books">
  <h3>Sách cùng thể loại</h3>
  <div class="related-books-container">
    <?php while ($related_book = $related_books_result->fetch_assoc()) { ?>
      <div class="related-book">
        <a href="book_detail.php?id=<?= $related_book['id']; ?>">
          <img src="<?= $related_book['image_url']; ?>" alt="<?= $related_book['title']; ?>">
          <p><?= $related_book['title']; ?></p>
          <p><strong>Giá:</strong> <?= number_format($related_book['price'], 0, ',', '.'); ?> VND</p>
        </a>
      </div>
    <?php } ?>
  </div>
</div>
<div style="margin-top: 30vh;"><?php require 'footer.php'; ?>
</div>
</body>
<script src="../vendor/dropdown.js"></script>
</html>

<?php $conn->close(); ?>
