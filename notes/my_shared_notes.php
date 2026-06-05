<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
include __DIR__ . '/../includes/header.php';

$user = current_user();
$uid = (int)$user['id'];
$userRole = $user['role'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$error = 'Invalid request. Please refresh.';
	} else {
		$noteId = (int)($_POST['note_id'] ?? 0);
		$stmt = mysqli_prepare($conn, "SELECT id, file_path FROM uploaded_notes WHERE id=? AND user_id=? LIMIT 1");
		mysqli_stmt_bind_param($stmt, 'ii', $noteId, $uid);
		mysqli_stmt_execute($stmt);
		$note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
		mysqli_stmt_close($stmt);

		if ($note) {
			$filePath = __DIR__ . '/..' . $note['file_path'];
			if (is_file($filePath)) {
				@unlink($filePath);
			}
			$deleteStmt = mysqli_prepare($conn, "DELETE FROM uploaded_notes WHERE id=? AND user_id=?");
			mysqli_stmt_bind_param($deleteStmt, 'ii', $noteId, $uid);
			mysqli_stmt_execute($deleteStmt);
			mysqli_stmt_close($deleteStmt);
			$success = 'Note deleted successfully.';
		} else {
			$error = 'Note not found.';
		}
	}
}

$stmt = mysqli_prepare($conn, "
	SELECT id, title, description, subject, teacher_name, uploader_type,
	       file_path, file_type, file_size, status, created_at
	FROM uploaded_notes
	WHERE user_id=?
	ORDER BY created_at DESC
");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$notes = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);
?>

<h3 class="mb-4"><i class="bi bi-journal-text me-2"></i>My Shared Notes</h3>

<?php if ($error): ?>
	<div class="alert alert-danger alert-dismissible fade show"><?php echo h($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($success): ?>
	<div class="alert alert-success alert-dismissible fade show"><?php echo h($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="mb-3">
	<a href="/student/upload_notes.php" class="btn btn-primary btn-sm"><i class="bi bi-cloud-upload me-1"></i>Upload New Notes</a>
	<a href="/notes/shared_notes.php" class="btn btn-outline-secondary btn-sm">Browse All Shared Notes</a>
</div>

<?php if (mysqli_num_rows($notes) === 0): ?>
	<div class="card">
		<div class="card-body text-center py-5 text-muted">
			<i class="bi bi-inbox fs-1 mb-3 d-block"></i>
			You have not shared any notes yet.
		</div>
	</div>
<?php else: ?>
	<div class="row g-4">
		<?php while ($note = mysqli_fetch_assoc($notes)): ?>
			<div class="col-md-6 col-lg-4">
				<div class="card h-100">
					<div class="card-body">
						<div class="d-flex justify-content-between align-items-start mb-2">
							<h5 class="card-title mb-0"><?php echo h($note['title']); ?></h5>
							<span class="badge text-bg-<?php
								echo match ($note['status']) {
									'approved' => 'success',
									'rejected' => 'danger',
									default => 'warning',
								};
							?>"><?php echo h(ucfirst($note['status'])); ?></span>
						</div>
						<?php if (!empty($note['description'])): ?>
							<p class="text-muted small"><?php echo h(mb_substr($note['description'], 0, 100)); ?><?php echo mb_strlen($note['description']) > 100 ? '...' : ''; ?></p>
						<?php endif; ?>
						<div class="small text-muted mb-3">
							<?php if (!empty($note['subject'])): ?><div>Subject: <?php echo h($note['subject']); ?></div><?php endif; ?>
							<div>Uploaded: <?php echo date('M d, Y', strtotime($note['created_at'])); ?></div>
						</div>
						<div class="d-flex gap-2">
							<?php if ($note['status'] === 'approved'): ?>
								<a href="<?php echo h($note['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary flex-fill">View</a>
							<?php endif; ?>
							<form method="post" onsubmit="return confirm('Delete this note?');">
								<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
								<input type="hidden" name="action" value="delete">
								<input type="hidden" name="note_id" value="<?php echo (int)$note['id']; ?>">
								<button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
							</form>
						</div>
					</div>
				</div>
			</div>
		<?php endwhile; ?>
	</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
