<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

function handle_cover_upload(array $file, ?string $currentPath = null): ?string {
	if ($file['error'] === UPLOAD_ERR_NO_FILE) {
		return $currentPath;
	}
	if ($file['error'] !== UPLOAD_ERR_OK) {
		throw new RuntimeException('Failed to upload file.');
	}
	$allowed = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mime = finfo_file($finfo, $file['tmp_name']);
	finfo_close($finfo);
	if (!isset($allowed[$mime])) {
		throw new RuntimeException('Only JPG, PNG or WEBP images are allowed.');
	}
	if ($file['size'] > 2 * 1024 * 1024) {
		throw new RuntimeException('Cover image must be smaller than 2 MB.');
	}
	$filename = uniqid('book_', true) . $allowed[$mime];
	$target = BOOK_UPLOAD_DIR . '/' . $filename;
	if (!move_uploaded_file($file['tmp_name'], $target)) {
		throw new RuntimeException('Unable to move uploaded file.');
	}
	if ($currentPath && file_exists(__DIR__ . '/../' . ltrim($currentPath, '/'))) {
		@unlink(__DIR__ . '/../' . ltrim($currentPath, '/'));
	}
	return '/uploads/books/' . $filename;
}

$errors = [];
$message = '';

// Datasets
$departments = [];
$deptRes = mysqli_query($conn, "SELECT id, name FROM departments ORDER BY name");
while ($row = mysqli_fetch_assoc($deptRes)) {
	$departments[] = $row;
}
$categories = [];
$catRes = mysqli_query($conn, "SELECT id, name FROM book_categories ORDER BY name");
while ($row = mysqli_fetch_assoc($catRes)) {
	$categories[] = $row;
}

// Determine edit target
$edit = null;
if (isset($_GET['edit'])) {
	$editId = (int)$_GET['edit'];
	if ($editId > 0) {
		$st = mysqli_prepare($conn, "SELECT * FROM books WHERE id=? LIMIT 1");
		mysqli_stmt_bind_param($st, 'i', $editId);
		mysqli_stmt_execute($st);
		$res = mysqli_stmt_get_result($st);
		$edit = mysqli_fetch_assoc($res);
		mysqli_stmt_close($st);
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create','update'], true)) {
	$action = $_POST['action'] ?? '';
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$errors[] = 'Invalid request token. Please refresh the page.';
	} else {
		$title = trim($_POST['title'] ?? '');
		$author = trim($_POST['author'] ?? '');
		$category = trim($_POST['category'] ?? '');
		$department = trim($_POST['department'] ?? '');
		$isbn = trim($_POST['isbn'] ?? '');
		$tags = trim($_POST['tags'] ?? '');
		$description = trim($_POST['description'] ?? '');
		$quantity = max(1, (int)($_POST['quantity'] ?? 1));
		$status = $_POST['status'] === 'issued' ? 'issued' : 'available';
		$available_copies = min($quantity, max(0, (int)($_POST['available_copies'] ?? $quantity)));

		if ($title === '' || $author === '') {
			$errors[] = 'Title and Author are required.';
		}
		if ($category === '') {
			$errors[] = 'Please choose a category.';
		}

		$coverPath = $edit['cover_image'] ?? null;
		if (isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
			try {
				$coverPath = handle_cover_upload($_FILES['cover'], $coverPath);
			} catch (RuntimeException $e) {
				$errors[] = $e->getMessage();
			}
		}

		if (empty($errors)) {
			if ($action === 'create') {
				$stmt = mysqli_prepare($conn, "INSERT INTO books (title, author, category, department, isbn, cover_image, description, quantity, available_copies, status, tags, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
				$createdBy = $_SESSION['user']['id'];
				mysqli_stmt_bind_param($stmt, 'sssssssisssi', $title, $author, $category, $department, $isbn, $coverPath, $description, $quantity, $available_copies, $status, $tags, $createdBy);
				if (mysqli_stmt_execute($stmt)) {
					$message = 'Book added successfully.';
				}
				mysqli_stmt_close($stmt);
			} elseif ($action === 'update') {
				$bookId = (int)($_POST['id'] ?? 0);
				if ($bookId > 0) {
					$stmt = mysqli_prepare($conn, "UPDATE books SET title=?, author=?, category=?, department=?, isbn=?, cover_image=?, description=?, quantity=?, available_copies=?, status=?, tags=? WHERE id=?");
					mysqli_stmt_bind_param($stmt, 'sssssssisssi', $title, $author, $category, $department, $isbn, $coverPath, $description, $quantity, $available_copies, $status, $tags, $bookId);
					if (mysqli_stmt_execute($stmt)) {
						$message = 'Book updated successfully.';
					}
					mysqli_stmt_close($stmt);
					$edit = null;
				}
			}
		}
	}
}

elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$errors[] = 'Invalid request.';
	} else {
		$bookId = (int)($_POST['id'] ?? 0);
		if ($bookId > 0) {
			$stmt = mysqli_prepare($conn, "DELETE FROM books WHERE id=?");
			mysqli_stmt_bind_param($stmt, 'i', $bookId);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			$message = 'Book deleted.';
		}
	}
}

