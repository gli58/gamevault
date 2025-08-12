<?php
require_once 'config.php';

// Get and validate game ID
if (!isset($_GET['id']) || !validate_id($_GET['id'])) {
    redirect('index.php');
}

$game_id = (int)$_GET['id'];

// Get game details
$sql = "SELECT g.*, c.name as category_name 
        FROM games g 
        LEFT JOIN categories c ON g.category_id = c.id 
        WHERE g.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $game_id]);
$game = $stmt->fetch();

if (!$game) {
    redirect('index.php');
}

// Handle comment submission
$comment_error = '';
$comment_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    if (!is_logged_in()) {
        $comment_error = 'You must be logged in to post a comment.';
    } else {
        $comment = sanitize_input($_POST['comment']);
        $user_id = $_SESSION['user_id'];
        
        if (empty($comment)) {
            $comment_error = 'Comment cannot be empty.';
        } elseif (strlen($comment) > 1000) {
            $comment_error = 'Comment is too long (max 1000 characters).';
        } else {
            //  Check image CAPTCHA if not admin
            if (!is_admin()) {
                $captcha_input = isset($_POST['captcha_code']) ? strtoupper(trim($_POST['captcha_code'])) : '';
                $expected_captcha = isset($_SESSION['captcha_code']) ? strtoupper($_SESSION['captcha_code']) : '';
                
                if (empty($captcha_input) || $captcha_input !== $expected_captcha) {
                    $comment_error = 'CAPTCHA verification failed. Please try again.';
                } else {
                    // Clear captcha from session after successful verification
                    unset($_SESSION['captcha_code']);
                }
            }
            
            if (empty($comment_error)) {
                $insert_sql = "INSERT INTO comments (game_id, user_id, comment) VALUES (:game_id, :user_id, :comment)";
                $insert_stmt = $pdo->prepare($insert_sql);
                
                if ($insert_stmt->execute([':game_id' => $game_id, ':user_id' => $user_id, ':comment' => $comment])) {
                    $comment_success = 'Comment posted successfully!';
                } else {
                    $comment_error = 'Error posting comment. Please try again.';
                }
            }
        }
    }
}

// Get comments
$comments_sql = "SELECT c.*, u.username 
                FROM comments c 
                LEFT JOIN users u ON c.user_id = u.id 
                WHERE c.game_id = :game_id 
                ORDER BY c.created_at DESC";
