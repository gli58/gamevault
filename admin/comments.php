<?php
require_once '../config.php';

// Check if user is admin
if (!is_admin()) {
    redirect('login.php');
}

$action = $_GET['action'] ?? 'list';
$comment_id = isset($_GET['id']) && validate_id($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// Handle moderation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'delete' && $comment_id > 0) {
        $delete_sql = "DELETE FROM comments WHERE id = :id";
        if ($pdo->prepare($delete_sql)->execute([':id' => $comment_id])) {
            $message = 'Comment deleted successfully!';
        } else {
            $error = 'Error deleting comment.';
        }
        $action = 'list';
    } elseif ($action === 'disemvowel' && $comment_id > 0) {
        // Get current comment
        $stmt = $pdo->prepare("SELECT comment FROM comments WHERE id = :id");
        $stmt->execute([':id' => $comment_id]);
        $current_comment = $stmt->fetchColumn();
        
        if ($current_comment) {
            // Remove vowels from comment (disemvowel)
            $disemvoweled = preg_replace('/[aeiouAEIOU]/', '', $current_comment);
            
            $update_sql = "UPDATE comments SET comment = :comment WHERE id = :id";
            if ($pdo->prepare($update_sql)->execute([':comment' => $disemvoweled, ':id' => $comment_id])) {
                $message = 'Comment disemvoweled successfully!';
            } else {
                $error = 'Error disemvoweling comment.';
            }
        } else {
            $error = 'Comment not found.';
        }
        $action = 'list';
    }
}

// Get comments with game and user information
$filter = $_GET['filter'] ?? 'all';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build filter conditions
$where_conditions = [];
$params = [];

