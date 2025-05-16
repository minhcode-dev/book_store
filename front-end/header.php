<!-- Navbar -->
<div class="navbar">
    <div class="logo">Min Book</div>
    <div class="search-bar">
        <form action="store.php" method="GET">
            <input type="text" name="keyword" placeholder="Tìm kiếm sách...">
            <button type="submit">🔍</button>
        </form>

    </div>
    <div class="actions">
    <?php if (isset($_SESSION['username'])): ?>
        <div class="dropdown" onclick="toggleDropdown()">
            <span class="dropdown-toggle">Xin chào, <?= htmlspecialchars($_SESSION['username']) ?></span>
            <div class="dropdown-content" id="userDropdown">
                <a href="profile.php">Thông tin cá nhân</a>
                <a href="order_history.php">Lịch sử đơn hàng</a>
                <a href="logout.php">Đăng xuất</a>
            </div>
        </div>
    <?php else: ?>
        <a href="login.php">Đăng nhập</a>
    <?php endif; ?>
    <a href="cart.php">🛒</a>
    </div>
</div>


</div>

<!-- Menu -->
<div class="nav-menu">
    <a href="homepage.php">Trang chủ</a>
    <a href="store.php">Cửa hàng</a>
    <a href="introduction.php">Giới thiệu</a>
    <a href="contact.php">Liên hệ</a>
</div>