<?php
require '../back-end/db_connect.php';

// Nhận các giá trị từ URL
$category_filter = $_GET['category'] ?? '';
$price_range = $_GET['price_range'] ?? '';
$keyword_filter = $_GET['keyword'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$books_per_page = 8;
$offset = ($page - 1) * $books_per_page;

// Tạo câu truy vấn và danh sách tham số
$conditions = ['is_deleted = ?'];
$params = [0]; // is_deleted = 0
$param_types = 'i'; // Kiểu int cho is_deleted

if ($category_filter) {
    $conditions[] = "category LIKE ?";
    $params[] = '%' . $category_filter . '%';
    $param_types .= 's';
}

if ($keyword_filter) {
    $conditions[] = "(title LIKE ? OR author LIKE ? OR description LIKE ?)";
    $keyword_like = '%' . $keyword_filter . '%';
    $params[] = $keyword_like;
    $params[] = $keyword_like;
    $params[] = $keyword_like;
    $param_types .= 'sss';
}

if ($price_range) {
    if (strpos($price_range, '+') !== false) {
        $min_price = (int)str_replace('+', '', $price_range);
        $conditions[] = "price >= ?";
        $params[] = $min_price;
        $param_types .= 'i';
    } else {
        list($min_price, $max_price) = explode('-', $price_range);
        $conditions[] = "price BETWEEN ? AND ?";
        $params[] = (int)$min_price;
        $params[] = (int)$max_price;
        $param_types .= 'ii';
    }
}

$where_clause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Truy vấn dữ liệu sách
$sql = "SELECT * FROM books $where_clause LIMIT ? OFFSET ?";
$param_types .= 'ii'; // Thêm kiểu cho LIMIT và OFFSET
$params[] = $books_per_page;
$params[] = $offset;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Lỗi chuẩn bị truy vấn: " . $conn->error);
}

// Liên kết tham số
$bind_params = [];
$bind_params[] = &$param_types;
foreach ($params as $index => $param) {
    $bind_params[$index + 1] = &$params[$index];
}
call_user_func_array([$stmt, 'bind_param'], $bind_params);

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Lấy tổng số sách để phân trang
$total_books_sql = "SELECT COUNT(*) AS total FROM books $where_clause";
$stmt = $conn->prepare($total_books_sql);
if (!$stmt) {
    die("Lỗi chuẩn bị truy vấn tổng số sách: " . $conn->error);
}

// Liên kết tham số cho truy vấn đếm
if ($params) {
    $count_params = array_slice($params, 0, count($params) - 2); // Loại bỏ LIMIT và OFFSET
    $count_param_types = substr($param_types, 0, -2); // Loại bỏ kiểu của LIMIT và OFFSET
    if ($count_params) {
        $bind_params = [];
        $bind_params[] = &$count_param_types;
        foreach ($count_params as $index => $param) {
            $bind_params[$index + 1] = &$count_params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
    }
}

$stmt->execute();
$total_books = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$total_pages = ceil($total_books / $books_per_page);
?>