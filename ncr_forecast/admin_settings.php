<?php
// Admin Settings
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied');
}

$message = '';

// Load current settings
$settings_file = 'config/settings.json';
$settings = [];

if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true) ?? [];
}

// Apply debug_mode setting immediately after loading
if (!empty($settings['debug_mode'])) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
}

// Load forecast parameters from config.json
$forecast_config_file = 'config.json';
$forecast_config_defaults = [
    'class_size'                    => 40,
    'academic_ratio'                => 0.65,
    'tvl_ratio'                     => 0.35,
    'forecast_years'                => 3,
    'confidence_level'              => 0.95,
    'sections_per_teacher'          => 1.5
];
$forecast_config = file_exists($forecast_config_file)
    ? array_merge($forecast_config_defaults, json_decode(file_get_contents($forecast_config_file), true) ?? [])
    : $forecast_config_defaults;

// Default settings
$default_settings = [
    'max_upload_size' => 5, // MB
    'session_timeout' => 30, // minutes
    'forecast_periods' => 3, // years
    'auto_backup' => false,
    'email_notifications' => false,
    'maintenance_mode' => false,
    'debug_mode' => false
];

$settings = array_merge($default_settings, $settings);

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $new_settings = [
        'max_upload_size' => (int)$_POST['max_upload_size'],
        'session_timeout' => (int)$_POST['session_timeout'],
        'forecast_periods' => (int)$_POST['forecast_periods'],
        'auto_backup' => isset($_POST['auto_backup']),
        'email_notifications' => isset($_POST['email_notifications']),
        'maintenance_mode' => isset($_POST['maintenance_mode']),
        'debug_mode' => isset($_POST['debug_mode'])
    ];

    try {
        // Create config directory if it doesn't exist
        if (!is_dir('config')) {
            mkdir('config', 0755, true);
        }

        file_put_contents($settings_file, json_encode($new_settings, JSON_PRETTY_PRINT));
        $settings = $new_settings;

        // Save forecast parameters to config.json
        $academic_ratio = round((float)$_POST['academic_ratio'] / 100, 4);
        $tvl_ratio       = round(1 - $academic_ratio, 4);
        $new_forecast_config = [
            'class_size'                    => max(1, (int)$_POST['class_size']),
            'academic_ratio'                => $academic_ratio,
            'tvl_ratio'                     => $tvl_ratio,
            'forecast_years'                => max(1, min(10, (int)$_POST['forecast_years_config'])),
            'confidence_level'              => 0.95,
            'sections_per_teacher'          => max(0.1, (float)$_POST['sections_per_teacher'])
        ];
        file_put_contents($forecast_config_file, json_encode($new_forecast_config, JSON_PRETTY_PRINT));
        $forecast_config = $new_forecast_config;

        $message = '<div class="success">✅ Settings updated successfully</div>';
        log_message('INFO', "Admin {$_SESSION['user_id']} updated system settings");
    } catch (Exception $e) {
        $message = '<div class="error">❌ Error saving settings: ' . $e->getMessage() . '</div>';
    }
}

// Get system information
$system_info = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'database_version' => 'MySQL',
    'upload_max_size' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'disk_free_space' => number_format(disk_free_space('.') / 1024 / 1024 / 1024, 2) . ' GB'
];
?>

