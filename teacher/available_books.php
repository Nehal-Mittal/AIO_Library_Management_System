<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');
include __DIR__ . '/../includes/header.php';

$dept = trim($_GET['department'] ?? '');
$query = trim($_GET['q'] ?? '');
$departments = mysqli_query($conn, "SELECT name FROM departments ORDER BY name");

// Log search if user is logged in and has a query
if (!empty($query) && isset($_SESSION['user']['id'])) {
	log_search($conn, (int)$_SESSION['user']['id'], $query);
}

$conditions = ["b.available_copies > 0"];
if ($dept !== '') {
	$deptEsc = mysqli_real_escape_string($conn, $dept);
	$conditions[] = "b.department = '{$deptEsc}'";
}
if ($query !== '') {
	$qEsc = mysqli_real_escape_string($conn, '%' . $query . '%');
	$conditions[] = "(b.title LIKE '{$qEsc}' OR b.author LIKE '{$qEsc}')";
}
$where = implode(' AND ', $conditions);
$books = mysqli_query($conn, "SELECT b.*, COALESCE(AVG(br.rating),0) AS avg_rating, COUNT(br.id) AS review_count
	FROM books b
	LEFT JOIN book_reviews br ON br.book_id=b.id
	WHERE {$where}
	GROUP BY b.id
	ORDER BY b.title");
?>

<h3 class="mb-3">Available Books</h3>

<form class="row g-2 mb-4" method="get">
	<div class="col-md-4">
		<input type="text" name="q" class="form-control" placeholder="Search title or author" value="<?php echo h($query); ?>">
	</div>
	<div class="col-md-3">
		<select name="department" class="form-select" onchange="this.form.submit()">
			<option value="">All Departments</option>
			<?php while ($d = mysqli_fetch_assoc($departments)): ?>
				<option value="<?php echo h($d['name']); ?>" <?php echo $dept===$d['name']?'selected':''; ?>><?php echo h($d['name']); ?></option>
			<?php endwhile; ?>
		</select>
	</div>
	<div class="col-md-2">
		<button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Search</button>
	</div>
</form>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
	<?php if (mysqli_num_rows($books) === 0): ?>
		<div class="col"><div class="alert alert-info">No books found.</div></div>
	<?php endif; ?>
	<?php while ($book = mysqli_fetch_assoc($books)): ?>
	<div class="col">
		<div class="card h-100 shadow-sm">
			<?php if (!empty($book['cover_image'])): ?>
				<img src="<?php echo h($book['cover_image']); ?>" class="card-img-top" alt="<?php echo h($book['title']); ?>" style="height:180px;object-fit:cover;">
			<?php endif; ?>
			<div class="card-body d-flex flex-column">
				<h5 class="card-title"><?php echo h($book['title']); ?></h5>
				<p class="text-muted mb-1"><?php echo h($book['author']); ?></p>
				<p class="small text-muted mb-2"><?php echo h($book['department'] ?: 'General'); ?></p>
				<p class="small text-muted mb-3"><i class="bi bi-star-fill text-warning me-1"></i><?php echo number_format($book['avg_rating'], 1); ?> (<?php echo (int)$book['review_count']; ?>)</p>
				<div class="mt-auto d-flex gap-2">
					<a href="/book.php?id=<?php echo (int)$book['id']; ?>" class="btn btn-outline-primary btn-sm">Details</a>
					<button type="button" 
							class="btn btn-sm btn-primary" 
							data-book-request 
							data-book-id="<?php echo (int)$book['id']; ?>"
							data-book-title="<?php echo h($book['title']); ?>">
						<i class="bi bi-bookmark-plus me-1"></i>Request
					</button>
				</div>
			</div>
		</div>
	</div>
	<?php endwhile; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
