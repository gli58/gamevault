<?php
require_once '../config.php';

// Check if user is admin
if (!is_admin()) {
    redirect('login.php');
}

$action = $_GET['action'] ?? 'list';
$user_id = isset($_GET['id']) && validate_id($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' || $action === 'edit') {
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'] ?? '';
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        
        // Validation
        if (empty($username) || empty($email)) {
            $error = 'Username and email are required.';
        } elseif (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters long.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($action === 'create' && empty($password)) {
            $error = 'Password is required for new users.';
        } elseif (!empty($password) && strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            // Check for duplicate username/email
            $check_sql = "SELECT id FROM users WHERE (username = :username OR email = :email)" . ($action === 'edit' ? " AND id != :id" : "");
            $check_stmt = $pdo->prepare($check_sql);
            $check_params = [':username' => $username, ':email' => $email];
            if ($action === 'edit') {
                $check_params[':id'] = $user_id;
            }
            $check_stmt->execute($check_params);
            
            if ($check_stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                if ($action === 'create') {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO users (username, password, email, is_admin) VALUES (:username, :password, :email, :is_admin)";
                    $params = [
                        ':username' => $username,
                        ':password' => $hashed_password,
                        ':email' => $email,
                        ':is_admin' => $is_admin
                    ];
                    
                    if ($pdo->prepare($sql)->execute($params)) {
                        $message = 'User created successfully!';
                        $action = 'list';
                    } else {
                        $error = 'Error creating user.';
                    }
                } elseif ($action === 'edit' && $user_id > 0) {
                    // Prevent removing admin status from current user
                    if ($user_id == $_SESSION['user_id'] && !$is_admin) {
                        $error = 'You cannot remove admin status from your own account.';
                    } else {
                        if (!empty($password)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $sql = "UPDATE users SET username = :username, password = :password, email = :email, is_admin = :is_admin WHERE id = :id";
                            $params = [
                                ':username' => $username,
                                ':password' => $hashed_password,
                                ':email' => $email,
                                ':is_admin' => $is_admin,
                                ':id' => $user_id
                            ];
                        } else {
                            $sql = "UPDATE users SET username = :username, email = :email, is_admin = :is_admin WHERE id = :id";
                            $params = [
                                ':username' => $username,
                                ':email' => $email,
                                ':is_admin' => $is_admin,
                                ':id' => $user_id
                            ];
                        }
                        
                        if ($pdo->prepare($sql)->execute($params)) {
                            $message = 'User updated successfully!';
                            $action = 'list';
                        } else {
                            $error = 'Error updating user.';
                        }
                    }
                }
            }
        }
    } elseif ($action === 'delete' && $user_id > 0) {
        // Prevent deleting current user
        if ($user_id == $_SESSION['user_id']) {
            $error = 'You cannot delete your own account.';
        } else {
            $delete_sql = "DELETE FROM users WHERE id = :id";
            if ($pdo->prepare($delete_sql)->execute([':id' => $user_id])) {
                $message = 'User deleted successfully!';
            } else {
                $error = 'Error deleting user.';
            }
        }
        $action = 'list';
    }
}

// Get user data for editing
$user = null;
if ($action === 'edit' && $user_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = 'User not found.';
        $action = 'list';
    }
}

