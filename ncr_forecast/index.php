<?php
session_start();
include 'db.php';

// --- START OF LOGIN FEATURE CODE ---
// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['LAST_ACTIVITY'] = time();

            // Remember Me
            if (!empty($_POST['remember'])) {
                $token = bin2hex(random_bytes(32));
                setcookie("remember_token", $token, time() + (86400 * 30), "/", "", true, true);
                $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $stmt->execute([$token, $user['id']]);
            }
        } else {
            $error = "Invalid credentials.";
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    setcookie("remember_token", "", time() - 3600, "/", "", true, true);
    header("Location: index.php");
    exit;
}

// Session timeout (30 minutes)
$timeout_duration = 1800;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Auto-login with Remember Me
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->execute([$_COOKIE['remember_token']]);
    $user = $stmt->fetch();
    if ($user) {
        // Rotate token on each use so a stolen cookie cannot be reused indefinitely
        $new_token = bin2hex(random_bytes(32));
        $stmt2 = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $stmt2->execute([$new_token, $user['id']]);
        setcookie("remember_token", $new_token, time() + (86400 * 30), "/", "", true, true);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['LAST_ACTIVITY'] = time();
    }
}
// --- END OF LOGIN FEATURE CODE ---

// Check if user is logged in to run your original dashboard logic
if (isset($_SESSION['user_id'])) {
    // --- START OF YOUR ORIGINAL PHP CODE ---
    // include 'session_check.php'; // Replaced by the logic above

    // Error handling and logging setup
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
    ini_set('error_log', 'logs/php_errors.log');

    // Apply debug_mode from saved settings
    $settings_file = 'config/settings.json';
    $app_settings = file_exists($settings_file)
        ? (json_decode(file_get_contents($settings_file), true) ?? [])
        : [];
    ini_set('display_errors', !empty($app_settings['debug_mode']) ? 1 : 0);

    // Create logs directory if it doesn't exist
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }

    function log_message($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_file = 'logs/app.log';
        $log_entry = "[{$timestamp}] [{$level}] {$message}\n";
        error_log($log_entry, 3, $log_file);
    }

    function sanitize_filename($filename) {
        // Remove any path components and null bytes
        $filename = basename($filename);
        $filename = str_replace(["\0", "..", "/", "\\"], "", $filename);
        return $filename;
    }

    // Admin helper functions
    function getUserCount() {
        global $pdo;
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            return $stmt->fetch()['count'];
        } catch (Exception $e) {
            return 0;
        }
    }

    function getActiveSessionCount() {
        // This is a simplified count - in reality you'd track active sessions in DB
        return 1; // Current user
    }

    function getTodayForecastCount() {
        // Count forecast generations today from logs (line-by-line to avoid loading entire file)
        $log_file = 'logs/app.log';
        if (!file_exists($log_file)) return 0;

        $today = date('Y-m-d');
        $count = 0;
        $handle = fopen($log_file, 'r');
        if (!$handle) return 0;
        while (($line = fgets($handle)) !== false) {
            if (strpos($line, $today) !== false && strpos($line, 'Forecast generated successfully') !== false) {
                $count++;
            }
        }
        fclose($handle);
        return $count;
    }

    // Per-school CSV download handler
    if (isset($_GET['download_school_csv'])) {
        $csv_file = 'forecast_per_school.csv';
        if (file_exists($csv_file)) {
            $timestamp = date('Y-m-d_H-i-s');
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="ncr_shs_per_school_forecast_' . $timestamp . '.csv"');
            header('Content-Length: ' . filesize($csv_file));
            readfile($csv_file);
            exit;
        }
    }

    // Per-school CSV upload handler
    if (isset($_FILES['file_school']) && $_FILES['file_school']['error'] === UPLOAD_ERR_OK) {
        try {
            $file_ext = strtolower(pathinfo($_FILES['file_school']['name'], PATHINFO_EXTENSION));
            if ($file_ext !== 'csv') throw new Exception('Only CSV files are allowed');
            if ($_FILES['file_school']['size'] > $max_upload_size) throw new Exception('File exceeds 5MB limit');

            $handle = fopen($_FILES['file_school']['tmp_name'], 'r');
            $header = fgetcsv($handle);
            $required_cols = ['School_ID','School_Name','Year','Academic_Enrollees','TVL_Enrollees','Total_Enrollees'];
            $missing = array_diff($required_cols, $header);
            if (!empty($missing)) throw new Exception('Missing columns: ' . implode(', ', $missing));

            // Validate first data row
            $first = fgetcsv($handle);
            if (!$first) throw new Exception('CSV has no data rows');
            fclose($handle);

            if (!move_uploaded_file($_FILES['file_school']['tmp_name'], 'NCR_SHS_Per_School_Enrollees.csv')) {
                throw new Exception('Failed to save file');
            }
            log_message('INFO', "Per-school CSV uploaded by user {$_SESSION['user_id']}");
            $message = "<div class='success'>✅ Per-school dataset uploaded successfully. Click Generate Forecast to run per-school forecasting.</div>";
        } catch (Exception $e) {
            $message = "<div class='error'>❌ " . htmlspecialchars($e->getMessage()) . "</div>";
            log_message('ERROR', 'Per-school upload error: ' . $e->getMessage());
        }
        if ($isAjax) { echo $message; exit; }
    }

    // Load model accuracy metrics if available
    $accuracy_metrics = null;
    if (file_exists('forecast_accuracy.json')) {
        $accuracy_metrics = json_decode(file_get_contents('forecast_accuracy.json'), true);
    }

    // CSV download handler
    if (isset($_GET['download_forecast_csv'])) {
        $csv_file = 'forecast.csv';
        if (file_exists($csv_file)) {
            $timestamp = date('Y-m-d_H-i-s');
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="ncr_shs_forecast_' . $timestamp . '.csv"');
            header('Content-Length: ' . filesize($csv_file));
            readfile($csv_file);
            exit;
        } else {
            $message = "<div class='error'>❌ No forecast file available to download. Generate a forecast first.</div>";
        }
    }

    $message = "";
    $previewTable = "";
    $max_upload_size = 5 * 1024 * 1024; // 5MB

    log_message('INFO', 'Index page loaded. Method: ' . $_SERVER['REQUEST_METHOD']);

    // Clear forecast data only when explicitly requested via the clear button,
    // NOT on every GET request (which was wiping forecasts on every page refresh).
    // Forecast clearing is handled below in the clear_forecast POST handler.

    $forecastExists = file_exists("forecast.csv");
    $isAjax = isset($_POST['ajax']);

    // File upload handling
    if(isset($_POST['upload']) || isset($_FILES['file'])) {
        log_message('INFO', 'File upload initiated');
        
        try {
            // Validate file upload
            if (!isset($_FILES['file'])) {
                throw new Exception('No file provided');
            }
            
            if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                    UPLOAD_ERR_PARTIAL => 'File upload was incomplete',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
                    UPLOAD_ERR_EXTENSION => 'Blocked by extension'
                ];
                throw new Exception($upload_errors[$_FILES['file']['error']] ?? 'Unknown upload error');
            }
            
            // Size validation
            if ($_FILES['file']['size'] > $max_upload_size) {
                throw new Exception('File exceeds maximum size of 5MB');
            }
            
            // File type validation - check both extension and MIME type
            $file_ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            if ($file_ext !== 'csv') {
                throw new Exception('Invalid file type. Only CSV files are allowed');
            }
            
            // Additional MIME type check (be lenient for CSV files)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['file']['tmp_name']);
            finfo_close($finfo);
            
            // Accept common MIME types for CSV files
            $allowed_mimes = ['text/plain', 'text/csv', 'text/x-csv', 'application/csv', 
                             'application/x-csv', 'application/vnd.ms-excel', 'application/octet-stream'];
            if (!in_array($mime_type, $allowed_mimes)) {
                log_message('WARNING', "Unusual MIME type for CSV: {$mime_type}. File: {$_FILES['file']['name']}");
                // Don't reject - file extension is verified and it's likely a CSV
            }
            
            $file = $_FILES['file']['tmp_name'];
            
            // Read and parse CSV
            if (!($handle = fopen($file, 'r'))) {
                throw new Exception('Could not open uploaded file');
            }
            
            $rows = [];
            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
            
            if (count($rows) < 2) {
                throw new Exception('CSV file must contain header and at least 1 data row');
            }
            
            $header = $rows[0];
            $required = ['Year','Total_Enrollees'];
            $missing = array_diff($required, $header);

            if(count($missing) > 0){
                throw new Exception('Missing columns: ' . implode(", ", $missing));
            }
            
            $yearIndex = array_search('Year', $header);
            $enrollIndex = array_search('Total_Enrollees', $header);

            // Validate data rows
            for($i=1;$i<count($rows);$i++){
                // Skip empty rows
                if (empty(array_filter($rows[$i]))) continue;
                
                $year = trim($rows[$i][$yearIndex] ?? '');
                $enroll = trim($rows[$i][$enrollIndex] ?? '');

                if(empty($year) || empty($enroll)){
                    throw new Exception("Row {$i}: Missing required values");
                }
                
                if(!is_numeric($year)){ 
                    throw new Exception("Row {$i}: Year must be numeric"); 
                }
                
                if(!is_numeric($enroll)){ 
                    throw new Exception("Row {$i}: Total_Enrollees must be numeric"); 
                }
                
                $year_int = intval($year);
                if ($year_int < 1900 || $year_int > 2100) {
                    throw new Exception("Row {$i}: Year must be between 1900 and 2100");
                }
                
                $enroll_val = floatval($enroll);
                if ($enroll_val < 0) {
                    throw new Exception("Row {$i}: Total_Enrollees cannot be negative");
                }
            }
            
            // Save the file
            if (!move_uploaded_file($file, "NCR_Total_Enrollees.csv")) {
                throw new Exception('Failed to save uploaded file');
            }
            
            log_message('INFO', 'File uploaded successfully: NCR_Total_Enrollees.csv');
            $message="<div class='success'>✅ Dataset uploaded and validated successfully.</div>";

            // Delete old forecasts
            if(file_exists("forecast.csv")) unlink("forecast.csv");
            if(file_exists("forecast_report.pdf")) unlink("forecast_report.pdf");

            // Preview
            $previewTable="<table><tr>";
            foreach($header as $col){ 
                $previewTable.="<th>" . htmlspecialchars($col) . "</th>"; 
            }
            $previewTable.="</tr>";

            for($i=1;$i<min(6,count($rows));$i++){
                $previewTable.="<tr>";
                foreach($rows[$i] as $cell){ 
                    $previewTable.="<td>" . htmlspecialchars($cell) . "</td>"; 
                }
                $previewTable.="</tr>";
            }
            $previewTable.="</table>";
            $forecastExists=false;
            
        } catch (Exception $e) {
            $error_msg = "File upload error: " . $e->getMessage();
            $message = "<div class='error'>❌ " . htmlspecialchars($error_msg) . "</div>";
            log_message('ERROR', $error_msg);
        }
        
        // If AJAX request, return only the message
        if($isAjax) {
            echo $message;
            exit;
        }
    }

    // Run forecast
    if(isset($_POST['run'])){
        log_message('INFO', 'Forecast generation initiated');
        
        try {
            if (!file_exists('NCR_Total_Enrollees.csv')) {
                throw new Exception('No dataset uploaded. Please upload data first.');
            }
            
            $output = shell_exec("python forecast.py 2>&1");
            
            if(strpos($output,"ERROR")!==false){
                log_message('ERROR', 'Forecast failed: ' . $output);
                $message="<div class='error'>❌ Forecast generation failed. Check logs for details.</div>";
            } else if(strpos($output,"SUCCESS")!==false) {
                log_message('INFO', 'Forecast generated successfully');
                $message="<div class='success'>✅ Forecast updated successfully.</div>";
                $forecastExists=true;
            } else {
                log_message('WARNING', 'Unexpected forecast output: ' . $output);
                $message="<div class='warning'>⚠️ Forecast completed with unexpected output.</div>";
                $forecastExists = file_exists("forecast.csv");
            }
        } catch (Exception $e) {
            $error_msg = "Forecast error: " . $e->getMessage();
            $message = "<div class='error'>❌ " . htmlspecialchars($error_msg) . "</div>";
            log_message('ERROR', $error_msg);
        }
        
        // If AJAX request, return only the message
        if($isAjax) {
            echo $message;
            exit;
        }
    }

    // Clear forecast
    if(isset($_POST['clear_forecast'])){
        try {
            if(file_exists("forecast.csv")) unlink("forecast.csv");
            if(file_exists("forecast_report.pdf")) unlink("forecast_report.pdf");
            log_message('INFO', 'Forecast cleared by user');
            $message="<div class='success'>✅ Forecast cleared successfully.</div>";
            $forecastExists=false;
        } catch (Exception $e) {
            $error_msg = "Clear forecast error: " . $e->getMessage();
            $message = "<div class='error'>❌ " . htmlspecialchars($error_msg) . "</div>";
            log_message('ERROR', $error_msg);
        }
        
        // If AJAX request, return only the message
        if($isAjax) {
            echo $message;
            exit;
        }
    }
    // --- END OF YOUR ORIGINAL PHP CODE ---
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>NCR SHS Forecast — DepEd Resource Planning</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php if (!isset($_SESSION['user_id'])): ?>
    <div class="login-container">
        <div class="login-scene"></div>
        <div class="login-particles">
            <span></span><span></span><span></span>
            <span></span><span></span><span></span>
        </div>
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo-circle">👤</div>
                <h2 class="login-title">NCR SHS Forecast</h2>
                <p class="login-subtitle">Enrollment Forecasting System</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="login-error-alert">
                    <span class="error-icon">⚠️</span>
                    <span class="error-message"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php" class="login-form">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" placeholder="Enter your username" required class="form-input" autofocus>
                        <span class="input-icon">👤</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required class="form-input">
                        <span class="input-icon password-icon">🔒</span>
                    </div>
                </div>

                <div class="remember-me-wrapper">
                    <input type="checkbox" id="remember" name="remember" class="checkbox-input">
                    <label for="remember" class="checkbox-label">Remember me on this device</label>
                </div>

                <button type="submit" name="login" class="login-button">Sign In</button>
            </form>

            <div class="login-footer">
                <p class="footer-text">Need help? Contact your administrator</p>
            </div>
        </div>
    </div>

