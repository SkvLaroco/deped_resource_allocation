<?php
session_start();
include 'db.php';

// ── Auth: admin only ───────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php'); exit;
}

// Session timeout
$timeout_duration = 1800;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset(); session_destroy();
    header('Location: index.php'); exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// ── Helpers ────────────────────────────────────────────────────
if (!function_exists('log_message')) {
    function log_message($level, $message) {
        if (!is_dir('logs')) mkdir('logs', 0755, true);
        $entry = "[" . date('Y-m-d H:i:s') . "] [{$level}] {$message}\n";
        error_log($entry, 3, 'logs/app.log');
    }
}

function getUserCount() {
    global $pdo;
    try { return $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); }
    catch (Exception $e) { return 0; }
}

function getActiveSessionCount() { return 1; }

function getTodayForecastCount() {
    $log_file = 'logs/app.log';
    if (!file_exists($log_file)) return 0;
    $today = date('Y-m-d'); $count = 0;
    $handle = fopen($log_file, 'r');
    if (!$handle) return 0;
    while (($line = fgets($handle)) !== false) {
        if (strpos($line, $today) !== false && strpos($line, 'Forecast generated successfully') !== false) $count++;
    }
    fclose($handle);
    return $count;
}

$admin_action = $_GET['section'] ?? 'dashboard';

