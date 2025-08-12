<?php
require_once 'config.php';

// Get search parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'created_at';

// Validate sort parameter
$allowed_sorts = ['title', 'created_at', 'updated_at', 'rating'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'created_at';
}
// NEW: decide sort direction (title A→Z, others newest/highest first)
$direction = ($sort === 'title') ? 'ASC' : 'DESC';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 6;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(g.title LIKE :search OR g.description LIKE :search OR g.developer LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($category_filter > 0) {
    $where_conditions[] = "g.category_id = :category_id";
    $params[':category_id'] = $category_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM games g $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_games = $count_stmt->fetch()['total'];
$total_pages = ceil($total_games / $per_page);

// Get games
$sql = "SELECT g.*, c.name as category_name 
        FROM games g 
        LEFT JOIN categories c ON g.category_id = c.id 
        $where_clause 
        ORDER BY g.$sort $direction
        LIMIT :offset, :per_page";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$games = $stmt->fetchAll();

// Get categories for filter
$categories_sql = "SELECT * FROM categories ORDER BY name";
$categories = $pdo->query($categories_sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Manitoba's Premier Gaming Community</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .game-card {
            transition: transform 0.3s;
            height: 100%;
        }
        .game-card:hover {
            transform: translateY(-5px);
        }
        .rating-stars {
            color: #ffc107;
        }
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
        }
        .admin-link {
            position: fixed;
            top: 10px;
            right: 10px;
            opacity: 0.1;
            transition: opacity 0.3s;
        }
        .admin-link:hover {
            opacity: 1;
        }
        .game-thumb {
        width: 100%;
        height: 220px;
        object-fit: contain;
        object-position: center;
        background: #f8f9fa;
        padding: 6px;
        border-radius: .375rem;
        }
        .nav-btn {
        background: transparent;
        border: none;
        color: white; 
        font-weight: 500;
        padding: 6px 12px;
        border-radius: 6px;
        transition: all 0.3s ease;
        }
        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.15); 
            backdrop-filter: blur(6px); 
            -webkit-backdrop-filter: blur(6px); 
            color: white; 
        }
    </style>
</head>
<body>
    <!-- Admin Link (hidden) -->
    <div class="admin-link">
        <a href="admin/login.php" class="btn btn-dark btn-sm">
            <i class="fas fa-cog"></i>
        </a>
    </div>
    <!-- User Auth Links -->
    <div class="admin-link" style="top: 50px; opacity: 1;">
        <?php if (is_logged_in()): ?>
            <?php if (is_admin()): ?>
                <a href="admin/dashboard.php" class="nav-btn me-1">Admin Panel</a>
            <?php endif; ?>
            <span class="me-2 text-light">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="auth/logout.php" class="nav-btn">Logout</a>
        <?php else: ?>
            <a href="auth/login.php" class="nav-btn me-1">Login</a>
            <a href="auth/register.php" class="nav-btn">Register</a>
        <?php endif; ?>
    </div>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="display-4 mb-4">
                        <i class="fas fa-gamepad me-3"></i>
                        <?= SITE_NAME ?>
                    </h1>
                    <p class="lead mb-4">Manitoba's Premier Gaming Community - Discover, Review, and Share Your Gaming Experience</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <label for="search" class="form-label">Search Games</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Search by title, description, or developer...">
                            </div>
                            <div class="col-md-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="0">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" 
                                                <?= $category_filter == $category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="sort" class="form-label">Sort By</label>
                                <select class="form-select" id="sort" name="sort">
                                    <option value="created_at" <?= $sort == 'created_at' ? 'selected' : '' ?>>Newest</option>
                                    <option value="title" <?= $sort == 'title' ? 'selected' : '' ?>>Title</option>
                                    <option value="rating" <?= $sort == 'rating' ? 'selected' : '' ?>>Rating</option>
                                    <option value="updated_at" <?= $sort == 'updated_at' ? 'selected' : '' ?>>Recently Updated</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </form>
                        
                        <?php if (!empty($search) || $category_filter > 0): ?>
                            <div class="mt-3">
                                <small class="text-muted">
                                    Showing <?= $total_games ?> results
                                    <?php if (!empty($search)): ?>
                                        for "<?= htmlspecialchars($search) ?>"
                                    <?php endif; ?>
                                    <?php if ($category_filter > 0): ?>
                                        in <?= htmlspecialchars($categories[array_search($category_filter, array_column($categories, 'id'))]['name'] ?? 'Unknown Category') ?>
                                    <?php endif; ?>
                                </small>
                                <a href="index.php" class="btn btn-outline-secondary btn-sm ms-2">Clear Filters</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Games Section -->
    <div class="container my-5">
        <?php if (empty($games)): ?>
            <div class="row">
                <div class="col-12 text-center">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No games found. <?php if (!empty($search) || $category_filter > 0): ?>Try adjusting your search criteria.<?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($games as $game): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card game-card h-100 shadow-sm">
                            <?php if (!empty($game['image_path'])): ?>
                                <img src="<?= htmlspecialchars($game['image_path']) ?>"
                                    class="game-thumb"
                                    alt="<?= htmlspecialchars($game['title']) ?>">
                            <?php else: ?>
                                <div class="card-img-top d-flex align-items-center justify-content-center" 
                                     style="height: 200px; background: #f8f9fa;">
                                    <i class="fas fa-gamepad fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?= htmlspecialchars($game['title']) ?></h5>
                                
                                <div class="mb-2">
                                    <?php if ($game['category_name']): ?>
                                        <span class="badge bg-primary"><?= htmlspecialchars($game['category_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-building me-1"></i>
                                        <?= htmlspecialchars($game['developer']) ?>
                                    </small>
                                </div>
                                
                                <div class="mb-2">
                                    <div class="rating-stars">
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
                                        <span class="ms-2 text-muted"><?= number_format($rating, 1) ?>/5</span>
                                    </div>
                                </div>
                                
                                <p class="card-text flex-grow-1">
                                    <?= mb_strimwidth(strip_tags($game['description']), 0, 150, '...', 'UTF-8') ?>
                                </p>
                                
                                <div class="mt-auto">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        Released: <?= date('M Y', strtotime($game['release_date'])) ?>
                                    </small>
                                    <a href="game.php?id=<?= $game['id'] ?>" class="btn btn-outline-primary btn-sm float-end">
                                        View Details <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Game pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>&sort=<?= $sort ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>&sort=<?= $sort ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>&sort=<?= $sort ?>">
                                    Next
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <h5>About <?= SITE_NAME ?></h5>
                    <p>Manitoba's grassroots gaming community bringing together local video game fans to share insights and recommendations across all platforms.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <p class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Community Driven Reviews
                    </p>
                    <p class="mb-0">
                        <i class="fas fa-gamepad me-2"></i>
                        All Platforms Welcome
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>