// Get users list with comment counts
if ($action === 'list') {
    $users_sql = "SELECT u.*, COUNT(c.id) as comment_count 
                  FROM users u 
                  LEFT JOIN comments c ON u.id = c.user_id 
                  GROUP BY u.id 
                  ORDER BY u.created_at DESC";
    $users = $pdo->query($users_sql)->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - <?= SITE_NAME ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #007bff, #6610f2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar text-white p-3" style="width: 250px;">
            <div class="text-center mb-4">
                <h4><i class="fas fa-shield-alt me-2"></i>Admin Panel</h4>
                <small>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></small>
            </div>
            
            <nav class="nav flex-column">
                <a class="nav-link text-white" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a class="nav-link text-white" href="games.php">
                    <i class="fas fa-gamepad me-2"></i>Manage Games
                </a>
                <a class="nav-link text-white" href="categories.php">
                    <i class="fas fa-tags me-2"></i>Manage Categories
                </a>
                <a class="nav-link text-white active" href="users.php">
                    <i class="fas fa-users me-2"></i>Manage Users
                </a>
                <a class="nav-link text-white" href="comments.php">
                    <i class="fas fa-comments me-2"></i>Moderate Comments
                </a>
                <hr class="text-white">
                <a class="nav-link text-white" href="../index.php">
                    <i class="fas fa-home me-2"></i>View Site
                </a>
                <a class="nav-link text-white" href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="flex-grow-1">
            <!-- Header -->
            <header class="bg-white shadow-sm border-bottom p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="h3 mb-0">
                        <?= $action === 'create' ? 'Add New User' : ($action === 'edit' ? 'Edit User' : 'Manage Users') ?>
                    </h1>
                    <?php if ($action === 'list'): ?>
                        <a href="?action=create" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>Add New User
                        </a>
                    <?php endif; ?>
                </div>
            </header>
            
            <!-- Content -->
            <div class="p-4">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($action === 'create' || $action === 'edit'): ?>
                    <!-- User Form -->
                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="username" class="form-label">Username *</label>
                                                    <input type="text" class="form-control" id="username" name="username" 
                                                           value="<?= htmlspecialchars($user['username'] ?? '') ?>" 
                                                           required minlength="3" maxlength="50">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">Email Address *</label>
                                                    <input type="email" class="form-control" id="email" name="email" 
                                                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                                                           required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="password" class="form-label">
                                                Password <?= $action === 'create' ? '*' : '(leave blank to keep current)' ?>
                                            </label>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   <?= $action === 'create' ? 'required' : '' ?> minlength="6">
                                            <div class="form-text">
                                                <?= $action === 'create' ? 'Must be at least 6 characters long.' : 'Only enter a password if you want to change it.' ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="is_admin" name="is_admin" 
                                                       <?= ($user['is_admin'] ?? 0) ? 'checked' : '' ?>
                                                       <?= ($action === 'edit' && $user_id == $_SESSION['user_id']) ? 'disabled' : '' ?>>
                                                <label class="form-check-label" for="is_admin">
                                                    Administrator Account
                                                </label>
                                                <div class="form-text">
                                                    Admin users can manage games, categories, users, and moderate comments.
                                                    <?php if ($action === 'edit' && $user_id == $_SESSION['user_id']): ?>
                                                        <br><strong>Note:</strong> You cannot remove admin status from your own account.
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>
                                                <?= $action === 'create' ? 'Create User' : 'Update User' ?>
                                            </button>
                                            <a href="users.php" class="btn btn-secondary">
                                                <i class="fas fa-times me-2"></i>Cancel
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Users List -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Users List</h5>
                        </div>
                        
                        <div class="card-body p-0">
                            <?php if (empty($users)): ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No users found.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>User</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Comments</th>
                                                <th>Joined</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $u): ?>
                                                <tr <?= $u['id'] == $_SESSION['user_id'] ? 'class="table-info"' : '' ?>>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="user-avatar me-3">
                                                                <?= strtoupper(substr($u['username'], 0, 2)) ?>
                                                            </div>
                                                            <div>
                                                                <strong><?= htmlspecialchars($u['username']) ?></strong>
                                                                <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                                                    <span class="badge bg-info ms-2">You</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if ($u['is_admin']): ?>
                                                            <span class="badge bg-warning">Administrator</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">User</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($u['comment_count'] > 0): ?>
                                                            <span class="badge bg-primary"><?= $u['comment_count'] ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">0</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= date('M j, Y', strtotime($u['created_at'])) ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="?action=edit&id=<?= $u['id'] ?>" 
                                                               class="btn btn-outline-primary" 
                                                               title="Edit User">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                                <button type="button" class="btn btn-outline-danger" 
                                                                        onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')"
                                                                        title="Delete User">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="button" class="btn btn-outline-secondary" 
                                                                        disabled title="Cannot delete your own account">
                                                                    <i class="fas fa-lock"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- User Statistics -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>User Statistics</h6>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $admin_count = array_filter($users, function($u) { return $u['is_admin']; });
                                    $regular_count = count($users) - count($admin_count);
                                    $active_users = array_filter($users, function($u) { return $u['comment_count'] > 0; });
                                    ?>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <h4 class="text-primary"><?= count($users) ?></h4>
                                            <small class="text-muted">Total Users</small>
                                        </div>
                                        <div class="col-4">
                                            <h4 class="text-warning"><?= count($admin_count) ?></h4>
                                            <small class="text-muted">Admins</small>
                                        </div>
                                        <div class="col-4">
                                            <h4 class="text-success"><?= count($active_users) ?></h4>
                                            <small class="text-muted">Active</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security Notes</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="mb-0 small">
                                        <li>All passwords are securely hashed using PHP's password_hash()</li>
                                        <li>Admin users have full access to all system functions</li>
                                        <li>You cannot delete or demote your own admin account</li>
                                        <li>Regular users can only post comments on games</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the user <strong id="deleteUsername"></strong>?</p>
                    <p class="text-danger small">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        This action cannot be undone and will remove all comments made by this user.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;" id="deleteForm">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete User
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Delete confirmation
        function confirmDelete(userId, username) {
            document.getElementById('deleteUsername').textContent = username;
            document.getElementById('deleteForm').action = '?action=delete&id=' + userId;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>