<div class="admin-section">
    <h2>⚙️ System Settings</h2>
    <?php echo $message; ?>

    <div class="settings-container">
        <!-- System Settings -->
        <div class="card">
            <h3>Application Settings</h3>
            <form method="POST" class="settings-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Max Upload Size (MB):</label>
                        <input type="number" name="max_upload_size" value="<?php echo $settings['max_upload_size']; ?>" min="1" max="100" required>
                    </div>
                    <div class="form-group">
                        <label>Session Timeout (minutes):</label>
                        <input type="number" name="session_timeout" value="<?php echo $settings['session_timeout']; ?>" min="5" max="480" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Forecast Periods (years):</label>
                        <input type="number" name="forecast_periods" value="<?php echo $settings['forecast_periods']; ?>" min="1" max="10" required>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="checkbox-group">
                            <input type="checkbox" name="auto_backup" id="auto_backup" <?php echo $settings['auto_backup'] ? 'checked' : ''; ?>>
                            <label for="auto_backup">Enable Auto Backup</label>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="email_notifications" id="email_notifications" <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                            <label for="email_notifications">Enable Email Notifications</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="maintenance_mode" id="maintenance_mode" <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                            <label for="maintenance_mode">Maintenance Mode</label>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="debug_mode" id="debug_mode" <?php echo $settings['debug_mode'] ? 'checked' : ''; ?>>
                            <label for="debug_mode">Debug Mode</label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update_settings" class="btn-primary">Save Settings</button>
                </div>
            </form>
        </div>

        <!-- Forecast Parameters -->
        <div class="card" style="grid-column: 1 / -1;">
            <h3>📐 Forecast Parameters</h3>
            <p style="color:#666; font-size:0.9em; margin-top:0;">These values control how classrooms and teachers are calculated from projected enrollees.</p>
            <form method="POST" class="settings-form">
                <input type="hidden" name="update_settings" value="1">
                <input type="hidden" name="max_upload_size" value="<?php echo $settings['max_upload_size']; ?>">
                <input type="hidden" name="session_timeout" value="<?php echo $settings['session_timeout']; ?>">
                <input type="hidden" name="forecast_periods" value="<?php echo $settings['forecast_periods']; ?>">
                <?php if($settings['auto_backup']): ?><input type="hidden" name="auto_backup" value="1"><?php endif; ?>
                <?php if($settings['email_notifications']): ?><input type="hidden" name="email_notifications" value="1"><?php endif; ?>
                <?php if($settings['maintenance_mode']): ?><input type="hidden" name="maintenance_mode" value="1"><?php endif; ?>
                <?php if($settings['debug_mode']): ?><input type="hidden" name="debug_mode" value="1"><?php endif; ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Class Size (students per room):</label>
                        <input type="number" name="class_size" value="<?php echo (int)$forecast_config['class_size']; ?>" min="1" max="200" required>
                        <small style="color:#888;">Currently: <?php echo (int)$forecast_config['class_size']; ?> students/room</small>
                    </div>
                    <div class="form-group">
                        <label>Academic Track Ratio (%):</label>
                        <input type="number" name="academic_ratio" value="<?php echo round($forecast_config['academic_ratio'] * 100, 1); ?>" min="1" max="99" step="0.1" required>
                        <small style="color:#888;">TVL ratio auto-calculates as the remainder. Currently: <?php echo round($forecast_config['academic_ratio']*100,1); ?>% Academic / <?php echo round($forecast_config['tvl_ratio']*100,1); ?>% TVL</small>
                    </div>
                    <div class="form-group">
                        <label>Forecast Years:</label>
                        <input type="number" name="forecast_years_config" value="<?php echo (int)$forecast_config['forecast_years']; ?>" min="1" max="10" required>
                        <small style="color:#888;">How many years ahead to project</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Sections per Teacher:</label>
                        <input type="number" name="sections_per_teacher" value="<?php echo $forecast_config['sections_per_teacher']; ?>" min="0.1" max="10" step="0.1" required>
                        <small style="color:#888;">
                            Teachers needed = Rooms ÷ this value &mdash; so <strong>rooms will always exceed teachers</strong>.<br>
                            DepEd SHS standard: <strong>1.5</strong> (9 sections per 6 teachers, per DepEd class programming guidelines).
                        </small>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Save Forecast Parameters</button>
                </div>
            </form>
        </div>

        <!-- System Information -->
        <div class="card">
            <h3>System Information</h3>
            <div class="system-info">
                <?php foreach ($system_info as $key => $value): ?>
                    <div class="info-row">
                        <span class="info-label"><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</span>
                        <span class="info-value"><?php echo htmlspecialchars($value); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
.settings-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

.settings-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.form-group {
    flex: 1;
    min-width: 200px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #003D99;
}

.form-group input[type="number"] {
    width: 100%;
    padding: 8px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 14px;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.checkbox-group label {
    margin: 0;
    font-weight: 500;
}

.form-actions {
    margin-top: 20px;
    text-align: center;
}

.system-info {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.info-label {
    font-weight: 600;
    color: #003D99;
}

.info-value {
    color: #666;
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
}

@media (max-width: 768px) {
    .settings-container {
        grid-template-columns: 1fr;
    }

    .form-row {
        flex-direction: column;
        gap: 15px;
    }
}
</style>