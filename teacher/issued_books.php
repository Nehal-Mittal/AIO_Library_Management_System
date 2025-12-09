<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');
include __DIR__ . '/../includes/header.php';

$uid = (int)$_SESSION['user']['id'];
$rows = mysqli_query($conn, "SELECT b.title, ib.issue_date, ib.return_date, ib.fine_status FROM issued_books ib JOIN books b ON ib.book_id=b.id WHERE ib.user_id=$uid ORDER BY ib.issue_date DESC");
?>

<h3 class="mb-3">My Issued Books</h3>
<div class="table-responsive">
    <table class="table table-striped">
        <thead><tr><th>Title</th><th>Issue Date</th><th>Return Date</th><th>Fine</th><th>Status</th></tr></thead>
		<tbody>
            <?php while ($r = mysqli_fetch_assoc($rows)): ?>
            <?php $calcFine = compute_fine($r['issue_date'], $r['return_date']); ?>
            <tr>
                <td><?php echo h($r['title']); ?></td>
                <td><?php echo h($r['issue_date']); ?></td>
                <td><?php echo h($r['return_date']); ?></td>
                <td><?php echo number_format($calcFine, 2); ?></td>
                <td><span class="badge text-bg-<?php echo ($r['fine_status']==='paid'?'success':'secondary'); ?>"><?php echo h($r['fine_status'] ? $r['fine_status'] : 'unpaid'); ?></span></td>
            </tr>
            <?php endwhile; ?>
		</tbody>
	</table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