include __DIR__ . '/../includes/header.php';

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
	$like = '%' . $search . '%';
	$stmt = mysqli_prepare($conn, "SELECT b.*, COALESCE(AVG(br.rating),0) AS avg_rating, COUNT(br.id) AS review_count
		FROM books b
		LEFT JOIN book_reviews br ON br.book_id=b.id
		WHERE b.title LIKE ? OR b.author LIKE ?
		GROUP BY b.id
		ORDER BY b.id DESC");
	mysqli_stmt_bind_param($stmt, 'ss', $like, $like);
	mysqli_stmt_execute($stmt);
	$books = mysqli_stmt_get_result($stmt);
	mysqli_stmt_close($stmt);
} else {
	$books = mysqli_query($conn, "SELECT b.*, COALESCE(AVG(br.rating),0) AS avg_rating, COUNT(br.id) AS review_count FROM books b LEFT JOIN book_reviews br ON br.book_id=b.id GROUP BY b.id ORDER BY b.id DESC");
}
?>

<div class="d-flex align-items-center justify-content-between mb-3">
	<div>
		<h3 class="mb-0">Manage Books</h3>
		<p class="text-muted mb-0">Add, edit and curate the library catalogue with cover images and categories.</p>
	</div>
</div>

<?php if (!empty($errors)): ?>
	<div class="alert alert-danger alert-dismissible fade show" role="alert">
		<ul class="mb-0 ps-3">
			<?php foreach ($errors as $error): ?>
				<li><?php echo h($error); ?></li>
			<?php endforeach; ?>
		</ul>
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	</div>
<?php endif; ?>

<?php if ($message): ?>
	<div class="alert alert-success alert-dismissible fade show" role="alert">
		<?php echo h($message); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	</div>
<?php endif; ?>

