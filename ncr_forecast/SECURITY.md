# Security Guide - NCR SHS Enrollment Forecast System

## Overview

This document outlines security considerations and best practices for deploying and maintaining the NCR SHS Enrollment Forecast System.

## Current Security Measures

### File Upload Security
✅ **Implemented**
- File type validation (MIME type checking)
- File size limits (5MB maximum)
- Name sanitization
- CSV format validation
- Data type validation (numeric checks)

### Input Validation
✅ **Implemented**
- Year range validation (1900-2100)
- Non-negative enrollment values
- Required field validation
- HTML escaping for output
- Array bounds checking

### Error Handling
✅ **Implemented**
- Try-catch exception handling (PHP & Python)
- Detailed error logging
- User-friendly error messages
- Stack trace logging for debugging

### Logging & Monitoring
✅ **Implemented**
- Application activity logging
- Error logging to separate file
- Timestamp tracking
- User action tracking

## Recommended Additional Security Measures

### 1. Authentication & Authorization

**Current Status:** ⚠️ None implemented

**Recommendation:** Add user authentication for Production

```php
// Example: Basic HTTP authentication
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="NCR Forecast"');
    header('HTTP/1.0 401 Unauthorized');
    echo "Authentication required";
    exit;
}

// Verify credentials
$valid_users = ['admin' => password_hash('password123', PASSWORD_DEFAULT)];
if (!isset($valid_users[$_SERVER['PHP_AUTH_USER']]) ||
    !password_verify($_SERVER['PHP_AUTH_PW'], 
                     $valid_users[$_SERVER['PHP_AUTH_USER']])) {
    die("Invalid credentials");
}
```

### 2. CSRF Protection

**Current Status:** ⚠️ Partial (form tokens recommended)

**Recommendation:** Implement token-based CSRF protection

```php
// Generate token
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// In form
echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';

// Validate on submit
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    die('CSRF token validation failed');
}
```

### 3. Rate Limiting

**Current Status:** ❌ Not implemented

**Recommendation:** Implement rate limiting for forecast generation

```php
function check_rate_limit($action, $max_requests = 5, $time_window = 3600) {
    $key = $action . '_' . $_SERVER['REMOTE_ADDR'];
    $limit_file = "logs/rate_limit_" . md5($key) . ".txt";
    
    if (file_exists($limit_file)) {
        $data = json_decode(file_get_contents($limit_file), true);
        if (time() - $data['first_request'] < $time_window) {
            if ($data['count'] >= $max_requests) {
                return false;
            }
            $data['count']++;
        } else {
            $data = ['first_request' => time(), 'count' => 1];
        }
    } else {
        $data = ['first_request' => time(), 'count' => 1];
    }
    
    file_put_contents($limit_file, json_encode($data));
    return true;
}
```

### 4. SQL Injection Prevention

**Current Status:** ✅ Not vulnerable (no database)

**Future:** When adding database, use prepared statements

```php
// DON'T DO THIS
$query = "SELECT * FROM users WHERE id = " . $_GET['id'];

// DO THIS
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_GET['id']]);
```

### 5. Cross-Site Scripting (XSS) Prevention

**Current Status:** ✅ Mostly implemented

```php
// Always escape user input for HTML output
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');

// For JSON responses
header('Content-Type: application/json');
echo json_encode(['message' => $user_message]);

// Never use unescaped user input in HTML
// BAD: echo "<p>" . $_POST['message'] . "</p>";
// GOOD: echo "<p>" . htmlspecialchars($_POST['message']) . "</p>";
```

### 6. Secure Configuration

**Current Status:** ⚠️ Partially implemented

**Recommendations:**

```php
// php.ini settings for security
display_errors = Off              // Don't show errors to users
log_errors = On                   // Log errors instead
error_log = /var/log/php_errors.log
expose_php = Off                  // Don't advertise PHP version
magic_quotes_gpc = Off            // Disable magic quotes

session.cookie_httponly = On      // Prevent JavaScript access to cookies
session.cookie_secure = On        // Only transmit over HTTPS
session.cookie_samesite = Strict  // CSRF protection
```

### 7. File System Security

**Current Status:** ⚠️ Basic permissions

**Recommendations:**

