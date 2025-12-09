<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = (int)$_SESSION['user']['id'];

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$_SESSION['flash_error'] = 'Invalid request token.';
	} else {
		$action = $_POST['action'] ?? '';
		if ($action === 'mark_read') {
			$notificationId = (int)($_POST['id'] ?? 0);
			if ($notificationId > 0) {
				mark_notification_read($conn, $notificationId, $userId);
			}
		} elseif ($action === 'mark_all_read') {
			mark_all_notifications_read($conn, $userId);
			$_SESSION['flash_success'] = 'All notifications marked as read.';
		}
	}
	header('Location: /notifications.php');
	exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$notifications = get_notifications($conn, $userId, $limit, false);
$unreadCount = get_unread_notification_count($conn, $userId);

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
	<h3>Notifications</h3>
	<?php if ($unreadCount > 0): ?>
		<form method="post" class="d-inline">
			<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
			<input type="hidden" name="action" value="mark_all_read">
			<button type="submit" class="btn btn-sm btn-outline-primary">Mark All as Read</button>
		</form>
	<?php endif; ?>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
	<div class="alert alert-danger alert-dismissible fade show" role="alert">
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

<?php if (empty($notifications)): ?>
	<div class="alert alert-info">
		<i class="bi bi-info-circle me-2"></i>No notifications yet. Check back later!
	</div>
<?php else: ?>
	<div class="list-group">
		<?php foreach ($notifications as $notif): ?>
			<div class="list-group-item <?php echo $notif['is_read'] ? '' : 'notification-unread'; ?> notification-item notification-type-<?php echo h($notif['type']); ?>" data-id="<?php echo (int)$notif['id']; ?>">
				<div class="d-flex justify-content-between align-items-start">
					<div class="flex-grow-1">
						<div class="d-flex align-items-center mb-2">
							<span class="badge text-bg-<?php 
								echo match($notif['type']) {
									'success' => 'success',
									'warning' => 'warning',
									'danger' => 'danger',
									'funny' => 'info',
									default => 'info'
								};
							?> me-2"><?php echo h(ucfirst($notif['type'])); ?></span>
							<strong class="<?php echo $notif['is_read'] ? '' : 'fw-bold'; ?>"><?php echo h($notif['title']); ?></strong>
							<?php if (!$notif['is_read']): ?>
								<span class="badge bg-danger ms-2">New</span>
							<?php endif; ?>
						</div>
						<p class="mb-1"><?php echo nl2br(h($notif['message'])); ?></p>
						<small class="text-muted">
							<i class="bi bi-clock me-1"></i><?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
						</small>
					</div>
					<?php if (!$notif['is_read']): ?>
						<form method="post" class="ms-3">
							<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
							<input type="hidden" name="action" value="mark_read">
							<input type="hidden" name="id" value="<?php echo (int)$notif['id']; ?>">
							<button type="submit" class="btn btn-sm btn-outline-secondary" title="Mark as read">
								<i class="bi bi-check"></i>
							</button>
						</form>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
