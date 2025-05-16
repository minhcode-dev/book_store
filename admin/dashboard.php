<?php
session_start();
require '../back-end/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Kiểm tra thời gian để đặt lại đơn đã giao và doanh thu
$reset_file = 'last_reset.txt'; // File lưu thời gian đặt lại
$reset_interval = 24 * 60 * 60; // 24 giờ (tính bằng giây)

if (file_exists($reset_file)) {
    $last_reset = file_get_contents($reset_file);
    $current_time = time();
    
    // Nếu đã qua 24 giờ, đặt lại cả đơn đã giao và doanh thu
    if (($current_time - $last_reset) > $reset_interval) {
        $completed_orders = 0;
        $completed_revenue = 0;
        file_put_contents($reset_file, $current_time); // Cập nhật thời gian đặt lại
    } else {
        // Lấy dữ liệu từ database, chỉ tính trong ngày hiện tại
        $completed_orders = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE status = 'delivered' AND DATE(created_at) = CURDATE()")->fetch_assoc()['total'];
        $completed_revenue = $conn->query("SELECT SUM(total_amount) AS revenue FROM orders WHERE status = 'delivered' AND DATE(created_at) = CURDATE()")->fetch_assoc()['revenue'] ?? 0;
    }
} else {
    // Nếu file không tồn tại, tạo mới và lấy dữ liệu
    $completed_orders = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE status = 'delivered' AND DATE(created_at) = CURDATE()")->fetch_assoc()['total'];
    $completed_revenue = $conn->query("SELECT SUM(total_amount) AS revenue FROM orders WHERE status = 'delivered' AND DATE(created_at) = CURDATE()")->fetch_assoc()['revenue'] ?? 0;
    file_put_contents($reset_file, time());
}

// Lấy số đơn chờ xác nhận (không đặt lại)
$pending_orders = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE status = 'Chờ xác nhận'")->fetch_assoc()['total'];

// Xác định màu sắc dựa trên số lượng đơn
$pending_color = $pending_orders > 0 ? '#ffeb3b' : '#ffc107'; // Vàng sáng nếu có đơn chờ
$completed_color = $completed_orders > 0 ? '#00e676' : '#28a745'; // Xanh sáng hơn nếu có đơn đã giao
$revenue_color = '#007bff'; // Màu doanh thu giữ nguyên
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f6f8;
            height: 100vh;
        }

        .navbar {
            background-color: #343a40;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #fff;
        }

        .navbar h1 {
            font-size: 24px;
        }

        .navbar ul {
            list-style: none;
            display: flex;
            gap: 20px;
        }

        .navbar ul li a {
            text-decoration: none;
            color: #fff;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .navbar ul li a:hover {
            background-color: #495057;
        }

        .dashboard-content {
            padding: 40px;
        }

        .stats {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-top: 30px;
        }

        .card {
            flex: 1;
            min-width: 260px;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: 0.3s;
        }

        .card:hover {
            transform: translateY(-4px);
        }

        .card h3 {
            margin-bottom: 10px;
            color: #333;
            font-size: 20px;
        }

        .card p {
            font-size: 28px;
            font-weight: bold;
            color: #007bff;
        }

        @media (max-width: 768px) {
            .stats {
                flex-direction: column;
            }
        }
    </style>

    <!-- Thêm Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php require 'header.php' ?>

    <div class="dashboard-content">
        <h2>Thống kê tổng quan</h2>
        <div class="stats">
            <div class="card">
                <h3>Đơn chờ xác nhận</h3>
                <p><?= $pending_orders ?></p>
            </div>
            <div class="card" style="color: #28a745;">
                <h3>Đơn đã giao</h3>
                <p><?= $completed_orders ?></p>
            </div>
            <div class="card" style="color: #ffc107;">
                <h3>Doanh thu hôm nay</h3>
                <p><?= number_format($completed_revenue, 0, ',', '.') ?> đ</p>
            </div>
        </div>
        <div style="margin-top:50px">
            <!-- Biểu đồ -->
            <h2>📈 Biểu đồ thống kê</h2>
            <div style="max-width: 800px; margin: 0 auto;">
                <canvas id="orderChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Dữ liệu cho biểu đồ
        var ctx = document.getElementById('orderChart').getContext('2d');
        var orderChart = new Chart(ctx, {
            type: 'bar', // Loại biểu đồ
            data: {
                labels: ['Đơn chờ xác nhận', 'Đơn đã giao', 'Doanh thu'],
                datasets: [{
                    label: 'Số lượng / Doanh thu',
                    data: [<?= $pending_orders ?>, <?= $completed_orders ?>, <?= $completed_revenue ?>],
                    backgroundColor: ['<?= $pending_color ?>', '<?= $completed_color ?>', '<?= $revenue_color ?>'],
                    borderColor: ['#e0a800', '#218838', '#0069d9'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutBounce',
                    animateScale: true,
                    animateRotate: true
                }
            }
        });

        // Hàm cập nhật biểu đồ (nếu cần cập nhật động)
        function updateChart(pending, completed, revenue) {
            orderChart.data.datasets[0].data = [pending, completed, revenue];
            orderChart.data.datasets[0].backgroundColor = [
                pending > 0 ? '#ffeb3b' : '#ffc107',
                completed > 0 ? '#00e676' : '#28a745',
                '<?= $revenue_color ?>'
            ];
            orderChart.update();
        }
    </script>
</body>
</html>