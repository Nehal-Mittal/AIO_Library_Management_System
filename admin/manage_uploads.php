<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

// Handle approval/rejection BEFORE including header (to avoid "headers already sent" error)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$_SESSION['flash_error'] = 'Invalid request.';
	} else {
		$action = $_POST['action'] ?? '';
		$uploadId = (int)($_POST['upload_id'] ?? 0);
		
		if ($uploadId > 0 && in_array($action, ['approve', 'reject'])) {
			$status = $action === 'approve' ? 'approved' : 'rejected';
			$stmt = mysqli_prepare($conn, "UPDATE uploaded_notes SET status=? WHERE id=?");
			mysqli_stmt_bind_param($stmt, 'si', $status, $uploadId);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			
			// Get upload details for notification
			$uploadStmt = mysqli_prepare($conn, "SELECT user_id, title FROM uploaded_notes WHERE id=? LIMIT 1");
			mysqli_stmt_bind_param($uploadStmt, 'i', $uploadId);
			mysqli_stmt_execute($uploadStmt);
			$uploadRes = mysqli_stmt_get_result($uploadStmt);
			$upload = mysqli_fetch_assoc($uploadRes);
			mysqli_stmt_close($uploadStmt);
			
			if ($upload) {
				$message = $action === 'approve' 
					? "Your notes '{$upload['title']}' have been approved!" 
					: "Your notes '{$upload['title']}' have been rejected.";
				create_notification($conn, (int)$upload['user_id'], 'Notes Status Update', $message, $action === 'approve' ? 'success' : 'danger');
			}
			
			$_SESSION['flash_success'] = 'Status updated successfully.';
		}
	}
	header('Location: /admin/manage_uploads.php');
	exit;
}

include __DIR__ . '/../includes/header.php';

$uploads = mysqli_query($conn, "SELECT un.id, un.title, un.description, un.file_path, un.file_type, un.file_size, un.status, un.created_at, u.name AS user_name, u.email FROM uploaded_notes un JOIN users u ON u.id=un.user_id ORDER BY un.created_at DESC");
?>
<h3 class="mb-3">Manage Uploaded Notes</h3>

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
						<th>User</th>
						<th>Type</th>
						<th>Size</th>
						<th>Status</th>
						<th>Uploaded</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if (mysqli_num_rows($uploads) === 0): ?>
						<tr><td colspan="8" class="text-center text-muted">No uploads found</td></tr>
					<?php else: ?>
						<?php while ($upload = mysqli_fetch_assoc($uploads)): ?>
						<tr>
							<td><?php echo (int)$upload['id']; ?></td>
							<td><?php echo h($upload['title']); ?></td>
							<td><?php echo h($upload['user_name']); ?><br><small class="text-muted"><?php echo h($upload['email']); ?></small></td>
							<td><span class="badge text-bg-<?php echo $upload['file_type']==='pdf'?'danger':'info'; ?>"><?php echo strtoupper($upload['file_type']); ?></span></td>
							<td><?php echo number_format($upload['file_size'] / 1024, 2); ?> KB</td>
							<td>
								<span class="badge text-bg-<?php 
									echo $upload['status']==='approved'?'success':($upload['status']==='rejected'?'danger':'warning'); 
								?>">
									<?php echo h(ucfirst($upload['status'])); ?>
								</span>
							</td>
							<td><?php echo date('Y-m-d H:i', strtotime($upload['created_at'])); ?></td>
							<td>
								<a href="<?php echo h($upload['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
								<?php if ($upload['status'] === 'pending'): ?>
									<form method="post" class="d-inline">
										<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
										<input type="hidden" name="upload_id" value="<?php echo (int)$upload['id']; ?>">
										<input type="hidden" name="action" value="approve">
										<button type="submit" class="btn btn-sm btn-success">Approve</button>
									</form>
									<form method="post" class="d-inline">
										<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
										<input type="hidden" name="upload_id" value="<?php echo (int)$upload['id']; ?>">
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