```bash
# Set proper permissions (Windows)
icacls C:\xampp\htdocs\ncr_forecast /grant:r SYSTEM:F /T
icacls C:\xampp\htdocs\ncr_forecast /grant:r "IUSR":RX /T
icacls C:\xampp\htdocs\ncr_forecast\uploads /grant:r "IUSR":F /T

# Prevent script execution in upload directory
# In .htaccess (if using Apache with mod_rewrite)
<Directory "uploads">
    php_flag engine off
    AddType text/plain .php .phtml .php3 .php4 .php5 .php6 .php7 .phps .pht .phar
</Directory>
```

### 8. Data Privacy & Encryption

**Current Status:** ⚠️ No encryption

**Recommendations for sensitive data:**

```php
// Encrypt sensitive data
$encryption_key = getenv('ENCRYPTION_KEY');  // Store in environment
$plaintext = "sensitive data";
$encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $encryption_key, 0, $iv);

// Store: base64_encode($iv . $encrypted)

// Decrypt
$data = base64_decode($encrypted_data);
$iv = substr($data, 0, 16);
$encrypted = substr($data, 16);
$plaintext = openssl_decrypt($encrypted, 'AES-256-CBC', $encryption_key, 0, $iv);
```

### 9. Secure Communication (HTTPS)

**Current Status:** ❌ Production requires HTTPS

**Recommendation for production:**

```apache
# Force HTTPS redirection
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Strict Transport Security header
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

### 10. Dependency Security

**Current Status:** ✅ Pinned versions in requirements.txt

**Recommendations:**

```bash
# Regular security audits
pip audit

# Check for vulnerable packages
pip-audit --fix

# Keep dependencies updated (test first!)
pip list --outdated
```

## Security Checklist

### Before Production Deployment

- [ ] Change default admin password
- [ ] Enable HTTPS/SSL certificates
- [ ] Implement user authentication
- [ ] Add CSRF token protection
- [ ] Set up rate limiting
- [ ] Configure proper file permissions
- [ ] Review and harden php.ini settings
- [ ] Set up Web Application Firewall (WAF)
- [ ] Enable access logging
- [ ] Create regular backup procedures
- [ ] Document security procedures for team
- [ ] Perform security penetration testing
- [ ] Set up intrusion detection monitoring

### Ongoing Security

- [ ] Review logs weekly for suspicious activity
- [ ] Monthly security updates (OS, PHP, dependencies)
- [ ] Quarterly penetration testing
- [ ] Regular backup testing (monthly)
- [ ] Update encryption keys annually
- [ ] Review user access rights quarterly

## Incident Response Plan

### If Unauthorized Access is Suspected

1. **Immediate Actions** (First 30 minutes)
   - Disable the affected account
   - Review access logs: `logs/app.log`
   - Check file modification times
   - Document all findings

2. **Investigation** (30 minutes - 2 hours)
   - Identify what data was accessed
   - Review forecast history
   - Check for modified configuration files
   - Analyze upload directory for malicious files

3. **Recovery** (2-24 hours)
   - Restore from clean backup
   - Verify integrity of restored data
   - Update all passwords
   - Patch any vulnerabilities exploited
   - Notify relevant stakeholders

4. **Post-Incident** (24+ hours)
   - Conduct root cause analysis
   - Implement preventive measures
   - Update security policies
   - Provide security awareness training

## Compliance Considerations

### Data Protection (if handling personally identifiable information)

- Implement data minimization (only collect needed data)
- Maintain detailed audit logs
- Enable data encryption
- Support data deletion requests
- Conduct privacy impact assessments

### Educational Institution Standards

- FERPA compliance (Family Educational Rights and Privacy Act)
- Student data protection policies
- Access control and accountability
- Regular security assessments

## Third-Party Library Security

### Current Dependencies License & Security Status

```
pandas (2.0.3) - BSD License - Well-maintained
prophet (1.1.5) - MIT License - Actively maintained by Facebook
reportlab (4.0.4) - BSD License - Well-maintained
pytz (2023.3) - MIT License - Well-maintained
numpy (1.24.3) - BSD License - Well-maintained
```

**Security Monitoring:**
- Monitor each library's security advisories
- Use `pip-audit` for vulnerability scanning
- Set up GitHub security alerts for dependencies

## Summary

The system currently has:
✅ Good: Input validation, error handling, basic access control
⚠️ Needs Improvement: Authentication, CSRF protection, rate limiting
❌ Missing: HTTPS/encryption, advanced monitoring

**For Development/Testing:** Current security is adequate
**For Production:** Implement additional measures in "Recommendations" section

## Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Guide](https://www.php.net/manual/en/security.php)
- [CWE/SANS Top 25](https://cwe.mitre.org/top25/)
