<?php
session_start();
require '../back-end/process_store.php';
?>

<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <title>Cửa Hàng Sách - Min Books</title>
  <link rel="stylesheet" href="../vendor/style.css">
  <link rel="stylesheet" href="../vendor/store.css">
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
  </style>
</head>

<body>
  <?php require 'header.php'; ?>

  <div class="filter-container">
    <form method="GET" action="store.php">
      <select name="category" onchange="this.form.submit()">
        <option value="">Tất cả thể loại</option>
        <option value="Tiểu Thuyết" <?= $category_filter == 'Tiểu Thuyết' ? 'selected' : '' ?>>Tiểu Thuyết</option>
        <option value="Khoa Học" <?= $category_filter == 'Khoa Học' ? 'selected' : '' ?>>Khoa Học</option>
        <option value="Lịch Sử" <?= $category_filter == 'Lịch Sử' ? 'selected' : '' ?>>Lịch Sử</option>
        <option value="Tâm Lý" <?= $category_filter == 'Tâm Lý' ? 'selected' : '' ?>>Tâm Lý</option>
        <option value="Văn Học" <?= $category_filter == 'Văn Học' ? 'selected' : '' ?>>Văn Học</option>
      </select>

      <select name="price_range" onchange="this.form.submit()">
        <option value="">Chọn giá</option>
        <option value="0-100000" <?= $price_range == '0-100000' ? 'selected' : '' ?>>Dưới 100,000 VND</option>
        <option value="100000-500000" <?= $price_range == '100000-500000' ? 'selected' : '' ?>>100,000 - 500,000</option>
        <option value="500000-1000000" <?= $price_range == '500000-1000000' ? 'selected' : '' ?>>500,000 - 1,000,000
        </option>
        <option value="1000000+" <?= $price_range == '1000000+' ? 'selected' : '' ?>>Trên 1,000,000</option>
      </select>

      <input type="text" name="keyword" placeholder="Tìm kiếm..." value="<?= htmlspecialchars($keyword_filter) ?>"
        onchange="this.form.submit()">
    </form>
  </div>

  <div class="books-container">
    <?php if ($result->num_rows > 0): ?>
      <?php while ($book = $result->fetch_assoc()): ?>
        <!-- Bao bọc toàn bộ phần tử sách trong thẻ <a> để người dùng có thể nhấn vào bất kỳ đâu -->
        <a href="book_detail.php?id=<?= $book['id']; ?>" class="book-item">
          <div class="book-item-inner">
            <img src="<?= $book['image_url']; ?>" alt="<?= $book['title']; ?>">
            <h3><?= $book['title']; ?></h3>
            <p><strong>Tác giả:</strong> <?= $book['author']; ?></p>
            <p><strong>Giá:</strong> <?= number_format($book['price'], 0, ',', '.'); ?> VND</p>
          </div>
          <div class="add-to-cart-icon" title="Thêm vào giỏ hàng">🛒</div>
        </a>
      <?php endwhile; ?>

      <?php if ($result->num_rows < 6): ?>
        <!-- Nếu sách ít hơn 6 cuốn, thêm padding phía dưới để footer không bị đẩy lên -->
        <div style="height: 200px;"></div>
      <?php endif; ?>

    <?php else: ?>
      <p style="text-align: center; margin-top: 100px;">Không có sách nào phù hợp.</p>
      <!-- Thêm khoảng trắng dưới cùng khi không có sách -->
      <div style="height: 300px;"></div>
    <?php endif; ?>
  </div>

  <!-- Phân trang: Định dạng mới -->
  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <a href="?page=<?= $page > 1 ? $page - 1 : 1; ?>&category=<?= urlencode($category_filter); ?>&price_range=<?= urlencode($price_range); ?>&keyword=<?= urlencode($keyword_filter); ?>"
          class="<?= $page <= 1 ? 'disabled' : ''; ?>">«</a>
      <span class="current">Trang <?= $page; ?> / <?= $total_pages; ?></span>
      <a href="?page=<?= $page < $total_pages ? $page + 1 : $total_pages; ?>&category=<?= urlencode($category_filter); ?>&price_range=<?= urlencode($price_range); ?>&keyword=<?= urlencode($keyword_filter); ?>"
          class="<?= $page >= $total_pages ? 'disabled' : ''; ?>">»</a>
    </div>
  <?php else: ?>
    <p style="text-align: center; margin-top: 20px;">Không có thêm trang vì số sách nhỏ hơn hoặc bằng giới hạn.</p>
  <?php endif; ?>

  <?php require 'footer.php'; ?>
</body>
<script src="../vendor/dropdown.js"></script>

</html>

<?php $conn->close(); ?>