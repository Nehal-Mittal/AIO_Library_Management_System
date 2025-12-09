<?php
require_once __DIR__ . '/../config.php';
include __DIR__ . '/../includes/header.php';

$rows = mysqli_query($conn, "SELECT title, description, organizer, event_date FROM notices WHERE status='approved' ORDER BY event_date DESC");
?>

<h3 class="mb-3">Notice Board</h3>

<div class="row g-3">
	<?php while ($n = mysqli_fetch_assoc($rows)): ?>
	<div class="col-md-6">
		<div class="card h-100">
			<div class="card-body">
				<h5 class="card-title mb-1"><?php echo h($n['title']); ?></h5>
				<div class="text-muted mb-2">Organizer: <?php echo h($n['organizer']); ?> | Date: <?php echo h($n['event_date']); ?></div>
				<p class="card-text"><?php echo nl2br(h($n['description'])); ?></p>
			</div>
		</div>
	</div>
	<?php endwhile; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


