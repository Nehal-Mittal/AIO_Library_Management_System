<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$_SESSION['flash_error'] = 'Invalid request. Please refresh and try again.';
		header('Location: /admin/manage_requests.php');
		exit;
	}
	$action = $_POST['action'] ?? '';
	$requestId = (int)($_POST['rid'] ?? 0);
	if ($requestId > 0) {
		$stmt = mysqli_prepare($conn, "SELECT br.*, u.name AS requester_name, u.email AS requester_email, u.role FROM book_requests br JOIN users u ON u.id = br.user_id WHERE br.id=? LIMIT 1");
		mysqli_stmt_bind_param($stmt, 'i', $requestId);
		mysqli_stmt_execute($stmt);
		$res = mysqli_stmt_get_result($stmt);
		$request = mysqli_fetch_assoc($res);
		mysqli_stmt_close($stmt);
		if ($request) {
			if ($action === 'approve') {
				if ($request['status'] !== 'pending') {
					$_SESSION['flash_error'] = 'Only pending requests can be approved.';
				} elseif (!can_issue_more($conn, (int)$request['user_id'], $request['role'])) {
					$_SESSION['flash_error'] = "Issue limit reached for {$request['requester_name']}.";
				} else {
					$bookRow = null;
					if (!empty($request['book_id'])) {
						$byId = mysqli_prepare($conn, "SELECT id, title FROM books WHERE id=? AND available_copies > 0 LIMIT 1");
						mysqli_stmt_bind_param($byId, 'i', $request['book_id']);
						mysqli_stmt_execute($byId);
						$resBook = mysqli_stmt_get_result($byId);
						$bookRow = mysqli_fetch_assoc($resBook);
						mysqli_stmt_close($byId);
					}
					if (!$bookRow) {
						$byTitle = mysqli_prepare($conn, "SELECT id, title FROM books WHERE title=? AND available_copies > 0 LIMIT 1");
						mysqli_stmt_bind_param($byTitle, 's', $request['title']);
						mysqli_stmt_execute($byTitle);
						$resBook = mysqli_stmt_get_result($byTitle);
						$bookRow = mysqli_fetch_assoc($resBook);
						mysqli_stmt_close($byTitle);
					}
					if ($bookRow) {
						$issueDate = (new DateTime('today'))->format('Y-m-d');
						$dueDate = (new DateTime('today'))->modify('+' . LOAN_DAYS . ' days')->format('Y-m-d');
						$rate = FINE_PER_DAY;
						$newIssue = mysqli_prepare($conn, "INSERT INTO issued_books (book_id, user_id, issue_date, due_date, fine, fine_status, fine_rate) VALUES (?, ?, ?, ?, 0, 'unpaid', ?)");
						mysqli_stmt_bind_param($newIssue, 'iissd', $bookRow['id'], $request['user_id'], $issueDate, $dueDate, $rate);
						mysqli_stmt_execute($newIssue);
						mysqli_stmt_close($newIssue);

						$updBook = mysqli_prepare($conn, "UPDATE books SET available_copies = CASE WHEN available_copies > 0 THEN available_copies - 1 ELSE 0 END, status = CASE WHEN available_copies <= 1 THEN 'issued' ELSE 'available' END WHERE id=?");
						mysqli_stmt_bind_param($updBook, 'i', $bookRow['id']);
						mysqli_stmt_execute($updBook);
						mysqli_stmt_close($updBook);

						$updReq = mysqli_prepare($conn, "UPDATE book_requests SET status='approved' WHERE id=?");
						mysqli_stmt_bind_param($updReq, 'i', $requestId);
						mysqli_stmt_execute($updReq);
						mysqli_stmt_close($updReq);

						$message = "<p>Hello {$request['requester_name']},</p><p>Your request for <strong>{$bookRow['title']}</strong> has been approved. Please collect the book by {$issueDate}. Due date: {$dueDate}.</p>";
						send_email($request['requester_email'], 'Book request approved', $message);

						// Create in-app notification with funny message
						sendFunnyNotification($conn, (int)$request['user_id'], 'issued', ['title' => $bookRow['title']]);

						$_SESSION['flash_success'] = "Issued '{$bookRow['title']}' to {$request['requester_name']}.";
					} else {
						$_SESSION['flash_error'] = 'No available copies found for this title.';
					}
				}
			} elseif ($action === 'reject') {
				if ($request['status'] === 'pending') {
					$rej = mysqli_prepare($conn, "UPDATE book_requests SET status='rejected' WHERE id=?");
					mysqli_stmt_bind_param($rej, 'i', $requestId);
					mysqli_stmt_execute($rej);
					mysqli_stmt_close($rej);
					$_SESSION['flash_success'] = 'Request rejected.';
				}
			}
		}
	}
	header('Location: /admin/manage_requests.php');
	exit;
}

