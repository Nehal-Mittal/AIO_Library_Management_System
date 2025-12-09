<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
include __DIR__ . '/includes/header.php';

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['csrf_token'] ?? '';
	if (!validate_csrf($token)) {
		$err = 'Invalid request. Please try again.';
	} else {
		$old = isset($_POST['old_password']) ? (string)$_POST['old_password'] : '';
		$new = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
		$confirm = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';
		$uid = (int)$_SESSION['user']['id'];

		if ($old === '' || $new === '' || $confirm === '') {
			$err = 'All fields are required.';
		} elseif ($new !== $confirm) {
			$err = 'New password and confirmation do not match.';
		} elseif (strlen($new) < 6) {
			$err = 'New password must be at least 6 characters.';
		} else {
			$stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
			mysqli_stmt_bind_param($stmt, 'i', $uid);
			mysqli_stmt_execute($stmt);
			$res = mysqli_stmt_get_result($stmt);
			$row = mysqli_fetch_assoc($res);
			mysqli_stmt_close($stmt);
			if (!$row || !password_verify($old, $row['password'])) {
				$err = 'Old password is incorrect.';
			} else {
				$newHash = password_hash($new, PASSWORD_DEFAULT);
				$upd = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
				mysqli_stmt_bind_param($upd, 'si', $newHash, $uid);
				if (mysqli_stmt_execute($upd)) {
					$msg = 'Password changed successfully.';
				} else {
					$err = 'Failed to change password. Please try again.';
				}
				mysqli_stmt_close($upd);
			}
		}
	}
}
?>

<h3 class="mb-3">Change Password</h3>

<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
	<?php echo h($err); ?>
	<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
	<?php echo h($msg); ?>
	<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<form method="post" autocomplete="off" class="col-md-6 col-lg-5">
	<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
	<div class="mb-3">
		<label class="form-label">Old Password</label>
		<input type="password" name="old_password" class="form-control" required>
	</div>
	<div class="mb-3">
		<label class="form-label">New Password</label>
		<input type="password" name="new_password" class="form-control" minlength="6" required>
	</div>
	<div class="mb-3">
		<label class="form-label">Confirm New Password</label>
		<input type="password" name="confirm_password" class="form-control" minlength="6" required>
	</div>
	<button type="submit" class="btn btn-primary">Update Password</button>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>



