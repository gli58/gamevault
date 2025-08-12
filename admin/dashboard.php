<?php
require_once '../config.php';

// Check if user is admin
if (!is_admin()) {
    redirect('login.php');
}

// Get dashboard statistics
$stats = [];

// Total games
$stats['total_games'] = $pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();

// Total users
$stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();

// Total comments
$stats['total_comments'] = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();

// Total categories
$stats['total_categories'] = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// Recent games (last 5)
$recent_games = $pdo->query("SELECT id, title, developer, created_at FROM games ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Recent comments (last 5)
$recent_comments = $pdo->query("
    SELECT c.id, c.comment, c.created_at, g.title as game_title, u.username 
    FROM comments c 
    JOIN games g ON c.game_id = g.id 
    LEFT JOIN users u ON c.user_id = u.id 
    ORDER BY c.created_at DESC 
    LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= SITE_NAME ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
        }
        .stat-card {
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
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
                <a class="nav-link text-white active" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a class="nav-link text-white" href="games.php">
                    <i class="fas fa-gamepad me-2"></i>Manage Games
                </a>
                <a class="nav-link text-white" href="categories.php">
                    <i class="fas fa-tags me-2"></i>Manage Categories
                </a>
                <a class="nav-link text-white" href="users.php">
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
                    <h1 class="h3 mb-0">Dashboard</h1>
                    <div>
                        <span class="text-muted">Last login: <?= date('M j, Y g:i A') ?></span>
                    </div>
                </div>
            </header>
            
            <!-- Dashboard Content -->
            <div class="p-4">
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-center border-0 shadow-sm bg-primary text-white">
                            <div class="card-body">
                                <i class="fas fa-gamepad fa-2x mb-2"></i>
                                <h4 class="mb-1"><?= $stats['total_games'] ?></h4>
                                <p class="mb-0">Total Games</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-center border-0 shadow-sm bg-success text-white">
                            <div class="card-body">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h4 class="mb-1"><?= $stats['total_users'] ?></h4>
                                <p class="mb-0">Registered Users</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-center border-0 shadow-sm bg-info text-white">
                            <div class="card-body">
                                <i class="fas fa-comments fa-2x mb-2"></i>
                                <h4 class="mb-1"><?= $stats['total_comments'] ?></h4>
                                <p class="mb-0">Total Comments</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-center border-0 shadow-sm bg-warning text-white">
                            <div class="card-body">
                                <i class="fas fa-tags fa-2x mb-2"></i>
                                <h4 class="mb-1"><?= $stats['total_categories'] ?></h4>
                                <p class="mb-0">Categories</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <a href="games.php?action=create" class="btn btn-primary btn-sm w-100">
                                            <i class="fas fa-plus me-2"></i>Add New Game
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="categories.php?action=create" class="btn btn-success btn-sm w-100">
                                            <i class="fas fa-plus me-2"></i>Add Category
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="users.php?action=create" class="btn btn-info btn-sm w-100">
                                            <i class="fas fa-user-plus me-2"></i>Add User
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="comments.php" class="btn btn-warning btn-sm w-100">
                                            <i class="fas fa-eye me-2"></i>Review Comments
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-gamepad me-2"></i>Recent Games
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_games)): ?>
                                    <p class="text-muted mb-0">No games added yet.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_games as $game): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($game['title']) ?></h6>
                                                    <small class="text-muted"><?= htmlspecialchars($game['developer']) ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted">
                                                        <?= date('M j', strtotime($game['created_at'])) ?>
                                                    </small>
                                                    <br>
                                                    <a href="games.php?action=edit&id=<?= $game['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        Edit
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-comments me-2"></i>Recent Comments
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_comments)): ?>
                                    <p class="text-muted mb-0">No comments yet.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_comments as $comment): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="mb-1">
                                                        <?= htmlspecialchars($comment['username'] ?? 'Anonymous') ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?= date('M j', strtotime($comment['created_at'])) ?>
                                                    </small>
                                                </div>
                                                <p class="mb-1 small">
                                                    <?= htmlspecialchars(substr($comment['comment'], 0, 60)) ?>
                                                    <?= strlen($comment['comment']) > 60 ? '...' : '' ?>
                                                </p>
                                                <small class="text-muted">
                                                    On: <?= htmlspecialchars($comment['game_title']) ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
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