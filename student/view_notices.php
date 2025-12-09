<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');
include __DIR__ . '/../includes/header.php';

$rows = mysqli_query($conn, "SELECT title, description, organizer, event_date FROM notices WHERE status='approved' ORDER BY event_date DESC");
?>

<h3 class="mb-3">Approved Notices</h3>
<div class="table-responsive">
	<table class="table table-striped">
		<thead><tr><th>Title</th><th>Organizer</th><th>Event Date</th><th>Description</th></tr></thead>
		<tbody>
			<?php while ($n = mysqli_fetch_assoc($rows)): ?>
			<tr>
				<td><?php echo h($n['title']); ?></td>
				<td><?php echo h($n['organizer']); ?></td>
				<td><?php echo h($n['event_date']); ?></td>
				<td><?php echo nl2br(h($n['description'])); ?></td>
			</tr>
			<?php endwhile; ?>
		</tbody>
	</table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


