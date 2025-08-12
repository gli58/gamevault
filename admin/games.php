<?php
require_once '../config.php';

// Check if user is admin
if (!is_admin()) {
    redirect('login.php');
}

$action = $_GET['action'] ?? 'list';
$game_id = isset($_GET['id']) && validate_id($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' || $action === 'edit') {
        $title = sanitize_input($_POST['title']);
        $category_id = validate_id($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $release_date = sanitize_input($_POST['release_date']);
        $developer = sanitize_input($_POST['developer']);
        // NOTE: FILTER_VALIDATE_FLOAT doesn't apply min/max via options; validate range manually after parsing
        $rating = filter_var($_POST['rating'], FILTER_VALIDATE_FLOAT);
        $description = $_POST['description'] ?? ''; // Don't fully sanitize HTML for rich text
        // Allowlisted tags for security
        $description = strip_tags($description, '<p><br><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6>');

        // Validation
        if (empty($title) || empty($developer) || empty(trim($description))) {
            $error = 'Please fill in all required fields.';
        } elseif ($rating !== null && $rating !== false && ($rating < 0 || $rating > 10)) {
            $error = 'Rating must be a number between 0 and 10.';
        } else {
            // Handle image upload
            $image_path = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../' . UPLOAD_PATH; // e.g. ../uploads/

                // Create upload directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Max 5MB
                if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                    $error = 'Image too large (max 5MB).';
                } else {
                    $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

                    if (in_array($file_ext, $allowed_exts, true)) {
                        // Verify it's a real image
                        if (getimagesize($_FILES['image']['tmp_name'])) {
                            $new_filename = uniqid('game_') . '.' . $file_ext;
                            $target_fs = $upload_dir . $new_filename;      // filesystem path from admin/

                            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_fs)) {
                                $image_path = UPLOAD_PATH . $new_filename;   // DB/web path like uploads/xxx.jpg
                            } else {
                                $error = 'Failed to upload image.';
                            }
                        } else {
                            $error = 'Invalid image file.';
                        }
                    } else {
                        $error = 'Only JPG, JPEG, PNG, and GIF files are allowed.';
                    }
                }
            }

            if (empty($error)) {
                if ($action === 'create') {
                    $sql = "INSERT INTO games (title, category_id, release_date, developer, rating, image_path, description) 
                            VALUES (:title, :category_id, :release_date, :developer, :rating, :image_path, :description)";
                    $params = [
                        ':title' => $title,
                        ':category_id' => $category_id,
                        ':release_date' => $release_date ?: null,
                        ':developer' => $developer,
                        ':rating' => $rating !== false ? $rating : null,
                        ':image_path' => $image_path,
                        ':description' => $description
                    ];

                    if ($pdo->prepare($sql)->execute($params)) {
                        $message = 'Game created successfully!';
                        $action = 'list';
                    } else {
                        $error = 'Error creating game.';
                    }
                } elseif ($action === 'edit' && $game_id > 0) {
                    // Get current game for image handling
                    $current_game = $pdo->prepare("SELECT image_path FROM games WHERE id = :id");
                    $current_game->execute([':id' => $game_id]);
                    $current_data = $current_game->fetch();

                    // Keep existing image if no new one uploaded
                    if (empty($image_path) && $current_data) {
                        $image_path = $current_data['image_path'];
                    }

                    $sql = "UPDATE games SET title = :title, category_id = :category_id, release_date = :release_date, 
                            developer = :developer, rating = :rating, image_path = :image_path, description = :description 
                            WHERE id = :id";
                    $params = [
                        ':title' => $title,
                        ':category_id' => $category_id,
                        ':release_date' => $release_date ?: null,
                        ':developer' => $developer,
                        ':rating' => $rating !== false ? $rating : null,
                        ':image_path' => $image_path,
                        ':description' => $description,
                        ':id' => $game_id
                    ];

                    if ($pdo->prepare($sql)->execute($params)) {
                        $message = 'Game updated successfully!';
                        $action = 'list';
                    } else {
                        $error = 'Error updating game.';
                    }
                }
            }
        }
    } elseif ($action === 'delete' && $game_id > 0) {
        // Delete associated image file
        $game_data = $pdo->prepare("SELECT image_path FROM games WHERE id = :id");
        $game_data->execute([':id' => $game_id]);
        $game_info = $game_data->fetch();

        if ($game_info && !empty($game_info['image_path']) && file_exists('../' . $game_info['image_path'])) {
            @unlink('../' . $game_info['image_path']);
        }

        // Delete game
        $delete_sql = "DELETE FROM games WHERE id = :id";
        if ($pdo->prepare($delete_sql)->execute([':id' => $game_id])) {
            $message = 'Game deleted successfully!';
        } else {
            $error = 'Error deleting game.';
        }
        $action = 'list';
    }
}

