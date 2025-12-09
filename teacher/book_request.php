<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');

$uid = (int)$_SESSION['user']['id'];
$canRequest = can_issue_more($conn, $uid, 'teacher');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$_SESSION['flash_error'] = 'Invalid request.';
	} elseif (!$canRequest) {
		$_SESSION['flash_error'] = 'Please return a book to free up an issue slot.';
	} else {
		$title = trim($_POST['title'] ?? '');
		$author = trim($_POST['author'] ?? '');
		$department = trim($_POST['department'] ?? '');
		if ($title !== '' && $department !== '') {
			$stmt = mysqli_prepare($conn, "INSERT INTO book_requests (user_id, title, author, department, status) VALUES (?, ?, ?, ?, 'pending')");
			mysqli_stmt_bind_param($stmt, 'isss', $uid, $title, $author, $department);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			$_SESSION['flash_success'] = 'Request submitted';
		}
	}
	header('Location: /teacher/book_request.php');
	exit;
}

$requests = mysqli_query($conn, "SELECT id, title, author, department, status FROM book_requests WHERE user_id={$uid} ORDER BY id DESC");
$departments = mysqli_query($conn, "SELECT name FROM departments ORDER BY name");
$prefillTitle = trim($_GET['title'] ?? '');

include __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-3">Request a Book</h3>
<?php if (!empty($_SESSION['flash_error'])): ?><div class="alert alert-warning alert-dismissible fade show" role="alert"><?php echo h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if (!empty($_SESSION['flash_success'])): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo h($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-3">
	<div class="col-lg-5">
		<div class="card">
			<div class="card-body">
				<form method="post">
					<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
					<div class="mb-3">
						<label class="form-label">Department</label>
						<select class="form-select" name="department" required data-dept>
							<option value="">Select department</option>
							<?php while ($d = mysqli_fetch_assoc($departments)): ?>
							<option value="<?php echo h($d['name']); ?>"><?php echo h($d['name']); ?></option>
							<?php endwhile; ?>
						</select>
					</div>
					<div class="mb-3" data-book-autocomplete>
						<label class="form-label">Title</label>
						<input class="form-control" name="title" required data-title value="<?php echo h($prefillTitle); ?>">
					</div>
					<div class="mb-3">
						<label class="form-label">Author</label>
						<input class="form-control" name="author" data-author>
					</div>
					<button class="btn btn-primary" type="submit" <?php echo !$canRequest ? 'disabled' : ''; ?>>Submit</button>
					<?php if (!$canRequest): ?>
						<p class="small text-danger mt-2 mb-0">Teachers may issue up to <?php echo MAX_TEACHER_ISSUES; ?> books.</p>
					<?php endif; ?>
				</form>
			</div>
		</div>
	</div>
	<div class="col-lg-7">
		<div class="card">
			<div class="card-body">
				<h5 class="card-title">My Requests</h5>
				<div class="table-responsive">
					<table class="table table-striped"><thead><tr><th>Title</th><th>Author</th><th>Department</th><th>Status</th></tr></thead><tbody>
					<?php while ($r = mysqli_fetch_assoc($requests)): ?>
					<tr><td><?php echo h($r['title']); ?></td><td><?php echo h($r['author']); ?></td><td><?php echo h($r['department']); ?></td><td><span class="badge text-bg-<?php echo $r['status']==='approved'?'success':($r['status']==='rejected'?'danger':'warning'); ?>"><?php echo h($r['status']); ?></span></td></tr>
					<?php endwhile; ?>
					</tbody></table>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="/assets/js/book_autocomplete.js"></script>
