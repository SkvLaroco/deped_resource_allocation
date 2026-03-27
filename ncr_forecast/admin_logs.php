<?php
// Admin System Logs
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied');
}

$message = '';
$logs = [];

// Handle log clearing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    try {
        $log_files = ['logs/app.log', 'logs/php_errors.log'];
        foreach ($log_files as $log_file) {
            if (file_exists($log_file)) {
                file_put_contents($log_file, '');
            }
        }
        $message = '<div class="success">✅ Logs cleared successfully</div>';
        log_message('INFO', "Admin {$_SESSION['user_id']} cleared all system logs");
    } catch (Exception $e) {
        $message = '<div class="error">❌ Error clearing logs: ' . $e->getMessage() . '</div>';
    }
}

// Read logs
function readLogFile($filename, $lines = 100) {
    if (!file_exists($filename)) {
        return ["Log file does not exist: $filename"];
    }

    $file = new SplFileObject($filename, 'r');
    $file->seek(PHP_INT_MAX);
    $total_lines = $file->key();

    $start_line = max(0, $total_lines - $lines);
    $file->seek($start_line);

    $logs = [];
    while (!$file->eof()) {
        $line = trim($file->fgets());
        if (!empty($line)) {
            $logs[] = $line;
        }
    }

    return array_reverse($logs); // Most recent first
}

$app_logs = readLogFile('logs/app.log');
$error_logs = readLogFile('logs/php_errors.log');
?>

<div class="admin-section">
    <h2>📋 System Logs</h2>
    <?php echo $message; ?>

    <div class="log-controls">
        <form method="POST" style="display:inline;">
            <button type="submit" name="clear_logs" class="btn-danger" onclick="return confirm('Are you sure you want to clear all logs?')">Clear All Logs</button>
        </form>
        <button onclick="refreshLogs()" class="btn-secondary">Refresh Logs</button>
    </div>

    <div class="logs-container">
        <!-- Application Logs -->
        <div class="card">
            <h3>📝 Application Logs</h3>
            <div class="log-content" id="appLogs">
                <?php if (empty($app_logs)): ?>
                    <p class="no-logs">No application logs found.</p>
                <?php else: ?>
                    <?php foreach ($app_logs as $log): ?>
                        <div class="log-entry <?php echo strpos($log, 'ERROR') !== false ? 'log-error' : (strpos($log, 'WARNING') !== false ? 'log-warning' : 'log-info'); ?>">
                            <pre><?php echo htmlspecialchars($log); ?></pre>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Error Logs -->
        <div class="card">
            <h3>❌ Error Logs</h3>
            <div class="log-content" id="errorLogs">
                <?php if (empty($error_logs)): ?>
                    <p class="no-logs">No error logs found.</p>
                <?php else: ?>
                    <?php foreach ($error_logs as $log): ?>
                        <div class="log-entry log-error">
                            <pre><?php echo htmlspecialchars($log); ?></pre>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.log-controls {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
}

.logs-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.log-content {
    max-height: 500px;
    overflow-y: auto;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 10px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.4;
}

.log-entry {
    margin-bottom: 8px;
    padding: 8px;
    border-radius: 4px;
    border-left: 3px solid;
}

.log-info {
    background: #e8f5e8;
    border-left-color: #28a745;
}

.log-warning {
    background: #fff3cd;
    border-left-color: #ffc107;
}

.log-error {
    background: #f8d7da;
    border-left-color: #dc3545;
}

.no-logs {
    text-align: center;
    color: #6c757d;
    font-style: italic;
    margin: 20px 0;
}

@media (max-width: 768px) {
    .logs-container {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function refreshLogs() {
    // Simple page refresh for now
    location.reload();
}
</script>