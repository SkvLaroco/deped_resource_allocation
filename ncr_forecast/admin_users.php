<?php
// Password strength validation function
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "At least 8 characters long";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "At least one uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "At least one lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "At least one number";
    }
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errors[] = "At least one special character";
    }
    
    return $errors;
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];

        if (empty($username) || empty($password)) {
            $message = '<div class="error">❌ Username and password are required</div>';
        } else {
            $passwordErrors = validatePasswordStrength($password);
            if (!empty($passwordErrors)) {
                $message = '<div class="error">❌ Password does not meet requirements:<br>• ' . implode('<br>• ', $passwordErrors) . '</div>';
            } else {
                try {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $role]);
                    $message = '<div class="success">✅ User added successfully</div>';
                    log_message('INFO', "Admin {$_SESSION['user_id']} added user: $username");
                } catch (Exception $e) {
                    $message = '<div class="error">❌ Error adding user: ' . $e->getMessage() . '</div>';
                }
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        if ($user_id !== $_SESSION['user_id']) { // Prevent self-deletion
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = '<div class="success">✅ User deleted successfully</div>';
                log_message('INFO', "Admin {$_SESSION['user_id']} deleted user ID: $user_id");
            } catch (Exception $e) {
                $message = '<div class="error">❌ Error deleting user: ' . $e->getMessage() . '</div>';
            }
        } else {
            $message = '<div class="error">❌ Cannot delete your own account</div>';
        }
    } elseif (isset($_POST['reset_password'])) {
        $user_id = (int)$_POST['user_id'];
        $new_password = $_POST['new_password'];

        if (empty($new_password)) {
            $message = '<div class="error">❌ New password is required</div>';
        } else {
            $passwordErrors = validatePasswordStrength($new_password);
            if (!empty($passwordErrors)) {
                $message = '<div class="error">❌ Password does not meet requirements:<br>• ' . implode('<br>• ', $passwordErrors) . '</div>';
            } else {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    $message = '<div class="success">✅ Password reset successfully</div>';
                    log_message('INFO', "Admin {$_SESSION['user_id']} reset password for user ID: $user_id");
                } catch (Exception $e) {
                    $message = '<div class="error">❌ Error resetting password: ' . $e->getMessage() . '</div>';
                }
            }
        }
    }
}

// Get all users
try {
    $stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY username");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
}
?>

<div class="admin-section">
    <h2>👥 User Management</h2>
    <?php echo $message; ?>

    <!-- Add User Form -->
    <div class="card">
        <h3>Add New User</h3>
        <form method="POST" class="admin-form">
            <div class="form-row">
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" name="password" id="add_password" required>
                    <div id="password-strength" class="password-strength">
                        <div class="strength-meter">
                            <div class="strength-bar" id="add_strength_bar"></div>
                        </div>
                        <div class="strength-text" id="add_strength_text">Enter password to check strength</div>
                    </div>
                    <div class="password-requirements">
                        <strong>Password Requirements:</strong>
                        <ul>
                            <li id="req-length">• At least 8 characters</li>
                            <li id="req-uppercase">• At least one uppercase letter</li>
                            <li id="req-lowercase">• At least one lowercase letter</li>
                            <li id="req-number">• At least one number</li>
                            <li id="req-special">• At least one special character</li>
                        </ul>
                    </div>
                </div>
                <div class="form-group">
                    <label>Role:</label>
                    <select name="role" required>
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" name="add_user" class="btn-primary">Add User</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Users List -->
    <div class="card">
        <h3>Current Users</h3>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><span class="role-badge role-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                        <td>
                            <button onclick="showResetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="btn-secondary btn-small">Reset Password</button>
                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <button onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="btn-danger btn-small">Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3>Reset Password</h3>
        <form method="POST" id="resetForm">
            <input type="hidden" name="user_id" id="resetUserId">
            <div class="form-group">
                <label>Username: <span id="resetUsername"></span></label>
            </div>
            <div class="form-group">
                <label>New Password:</label>
                <input type="password" name="new_password" id="reset_password" required>
                <div id="reset-password-strength" class="password-strength">
                    <div class="strength-meter">
                        <div class="strength-bar" id="reset_strength_bar"></div>
                    </div>
                    <div class="strength-text" id="reset_strength_text">Enter password to check strength</div>
                </div>
                <div class="password-requirements">
                    <strong>Password Requirements:</strong>
                    <ul>
                        <li id="reset-req-length">• At least 8 characters</li>
                        <li id="reset-req-uppercase">• At least one uppercase letter</li>
                        <li id="reset-req-lowercase">• At least one lowercase letter</li>
                        <li id="reset-req-number">• At least one number</li>
                        <li id="reset-req-special">• At least one special character</li>
                    </ul>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" onclick="closeModal()" class="btn-secondary">Cancel</button>
                <button type="submit" name="reset_password" class="btn-primary">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete user "<span id="deleteUsername"></span>"?</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="user_id" id="deleteUserId">
            <div class="form-actions">
                <button type="button" onclick="closeModal()" class="btn-secondary">Cancel</button>
                <button type="submit" name="delete_user" class="btn-danger">Delete User</button>
            </div>
        </form>
    </div>
