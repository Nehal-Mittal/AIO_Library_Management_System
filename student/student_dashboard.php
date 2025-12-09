<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');
include __DIR__ . '/../includes/header.php';

$uid = (int)$_SESSION['user']['id'];
$issued = mysqli_query($conn, "SELECT b.title, ib.issue_date, ib.due_date, ib.return_date, ib.fine_status FROM issued_books ib JOIN books b ON ib.book_id=b.id WHERE ib.user_id=$uid ORDER BY ib.issue_date DESC LIMIT 5");
$dueSoon = mysqli_query($conn, "SELECT b.title, ib.issue_date, ib.due_date FROM issued_books ib JOIN books b ON ib.book_id=b.id WHERE ib.user_id=$uid AND ib.return_date IS NULL");
$recommendations = recommended_books($conn, $uid, 10);
$trendingBooks = get_trending_books($conn, 4);
$similarUserBooks = get_books_from_similar_users($conn, $uid, 4);
$notices = mysqli_query($conn, "SELECT title, organizer, event_date FROM notices WHERE status='approved' ORDER BY event_date DESC LIMIT 5");

// Create funny notification if we have new recommendations (only once per day)
$lastNotifCheck = $_SESSION['last_recommendation_notif'] ?? null;
$today = date('Y-m-d');
if ($lastNotifCheck !== $today && !empty($recommendations)) {
	notify_new_recommendations($conn, $uid, count($recommendations));
	$_SESSION['last_recommendation_notif'] = $today;
}
?>

<h3 class="mb-3">Student Dashboard</h3>