// Handle backup file download
if (isset($_GET['download'])) {
    $file     = basename($_GET['download']);
    $filepath = realpath('backups/' . $file);
    $base     = realpath('backups/');
    if ($filepath && $base && strpos($filepath, $base) === 0 && file_exists($filepath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel — NCR SHS Forecast</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-layout { display: flex; min-height: calc(100vh - 68px); position: relative; z-index: 1; }

        /* Sidebar */
        .admin-sidebar {
            width: 240px;
            flex-shrink: 0;
            background: linear-gradient(180deg, var(--navy-deep) 0%, var(--navy) 100%);
            padding: 28px 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
            box-shadow: 4px 0 20px rgba(0,0,0,0.25);
            position: sticky;
            top: 68px;
            height: calc(100vh - 68px);
            overflow-y: auto;
        }

        .sidebar-section-label {
            font-size: 0.68em;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.30);
            font-weight: 700;
            padding: 14px 22px 6px;
            font-family: var(--font-body);
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 22px;
            color: rgba(255,255,255,0.65);
            text-decoration: none;
            font-size: 0.92em;
            font-weight: 500;
            transition: all 0.18s;
            border-left: 3px solid transparent;
            font-family: var(--font-body);
        }

        .sidebar-link:hover {
            color: var(--white);
            background: rgba(255,255,255,0.07);
        }

        .sidebar-link.active {
            color: var(--gold);
            background: rgba(212,168,67,0.10);
            border-left-color: var(--gold);
            font-weight: 700;
        }

        .sidebar-back {
            margin-top: auto;
            padding: 16px 22px 0;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        .sidebar-back a {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.45);
            text-decoration: none;
            font-size: 0.85em;
            font-weight: 500;
            transition: color 0.18s;
        }

        .sidebar-back a:hover { color: rgba(255,255,255,0.80); }

        /* Main content */
        .admin-main {
            flex: 1;
            background: var(--surface);
            padding: 32px 36px;
            min-width: 0;
        }

        /* Dashboard welcome cards */
        .admin-dash-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 32px;
        }

        .admin-dash-card {
            background: linear-gradient(145deg, rgba(255,255,255,0.98) 0%, rgba(240,246,255,0.96) 100%);
            border-radius: var(--radius-lg);
            padding: 24px 26px;
            box-shadow: var(--card-3d);
            border: 1px solid rgba(255,255,255,0.88);
            display: flex;
            flex-direction: column;
            gap: 6px;
            transition: transform 0.22s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.22s ease;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .admin-dash-card:hover {
            transform: translateY(-5px) scale(1.01);
            box-shadow: var(--card-3d-hover);
        }

        .admin-dash-card::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 5px;
        }

        .adc-navy::before  { background: linear-gradient(180deg, var(--navy), var(--navy-mid)); }
        .adc-green::before { background: linear-gradient(180deg, var(--green), #22C55E); }
        .adc-gold::before  { background: linear-gradient(180deg, var(--gold), #F59E0B); }

        .adc-icon { font-size: 1.6em; }
        .adc-label { font-size: 0.75em; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); font-weight: 700; }
        .adc-value { font-family: var(--font-display); font-size: 2.2em; font-weight: 800; color: var(--text-primary); line-height: 1; }

        .admin-section-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 8px;
        }

        .section-nav-card {
            background: linear-gradient(145deg, rgba(255,255,255,0.98) 0%, rgba(240,246,255,0.96) 100%);
            border-radius: var(--radius-lg);
            padding: 22px 24px;
            box-shadow: var(--card-3d);
            border: 1px solid rgba(255,255,255,0.88);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: transform 0.22s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.22s ease;
            border-top: 4px solid var(--navy);
        }

        .section-nav-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-3d-hover);
        }

        .snc-icon { font-size: 1.8em; }
        .snc-title { font-family: var(--font-display); font-size: 1.0em; font-weight: 700; color: var(--text-primary); }
        .snc-desc  { font-size: 0.84em; color: var(--text-muted); }

        .page-section-title {
            font-family: var(--font-display);
            font-size: 1.4em;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .page-section-sub {
            font-size: 0.90em;
            color: var(--text-muted);
            margin-bottom: 26px;
            font-weight: 500;
        }

        .content-card {
            background: linear-gradient(145deg, rgba(255,255,255,0.98) 0%, rgba(242,247,255,0.97) 100%);
            border-radius: var(--radius-lg);
            padding: 28px 32px;
            box-shadow: var(--card-3d);
            border: 1px solid rgba(255,255,255,0.88);
        }

        @media (max-width: 768px) {
            .admin-layout { flex-direction: column; }
            .admin-sidebar { width: 100%; height: auto; position: static; flex-direction: row; flex-wrap: wrap; padding: 12px; gap: 4px; }
            .sidebar-section-label { display: none; }
            .admin-main { padding: 20px; }
        }
    </style>
</head>
<body>

<?php $current_page = 'admin'; include 'nav.php'; ?>

<div class="admin-layout">

    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-section-label">Management</div>
        <a href="admin.php?section=dashboard"
           class="sidebar-link <?php echo $admin_action === 'dashboard' ? 'active' : ''; ?>">
            🏠&nbsp; Dashboard
        </a>
        <a href="admin.php?section=users"
           class="sidebar-link <?php echo $admin_action === 'users' ? 'active' : ''; ?>">
            👥&nbsp; User Management
        </a>
        <div class="sidebar-section-label">System</div>
        <a href="admin.php?section=logs"
           class="sidebar-link <?php echo $admin_action === 'logs' ? 'active' : ''; ?>">
            📋&nbsp; System Logs
        </a>
        <a href="admin.php?section=backup"
           class="sidebar-link <?php echo $admin_action === 'backup' ? 'active' : ''; ?>">
            💾&nbsp; Backup
        </a>
        <a href="admin.php?section=settings"
           class="sidebar-link <?php echo $admin_action === 'settings' ? 'active' : ''; ?>">
            ⚙️&nbsp; Settings
        </a>

        <div class="sidebar-back">
            <a href="index.php">← Back to Dashboard</a>
        </div>
    </aside>

    <!-- Main content -->
    <main class="admin-main">

        <?php if ($admin_action === 'dashboard'): ?>
        <div class="page-section-title">Admin Dashboard</div>
        <div class="page-section-sub">System overview and quick navigation</div>

        <div class="admin-dash-grid">
            <div class="admin-dash-card adc-navy">
                <div class="adc-icon">👥</div>
                <div class="adc-label">Total Users</div>
                <div class="adc-value"><?php echo getUserCount(); ?></div>
            </div>
            <div class="admin-dash-card adc-green">
                <div class="adc-icon">📊</div>
                <div class="adc-label">Forecasts Today</div>
                <div class="adc-value"><?php echo getTodayForecastCount(); ?></div>
            </div>
            <div class="admin-dash-card adc-gold">
                <div class="adc-icon">🖥️</div>
                <div class="adc-label">PHP Version</div>
                <div class="adc-value" style="font-size:1.4em;"><?php echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION; ?></div>
            </div>
        </div>

        <div style="font-family:var(--font-display); font-size:1.0em; font-weight:700; color:var(--text-primary); margin-bottom:14px;">Quick Navigation</div>
        <div class="admin-section-cards">
            <a href="admin.php?section=users" class="section-nav-card">
                <div class="snc-icon">👥</div>
                <div class="snc-title">User Management</div>
                <div class="snc-desc">Add, remove, or reset passwords for system users</div>
            </a>
            <a href="admin.php?section=logs" class="section-nav-card" style="border-top-color:var(--green);">
                <div class="snc-icon">📋</div>
                <div class="snc-title">System Logs</div>
                <div class="snc-desc">View application and error logs</div>
            </a>
            <a href="admin.php?section=backup" class="section-nav-card" style="border-top-color:var(--gold);">
                <div class="snc-icon">💾</div>
                <div class="snc-title">Backup</div>
                <div class="snc-desc">Create and download database and forecast backups</div>
            </a>
            <a href="admin.php?section=settings" class="section-nav-card" style="border-top-color:var(--purple);">
                <div class="snc-icon">⚙️</div>
                <div class="snc-title">Settings</div>
                <div class="snc-desc">Configure forecast parameters and system settings</div>
            </a>
        </div>

        <?php elseif ($admin_action === 'users'): ?>
        <div class="page-section-title">User Management</div>
        <div class="page-section-sub">Manage system user accounts and access</div>
        <div class="content-card">
            <?php include 'admin_users.php'; ?>
        </div>

        <?php elseif ($admin_action === 'logs'): ?>
        <div class="page-section-title">System Logs</div>
        <div class="page-section-sub">Application and error log viewer</div>
        <div class="content-card">
            <?php include 'admin_logs.php'; ?>
        </div>

        <?php elseif ($admin_action === 'backup'): ?>
        <div class="page-section-title">Backup</div>
        <div class="page-section-sub">Database and forecast data backups</div>
        <div class="content-card">
            <?php include 'admin_backup.php'; ?>
        </div>

        <?php elseif ($admin_action === 'settings'): ?>
        <div class="page-section-title">System Settings</div>
        <div class="page-section-sub">Forecast parameters, session settings, and system configuration</div>
        <div class="content-card">
            <?php include 'admin_settings.php'; ?>
        </div>

        <?php endif; ?>

    </main>
</div>

<?php $bg_mode = 'dashboard'; include 'bg_canvas.php'; ?>
</body>
</html>