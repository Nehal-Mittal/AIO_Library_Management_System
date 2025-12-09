<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');
include __DIR__ . '/../includes/header.php';

$uid = (int)$_SESSION['user']['id'];
$issued = mysqli_query($conn, "SELECT b.title, ib.issue_date, ib.due_date, ib.return_date, ib.fine_status FROM issued_books ib JOIN books b ON ib.book_id=b.id WHERE ib.user_id=$uid ORDER BY ib.issue_date DESC LIMIT 5");
$dueSoon = mysqli_query($conn, "SELECT b.title, ib.issue_date, ib.due_date FROM issued_books ib JOIN books b ON ib.book_id=b.id WHERE ib.user_id=$uid AND ib.return_date IS NULL");
$recommendations = recommended_books($conn, $uid, 4);
?>

<h3 class="mb-3">Teacher Dashboard</h3>

<?php
if ($dueSoon && mysqli_num_rows($dueSoon) > 0) {
	$alerts = [];
	while ($row = mysqli_fetch_assoc($dueSoon)) {
		$due = compute_due_date($row['issue_date'], $row['due_date']);
		$today = new DateTime('today');
		$diff = (int)$today->diff($due)->format('%r%a');
		if ($diff <= 2 && $diff >= 0) {
			$alerts[] = 'Due soon: ' . h($row['title']) . ' (in ' . $diff . ' day' . ($diff===1?'':'s') . ')';
		} elseif ($diff < 0) {
			$alerts[] = 'Overdue: ' . h($row['title']) . ' (fine ' . number_format(compute_fine($row['issue_date'], null), 2) . ')';
		}
	}
	if (!empty($alerts)) {
		echo '<div class="alert alert-warning"><ul class="mb-0">';
		foreach ($alerts as $a) echo '<li>' . $a . '</li>';
		echo '</ul></div>';
	}
}
?>

<div class="row g-3">
	<div class="col-lg-6">
		<div class="card">
			<div class="card-body">
				<h5 class="card-title">Recent Issued Books</h5>
				<div class="table-responsive">
					<table class="table table-striped">
						<thead><tr><th>Title</th><th>Issued</th><th>Due Date</th><th>Return Date</th><th>Fine</th><th>Status</th></tr></thead>
						<tbody>
							<?php 
							mysqli_data_seek($issued, 0);
							while ($r = mysqli_fetch_assoc($issued)): 
								$calcFine = compute_fine($r['issue_date'], $r['return_date']);
								$dueDate = compute_due_date($r['issue_date'], $r['due_date'] ?? null);
							?>
							<tr>
								<td><?php echo h($r['title']); ?></td>
								<td><?php echo h($r['issue_date']); ?></td>
								<td><?php echo $dueDate->format('Y-m-d'); ?></td>
								<td><?php echo $r['return_date'] ? h($r['return_date']) : '<span class="text-muted">Not returned</span>'; ?></td>
								<td><?php echo number_format($calcFine, 2); ?></td>
								<td><span class="badge text-bg-<?php echo ($r['fine_status']==='paid'?'success':'secondary'); ?>"><?php echo h($r['fine_status'] ? $r['fine_status'] : 'unpaid'); ?></span></td>
							</tr>
							<?php endwhile; ?>
						</tbody>
					</table>
				</div>
				<a href="/teacher/issued_books.php" class="btn btn-outline-primary btn-sm">View all</a>
			</div>
		</div>
	</div>
	<div class="col-lg-6">
		<div class="card">
			<div class="card-body">
				<h5 class="card-title">Quick Actions</h5>
				<a href="/teacher/available_books.php" class="btn btn-primary me-2">Browse Books</a>
				<a href="/teacher/post_notice.php" class="btn btn-warning">Post Notice</a>
			</div>
		</div>
	</div>
	<div class="col-12">
		<div class="card">
			<div class="card-body">
				<h5 class="card-title">Suggested Titles</h5>
				<?php if (empty($recommendations)): ?>
					<p class="text-muted mb-0">Issue a couple of books to unlock personalised suggestions.</p>
				<?php else: ?>
					<div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-3">
						<?php foreach ($recommendations as $book): ?>
						<div class="col">
							<div class="card shadow-sm h-100">
								<div class="card-body">
									<h6 class="card-title mb-1"><?php echo h($book['title']); ?></h6>
									<p class="text-muted small mb-2"><?php echo h($book['author']); ?></p>
									<p class="small text-muted mb-2"><i class="bi bi-star-fill text-warning me-1"></i><?php echo number_format($book['avg_rating'], 1); ?> / 5</p>
									<a href="/book.php?id=<?php echo (int)$book['id']; ?>" class="btn btn-sm btn-outline-primary">Details</a>
								</div>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


