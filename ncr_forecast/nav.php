<?php
// nav.php — shared navigation bar
// Set $current_page = 'ncr' or 'school' before including
$current_page = $current_page ?? 'ncr';
?>
<div class="dashboard-header">
    <div class="header-left">
        <span class="wordmark">NCR SHS <span>Forecast</span></span>
        <span class="user-info">
            <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></strong>
            <span class="role-badge-header"><?php echo ucfirst(htmlspecialchars($_SESSION['role'])); ?></span>
        </span>
    </div>

    <nav class="main-nav">
        <a href="index.php"
           class="nav-link <?php echo $current_page === 'ncr' ? 'nav-active' : ''; ?>">
            🌐&nbsp; NCR Overview
        </a>
        <a href="school_forecast.php"
           class="nav-link <?php echo $current_page === 'school' ? 'nav-active' : ''; ?>">
            🏫&nbsp; Per-School Forecast
        </a>
        <a href="methodology.php"
           class="nav-link <?php echo $current_page === 'methodology' ? 'nav-active' : ''; ?>">
            📐&nbsp; Methodology
        </a>
    </nav>

    <div class="header-right">
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="admin.php" class="admin-link">⚙ Admin</a>
        <?php endif; ?>
        <a href="index.php?logout=1" class="logout-link">Sign Out</a>
    </div>
</div>