include __DIR__ . '/../includes/header.php';

$requests = mysqli_query($conn, "SELECT br.id, br.book_id, br.title, br.author, br.department, br.status, br.created_at, u.name, u.email, u.role FROM book_requests br JOIN users u ON br.user_id=u.id ORDER BY br.created_at DESC, br.id DESC");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
	<div>
		<h3 class="mb-0">Manage Book Requests</h3>
		<p class="text-muted mb-0">Approve within role-based issue limits (students max <?php echo MAX_STUDENT_ISSUES; ?>, teachers max <?php echo MAX_TEACHER_ISSUES; ?>).</p>
	</div>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
	<div class="alert alert-warning alert-dismissible fade show" role="alert">
		<?php echo h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
		<button class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	</div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_success'])): ?>
	<div class="alert alert-success alert-dismissible fade show" role="alert">
		<?php echo h($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
		<button class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	</div>
<?php endif; ?>

<div class="card">
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-striped align-middle">
				<thead>
					<tr>
						<th>ID</th>
						<th>Requester</th>
						<th>Email</th>
						<th>Role</th>
						<th>Department</th>
						<th>Title</th>
						<th>Author</th>
						<th>Requested</th>
						<th>Status</th>
						<th class="text-end">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php while ($r = mysqli_fetch_assoc($requests)): ?>
					<tr>
						<td><?php echo (int)$r['id']; ?></td>
						<td><?php echo h($r['name']); ?></td>
						<td><?php echo h($r['email']); ?></td>
						<td><span class="badge text-bg-<?php echo $r['role']==='teacher'?'info':'primary'; ?>"><?php echo h($r['role']); ?></span></td>
						<td><?php echo h($r['department']); ?></td>
						<td><?php echo h($r['title']); ?></td>
						<td><?php echo h($r['author']); ?></td>
						<td><?php echo h($r['created_at']); ?></td>
						<td><span class="badge text-bg-<?php echo $r['status']==='approved'?'success':($r['status']==='rejected'?'danger':'warning'); ?>"><?php echo h($r['status']); ?></span></td>
						<td class="text-end">
							<form method="post" class="d-inline" onsubmit="return confirm('Approve this request?');">
								<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
								<input type="hidden" name="rid" value="<?php echo (int)$r['id']; ?>">
								<input type="hidden" name="action" value="approve">
								<button type="submit" class="btn btn-sm btn-success" <?php echo $r['status']!=='pending'?'disabled':''; ?>>Approve</button>
							</form>
							<form method="post" class="d-inline" onsubmit="return confirm('Reject this request?');">
								<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
								<input type="hidden" name="rid" value="<?php echo (int)$r['id']; ?>">
								<input type="hidden" name="action" value="reject">
								<button type="submit" class="btn btn-sm btn-outline-danger" <?php echo $r['status']!=='pending'?'disabled':''; ?>>Reject</button>
							</form>
						</td>
					</tr>
					<?php endwhile; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