<?php
// Alerts for due in <=2 days and overdue
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
					<table class="table table-striped"><thead><tr><th>Title</th><th>Issued</th><th>Due Date</th><th>Return Date</th><th>Fine</th><th>Status</th></tr></thead><tbody>
					<?php 
					// Reset result pointer
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
					</tbody></table>
				</div>
				<a href="/student/my_books.php" class="btn btn-outline-primary btn-sm">View all</a>
			</div>
		</div>
	</div>
	<div class="col-lg-6">
		<div class="card">
			<div class="card-body">
				<h5 class="card-title">Latest Notices</h5>
				<div class="table-responsive">
					<table class="table table-striped"><thead><tr><th>Title</th><th>Organizer</th><th>Date</th></tr></thead><tbody>
					<?php while ($n = mysqli_fetch_assoc($notices)): ?>
					<tr><td><?php echo h($n['title']); ?></td><td><?php echo h($n['organizer']); ?></td><td><?php echo h($n['event_date']); ?></td></tr>
					<?php endwhile; ?>
					</tbody></table>
				</div>
				<a href="/student/view_notices.php" class="btn btn-outline-secondary btn-sm">View all</a>
			</div>
		</div>
	</div>
	<!-- Recommended For You Section -->
	<div class="col-12">
		<div class="card mt-3 shadow-sm">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h5 class="card-title mb-0"><i class="bi bi-stars me-2 text-warning"></i>Recommended For You</h5>
					<small class="text-muted">Powered by AI & your reading history</small>
				</div>
				<?php if (empty($recommendations)): ?>
					<div class="text-center py-4">
						<i class="bi bi-book display-4 text-muted mb-3"></i>
						<p class="text-muted mb-0">Borrow a few books to see smart recommendations based on your favourite categories.</p>
					</div>
				<?php else: ?>
					<div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-3">
						<?php foreach (array_slice($recommendations, 0, 8) as $book): ?>
							<div class="col">
								<div class="card h-100 shadow-sm border-0">
									<?php if (!empty($book['cover_image'])): ?>
										<img src="<?php echo h($book['cover_image']); ?>" class="card-img-top" alt="<?php echo h($book['title']); ?>" style="height:200px;object-fit:cover;">
									<?php else: ?>
										<div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:200px;">
											<i class="bi bi-book display-4 text-muted"></i>
										</div>
									<?php endif; ?>
									<div class="card-body d-flex flex-column">
										<h6 class="card-title mb-1"><?php echo h($book['title']); ?></h6>
										<p class="text-muted small mb-2"><?php echo h($book['author']); ?></p>
										<?php if (!empty($book['category'])): ?>
											<span class="badge text-bg-secondary mb-2"><?php echo h($book['category']); ?></span>
										<?php endif; ?>
										<div class="mb-2">
											<?php if ($book['avg_rating'] > 0): ?>
												<i class="bi bi-star-fill text-warning me-1"></i>
												<small class="text-muted"><?php echo number_format($book['avg_rating'], 1); ?> (<?php echo (int)$book['review_count']; ?> reviews)</small>
											<?php else: ?>
												<small class="text-muted">No ratings yet</small>
											<?php endif; ?>
										</div>
										<?php if (!empty($book['description'])): ?>
											<p class="small text-muted mb-2 flex-grow-1"><?php echo h(mb_substr($book['description'], 0, 100)) . (mb_strlen($book['description']) > 100 ? '...' : ''); ?></p>
										<?php endif; ?>
										<a href="/book.php?id=<?php echo (int)$book['id']; ?>" class="btn btn-sm btn-primary mt-auto">View Details</a>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	
	<!-- Trending Books Section -->
	<?php if (!empty($trendingBooks)): ?>
	<div class="col-12">
		<div class="card mt-3 shadow-sm">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h5 class="card-title mb-0"><i class="bi bi-fire me-2 text-danger"></i>Trending Books</h5>
					<small class="text-muted">Most popular in the last 30 days</small>
				</div>
				<div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-3">
					<?php foreach ($trendingBooks as $book): ?>
						<div class="col">
							<div class="card h-100 shadow-sm border-0">
								<?php if (!empty($book['cover_image'])): ?>
									<img src="<?php echo h($book['cover_image']); ?>" class="card-img-top" alt="<?php echo h($book['title']); ?>" style="height:200px;object-fit:cover;">
								<?php else: ?>
									<div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:200px;">
										<i class="bi bi-book display-4 text-muted"></i>
									</div>
								<?php endif; ?>
								<div class="card-body d-flex flex-column">
									<h6 class="card-title mb-1"><?php echo h($book['title']); ?></h6>
									<p class="text-muted small mb-2"><?php echo h($book['author']); ?></p>
									<div class="mb-2">
										<span class="badge text-bg-danger me-1">🔥 Trending</span>
										<?php if ($book['issue_count'] > 0): ?>
											<small class="text-muted"><?php echo (int)$book['issue_count']; ?> issues this month</small>
										<?php endif; ?>
									</div>
									<a href="/book.php?id=<?php echo (int)$book['id']; ?>" class="btn btn-sm btn-outline-danger mt-auto">View Details</a>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>
	
	<!-- People Like You Also Read Section -->
	<?php if (!empty($similarUserBooks)): ?>
	<div class="col-12">
		<div class="card mt-3 shadow-sm">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h5 class="card-title mb-0"><i class="bi bi-people me-2 text-info"></i>People Like You Also Read</h5>
					<small class="text-muted">Based on collaborative filtering</small>
				</div>
				<div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-3">
					<?php foreach ($similarUserBooks as $book): ?>
						<div class="col">
							<div class="card h-100 shadow-sm border-0">
								<?php if (!empty($book['cover_image'])): ?>
									<img src="<?php echo h($book['cover_image']); ?>" class="card-img-top" alt="<?php echo h($book['title']); ?>" style="height:200px;object-fit:cover;">
								<?php else: ?>
									<div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:200px;">
										<i class="bi bi-book display-4 text-muted"></i>
									</div>
								<?php endif; ?>
								<div class="card-body d-flex flex-column">
									<h6 class="card-title mb-1"><?php echo h($book['title']); ?></h6>
									<p class="text-muted small mb-2"><?php echo h($book['author']); ?></p>
									<div class="mb-2">
										<span class="badge text-bg-info me-1">👥 Popular</span>
										<?php if ($book['avg_rating'] > 0): ?>
											<small class="text-muted"><i class="bi bi-star-fill text-warning me-1"></i><?php echo number_format($book['avg_rating'], 1); ?></small>
										<?php endif; ?>
									</div>
									<a href="/book.php?id=<?php echo (int)$book['id']; ?>" class="btn btn-sm btn-outline-info mt-auto">View Details</a>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


