<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

// Update overdue fines daily
$fineUpdateStats = ['updated' => 0, 'errors' => 0];
if (!isset($_SESSION['last_fine_update']) || (time() - $_SESSION['last_fine_update']) > 3600) {
	$fineUpdateStats = update_overdue_fines($conn);
	$_SESSION['last_fine_update'] = time();
}

// Send due date notifications
$notificationSummary = ['due_soon' => 0, 'due_today' => 0, 'overdue' => 0, 'errors' => 0];
if (!isset($_SESSION['last_due_check']) || (time() - $_SESSION['last_due_check']) > 600) {
	$notificationSummary = ensure_due_notifications($conn);
	$_SESSION['last_due_check'] = time();
}

// Quick counts
$counts = [
	'books' => 0,
	'users' => 0,
	'issued' => 0,
	'pending_notices' => 0,
	'due_today' => 0,
	'overdue' => 0,
];
$res = mysqli_query($conn, "SELECT COUNT(*) c FROM books");
$counts['books'] = (int)mysqli_fetch_assoc($res)['c'];
$res = mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE role IN ('student','teacher')");
$counts['users'] = (int)mysqli_fetch_assoc($res)['c'];
$res = mysqli_query($conn, "SELECT COUNT(*) c FROM issued_books WHERE return_date IS NULL");
$counts['issued'] = (int)mysqli_fetch_assoc($res)['c'];
$res = mysqli_query($conn, "SELECT COUNT(*) c FROM notices WHERE status = 'pending'");
$counts['pending_notices'] = (int)mysqli_fetch_assoc($res)['c'];
$dueRes = mysqli_query($conn, "SELECT COUNT(*) c FROM issued_books WHERE return_date IS NULL AND COALESCE(due_date, DATE_ADD(issue_date, INTERVAL " . LOAN_DAYS . " DAY)) = CURDATE()");
$counts['due_today'] = (int)mysqli_fetch_assoc($dueRes)['c'];
$overRes = mysqli_query($conn, "SELECT COUNT(*) c FROM issued_books WHERE return_date IS NULL AND COALESCE(due_date, DATE_ADD(issue_date, INTERVAL " . LOAN_DAYS . " DAY)) < CURDATE()");
$counts['overdue'] = (int)mysqli_fetch_assoc($overRes)['c'];

include __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4">Admin Dashboard</h3>

<?php if ($fineUpdateStats['updated'] > 0 || $notificationSummary['due_soon'] || $notificationSummary['due_today'] || $notificationSummary['overdue']): ?>
	<div class="alert alert-info alert-dismissible fade show" role="alert">
		<?php if ($fineUpdateStats['updated'] > 0): ?>
			<strong>Fines Updated:</strong> <?php echo (int)$fineUpdateStats['updated']; ?> overdue book fine(s) updated. 
		<?php endif; ?>
		<?php if ($notificationSummary['due_soon'] || $notificationSummary['due_today'] || $notificationSummary['overdue']): ?>
			<strong>Notifications Sent:</strong> 
			<?php if ($notificationSummary['due_soon'] > 0): ?>
				<?php echo (int)$notificationSummary['due_soon']; ?> due soon, 
			<?php endif; ?>
			<?php if ($notificationSummary['due_today'] > 0): ?>
				<?php echo (int)$notificationSummary['due_today']; ?> due today, 
			<?php endif; ?>
			<?php if ($notificationSummary['overdue'] > 0): ?>
				<?php echo (int)$notificationSummary['overdue']; ?> overdue reminder(s).
			<?php endif; ?>
		<?php endif; ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	</div>
<?php endif; ?>

<div class="row g-3">
	<div class="col-md-4 col-lg-2">
		<div class="card shadow-sm">
			<div class="card-body">
				<h6 class="text-muted">Total Books</h6>
				<div class="display-6"><?php echo (int)$counts['books']; ?></div>
			</div>
		</div>
	</div>
	<div class="col-md-4 col-lg-2">
		<div class="card shadow-sm">
			<div class="card-body">
				<h6 class="text-muted">Members</h6>
				<div class="display-6"><?php echo (int)$counts['users']; ?></div>
			</div>
		</div>
	</div>
	<div class="col-md-4 col-lg-2">
		<div class="card shadow-sm">
			<div class="card-body">
				<h6 class="text-muted">Issued (Open)</h6>
				<div class="display-6"><?php echo (int)$counts['issued']; ?></div>
			</div>
		</div>
	</div>
	<div class="col-md-4 col-lg-2">
		<div class="card shadow-sm">
			<div class="card-body">
				<h6 class="text-muted">Pending Notices</h6>
				<div class="display-6"><?php echo (int)$counts['pending_notices']; ?></div>
			</div>
		</div>
	</div>
	<div class="col-md-4 col-lg-2">
		<div class="card shadow-sm border-info">
			<div class="card-body">
				<h6 class="text-muted">Due Today</h6>
				<div class="display-6 text-info"><?php echo (int)$counts['due_today']; ?></div>
			</div>
		</div>
	</div>
	<div class="col-md-4 col-lg-2">
		<div class="card shadow-sm border-danger">
			<div class="card-body">
				<h6 class="text-muted">Overdue</h6>
				<div class="display-6 text-danger"><?php echo (int)$counts['overdue']; ?></div>
			</div>
		</div>
	</div>
</div>

<div class="mt-4 d-flex gap-2 flex-wrap">
	<a href="/admin/manage_books.php" class="btn btn-primary">Manage Books</a>
	<a href="/admin/manage_users.php" class="btn btn-secondary">Manage Users</a>
	<a href="/admin/manage_notices.php" class="btn btn-warning">Approve Notices</a>
	<a href="/admin/manage_requests.php" class="btn btn-info">Requests</a>
	<a href="/admin/issued_books.php" class="btn btn-outline-primary">Issued Records</a>
	<a href="/admin/generate_reports.php" class="btn btn-success">Reports</a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
