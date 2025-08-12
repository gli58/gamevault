<?php
require_once '../config.php';

$error = '';
$success = '';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(is_admin() ? '../admin/dashboard.php' : '../index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $sql = "SELECT id, username, password, email, is_admin FROM users WHERE username = :username";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'];
            
            $redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : (is_admin() ? '../admin/dashboard.php' : '../index.php');
            redirect($redirect_url);
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
    </style>
</head>
<body>
    <div class="login-container d-flex align-items-center justify-content-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="card login-card shadow-lg">
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <h2 class="mb-3">
                                    <i class="fas fa-gamepad text-primary me-2"></i>
                                    Login
                                </h2>
                                <p class="text-muted">Sign in to your GameVault account</p>
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
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">
                                        <i class="fas fa-user me-2"></i>Username
                                    </label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                                           required autocomplete="username">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Password
                                    </label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           required autocomplete="current-password">
                                </div>
                                
                                <div class="d-grid gap-2 mb-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
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
                                    Don't have an account? 
                                    <a href="register.php" class="text-decoration-none">Sign up here</a>
                                </p>
                            </div>
                            
                            <!-- Demo Credentials -->
                            <div class="mt-4 p-3 bg-light rounded">
                                <h6 class="mb-2">Demo Credentials:</h6>
                                <small class="text-muted">
                                    <strong>Admin:</strong> admin / password<br>
                                    <strong>User:</strong> Create a new account or use admin
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>