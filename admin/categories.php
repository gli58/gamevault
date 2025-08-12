<?php
require_once '../config.php';

// Check if user is admin
if (!is_admin()) {
    redirect('login.php');
}

$action = $_GET['action'] ?? 'list';
$category_id = isset($_GET['id']) && validate_id($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' || $action === 'edit') {
        $name = sanitize_input($_POST['name']);
        
        if (empty($name)) {
            $error = 'Category name is required.';
        } elseif (strlen($name) > 100) {
            $error = 'Category name must be less than 100 characters.';
        } else {
            // Check for duplicate name
            $check_sql = "SELECT id FROM categories WHERE name = :name" . ($action === 'edit' ? " AND id != :id" : "");
            $check_stmt = $pdo->prepare($check_sql);
            $check_params = [':name' => $name];
            if ($action === 'edit') {
                $check_params[':id'] = $category_id;
            }
            $check_stmt->execute($check_params);
            
            if ($check_stmt->fetch()) {
                $error = 'A category with this name already exists.';
            } else {
                if ($action === 'create') {
                    $sql = "INSERT INTO categories (name) VALUES (:name)";
                    $params = [':name' => $name];
                    
                    if ($pdo->prepare($sql)->execute($params)) {
                        $message = 'Category created successfully!';
                        $action = 'list';
                    } else {
                        $error = 'Error creating category.';
                    }
                } elseif ($action === 'edit' && $category_id > 0) {
                    $sql = "UPDATE categories SET name = :name WHERE id = :id";
                    $params = [':name' => $name, ':id' => $category_id];
                    
                    if ($pdo->prepare($sql)->execute($params)) {
                        $message = 'Category updated successfully!';
                        $action = 'list';
                    } else {
                        $error = 'Error updating category.';
                    }
                }
            }
        }
    } elseif ($action === 'delete' && $category_id > 0) {
        // Check if category is in use
        $usage_check = $pdo->prepare("SELECT COUNT(*) FROM games WHERE category_id = :id");
        $usage_check->execute([':id' => $category_id]);
        $usage_count = $usage_check->fetchColumn();
        
        if ($usage_count > 0) {
            $error = "Cannot delete category. It is currently used by {$usage_count} game(s).";
        } else {
            $delete_sql = "DELETE FROM categories WHERE id = :id";
            if ($pdo->prepare($delete_sql)->execute([':id' => $category_id])) {
                $message = 'Category deleted successfully!';
            } else {
                $error = 'Error deleting category.';
            }
        }
        $action = 'list';
    }
}

// Get category data for editing
$category = null;
if ($action === 'edit' && $category_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->execute([':id' => $category_id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        $error = 'Category not found.';
        $action = 'list';
    }
}

// Get categories list with game counts
if ($action === 'list') {
    $categories_sql = "SELECT c.*, COUNT(g.id) as game_count 
                      FROM categories c 
                      LEFT JOIN games g ON c.id = g.category_id 
                      GROUP BY c.id 
                      ORDER BY c.name";
    $categories = $pdo->query($categories_sql)->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - <?= SITE_NAME ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
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
                <a class="nav-link text-white active" href="categories.php">
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
                    <h1 class="h3 mb-0">
                        <?= $action === 'create' ? 'Add New Category' : ($action === 'edit' ? 'Edit Category' : 'Manage Categories') ?>
                    </h1>
                    <?php if ($action === 'list'): ?>
                        <a href="?action=create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add New Category
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
                    <!-- Category Form -->
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Category Name *</label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?= htmlspecialchars($category['name'] ?? '') ?>" 
                                                   required maxlength="100" 
                                                   placeholder="Enter category name...">
                                            <div class="form-text">
                                                Example: Action, Adventure, RPG, Strategy, etc.
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>
                                                <?= $action === 'create' ? 'Create Category' : 'Update Category' ?>
                                            </button>
                                            <a href="categories.php" class="btn btn-secondary">
                                                <i class="fas fa-times me-2"></i>Cancel
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Categories List -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Categories List</h5>
                        </div>
                        
                        <div class="card-body p-0">
                            <?php if (empty($categories)): ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No categories found. <a href="?action=create">Add the first category</a>.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Category Name</th>
                                                <th>Games Count</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $cat): ?>
                                                <tr>
                                                    <td><strong>#<?= $cat['id'] ?></strong></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <span class="badge bg-primary me-2">
                                                                <?= htmlspecialchars($cat['name']) ?>
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($cat['game_count'] > 0): ?>
                                                            <span class="badge bg-success">
                                                                <?= $cat['game_count'] ?> game<?= $cat['game_count'] != 1 ? 's' : '' ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">No games</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= date('M j, Y', strtotime($cat['created_at'])) ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="../index.php?category=<?= $cat['id'] ?>" 
                                                               class="btn btn-outline-info" target="_blank" 
                                                               title="View Games in Category">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="?action=edit&id=<?= $cat['id'] ?>" 
                                                               class="btn btn-outline-primary" 
                                                               title="Edit Category">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php if ($cat['game_count'] == 0): ?>
                                                                <button type="button" class="btn btn-outline-danger" 
                                                                        onclick="confirmDelete(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>')"
                                                                        title="Delete Category">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="button" class="btn btn-outline-secondary" 
                                                                        disabled title="Cannot delete - has games">
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
                    
                    <!-- Usage Information -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Category Usage</h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2">Categories help organize games and make them easier to find.</p>
                                    <ul class="mb-0">
                                        <li>Categories with games cannot be deleted</li>
                                        <li>Removing a category from all games allows deletion</li>
                                        <li>Users can filter games by category on the homepage</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Suggested Categories</h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2">Consider adding these popular game categories:</p>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="badge bg-light text-dark">Action</span>
                                        <span class="badge bg-light text-dark">Adventure</span>
                                        <span class="badge bg-light text-dark">RPG</span>
                                        <span class="badge bg-light text-dark">Strategy</span>
                                        <span class="badge bg-light text-dark">Sports</span>
                                        <span class="badge bg-light text-dark">Racing</span>
                                        <span class="badge bg-light text-dark">Puzzle</span>
                                        <span class="badge bg-light text-dark">Horror</span>
                                        <span class="badge bg-light text-dark">Simulation</span>
                                        <span class="badge bg-light text-dark">Platform</span>
                                    </div>
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
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the category <strong id="deleteCategoryName"></strong>?</p>
                    <p class="text-warning small">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;" id="deleteForm">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Category
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Delete confirmation
        function confirmDelete(categoryId, categoryName) {
            document.getElementById('deleteCategoryName').textContent = categoryName;
            document.getElementById('deleteForm').action = '?action=delete&id=' + categoryId;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>