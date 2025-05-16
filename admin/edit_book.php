<?php
// Bật hiển thị lỗi để debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../back-end/db_connect.php';

// Kiểm tra admin đăng nhập
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Kiểm tra ID sách hợp lệ
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_books.php");
    exit();
}

$book_id = intval($_GET['id']);

// Lấy thông tin sách
$stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
if (!$stmt) {
    die("Lỗi chuẩn bị truy vấn: " . $conn->error);
}
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
    header("Location: manage_books.php");
    exit();
}

// Danh mục cố định
$categories = ['Tiểu Thuyết', 'Khoa Học', 'Lịch Sử', 'Tâm Lý', 'Văn Học'];

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $category = in_array($_POST['category'], $categories) ? $_POST['category'] : '';
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);
    $image_url = $book['image_url'];

    // Kiểm tra danh mục hợp lệ
    if (!$category) {
        $error = "Vui lòng chọn danh mục hợp lệ.";
    }

    // Xử lý upload hình ảnh
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

    if (!$error) {
        $stmt = $conn->prepare("UPDATE books SET title = ?, author = ?, category = ?, price = ?, description = ?, image_url = ? WHERE id = ?");
        if (!$stmt) {
            $error = "Lỗi chuẩn bị truy vấn: " . $conn->error;
        } else {
            $stmt->bind_param("sssdssi", $title, $author, $category, $price, $description, $image_url, $book_id);
            if ($stmt->execute()) {
                $success = "Cập nhật sách thành công.";
                // Cập nhật dữ liệu sách để hiển thị
                $book = [
                    'title' => $title,
                    'author' => $author,
                    'category' => $category,
                    'price' => $price,
                    'description' => $description,
                    'image_url' => $image_url
                ];
                header("Location: manage_books.php");
            } else {
                $error = "Lỗi khi cập nhật sách: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa sách</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        /* CSS dự phòng */
        .success { color: green; margin-bottom: 10px; }
        .error { color: red; margin-bottom: 10px; }
        .admin-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;
        }
        .form-group textarea { height: 100px; }
        .btn { 
            padding: 8px 16px; 
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 14px; 
            min-width: 100px; 
        }
        .btn:hover { background: #0056b3; }
        #image-preview { max-width: 200px; margin-top: 10px; }
    </style>
</head>
<body>
    <?php require 'header.php' ?>
    <div class="admin-container">
        <h2>Sửa sách</h2>
        <?php if ($success): ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Tiêu đề:</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($book['title']); ?>" required>
            </div>
            <div class="form-group">
                <label for="author">Tác giả:</label>
                <input type="text" id="author" name="author" value="<?php echo htmlspecialchars($book['author']); ?>" required>
            </div>
            <div class="form-group">
                <label for="category">Danh mục:</label>
                <select id="category" name="category" required>
                    <option value="">Chọn danh mục</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $book['category'] == $category ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="price">Giá:</label>
                <input type="number" id="price" name="price" step="0.01" value="<?php echo htmlspecialchars($book['price']); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Mô tả:</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($book['description'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="image">Hình ảnh:</label>
                <input type="file" id="image" name="image" accept="image/*" onchange="previewImage(event)">
                <img id="image-preview" src="<?php echo htmlspecialchars($book['image_url'] ?? '../Uploads/default.jpg'); ?>" style="max-width:200px;" alt="Hình ảnh sách">
            </div>
            <button type="submit" class="btn">Cập nhật</button>
        </form>
    </div>

    <script>
        function previewImage(event) {
            const preview = document.getElementById('image-preview');
            preview.src = URL.createObjectURL(event.target.files[0]);
        }
    </script>
</body>
</html>