<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$uid = (int)$_SESSION['user']['id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_photo') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$error = 'Invalid request. Please refresh the page.';
	} elseif (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
		$error = 'Please choose a valid image to upload.';
	} else {
		$file = $_FILES['profile_picture'];
		$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
		$mime = mime_content_type($file['tmp_name']);
		if (!isset($allowed[$mime]) || $file['size'] > 2 * 1024 * 1024) {
			$error = 'Upload a JPG, PNG, or WEBP image under 2 MB.';
		} else {
			$ext = $allowed[$mime];
			$filename = 'user_' . $uid . '_' . time() . '.' . $ext;
			$dest = PROFILE_UPLOAD_DIR . '/' . $filename;
			if (move_uploaded_file($file['tmp_name'], $dest)) {
				$old = get_user_profile_data($conn, $uid);
				if (!empty($old['profile_picture'])) {
					$oldPath = __DIR__ . $old['profile_picture'];
					if (is_file($oldPath)) {
						@unlink($oldPath);
					}
				}
				$webPath = '/uploads/profiles/' . $filename;
				$stmt = mysqli_prepare($conn, "UPDATE users SET profile_picture=? WHERE id=?");
				mysqli_stmt_bind_param($stmt, 'si', $webPath, $uid);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				$success = 'Profile picture updated.';
			} else {
				$error = 'Failed to save image. Please try again.';
			}
		}
	}
}

$profile = get_user_profile_data($conn, $uid);
if (!$profile) {
	header('Location: /logout.php');
	exit;
}

include __DIR__ . '/includes/header.php';
$avatarUrl = !empty($profile['profile_picture']) ? $profile['profile_picture'] : null;
?>

<div class="row justify-content-center">
	<div class="col-lg-10">
		<div class="card shadow-sm mb-4">
			<div class="card-body text-center py-4">
				<div class="mb-3">
					<?php if ($avatarUrl): ?>
						<img src="<?php echo h($avatarUrl); ?>" alt="Profile picture" class="rounded-circle border shadow-sm" style="width: 140px; height: 140px; object-fit: cover;">
					<?php else: ?>
						<div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center border" style="width: 140px; height: 140px;">
							<i class="bi bi-person-fill" style="font-size: 4rem;"></i>
						</div>
					<?php endif; ?>
				</div>
				<h3 class="mb-1"><?php echo h($profile['name']); ?></h3>
				<p class="text-muted mb-3">
					<span class="badge text-bg-secondary me-1"><?php echo h(ucfirst($profile['role'])); ?></span>
					<span class="badge text-bg-<?php echo $profile['status'] === 'active' ? 'success' : 'warning'; ?>"><?php echo h(ucfirst($profile['status'])); ?></span>
				</p>
				<form method="post" enctype="multipart/form-data" class="d-inline-flex flex-wrap gap-2 justify-content-center align-items-center">
					<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
					<input type="hidden" name="action" value="upload_photo">
					<input type="file" name="profile_picture" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp" required style="max-width: 260px;">
					<button type="submit" class="btn btn-sm btn-outline-primary">Update Photo</button>
				</form>
			</div>
		</div>

		<?php if ($error): ?>
			<div class="alert alert-danger"><?php echo h($error); ?></div>
		<?php endif; ?>
		<?php if ($success): ?>
			<div class="alert alert-success"><?php echo h($success); ?></div>
		<?php endif; ?>

		<div class="row g-3 mb-4">
			<div class="col-md-3">
				<div class="card h-100 text-center">
					<div class="card-body">
						<div class="text-muted small">Email</div>
						<div class="fw-semibold"><?php echo h($profile['email']); ?></div>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card h-100 text-center">
					<div class="card-body">
						<div class="text-muted small">Phone</div>
						<div class="fw-semibold"><?php echo h($profile['phone'] ?: 'Not added'); ?></div>
					</div>
				</div>
			</div>
			<div class="col-md-2">
				<div class="card h-100 text-center">
					<div class="card-body">
						<div class="text-muted small">Books Issued Now</div>
						<div class="fs-4 fw-bold text-primary"><?php echo (int)$profile['open_books_count']; ?></div>
					</div>
				</div>
			</div>
			<div class="col-md-2">
				<div class="card h-100 text-center">
					<div class="card-body">
						<div class="text-muted small">Pending Fine</div>
						<div class="fs-4 fw-bold text-danger">₹<?php echo number_format((float)$profile['total_pending_fine'], 2); ?></div>
					</div>
				</div>
			</div>
			<div class="col-md-2">
				<div class="card h-100 text-center">
					<div class="card-body">
						<div class="text-muted small">Shared Notes</div>
						<div class="fs-4 fw-bold text-success"><?php echo (int)$profile['shared_notes_count']; ?></div>
						<a href="/notes/my_shared_notes.php" class="small">View mine</a>
					</div>
				</div>
			</div>
		</div>

		<div class="card shadow-sm">
			<div class="card-header bg-white">
				<h5 class="mb-0"><i class="bi bi-journal-check me-2"></i>My Issued Books</h5>
			</div>
			<div class="card-body p-0">
				<?php if (empty($profile['issued_books'])): ?>
					<p class="text-muted p-4 mb-0">No books issued yet.</p>
				<?php else: ?>
					<div class="table-responsive">
						<table class="table table-striped mb-0">
							<thead>
								<tr>
									<th>Title</th>
									<th>Issue Date</th>
									<th>Due Date</th>
									<th>Return Date</th>
									<th>Fine</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($profile['issued_books'] as $book): ?>
									<tr>
										<td><?php echo h($book['title']); ?></td>
										<td><?php echo h($book['issue_date']); ?></td>
										<td><?php echo h($book['due_date']); ?></td>
										<td><?php echo h($book['return_date'] ?: '—'); ?></td>
										<td>₹<?php echo number_format((float)$book['current_fine'], 2); ?></td>
										<td>
											<?php if ($book['is_open']): ?>
												<span class="badge text-bg-primary">Issued</span>
											<?php else: ?>
												<span class="badge text-bg-<?php echo ($book['fine_status'] ?? '') === 'paid' ? 'success' : 'secondary'; ?>">
													<?php echo h($book['fine_status'] ?: 'returned'); ?>
												</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
