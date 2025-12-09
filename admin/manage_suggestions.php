<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

// Handle approval/rejection BEFORE including header (to avoid "headers already sent" error)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$_SESSION['flash_error'] = 'Invalid request.';
	} else {
		$action = $_POST['action'] ?? '';
		$suggestionId = (int)($_POST['suggestion_id'] ?? 0);
		
		if ($suggestionId > 0 && in_array($action, ['approve', 'reject'])) {
			$status = $action === 'approve' ? 'approved' : 'rejected';
			$stmt = mysqli_prepare($conn, "UPDATE book_suggestions SET status=? WHERE id=?");
			mysqli_stmt_bind_param($stmt, 'si', $status, $suggestionId);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			
			// Get suggestion details for notification
			$sugStmt = mysqli_prepare($conn, "SELECT user_id, title FROM book_suggestions WHERE id=? LIMIT 1");
			mysqli_stmt_bind_param($sugStmt, 'i', $suggestionId);
			mysqli_stmt_execute($sugStmt);
			$sugRes = mysqli_stmt_get_result($sugStmt);
			$suggestion = mysqli_fetch_assoc($sugRes);
			mysqli_stmt_close($sugStmt);
			
			if ($suggestion) {
				$message = $action === 'approve' 
					? "Your book suggestion '{$suggestion['title']}' has been approved!" 
					: "Your book suggestion '{$suggestion['title']}' has been rejected.";
				create_notification($conn, (int)$suggestion['user_id'], 'Book Suggestion Update', $message, $action === 'approve' ? 'success' : 'danger');
			}
			
			$_SESSION['flash_success'] = 'Status updated successfully.';
		}
	}
	header('Location: /admin/manage_suggestions.php');
	exit;
}

include __DIR__ . '/../includes/header.php';

$suggestions = mysqli_query($conn, "SELECT bs.id, bs.title, bs.author, bs.note, bs.status, bs.created_at, u.name AS user_name, u.email FROM book_suggestions bs JOIN users u ON u.id=bs.user_id ORDER BY bs.created_at DESC");
?>
<h3 class="mb-3">Manage Book Suggestions</h3>

<?php if (!empty($_SESSION['flash_success'])): ?>
	<div class="alert alert-success alert-dismissible fade show">
		<?php echo h($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
<?php endif; ?>

<div class="card">
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Title</th>
						<th>Author</th>
						<th>User</th>
						<th>Note</th>
						<th>Status</th>
						<th>Suggested</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if (mysqli_num_rows($suggestions) === 0): ?>
						<tr><td colspan="8" class="text-center text-muted">No suggestions found</td></tr>
					<?php else: ?>
						<?php while ($suggestion = mysqli_fetch_assoc($suggestions)): ?>
						<tr>
							<td><?php echo (int)$suggestion['id']; ?></td>
							<td><?php echo h($suggestion['title']); ?></td>
							<td><?php echo h($suggestion['author']); ?></td>
							<td><?php echo h($suggestion['user_name']); ?><br><small class="text-muted"><?php echo h($suggestion['email']); ?></small></td>
							<td><?php echo h($suggestion['note'] ?: '-'); ?></td>
							<td>
								<span class="badge text-bg-<?php 
									echo $suggestion['status']==='approved'?'success':($suggestion['status']==='rejected'?'danger':'warning'); 
								?>">
									<?php echo h(ucfirst($suggestion['status'])); ?>
								</span>
							</td>
							<td><?php echo date('Y-m-d', strtotime($suggestion['created_at'])); ?></td>
							<td>
								<?php if ($suggestion['status'] === 'pending'): ?>
									<form method="post" class="d-inline">
										<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
										<input type="hidden" name="suggestion_id" value="<?php echo (int)$suggestion['id']; ?>">
										<input type="hidden" name="action" value="approve">
										<button type="submit" class="btn btn-sm btn-success">Approve</button>
									</form>
									<form method="post" class="d-inline">
										<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
										<input type="hidden" name="suggestion_id" value="<?php echo (int)$suggestion['id']; ?>">
										<input type="hidden" name="action" value="reject">
										<button type="submit" class="btn btn-sm btn-danger">Reject</button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
						<?php endwhile; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

