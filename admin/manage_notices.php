<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
include __DIR__ . '/../includes/header.php';

$msg = '';
if (isset($_GET['approve'])) {
	$id = (int)$_GET['approve'];
	$stmt = mysqli_prepare($conn, "UPDATE notices SET status='approved', approved_by=? WHERE id=?");
	$adminId = $_SESSION['user']['id'];
	mysqli_stmt_bind_param($stmt, 'ii', $adminId, $id);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	$msg = 'Notice approved';
}
if (isset($_GET['reject'])) {
	$id = (int)$_GET['reject'];
	mysqli_query($conn, "UPDATE notices SET status='rejected' WHERE id = $id");
	$msg = 'Notice rejected';
}

$pending = mysqli_query($conn, "SELECT n.*, u.name AS created_by_name FROM notices n LEFT JOIN users u ON n.created_by = u.id WHERE n.status='pending' ORDER BY n.event_date DESC");
$approved = mysqli_query($conn, "SELECT n.*, u.name AS created_by_name FROM notices n LEFT JOIN users u ON n.created_by = u.id WHERE n.status='approved' ORDER BY n.event_date DESC LIMIT 10");
?>

<h3 class="mb-3">Manage Notices</h3>
<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo h($msg); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-3">
	<div class="col-lg-6">
		<div class="card">
			<div class="card-body">
				<h5 class="card-title">Pending Approval</h5>
				<div class="table-responsive">
					<table class="table table-striped">
						<thead><tr><th>Title</th><th>Organizer</th><th>Date</th><th>By</th><th>Actions</th></tr></thead>
						<tbody>
							<?php while ($n = mysqli_fetch_assoc($pending)): ?>
							<tr>
								<td><?php echo h($n['title']); ?></td>
								<td><?php echo h($n['organizer']); ?></td>
								<td><?php echo h($n['event_date']); ?></td>
								<td><?php echo h($n['created_by_name']); ?></td>
								<td>
									<a class="btn btn-sm btn-success" href="?approve=<?php echo (int)$n['id']; ?>">Approve</a>
									<a class="btn btn-sm btn-outline-danger" href="?reject=<?php echo (int)$n['id']; ?>">Reject</a>
								</td>
							</tr>
							<?php endwhile; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
	<div class="col-lg-6">
		<div class="card">
			<div class="card-body">
				<h5 class="card-title">Recently Approved</h5>
				<div class="table-responsive">
					<table class="table table-striped">
						<thead><tr><th>Title</th><th>Organizer</th><th>Date</th><th>Status</th></tr></thead>
						<tbody>
							<?php while ($n = mysqli_fetch_assoc($approved)): ?>
							<tr>
								<td><?php echo h($n['title']); ?></td>
								<td><?php echo h($n['organizer']); ?></td>
								<td><?php echo h($n['event_date']); ?></td>
								<td><span class="badge text-bg-success">approved</span></td>
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