<div class="row g-4">
	<div class="col-xl-4">
		<div class="card h-100">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h5 class="card-title mb-0"><?php echo $edit ? 'Update Book' : 'Add New Book'; ?></h5>
					<?php if ($edit): ?>
						<a class="btn btn-sm btn-outline-secondary" href="/admin/manage_books.php">Reset</a>
					<?php endif; ?>
				</div>
				<form method="post" enctype="multipart/form-data">
					<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
					<input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'create'; ?>">
					<?php if ($edit): ?>
						<input type="hidden" name="id" value="<?php echo (int)$edit['id']; ?>">
					<?php endif; ?>
					<div class="mb-3">
						<label class="form-label fw-semibold">Title</label>
						<input type="text" name="title" class="form-control" required value="<?php echo h($edit['title'] ?? ''); ?>">
					</div>
					<div class="mb-3">
						<label class="form-label fw-semibold">Author</label>
						<input type="text" name="author" class="form-control" required value="<?php echo h($edit['author'] ?? ''); ?>">
					</div>
					<div class="mb-3">
						<label class="form-label fw-semibold">Category</label>
						<select name="category" class="form-select" required>
							<option value="">Select category</option>
							<?php foreach ($categories as $cat): ?>
								<option value="<?php echo h($cat['name']); ?>" <?php echo (($edit['category'] ?? '') === $cat['name']) ? 'selected' : ''; ?>>
									<?php echo h($cat['name']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="mb-3">
						<label class="form-label fw-semibold">Department</label>
						<select name="department" class="form-select">
							<option value="">-- Not linked --</option>
							<?php foreach ($departments as $dept): ?>
								<option value="<?php echo h($dept['name']); ?>" <?php echo (($edit['department'] ?? '') === $dept['name']) ? 'selected' : ''; ?>>
									<?php echo h($dept['name']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="row g-2">
						<div class="col">
							<label class="form-label fw-semibold">ISBN</label>
							<input type="text" name="isbn" class="form-control" value="<?php echo h($edit['isbn'] ?? ''); ?>" placeholder="Optional">
						</div>
						<div class="col">
							<label class="form-label fw-semibold">Quantity</label>
							<input type="number" min="1" name="quantity" class="form-control" value="<?php echo h($edit['quantity'] ?? 1); ?>">
						</div>
					</div>
					<div class="row g-2 mt-1">
						<div class="col">
							<label class="form-label fw-semibold">Available Copies</label>
							<input type="number" min="0" name="available_copies" class="form-control" value="<?php echo h($edit['available_copies'] ?? ($edit['quantity'] ?? 1)); ?>">
						</div>
						<div class="col">
							<label class="form-label fw-semibold">Status</label>
							<select name="status" class="form-select">
								<?php $st = $edit['status'] ?? 'available'; ?>
								<option value="available" <?php echo $st === 'available' ? 'selected' : ''; ?>>Available</option>
								<option value="issued" <?php echo $st === 'issued' ? 'selected' : ''; ?>>Issued</option>
							</select>
						</div>
					</div>
					<div class="mb-3 mt-2">
						<label class="form-label fw-semibold">Tags</label>
						<input type="text" name="tags" class="form-control" placeholder="comma separated e.g. algorithms, beginner" value="<?php echo h($edit['tags'] ?? ''); ?>">
					</div>
					<div class="mb-3">
						<label class="form-label fw-semibold">Description</label>
						<textarea name="description" class="form-control" rows="3" placeholder="Short summary"><?php echo h($edit['description'] ?? ''); ?></textarea>
					</div>
					<div class="mb-3">
						<label class="form-label fw-semibold d-flex justify-content-between">
							<span>Cover Image</span>
							<small class="text-muted">PNG/JPG up to 2 MB</small>
						</label>
						<input type="file" name="cover" class="form-control" accept="image/png,image/jpeg,image/webp">
						<?php if (!empty($edit['cover_image'])): ?>
							<div class="mt-2">
								<img src="<?php echo h($edit['cover_image']); ?>" class="rounded shadow-sm" style="height: 80px" alt="cover preview">
							</div>
						<?php endif; ?>
					</div>
					<button class="btn btn-primary w-100" type="submit">
						<?php echo $edit ? 'Save Changes' : 'Add Book'; ?>
					</button>
				</form>
			</div>
		</div>
	</div>
	<div class="col-xl-8">
		<div class="card">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h5 class="card-title mb-0">Library Catalogue</h5>
					<form class="d-flex" method="get">
						<input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Search title or author" value="<?php echo h($_GET['search'] ?? ''); ?>">
						<button class="btn btn-sm btn-outline-secondary">Search</button>
					</form>
				</div>
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead class="table-light">
							<tr>
								<th>Book</th>
								<th>Category</th>
								<th>ISBN</th>
								<th>Stock</th>
								<th>Rating</th>
								<th>Status</th>
								<th class="text-end">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php while ($book = mysqli_fetch_assoc($books)): ?>
								<tr>
									<td>
										<div class="d-flex align-items-center">
											<?php if ($book['cover_image']): ?>
												<img src="<?php echo h($book['cover_image']); ?>" alt="cover" class="rounded me-3" style="width:48px;height:64px;object-fit:cover;">
											<?php else: ?>
												<div class="placeholder rounded me-3 bg-secondary-subtle d-flex align-items-center justify-content-center" style="width:48px;height:64px;">
													<i class="bi bi-book text-muted"></i>
												</div>
											<?php endif; ?>
											<div>
												<div class="fw-semibold"><?php echo h($book['title']); ?></div>
												<small class="text-muted"><?php echo h($book['author']); ?></small>
											</div>
										</div>
									</td>
									<td><?php echo h($book['category'] ?: '—'); ?></td>
									<td><?php echo h($book['isbn'] ?: '—'); ?></td>
									<td>
										<span class="badge bg-light text-dark"><?php echo (int)$book['available_copies']; ?> / <?php echo (int)$book['quantity']; ?></span>
									</td>
									<td>
										<?php if ($book['review_count'] > 0): ?>
											<span class="text-warning">
												<i class="bi bi-star-fill"></i>
												<?php echo number_format($book['avg_rating'], 1); ?>
											</span>
											<small class="text-muted">(<?php echo (int)$book['review_count']; ?>)</small>
										<?php else: ?>
											<span class="text-muted">No reviews</span>
										<?php endif; ?>
									</td>
									<td>
										<span class="badge text-bg-<?php echo $book['status'] === 'available' ? 'success' : 'secondary'; ?>">
											<?php echo h(ucfirst($book['status'])); ?>
										</span>
									</td>
									<td class="text-end">
										<a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo (int)$book['id']; ?>">Edit</a>
										<form method="post" class="d-inline" onsubmit="return confirm('Delete this book?');">
											<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
											<input type="hidden" name="action" value="delete">
											<input type="hidden" name="id" value="<?php echo (int)$book['id']; ?>">
											<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
										</form>
									</td>
								</tr>
							<?php endwhile; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
