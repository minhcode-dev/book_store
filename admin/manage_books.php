<?php
ob_start();
session_start();
require '../back-end/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$success = $error = '';
$books_per_page = 10;
$categories = ['Tiểu Thuyết', 'Khoa Học', 'Lịch Sử', 'Tâm Lý', 'Văn Học'];

// Xử lý chế độ hiển thị (sách hiện tại hoặc sách đã xóa)
$view_mode = isset($_GET['view_mode']) && $_GET['view_mode'] === 'deleted' ? 'deleted' : 'active';
$is_deleted_filter = $view_mode === 'deleted' ? 1 : 0;

// Xử lý bộ lọc danh mục
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$where_clause = "WHERE is_deleted = ?";
$where_params = [$is_deleted_filter];
if ($category_filter && in_array($category_filter, $categories)) {
    $where_clause .= " AND category = ?";
    $where_params[] = $category_filter;
}

// Xử lý upload sách
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $category = in_array($_POST['category'], $categories) ? $_POST['category'] : '';
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);
    $image_url = '';

    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../Uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $image_url = $target_dir . basename($_FILES['image']['name']);
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_url)) {
            $error = "Lỗi khi upload hình ảnh.";
        }
    }

    if (!$category) {
        $error = "Vui lòng chọn danh mục hợp lệ.";
    }

    if (!$error) {
        $stmt = $conn->prepare("INSERT INTO books (title, author, category, price, description, image_url, is_deleted) VALUES (?, ?, ?, ?, ?, ?, 0)");
        if (!$stmt) {
            $error = "Lỗi chuẩn bị truy vấn: " . $conn->error;
        } else {
            $stmt->bind_param("sssdss", $title, $author, $category, $price, $description, $image_url);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Thêm sách thành công.";
                header("Location: manage_books.php?page=$page" . ($category_filter ? '&category=' . urlencode($category_filter) : '') . ($view_mode ? '&view_mode=' . $view_mode : ''));
                exit();
            } else {
                $error = "Lỗi khi thêm sách: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Xử lý xóa sách
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_book'])) {
    $book_id = intval($_POST['book_id']);
    $delete_type = isset($_POST['delete_type']) && $_POST['delete_type'] === 'hard' ? 'hard' : 'soft';

    // Kiểm tra xem sách có trong order_items không
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE book_id = ?");
    if (!$stmt) {
        $error = "Lỗi chuẩn bị truy vấn kiểm tra đơn hàng: " . $conn->error;
    } else {
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();

        if ($count > 0) {
            // Sách đã được bán, thực hiện xóa mềm
            $stmt = $conn->prepare("UPDATE books SET is_deleted = 1 WHERE id = ? AND is_deleted = 0");
            if (!$stmt) {
                $error = "Lỗi chuẩn bị truy vấn xóa mềm: " . $conn->error;
            } else {
                $stmt->bind_param("i", $book_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $_SESSION['success'] = "Sách đã được ẩn thành công vì đã có đơn hàng liên quan.";
                        header("Location: manage_books.php?page=$page" . ($category_filter ? '&category=' . urlencode($category_filter) : '') . ($view_mode ? '&view_mode=' . $view_mode : ''));
                        exit();
                    } else {
                        $error = "Sách không tồn tại hoặc đã được ẩn.";
                    }
                } else {
                    $error = "Lỗi khi ẩn sách: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            // Sách chưa được bán
            if ($delete_type === 'hard') {
                // Thực hiện xóa vật lý
                $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
                if (!$stmt) {
                    $error = "Lỗi chuẩn bị truy vấn xóa vật lý: " . $conn->error;
                } else {
                    $stmt->bind_param("i", $book_id);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $_SESSION['success'] = "Xóa sách thành công.";
                            header("Location: manage_books.php?page=$page" . ($category_filter ? '&category=' . urlencode($category_filter) : '') . ($view_mode ? '&view_mode=' . $view_mode : ''));
                            exit();
                        } else {
                            $error = "Sách không tồn tại.";
                        }
                    } else {
                        $error = "Lỗi khi xóa sách: " . $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
                // Yêu cầu xác nhận xóa vật lý
                $error = "Sách chưa được bán. Vui lòng xác nhận để xóa hoàn toàn.";
                $_SESSION['confirm_delete_book_id'] = $book_id;
            }
        }
    }
}

// Xử lý khôi phục sách
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_book'])) {
    $book_id = intval($_POST['book_id']);
    $stmt = $conn->prepare("UPDATE books SET is_deleted = 0 WHERE id = ? AND is_deleted = 1");
    if (!$stmt) {
        $error = "Lỗi chuẩn bị truy vấn khôi phục: " . $conn->error;
    } else {
        $stmt->bind_param("i", $book_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['success'] = "Khôi phục sách thành công.";
                header("Location: manage_books.php?page=$page" . ($category_filter ? '&category=' . urlencode($category_filter) : '') . ($view_mode ? '&view_mode=' . $view_mode : ''));
                exit();
            } else {
                $error = "Sách không tồn tại hoặc chưa bị xóa.";
            }
        } else {
            $error = "Lỗi khi khôi phục sách: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Lấy tổng số sách
$total_books_query = "SELECT COUNT(*) AS total FROM books $where_clause";
$stmt = $conn->prepare($total_books_query);
if (!$stmt) {
    $error = "Lỗi chuẩn bị truy vấn tổng số sách: " . $conn->error;
    $total_books = 0;
} else {
    if ($where_params) {
        if (count($where_params) == 1) {
            $stmt->bind_param("i", $where_params[0]);
        } else {
            $stmt->bind_param("is", $where_params[0], $where_params[1]);
        }
    }
    $stmt->execute();
    $total_books = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}

// Tính toán phân trang
$total_pages = ceil($total_books / $books_per_page);
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$page = max(1, min($page, $total_pages > 0 ? $total_pages : 1));
$offset = ($page - 1) * $books_per_page;

// Lấy danh sách sách cho trang hiện tại
$query = "SELECT * FROM books $where_clause LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    $error = "Lỗi chuẩn bị truy vấn danh sách sách: " . $conn->error;
} else {
    if ($where_params) {
        if (count($where_params) == 1) {
            $stmt->bind_param("iii", $where_params[0], $books_per_page, $offset);
        } else {
            $stmt->bind_param("isii", $where_params[0], $where_params[1], $books_per_page, $offset);
        }
    } else {
        $stmt->bind_param("ii", $books_per_page, $offset);
    }
    $stmt->execute();
    $books = $stmt->get_result();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý sách</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .success {
            color: green;
            margin-bottom: 10px;
        }

        .error {
            color: red;
            margin-bottom: 10px;
        }

        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-group textarea {
            height: 100px;
        }

        .filter-form {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .filter-form label {
            margin-right: 10px;
        }

        .filter-form select {
            width: 200px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 5px;
            min-width: 80px;
            text-align: center;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .btn-restore {
            background: #28a745;
            color: white;
        }

        .btn-restore:hover {
            background: #218838;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th,
        .table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        .table th {
            background: #f2f2f2;
            font-weight: bold;
        }

        .table img {
            max-width: 100px;
            height: auto;
        }

        .pagination {
            margin-top: 20px;
            text-align: center;
        }

        .pagination a,
        .pagination span {
            margin: 0 5px;
            text-decoration: none;
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .pagination a:hover {
            background: #f2f2f2;
        }

        .pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .pagination .disabled {
            color: #ccc;
            border-color: #ccc;
            pointer-events: none;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 5px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .view-mode-form {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .view-mode-form label {
            margin-right: 10px;
        }

        .view-mode-form select {
            width: 200px;
        }
    </style>
</head>

<body>
    <?php require 'header.php'; ?>
    <div class="admin-container">
        <h2 style="text-align: center;">Quản lý sách</h2>
        <?php
        if (isset($_SESSION['success'])) {
            echo '<p class="success">' . htmlspecialchars($_SESSION['success']) . '</p>';
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo '<p class="error">' . htmlspecialchars($_SESSION['error']) . '</p>';
            unset($_SESSION['error']);
        }
        if ($success) {
            echo '<p class="success">' . htmlspecialchars($success) . '</p>';
        }
        if ($error) {
            echo '<p class="error">' . htmlspecialchars($error) . '</p>';
        }
        ?>
        <!-- Modal: Thêm sách -->
        <div id="addBookModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeAddBookModal()">×</span>
                <h3>Thêm sách mới</h3>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title">Tiêu đề:</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="author">Tác giả:</label>
                        <input type="text" id="author" name="author" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Danh mục:</label>
                        <select id="category" name="category" required>
                            <option value="">Chọn danh mục</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>">
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="price">Giá:</label>
                        <input type="number" id="price" name="price" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Mô tả:</label>
                        <textarea id="description" name="description"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="image">Hình ảnh:</label>
                        <input type="file" id="image" name="image" accept="image/*" onchange="previewImage(event)">
                        <img id="image-preview" src="../Uploads/default.jpg" style="max-width:200px;" alt="Hình ảnh sách">
                    </div>
                    <button type="submit" name="add_book" class="btn btn-primary">Thêm sách</button>
                </form>
            </div>
        </div>
        <!-- Chọn chế độ hiển thị -->
        <h3>Chế độ hiển thị</h3>
        <form class="view-mode-form" method="GET" action="">
            <label for="view_mode">Hiển thị:</label>
            <select id="view_mode" name="view_mode" onchange="this.form.submit()">
                <option value="active" <?php echo $view_mode == 'active' ? 'selected' : ''; ?>>Sách hiện tại</option>
                <option value="deleted" <?php echo $view_mode == 'deleted' ? 'selected' : ''; ?>>Sách đã xóa</option>
            </select>
            <?php if ($category_filter): ?>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
            <?php endif; ?>
            <?php if (isset($_GET['page'])): ?>
                <input type="hidden" name="page" value="<?php echo htmlspecialchars($_GET['page']); ?>">
            <?php endif; ?>
        </form>
        <!-- Lọc danh mục -->
        <h3>Lọc sách</h3>
        <form class="filter-form" method="GET" action="">
            <label for="category_filter">Danh mục:</label>
            <select id="category_filter" name="category" onchange="this.form.submit()">
                <option value="">Tất cả</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter == $category ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="view_mode" value="<?php echo htmlspecialchars($view_mode); ?>">
            <?php if (isset($_GET['page'])): ?>
                <input type="hidden" name="page" value="<?php echo htmlspecialchars($_GET['page']); ?>">
            <?php endif; ?>
        </form>
        <div style="display: flex; justify-content: flex-end;">
            <button onclick="openAddBookModal()" class="btn btn-primary">+ Thêm sách mới</button>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tiêu đề</th>
                    <th>Tác giả</th>
                    <th>Danh mục</th>
                    <th>Giá</th>
                    <th>Hình ảnh</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($books && $books->num_rows > 0): ?>
                    <?php while ($book = $books->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['id']); ?></td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['category'] ?: 'Không có danh mục'); ?></td>
                            <td><?php echo number_format($book['price'], 2); ?></td>
                            <td>
                                <?php if ($book['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($book['image_url']); ?>" alt="Hình ảnh sách">
                                <?php else: ?>
                                    Không có hình
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($view_mode == 'active'): ?>
                                    <a href="edit_book.php?id=<?php echo $book['id']; ?>" class="btn btn-primary">Sửa</a>
                                    <form method="POST" action="" style="display:inline;" class="delete-book-form">
                                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                        <input type="hidden" name="delete_book" value="1">
                                        <button type="submit" class="btn btn-delete" onclick="return confirmDelete(this, '<?php echo htmlspecialchars($book['title']); ?>')">Xóa</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="" style="display:inline;">
                                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                        <input type="hidden" name="restore_book" value="1">
                                        <button type="submit" class="btn btn-restore" onclick="return confirm('Bạn có chắc chắn muốn khôi phục sách \"<?php echo htmlspecialchars($book['title']); ?>\"?')">Khôi phục</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">Không có sách nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="pagination">
            <a href="?page=<?php echo $page > 1 ? $page - 1 : 1; ?>&category=<?php echo urlencode($category_filter); ?>&view_mode=<?php echo $view_mode; ?>"
                class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">«</a>
            <span class="current">Trang <?php echo $page; ?> / <?php echo $total_pages; ?></span>
            <a href="?page=<?php echo $page < $total_pages ? $page + 1 : $total_pages; ?>&category=<?php echo urlencode($category_filter); ?>&view_mode=<?php echo $view_mode; ?>"
                class="<?php echo $page >= $total_pages ? 'disabled' : ''; ?>">»</a>
        </div>

        <!-- Modal xác nhận xóa vật lý -->
        <?php if (isset($_SESSION['confirm_delete_book_id'])): ?>
            <?php
            $confirm_book_id = intval($_SESSION['confirm_delete_book_id']);
            $stmt = $conn->prepare("SELECT title FROM books WHERE id = ? AND is_deleted = 0");
            $stmt->bind_param("i", $confirm_book_id);
            $stmt->execute();
            $book = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            ?>
            <?php if ($book): ?>
                <div id="confirmDeleteModal" class="modal" style="display: block;">
                    <div class="modal-content">
                        <span class="close" onclick="closeConfirmDeleteModal()">×</span>
                        <h3>Xác nhận xóa sách</h3>
                        <p>Sách "<strong><?php echo htmlspecialchars($book['title']); ?></strong>" chưa được bán. Bạn có chắc chắn muốn xóa hoàn toàn sách này không?</p>
                        <form method="POST" action="">
                            <input type="hidden" name="book_id" value="<?php echo $confirm_book_id; ?>">
                            <input type="hidden" name="delete_book" value="1">
                            <input type="hidden" name="delete_type" value="hard">
                            <button style="margin-left: 30%;" type="submit" class="btn btn-delete">Xóa hoàn toàn</button>
                            <button type="button" class="btn btn-primary" onclick="closeConfirmDeleteModal()">Hủy</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <?php unset($_SESSION['confirm_delete_book_id']); ?>
        <?php endif; ?>
    </div>
    <script>
        setTimeout(function () {
            const successMsg = document.querySelector('.success');
            if (successMsg) successMsg.style.display = 'none';
            const errorMsg = document.querySelector('.error');
            if (errorMsg) errorMsg.style.display = 'none';
        }, 3000);

        function openAddBookModal() {
            document.getElementById('addBookModal').style.display = 'block';
        }

        function closeAddBookModal() {
            document.getElementById('addBookModal').style.display = 'none';
        }

        function closeConfirmDeleteModal() {
            document.getElementById('confirmDeleteModal').style.display = 'none';
        }

        function confirmDelete(button, bookTitle) {
            const form = button.closest('.delete-book-form');
            return confirm(`Bạn có chắc chắn muốn xóa sách "${bookTitle}"?.`);
        }

        window.onclick = function (event) {
            const addModal = document.getElementById('addBookModal');
            const confirmModal = document.getElementById('confirmDeleteModal');
            if (event.target == addModal) {
                addModal.style.display = 'none';
            }
            if (confirmModal && event.target == confirmModal) {
                confirmModal.style.display = 'none';
            }
        };

        function previewImage(event) {
            const preview = document.getElementById('image-preview');
            preview.src = URL.createObjectURL(event.target.files[0]);
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>