if ($filter === 'recent') {
    $where_conditions[] = "c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filter === 'flagged') {
    // For demonstration, we'll consider comments with certain words as flagged
    $where_conditions[] = "(c.comment LIKE '%spam%' OR c.comment LIKE '%bad%' OR LENGTH(c.comment) > 500)";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM comments c $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_comments = $count_stmt->fetch()['total'];
$total_pages = ceil($total_comments / $per_page);

// Get comments
$comments_sql = "SELECT c.*, g.title as game_title, u.username, u.email
                FROM comments c
                JOIN games g ON c.game_id = g.id
                LEFT JOIN users u ON c.user_id = u.id
                $where_clause
                ORDER BY c.created_at DESC
                LIMIT :offset, :per_page";

$comments_stmt = $pdo->prepare($comments_sql);
foreach ($params as $key => $value) {
    $comments_stmt->bindValue($key, $value);
}
$comments_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$comments_stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$comments_stmt->execute();
$comments = $comments_stmt->fetchAll();

// Get statistics
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$stats['recent'] = $pdo->query("SELECT COUNT(*) FROM comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$stats['today'] = $pdo->query("SELECT COUNT(*) FROM comments WHERE DATE(created_at) = CURDATE()")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderate Comments - <?= SITE_NAME ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
        }
        .comment-card {
            border-left: 4px solid #007bff;
        }
        .comment-flagged {
            border-left-color: #dc3545;
            background-color: #fff5f5;
        }
        .comment-content {
            max-height: 100px;
            overflow-y: auto;
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
                <a class="nav-link text-white" href="users.php">
                    <i class="fas fa-users me-2"></i>Manage Users
                </a>
                <a class="nav-link text-white active" href="comments.php">
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
                    <h1 class="h3 mb-0">Moderate Comments</h1>
                    
                    <!-- Filter Buttons -->
                    <div class="btn-group">
                        <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm">
                            All Comments
                        </a>
                        <a href="?filter=recent" class="btn <?= $filter === 'recent' ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm">
                            Recent (7 days)
                        </a>
                        <a href="?filter=flagged" class="btn <?= $filter === 'flagged' ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm">
                            Flagged
                        </a>
                    </div>
                </div>
            </header>
            
            <!-- Statistics -->
            <div class="p-4 pb-2">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card text-center border-primary">
                            <div class="card-body">
                                <i class="fas fa-comments fa-2x text-primary mb-2"></i>
                                <h4 class="mb-1"><?= $stats['total'] ?></h4>
                                <p class="mb-0 text-muted">Total Comments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center border-info">
                            <div class="card-body">
                                <i class="fas fa-clock fa-2x text-info mb-2"></i>
                                <h4 class="mb-1"><?= $stats['recent'] ?></h4>
                                <p class="mb-0 text-muted">Last 7 Days</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center border-success">
                            <div class="card-body">
                                <i class="fas fa-calendar-day fa-2x text-success mb-2"></i>
                                <h4 class="mb-1"><?= $stats['today'] ?></h4>
                                <p class="mb-0 text-muted">Today</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content -->
            <div class="px-4">
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
                
                <!-- Comments List -->
                <?php if (empty($comments)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Comments Found</h5>
                            <p class="text-muted">
                                <?php if ($filter === 'recent'): ?>
                                    No comments have been posted in the last 7 days.
                                <?php elseif ($filter === 'flagged'): ?>
                                    No flagged comments found.
                                <?php else: ?>
                                    No comments have been posted yet.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <?php 
                        $is_flagged = (strpos(strtolower($comment['comment']), 'spam') !== false || 
                                      strpos(strtolower($comment['comment']), 'bad') !== false || 
                                      strlen($comment['comment']) > 500);
                        ?>
                        <div class="card comment-card mb-3 <?= $is_flagged ? 'comment-flagged' : '' ?>">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0">
                                                <i class="fas fa-user-circle text-primary me-2"></i>
                                                <?= htmlspecialchars($comment['username'] ?? 'Anonymous User') ?>
                                                <?php if ($is_flagged): ?>
                                                    <span class="badge bg-danger ms-2">Flagged</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?= date('M j, Y \a\t g:i A', strtotime($comment['created_at'])) ?>
                                            </small>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong>On Game:</strong> 
                                            <a href="../game.php?id=<?= $comment['game_id'] ?>" target="_blank" class="text-decoration-none">
                                                <?= htmlspecialchars($comment['game_title']) ?>
                                                <i class="fas fa-external-link-alt ms-1"></i>
                                            </a>
                                        </div>
                                        
                                        <div class="comment-content">
                                            <p class="mb-0"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                                        </div>
                                        
                                        <?php if ($comment['email']): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-envelope me-1"></i>
                                                    <?= htmlspecialchars($comment['email']) ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-4 text-end">
                                        <div class="btn-group-vertical btn-group-sm w-100">
                                            <button type="button" class="btn btn-outline-warning mb-1" 
                                                    onclick="confirmDisemvowel(<?= $comment['id'] ?>, '<?= htmlspecialchars($comment['username'] ?? 'Anonymous', ENT_QUOTES) ?>')">
                                                <i class="fas fa-volume-mute me-2"></i>Disemvowel
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="confirmDelete(<?= $comment['id'] ?>, '<?= htmlspecialchars($comment['username'] ?? 'Anonymous', ENT_QUOTES) ?>')">
                                                <i class="fas fa-trash me-2"></i>Delete
                                            </button>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                Length: <?= strlen($comment['comment']) ?> chars
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Comments pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&filter=<?= $filter ?>">
                                            Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&filter=<?= $filter ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&filter=<?= $filter ?>">
                                            Next
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Comment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this comment by <strong id="deleteUsername"></strong>?</p>
                    <p class="text-danger small">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;" id="deleteForm">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Comment
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Disemvowel Confirmation Modal -->
    <div class="modal fade" id="disemvowelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Disemvowel Comment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to disemvowel this comment by <strong id="disemvowelUsername"></strong>?</p>
                    <p class="text-warning small">
                        <i class="fas fa-info-circle me-1"></i>
                        This will remove all vowels from the comment, making it difficult to read but still preserving the content.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;" id="disemvowelForm">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-volume-mute me-2"></i>Disemvowel
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Delete confirmation
        function confirmDelete(commentId, username) {
            document.getElementById('deleteUsername').textContent = username;
            document.getElementById('deleteForm').action = '?action=delete&id=' + commentId;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
        
        // Disemvowel confirmation
        function confirmDisemvowel(commentId, username) {
            document.getElementById('disemvowelUsername').textContent = username;
            document.getElementById('disemvowelForm').action = '?action=disemvowel&id=' + commentId;
            
            const modal = new bootstrap.Modal(document.getElementById('disemvowelModal'));
            modal.show();
        }
    </script>
</body>
</html>