$comments_stmt = $pdo->prepare($comments_sql);
$comments_stmt->execute([':game_id' => $game_id]);
$comments = $comments_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($game['title']) ?> - <?= SITE_NAME ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .rating-stars {
            color: #ffc107;
        }
        .comment-card {
            border-left: 4px solid #007bff;
        }
        .captcha-container {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            background: #f8f9fa;
        }
        .captcha-image {
            border: 1px solid #ccc;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-gamepad me-2"></i>
                <?= SITE_NAME ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <?php if (is_logged_in()): ?>
                    <span class="navbar-text me-3">
                        Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!
                        <?php if (is_admin()): ?>
                            <span class="badge bg-warning">Admin</span>
                        <?php endif; ?>
                    </span>
                    <?php if (is_admin()): ?>
                        <a href="admin/dashboard.php" class="btn btn-outline-light btn-sm me-2">Admin Panel</a>
                    <?php endif; ?>
                    <a href="auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn btn-outline-light btn-sm me-2">Login</a>
                    <a href="auth/register.php" class="btn btn-primary btn-sm">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Game Details -->
    <div class="container my-5">
        <div class="row">
            <!-- Game Image -->
            <div class="col-lg-4 mb-4">
                <?php if ($game['image_path'] && file_exists($game['image_path'])): ?>
                    <img src="<?= htmlspecialchars($game['image_path']) ?>" 
                         class="img-fluid rounded shadow" 
                         alt="<?= htmlspecialchars($game['title']) ?>">
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center bg-light rounded shadow" 
                         style="height: 400px;">
                        <i class="fas fa-gamepad fa-5x text-muted"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Game Information -->
            <div class="col-lg-8">
                <div class="mb-3">
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Games
                    </a>
                </div>
                
                <h1 class="display-5 mb-3"><?= htmlspecialchars($game['title']) ?></h1>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6><i class="fas fa-building text-primary me-2"></i>Developer</h6>
                        <p><?= htmlspecialchars($game['developer']) ?></p>
                        
                        <h6><i class="fas fa-calendar text-primary me-2"></i>Release Date</h6>
                        <p><?= date('F j, Y', strtotime($game['release_date'])) ?></p>
                    </div>
                    
                    <div class="col-md-6">
                        <?php if ($game['category_name']): ?>
                            <h6><i class="fas fa-tag text-primary me-2"></i>Category</h6>
                            <p>
                                <a href="index.php?category=<?= $game['category_id'] ?>" class="badge bg-primary text-decoration-none">
                                    <?= htmlspecialchars($game['category_name']) ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        
                        <h6><i class="fas fa-star text-primary me-2"></i>Rating</h6>
                        <div class="rating-stars mb-2">
                            <?php
                            $rating = (float)$game['rating'];
                            for ($i = 1; $i <= 5; $i++) {
                                if ($rating >= $i) {
                                    echo '<i class="fas fa-star"></i>';
                                } elseif ($rating >= $i - 0.5) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                            <span class="ms-2 text-muted fs-6"><?= number_format($rating, 1) ?>/5</span>
                        </div>
                    </div>
                </div>
                
                <h6><i class="fas fa-info-circle text-primary me-2"></i>Description</h6>
                <p class="lead"><?= nl2br(strip_tags($game['description'], '<p><br><strong><em><ul><ol><li>')) ?></p>
            </div>
        </div>
    </div>

    <!-- Comments Section -->
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-comments me-2"></i>Comments (<?= count($comments) ?>)</h5>
                    </div>
                    
                    <div class="card-body">
                        <!-- Comment Form -->
                        <?php if (is_logged_in()): ?>
                            <form method="POST" class="mb-4">
                                <div class="mb-3">
                                    <label for="comment" class="form-label">Add Your Comment</label>
                                    <textarea class="form-control" id="comment" name="comment" rows="4" 
                                              placeholder="Share your thoughts about this game..." 
                                              maxlength="1000" required></textarea>
                                    <div class="form-text">Maximum 1000 characters</div>
                                </div>
                                
                                <!--  Image CAPTCHA for non-admin users -->
                                <?php if (!is_admin()): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Human Verification</label>
                                        <div class="captcha-container">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <img src="captcha.php" alt="CAPTCHA" class="captcha-image mb-2" id="captcha-image" onclick="refreshCaptcha()" title="Click to refresh">
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-refresh me-1"></i>
                                                        Click image to refresh
                                                    </small>
                                                </div>
                                                <div class="col-md-6">
                                                    <input type="text" class="form-control" name="captcha_code" 
                                                           placeholder="Enter code from image" 
                                                           maxlength="5" required autocomplete="off">
                                                    <small class="form-text text-muted">
                                                        Enter the 5-character code shown in the image
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($comment_error)): ?>
                                    <div class="alert alert-danger"><?= htmlspecialchars($comment_error) ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($comment_success)): ?>
                                    <div class="alert alert-success"><?= htmlspecialchars($comment_success) ?></div>
                                <?php endif; ?>
                                
                                <button type="submit" name="submit_comment" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Post Comment
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <a href="auth/login.php">Login</a> or <a href="auth/register.php">register</a> to post comments.
                            </div>
                        <?php endif; ?>
                        
                        <!-- Comments List -->
                        <?php if (empty($comments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No comments yet. Be the first to share your thoughts!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="card comment-card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0">
                                                <i class="fas fa-user-circle me-2"></i>
                                                <?= htmlspecialchars($comment['username'] ?? 'Anonymous') ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?= date('M j, Y \a\t g:i A', strtotime($comment['created_at'])) ?>
                                            </small>
                                        </div>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Games Section -->
    <div class="container my-5">
        <h4 class="mb-4">More Games Like This</h4>
        <div class="row">
            <?php
            // Get related games from same category
            $related_sql = "SELECT * FROM games 
                           WHERE category_id = :category_id AND id != :current_id 
                           ORDER BY rating DESC 
                           LIMIT 3";
            $related_stmt = $pdo->prepare($related_sql);
            $related_stmt->execute([':category_id' => $game['category_id'], ':current_id' => $game_id]);
            $related_games = $related_stmt->fetchAll();
            ?>
            
            <?php if (empty($related_games)): ?>
                <div class="col-12">
                    <p class="text-muted">No related games found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($related_games as $related_game): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <?php if ($related_game['image_path'] && file_exists($related_game['image_path'])): ?>
                                <img src="<?= htmlspecialchars($related_game['image_path']) ?>" 
                                     class="card-img-top" style="height: 150px; object-fit: cover;" 
                                     alt="<?= htmlspecialchars($related_game['title']) ?>">
                            <?php else: ?>
                                <div class="card-img-top d-flex align-items-center justify-content-center" 
                                     style="height: 150px; background: #f8f9fa;">
                                    <i class="fas fa-gamepad fa-2x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <h6 class="card-title"><?= htmlspecialchars($related_game['title']) ?></h6>
                                <div class="rating-stars mb-2">
                                    <?php
                                    $related_rating = (float)$related_game['rating'];
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($related_rating >= $i) {
                                            echo '<i class="fas fa-star"></i>';
                                        } elseif ($related_rating >= $i - 0.5) {
                                            echo '<i class="fas fa-star-half-alt"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                    <span class="ms-1 small text-muted"><?= number_format($related_rating, 1) ?></span>
                                </div>
                                <a href="game.php?id=<?= $related_game['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="text-center">
                <p class="mb-0">&copy; 2025 <?= SITE_NAME ?>. Manitoba's Gaming Community.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    
    <script>
        //  Refresh CAPTCHA image
        function refreshCaptcha() {
            const captchaImg = document.getElementById('captcha-image');
            captchaImg.src = 'captcha.php?' + Math.random();
        }
        
        // Auto-refresh CAPTCHA if form submission fails
        <?php if (!empty($comment_error) && !is_admin()): ?>
        refreshCaptcha();
        <?php endif; ?>
    </script>
</body>
</html>