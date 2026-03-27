<?php
// Admin Database Backup
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied');
}

$message = '';

// Handle backup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['backup_database'])) {
        try {
            // Create backup directory if it doesn't exist
            $backup_dir = 'backups/';
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }

            $timestamp = date('Y-m-d_H-i-s');
            $backup_file = $backup_dir . 'ncr_forecast_backup_' . $timestamp . '.sql';

            // Use mysqldump to create backup
            $command = "mysqldump -u root ncr_forecast > \"$backup_file\" 2>&1";
            $output = shell_exec($command);

            if (file_exists($backup_file) && filesize($backup_file) > 0) {
                $message = '<div class="success">✅ Database backup created successfully: ' . basename($backup_file) . '</div>';
                log_message('INFO', "Admin {$_SESSION['user_id']} created database backup: $backup_file");
            } else {
                $message = '<div class="error">❌ Backup failed: ' . $output . '</div>';
            }
        } catch (Exception $e) {
            $message = '<div class="error">❌ Error creating backup: ' . $e->getMessage() . '</div>';
        }
    } elseif (isset($_POST['backup_forecasts'])) {
        try {
            $backup_dir = 'backups/forecasts/';
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }

            $timestamp = date('Y-m-d_H-i-s');
            $zip_file = $backup_dir . 'forecast_data_' . $timestamp . '.zip';

            $files_to_backup = [];
            if (file_exists('forecast.csv')) $files_to_backup[] = 'forecast.csv';
            if (file_exists('forecast_report.pdf')) $files_to_backup[] = 'forecast_report.pdf';
            if (file_exists('NCR_Total_Enrollees.csv')) $files_to_backup[] = 'NCR_Total_Enrollees.csv';

            if (!empty($files_to_backup)) {
                // Create ZIP file
                $zip = new ZipArchive();
                if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
                    foreach ($files_to_backup as $file) {
                        $zip->addFile($file, basename($file));
                    }
                    $zip->close();
                    $message = '<div class="success">✅ Forecast data backup created: ' . basename($zip_file) . '</div>';
                    log_message('INFO', "Admin {$_SESSION['user_id']} created forecast data backup: $zip_file");
                } else {
                    $message = '<div class="error">❌ Failed to create ZIP archive</div>';
                }
            } else {
                $message = '<div class="warning">⚠️ No forecast data files found to backup</div>';
            }
        } catch (Exception $e) {
            $message = '<div class="error">❌ Error creating forecast backup: ' . $e->getMessage() . '</div>';
        }
    } elseif (isset($_GET['download'])) {
        $file = basename($_GET['download']);
        $filepath = 'backups/' . $file;

        if (file_exists($filepath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            $message = '<div class="error">❌ File not found</div>';
        }
    }
}

// Get backup files
$backup_files = [];
$forecast_backups = [];

if (is_dir('backups/')) {
    $files = scandir('backups/');
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && !is_dir('backups/' . $file)) {
            $backup_files[] = $file;
        }
    }
}

if (is_dir('backups/forecasts/')) {
    $files = scandir('backups/forecasts/');
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && !is_dir('backups/forecasts/' . $file)) {
            $forecast_backups[] = $file;
        }
    }
}
?>

<div class="admin-section">
    <h2>💾 Database & Data Backup</h2>
    <?php echo $message; ?>

    <div class="backup-actions">
        <div class="card">
            <h3>Create New Backup</h3>
            <div class="backup-buttons">
                <form method="POST" style="display:inline;">
                    <button type="submit" name="backup_database" class="btn-primary">📊 Backup Database</button>
                </form>
                <form method="POST" style="display:inline;">
                    <button type="submit" name="backup_forecasts" class="btn-secondary">📈 Backup Forecast Data</button>
                </form>
            </div>
        </div>
    </div>

    <div class="backup-files">
        <!-- Database Backups -->
        <div class="card">
            <h3>Database Backups</h3>
            <?php if (empty($backup_files)): ?>
                <p class="no-backups">No database backups found.</p>
            <?php else: ?>
                <div class="file-list">
                    <?php foreach (array_reverse($backup_files) as $file): ?>
                        <div class="file-item">
                            <span class="file-name"><?php echo htmlspecialchars($file); ?></span>
                            <span class="file-size"><?php echo number_format(filesize('backups/' . $file)); ?> bytes</span>
                            <a href="admin.php?section=backup&download=<?php echo urlencode($file); ?>" class="btn-small btn-secondary">Download</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Forecast Data Backups -->
        <div class="card">
            <h3>Forecast Data Backups</h3>
            <?php if (empty($forecast_backups)): ?>
                <p class="no-backups">No forecast data backups found.</p>
            <?php else: ?>
                <div class="file-list">
                    <?php foreach (array_reverse($forecast_backups) as $file): ?>
                        <div class="file-item">
                            <span class="file-name"><?php echo htmlspecialchars($file); ?></span>
                            <span class="file-size"><?php echo number_format(filesize('backups/forecasts/' . $file)); ?> bytes</span>
                            <a href="admin.php?section=backup&download=<?php echo urlencode($file); ?>" class="btn-small btn-secondary">Download</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.backup-actions {
    margin-bottom: 30px;
}

.backup-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.backup-files {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.file-list {
    max-height: 300px;
    overflow-y: auto;
}

.file-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    margin-bottom: 8px;
    background: #f8f9fa;
}

.file-name {
    flex: 1;
    font-weight: 500;
    margin-right: 10px;
}

.file-size {
    color: #6c757d;
    font-size: 0.9em;
    margin-right: 15px;
}

.no-backups {
    text-align: center;
    color: #6c757d;
    font-style: italic;
    margin: 20px 0;
}

.btn-small {
    padding: 6px 12px;
    font-size: 0.9em;
}

@media (max-width: 768px) {
    .backup-files {
        grid-template-columns: 1fr;
    }

    .backup-buttons {
        flex-direction: column;
    }

    .file-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
}
</style>