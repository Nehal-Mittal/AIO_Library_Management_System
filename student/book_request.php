<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$uid = (int)$_SESSION['user']['id'];
$canRequest = can_issue_more($conn, $uid, 'student');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$_SESSION['flash_error'] = 'Invalid request.';
	} elseif (!$canRequest) {
		$_SESSION['flash_error'] = 'Please return a book before requesting a new one.';
	} else {
		$book_id = (int)($_POST['book_id'] ?? 0);
		$department = trim($_POST['department'] ?? '');
		if ($book_id > 0) {
			$bookStmt = mysqli_prepare($conn, "SELECT title, author FROM books WHERE id=? LIMIT 1");
			mysqli_stmt_bind_param($bookStmt, 'i', $book_id);
			mysqli_stmt_execute($bookStmt);
			$res = mysqli_stmt_get_result($bookStmt);
			$book = mysqli_fetch_assoc($res);
			mysqli_stmt_close($bookStmt);
			if ($book) {
				$stmt = mysqli_prepare($conn, "INSERT INTO book_requests (user_id, book_id, title, author, department, status) VALUES (?, ?, ?, ?, ?, 'pending')");
				mysqli_stmt_bind_param($stmt, 'iisss', $uid, $book_id, $book['title'], $book['author'], $department);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				$_SESSION['flash_success'] = 'Request submitted successfully!';
			}
		}
	}
	header('Location: /student/book_request.php');
	exit;
}

$requests = mysqli_query($conn, "SELECT id, title, author, department, status FROM book_requests WHERE user_id={$uid} ORDER BY id DESC");
$departments = mysqli_query($conn, "SELECT name FROM departments ORDER BY name");
$prefillBook = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;

include __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-3">Request a Book</h3>
<?php if (!empty($_SESSION['flash_error'])): ?>
	<div class="alert alert-warning alert-dismissible fade show" role="alert"><?php echo h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_success'])): ?>
	<div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo h($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-3">
	<div class="col-lg-5">
		<div class="card">
			<div class="card-body">
				<form method="post">
					<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department" required data-dept id="department">
							<option value="">Select department</option>
							<?php while ($d = mysqli_fetch_assoc($departments)): ?>
							<option value="<?php echo h($d['name']); ?>"><?php echo h($d['name']); ?></option>
							<?php endwhile; ?>
						</select>
					</div>
                    <input type="hidden" name="book_id" id="book_id" value="<?php echo $prefillBook ?: ''; ?>">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <select class="form-select" name="book_title" id="book_title" required>
                            <option value="">Select book</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Author</label>
                        <input class="form-control" name="author" id="author" readonly>
                    </div>
					<button class="btn btn-primary" type="submit" <?php echo !$canRequest ? 'disabled' : ''; ?>>Submit</button>
					<?php if (!$canRequest): ?>
						<p class="small text-danger mt-2 mb-0">Issuing limit reached (<?php echo MAX_STUDENT_ISSUES; ?> books). Return a book to request more.</p>
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