</div>

<script>
function showResetPassword(userId, username) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUsername').textContent = username;
    document.getElementById('resetPasswordModal').style.display = 'flex';
    
    // Clear and reinitialize the password field
    const resetPasswordField = document.getElementById('reset_password');
    resetPasswordField.value = '';
    checkPasswordStrength('', 'reset');
}

function confirmDelete(userId, username) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUsername').textContent = username;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('resetPasswordModal').style.display = 'none';
    document.getElementById('deleteModal').style.display = 'none';
    
    // Clear password field when modal closes
    const resetPasswordField = document.getElementById('reset_password');
    if (resetPasswordField) {
        resetPasswordField.value = '';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = 'none';
    }
}
// Password strength validation
function checkPasswordStrength(password, prefix = 'add') {
    const requirements = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/.test(password)
    };

    // Update requirement list
    document.getElementById(`${prefix}-req-length`).className = requirements.length ? 'valid' : 'invalid';
    document.getElementById(`${prefix}-req-uppercase`).className = requirements.uppercase ? 'valid' : 'invalid';
    document.getElementById(`${prefix}-req-lowercase`).className = requirements.lowercase ? 'valid' : 'invalid';
    document.getElementById(`${prefix}-req-number`).className = requirements.number ? 'valid' : 'invalid';
    document.getElementById(`${prefix}-req-special`).className = requirements.special ? 'valid' : 'invalid';

    // Calculate strength
    const validCount = Object.values(requirements).filter(Boolean).length;
    const strengthBar = document.getElementById(`${prefix}_strength_bar`);
    const strengthText = document.getElementById(`${prefix}_strength_text`);

    strengthBar.className = 'strength-bar';

    if (password.length === 0) {
        strengthText.textContent = 'Enter password to check strength';
        strengthBar.style.width = '0%';
        return false;
    } else if (validCount < 3) {
        strengthBar.classList.add('strength-weak');
        strengthText.textContent = 'Weak password';
    } else if (validCount < 5) {
        strengthBar.classList.add('strength-medium');
        strengthText.textContent = 'Medium strength';
    } else {
        strengthBar.classList.add('strength-strong');
        strengthText.textContent = 'Strong password';
    }

    return validCount === 5;
}

// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Initialize password strength indicators
    checkPasswordStrength('', 'add');
    
    // Initialize reset password if element exists (even if modal is hidden)
    if (document.getElementById('reset_password')) {
        checkPasswordStrength('', 'reset');
    }

    // Add user password validation
    const addPassword = document.getElementById('add_password');
    if (addPassword) {
        addPassword.addEventListener('input', function() {
            checkPasswordStrength(this.value, 'add');
        });
    }

    // Reset password validation
    const resetPassword = document.getElementById('reset_password');
    if (resetPassword) {
        resetPassword.addEventListener('input', function() {
            checkPasswordStrength(this.value, 'reset');
        });
    }
});
</script>