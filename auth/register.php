<?php
require_once '../config.php';

$error = '';
$success = '';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('../index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long.';
    } elseif (strlen($username) > 50) {
        $error = 'Username must be less than 50 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Check if username or email already exists
        $check_sql = "SELECT id FROM users WHERE username = :username OR email = :email";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([':username' => $username, ':email' => $email]);
        
        if ($check_stmt->fetch()) {
            $error = 'Username or email already exists.';
        } else {
            // Create new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_sql = "INSERT INTO users (username, password, email, is_admin) VALUES (:username, :password, :email, 0)";
            $insert_stmt = $pdo->prepare($insert_sql);
            
            if ($insert_stmt->execute([':username' => $username, ':password' => $hashed_password, ':email' => $email])) {
                $success = 'Account created successfully! You can now log in.';
                
                // Auto login the user
                $user_id = $pdo->lastInsertId();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['is_admin'] = 0;
                
                // Redirect to homepage with delay
                header("refresh:2;url=../index.php");
            } else {
                $error = 'Error creating account. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= SITE_NAME ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .register-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .register-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        .password-strength {
            height: 3px;
            transition: all 0.3s;
        }
    </style>
</head>
<body>
    <div class="register-container d-flex align-items-center justify-content-center py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card register-card shadow-lg">
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <h2 class="mb-3">
                                    <i class="fas fa-user-plus text-primary me-2"></i>
                                    Join GameVault
                                </h2>
                                <p class="text-muted">Create your gaming community account</p>
                            </div>
                            
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?= htmlspecialchars($error) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?= htmlspecialchars($success) ?>
                                    <br><small>Redirecting you to the homepage...</small>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" id="registerForm">
                                <div class="mb-3">
                                    <label for="username" class="form-label">
                                        <i class="fas fa-user me-2"></i>Username *
                                    </label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                                           required minlength="3" maxlength="50" autocomplete="username">
                                    <div class="form-text">3-50 characters, letters, numbers, and underscores only</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope me-2"></i>Email Address *
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                           required autocomplete="email">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Password *
                                    </label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           required minlength="6" autocomplete="new-password">
                                    <div class="password-strength bg-light mt-1" id="passwordStrength"></div>
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Confirm Password *
                                    </label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           required autocomplete="new-password">
                                    <div class="invalid-feedback" id="passwordMismatch">
                                        Passwords do not match
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 mb-3">
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        <i class="fas fa-user-plus me-2"></i>Create Account
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center">
                                <p class="mb-2">
                                    <a href="../index.php" class="text-decoration-none">
                                        <i class="fas fa-home me-1"></i>Back to Home
                                    </a>
                                </p>
                                <p class="mb-0">
                                    Already have an account? 
                                    <a href="login.php" class="text-decoration-none">Sign in here</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 6) strength += 25;
            if (password.match(/[a-z]/)) strength += 25;
            if (password.match(/[A-Z]/)) strength += 25;
            if (password.match(/[0-9]/)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (strength <= 25) {
                strengthBar.className = 'password-strength bg-danger';
            } else if (strength <= 50) {
                strengthBar.className = 'password-strength bg-warning';
            } else if (strength <= 75) {
                strengthBar.className = 'password-strength bg-info';
            } else {
                strengthBar.className = 'password-strength bg-success';
            }
        });
        
        // Password confirmation validation
        function validatePasswords() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const mismatchMsg = document.getElementById('passwordMismatch');
            const submitBtn = document.getElementById('submitBtn');
            
            if (confirmPassword && password !== confirmPassword) {
                document.getElementById('confirm_password').classList.add('is-invalid');
                submitBtn.disabled = true;
            } else {
                document.getElementById('confirm_password').classList.remove('is-invalid');
                submitBtn.disabled = false;
            }
        }
        
        document.getElementById('password').addEventListener('input', validatePasswords);
        document.getElementById('confirm_password').addEventListener('input', validatePasswords);
    </script>
</body>
</html>