// Get game data for editing
$game = null;
if ($action === 'edit' && $game_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM games WHERE id = :id");
    $stmt->execute([':id' => $game_id]);
    $game = $stmt->fetch();

    if (!$game) {
        $error = 'Game not found.';
        $action = 'list';
    }
}

// Get categories for dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Get games list
if ($action === 'list') {
    $sort = isset($_GET['sort']) && in_array($_GET['sort'], ['title', 'created_at', 'updated_at', 'rating'], true) ? $_GET['sort'] : 'created_at';
    $games_sql = "SELECT g.*, c.name as category_name FROM games g 
                  LEFT JOIN categories c ON g.category_id = c.id 
                  ORDER BY g.$sort DESC";
    $games = $pdo->query($games_sql)->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Games - <?= SITE_NAME ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%); }
        .table-actions { white-space: nowrap; }
        .game-image-preview { max-width: 100px; height: 60px; object-fit: cover; border-radius: 4px; }
        .ck-editor__editable { min-height: 200px; }
        .editor-container { border: 1px solid #ddd; border-radius: 0.375rem; }
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
            <a class="nav-link text-white" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link text-white active" href="games.php"><i class="fas fa-gamepad me-2"></i>Manage Games</a>
            <a class="nav-link text-white" href="categories.php"><i class="fas fa-tags me-2"></i>Manage Categories</a>
            <a class="nav-link text-white" href="users.php"><i class="fas fa-users me-2"></i>Manage Users</a>
            <a class="nav-link text-white" href="comments.php"><i class="fas fa-comments me-2"></i>Moderate Comments</a>
            <hr class="text-white">
            <a class="nav-link text-white" href="../index.php"><i class="fas fa-home me-2"></i>View Site</a>
            <a class="nav-link text-white" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-grow-1">
        <header class="bg-white shadow-sm border-bottom p-3">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0"><?= $action === 'create' ? 'Add New Game' : ($action === 'edit' ? 'Edit Game' : 'Manage Games') ?></h1>
                <?php if ($action === 'list'): ?>
                    <a href="?action=create" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add New Game</a>
                <?php endif; ?>
            </div>
        </header>

        <div class="p-4">
            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($action === 'create' || $action === 'edit'): ?>
                <!-- Game Form -->
                <div class="card">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="gameForm">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Game Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($game['title'] ?? '') ?>" required maxlength="200" placeholder="Enter game title...">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="developer" class="form-label">Developer *</label>
                                                <input type="text" class="form-control" id="developer" name="developer" value="<?= htmlspecialchars($game['developer'] ?? '') ?>" required maxlength="100" placeholder="Game developer name...">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="category_id" class="form-label">Category</label>
                                                <select class="form-select" id="category_id" name="category_id">
                                                    <option value="">Select Category</option>
                                                    <?php foreach ($categories as $category): ?>
                                                        <option value="<?= $category['id'] ?>" <?= ($game['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="release_date" class="form-label">Release Date</label>
                                                <input type="date" class="form-control" id="release_date" name="release_date" value="<?= htmlspecialchars($game['release_date'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="rating" class="form-label">Rating (0-10)</label>
                                                <input type="number" class="form-control" id="rating" name="rating" value="<?= htmlspecialchars($game['rating'] ?? '') ?>" min="0" max="10" step="0.1" placeholder="e.g. 8.5">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description *</label>
                                        <div class="editor-container"><div id="description"><?= $game['description'] ?? '' ?></div></div>
                                        <textarea name="description" id="descriptionHidden" style="display:none;"></textarea>
                                        <div class="form-text mt-2"><i class="fas fa-info-circle"></i> Use the rich text editor to format your game description with headers, bold text, lists, etc.</div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-image me-2"></i>Game Image</h6></div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                                <div class="form-text">JPG, JPEG, PNG, or GIF files only. Max 5MB.</div>
                                            </div>

                                            <?php if ($action === 'edit' && !empty($game['image_path'])): ?>
                                                <div class="text-center">
                                                    <label class="form-label">Current Image:</label><br>
                                                    <img src="../<?= htmlspecialchars($game['image_path']) ?>" alt="Current game image" class="img-thumbnail game-image-preview">
                                                    <div class="form-text mt-2">Upload a new image to replace this one</div>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center text-muted">
                                                    <i class="fas fa-image fa-3x mb-2"></i>
                                                    <p class="small mb-0">No image uploaded yet</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?= $action === 'create' ? 'Create Game' : 'Update Game' ?></button>
                                <a href="games.php" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

            <?php else: ?>
                <!-- Games List -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Games List (<?= count($games) ?> games)</h5>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown"><i class="fas fa-sort me-1"></i>Sort By</button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?sort=created_at">Date Added</a></li>
                                <li><a class="dropdown-item" href="?sort=title">Title A-Z</a></li>
                                <li><a class="dropdown-item" href="?sort=rating">Rating</a></li>
                                <li><a class="dropdown-item" href="?sort=updated_at">Last Updated</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($games)): ?>
                            <div class="text-center p-5">
                                <i class="fas fa-gamepad fa-4x text-muted mb-3"></i>
                                <h5 class="textMuted mb-2">No Games Found</h5>
                                <p class="text-muted mb-3">Start building your game collection by adding the first game.</p>
                                <a href="?action=create" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add First Game</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40%">Game</th>
                                            <th>Developer</th>
                                            <th>Category</th>
                                            <th>Rating</th>
                                            <th>Release Date</th>
                                            <th width="120px">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($games as $g): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($g['image_path'])): ?>
                                                        <img src="../<?= htmlspecialchars($g['image_path']) ?>" alt="<?= htmlspecialchars($g['title']) ?>" class="game-image-preview me-3">
                                                    <?php else: ?>
                                                        <div class="bg-light d-flex align-items-center justify-content-center me-3 game-image-preview"><i class="fas fa-gamepad text-muted"></i></div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong class="d-block"><?= htmlspecialchars($g['title']) ?></strong>
                                                        <small class="text-muted">Added: <?= date('M j, Y', strtotime($g['created_at'])) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($g['developer']) ?></td>
                                            <td>
                                                <?php if ($g['category_name']): ?>
                                                    <span class="badge bg-primary"><?= htmlspecialchars($g['category_name']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">Uncategorized</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($g['rating'] !== null && $g['rating'] !== ''): ?>
                                                    <div class="d-flex align-items-center"><i class="fas fa-star text-warning me-1"></i><strong><?= number_format((float)$g['rating'], 1) ?></strong></div>
                                                <?php else: ?>
                                                    <span class="text-muted">Not rated</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $g['release_date'] ? date('M j, Y', strtotime($g['release_date'])) : '<span class="text-muted">-</span>' ?></td>
                                            <td class="table-actions">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="../game.php?id=<?= $g['id'] ?>" class="btn btn-outline-info" target="_blank" title="View Game"><i class="fas fa-eye"></i></a>
                                                    <a href="?action=edit&id=<?= $g['id'] ?>" class="btn btn-outline-primary" title="Edit Game"><i class="fas fa-edit"></i></a>
                                                    <button type="button" class="btn btn-outline-danger" onclick="confirmDelete(<?= $g['id'] ?>, '<?= htmlspecialchars($g['title'], ENT_QUOTES) ?>')" title="Delete Game"><i class="fas fa-trash"></i></button>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Game</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the game <strong id="deleteGameTitle"></strong>?</p>
                <p class="text-danger small"><i class="fas fa-exclamation-triangle me-1"></i> This action cannot be undone and will remove all comments for this game.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;" id="deleteForm">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Delete Game</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/35.4.0/classic/ckeditor.js"></script>
<script>
let editorInstance;
<?php if ($action === 'create' || $action === 'edit'): ?>
ClassicEditor
  .create(document.querySelector('#description'), {
    toolbar: ['heading','|','bold','italic','|','bulletedList','numberedList','|','outdent','indent','|','undo','redo'],
    heading: { options: [
      { model:'paragraph', title:'Paragraph', class:'ck-heading_paragraph' },
      { model:'heading1',  view:'h1', title:'Heading 1', class:'ck-heading_heading1' },
      { model:'heading2',  view:'h2', title:'Heading 2', class:'ck-heading_heading2' },
      { model:'heading3',  view:'h3', title:'Heading 3', class:'ck-heading_heading3' }
    ]}
  })
  .then(editor => {
    editorInstance = editor;

    // Validate and sync editor content before submit
    document.getElementById('gameForm').addEventListener('submit', function (e) {
      const htmlContent = editor.getData().trim();
      const plainText  = htmlContent.replace(/<[^>]*>/g, '').trim();

      // Prevent empty descriptions (even if they contain only tags/br)
      if (!plainText) {
        e.preventDefault();
        alert('Description is required.');
        return;
      }

      // Copy editor HTML into the hidden textarea so PHP receives it
      document.getElementById('descriptionHidden').value = htmlContent;
    });
  })
  .catch(error => {
    console.error('CKEditor initialization error:', error);

    // Fallback: replace editor DIV with a normal textarea (visible + required)
    const editorDiv = document.getElementById('description');
    const textarea = document.createElement('textarea');
    textarea.name = 'description';
    textarea.className = 'form-control';
    textarea.rows = 8;
    textarea.required = true; // visible field can be required
    textarea.value = editorDiv.innerHTML;
    editorDiv.parentNode.replaceChild(textarea, editorDiv);

    // In fallback mode, we don't need to sync to the hidden field
  });
<?php endif; ?>

// Delete confirmation modal
function confirmDelete(gameId, gameTitle) {
  document.getElementById('deleteGameTitle').textContent = gameTitle;
  document.getElementById('deleteForm').action = '?action=delete&id=' + gameId;
  const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
  modal.show();
}
</script>
</body>
</html>
