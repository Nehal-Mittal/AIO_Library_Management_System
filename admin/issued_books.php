<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$_SESSION['flash_error'] = 'Invalid request token.';
	} else {
		$action = $_POST['action'] ?? '';
		$issueId = (int)($_POST['iid'] ?? 0);
		if ($issueId > 0) {
			if ($action === 'return') {
				$getIssue = mysqli_prepare($conn, "SELECT id, book_id, issue_date, due_date, fine_rate, return_date FROM issued_books WHERE id=? LIMIT 1");
				mysqli_stmt_bind_param($getIssue, 'i', $issueId);
				mysqli_stmt_execute($getIssue);
				$res = mysqli_stmt_get_result($getIssue);
				$issue = mysqli_fetch_assoc($res);
				mysqli_stmt_close($getIssue);
				if ($issue && empty($issue['return_date'])) {
					$today = (new DateTime('today'))->format('Y-m-d');
					$totalFine = compute_fine($issue['issue_date'], $today, $issue['due_date'], (float)$issue['fine_rate']);
					$status = $totalFine > 0 ? 'unpaid' : 'paid';
					$upd = mysqli_prepare($conn, "UPDATE issued_books SET return_date=?, fine=?, fine_status=? WHERE id=?");
					mysqli_stmt_bind_param($upd, 'sdsi', $today, $totalFine, $status, $issueId);
					mysqli_stmt_execute($upd);
					mysqli_stmt_close($upd);

					$updBook = mysqli_prepare($conn, "UPDATE books SET available_copies = LEAST(quantity, available_copies + 1), status='available' WHERE id=?");
					mysqli_stmt_bind_param($updBook, 'i', $issue['book_id']);
					mysqli_stmt_execute($updBook);
					mysqli_stmt_close($updBook);

					// Get book and user info for notification
					$getInfo = mysqli_prepare($conn, "SELECT b.title, ib.user_id FROM issued_books ib JOIN books b ON b.id=ib.book_id WHERE ib.id=? LIMIT 1");
					mysqli_stmt_bind_param($getInfo, 'i', $issueId);
					mysqli_stmt_execute($getInfo);
					$resInfo = mysqli_stmt_get_result($getInfo);
					$info = mysqli_fetch_assoc($resInfo);
					mysqli_stmt_close($getInfo);
					
					if ($info) {
						// Send funny notification for book return
						sendFunnyNotification($conn, (int)$info['user_id'], 'returned', [
							'title' => $info['title'],
							'fine' => $totalFine
						]);
					}

					$_SESSION['flash_success'] = 'Book marked as returned.';
				}
			} elseif ($action === 'toggle_fine') {
				$newStatus = $_POST['to'] === 'paid' ? 'paid' : 'unpaid';
				$upd = mysqli_prepare($conn, "UPDATE issued_books SET fine_status=? WHERE id=?");
				mysqli_stmt_bind_param($upd, 'si', $newStatus, $issueId);
				mysqli_stmt_execute($upd);
				mysqli_stmt_close($upd);
				$_SESSION['flash_success'] = 'Fine status updated.';
			}
		}
	}
	header('Location: /admin/issued_books.php');
	exit;
}

include __DIR__ . '/../includes/header.php';

$rows = mysqli_query($conn, "SELECT ib.id, ib.issue_date, ib.due_date, ib.return_date, ib.fine, ib.fine_status, ib.fine_rate, b.title, b.author, u.name, u.email, u.role
	FROM issued_books ib
	JOIN books b ON ib.book_id=b.id
	JOIN users u ON ib.user_id=u.id
	ORDER BY ib.issue_date DESC");
?>

<h3 class="mb-3">Issued Books & Fines</h3>

<?php if (!empty($_SESSION['flash_error'])): ?>
	<div class="alert alert-warning alert-dismissible fade show" role="alert">
		<?php echo h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_success'])): ?>
	<div class="alert alert-success alert-dismissible fade show" role="alert">
		<?php echo h($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
<?php endif; ?>

<div class="card">
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-striped align-middle">
				<thead>
					<tr>
						<th>#</th>
						<th>Book</th>
						<th>Borrower</th>
						<th>Issued</th>
						<th>Due</th>
						<th>Returned</th>
						<th>Live Fine</th>
						<th>Recorded Fine</th>
						<th>Fine Status</th>
						<th class="text-end">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php while ($r = mysqli_fetch_assoc($rows)): ?>
					<?php
						$dueDate = $r['due_date'] ?: compute_due_date($r['issue_date'])->format('Y-m-d');
						$liveFine = $r['return_date'] ? $r['fine'] : compute_fine($r['issue_date'], null, $dueDate, (float)$r['fine_rate']);
					?>
					<tr>
						<td><?php echo (int)$r['id']; ?></td>
						<td>
							<div class="fw-semibold"><?php echo h($r['title']); ?></div>
							<small class="text-muted"><?php echo h($r['author']); ?></small>
						</td>
						<td>
							<div><?php echo h($r['name']); ?></div>
							<small class="text-muted"><?php echo h($r['email']); ?> · <?php echo h($r['role']); ?></small>
						</td>
						<td><?php echo h($r['issue_date']); ?></td>
						<td><?php echo h($dueDate); ?></td>
						<td><?php echo h($r['return_date'] ?? '—'); ?></td>
						<td><?php echo number_format($liveFine, 2); ?></td>
						<td><?php echo number_format((float)$r['fine'], 2); ?></td>
						<td>
							<span class="badge text-bg-<?php echo $r['fine_status']==='paid'?'success':'secondary'; ?>">
								<?php echo h(ucfirst($r['fine_status'])); ?>
							</span>
						</td>
						<td class="text-end">
							<form method="post" class="d-inline">
								<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
								<input type="hidden" name="iid" value="<?php echo (int)$r['id']; ?>">
								<input type="hidden" name="action" value="toggle_fine">
								<input type="hidden" name="to" value="<?php echo $r['fine_status']==='paid'?'unpaid':'paid'; ?>">
								<button type="submit" class="btn btn-sm btn-outline-<?php echo $r['fine_status']==='paid'?'warning':'success'; ?>"><?php echo $r['fine_status']==='paid'?'Mark Unpaid':'Mark Paid'; ?></button>
							</form>
							<form method="post" class="d-inline ms-1" onsubmit="return confirm('Mark this book as returned?');">
								<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
								<input type="hidden" name="iid" value="<?php echo (int)$r['id']; ?>">
								<input type="hidden" name="action" value="return">
								<button type="submit" class="btn btn-sm btn-primary" <?php echo $r['return_date'] ? 'disabled' : ''; ?>>Return</button>
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

