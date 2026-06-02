<?php
require_once __DIR__ . '/config.php';
$active_page = basename($_SERVER['PHP_SELF']);

$is_db_connected = false;
try {
    if (getDB()) {
        $is_db_connected = true;
    }
} catch (Exception $e) {
    $is_db_connected = false;
}
?>
<header class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-logo">
            <span>🚀</span> Bootcamp Hub
        </a>
        <nav class="nav-links">
            <a href="index.php" class="nav-link <?= $active_page == 'index.php' ? 'active' : '' ?>">Dashboard</a>
            <a href="ogrenciler.php" class="nav-link <?= $active_page == 'ogrenciler.php' ? 'active' : '' ?>">Öğrenciler</a>
            <a href="egitmenler.php" class="nav-link <?= $active_page == 'egitmenler.php' ? 'active' : '' ?>">Eğitmenler</a>
            <a href="bootcampler.php" class="nav-link <?= $active_page == 'bootcampler.php' ? 'active' : '' ?>">Bootcampler</a>
            <a href="dersler.php" class="nav-link <?= $active_page == 'dersler.php' ? 'active' : '' ?>">Dersler</a>
            <a href="yoklama.php" class="nav-link <?= $active_page == 'yoklama.php' ? 'active' : '' ?>">Yoklama</a>
            <a href="notlar.php" class="nav-link <?= $active_page == 'notlar.php' ? 'active' : '' ?>">Not Sistemi</a>
            <a href="raporlar.php" class="nav-link <?= $active_page == 'raporlar.php' ? 'active' : '' ?>">Raporlama</a>
            
            <?php if ($is_db_connected): ?>
                <span class="badge badge-success" style="box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);">☁️ DB: Connected</span>
            <?php else: ?>
                <span class="badge badge-danger" style="box-shadow: 0 0 10px rgba(239, 68, 68, 0.4);">☁️ DB: Offline</span>
            <?php endif; ?>
        </nav>
    </div>
</header>