<?php else: ?>
    <?php $current_page = 'ncr'; include 'nav.php'; ?>

    <div class="page-wrapper">

    <?php echo $message; ?>

    <!-- Hero banner -->
    <div class="page-hero">
        <div class="page-hero-text">
            <h1>NCR Senior High School Enrollment Forecast</h1>
            <p>Department of Education — National Capital Region &nbsp;|&nbsp; Resource Planning Dashboard</p>
        </div>
        <?php if($forecastExists && $accuracy_metrics): ?>
        <div class="page-hero-badge">
            <div class="badge-label">Model Accuracy</div>
            <div class="badge-value"><?php echo $accuracy_metrics['accuracy']; ?>%</div>
            <div class="badge-sub">MAPE: <?php echo $accuracy_metrics['mape']; ?>%</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- KPI stat cards -->
    <div class="stat-grid">
        <div class="stat-card stat-primary">
            <div class="stat-label">Target School Year</div>
            <div class="stat-value" id="stat-target-year">
            <?php
                if ($forecastExists) {
                    $lines = file('forecast.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $firstDataLine = isset($lines[1]) ? $lines[1] : '';
                    $cols = str_getcsv($firstDataLine);
                    echo isset($cols[0]) && is_numeric(trim($cols[0])) ? (int)trim($cols[0]) : '--';
                } else { echo '--'; }
            ?>
            </div>
            <div class="stat-subtext">Next forecast year</div>
        </div>
        <div class="stat-card stat-highlight" id="stat-enrollees">
            <div class="stat-label">Projected Enrollees</div>
            <div class="stat-value">--</div>
            <div class="stat-subtext">NCR total</div>
        </div>
        <div class="stat-card stat-accent" id="stat-academic-rooms">
            <div class="stat-label">Academic Classrooms</div>
            <div class="stat-value">--</div>
            <div class="stat-subtext">rooms needed</div>
        </div>
        <div class="stat-card stat-accent" id="stat-tvl-rooms">
            <div class="stat-label">TVL Classrooms</div>
            <div class="stat-value">--</div>
            <div class="stat-subtext">rooms needed</div>
        </div>
        <div class="stat-card stat-warning" id="stat-academic-teachers">
            <div class="stat-label">Academic Teachers</div>
            <div class="stat-value">--</div>
            <div class="stat-subtext">positions needed</div>
        </div>
        <div class="stat-card stat-warning" id="stat-tvl-teachers">
            <div class="stat-label">TVL Teachers</div>
            <div class="stat-value">--</div>
            <div class="stat-subtext">positions needed</div>
        </div>
        <div class="stat-card stat-accuracy" id="stat-accuracy">
            <div class="stat-label">Model Accuracy</div>
            <div class="stat-value" id="stat-accuracy-value">
                <?php echo $accuracy_metrics ? $accuracy_metrics['accuracy'] . '%' : '--'; ?>
            </div>
            <div class="stat-subtext" id="stat-accuracy-sub">
                <?php echo $accuracy_metrics ? 'MAPE: ' . $accuracy_metrics['mape'] . '%' : 'Generate forecast to compute'; ?>
            </div>
        </div>
    </div>

    <!-- Main content grid -->
    <div class="container">

        <!-- Data Management card -->
        <div class="card card-wide">
            <div class="section-header" onclick="toggleDataSection()">
                <h2 style="margin:0; padding:0; border:none; font-size:1em; font-family:var(--font-body); font-weight:700; color:var(--text-primary);">
                    Data Management
                </h2>
                <span class="toggle-icon" id="toggle-icon">▼</span>
            </div>
            <div id="data-section" class="data-section-content" style="display:none;">
                <div class="dm-grid">
                    <!-- Left: upload -->
                    <div>
                        <div class="dm-panel-label">Upload NCR Total Dataset</div>
                        <p style="font-size:0.82em; color:var(--text-muted); margin:0 0 10px;">
                            For per-school forecasting, visit the
                            <a href="school_forecast.php">🏫 Per-School Forecast</a> page.
                        </p>
                        <form id="uploadForm" enctype="multipart/form-data">
                            <input type="file" id="csvFile" name="file" accept=".csv" required>
                            <button type="button" class="btn-primary" onclick="handleFileUpload()" style="width:100%;">Upload Dataset</button>
                        </form>
                        <div id="uploadMessage"></div>
                        <hr>
                        <div class="dm-panel-label">Required Format</div>
                        <pre>Year,Total_Enrollees
2023,245000
2024,265000
2025,287000</pre>
                    </div>
                    <!-- Right: actions -->
                    <div>
                        <div class="dm-panel-label">Forecast Actions</div>
                        <div class="action-panel">
                            <div class="action-panel-title">⚡ Generate Forecast</div>
                            <p style="font-size:0.8em; color:var(--text-muted); margin:0 0 10px;">Run Prophet time-series model on uploaded data.</p>
                            <button type="button" onclick="handleGenerateForecast()" class="btn-primary" style="width:100%;">Generate 3-Year Forecast</button>
                            <div id="generateMessage"></div>
                        </div>
                        <div class="action-panel" style="margin-top:10px;">
                            <div class="action-panel-title">📥 Download Results</div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <a href="forecast_report.pdf" download style="flex:1; text-decoration:none;">
                                    <button type="button" class="btn-secondary" style="width:100%;">PDF Report</button>
                                </a>
                                <a href="?download_forecast_csv=1" download style="flex:1; text-decoration:none;">
                                    <button type="button" class="btn-teal" style="width:100%;">CSV Export</button>
                                </a>
                            </div>
                        </div>
                        <div class="action-panel" style="margin-top:10px;">
                            <div class="action-panel-title">🗑️ Reset</div>
                            <button type="button" onclick="handleClearForecast()" class="btn-danger" style="width:100%;">Clear Forecast Data</button>
                            <div id="clearMessage"></div>
                        </div>
                    </div>
                </div>
                <!-- Dataset preview -->
                <hr style="margin-top:20px;">
                <div class="dm-panel-label" style="margin-top:16px;">Current Dataset Preview</div>
                <div class="table-wrapper" id="previewTableContainer">
                    <?php echo $previewTable ?: "<p style='color:var(--text-muted); padding:12px 0; font-size:0.85em;'>No dataset uploaded yet.</p>"; ?>
                </div>
            </div>
        </div>


        <!-- Forecast chart -->
        <div class="card card-wide">
            <h2>Enrollment Forecast Trend</h2>
            <?php if($forecastExists): ?>
            <div class="last-updated-label">
                🕒 Last generated: <strong><?php echo date('F j, Y 	 g:i A', filemtime('forecast.csv')); ?></strong>
            </div>
            <?php endif; ?>
            <div id="chartContainer" style="position:relative; min-height:260px;">
                <?php if($forecastExists): ?>
                    <canvas id="forecastChart"></canvas>
                <?php else: ?>
                    <div style="text-align:center; padding:60px 20px; color:var(--text-muted);">
                        <div style="font-size:2em; margin-bottom:12px;">📊</div>
                        <p>Upload a dataset and generate a forecast to view the enrollment trend.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Resource allocation insight -->
        <div class="card card-wide">
            <h2>Recommended Resource Allocation — Next School Year</h2>
            <div id="insightText" class="insights-content">
                <?php if(!$forecastExists): ?>
                <p style="color:var(--text-muted);">Generate a forecast to view resource allocation recommendations.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3-year detail table -->
        <div class="card card-wide">
            <h2>3-Year Forecast Detail</h2>
            <div class="table-wrapper">
                <table id="forecastTable">
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Projected Enrollees (95% CI)</th>
                            <th>Academic Rooms</th>
                            <th>TVL Rooms</th>
                            <th>Academic Teachers</th>
                            <th>TVL Teachers</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <?php if(!$forecastExists): ?>
                    <p style="text-align:center; padding:24px; color:var(--text-muted); font-size:0.88em;">
                        No forecast data available. Upload a dataset and click Generate Forecast.
                    </p>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- end .container -->

    <?php if($forecastExists): ?>
    <script>
    // Build historical data from uploaded dataset
    <?php
    $hist_labels = [];
    $hist_values = [];
    if (file_exists('NCR_Total_Enrollees.csv')) {
        $hlines = file('NCR_Total_Enrollees.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        for ($i = 1; $i < count($hlines); $i++) {
            $hcols = str_getcsv($hlines[$i]);
            if (count($hcols) >= 2 && is_numeric(trim($hcols[0]))) {
                $hist_labels[] = (int)trim($hcols[0]);
                $hist_values[] = (int)trim($hcols[1]);
            }
        }
    }
    echo "const histLabels = " . json_encode($hist_labels) . ";
";
    echo "const histValues = " . json_encode($hist_values) . ";
";
    ?>

    fetch('forecast.csv')
    .then(res=>res.text())
    .then(data=>{
        if(!data) return;
        let rows=data.split("\n");
        let forecastLabels=[], forecastValues=[];
        let table=document.getElementById("forecastTable");

        let lowerValues=[], upperValues=[];

        for(let i=1;i<rows.length;i++){
            let cols=rows[i].split(",");
            if(cols.length<8) continue;
            
            let year=parseInt(cols[0].trim());
            if(!year || isNaN(year)) continue;
            if(year < 2026) continue;

            forecastLabels.push(year);
            forecastValues.push(Math.round(parseFloat(cols[1])));
            lowerValues.push(Math.round(parseFloat(cols[2]))); // yhat_lower
            upperValues.push(Math.round(parseFloat(cols[3]))); // yhat_upper

            let row=table.insertRow();
            row.insertCell(0).innerText=year;
            row.insertCell(1).innerText=Math.round(parseFloat(cols[1])) + ' (' + Math.round(parseFloat(cols[2])).toLocaleString() + '–' + Math.round(parseFloat(cols[3])).toLocaleString() + ')';
            row.insertCell(2).innerText=Math.round(parseFloat(cols[4]));
            row.insertCell(3).innerText=Math.round(parseFloat(cols[5]));
            row.insertCell(4).innerText=Math.round(parseFloat(cols[6]));
            row.insertCell(5).innerText=Math.round(parseFloat(cols[7]));
        }

        // Combine labels: historical + forecast (no duplicates)
        let allLabels = [...histLabels];
        forecastLabels.forEach(y => { if (!allLabels.includes(y)) allLabels.push(y); });
        allLabels.sort((a,b) => a-b);

        // Align historical data to allLabels (null where no data)
        let alignedHist = allLabels.map(y => {
            let idx = histLabels.indexOf(y);
            return idx !== -1 ? histValues[idx] : null;
        });

        // Align forecast data to allLabels (null where no data)
        let alignedForecast = allLabels.map(y => {
            let idx = forecastLabels.indexOf(y);
            return idx !== -1 ? forecastValues[idx] : null;
        });
        let alignedLower = allLabels.map(y => {
            let idx = forecastLabels.indexOf(y);
            return idx !== -1 ? lowerValues[idx] : null;
        });
        let alignedUpper = allLabels.map(y => {
            let idx = forecastLabels.indexOf(y);
            return idx !== -1 ? upperValues[idx] : null;
        });

        // Find the dividing index (last historical year)
        let lastHistYear = histLabels.length ? Math.max(...histLabels) : null;

        new Chart(document.getElementById("forecastChart"),{
            type:'line',
            data:{
                labels: allLabels,
                datasets:[
                    {
                        label: 'Actual Enrollees (Historical)',
                        data: alignedHist,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40,167,69,0.08)',
                        borderWidth: 2,
                        pointRadius: 4,
                        fill: false,
                        spanGaps: false,
                        order: 1
                    },
                    {
                        label: '95% Confidence Upper Bound',
                        data: alignedUpper,
                        borderColor: 'rgba(0,61,153,0.15)',
                        backgroundColor: 'rgba(0,61,153,0.08)',
                        borderWidth: 1,
                        borderDash: [3, 3],
                        pointRadius: 0,
                        fill: '+1',
                        spanGaps: false,
                        order: 3
                    },
                    {
                        label: 'Projected Enrollees (Forecast)',
                        data: alignedForecast,
                        borderColor: '#003D99',
                        backgroundColor: 'rgba(0,61,153,0.10)',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        pointRadius: 4,
                        fill: false,
                        spanGaps: false,
                        order: 2
                    },
                    {
                        label: '95% Confidence Lower Bound',
                        data: alignedLower,
                        borderColor: 'rgba(0,61,153,0.15)',
                        backgroundColor: 'rgba(0,61,153,0.08)',
                        borderWidth: 1,
                        borderDash: [3, 3],
                        pointRadius: 0,
                        fill: '-1',
                        spanGaps: false,
                        order: 4
                    }
                ]
            },
            options:{
                responsive:true,
                plugins:{
                    title:{
                        display:true,
                        text: 'Historical & 3-Year Enrollment Forecast'
                    },
                    legend:{ display:true },
                    annotation: {
                        annotations: lastHistYear ? [{
                            type: 'line',
                            scaleID: 'x',
                            value: String(lastHistYear),
                            borderColor: '#aaa',
                            borderWidth: 1,
                            borderDash: [4,4],
                            label: { content: 'Forecast starts', enabled: true, position: 'end' }
                        }] : []
                    }
                },
                scales:{
                    y:{
                        title:{display:true, text:'Number of Enrollees'},
                        beginAtZero:false
                    },
                    x:{
                        title:{display:true, text:'Year'}
                    }
                }
            }
        });

        let latest=rows[1].split(","); // First forecast year (next school year)
        let latestEnrollees = Math.round(parseFloat(latest[1]));
        let latestAcadRooms = Math.round(parseFloat(latest[4]));
        let latestTVLRooms = Math.round(parseFloat(latest[5]));
        let latestAcadTeachers = Math.round(parseFloat(latest[6]));
        let latestTVLTeachers = Math.round(parseFloat(latest[7]));

        document.getElementById("stat-target-year").innerText = forecastLabels[0] || '--'; // First forecast year = target year
        document.getElementById("stat-enrollees").querySelector(".stat-value").innerText = latestEnrollees.toLocaleString();
        document.getElementById("stat-academic-rooms").querySelector(".stat-value").innerText = latestAcadRooms;
        document.getElementById("stat-tvl-rooms").querySelector(".stat-value").innerText = latestTVLRooms;
        document.getElementById("stat-academic-teachers").querySelector(".stat-value").innerText = latestAcadTeachers;
        document.getElementById("stat-tvl-teachers").querySelector(".stat-value").innerText = latestTVLTeachers;

        // Load and display accuracy metrics
        fetch('forecast_accuracy.json?t=' + Date.now())
            .then(r => r.json())
            .then(acc => {
                document.getElementById("stat-accuracy-value").innerText = acc.accuracy + '%';
                document.getElementById("stat-accuracy-sub").innerText = 'MAPE: ' + acc.mape + '%';
            }).catch(() => {});

        document.getElementById("insightText").innerHTML=`
            <div class="insights-content">
                <p><strong>📊 Projected Enrollees:</strong> <span style="color:#003D99; font-size:1.2em; font-weight:700;">${latestEnrollees.toLocaleString()}</span></p>
                <p><strong>🏫 Academic Classrooms:</strong> <span style="color:#003D99; font-size:1.1em; font-weight:600;">${latestAcadRooms}</span> rooms</p>
                <p><strong>🏫 TVL Classrooms:</strong> <span style="color:#003D99; font-size:1.1em; font-weight:600;">${latestTVLRooms}</span> rooms</p>
                <p><strong>👨‍🏫 Academic Teachers:</strong> <span style="color:#003D99; font-size:1.1em; font-weight:600;">${latestAcadTeachers}</span> teachers</p>
                <p><strong>👩‍🏫 TVL Teachers:</strong> <span style="color:#003D99; font-size:1.1em; font-weight:600;">${latestTVLTeachers}</span> teachers</p>
            </div>
        `;
    });
    </script>
    <?php endif; ?>

    <script>
    function handleFileUpload() {
        let fileInput = document.getElementById('csvFile');
        let file = fileInput.files[0];
        let messageDiv = document.getElementById('uploadMessage');
        
        if (!file) {
            messageDiv.innerHTML = "<div class='error'>❌ Please select a file to upload.</div>";
            return;
        }
        
        let formData = new FormData();
        formData.append('file', file);
        formData.append('ajax', '1');
        
        messageDiv.innerHTML = "<div style='color:#003D99; padding:10px;'>⏳ Uploading and validating...</div>";
        
        fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            messageDiv.innerHTML = data;
            fileInput.value = ''; // Clear file input
            
            // Update preview dynamically without page reload
            setTimeout(() => {
                fetch('NCR_Total_Enrollees.csv?t=' + Date.now()) // Cache bust
                    .then(res => res.text())
                    .then(csvData => {
                        let lines = csvData.trim().split('\n');
                        let headers = lines[0].split(',');
                        let previewHTML = '<table><tr>';
                        
                        headers.forEach(h => {
                            previewHTML += '<th>' + h.trim() + '</th>';
                        });
                        previewHTML += '</tr>';
                        
                        // Show first 5 rows
                        for (let i = 1; i < Math.min(6, lines.length); i++) {
                            let cells = lines[i].split(',');
                            previewHTML += '<tr>';
                            cells.forEach(cell => {
                                previewHTML += '<td>' + cell.trim() + '</td>';
                            });
                            previewHTML += '</tr>';
                        }
                        previewHTML += '</table>';
                        
                        document.getElementById('previewTableContainer').innerHTML = previewHTML;
                    });
            }, 500);
        })
        .catch(error => {
            messageDiv.innerHTML = "<div class='error'>❌ Upload failed: " + error.message + "</div>";
        });
    }

    function toggleDataSection() {
        let section = document.getElementById("data-section");
        let icon = document.getElementById("toggle-icon");
        if (section.style.display === "none") {
            section.style.display = "block";
            icon.textContent = "▲";
        } else {
            section.style.display = "none";
            icon.textContent = "▼";
        }
    }

    function handleGenerateForecast() {
        let messageDiv = document.getElementById('generateMessage');
        messageDiv.innerHTML = "<div style='color:#003D99; padding:10px; margin-top:8px;'>⏳ Generating forecast...</div>";
        
        let formData = new FormData();
        formData.append('run', '1');
        formData.append('ajax', '1');
        
        fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            messageDiv.innerHTML = data;
            
            // Reload chart and stats dynamically after forecast is generated
            setTimeout(() => {
                loadForecastChart();
            }, 500);
        })
        .catch(error => {
            messageDiv.innerHTML = "<div class='error'>❌ Forecast generation failed: " + error.message + "</div>";
        });
    }

    function handleClearForecast() {
        if (!confirm('Are you sure you want to clear all forecast data?')) {
            return;
        }
        
        let messageDiv = document.getElementById('clearMessage');
        messageDiv.innerHTML = "<div style='color:#003D99; padding:10px; margin-top:8px;'>⏳ Clearing forecast...</div>";
        
        let formData = new FormData();
        formData.append('clear_forecast', '1');
        formData.append('ajax', '1');
        
        fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            messageDiv.innerHTML = data;
            
            // Clear chart and reset stats
            setTimeout(() => {
                if (window.forecastChartInstance) {
                    window.forecastChartInstance.destroy();
                }
                document.getElementById("forecastChart").innerHTML = "";
                document.getElementById("forecastChart").parentElement.innerHTML = "<p>⚠️ No forecast data yet. Upload a dataset and generate a forecast to see the trend.</p>";
                document.getElementById("forecastTable").innerHTML = "<tr><th>Year</th><th>Projected Enrollees (95% CI)</th><th>Academic Rooms</th><th>TVL Rooms</th><th>Academic Teachers</th><th>TVL Teachers</th></tr>";
                document.getElementById("insightText").innerHTML = "<p>⚠️ Generate a forecast to view resource recommendations.</p>";
                document.getElementById("stat-enrollees").querySelector(".stat-value").innerText = "--";
                document.getElementById("stat-academic-rooms").querySelector(".stat-value").innerText = "--";
                document.getElementById("stat-tvl-rooms").querySelector(".stat-value").innerText = "--";
                document.getElementById("stat-academic-teachers").querySelector(".stat-value").innerText = "--";
                document.getElementById("stat-tvl-teachers").querySelector(".stat-value").innerText = "--";
            }, 500);
        })
        .catch(error => {
            messageDiv.innerHTML = "<div class='error'>❌ Clear failed: " + error.message + "</div>";
        });
    }

    function loadForecastChart() {
        // Fetch both historical and forecast CSVs in parallel
        Promise.all([
            fetch('NCR_Total_Enrollees.csv?t=' + Date.now()).then(r => r.text()).catch(() => ''),
            fetch('forecast.csv?t=' + Date.now()).then(r => r.text())
        ]).then(([histData, forecastData]) => {
            if (!forecastData) return;

            // Parse historical data
            let histLabels = [], histValues = [];
            if (histData) {
                let hrows = histData.trim().split("\n");
                for (let i = 1; i < hrows.length; i++) {
                    let c = hrows[i].split(",");
                    let y = parseInt(c[0]);
                    if (!isNaN(y)) { histLabels.push(y); histValues.push(parseInt(c[1])); }
                }
            }

            // Parse forecast data
            let forecastLabels = [], forecastValues = [];
            let rows = forecastData.split("\n");
            let table = document.getElementById("forecastTable");
            table.innerHTML = "<tr><th>Year</th><th>Projected Enrollees (95% CI)</th><th>Academic Rooms</th><th>TVL Rooms</th><th>Academic Teachers</th><th>TVL Teachers</th></tr>";

            let lowerValues = [], upperValues = [];

            for (let i = 1; i < rows.length; i++) {
                let cols = rows[i].split(",");
                if (cols.length < 8) continue;
                let year = parseInt(cols[0].trim());
                if (!year || isNaN(year) || year < 2026) continue;

                forecastLabels.push(year);
                forecastValues.push(Math.round(parseFloat(cols[1])));
                lowerValues.push(Math.round(parseFloat(cols[2]))); // yhat_lower
                upperValues.push(Math.round(parseFloat(cols[3]))); // yhat_upper

                let row = table.insertRow();
                row.insertCell(0).innerText = year;
                row.insertCell(1).innerText = Math.round(parseFloat(cols[1])) + ' (' + Math.round(parseFloat(cols[2])).toLocaleString() + '–' + Math.round(parseFloat(cols[3])).toLocaleString() + ')';
                row.insertCell(2).innerText = Math.round(parseFloat(cols[4]));
                row.insertCell(3).innerText = Math.round(parseFloat(cols[5]));
                row.insertCell(4).innerText = Math.round(parseFloat(cols[6]));
                row.insertCell(5).innerText = Math.round(parseFloat(cols[7]));
            }

            // Merge all labels in order
            let allLabels = [...histLabels];
            forecastLabels.forEach(y => { if (!allLabels.includes(y)) allLabels.push(y); });
            allLabels.sort((a, b) => a - b);

            let alignedHist     = allLabels.map(y => { let i = histLabels.indexOf(y);     return i !== -1 ? histValues[i]     : null; });
            let alignedForecast = allLabels.map(y => { let i = forecastLabels.indexOf(y); return i !== -1 ? forecastValues[i] : null; });
            let alignedLower    = allLabels.map(y => { let i = forecastLabels.indexOf(y); return i !== -1 ? lowerValues[i]    : null; });
            let alignedUpper    = allLabels.map(y => { let i = forecastLabels.indexOf(y); return i !== -1 ? upperValues[i]    : null; });

            // Destroy old chart if exists
            if (window.forecastChartInstance) window.forecastChartInstance.destroy();

            // Create canvas if it doesn't exist
            let canvasElement = document.getElementById("forecastChart");
            if (!canvasElement) {
                document.getElementById("chartContainer").innerHTML = '<canvas id="forecastChart"></canvas>';
                canvasElement = document.getElementById("forecastChart");
            }

            window.forecastChartInstance = new Chart(canvasElement, {
                type: 'line',
                data: {
                    labels: allLabels,
                    datasets: [
                        {
                            label: 'Actual Enrollees (Historical)',
                            data: alignedHist,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40,167,69,0.08)',
                            borderWidth: 2,
                            pointRadius: 4,
                            fill: false,
                            spanGaps: false,
                            order: 1
                        },
                        {
                            label: '95% Confidence Upper Bound',
                            data: alignedUpper,
                            borderColor: 'rgba(0,61,153,0.15)',
                            backgroundColor: 'rgba(0,61,153,0.08)',
                            borderWidth: 1,
                            borderDash: [3, 3],
                            pointRadius: 0,
                            fill: '+1',
                            spanGaps: false,
                            order: 3
                        },
                        {
                            label: 'Projected Enrollees (Forecast)',
                            data: alignedForecast,
                            borderColor: '#003D99',
                            backgroundColor: 'rgba(0,61,153,0.10)',
                            borderWidth: 2,
                            borderDash: [6, 4],
                            pointRadius: 4,
                            fill: false,
                            spanGaps: false,
                            order: 2
                        },
                        {
                            label: '95% Confidence Lower Bound',
                            data: alignedLower,
                            borderColor: 'rgba(0,61,153,0.15)',
                            backgroundColor: 'rgba(0,61,153,0.08)',
                            borderWidth: 1,
                            borderDash: [3, 3],
                            pointRadius: 0,
                            fill: '-1',
                            spanGaps: false,
                            order: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: { display: true, text: 'Historical & 3-Year Enrollment Forecast' },
                        legend: { display: true }
                    },
                    scales: {
                        y: { title: { display: true, text: 'Number of Enrollees' }, beginAtZero: false },
                        x: { title: { display: true, text: 'Year' } }
                    }
                }
            });

            // Update stats
            let latest = rows[1].split(","); // First forecast year (next school year)
            let latestEnrollees  = Math.round(parseFloat(latest[1]));
            let latestAcadRooms  = Math.round(parseFloat(latest[4]));
            let latestTVLRooms   = Math.round(parseFloat(latest[5]));
            let latestAcadTeachers = Math.round(parseFloat(latest[6]));
            let latestTVLTeachers  = Math.round(parseFloat(latest[7]));

            document.getElementById("stat-target-year").innerText = forecastLabels[0] || '--'; // First forecast year = target year
            document.getElementById("stat-enrollees").querySelector(".stat-value").innerText = latestEnrollees.toLocaleString();
            document.getElementById("stat-academic-rooms").querySelector(".stat-value").innerText = latestAcadRooms;
            document.getElementById("stat-tvl-rooms").querySelector(".stat-value").innerText = latestTVLRooms;
            document.getElementById("stat-academic-teachers").querySelector(".stat-value").innerText = latestAcadTeachers;
            document.getElementById("stat-tvl-teachers").querySelector(".stat-value").innerText = latestTVLTeachers;

            // Refresh accuracy card
            fetch('forecast_accuracy.json?t=' + Date.now())
                .then(r => r.json())
                .then(acc => {
                    document.getElementById("stat-accuracy-value").innerText = acc.accuracy + '%';
                    document.getElementById("stat-accuracy-sub").innerText = 'MAPE: ' + acc.mape + '%';
                }).catch(() => {});

            document.getElementById("insightText").innerHTML = `
                <div class="insights-content">
                    <p><strong>📊 Projected Enrollees:</strong> <span style="color:#003D99; font-size:1.2em; font-weight:700;">${latestEnrollees.toLocaleString()}</span></p>
                    <p><strong>🏫 Academic Classrooms:</strong> <span style="color:#003D99; font-size:1.1em; font-weight:600;">${latestAcadRooms}</span> rooms</p>
                    <p><strong>🏫 TVL Classrooms:</strong> <span style="color:#003D99; font-size:1.1em; font-weight:600;">${latestTVLRooms}</span> rooms</p>
                    <p><strong>👨‍🏫 Academic Teachers:</strong> <span style="color:#003D99; font-size:1.1em; font-weight:600;">${latestAcadTeachers}</span> teachers</p>
                    <p><strong>👩‍🏫 TVL Teachers:</strong> <span style="color:#003D99; font-size:1.1em; font-weight:600;">${latestTVLTeachers}</span> teachers</p>
                </div>
            `;
        });
    }

    // Start with data section collapsed
    document.getElementById("data-section").style.display = "none";
    document.getElementById("toggle-icon").textContent = "▼";
    </script>

    <!-- Formula reference footer -->
    <div class="formula-footer">
        <div style="max-width:900px; margin:0 auto;">
            <h3>Calculation Reference</h3>
            <div class="formula-grid">
                <div class="formula-item">Academic Rooms = (Enrollees × 65%) ÷ 40</div>
                <div class="formula-item">TVL Rooms = (Enrollees × 35%) ÷ 40</div>
                <div class="formula-item">Academic Teachers = Academic Rooms ÷ 1.5</div>
                <div class="formula-item">TVL Teachers = TVL Rooms ÷ 1.5</div>
            </div>
            <p class="formula-note">
                DepEd SHS parameters: Class size 40 · Academic/TVL split 65%/35% ·
                1 teacher per 1.5 sections (9 sections / 6 teachers) · 95% confidence intervals via Prophet
            </p>
        </div>
    </div>

    </div><!-- end .page-wrapper -->
<?php endif; ?>

<script>
// Password visibility toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const passwordIcon = passwordInput ? passwordInput.parentElement.querySelector('.input-icon') : null;
    
    if (passwordInput && passwordIcon) {
        passwordIcon.addEventListener('click', function() {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            
            // Update icon based on visibility state
            this.textContent = isPassword ? '👁️' : '🔒';
            
            // Add temporary visual feedback
            this.style.transform = 'translateY(-50%) scale(1.2)';
            setTimeout(() => {
                this.style.transform = 'translateY(-50%) scale(1.1)';
            }, 150);
        });
        
        // Add hover effect for interactivity indication
        passwordIcon.addEventListener('mouseenter', function() {
            if (passwordInput.type === 'password') {
                this.style.opacity = '0.8';
            }
        });
        
        passwordIcon.addEventListener('mouseleave', function() {
            this.style.opacity = '1';
        });
    }
});
</script>

<?php $bg_mode = isset($_SESSION['user_id']) ? 'dashboard' : 'login'; include 'bg_canvas.php'; ?>
</body>
</html>