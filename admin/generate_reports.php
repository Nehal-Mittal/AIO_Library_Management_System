<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
include __DIR__ . '/../includes/header.php';

// Basic reports via tabs
$students = mysqli_query($conn, "SELECT u.id, u.name, u.email, COUNT(ib.id) issued_count FROM users u LEFT JOIN issued_books ib ON ib.user_id=u.id WHERE u.role='student' GROUP BY u.id ORDER BY u.name");
$teachers = mysqli_query($conn, "SELECT u.id, u.name, u.email, COUNT(ib.id) issued_count FROM users u LEFT JOIN issued_books ib ON ib.user_id=u.id WHERE u.role='teacher' GROUP BY u.id ORDER BY u.name");
$books = mysqli_query($conn, "SELECT title, author, department, status FROM books ORDER BY title");
$issues = mysqli_query($conn, "SELECT ib.id, b.title, u.name, ib.issue_date, ib.return_date, ib.fine FROM issued_books ib JOIN books b ON ib.book_id=b.id JOIN users u ON ib.user_id=u.id ORDER BY ib.issue_date DESC");
$fines = mysqli_query($conn, "SELECT ib.id, b.title, u.name, ib.return_date, ib.fine FROM issued_books ib JOIN books b ON ib.book_id=b.id JOIN users u ON ib.user_id=u.id WHERE ib.fine>0 ORDER BY ib.return_date DESC");
?>

<h3 class="mb-3">Reports</h3>

<ul class="nav nav-tabs" id="reportTabs" role="tablist">
	<li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab">Students</button></li>
	<li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#teachers" type="button" role="tab">Teachers</button></li>
	<li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#books" type="button" role="tab">Books</button></li>
	<li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#issues" type="button" role="tab">Issues</button></li>
	<li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#fines" type="button" role="tab">Fines</button></li>
</ul>
<div class="tab-content border border-top-0 p-3 bg-white">
	<div class="tab-pane fade show active" id="students" role="tabpanel">
		<div class="table-responsive"><table class="table table-striped"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Issued</th></tr></thead><tbody>
		<?php while ($r = mysqli_fetch_assoc($students)): ?>
		<tr><td><?php echo (int)$r['id']; ?></td><td><?php echo h($r['name']); ?></td><td><?php echo h($r['email']); ?></td><td><?php echo (int)$r['issued_count']; ?></td></tr>
		<?php endwhile; ?>
		</tbody></table></div>
	</div>
	<div class="tab-pane fade" id="teachers" role="tabpanel">
		<div class="table-responsive"><table class="table table-striped"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Issued</th></tr></thead><tbody>
		<?php while ($r = mysqli_fetch_assoc($teachers)): ?>
		<tr><td><?php echo (int)$r['id']; ?></td><td><?php echo h($r['name']); ?></td><td><?php echo h($r['email']); ?></td><td><?php echo (int)$r['issued_count']; ?></td></tr>
		<?php endwhile; ?>
		</tbody></table></div>
	</div>
	<div class="tab-pane fade" id="books" role="tabpanel">
		<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Title</th><th>Author</th><th>Department</th><th>Status</th></tr></thead><tbody>
		<?php while ($r = mysqli_fetch_assoc($books)): ?>
		<tr><td><?php echo h($r['title']); ?></td><td><?php echo h($r['author']); ?></td><td><?php echo h($r['department']); ?></td><td><?php echo h($r['status']); ?></td></tr>
		<?php endwhile; ?>
		</tbody></table></div>
	</div>
	<div class="tab-pane fade" id="issues" role="tabpanel">
		<div class="table-responsive"><table class="table table-striped"><thead><tr><th>ID</th><th>Book</th><th>User</th><th>Issued</th><th>Returned</th><th>Fine</th></tr></thead><tbody>
		<?php while ($r = mysqli_fetch_assoc($issues)): ?>
		<tr><td><?php echo (int)$r['id']; ?></td><td><?php echo h($r['title']); ?></td><td><?php echo h($r['name']); ?></td><td><?php echo h($r['issue_date']); ?></td><td><?php echo h($r['return_date']); ?></td><td><?php echo h($r['fine']); ?></td></tr>
		<?php endwhile; ?>
		</tbody></table></div>
	</div>
	<div class="tab-pane fade" id="fines" role="tabpanel">
		<div class="table-responsive"><table class="table table-striped"><thead><tr><th>ID</th><th>Book</th><th>User</th><th>Returned</th><th>Fine</th></tr></thead><tbody>
		<?php while ($r = mysqli_fetch_assoc($fines)): ?>
		<tr><td><?php echo (int)$r['id']; ?></td><td><?php echo h($r['title']); ?></td><td><?php echo h($r['name']); ?></td><td><?php echo h($r['return_date']); ?></td><td><?php echo h($r['fine']); ?></td></tr>
		<?php endwhile; ?>
		</tbody></table></div>
	</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


