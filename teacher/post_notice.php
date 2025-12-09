<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');
include __DIR__ . '/../includes/header.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$title = trim($_POST['title'] ?? '');
	$description = trim($_POST['description'] ?? '');
	$organizer = trim($_POST['organizer'] ?? '');
	$event_date = trim($_POST['event_date'] ?? '');
	if ($title !== '' && $event_date !== '') {
		$stmt = mysqli_prepare($conn, "INSERT INTO notices (title, description, organizer, event_date, created_by, status) VALUES (?, ?, ?, ?, ?, 'pending')");
		$uid = (int)$_SESSION['user']['id'];
		mysqli_stmt_bind_param($stmt, 'ssssi', $title, $description, $organizer, $event_date, $uid);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		$msg = 'Notice submitted for approval';
	}
}
?>

<h3 class="mb-3">Post Notice</h3>
<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo h($msg); ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card">
	<div class="card-body">
		<form method="post">
			<div class="mb-3">
				<label class="form-label">Title</label>
				<input type="text" name="title" class="form-control" required>
			</div>
			<div class="mb-3">
				<label class="form-label">Description</label>
				<textarea name="description" class="form-control" rows="4"></textarea>
			</div>
			<div class="mb-3">
				<label class="form-label">Organizer</label>
				<input type="text" name="organizer" class="form-control">
			</div>
			<div class="mb-3">
				<label class="form-label">Event Date</label>
				<input type="date" name="event_date" class="form-control" required>
			</div>
			<button class="btn btn-primary" type="submit">Submit</button>
		</form>
	</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


