<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'teacher']);
include __DIR__ . '/../includes/header.php';

$uid = (int)$_SESSION['user']['id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$error = 'Invalid request. Please refresh.';
	} else {
		$title = trim($_POST['title'] ?? '');
		$author = trim($_POST['author'] ?? '');
		$note = trim($_POST['note'] ?? '');
		
		if (empty($title) || empty($author)) {
			$error = 'Title and author are required.';
		} else {
			$stmt = mysqli_prepare($conn, "INSERT INTO book_suggestions (user_id, title, author, note, status) VALUES (?, ?, ?, ?, 'pending')");
			mysqli_stmt_bind_param($stmt, 'isss', $uid, $title, $author, $note);
			
			if (mysqli_stmt_execute($stmt)) {
				$success = 'Book suggestion submitted! Admin will review it.';
				create_notification($conn, $uid, 'Book Suggestion Submitted', "Your suggestion for '{$title}' has been submitted.", 'info');
			} else {
				$error = 'Failed to submit suggestion. Please try again.';
			}
			mysqli_stmt_close($stmt);
		}
	}
}

$mySuggestions = mysqli_query($conn, "SELECT id, title, author, note, status, created_at FROM book_suggestions WHERE user_id={$uid} ORDER BY created_at DESC");
?>
<h3 class="mb-3">Suggest a New Book</h3>

<?php if ($error): ?>
	<div class="alert alert-danger alert-dismissible fade show">
		<?php echo h($error); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
<?php endif; ?>

<?php if ($success): ?>
	<div class="alert alert-success alert-dismissible fade show">
		<?php echo h($success); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
<?php endif; ?>

<div class="row g-3">
	<div class="col-lg-5">
		<div class="card">
			<div class="card-body">
				<h5 class="card-title">Submit Book Suggestion</h5>
				<form method="post">
					<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
					<div class="mb-3">
						<label class="form-label">Book Title <span class="text-danger">*</span></label>
						<input type="text" name="title" class="form-control" required maxlength="200">
					</div>
					<div class="mb-3">
						<label class="form-label">Author <span class="text-danger">*</span></label>
						<input type="text" name="author" class="form-control" required maxlength="200">
					</div>
					<div class="mb-3">
						<label class="form-label">Note (Optional)</label>
						<textarea name="note" class="form-control" rows="3" maxlength="500" placeholder="Why do you think this book should be added?"></textarea>
					</div>
					<button type="submit" class="btn btn-primary">Submit Suggestion</button>
				</form>
			</div>
		</div>
	</div>
	<div class="col-lg-7">
		<div class="card">
			<div class="card-body">
				<h5 class="card-title">My Suggestions</h5>
				<div class="table-responsive">
					<table class="table table-striped">
						<thead>
							<tr>
								<th>Title</th>
								<th>Author</th>
								<th>Status</th>
								<th>Submitted</th>
							</tr>
						</thead>
						<tbody>
							<?php if (mysqli_num_rows($mySuggestions) === 0): ?>
								<tr><td colspan="4" class="text-center text-muted">No suggestions yet</td></tr>
							<?php else: ?>
								<?php while ($suggestion = mysqli_fetch_assoc($mySuggestions)): ?>
								<tr>
									<td><?php echo h($suggestion['title']); ?></td>
									<td><?php echo h($suggestion['author']); ?></td>
									<td>
										<span class="badge text-bg-<?php 
											echo $suggestion['status']==='approved'?'success':($suggestion['status']==='rejected'?'danger':'warning'); 
										?>">
											<?php echo h(ucfirst($suggestion['status'])); ?>
										</span>
									</td>
									<td><?php echo date('Y-m-d', strtotime($suggestion['created_at'])); ?></td>
								</tr>
								<?php endwhile; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

